<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Download, X } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { ref } from 'vue';
import PaymentController from '@/actions/App/Http/Controllers/Accountant/PaymentController';
import { degreeLabel, paymentStatusLabel, paymentStatusSeverity } from '@/lib/statusDisplay';
import accountant from '@/routes/accountant';
import payments from '@/routes/payments';

type Student = {
    matricule: string | null;
    level: number;
    academic_year: string;
    name: string | null;
    email: string | null;
    program_offering: {
        degree_program: string;
        department: { name: string; code: string } | null;
    } | null;
} | null;

type Payment = {
    id: number;
    status: string;
    is_terminal: boolean;
    bank: string;
    bank_label: string;
    amount_xaf: number;
    bank_reference: string;
    academic_year: string;
    slip_original_filename: string;
    slip_mime_type: string;
    rejection_reason: string | null;
    reviewed_at: string | null;
    created_at: string | null;
    reviewed_by: { id: number; name: string } | null;
    student: Student;
};

const props = defineProps<{ payment: Payment }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Accountant Dashboard', href: accountant.dashboard() },
            { title: 'Payments', href: accountant.payments.index() },
            { title: 'Review', href: accountant.payments.index() },
        ],
    },
});

const xaf = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XAF',
    maximumFractionDigits: 0,
});

function formatXaf(value: number): string {
    return xaf.format(value);
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

const validateForm = useForm({});
const rejectForm = useForm({ rejection_reason: '' });
const rejectDialogVisible = ref(false);

function validate(): void {
    validateForm.post(PaymentController.validatePayment(props.payment.id).url, {
        preserveScroll: true,
    });
}

function openReject(): void {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectDialogVisible.value = true;
}

function submitReject(): void {
    rejectForm.post(PaymentController.reject(props.payment.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            rejectDialogVisible.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Payment #${payment.id}`" />

    <div class="space-y-4 p-4">
        <Link :href="accountant.payments.index().url">
            <Button label="Back to queue" severity="secondary" text size="small">
                <template #icon>
                    <ArrowLeft class="size-4" />
                </template>
            </Button>
        </Link>

        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <span>Payment #{{ payment.id }}</span>
                    <Tag
                        :value="paymentStatusLabel(payment.status)"
                        :severity="paymentStatusSeverity(payment.status)"
                    />
                </div>
            </template>
            <template #content>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">Student</dt>
                        <dd class="text-sm">
                            {{ payment.student?.name ?? '—' }}
                            <span class="font-mono text-xs text-muted-foreground">
                                ({{ payment.student?.matricule ?? '—' }})
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Programme</dt>
                        <dd class="text-sm">
                            {{
                                payment.student?.program_offering?.department
                                    ?.name ?? '—'
                            }}
                            ·
                            {{
                                degreeLabel(
                                    payment.student?.program_offering
                                        ?.degree_program,
                                )
                            }}
                            · L{{ payment.student?.level }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Bank</dt>
                        <dd class="text-sm">{{ payment.bank_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Bank reference
                        </dt>
                        <dd class="text-sm">{{ payment.bank_reference }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Amount</dt>
                        <dd class="text-sm font-medium">
                            {{ formatXaf(payment.amount_xaf) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Academic year
                        </dt>
                        <dd class="text-sm">{{ payment.academic_year }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Reported</dt>
                        <dd class="text-sm">
                            {{ formatDate(payment.created_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Bank slip</dt>
                        <dd class="text-sm">
                            <a
                                :href="payments.slip(payment.id).url"
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                            >
                                <Download class="size-4" />
                                {{ payment.slip_original_filename }}
                            </a>
                        </dd>
                    </div>
                </dl>
            </template>
        </Card>

        <Card v-if="payment.is_terminal">
            <template #title>Review outcome</template>
            <template #content>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Reviewed by
                        </dt>
                        <dd class="text-sm">
                            {{ payment.reviewed_by?.name ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Reviewed at
                        </dt>
                        <dd class="text-sm">
                            {{ formatDate(payment.reviewed_at) }}
                        </dd>
                    </div>
                    <div v-if="payment.rejection_reason" class="sm:col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Rejection reason
                        </dt>
                        <dd class="text-sm">{{ payment.rejection_reason }}</dd>
                    </div>
                </dl>
            </template>
        </Card>

        <Card v-else>
            <template #title>Decision</template>
            <template #content>
                <p class="mb-4 text-sm text-muted-foreground">
                    Verify the bank slip against the reported amount and
                    reference, then validate or reject this payment.
                </p>
                <div class="flex items-center gap-2">
                    <Button
                        label="Validate"
                        severity="success"
                        :loading="validateForm.processing"
                        @click="validate"
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
            v-model:visible="rejectDialogVisible"
            header="Reject payment"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!rejectForm.processing"
            :closable="!rejectForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitReject">
                <div class="space-y-1">
                    <label for="reject-reason" class="text-sm font-medium">
                        Reason
                    </label>
                    <Textarea
                        id="reject-reason"
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        class="w-full"
                        :invalid="!!rejectForm.errors.rejection_reason"
                    />
                    <Message
                        v-if="rejectForm.errors.rejection_reason"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ rejectForm.errors.rejection_reason }}
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
                        label="Reject payment"
                        severity="danger"
                        :loading="rejectForm.processing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>
