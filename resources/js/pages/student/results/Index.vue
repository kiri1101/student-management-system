<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { GraduationCap } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { ref } from 'vue';
import {
    disputeStatusLabel,
    disputeStatusSeverity,
} from '@/lib/statusDisplay';
import student from '@/routes/student';

type Dispute = {
    id: number;
    status: string;
    reason: string;
    resolution_notes: string | null;
    created_at: string;
};

type CourseRow = {
    id: number;
    code: string;
    title: string;
    academic_year: string;
    ca_score: number | null;
    exam_score: number | null;
    final_score: number | null;
    grade: string | null;
    result_id: number;
    dispute: Dispute | null;
};

defineProps<{ courses: CourseRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My results', href: student.results.index() },
        ],
    },
});

const disputeDialogVisible = ref(false);
const activeResultId = ref<number | null>(null);

const disputeForm = useForm<{ reason: string }>({
    reason: '',
});

function openDispute(course: CourseRow): void {
    activeResultId.value = course.result_id;
    disputeForm.reset();
    disputeForm.clearErrors();
    disputeDialogVisible.value = true;
}

function submitDispute(): void {
    if (activeResultId.value === null) {
        return;
    }

    disputeForm.post(student.results.dispute(activeResultId.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            disputeDialogVisible.value = false;
            activeResultId.value = null;
            disputeForm.reset();
        },
    });
}
</script>

<template>
    <Head title="My results" />

    <div class="space-y-4 p-4">
        <Card v-for="course in courses" :key="course.id">
            <template #title>
                <div class="flex items-center gap-2">
                    <GraduationCap class="size-5" />
                    <span>{{ course.code }} — {{ course.title }}</span>
                    <span class="text-sm text-muted-foreground">
                        ({{ course.academic_year }})
                    </span>
                </div>
            </template>
            <template #content>
                <div class="flex flex-wrap items-center gap-6">
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground">CA</span>
                        <span class="font-medium">
                            {{ course.ca_score ?? '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground">Exam</span>
                        <span class="font-medium">
                            {{ course.exam_score ?? '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground">Final</span>
                        <span class="font-medium">
                            {{ course.final_score ?? '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-muted-foreground">Grade</span>
                        <span class="text-lg font-semibold">
                            {{ course.grade ?? '—' }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 border-t pt-4">
                    <div
                        v-if="course.dispute"
                        class="flex flex-col gap-2"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium">
                                Your dispute
                            </span>
                            <Tag
                                :value="
                                    disputeStatusLabel(course.dispute.status)
                                "
                                :severity="
                                    disputeStatusSeverity(course.dispute.status)
                                "
                            />
                        </div>
                        <p class="whitespace-pre-line text-sm">
                            {{ course.dispute.reason }}
                        </p>
                        <Message
                            v-if="course.dispute.resolution_notes"
                            severity="info"
                            :closable="false"
                        >
                            {{ course.dispute.resolution_notes }}
                        </Message>
                    </div>
                    <Button
                        v-else
                        label="Raise a dispute"
                        severity="secondary"
                        size="small"
                        @click="openDispute(course)"
                    />
                </div>
            </template>
        </Card>

        <Card v-if="courses.length === 0">
            <template #content>
                <div class="py-6 text-center text-sm text-muted-foreground">
                    No published results yet. Your CA and exam results will
                    appear here once they have been published.
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="disputeDialogVisible"
            header="Raise a dispute"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!disputeForm.processing"
            :closable="!disputeForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitDispute">
                <div class="space-y-1">
                    <label for="dispute-reason" class="text-sm font-medium">
                        Reason
                    </label>
                    <Textarea
                        id="dispute-reason"
                        v-model="disputeForm.reason"
                        rows="4"
                        class="w-full"
                        auto-resize
                        :invalid="!!disputeForm.errors.reason"
                    />
                    <Message
                        v-if="disputeForm.errors.reason"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ disputeForm.errors.reason }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="disputeForm.processing"
                        @click="disputeDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Submit dispute"
                        :loading="disputeForm.processing"
                        :disabled="!disputeForm.reason"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>
