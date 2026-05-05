<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { Inbox } from 'lucide-vue-next';
import Card from 'primevue/card';
import type {
    DataTablePageEvent,
    DataTableSortEvent,
} from 'primevue/datatable';
import MultiSelect from 'primevue/multiselect';
import Tag from 'primevue/tag';
import { ref, watch } from 'vue';
import ApplicationReviewController from '@/actions/App/Http/Controllers/Sao/ApplicationReviewController';
import sao from '@/routes/sao';

type Department = { id: number; name: string; code: string };
type ProgramOffering = {
    id: number;
    degree_program: string;
    department: Department;
};

type ApplicationRow = {
    id: number;
    status: string;
    level: number;
    first_name: string;
    last_name: string;
    submitted_at: string | null;
    created_at: string | null;
    program_offering: ProgramOffering;
};

type Paginator = {
    data: ApplicationRow[];
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
    applications: Paginator;
    filters: Filters;
    statusOptions: StatusOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'SAO Dashboard', href: sao.dashboard() },
            { title: 'Applications', href: sao.applications.index() },
        ],
    },
});

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    submitted: 'Submitted',
    under_review: 'Under review',
    documents_requested: 'Documents requested',
    admitted: 'Admitted',
    rejected: 'Rejected',
    waitlisted: 'Waitlisted',
    withdrawn: 'Withdrawn',
};

const STATUS_SEVERITY: Record<
    string,
    'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'
> = {
    draft: 'secondary',
    submitted: 'info',
    under_review: 'warn',
    documents_requested: 'warn',
    admitted: 'success',
    rejected: 'danger',
    waitlisted: 'secondary',
    withdrawn: 'secondary',
};

const DEGREE_LABELS: Record<string, string> = {
    hnd: 'HND',
    bachelors: 'Bachelors',
    masters: 'Masters',
};

const selectedStatuses = ref<string[]>([...props.filters.status]);

function statusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status;
}

function statusSeverity(
    status: string,
): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    return STATUS_SEVERITY[status] ?? 'secondary';
}

function degreeLabel(value: string): string {
    return DEGREE_LABELS[value] ?? value;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}

function reload(overrides: Record<string, FormDataConvertible> = {}): void {
    const data: Record<string, FormDataConvertible> = {
        status: selectedStatuses.value,
        sort_field: props.filters.sort_field,
        sort_order: props.filters.sort_order,
        rows: props.filters.rows,
        page: props.applications.current_page,
        ...overrides,
    };

    router.get(sao.applications.index().url, data, {
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
    <Head title="SAO · Applications" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Inbox class="size-5" />
                        <span>Applications queue</span>
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
                    :value="applications.data"
                    data-key="id"
                    striped-rows
                    lazy
                    paginator
                    :rows="applications.per_page"
                    :total-records="applications.total"
                    :first="
                        (applications.current_page - 1) * applications.per_page
                    "
                    :rows-per-page-options="[15, 25, 50]"
                    sort-mode="single"
                    :sort-field="filters.sort_field"
                    :sort-order="filters.sort_order === 'asc' ? 1 : -1"
                    responsive-layout="scroll"
                    @page="onPage"
                    @sort="onSort"
                >
                    <Column header="Applicant">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>
                                    {{ data.first_name }} {{ data.last_name }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    #{{ data.id }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Programme">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{
                                    data.program_offering.department.name
                                }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        degreeLabel(
                                            data.program_offering
                                                .degree_program,
                                        )
                                    }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="level"
                        header="Level"
                        sortable
                        style="width: 6rem"
                    />
                    <Column
                        header="Status"
                        sortable
                        sort-field="status"
                        style="width: 12rem"
                    >
                        <template #body="{ data }">
                            <Tag
                                :value="statusLabel(data.status)"
                                :severity="statusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column
                        header="Submitted"
                        sortable
                        sort-field="submitted_at"
                        style="width: 9rem"
                    >
                        <template #body="{ data }">
                            <span>{{ formatDate(data.submitted_at) }}</span>
                        </template>
                    </Column>
                    <Column header="" style="width: 6rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end">
                                <Link
                                    :href="
                                        ApplicationReviewController.show(
                                            data.id,
                                        ).url
                                    "
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
                            No applications match the selected filters.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
