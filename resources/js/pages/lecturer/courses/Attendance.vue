<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { reactive } from 'vue';
import CourseSessionController from '@/actions/App/Http/Controllers/Lecturer/CourseSessionController';
import {
    sessionStatusLabel,
    sessionStatusSeverity,
} from '@/lib/statusDisplay';
import lecturer from '@/routes/lecturer';

type Course = {
    id: number;
    code: string;
    title: string;
};

type Session = {
    id: number;
    scheduled_for: string;
    topic: string;
    status: string;
};

type StudentRow = {
    student_profile_id: number;
    name: string;
    matricule: string;
    status: string | null;
};

const props = defineProps<{
    course: Course;
    session: Session;
    students: StudentRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lecturer Dashboard', href: lecturer.dashboard() },
            { title: 'Courses', href: lecturer.courses.index() },
            { title: 'Sessions', href: lecturer.courses.index() },
            { title: 'Attendance', href: lecturer.courses.index() },
        ],
    },
});

const statusOptions = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'late', label: 'Late' },
    { value: 'excused', label: 'Excused' },
];

const statuses = reactive<Record<number, string>>(
    Object.fromEntries(
        props.students.map((s) => [
            s.student_profile_id,
            s.status ?? 'present',
        ]),
    ),
);

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString();
}

const form = useForm<{
    records: Array<{ student_profile_id: number; status: string }>;
}>({
    records: [],
});

function save(): void {
    form
        .transform(() => ({
            records: props.students.map((s) => ({
                student_profile_id: s.student_profile_id,
                status: statuses[s.student_profile_id],
            })),
        }))
        .post(
            CourseSessionController.markAttendance([
                props.course.id,
                props.session.id,
            ]).url,
            { preserveScroll: true },
        );
}
</script>

<template>
    <Head :title="`Attendance · ${course.code}`" />

    <div class="space-y-4 p-4">
        <Link :href="lecturer.courses.sessions.index(course.id).url">
            <Button
                label="Back to sessions"
                severity="secondary"
                text
                size="small"
            >
                <template #icon>
                    <ArrowLeft class="size-4" />
                </template>
            </Button>
        </Link>

        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <ClipboardList class="size-5" />
                        <span>{{ course.code }} — {{ session.topic }}</span>
                    </div>
                    <Tag
                        :value="sessionStatusLabel(session.status)"
                        :severity="sessionStatusSeverity(session.status)"
                    />
                </div>
            </template>
            <template #content>
                <p class="mb-4 text-sm text-muted-foreground">
                    {{ formatDateTime(session.scheduled_for) }}
                </p>

                <DataTable
                    :value="students"
                    data-key="student_profile_id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column header="Student">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{ data.name }}</span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ data.matricule }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Attendance" style="width: 14rem">
                        <template #body="{ data }">
                            <Select
                                v-model="statuses[data.student_profile_id]"
                                :options="statusOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                            />
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No students are enrolled in this course cohort yet.
                        </div>
                    </template>
                </DataTable>

                <div
                    v-if="students.length > 0"
                    class="flex items-center justify-end pt-4"
                >
                    <Button
                        label="Save attendance"
                        :loading="form.processing"
                        @click="save"
                    />
                </div>
            </template>
        </Card>
    </div>
</template>
