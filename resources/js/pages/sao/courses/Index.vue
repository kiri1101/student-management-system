<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Plus, Send, UserCog, X } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { ref } from 'vue';
import CourseController from '@/actions/App/Http/Controllers/Sao/CourseController';
import {
    coursePlanStatusLabel,
    coursePlanStatusSeverity,
} from '@/lib/statusDisplay';
import sao from '@/routes/sao';

type CourseRow = {
    id: number;
    code: string;
    title: string;
    credits: number;
    semester: number;
    level: number;
    academic_year: string;
    plan_status: string;
    offering_label: string;
    lecturer_name: string | null;
};

type LecturerOption = { id: number; name: string };

const props = defineProps<{
    courses: CourseRow[];
    filters: { academic_year: string | null; plan_status: string | null };
    lecturers: LecturerOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'SAO Dashboard', href: sao.dashboard() },
            { title: 'Courses', href: sao.courses.index() },
        ],
    },
});

const assignDialogVisible = ref(false);
const rejectDialogVisible = ref(false);
const activeCourse = ref<CourseRow | null>(null);

const assignForm = useForm<{ lecturer_profile_id: number | null }>({
    lecturer_profile_id: null,
});
const rejectForm = useForm({ plan_review_notes: '' });
const approveForm = useForm({});

function openAssign(course: CourseRow): void {
    activeCourse.value = course;
    assignForm.reset();
    assignForm.clearErrors();
    assignDialogVisible.value = true;
}

function submitAssign(): void {
    if (!activeCourse.value) {
        return;
    }

    assignForm.post(
        CourseController.assignLecturer(activeCourse.value.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                assignDialogVisible.value = false;
            },
        },
    );
}

function approve(course: CourseRow): void {
    approveForm.post(CourseController.approve(course.id).url, {
        preserveScroll: true,
    });
}

function openReject(course: CourseRow): void {
    activeCourse.value = course;
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectDialogVisible.value = true;
}

function submitReject(): void {
    if (!activeCourse.value) {
        return;
    }

    rejectForm.post(CourseController.reject(activeCourse.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            rejectDialogVisible.value = false;
        },
    });
}

function goToEdit(course: CourseRow): void {
    router.visit(sao.courses.edit(course.id).url);
}

const publishDialogVisible = ref(false);
const publishForm = useForm({});

function openPublish(course: CourseRow): void {
    activeCourse.value = course;
    publishDialogVisible.value = true;
}

function submitPublish(): void {
    if (!activeCourse.value) {
        return;
    }

    publishForm.post(sao.courses.publishResults(activeCourse.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            publishDialogVisible.value = false;
        },
    });
}
</script>

<template>
    <Head title="SAO · Courses" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Student Affairs
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">Courses</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Plan the catalog, assign lecturers, and approve course
                    plans.
                </p>
            </div>
            <Link :href="sao.courses.create().url" class="inline-block">
                <Button label="New course">
                    <template #icon>
                        <Plus class="size-4" />
                    </template>
                </Button>
            </Link>
        </section>

        <Card>
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
                                <span class="font-medium">{{
                                    data.title
                                }}</span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ data.code }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column field="offering_label" header="Offering" />
                    <Column field="level" header="Level" style="width: 6rem">
                        <template #body="{ data }">L{{ data.level }}</template>
                    </Column>
                    <Column
                        field="academic_year"
                        header="Year"
                        style="width: 7rem"
                    />
                    <Column
                        field="credits"
                        header="Credits"
                        style="width: 6rem"
                    />
                    <Column field="semester" header="Sem." style="width: 6rem">
                        <template #body="{ data }"
                            >S{{ data.semester }}</template
                        >
                    </Column>
                    <Column header="Lecturer">
                        <template #body="{ data }">
                            <span
                                :class="
                                    data.lecturer_name
                                        ? ''
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ data.lecturer_name ?? 'Unassigned' }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Plan" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="coursePlanStatusLabel(data.plan_status)"
                                :severity="
                                    coursePlanStatusSeverity(data.plan_status)
                                "
                            />
                        </template>
                    </Column>
                    <Column header="" style="width: 22rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    label="Edit"
                                    severity="secondary"
                                    text
                                    size="small"
                                    @click="goToEdit(data)"
                                />
                                <Button
                                    label="Assign"
                                    severity="secondary"
                                    text
                                    size="small"
                                    @click="openAssign(data)"
                                >
                                    <template #icon>
                                        <UserCog class="size-4" />
                                    </template>
                                </Button>
                                <template
                                    v-if="data.plan_status === 'submitted'"
                                >
                                    <Button
                                        label="Approve"
                                        severity="success"
                                        text
                                        size="small"
                                        @click="approve(data)"
                                    >
                                        <template #icon>
                                            <Check class="size-4" />
                                        </template>
                                    </Button>
                                    <Button
                                        label="Reject"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="openReject(data)"
                                    >
                                        <template #icon>
                                            <X class="size-4" />
                                        </template>
                                    </Button>
                                </template>
                                <Button
                                    v-if="data.plan_status === 'approved'"
                                    label="Publish results"
                                    severity="success"
                                    text
                                    size="small"
                                    @click="openPublish(data)"
                                >
                                    <template #icon>
                                        <Send class="size-4" />
                                    </template>
                                </Button>
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No courses yet.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="assignDialogVisible"
            header="Assign lecturer"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!assignForm.processing"
            :closable="!assignForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitAssign">
                <p class="text-sm text-muted-foreground">
                    Assign a lecturer to
                    <span class="font-medium">{{ activeCourse?.code }}</span>
                    — {{ activeCourse?.title }}.
                </p>
                <div class="space-y-1">
                    <label for="assign-lecturer" class="text-sm font-medium">
                        Lecturer
                    </label>
                    <Select
                        id="assign-lecturer"
                        v-model="assignForm.lecturer_profile_id"
                        :options="props.lecturers"
                        option-label="name"
                        option-value="id"
                        placeholder="Select a lecturer"
                        class="w-full"
                        filter
                        :invalid="!!assignForm.errors.lecturer_profile_id"
                    />
                    <Message
                        v-if="assignForm.errors.lecturer_profile_id"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ assignForm.errors.lecturer_profile_id }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="assignForm.processing"
                        @click="assignDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Assign"
                        :loading="assignForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="rejectDialogVisible"
            header="Reject course plan"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!rejectForm.processing"
            :closable="!rejectForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitReject">
                <div class="space-y-1">
                    <label for="reject-notes" class="text-sm font-medium">
                        Review notes
                    </label>
                    <Textarea
                        id="reject-notes"
                        v-model="rejectForm.plan_review_notes"
                        rows="3"
                        class="w-full"
                        :invalid="!!rejectForm.errors.plan_review_notes"
                    />
                    <Message
                        v-if="rejectForm.errors.plan_review_notes"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ rejectForm.errors.plan_review_notes }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="rejectForm.processing"
                        @click="rejectDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Reject plan"
                        severity="danger"
                        :loading="rejectForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="publishDialogVisible"
            header="Publish results"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!publishForm.processing"
            :closable="!publishForm.processing"
        >
            <p class="text-sm text-muted-foreground">
                Publish all fully-scored results for
                <span class="font-medium">{{ activeCourse?.code }}</span>
                — {{ activeCourse?.title }}? Students will be able to view their
                published CA and exam results and raise disputes.
            </p>
            <div class="flex items-center justify-end gap-2 pt-4">
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    text
                    :disabled="publishForm.processing"
                    @click="publishDialogVisible = false"
                />
                <Button
                    type="button"
                    label="Publish results"
                    severity="success"
                    :loading="publishForm.processing"
                    @click="submitPublish"
                />
            </div>
        </Dialog>
    </div>
</template>
