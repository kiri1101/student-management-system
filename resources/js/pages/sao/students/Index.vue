<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import { ref } from 'vue';
import sao from '@/routes/sao';

type StudentRow = {
    id: number;
    matricule: string | null;
    name: string | null;
    programme: string | null;
    level: number | null;
    status: string;
};

type Paginator<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    students: Paginator<StudentRow>;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Students', href: sao.students.index() }],
    },
});

const search = ref(props.filters.search);

function runSearch(): void {
    router.get(
        sao.students.index().url,
        { search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function transcriptUrl(id: number): string {
    return sao.students.transcript(id).url;
}

function goToPage(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Students" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <section>
            <p class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400">
                Student affairs
            </p>
            <h1 class="mt-1 flex items-center gap-2 text-2xl font-bold tracking-tight">
                <Users class="size-6" /> Students
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Look up a student and download their official academic transcript.
            </p>
        </section>

        <form class="flex items-center gap-2" @submit.prevent="runSearch">
            <InputText
                v-model="search"
                placeholder="Search by matricule or name"
                class="w-full max-w-sm"
            />
            <Button type="submit" label="Search" />
        </form>

        <DataTable :value="students.data" data-key="id" size="small" class="text-sm">
            <Column field="matricule" header="Matricule">
                <template #body="{ data }">
                    <span class="font-mono">{{ data.matricule ?? '—' }}</span>
                </template>
            </Column>
            <Column field="name" header="Name">
                <template #body="{ data }">{{ data.name ?? '—' }}</template>
            </Column>
            <Column field="programme" header="Programme">
                <template #body="{ data }">{{ data.programme ?? '—' }}</template>
            </Column>
            <Column field="level" header="Level">
                <template #body="{ data }">{{ data.level ?? '—' }}</template>
            </Column>
            <Column field="status" header="Status">
                <template #body="{ data }"><span class="capitalize">{{ data.status }}</span></template>
            </Column>
            <Column header="Transcript">
                <template #body="{ data }">
                    <a :href="transcriptUrl(data.id)">
                        <Button label="Download" size="small" severity="secondary" />
                    </a>
                </template>
            </Column>
            <template #empty>
                <div class="p-6 text-center text-muted-foreground">No students found.</div>
            </template>
        </DataTable>

        <div v-if="students.total > 0" class="flex flex-wrap items-center gap-1">
            <template v-for="(link, i) in students.links" :key="i">
                <button
                    v-if="link.url"
                    class="rounded px-3 py-1 text-sm"
                    :class="link.active ? 'bg-primary text-primary-contrast' : 'hover:bg-muted'"
                    @click="goToPage(link.url)"
                    v-html="link.label"
                />
                <span v-else class="px-3 py-1 text-sm text-muted-foreground" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
