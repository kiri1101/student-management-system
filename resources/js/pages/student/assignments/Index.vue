<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, FileText, Upload } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import FileUpload from 'primevue/fileupload';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { ref } from 'vue';
import FileViewerDialog from '@/components/FileViewerDialog.vue';
import {
    assignmentSubmissionStatusLabel,
    assignmentSubmissionStatusSeverity,
} from '@/lib/statusDisplay';
import student from '@/routes/student';

type Submission = {
    original_filename: string;
    submitted_at: string;
    is_late: boolean;
    score: number | null;
    feedback: string | null;
    status: string;
    view_url: string;
    download_url: string;
};

type Assignment = {
    id: number;
    title: string;
    instructions: string;
    due_at: string;
    max_score: number;
    submission: Submission | null;
};

type CourseRow = {
    id: number;
    code: string;
    title: string;
    academic_year: string;
    assignments: Assignment[];
};

defineProps<{ courses: CourseRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My assignments', href: student.assignments.index() },
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

function openViewer(submission: Submission): void {
    viewerUrl.value = submission.view_url;
    viewerDownloadUrl.value = submission.download_url;
    viewerFilename.value = submission.original_filename;
    viewerVisible.value = true;
}

const uploadForm = useForm<{ file: File | null }>({
    file: null,
});
const uploadDialogVisible = ref(false);
const activeAssignmentId = ref<number | null>(null);

function openUpload(assignment: Assignment): void {
    activeAssignmentId.value = assignment.id;
    uploadForm.reset();
    uploadForm.clearErrors();
    uploadDialogVisible.value = true;
}

function onFileSelect(event: { files: File[] }): void {
    uploadForm.file = event.files?.[0] ?? null;
}

function onFileClear(): void {
    uploadForm.file = null;
}

function submitUpload(): void {
    if (activeAssignmentId.value === null) {
        return;
    }

    uploadForm.post(
        student.assignments.submit(activeAssignmentId.value).url,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadDialogVisible.value = false;
                activeAssignmentId.value = null;
                uploadForm.reset();
            },
        },
    );
}
</script>

<template>
    <Head title="My assignments" />

    <div class="space-y-4 p-4">
        <Card v-for="course in courses" :key="course.id">
            <template #title>
                <div class="flex items-center gap-2">
                    <FileText class="size-5" />
                    <span>{{ course.code }} — {{ course.title }}</span>
                    <span class="text-sm text-muted-foreground">
                        ({{ course.academic_year }})
                    </span>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="course.assignments"
                    data-key="id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column field="title" header="Title" />
                    <Column header="Instructions">
                        <template #body="{ data }">
                            <span class="line-clamp-2 text-sm">
                                {{ data.instructions }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Due" style="width: 12rem">
                        <template #body="{ data }">
                            {{ formatDateTime(data.due_at) }}
                        </template>
                    </Column>
                    <Column header="Your submission" style="width: 22rem">
                        <template #body="{ data }">
                            <div
                                v-if="data.submission"
                                class="flex flex-col gap-1"
                            >
                                <div class="flex items-center gap-2">
                                    <Tag
                                        :value="
                                            assignmentSubmissionStatusLabel(
                                                data.submission.status,
                                            )
                                        "
                                        :severity="
                                            assignmentSubmissionStatusSeverity(
                                                data.submission.status,
                                            )
                                        "
                                    />
                                    <Tag
                                        v-if="data.submission.is_late"
                                        severity="warn"
                                        value="Late"
                                    />
                                    <span
                                        v-if="
                                            data.submission.status === 'graded'
                                        "
                                        class="text-sm font-medium"
                                    >
                                        {{ data.submission.score }}/{{
                                            data.max_score
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        —
                                    </span>
                                </div>
                                <p
                                    v-if="
                                        data.submission.status === 'graded' &&
                                        data.submission.feedback
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ data.submission.feedback }}
                                </p>
                                <div class="flex items-center gap-1">
                                    <Button
                                        label="View"
                                        severity="secondary"
                                        text
                                        size="small"
                                        @click="openViewer(data.submission)"
                                    >
                                        <template #icon>
                                            <Eye class="size-4" />
                                        </template>
                                    </Button>
                                    <Button
                                        label="Replace"
                                        severity="secondary"
                                        text
                                        size="small"
                                        @click="openUpload(data)"
                                    >
                                        <template #icon>
                                            <Upload class="size-4" />
                                        </template>
                                    </Button>
                                </div>
                            </div>
                            <Button
                                v-else
                                label="Submit"
                                size="small"
                                @click="openUpload(data)"
                            >
                                <template #icon>
                                    <Upload class="size-4" />
                                </template>
                            </Button>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No assignments have been set for this course yet.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Card v-if="courses.length === 0">
            <template #content>
                <div class="py-6 text-center text-sm text-muted-foreground">
                    You are not enrolled in any courses yet, so there are no
                    assignments to show.
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="uploadDialogVisible"
            header="Submit assignment"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!uploadForm.processing"
            :closable="!uploadForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitUpload">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Your file</label>
                    <FileUpload
                        mode="basic"
                        :auto="false"
                        custom-upload
                        choose-label="Choose file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        @select="onFileSelect"
                        @clear="onFileClear"
                    />
                    <p class="text-xs text-muted-foreground">
                        Accepted: PDF, JPG, PNG — max 8 MB.
                    </p>
                    <Message
                        v-if="uploadForm.errors.file"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ uploadForm.errors.file }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="uploadForm.processing"
                        @click="uploadDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Submit"
                        :loading="uploadForm.processing"
                        :disabled="!uploadForm.file"
                    />
                </div>
            </form>
        </Dialog>

        <FileViewerDialog
            v-model:visible="viewerVisible"
            :view-url="viewerUrl"
            :download-url="viewerDownloadUrl"
            :filename="viewerFilename"
        />
    </div>
</template>
