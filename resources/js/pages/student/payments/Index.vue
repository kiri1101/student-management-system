<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CloudUpload, Download, Wallet } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import StudentPaymentController from '@/actions/App/Http/Controllers/Student/PaymentController';
import { degreeLabel, paymentStatusLabel, paymentStatusSeverity } from '@/lib/statusDisplay';
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
};

type BankOption = { value: string; label: string };

const props = defineProps<{
    profile: Profile;
    schedule: Schedule;
    validatedTotal: number;
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
    props.schedule ? Math.max(props.schedule.total_xaf - props.validatedTotal, 0) : null,
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
</script>

<template>
    <Head title="My payments" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <Wallet class="size-5" />
                    <span>Tuition payments</span>
                </div>
            </template>
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
                                {{ profile.program_offering?.department?.name ?? '—' }}
                                ·
                                {{
                                    degreeLabel(
                                        profile.program_offering?.degree_program,
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
                                {{ remaining === null ? '—' : formatXaf(remaining) }}
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
                        <label for="bank" class="text-sm font-medium">Bank</label>
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
                        <p v-if="form.errors.bank" class="text-xs text-destructive">
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
                        <p v-if="form.errors.slip" class="text-xs text-destructive">
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
    </div>
</template>
