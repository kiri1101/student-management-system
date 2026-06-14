<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import {
    coursePlanStatusLabel,
    coursePlanStatusSeverity,
} from '@/lib/statusDisplay';
import lecturer from '@/routes/lecturer';

type CourseRow = {
    id: number;
    code: string;
    title: string;
    level: number;
    academic_year: string;
    semester: number;
    credits: number;
    plan_status: string;
    plan_review_notes: string | null;
};

defineProps<{ courses: CourseRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lecturer Dashboard', href: lecturer.dashboard() },
            { title: 'Courses', href: lecturer.courses.index() },
        ],
    },
});
</script>

<template>
    <Head title="Lecturer · Courses" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <BookOpen class="size-5" />
                    <span>My courses</span>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="courses"
                    data-key="id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column header="Course">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-medium">{{ data.title }}</span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ data.code }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column field="level" header="Level" style="width: 6rem">
                        <template #body="{ data }">L{{ data.level }}</template>
                    </Column>
                    <Column
                        field="academic_year"
                        header="Year"
                        style="width: 7rem"
                    />
                    <Column field="semester" header="Sem." style="width: 6rem">
                        <template #body="{ data }">S{{ data.semester }}</template>
                    </Column>
                    <Column
                        field="credits"
                        header="Credits"
                        style="width: 6rem"
                    />
                    <Column header="Plan">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-1">
                                <Tag
                                    :value="
                                        coursePlanStatusLabel(data.plan_status)
                                    "
                                    :severity="
                                        coursePlanStatusSeverity(
                                            data.plan_status,
                                        )
                                    "
                                    class="w-fit"
                                />
                                <span
                                    v-if="
                                        data.plan_status === 'Rejected' &&
                                        data.plan_review_notes
                                    "
                                    class="text-xs text-destructive"
                                >
                                    {{ data.plan_review_notes }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="" style="width: 8rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end">
                                <Link :href="lecturer.courses.edit(data.id).url">
                                    <Button
                                        label="Edit plan"
                                        severity="secondary"
                                        text
                                        size="small"
                                    />
                                </Link>
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            You have no assigned courses.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
