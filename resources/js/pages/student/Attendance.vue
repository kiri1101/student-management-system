<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarCheck } from 'lucide-vue-next';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import {
    attendanceStatusLabel,
    attendanceStatusSeverity,
    sessionStatusLabel,
    sessionStatusSeverity,
} from '@/lib/statusDisplay';
import student from '@/routes/student';

type Session = {
    id: number;
    scheduled_for: string;
    topic: string;
    status: string;
    attendance_status: string | null;
};

type CourseRow = {
    id: number;
    code: string;
    title: string;
    academic_year: string;
    sessions: Session[];
};

defineProps<{ courses: CourseRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My attendance', href: student.attendance.index() },
        ],
    },
});

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="My attendance" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <section>
            <p
                class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
            >
                Student
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">
                My attendance
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Your session attendance across enrolled courses.
            </p>
        </section>

        <Card v-for="course in courses" :key="course.id">
            <template #title>
                <div class="flex items-center gap-2">
                    <CalendarCheck class="size-5" />
                    <span>{{ course.code }} — {{ course.title }}</span>
                    <span class="text-sm text-muted-foreground">
                        ({{ course.academic_year }})
                    </span>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="course.sessions"
                    data-key="id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column header="Scheduled">
                        <template #body="{ data }">
                            {{ formatDateTime(data.scheduled_for) }}
                        </template>
                    </Column>
                    <Column field="topic" header="Topic" />
                    <Column header="Session" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="sessionStatusLabel(data.status)"
                                :severity="sessionStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column header="My attendance" style="width: 10rem">
                        <template #body="{ data }">
                            <Tag
                                v-if="data.attendance_status"
                                :value="
                                    attendanceStatusLabel(
                                        data.attendance_status,
                                    )
                                "
                                :severity="
                                    attendanceStatusSeverity(
                                        data.attendance_status,
                                    )
                                "
                            />
                            <span v-else class="text-muted-foreground">—</span>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No sessions have been scheduled for this course yet.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Card v-if="courses.length === 0">
            <template #content>
                <div class="py-6 text-center text-sm text-muted-foreground">
                    You are not enrolled in any courses yet, so there is no
                    attendance to show.
                </div>
            </template>
        </Card>
    </div>
</template>
