<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarClock, ClipboardList } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
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
    plan_status: string;
};

type Session = {
    id: number;
    scheduled_for: string;
    topic: string;
    duration_minutes: number;
    status: string;
};

const props = defineProps<{
    course: Course;
    sessions: Session[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lecturer Dashboard', href: lecturer.dashboard() },
            { title: 'Courses', href: lecturer.courses.index() },
            { title: 'Sessions', href: lecturer.courses.index() },
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
    scheduled_for: Date | null;
    topic: string;
    duration_minutes: number | null;
}>({
    scheduled_for: null,
    topic: '',
    duration_minutes: 60,
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
            scheduled_for: data.scheduled_for
                ? toDateTimeString(data.scheduled_for)
                : null,
            topic: data.topic,
            duration_minutes: data.duration_minutes,
        }))
        .post(CourseSessionController.store(props.course.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                createDialogVisible.value = false;
                createForm.reset();
            },
        });
}

const editForm = useForm<{
    scheduled_for: Date | null;
    topic: string;
    duration_minutes: number | null;
}>({
    scheduled_for: null,
    topic: '',
    duration_minutes: 60,
});
const editDialogVisible = ref(false);
const editingSession = ref<Session | null>(null);

function openEdit(session: Session): void {
    editingSession.value = session;
    editForm.clearErrors();
    editForm.scheduled_for = new Date(session.scheduled_for);
    editForm.topic = session.topic;
    editForm.duration_minutes = session.duration_minutes;
    editDialogVisible.value = true;
}

function submitEdit(): void {
    if (!editingSession.value) {
        return;
    }

    editForm
        .transform((data) => ({
            scheduled_for: data.scheduled_for
                ? toDateTimeString(data.scheduled_for)
                : null,
            topic: data.topic,
            duration_minutes: data.duration_minutes,
        }))
        .patch(
            CourseSessionController.update([
                props.course.id,
                editingSession.value.id,
            ]).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    editDialogVisible.value = false;
                    editingSession.value = null;
                    editForm.reset();
                },
            },
        );
}

const cancelForm = useForm<{ reason: string }>({ reason: '' });
const cancelDialogVisible = ref(false);
const cancellingSession = ref<Session | null>(null);

function openCancel(session: Session): void {
    cancellingSession.value = session;
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelDialogVisible.value = true;
}

function confirmCancel(): void {
    if (!cancellingSession.value) {
        return;
    }

    const reason = cancelForm.reason.trim();

    cancelForm
        .transform(() => ({ reason: reason || undefined }))
        .delete(
            CourseSessionController.destroy([
                props.course.id,
                cancellingSession.value.id,
            ]).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    cancelDialogVisible.value = false;
                    cancellingSession.value = null;
                    cancelForm.reset();
                },
            },
        );
}
</script>

<template>
    <Head :title="`Sessions · ${course.code}`" />

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
                        <CalendarClock class="size-5" />
                        <span>{{ course.code }} — {{ course.title }}</span>
                    </div>
                    <Button
                        label="Add session"
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
                    The course plan must be approved before you can schedule
                    sessions or mark attendance.
                </Message>

                <DataTable
                    :value="sessions"
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
                    <Column header="Duration" style="width: 8rem">
                        <template #body="{ data }">
                            {{ data.duration_minutes }} min
                        </template>
                    </Column>
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="sessionStatusLabel(data.status)"
                                :severity="sessionStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column header="" style="width: 16rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    v-if="planApproved"
                                    :href="
                                        lecturer.courses.sessions.attendance([
                                            course.id,
                                            data.id,
                                        ]).url
                                    "
                                >
                                    <Button
                                        label="Attendance"
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
                                    v-else
                                    label="Attendance"
                                    severity="secondary"
                                    text
                                    size="small"
                                    disabled
                                />
                                <Button
                                    label="Edit"
                                    severity="secondary"
                                    text
                                    size="small"
                                    @click="openEdit(data)"
                                />
                                <Button
                                    label="Cancel"
                                    severity="danger"
                                    text
                                    size="small"
                                    :disabled="data.status === 'cancelled'"
                                    @click="openCancel(data)"
                                />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No sessions scheduled yet.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="createDialogVisible"
            header="Add session"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!createForm.processing"
            :closable="!createForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitCreate">
                <div class="space-y-1">
                    <label for="create-scheduled" class="text-sm font-medium">
                        Scheduled for
                    </label>
                    <DatePicker
                        v-model="createForm.scheduled_for"
                        input-id="create-scheduled"
                        date-format="yy-mm-dd"
                        show-time
                        hour-format="24"
                        show-icon
                        fluid
                        :invalid="!!createForm.errors.scheduled_for"
                    />
                    <Message
                        v-if="createForm.errors.scheduled_for"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ createForm.errors.scheduled_for }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="create-topic" class="text-sm font-medium">
                        Topic
                    </label>
                    <InputText
                        id="create-topic"
                        v-model="createForm.topic"
                        class="w-full"
                        :invalid="!!createForm.errors.topic"
                    />
                    <Message
                        v-if="createForm.errors.topic"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ createForm.errors.topic }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="create-duration" class="text-sm font-medium">
                        Duration (minutes)
                    </label>
                    <InputNumber
                        v-model="createForm.duration_minutes"
                        input-id="create-duration"
                        :min="1"
                        class="w-full"
                        :invalid="!!createForm.errors.duration_minutes"
                    />
                    <Message
                        v-if="createForm.errors.duration_minutes"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ createForm.errors.duration_minutes }}
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
                        label="Add session"
                        :loading="createForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="editDialogVisible"
            header="Edit session"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!editForm.processing"
            :closable="!editForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitEdit">
                <div class="space-y-1">
                    <label for="edit-scheduled" class="text-sm font-medium">
                        Scheduled for
                    </label>
                    <DatePicker
                        v-model="editForm.scheduled_for"
                        input-id="edit-scheduled"
                        date-format="yy-mm-dd"
                        show-time
                        hour-format="24"
                        show-icon
                        fluid
                        :invalid="!!editForm.errors.scheduled_for"
                    />
                    <Message
                        v-if="editForm.errors.scheduled_for"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ editForm.errors.scheduled_for }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="edit-topic" class="text-sm font-medium">
                        Topic
                    </label>
                    <InputText
                        id="edit-topic"
                        v-model="editForm.topic"
                        class="w-full"
                        :invalid="!!editForm.errors.topic"
                    />
                    <Message
                        v-if="editForm.errors.topic"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ editForm.errors.topic }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="edit-duration" class="text-sm font-medium">
                        Duration (minutes)
                    </label>
                    <InputNumber
                        v-model="editForm.duration_minutes"
                        input-id="edit-duration"
                        :min="1"
                        class="w-full"
                        :invalid="!!editForm.errors.duration_minutes"
                    />
                    <Message
                        v-if="editForm.errors.duration_minutes"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ editForm.errors.duration_minutes }}
                    </Message>
                </div>
                <p class="text-xs text-muted-foreground">
                    Students in this cohort will be emailed and notified in-app
                    when you reschedule.
                </p>
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
            v-model:visible="cancelDialogVisible"
            header="Cancel session"
            modal
            :style="{ width: '28rem' }"
            :dismissable-mask="!cancelForm.processing"
            :closable="!cancelForm.processing"
        >
            <p class="text-sm text-muted-foreground">
                Are you sure you want to cancel this session? Students will see
                it marked as cancelled.
            </p>
            <div class="mt-3 space-y-1">
                <label for="cancel-reason" class="text-sm font-medium">
                    Reason (shared with students)
                </label>
                <Textarea
                    id="cancel-reason"
                    v-model="cancelForm.reason"
                    class="w-full"
                    rows="3"
                    auto-resize
                    :maxlength="500"
                    :disabled="cancelForm.processing"
                    :invalid="!!cancelForm.errors.reason"
                />
                <Message
                    v-if="cancelForm.errors.reason"
                    severity="error"
                    :closable="false"
                    size="small"
                >
                    {{ cancelForm.errors.reason }}
                </Message>
                <p class="text-xs text-muted-foreground">
                    Students in this cohort will be emailed and notified in-app.
                </p>
            </div>
            <div class="flex items-center justify-end gap-2 pt-4">
                <Button
                    type="button"
                    label="Keep session"
                    severity="secondary"
                    text
                    :disabled="cancelForm.processing"
                    @click="cancelDialogVisible = false"
                />
                <Button
                    type="button"
                    label="Cancel session"
                    severity="danger"
                    :loading="cancelForm.processing"
                    @click="confirmCancel"
                />
            </div>
        </Dialog>
    </div>
</template>
