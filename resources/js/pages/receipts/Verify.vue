<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldAlert, ShieldCheck } from 'lucide-vue-next';

type VerifiedReceipt = {
    receipt_number: string;
    amount_xaf: number;
    academic_year: string;
    issued_at: string | null;
    student: {
        matricule: string | null;
        name: string | null;
        level: number | null;
        programme: string | null;
    };
};

defineProps<{
    receiptNumber: string;
    valid: boolean;
    receipt: VerifiedReceipt | null;
}>();

const xaf = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XAF',
    maximumFractionDigits: 0,
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}
</script>

<template>
    <Head title="Verify receipt" />

    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 p-4 dark:bg-gray-950"
    >
        <div
            class="w-full max-w-md overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-900"
        >
            <div
                v-if="valid && receipt"
                class="flex flex-col items-center gap-1 bg-green-50 p-6 text-center dark:bg-green-950/40"
            >
                <ShieldCheck class="size-10 text-green-600" />
                <h1 class="text-lg font-semibold text-green-800 dark:text-green-300">
                    Authentic receipt
                </h1>
                <p class="font-mono text-sm text-gray-500">
                    {{ receipt.receipt_number }}
                </p>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-1 bg-red-50 p-6 text-center dark:bg-red-950/40"
            >
                <ShieldAlert class="size-10 text-red-600" />
                <h1 class="text-lg font-semibold text-red-800 dark:text-red-300">
                    Invalid receipt
                </h1>
                <p class="font-mono text-sm text-gray-500">
                    {{ receiptNumber }}
                </p>
            </div>

            <div v-if="valid && receipt" class="space-y-4 p-6">
                <p class="text-sm text-muted-foreground">
                    This receipt is genuine and bound to the student below.
                    Confirm these details match the person presenting it.
                </p>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Student</dt>
                        <dd>{{ receipt.student.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Matricule</dt>
                        <dd class="font-mono">
                            {{ receipt.student.matricule ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Programme</dt>
                        <dd>
                            {{ receipt.student.programme ?? '—' }} · L{{
                                receipt.student.level
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Academic year
                        </dt>
                        <dd>{{ receipt.academic_year }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Amount</dt>
                        <dd class="font-medium">
                            {{ xaf.format(receipt.amount_xaf) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Issued</dt>
                        <dd>{{ formatDate(receipt.issued_at) }}</dd>
                    </div>
                </dl>
            </div>
            <div v-else class="p-6">
                <p class="text-sm text-muted-foreground">
                    We could not verify this receipt. It may have been altered,
                    forged, or the code may be incorrect. Do not accept it as
                    proof of payment — contact the accounts office.
                </p>
            </div>
        </div>
    </div>
</template>
