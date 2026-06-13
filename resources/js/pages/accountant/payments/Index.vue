<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { Wallet } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import type {
    DataTablePageEvent,
    DataTableSortEvent,
} from 'primevue/datatable';
import DataTable from 'primevue/datatable';
import MultiSelect from 'primevue/multiselect';
import Tag from 'primevue/tag';
import { ref, watch } from 'vue';
import PaymentController from '@/actions/App/Http/Controllers/Accountant/PaymentController';
import { paymentStatusLabel, paymentStatusSeverity } from '@/lib/statusDisplay';
import accountant from '@/routes/accountant';

type PaymentRow = {
    id: number;
    status: string;
    bank: string;
    bank_label: string;
    amount_xaf: number;
    bank_reference: string;
    academic_year: string;
    created_at: string | null;
    student: { matricule: string | null; name: string | null };
};

type Paginator = {
    data: PaymentRow[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
};

type Filters = {
    status: string[];
    sort_field: string;
    sort_order: 'asc' | 'desc';
    rows: number;
};

type StatusOption = { value: string; label: string };

const props = defineProps<{
    payments: Paginator;
    filters: Filters;
    statusOptions: StatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Accountant Dashboard', href: accountant.dashboard() },
            { title: 'Payments', href: accountant.payments.index() },
        ],
    },
});

const selectedStatuses = ref<string[]>([...props.filters.status]);

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

function reload(overrides: Record<string, FormDataConvertible> = {}): void {
    const data: Record<string, FormDataConvertible> = {
        status: selectedStatuses.value,
        sort_field: props.filters.sort_field,
        sort_order: props.filters.sort_order,
        rows: props.filters.rows,
        page: props.payments.current_page,
        ...overrides,
    };

    router.get(PaymentController.index().url, data, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onPage(event: DataTablePageEvent): void {
    reload({ page: event.page + 1, rows: event.rows });
}

function onSort(event: DataTableSortEvent): void {
    if (typeof event.sortField !== 'string') {
        return;
    }

    reload({
        sort_field: event.sortField,
        sort_order: event.sortOrder === 1 ? 'asc' : 'desc',
        page: 1,
    });
}

watch(selectedStatuses, () => {
    reload({ page: 1 });
});
</script>

<template>
    <Head title="Accountant · Payments" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Wallet class="size-5" />
                        <span>Payment queue</span>
                    </div>
                    <MultiSelect
                        v-model="selectedStatuses"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Filter by status"
                        :max-selected-labels="3"
                        class="w-72"
                    />
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="payments.data"
                    data-key="id"
                    striped-rows
                    lazy
                    paginator
                    :rows="payments.per_page"
                    :total-records="payments.total"
                    :first="(payments.current_page - 1) * payments.per_page"
                    :rows-per-page-options="[15, 25, 50]"
                    sort-mode="single"
                    :sort-field="filters.sort_field"
                    :sort-order="filters.sort_order === 'asc' ? 1 : -1"
                    responsive-layout="scroll"
                    @page="onPage"
                    @sort="onSort"
                >
                    <Column header="Student">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{ data.student.name ?? '—' }}</span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ data.student.matricule ?? '—' }}
                                </span>
                            </div>
                        </template>
                    </Column>
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
                    <Column
                        header="Amount"
                        sortable
                        sort-field="amount_xaf"
                        style="width: 11rem"
                    >
                        <template #body="{ data }">
                            {{ formatXaf(data.amount_xaf) }}
                        </template>
                    </Column>
                    <Column
                        field="academic_year"
                        header="Year"
                        style="width: 6rem"
                    />
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="paymentStatusLabel(data.status)"
                                :severity="paymentStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column
                        header="Reported"
                        sortable
                        sort-field="created_at"
                        style="width: 9rem"
                    >
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                    <Column header="" style="width: 6rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end">
                                <Link :href="PaymentController.show(data.id).url">
                                    <Button
                                        label="Review"
                                        severity="secondary"
                                        text
                                        size="small"
                                    />
                                </Link>
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No payments match the selected filters.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
