<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { ref, watch } from 'vue';
import { resultStatusLabel, resultStatusSeverity } from '@/lib/statusDisplay';
import lecturer from '@/routes/lecturer';

type Course = {
    id: number;
    code: string;
    title: string;
    plan_status: string;
};

type ResultRow = {
    student_profile_id: number;
    student_name: string | null;
    matricule: string | null;
    ca_score: number | null;
    exam_score: number | null;
    final_score: number | null;
    grade: string | null;
    status: string;
};

const props = defineProps<{
    course: Course;
    results: ResultRow[];
    draft_count: number;
    published_count: number;
    publishable_count: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lecturer Dashboard', href: lecturer.dashboard() },
            { title: 'Courses', href: lecturer.courses.index() },
            { title: 'Results', href: lecturer.courses.index() },
        ],
    },
});

const rows = ref<ResultRow[]>([]);

function syncRows(): void {
    rows.value = props.results.map((row) => ({ ...row }));
}

syncRows();
watch(() => props.results, syncRows, { deep: true });

const saving = ref(false);

function saveResults(): void {
    saving.value = true;

    router.post(
        lecturer.courses.results.store(props.course.id).url,
        {
            results: rows.value.map((row) => ({
                student_profile_id: row.student_profile_id,
                ca_score: row.ca_score,
                exam_score: row.exam_score,
            })),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Lecturer · Results" />

    <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
        <Link :href="lecturer.courses.index().url" class="inline-block">
            <Button
                label="Back to courses"
                severity="secondary"
                text
                size="small"
            >
                <template #icon>
                    <ArrowLeft class="size-4" />
                </template>
            </Button>
        </Link>

        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Results
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    {{ course.code }} · {{ course.title }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ draft_count }} draft · {{ published_count }} published ·
                    {{ publishable_count }} ready to publish
                </p>
            </div>
            <Button
                label="Save results"
                :loading="saving"
                :disabled="rows.length === 0"
                @click="saveResults"
            />
        </section>

        <Card>
            <template #content>
                <Message severity="info" :closable="false" class="mb-4">
                    Enter CA and exam scores, then save. Fully-scored results
                    are published to students by the Student Affairs Officer.
                    Published rows are locked.
                </Message>

                <DataTable
                    :value="rows"
                    data-key="student_profile_id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column header="Student">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-medium">
                                    {{ data.student_name ?? '—' }}
                                </span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ data.matricule ?? '—' }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="CA" style="width: 9rem">
                        <template #body="{ data }">
                            <InputNumber
                                v-model="data.ca_score"
                                :min="0"
                                :max="100"
                                :max-fraction-digits="0"
                                class="w-full"
                                :disabled="data.status === 'published'"
                            />
                        </template>
                    </Column>
                    <Column header="Exam" style="width: 9rem">
                        <template #body="{ data }">
                            <InputNumber
                                v-model="data.exam_score"
                                :min="0"
                                :max="100"
                                :max-fraction-digits="0"
                                class="w-full"
                                :disabled="data.status === 'published'"
                            />
                        </template>
                    </Column>
                    <Column header="Final" style="width: 7rem">
                        <template #body="{ data }">
                            <span v-if="data.final_score !== null">
                                {{ data.final_score }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </template>
                    </Column>
                    <Column header="Grade" style="width: 7rem">
                        <template #body="{ data }">
                            <span v-if="data.grade" class="font-medium">
                                {{ data.grade }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </template>
                    </Column>
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="resultStatusLabel(data.status)"
                                :severity="resultStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            This course has no enrolled students yet.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
