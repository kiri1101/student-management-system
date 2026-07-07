<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import type { DataTablePageEvent } from 'primevue/datatable';
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

type Paginator = {
    data: StudentRow[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
};

const props = defineProps<{
    students: Paginator;
    filters: { search: string; rows: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Students', href: sao.students.index() }],
    },
});

const search = ref(props.filters.search);

function reload(overrides: Record<string, FormDataConvertible> = {}): void {
    const data: Record<string, FormDataConvertible> = {
        search: search.value,
        rows: props.filters.rows,
        page: props.students.current_page,
        ...overrides,
    };

    router.get(sao.students.index().url, data, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function runSearch(): void {
    reload({ page: 1 });
}

function onPage(event: DataTablePageEvent): void {
    reload({ page: event.page + 1, rows: event.rows });
}

function transcriptUrl(id: number): string {
    return sao.students.transcript(id).url;
}
</script>

<template>
    <Head title="Students" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Student affairs
                </p>
                <h1
                    class="mt-1 flex items-center gap-2 text-2xl font-bold tracking-tight"
                >
                    <Users class="size-6" /> Students
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Look up a student and download their official academic
                    transcript.
                </p>
            </div>
            <form
                class="flex w-full items-center gap-2 sm:w-auto"
                @submit.prevent="runSearch"
            >
                <InputText
                    v-model="search"
                    placeholder="Search by matricule or name"
                    class="w-full sm:w-72"
                />
                <Button type="submit" label="Search" />
            </form>
        </section>

        <Card>
            <template #content>
                <DataTable
                    :value="students.data"
                    data-key="id"
                    striped-rows
                    lazy
                    paginator
                    :rows="students.per_page"
                    :total-records="students.total"
                    :first="(students.current_page - 1) * students.per_page"
                    :rows-per-page-options="[15, 25, 50]"
                    responsive-layout="scroll"
                    @page="onPage"
                >
                    <Column field="matricule" header="Matricule">
                        <template #body="{ data }">
                            <span class="font-mono">{{
                                data.matricule ?? '—'
                            }}</span>
                        </template>
                    </Column>
                    <Column field="name" header="Name">
                        <template #body="{ data }">{{
                            data.name ?? '—'
                        }}</template>
                    </Column>
                    <Column field="programme" header="Programme">
                        <template #body="{ data }">{{
                            data.programme ?? '—'
                        }}</template>
                    </Column>
                    <Column field="level" header="Level" style="width: 6rem">
                        <template #body="{ data }">{{
                            data.level ?? '—'
                        }}</template>
                    </Column>
                    <Column field="status" header="Status" style="width: 9rem">
                        <template #body="{ data }"
                            ><span class="capitalize">{{
                                data.status
                            }}</span></template
                        >
                    </Column>
                    <Column header="Transcript" style="width: 9rem">
                        <template #body="{ data }">
                            <a :href="transcriptUrl(data.id)">
                                <Button
                                    label="Download"
                                    size="small"
                                    severity="secondary"
                                />
                            </a>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No students found.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
