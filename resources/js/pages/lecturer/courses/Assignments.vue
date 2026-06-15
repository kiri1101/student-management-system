<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, FileText } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
import AssignmentController from '@/actions/App/Http/Controllers/Lecturer/AssignmentController';
import lecturer from '@/routes/lecturer';

type Course = {
    id: number;
    code: string;
    title: string;
    plan_status: string;
};

type Assignment = {
    id: number;
    title: string;
    instructions: string;
    due_at: string;
    max_score: number;
    submission_count: number;
    graded_count: number;
};

const props = defineProps<{
    course: Course;
    assignments: Assignment[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lecturer Dashboard', href: lecturer.dashboard() },
            { title: 'Courses', href: lecturer.courses.index() },
            { title: 'Assignments', href: lecturer.courses.index() },
        ],
    },
});

const planApproved = computed(() => props.course.plan_status === 'approved');

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString();
}

function toDateTimeString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day} ${hours}:${minutes}`;
}

const createForm = useForm<{
    title: string;
    instructions: string;
    due_at: Date | null;
    max_score: number | null;
}>({
    title: '',
    instructions: '',
    due_at: null,
    max_score: 20,
});
const createDialogVisible = ref(false);

function openCreate(): void {
    createForm.reset();
    createForm.clearErrors();
    createDialogVisible.value = true;
}

function submitCreate(): void {
    createForm
        .transform((data) => ({
            title: data.title,
            instructions: data.instructions,
            due_at: data.due_at ? toDateTimeString(data.due_at) : null,
            max_score: data.max_score,
        }))
        .post(AssignmentController.store(props.course.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                createDialogVisible.value = false;
                createForm.reset();
            },
        });
}

const editForm = useForm<{
    title: string;
    instructions: string;
    due_at: Date | null;
    max_score: number | null;
}>({
    title: '',
    instructions: '',
    due_at: null,
    max_score: 20,
});
const editDialogVisible = ref(false);
const editingAssignment = ref<Assignment | null>(null);

function openEdit(assignment: Assignment): void {
    editingAssignment.value = assignment;
    editForm.clearErrors();
    editForm.title = assignment.title;
    editForm.instructions = assignment.instructions;
    editForm.due_at = new Date(assignment.due_at);
    editForm.max_score = assignment.max_score;
    editDialogVisible.value = true;
}

function submitEdit(): void {
    if (!editingAssignment.value) {
        return;
    }

    editForm
        .transform((data) => ({
            title: data.title,
            instructions: data.instructions,
            due_at: data.due_at ? toDateTimeString(data.due_at) : null,
            max_score: data.max_score,
        }))
        .patch(
            AssignmentController.update([
                props.course.id,
                editingAssignment.value.id,
            ]).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    editDialogVisible.value = false;
                    editingAssignment.value = null;
                    editForm.reset();
                },
            },
        );
}

const deleteForm = useForm({});
const deleteDialogVisible = ref(false);
const deletingAssignment = ref<Assignment | null>(null);

function openDelete(assignment: Assignment): void {
    deletingAssignment.value = assignment;
    deleteDialogVisible.value = true;
}

function confirmDelete(): void {
    if (!deletingAssignment.value) {
        return;
    }

    deleteForm.delete(
        AssignmentController.destroy([
            props.course.id,
            deletingAssignment.value.id,
        ]).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogVisible.value = false;
                deletingAssignment.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Assignments · ${course.code}`" />

    <div class="space-y-4 p-4">
        <Link :href="lecturer.courses.index().url">
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

        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <FileText class="size-5" />
                        <span>{{ course.code }} — {{ course.title }}</span>
                    </div>
                    <Button
                        label="New assignment"
                        size="small"
                        :disabled="!planApproved"
                        @click="openCreate"
                    />
                </div>
            </template>
            <template #content>
                <Message
                    v-if="!planApproved"
                    severity="warn"
                    :closable="false"
                    class="mb-4"
                >
                    The course plan must be approved before you can create
                    assignments.
                </Message>

                <DataTable
                    :value="assignments"
                    data-key="id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column field="title" header="Title" />
                    <Column header="Due">
                        <template #body="{ data }">
                            {{ formatDateTime(data.due_at) }}
                        </template>
                    </Column>
                    <Column header="Max score" style="width: 8rem">
                        <template #body="{ data }">
                            {{ data.max_score }}
                        </template>
                    </Column>
                    <Column header="Submissions" style="width: 11rem">
                        <template #body="{ data }">
                            {{ data.submission_count }} ({{ data.graded_count }}
                            graded)
                        </template>
                    </Column>
                    <Column header="" style="width: 18rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    :href="
                                        lecturer.courses.assignments.submissions(
                                            [course.id, data.id],
                                        ).url
                                    "
                                >
                                    <Button
                                        label="Submissions"
                                        severity="secondary"
                                        text
                                        size="small"
                                    >
                                        <template #icon>
                                            <ClipboardList class="size-4" />
                                        </template>
                                    </Button>
                                </Link>
                                <Button
                                    label="Edit"
                                    severity="secondary"
                                    text
                                    size="small"
                                    @click="openEdit(data)"
                                />
                                <Button
                                    label="Delete"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="openDelete(data)"
                                />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No assignments created yet.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="createDialogVisible"
            header="New assignment"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!createForm.processing"
            :closable="!createForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitCreate">
                <div class="space-y-1">
                    <label for="create-title" class="text-sm font-medium">
                        Title
                    </label>
                    <InputText
                        id="create-title"
                        v-model="createForm.title"
                        class="w-full"
                        :invalid="!!createForm.errors.title"
                    />
                    <Message
                        v-if="createForm.errors.title"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ createForm.errors.title }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label
                        for="create-instructions"
                        class="text-sm font-medium"
                    >
                        Instructions
                    </label>
                    <Textarea
                        id="create-instructions"
                        v-model="createForm.instructions"
                        class="w-full"
                        rows="4"
                        auto-resize
                        :invalid="!!createForm.errors.instructions"
                    />
                    <Message
                        v-if="createForm.errors.instructions"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ createForm.errors.instructions }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="create-due" class="text-sm font-medium">
                        Due at
                    </label>
                    <DatePicker
                        v-model="createForm.due_at"
                        input-id="create-due"
                        date-format="yy-mm-dd"
                        show-time
                        hour-format="24"
                        show-icon
                        fluid
                        :invalid="!!createForm.errors.due_at"
                    />
                    <Message
                        v-if="createForm.errors.due_at"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ createForm.errors.due_at }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="create-max-score" class="text-sm font-medium">
                        Max score
                    </label>
                    <InputNumber
                        v-model="createForm.max_score"
                        input-id="create-max-score"
                        :min="1"
                        class="w-full"
                        :invalid="!!createForm.errors.max_score"
                    />
                    <Message
                        v-if="createForm.errors.max_score"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ createForm.errors.max_score }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="createForm.processing"
                        @click="createDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Create assignment"
                        :loading="createForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="editDialogVisible"
            header="Edit assignment"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!editForm.processing"
            :closable="!editForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitEdit">
                <div class="space-y-1">
                    <label for="edit-title" class="text-sm font-medium">
                        Title
                    </label>
                    <InputText
                        id="edit-title"
                        v-model="editForm.title"
                        class="w-full"
                        :invalid="!!editForm.errors.title"
                    />
                    <Message
                        v-if="editForm.errors.title"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ editForm.errors.title }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="edit-instructions" class="text-sm font-medium">
                        Instructions
                    </label>
                    <Textarea
                        id="edit-instructions"
                        v-model="editForm.instructions"
                        class="w-full"
                        rows="4"
                        auto-resize
                        :invalid="!!editForm.errors.instructions"
                    />
                    <Message
                        v-if="editForm.errors.instructions"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ editForm.errors.instructions }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="edit-due" class="text-sm font-medium">
                        Due at
                    </label>
                    <DatePicker
                        v-model="editForm.due_at"
                        input-id="edit-due"
                        date-format="yy-mm-dd"
                        show-time
                        hour-format="24"
                        show-icon
                        fluid
                        :invalid="!!editForm.errors.due_at"
                    />
                    <Message
                        v-if="editForm.errors.due_at"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ editForm.errors.due_at }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="edit-max-score" class="text-sm font-medium">
                        Max score
                    </label>
                    <InputNumber
                        v-model="editForm.max_score"
                        input-id="edit-max-score"
                        :min="1"
                        class="w-full"
                        :invalid="!!editForm.errors.max_score"
                    />
                    <Message
                        v-if="editForm.errors.max_score"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ editForm.errors.max_score }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="editForm.processing"
                        @click="editDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Save changes"
                        :loading="editForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="deleteDialogVisible"
            header="Delete assignment"
            modal
            :style="{ width: '28rem' }"
            :dismissable-mask="!deleteForm.processing"
            :closable="!deleteForm.processing"
        >
            <p class="text-sm text-muted-foreground">
                Are you sure you want to delete this assignment? All student
                submissions for it will be removed.
            </p>
            <div class="flex items-center justify-end gap-2 pt-4">
                <Button
                    type="button"
                    label="Keep assignment"
                    severity="secondary"
                    text
                    :disabled="deleteForm.processing"
                    @click="deleteDialogVisible = false"
                />
                <Button
                    type="button"
                    label="Delete assignment"
                    severity="danger"
                    :loading="deleteForm.processing"
                    @click="confirmDelete"
                />
            </div>
        </Dialog>
    </div>
</template>
