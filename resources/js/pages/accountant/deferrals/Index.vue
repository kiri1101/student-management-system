<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarClock } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import type { DataTablePageEvent } from 'primevue/datatable';
import DataTable from 'primevue/datatable';
import MultiSelect from 'primevue/multiselect';
import Tag from 'primevue/tag';
import { ref, watch } from 'vue';
import DeferralController from '@/actions/App/Http/Controllers/Accountant/DeferralController';
import { deferralStatusLabel, deferralStatusSeverity } from '@/lib/statusDisplay';
import accountant from '@/routes/accountant';

type DeferralRow = {
    id: number;
    status: string;
    academic_year: string;
    reason: string;
    requested_new_deadline: string | null;
    created_at: string | null;
    student: { matricule: string | null; name: string | null };
};

type Paginator = {
    data: DeferralRow[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
};

type Filters = { status: string[]; sort_order: 'asc' | 'desc'; rows: number };
type StatusOption = { value: string; label: string };

const props = defineProps<{
    deferrals: Paginator;
    filters: Filters;
    statusOptions: StatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Accountant Dashboard', href: accountant.dashboard() },
            { title: 'Deferrals', href: accountant.deferrals.index() },
        ],
    },
});

const selectedStatuses = ref<string[]>([...props.filters.status]);

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function reload(overrides: Record<string, FormDataConvertible> = {}): void {
    router.get(
        DeferralController.index().url,
        {
            status: selectedStatuses.value,
            sort_order: props.filters.sort_order,
            rows: props.filters.rows,
            page: props.deferrals.current_page,
            ...overrides,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onPage(event: DataTablePageEvent): void {
    reload({ page: event.page + 1, rows: event.rows });
}

watch(selectedStatuses, () => reload({ page: 1 }));
</script>

<template>
    <Head title="Accountant · Deferrals" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <CalendarClock class="size-5" />
                        <span>Deferral queue</span>
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
                    :value="deferrals.data"
                    data-key="id"
                    striped-rows
                    lazy
                    paginator
                    :rows="deferrals.per_page"
                    :total-records="deferrals.total"
                    :first="(deferrals.current_page - 1) * deferrals.per_page"
                    :rows-per-page-options="[15, 25, 50]"
                    responsive-layout="scroll"
                    @page="onPage"
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
                    <Column header="Reason">
                        <template #body="{ data }">
                            <span class="line-clamp-2 text-sm">{{
                                data.reason
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="academic_year"
                        header="Year"
                        style="width: 6rem"
                    />
                    <Column header="Desired deadline" style="width: 10rem">
                        <template #body="{ data }">
                            {{ formatDate(data.requested_new_deadline) }}
                        </template>
                    </Column>
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                :value="deferralStatusLabel(data.status)"
                                :severity="deferralStatusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column header="" style="width: 6rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end">
                                <Link
                                    :href="DeferralController.show(data.id).url"
                                >
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
                            No deferral requests match the selected filters.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
