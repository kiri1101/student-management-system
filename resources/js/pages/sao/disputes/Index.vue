<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
import { disputeStatusLabel, disputeStatusSeverity } from '@/lib/statusDisplay';
import sao from '@/routes/sao';

type DisputeRow = {
    id: number;
    status: string;
    reason: string;
    resolution_notes: string | null;
    created_at: string;
    reviewed_at: string | null;
    reviewer_name: string | null;
    student_name: string | null;
    matricule: string | null;
    course_code: string;
    course_title: string;
    ca_score: number | null;
    exam_score: number | null;
    final_score: number | null;
    grade: string | null;
};

const props = defineProps<{
    disputes: DisputeRow[];
    filters: { status: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'SAO Dashboard', href: sao.dashboard() },
            { title: 'Disputes', href: sao.disputes.index() },
        ],
    },
});

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString();
}

const statusFilter = ref<string>(props.filters.status ?? 'all');

const filterOptions = [
    { label: 'All', value: 'all' },
    { label: 'Open', value: 'open' },
    { label: 'Under review', value: 'under_review' },
    { label: 'Resolved', value: 'resolved' },
    { label: 'Rejected', value: 'rejected' },
];

function applyFilter(): void {
    router.get(
        sao.disputes.index().url,
        {
            status:
                statusFilter.value === 'all' ? undefined : statusFilter.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function isTerminal(status: string): boolean {
    return status === 'resolved' || status === 'rejected';
}

const reviewDialogVisible = ref(false);
const activeDispute = ref<DisputeRow | null>(null);

const reviewForm = useForm<{
    status: string | null;
    resolution_notes: string;
}>({
    status: null,
    resolution_notes: '',
});

const reviewStatusOptions = [
    { label: 'Under review', value: 'under_review' },
    { label: 'Resolved', value: 'resolved' },
    { label: 'Rejected', value: 'rejected' },
];

function openReview(dispute: DisputeRow): void {
    activeDispute.value = dispute;
    reviewForm.clearErrors();
    reviewForm.status =
        dispute.status === 'open' ? 'under_review' : dispute.status;
    reviewForm.resolution_notes = dispute.resolution_notes ?? '';
    reviewDialogVisible.value = true;
}

function submitReview(): void {
    if (!activeDispute.value) {
        return;
    }

    reviewForm.post(sao.disputes.review(activeDispute.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            reviewDialogVisible.value = false;
            activeDispute.value = null;
            reviewForm.reset();
        },
    });
}
</script>

<template>
    <Head title="SAO · Result disputes" />

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
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    Result disputes
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Review and resolve student result disputes.
                </p>
            </div>
            <Select
                v-model="statusFilter"
                :options="filterOptions"
                option-label="label"
                option-value="value"
                class="w-full sm:w-48"
                @change="applyFilter"
            />
        </section>

        <Card>
            <template #content>
                <DataTable
                    :value="disputes"
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
                    <Column header="Course">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-medium">
                                    {{ data.course_title }}
                                </span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ data.course_code }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Result" style="width: 11rem">
                        <template #body="{ data }">
                            <div class="flex flex-col text-sm">
                                <span>
                                    Final:
                                    {{ data.final_score ?? '—' }}
                                    <span v-if="data.grade" class="font-medium">
                                        ({{ data.grade }})
                                    </span>
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    CA {{ data.ca_score ?? '—' }} · Exam
                                    {{ data.exam_score ?? '—' }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="disputeStatusLabel(data.status)"
                                :severity="disputeStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column header="Raised" style="width: 12rem">
                        <template #body="{ data }">
                            {{ formatDateTime(data.created_at) }}
                        </template>
                    </Column>
                    <Column header="" style="width: 12rem">
                        <template #body="{ data }">
                            <div class="flex flex-col items-end gap-1">
                                <Button
                                    v-if="!isTerminal(data.status)"
                                    label="Review"
                                    size="small"
                                    @click="openReview(data)"
                                />
                                <span
                                    v-else-if="data.resolution_notes"
                                    class="text-right text-xs text-muted-foreground"
                                >
                                    {{ data.resolution_notes }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No result disputes to show.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="reviewDialogVisible"
            header="Review dispute"
            modal
            :style="{ width: '34rem' }"
            :dismissable-mask="!reviewForm.processing"
            :closable="!reviewForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitReview">
                <div
                    v-if="activeDispute"
                    class="rounded-md bg-muted/50 p-3 text-sm"
                >
                    <p class="font-medium">
                        {{ activeDispute.course_code }} —
                        {{ activeDispute.student_name ?? '—' }}
                    </p>
                    <p class="mt-1 whitespace-pre-line text-muted-foreground">
                        {{ activeDispute.reason }}
                    </p>
                </div>
                <div class="space-y-1">
                    <label for="review-status" class="text-sm font-medium">
                        New status
                    </label>
                    <Select
                        id="review-status"
                        v-model="reviewForm.status"
                        :options="reviewStatusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Select a status"
                        class="w-full"
                        :invalid="!!reviewForm.errors.status"
                    />
                    <Message
                        v-if="reviewForm.errors.status"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ reviewForm.errors.status }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="review-notes" class="text-sm font-medium">
                        Resolution notes
                    </label>
                    <Textarea
                        id="review-notes"
                        v-model="reviewForm.resolution_notes"
                        rows="4"
                        class="w-full"
                        auto-resize
                        :invalid="!!reviewForm.errors.resolution_notes"
                    />
                    <Message
                        v-if="reviewForm.errors.resolution_notes"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ reviewForm.errors.resolution_notes }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="reviewForm.processing"
                        @click="reviewDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Save review"
                        :loading="reviewForm.processing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>
