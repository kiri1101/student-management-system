<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    CloudUpload,
    Download,
    FileCheck,
} from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
import StudentDeferralController from '@/actions/App/Http/Controllers/Student/DeferralController';
import StudentPaymentController from '@/actions/App/Http/Controllers/Student/PaymentController';
import {
    deferralStatusLabel,
    deferralStatusSeverity,
    degreeLabel,
    paymentStatusLabel,
    paymentStatusSeverity,
    standingLabel,
    standingSeverity,
} from '@/lib/statusDisplay';
import payments from '@/routes/payments';
import student from '@/routes/student';

type Installment = {
    sequence: number;
    label: string;
    amount_xaf: number;
    due_date: string | null;
};

type Profile = {
    matricule: string;
    level: number;
    academic_year: string;
    program_offering: {
        degree_program: string;
        department: { name: string; code: string } | null;
    } | null;
} | null;

type Schedule = {
    total_xaf: number;
    installments: Installment[];
} | null;

type Submission = {
    id: number;
    status: string;
    bank: string;
    bank_label: string;
    amount_xaf: number;
    bank_reference: string;
    slip_original_filename: string;
    rejection_reason: string | null;
    reviewed_at: string | null;
    created_at: string | null;
    receipt_number: string | null;
};

type BankOption = { value: string; label: string };

type Standing = {
    standing: string;
    has_schedule: boolean;
    total_xaf: number;
    required_so_far: number;
    validated_paid: number;
    shortfall: number;
    active_deferral_deadline: string | null;
} | null;

type Deferral = {
    id: number;
    status: string;
    academic_year: string;
    reason: string;
    requested_new_deadline: string | null;
    new_deadline: string | null;
    decision_notes: string | null;
    created_at: string | null;
};

const props = defineProps<{
    profile: Profile;
    schedule: Schedule;
    validatedTotal: number;
    standing: Standing;
    deferrals: Deferral[];
    submissions: Submission[];
    banks: BankOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My payments', href: student.payments.index() }],
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
    return value ? new Date(value).toLocaleDateString() : '—';
}

const remaining = computed(() =>
    props.schedule
        ? Math.max(props.schedule.total_xaf - props.validatedTotal, 0)
        : null,
);

const form = useForm<{
    bank: string | null;
    amount_xaf: number | null;
    bank_reference: string;
    slip: File | null;
}>({
    bank: null,
    amount_xaf: null,
    bank_reference: '',
    slip: null,
});

function onFileSelect(event: { files: File[] }): void {
    form.slip = event.files?.[0] ?? null;
}

function onFileClear(): void {
    form.slip = null;
}

function submit(): void {
    form.post(StudentPaymentController.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });
}

const hasPendingDeferral = computed(() =>
    props.deferrals.some((d) => d.status === 'requested'),
);

const deferralForm = useForm<{
    reason: string;
    requested_new_deadline: Date | null;
}>({
    reason: '',
    requested_new_deadline: null,
});
const deferralDialogVisible = ref(false);

function toYmd(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function openDeferral(): void {
    deferralForm.reset();
    deferralForm.clearErrors();
    deferralDialogVisible.value = true;
}

function submitDeferral(): void {
    deferralForm
        .transform((data) => ({
            reason: data.reason,
            requested_new_deadline: data.requested_new_deadline
                ? toYmd(data.requested_new_deadline)
                : null,
        }))
        .post(StudentDeferralController.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                deferralDialogVisible.value = false;
                deferralForm.reset();
            },
        });
}
</script>

<template>
    <Head title="My payments" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <section>
            <p
                class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
            >
                Student
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">
                Tuition payments
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Report deposits, track validation, and request a deferral.
            </p>
        </section>

        <Card>
            <template #content>
                <div v-if="profile" class="space-y-4">
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Matricule
                            </dt>
                            <dd class="font-mono text-sm">
                                {{ profile.matricule }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Programme
                            </dt>
                            <dd class="text-sm">
                                {{
                                    profile.program_offering?.department
                                        ?.name ?? '—'
                                }}
                                ·
                                {{
                                    degreeLabel(
                                        profile.program_offering
                                            ?.degree_program,
                                    )
                                }}
                                · L{{ profile.level }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Academic year
                            </dt>
                            <dd class="text-sm">{{ profile.academic_year }}</dd>
                        </div>
                    </dl>

                    <div
                        v-if="standing && standing.has_schedule"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-md border p-4"
                    >
                        <div class="flex items-center gap-3">
                            <Tag
                                :value="standingLabel(standing.standing)"
                                :severity="standingSeverity(standing.standing)"
                            />
                            <div class="text-sm">
                                <p v-if="standing.standing === 'cleared'">
                                    You are cleared for exams and facilities.
                                </p>
                                <p
                                    v-else-if="standing.standing === 'deferred'"
                                    class="text-muted-foreground"
                                >
                                    Access extended until
                                    <span class="font-medium">{{
                                        formatDate(
                                            standing.active_deferral_deadline,
                                        )
                                    }}</span>
                                    — settle
                                    {{ formatXaf(standing.shortfall) }} before
                                    then.
                                </p>
                                <p v-else class="text-muted-foreground">
                                    Pay
                                    <span
                                        class="font-medium text-destructive"
                                        >{{
                                            formatXaf(standing.shortfall)
                                        }}</span
                                    >
                                    to clear, or request a deferral.
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="standing.standing !== 'cleared'"
                            label="Request deferral"
                            size="small"
                            severity="secondary"
                            outlined
                            :disabled="hasPendingDeferral"
                            @click="openDeferral"
                        >
                            <template #icon>
                                <CalendarClock class="size-4" />
                            </template>
                        </Button>
                    </div>

                    <div
                        v-if="schedule"
                        class="grid gap-4 rounded-md bg-muted/40 p-4 sm:grid-cols-3"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Tuition total
                            </dt>
                            <dd class="text-sm font-medium">
                                {{ formatXaf(schedule.total_xaf) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Validated paid
                            </dt>
                            <dd class="text-sm font-medium text-green-600">
                                {{ formatXaf(validatedTotal) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Remaining
                            </dt>
                            <dd class="text-sm font-medium">
                                {{
                                    remaining === null
                                        ? '—'
                                        : formatXaf(remaining)
                                }}
                            </dd>
                        </div>
                    </div>
                    <Message v-else severity="info" :closable="false">
                        No fee schedule has been configured for your enrollment
                        yet. You can still report a deposit; the accounts office
                        will validate it.
                    </Message>
                </div>
                <Message v-else severity="warn" :closable="false">
                    No active student enrollment found, so there is nothing to
                    pay against yet.
                </Message>
            </template>
        </Card>

        <Card v-if="profile">
            <template #title>Report a deposit</template>
            <template #content>
                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <div class="space-y-1">
                        <label for="bank" class="text-sm font-medium"
                            >Bank</label
                        >
                        <Select
                            id="bank"
                            v-model="form.bank"
                            :options="banks"
                            option-label="label"
                            option-value="value"
                            placeholder="Select the bank"
                            class="w-full"
                            :invalid="!!form.errors.bank"
                        />
                        <p
                            v-if="form.errors.bank"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.bank }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label for="amount" class="text-sm font-medium">
                            Amount (XAF)
                        </label>
                        <InputNumber
                            v-model="form.amount_xaf"
                            input-id="amount"
                            mode="currency"
                            currency="XAF"
                            :min="0"
                            class="w-full"
                            :invalid="!!form.errors.amount_xaf"
                        />
                        <p
                            v-if="form.errors.amount_xaf"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.amount_xaf }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label for="reference" class="text-sm font-medium">
                            Bank transaction reference
                        </label>
                        <InputText
                            id="reference"
                            v-model="form.bank_reference"
                            class="w-full"
                            :invalid="!!form.errors.bank_reference"
                        />
                        <p
                            v-if="form.errors.bank_reference"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.bank_reference }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium">Bank slip</label>
                        <FileUpload
                            mode="basic"
                            name="slip"
                            accept="application/pdf,image/jpeg,image/png"
                            :max-file-size="8 * 1024 * 1024"
                            :auto="false"
                            choose-label="Choose file"
                            :invalid="!!form.errors.slip"
                            @select="onFileSelect"
                            @clear="onFileClear"
                        >
                            <template #chooseicon>
                                <CloudUpload class="size-4" />
                            </template>
                        </FileUpload>
                        <p
                            v-if="form.errors.slip"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.slip }}
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <Button
                            type="submit"
                            label="Report payment"
                            :loading="form.processing"
                        />
                    </div>
                </form>
            </template>
        </Card>

        <Card>
            <template #title>My reported deposits</template>
            <template #content>
                <DataTable
                    :value="submissions"
                    data-key="id"
                    striped-rows
                    :paginator="submissions.length > 10"
                    :rows="10"
                    responsive-layout="scroll"
                >
                    <Column header="Bank">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{ data.bank_label }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ data.bank_reference }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Amount" style="width: 11rem">
                        <template #body="{ data }">
                            {{ formatXaf(data.amount_xaf) }}
                        </template>
                    </Column>
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="paymentStatusLabel(data.status)"
                                :severity="paymentStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column header="Reported" style="width: 9rem">
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                    <Column header="Slip" style="width: 6rem">
                        <template #body="{ data }">
                            <a
                                :href="payments.slip(data.id).url"
                                class="inline-flex items-center text-primary hover:underline"
                                :title="data.slip_original_filename"
                            >
                                <Download class="size-4" />
                            </a>
                        </template>
                    </Column>
                    <Column header="Receipt" style="width: 7rem">
                        <template #body="{ data }">
                            <a
                                v-if="data.receipt_number"
                                :href="
                                    StudentPaymentController.receipt(data.id)
                                        .url
                                "
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                                :title="data.receipt_number"
                            >
                                <FileCheck class="size-4" />
                                <span class="text-xs">View</span>
                            </a>
                            <span v-else class="text-xs text-muted-foreground">
                                —
                            </span>
                        </template>
                    </Column>
                    <Column header="Note">
                        <template #body="{ data }">
                            <span
                                v-if="data.rejection_reason"
                                class="text-xs text-destructive"
                            >
                                {{ data.rejection_reason }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">
                                —
                            </span>
                        </template>
                    </Column>
                    <template #empty>
                        <p
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            You have not reported any deposits yet.
                        </p>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Card v-if="deferrals.length > 0">
            <template #title>My deferral requests</template>
            <template #content>
                <DataTable
                    :value="deferrals"
                    data-key="id"
                    striped-rows
                    :paginator="deferrals.length > 10"
                    :rows="10"
                    responsive-layout="scroll"
                >
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="deferralStatusLabel(data.status)"
                                :severity="deferralStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column header="Reason">
                        <template #body="{ data }">
                            <span class="line-clamp-2 text-sm">{{
                                data.reason
                            }}</span>
                        </template>
                    </Column>
                    <Column header="Extended to" style="width: 9rem">
                        <template #body="{ data }">
                            {{ formatDate(data.new_deadline) }}
                        </template>
                    </Column>
                    <Column header="Requested" style="width: 9rem">
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                    <Column header="Note">
                        <template #body="{ data }">
                            <span
                                v-if="data.decision_notes"
                                class="text-xs text-muted-foreground"
                            >
                                {{ data.decision_notes }}
                            </span>
                            <span v-else class="text-xs text-muted-foreground">
                                —
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="deferralDialogVisible"
            header="Request a tuition deferral"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!deferralForm.processing"
            :closable="!deferralForm.processing"
        >
            <form class="space-y-3" @submit.prevent="submitDeferral">
                <div class="space-y-1">
                    <label for="deferral-reason" class="text-sm font-medium">
                        Reason
                    </label>
                    <Textarea
                        id="deferral-reason"
                        v-model="deferralForm.reason"
                        rows="3"
                        class="w-full"
                        :invalid="!!deferralForm.errors.reason"
                    />
                    <Message
                        v-if="deferralForm.errors.reason"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ deferralForm.errors.reason }}
                    </Message>
                </div>
                <div class="space-y-1">
                    <label for="deferral-deadline" class="text-sm font-medium">
                        Desired new deadline (optional)
                    </label>
                    <DatePicker
                        v-model="deferralForm.requested_new_deadline"
                        input-id="deferral-deadline"
                        date-format="yy-mm-dd"
                        show-icon
                        fluid
                        :invalid="!!deferralForm.errors.requested_new_deadline"
                    />
                    <Message
                        v-if="deferralForm.errors.requested_new_deadline"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ deferralForm.errors.requested_new_deadline }}
                    </Message>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="deferralForm.processing"
                        @click="deferralDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Submit request"
                        :loading="deferralForm.processing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>
