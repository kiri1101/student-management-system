<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, X } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { ref } from 'vue';
import DeferralController from '@/actions/App/Http/Controllers/Accountant/DeferralController';
import { deferralStatusLabel, deferralStatusSeverity, degreeLabel } from '@/lib/statusDisplay';
import accountant from '@/routes/accountant';

type Deferral = {
    id: number;
    status: string;
    is_terminal: boolean;
    academic_year: string;
    reason: string;
    requested_new_deadline: string | null;
    new_deadline: string | null;
    decision_notes: string | null;
    reviewed_at: string | null;
    created_at: string | null;
    reviewed_by: { id: number; name: string } | null;
    student: {
        matricule: string | null;
        level: number;
        name: string | null;
        email: string | null;
        program_offering: {
            degree_program: string;
            department: { name: string; code: string } | null;
        } | null;
    } | null;
};

const props = defineProps<{ deferral: Deferral }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Accountant Dashboard', href: accountant.dashboard() },
            { title: 'Deferrals', href: accountant.deferrals.index() },
            { title: 'Review', href: accountant.deferrals.index() },
        ],
    },
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function toYmd(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const approveForm = useForm<{ new_deadline: Date | null; decision_notes: string }>({
    new_deadline: null,
    decision_notes: '',
});
const rejectForm = useForm({ decision_notes: '' });
const approveDialogVisible = ref(false);
const rejectDialogVisible = ref(false);

function openApprove(): void {
    approveForm.reset();
    approveForm.clearErrors();
    approveDialogVisible.value = true;
}

function openReject(): void {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectDialogVisible.value = true;
}

function submitApprove(): void {
    approveForm
        .transform((data) => ({
            new_deadline: data.new_deadline ? toYmd(data.new_deadline) : null,
            decision_notes: data.decision_notes || null,
        }))
        .post(DeferralController.approve(props.deferral.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                approveDialogVisible.value = false;
            },
        });
}

function submitReject(): void {
    rejectForm.post(DeferralController.reject(props.deferral.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            rejectDialogVisible.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Deferral #${deferral.id}`" />

    <div class="space-y-4 p-4">
        <Link :href="accountant.deferrals.index().url">
            <Button label="Back to queue" severity="secondary" text size="small">
                <template #icon>
                    <ArrowLeft class="size-4" />
                </template>
            </Button>
        </Link>

        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <span>Deferral #{{ deferral.id }}</span>
                    <Tag
                        :value="deferralStatusLabel(deferral.status)"
                        :severity="deferralStatusSeverity(deferral.status)"
                    />
                </div>
            </template>
            <template #content>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">Student</dt>
                        <dd class="text-sm">
                            {{ deferral.student?.name ?? '—' }}
                            <span class="font-mono text-xs text-muted-foreground">
                                ({{ deferral.student?.matricule ?? '—' }})
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Programme</dt>
                        <dd class="text-sm">
                            {{
                                deferral.student?.program_offering?.department
                                    ?.name ?? '—'
                            }}
                            ·
                            {{
                                degreeLabel(
                                    deferral.student?.program_offering
                                        ?.degree_program,
                                )
                            }}
                            · L{{ deferral.student?.level }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Academic year
                        </dt>
                        <dd class="text-sm">{{ deferral.academic_year }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Desired deadline
                        </dt>
                        <dd class="text-sm">
                            {{ formatDate(deferral.requested_new_deadline) }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-muted-foreground">Reason</dt>
                        <dd class="text-sm">{{ deferral.reason }}</dd>
                    </div>
                </dl>
            </template>
        </Card>

        <Card v-if="deferral.is_terminal">
            <template #title>Review outcome</template>
            <template #content>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Reviewed by
                        </dt>
                        <dd class="text-sm">
                            {{ deferral.reviewed_by?.name ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Extended deadline
                        </dt>
                        <dd class="text-sm">
                            {{ formatDate(deferral.new_deadline) }}
                        </dd>
                    </div>
                    <div v-if="deferral.decision_notes" class="sm:col-span-2">
                        <dt class="text-xs text-muted-foreground">Notes</dt>
                        <dd class="text-sm">{{ deferral.decision_notes }}</dd>
                    </div>
                </dl>
            </template>
        </Card>

        <Card v-else>
            <template #title>Decision</template>
            <template #content>
                <div class="flex items-center gap-2">
                    <Button
                        label="Approve"
                        severity="success"
                        @click="openApprove"
                    >
                        <template #icon>
                            <Check class="size-4" />
                        </template>
                    </Button>
                    <Button
                        label="Reject"
                        severity="danger"
                        outlined
                        @click="openReject"
                    >
                        <template #icon>
                            <X class="size-4" />
                        </template>
                    </Button>
                </div>
            </template>
        </Card>

        <Dialog
            v-model:visible="approveDialogVisible"
            header="Approve deferral"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!approveForm.processing"
            :closable="!approveForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitApprove">
                <div class="space-y-1">
                    <label for="new-deadline" class="text-sm font-medium">
                        Extended deadline
                    </label>
                    <DatePicker
                        v-model="approveForm.new_deadline"
                        input-id="new-deadline"
                        date-format="yy-mm-dd"
                        show-icon
                        fluid
                        :invalid="!!approveForm.errors.new_deadline"
                    />
                    <Message
                        v-if="approveForm.errors.new_deadline"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ approveForm.errors.new_deadline }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="approve-notes" class="text-sm font-medium">
                        Notes (optional)
                    </label>
                    <Textarea
                        id="approve-notes"
                        v-model="approveForm.decision_notes"
                        rows="2"
                        class="w-full"
                    />
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="approveForm.processing"
                        @click="approveDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Approve deferral"
                        severity="success"
                        :loading="approveForm.processing"
                    />
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="rejectDialogVisible"
            header="Reject deferral"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!rejectForm.processing"
            :closable="!rejectForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitReject">
                <div class="space-y-1">
                    <label for="reject-notes" class="text-sm font-medium">
                        Reason
                    </label>
                    <Textarea
                        id="reject-notes"
                        v-model="rejectForm.decision_notes"
                        rows="3"
                        class="w-full"
                        :invalid="!!rejectForm.errors.decision_notes"
                    />
                    <Message
                        v-if="rejectForm.errors.decision_notes"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ rejectForm.errors.decision_notes }}
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
                        label="Reject deferral"
                        severity="danger"
                        :loading="rejectForm.processing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>
