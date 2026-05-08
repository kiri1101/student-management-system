<script setup lang="ts">
import type {
    DataTablePageEvent,
    DataTableSortEvent,
} from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import MultiSelect from 'primevue/multiselect';
import Tag from 'primevue/tag';
import { computed, ref, watch } from 'vue';
import auditLogs from '@/routes/admin/audit-logs';

type FilterOption = { value: string; label: string };

type AuditUser = { id: number; name: string; email: string };

type AuditRow = {
    id: number;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    changes: Record<string, unknown> | null;
    context: Record<string, unknown> | null;
    occurred_at: string;
    user: AuditUser | null;
};

type ResponseShape = {
    data: AuditRow[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    options: {
        actions: FilterOption[];
        subject_types: FilterOption[];
    };
};

const props = defineProps<{ visible: boolean }>();
const emit = defineEmits<{ (e: 'update:visible', value: boolean): void }>();

const modalVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const ROWS_PER_PAGE = 25;

const rows = ref<AuditRow[]>([]);
const totalRecords = ref(0);
const currentPage = ref(1);
const perPage = ref(ROWS_PER_PAGE);
const sortOrder = ref<'asc' | 'desc'>('desc');
const loading = ref(false);
const errorMessage = ref<string | null>(null);

const actionOptions = ref<FilterOption[]>([]);
const subjectOptions = ref<FilterOption[]>([]);
const optionsLoaded = ref(false);

const userIdFilter = ref<number | null>(null);
const actionsFilter = ref<string[]>([]);
const subjectsFilter = ref<string[]>([]);
const fromFilter = ref<Date | null>(null);
const toFilter = ref<Date | null>(null);

const expandedRows = ref<Record<string, boolean>>({});

function toIsoDate(value: Date | null): string | undefined {
    if (!value) {
        return undefined;
    }

    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;

    const url = new URL(auditLogs.index.url(), window.location.origin);
    url.searchParams.set('page', String(currentPage.value));
    url.searchParams.set('rows', String(perPage.value));
    url.searchParams.set('sort_order', sortOrder.value);

    if (userIdFilter.value !== null) {
        url.searchParams.set('user_id', String(userIdFilter.value));
    }

    actionsFilter.value.forEach((value) => {
        url.searchParams.append('actions[]', value);
    });

    subjectsFilter.value.forEach((value) => {
        url.searchParams.append('subject_types[]', value);
    });

    const from = toIsoDate(fromFilter.value);
    const to = toIsoDate(toFilter.value);

    if (from) {
        url.searchParams.set('from', from);
    }

    if (to) {
        url.searchParams.set('to', to);
    }

    try {
        const response = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`Request failed: ${response.status}`);
        }

        const payload = (await response.json()) as ResponseShape;

        rows.value = payload.data;
        totalRecords.value = payload.meta.total;
        currentPage.value = payload.meta.current_page;
        perPage.value = payload.meta.per_page;

        if (!optionsLoaded.value) {
            actionOptions.value = payload.options.actions;
            subjectOptions.value = payload.options.subject_types;
            optionsLoaded.value = true;
        }
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Failed to load audit log.';
    } finally {
        loading.value = false;
    }
}

function reload(reset = true): void {
    if (reset) {
        currentPage.value = 1;
    }

    load();
}

function onPage(event: DataTablePageEvent): void {
    currentPage.value = event.page + 1;
    perPage.value = event.rows;
    load();
}

function onSort(event: DataTableSortEvent): void {
    if (typeof event.sortField !== 'string' && event.sortField !== undefined) {
        return;
    }

    sortOrder.value = event.sortOrder === 1 ? 'asc' : 'desc';
    currentPage.value = 1;
    load();
}

function clearFilters(): void {
    userIdFilter.value = null;
    actionsFilter.value = [];
    subjectsFilter.value = [];
    fromFilter.value = null;
    toFilter.value = null;
    reload();
}

function actionLabel(value: string): string {
    return (
        actionOptions.value.find((option) => option.value === value)?.label ??
        value
    );
}

function actionSeverity(
    value: string,
): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    if (value.startsWith('login_failed')) {
        return 'danger';
    }

    if (
        [
            'created',
            'restored',
            'role_assigned',
            'application_decided',
        ].includes(value)
    ) {
        return 'success';
    }

    if (['updated', 'status_changed'].includes(value)) {
        return 'info';
    }

    if (value === 'deleted') {
        return 'warn';
    }

    return 'secondary';
}

function formatTimestamp(value: string): string {
    return new Date(value).toLocaleString();
}

function formatJson(value: Record<string, unknown> | null): string {
    if (!value) {
        return '—';
    }

    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return String(value);
    }
}

watch(
    () => props.visible,
    (next) => {
        if (next) {
            reload();
        }
    },
);

watch([actionsFilter, subjectsFilter], () => {
    if (props.visible) {
        reload();
    }
});
</script>

<template>
    <Dialog
        v-model:visible="modalVisible"
        modal
        header="Audit log"
        :style="{ width: '85vw', maxWidth: '1200px' }"
        :pt="{ content: { class: 'space-y-4' } }"
    >
        <div class="grid gap-3 md:grid-cols-5">
            <div class="md:col-span-1">
                <label class="mb-1 block text-xs text-muted-foreground">
                    Actor user ID
                </label>
                <InputNumber
                    v-model="userIdFilter"
                    :use-grouping="false"
                    :min="1"
                    placeholder="Any"
                    class="w-full"
                    fluid
                    @update:model-value="reload()"
                />
            </div>
            <div class="md:col-span-1">
                <label class="mb-1 block text-xs text-muted-foreground">
                    Action
                </label>
                <MultiSelect
                    v-model="actionsFilter"
                    :options="actionOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Any"
                    :max-selected-labels="2"
                    filter
                    class="w-full"
                />
            </div>
            <div class="md:col-span-1">
                <label class="mb-1 block text-xs text-muted-foreground">
                    Subject type
                </label>
                <MultiSelect
                    v-model="subjectsFilter"
                    :options="subjectOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Any"
                    :max-selected-labels="2"
                    filter
                    class="w-full"
                />
            </div>
            <div class="md:col-span-1">
                <label class="mb-1 block text-xs text-muted-foreground">
                    From
                </label>
                <DatePicker
                    v-model="fromFilter"
                    date-format="yy-mm-dd"
                    show-icon
                    show-button-bar
                    class="w-full"
                    fluid
                    @update:model-value="reload()"
                />
            </div>
            <div class="md:col-span-1">
                <label class="mb-1 block text-xs text-muted-foreground">
                    To
                </label>
                <DatePicker
                    v-model="toFilter"
                    date-format="yy-mm-dd"
                    show-icon
                    show-button-bar
                    class="w-full"
                    fluid
                    @update:model-value="reload()"
                />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button
                label="Clear filters"
                severity="secondary"
                size="small"
                text
                @click="clearFilters"
            />
            <Button
                label="Refresh"
                size="small"
                severity="secondary"
                :loading="loading"
                @click="() => reload()"
            />
        </div>

        <Message v-if="errorMessage" severity="error" :closable="false">
            {{ errorMessage }}
        </Message>

        <DataTable
            v-model:expanded-rows="expandedRows"
            :value="rows"
            data-key="id"
            striped-rows
            lazy
            paginator
            :loading="loading"
            :rows="perPage"
            :total-records="totalRecords"
            :first="(currentPage - 1) * perPage"
            :rows-per-page-options="[15, 25, 50]"
            sort-mode="single"
            sort-field="occurred_at"
            :sort-order="sortOrder === 'asc' ? 1 : -1"
            responsive-layout="scroll"
            @page="onPage"
            @sort="onSort"
        >
            <Column expander style="width: 3rem" />
            <Column
                header="When"
                sortable
                sort-field="occurred_at"
                style="width: 13rem"
            >
                <template #body="{ data }">
                    <span class="font-mono text-xs">
                        {{ formatTimestamp(data.occurred_at) }}
                    </span>
                </template>
            </Column>
            <Column header="Actor">
                <template #body="{ data }">
                    <template v-if="data.user">
                        <div class="flex flex-col">
                            <span>{{ data.user.name }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ data.user.email }}
                            </span>
                        </div>
                    </template>
                    <span v-else class="text-xs text-muted-foreground">
                        System / anonymous
                    </span>
                </template>
            </Column>
            <Column header="Action" style="width: 13rem">
                <template #body="{ data }">
                    <Tag
                        :value="actionLabel(data.action)"
                        :severity="actionSeverity(data.action)"
                    />
                </template>
            </Column>
            <Column header="Subject" style="width: 14rem">
                <template #body="{ data }">
                    <template v-if="data.subject_type">
                        <div class="flex flex-col">
                            <span>{{ data.subject_type }}</span>
                            <span
                                v-if="data.subject_id !== null"
                                class="text-xs text-muted-foreground"
                            >
                                #{{ data.subject_id }}
                            </span>
                        </div>
                    </template>
                    <span v-else class="text-xs text-muted-foreground">
                        —
                    </span>
                </template>
            </Column>
            <template #expansion="{ data }">
                <div class="grid gap-3 p-2 md:grid-cols-2">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase">
                            Changes
                        </p>
                        <pre
                            class="max-h-60 overflow-auto rounded bg-muted/40 p-2 text-xs"
                            >{{ formatJson(data.changes) }}</pre
                        >
                    </div>
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase">
                            Context
                        </p>
                        <pre
                            class="max-h-60 overflow-auto rounded bg-muted/40 p-2 text-xs"
                            >{{ formatJson(data.context) }}</pre
                        >
                    </div>
                </div>
            </template>
            <template #empty>
                <div class="py-6 text-center text-sm text-muted-foreground">
                    No audit log entries match the selected filters.
                </div>
            </template>
        </DataTable>
    </Dialog>
</template>
