<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { ref } from 'vue';
import AssignmentController from '@/actions/App/Http/Controllers/Lecturer/AssignmentController';
import FileViewerDialog from '@/components/FileViewerDialog.vue';
import {
    assignmentSubmissionStatusLabel,
    assignmentSubmissionStatusSeverity,
} from '@/lib/statusDisplay';
import lecturer from '@/routes/lecturer';

type Course = {
    id: number;
    code: string;
    title: string;
};

type Assignment = {
    id: number;
    title: string;
    instructions: string;
    due_at: string;
    max_score: number;
};

type Submission = {
    id: number;
    student_profile_id: number;
    student_name: string | null;
    matricule: string | null;
    original_filename: string;
    mime_type: string;
    submitted_at: string;
    is_late: boolean;
    score: number | null;
    feedback: string | null;
    status: string;
    view_url: string;
    download_url: string;
};

const props = defineProps<{
    course: Course;
    assignment: Assignment;
    submissions: Submission[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lecturer Dashboard', href: lecturer.dashboard() },
            { title: 'Courses', href: lecturer.courses.index() },
            { title: 'Assignments', href: lecturer.courses.index() },
            { title: 'Submissions', href: lecturer.courses.index() },
        ],
    },
});

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString();
}

const viewerVisible = ref(false);
const viewerUrl = ref('');
const viewerDownloadUrl = ref('');
const viewerFilename = ref('');
const viewerMime = ref<string | undefined>(undefined);

function openViewer(submission: Submission): void {
    viewerUrl.value = submission.view_url;
    viewerDownloadUrl.value = submission.download_url;
    viewerFilename.value = submission.original_filename;
    viewerMime.value = submission.mime_type;
    viewerVisible.value = true;
}

const gradeForm = useForm<{
    score: number | null;
    feedback: string;
}>({
    score: null,
    feedback: '',
});
const gradeDialogVisible = ref(false);
const gradingSubmission = ref<Submission | null>(null);

function openGrade(submission: Submission): void {
    gradingSubmission.value = submission;
    gradeForm.clearErrors();
    gradeForm.score = submission.score;
    gradeForm.feedback = submission.feedback ?? '';
    gradeDialogVisible.value = true;
}

function submitGrade(): void {
    if (!gradingSubmission.value) {
        return;
    }

    gradeForm.post(
        AssignmentController.grade([
            props.course.id,
            props.assignment.id,
            gradingSubmission.value.id,
        ]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                gradeDialogVisible.value = false;
                gradingSubmission.value = null;
                gradeForm.reset();
            },
        },
    );
}
</script>

<template>
    <Head :title="`Submissions · ${assignment.title}`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <Link
            :href="lecturer.courses.assignments.index(course.id).url"
            class="inline-block"
        >
            <Button
                label="Back to assignments"
                severity="secondary"
                text
                size="small"
            >
                <template #icon>
                    <ArrowLeft class="size-4" />
                </template>
            </Button>
        </Link>

        <section>
            <p
                class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
            >
                Submissions
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">
                {{ course.code }} · {{ assignment.title }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Due {{ formatDateTime(assignment.due_at) }} · Max score
                {{ assignment.max_score }}
            </p>
        </section>

        <Card v-if="assignment.instructions">
            <template #content>
                <p class="text-sm whitespace-pre-line">
                    {{ assignment.instructions }}
                </p>
            </template>
        </Card>

        <Card>
            <template #content>
                <DataTable
                    :value="submissions"
                    data-key="id"
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
                    <Column header="Submitted">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <span>{{
                                    formatDateTime(data.submitted_at)
                                }}</span>
                                <Tag
                                    v-if="data.is_late"
                                    severity="warn"
                                    value="Late"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column header="File" style="width: 8rem">
                        <template #body="{ data }">
                            <Button
                                label="View"
                                severity="secondary"
                                text
                                size="small"
                                @click="openViewer(data)"
                            >
                                <template #icon>
                                    <Eye class="size-4" />
                                </template>
                            </Button>
                        </template>
                    </Column>
                    <Column header="Score" style="width: 8rem">
                        <template #body="{ data }">
                            <span v-if="data.score !== null">
                                {{ data.score }} / {{ assignment.max_score }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </template>
                    </Column>
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="
                                    assignmentSubmissionStatusLabel(data.status)
                                "
                                :severity="
                                    assignmentSubmissionStatusSeverity(
                                        data.status,
                                    )
                                "
                            />
                        </template>
                    </Column>
                    <Column header="" style="width: 8rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end">
                                <Button
                                    label="Grade"
                                    size="small"
                                    @click="openGrade(data)"
                                />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No submissions yet.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="gradeDialogVisible"
            header="Grade submission"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!gradeForm.processing"
            :closable="!gradeForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitGrade">
                <div class="space-y-1">
                    <label for="grade-score" class="text-sm font-medium">
                        Score (out of {{ assignment.max_score }})
                    </label>
                    <InputNumber
                        v-model="gradeForm.score"
                        input-id="grade-score"
                        :min="0"
                        :max="assignment.max_score"
                        class="w-full"
                        :invalid="!!gradeForm.errors.score"
                    />
                    <Message
                        v-if="gradeForm.errors.score"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ gradeForm.errors.score }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="grade-feedback" class="text-sm font-medium">
                        Feedback
                    </label>
                    <Textarea
                        id="grade-feedback"
                        v-model="gradeForm.feedback"
                        class="w-full"
                        rows="4"
                        auto-resize
                        :invalid="!!gradeForm.errors.feedback"
                    />
                    <Message
                        v-if="gradeForm.errors.feedback"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ gradeForm.errors.feedback }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="gradeForm.processing"
                        @click="gradeDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Save grade"
                        :loading="gradeForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <FileViewerDialog
            v-model:visible="viewerVisible"
            :view-url="viewerUrl"
            :download-url="viewerDownloadUrl"
            :filename="viewerFilename"
            :mime="viewerMime"
        />
    </div>
</template>
