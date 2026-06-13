<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer, ShieldCheck } from 'lucide-vue-next';
import Button from 'primevue/button';
import QRCode from 'qrcode';
import { onMounted, ref } from 'vue';
import student from '@/routes/student';

type Receipt = {
    receipt_number: string;
    amount_xaf: number;
    issued_at: string | null;
    academic_year: string;
    bank_label: string;
    bank_reference: string;
    student: {
        matricule: string | null;
        name: string | null;
        level: number | null;
        programme: string | null;
    };
};

const props = defineProps<{
    receipt: Receipt;
    verifyUrl: string;
}>();

const qrDataUrl = ref<string>('');

const xaf = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XAF',
    maximumFractionDigits: 0,
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

onMounted(async () => {
    qrDataUrl.value = await QRCode.toDataURL(props.verifyUrl, {
        margin: 1,
        width: 160,
    });
});

function print(): void {
    window.print();
}
</script>

<template>
    <Head :title="`Receipt ${receipt.receipt_number}`" />

    <div class="mx-auto max-w-2xl space-y-4 p-4">
        <div class="flex items-center justify-between print:hidden">
            <Link :href="student.payments.index().url">
                <Button label="Back" severity="secondary" text size="small">
                    <template #icon>
                        <ArrowLeft class="size-4" />
                    </template>
                </Button>
            </Link>
            <Button label="Print" size="small" @click="print">
                <template #icon>
                    <Printer class="size-4" />
                </template>
            </Button>
        </div>

        <div
            class="rounded-lg border bg-white p-8 text-gray-900 shadow-sm print:border-0 print:shadow-none"
        >
            <header
                class="flex items-start justify-between border-b border-gray-200 pb-4"
            >
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Official school receipt
                    </h1>
                    <p class="text-sm text-gray-500">
                        Proof of validated tuition payment
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Receipt no.</p>
                    <p class="font-mono text-sm font-semibold">
                        {{ receipt.receipt_number }}
                    </p>
                </div>
            </header>

            <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">Student</dt>
                    <dd>{{ receipt.student.name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Matricule</dt>
                    <dd class="font-mono">
                        {{ receipt.student.matricule ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Programme</dt>
                    <dd>
                        {{ receipt.student.programme ?? '—' }} · L{{
                            receipt.student.level
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Academic year</dt>
                    <dd>{{ receipt.academic_year }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Bank</dt>
                    <dd>
                        {{ receipt.bank_label }} ({{ receipt.bank_reference }})
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Issued</dt>
                    <dd>{{ formatDate(receipt.issued_at) }}</dd>
                </div>
            </dl>

            <div
                class="mt-6 rounded-md bg-gray-50 p-4 text-center print:bg-transparent"
            >
                <p class="text-xs uppercase tracking-wide text-gray-500">
                    Amount validated
                </p>
                <p class="text-2xl font-bold">
                    {{ xaf.format(receipt.amount_xaf) }}
                </p>
            </div>

            <footer
                class="mt-6 flex items-end justify-between border-t border-gray-200 pt-4"
            >
                <div class="max-w-xs space-y-1">
                    <div class="flex items-center gap-1 text-sm font-medium">
                        <ShieldCheck class="size-4 text-green-600" />
                        <span>Tamper-proof</span>
                    </div>
                    <p class="text-xs text-gray-500">
                        Scan the code or visit the link to confirm this receipt
                        is authentic and bound to the student above.
                    </p>
                    <p class="break-all font-mono text-[10px] text-gray-400">
                        {{ verifyUrl }}
                    </p>
                </div>
                <img
                    v-if="qrDataUrl"
                    :src="qrDataUrl"
                    alt="Verification QR code"
                    class="size-32"
                />
            </footer>
        </div>
    </div>
</template>
