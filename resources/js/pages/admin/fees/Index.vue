<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, RotateCcw, Trash2, X } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import { computed, ref, watch } from 'vue';
import FeeScheduleController from '@/actions/App/Http/Controllers/Admin/Fees/FeeScheduleController';
import admin from '@/routes/admin';

type DepartmentRef = { id: number; name: string; code: string };

type Offering = {
    id: number;
    department_id: number;
    department: DepartmentRef | null;
    degree_program: string;
    min_level: number;
    max_level: number;
};

type Installment = {
    id: number;
    sequence: number;
    label: string;
    amount_xaf: number;
    due_date: string;
};

type Schedule = {
    id: number;
    program_offering_id: number;
    program_offering: Offering | null;
    level: number;
    academic_year: string;
    total_xaf: number;
    deleted_at: string | null;
    installments: Installment[];
};

type InstallmentRow = {
    sequence: number;
    label: string;
    amount_xaf: number | null;
    due_date: Date | null;
};

const props = defineProps<{
    schedules: Schedule[];
    offerings: Offering[];
    filters: { trashed: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Fees', href: admin.fees.index() },
        ],
    },
});

const dialogVisible = ref(false);
const editing = ref<Schedule | null>(null);
const showDeleted = ref(props.filters.trashed);

watch(showDeleted, () => {
    router.get(
        FeeScheduleController.index().url,
        showDeleted.value ? { trashed: 1 } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const form = useForm({
    program_offering_id: null as number | null,
    level: 1,
    academic_year: String(new Date().getFullYear()),
    total_xaf: 0 as number | null,
    installments: [] as InstallmentRow[],
});

const offeringOptions = computed(() =>
    props.offerings.map((offering) => ({
        value: offering.id,
        label: `${offering.department?.name ?? '—'} · ${offering.degree_program}`,
    })),
);

const selectedOffering = computed<Offering | null>(
    () =>
        props.offerings.find((o) => o.id === form.program_offering_id) ?? null,
);

const xaf = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XAF',
    maximumFractionDigits: 0,
});

function formatXaf(value: number): string {
    return xaf.format(value);
}

const installmentsTotal = computed(() =>
    form.installments.reduce((sum, row) => sum + (row.amount_xaf ?? 0), 0),
);

function toDate(value: string): Date {
    return new Date(value);
}

function toYmd(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function openCreate(): void {
    editing.value = null;
    form.clearErrors();
    form.defaults({
        program_offering_id: null,
        level: 1,
        academic_year: String(new Date().getFullYear()),
        total_xaf: 0,
        installments: [],
    });
    form.reset();
    dialogVisible.value = true;
}

function openEdit(row: Schedule): void {
    editing.value = row;
    form.clearErrors();
    form.defaults({
        program_offering_id: row.program_offering_id,
        level: row.level,
        academic_year: row.academic_year,
        total_xaf: row.total_xaf,
        installments: row.installments.map((i) => ({
            sequence: i.sequence,
            label: i.label,
            amount_xaf: i.amount_xaf,
            due_date: toDate(i.due_date),
        })),
    });
    form.reset();
    dialogVisible.value = true;
}

function addInstallment(): void {
    const nextSequence =
        form.installments.reduce((max, row) => Math.max(max, row.sequence), 0) +
        1;

    form.installments.push({
        sequence: nextSequence,
        label: '',
        amount_xaf: null,
        due_date: null,
    });
}

function removeInstallment(index: number): void {
    form.installments.splice(index, 1);
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        installments: data.installments.map((row) => ({
            sequence: row.sequence,
            label: row.label,
            amount_xaf: row.amount_xaf,
            due_date: row.due_date ? toYmd(row.due_date) : null,
        })),
    }));

    const onSuccess = (): void => {
        dialogVisible.value = false;
        editing.value = null;
        form.reset();
    };

    if (editing.value) {
        form.patch(FeeScheduleController.update(editing.value.id).url, {
            preserveScroll: true,
            onSuccess,
        });
    } else {
        form.post(FeeScheduleController.store().url, {
            preserveScroll: true,
            onSuccess,
        });
    }
}

function destroy(row: Schedule): void {
    const label = `${row.program_offering?.degree_program ?? '—'} · L${row.level} · ${row.academic_year}`;

    if (!window.confirm(`Delete fee schedule "${label}"?`)) {
        return;
    }

    router.delete(FeeScheduleController.destroy(row.id).url, {
        preserveScroll: true,
    });
}

function restore(row: Schedule): void {
    router.post(
        FeeScheduleController.restore(row.id).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Fees" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Administration
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    Fee schedules
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Tuition totals and installment deadlines per offering,
                    level, and year.
                </p>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm font-normal">
                    <ToggleSwitch v-model="showDeleted" />
                    <span>Show deleted</span>
                </label>
                <Button label="New schedule" @click="openCreate">
                    <template #icon>
                        <Plus class="size-4" />
                    </template>
                </Button>
            </div>
        </section>

        <Card>
            <template #content>
                <DataTable
                    :value="schedules"
                    data-key="id"
                    striped-rows
                    :paginator="schedules.length > 10"
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                >
                    <Column header="Offering">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <div class="flex flex-col">
                                    <span>{{
                                        data.program_offering?.department
                                            ?.name ?? '—'
                                    }}</span>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            data.program_offering
                                                ?.degree_program ?? ''
                                        }}</span
                                    >
                                </div>
                                <Tag
                                    v-if="data.deleted_at"
                                    value="Deleted"
                                    severity="danger"
                                />
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
                        field="academic_year"
                        header="Year"
                        sortable
                        style="width: 7rem"
                    />
                    <Column header="Total" sortable sort-field="total_xaf">
                        <template #body="{ data }">
                            {{ formatXaf(data.total_xaf) }}
                        </template>
                    </Column>
                    <Column header="Installments" style="width: 9rem">
                        <template #body="{ data }">
                            {{ data.installments.length }}
                        </template>
                    </Column>
                    <Column header="" style="width: 8rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end gap-2">
                                <Button
                                    v-if="!data.deleted_at"
                                    severity="secondary"
                                    text
                                    rounded
                                    aria-label="Edit"
                                    @click="openEdit(data)"
                                >
                                    <template #icon>
                                        <Pencil class="size-4" />
                                    </template>
                                </Button>
                                <Button
                                    v-if="!data.deleted_at"
                                    severity="danger"
                                    text
                                    rounded
                                    aria-label="Delete"
                                    @click="destroy(data)"
                                >
                                    <template #icon>
                                        <Trash2 class="size-4" />
                                    </template>
                                </Button>
                                <Button
                                    v-if="data.deleted_at"
                                    severity="success"
                                    text
                                    rounded
                                    aria-label="Restore"
                                    title="Restore"
                                    @click="restore(data)"
                                >
                                    <template #icon>
                                        <RotateCcw class="size-4" />
                                    </template>
                                </Button>
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <p
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No fee schedules yet.
                        </p>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="dialogVisible"
            :header="editing ? 'Edit fee schedule' : 'New fee schedule'"
            modal
            :style="{ width: '46rem' }"
            :breakpoints="{ '768px': '92vw' }"
            :dismissable-mask="!form.processing"
            :closable="!form.processing"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="fs-offering" class="text-sm font-medium"
                            >Program offering</label
                        >
                        <Select
                            id="fs-offering"
                            v-model="form.program_offering_id"
                            :options="offeringOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Select an offering"
                            class="w-full"
                            :invalid="!!form.errors.program_offering_id"
                        />
                        <p
                            v-if="form.errors.program_offering_id"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.program_offering_id }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label for="fs-level" class="text-sm font-medium"
                            >Level</label
                        >
                        <InputNumber
                            v-model="form.level"
                            input-id="fs-level"
                            :min="selectedOffering?.min_level ?? 1"
                            :max="selectedOffering?.max_level ?? 10"
                            show-buttons
                            class="w-full"
                            :invalid="!!form.errors.level"
                        />
                        <p
                            v-if="selectedOffering"
                            class="text-xs text-muted-foreground"
                        >
                            Allowed: {{ selectedOffering.min_level }} –
                            {{ selectedOffering.max_level }}
                        </p>
                        <p
                            v-if="form.errors.level"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.level }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label for="fs-year" class="text-sm font-medium"
                            >Academic year</label
                        >
                        <InputText
                            id="fs-year"
                            v-model="form.academic_year"
                            placeholder="2026"
                            class="w-full"
                            :invalid="!!form.errors.academic_year"
                        />
                        <p
                            v-if="form.errors.academic_year"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.academic_year }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label for="fs-total" class="text-sm font-medium"
                            >Total (XAF)</label
                        >
                        <InputNumber
                            v-model="form.total_xaf"
                            input-id="fs-total"
                            mode="currency"
                            currency="XAF"
                            :min="0"
                            class="w-full"
                            :invalid="!!form.errors.total_xaf"
                        />
                        <p
                            v-if="form.errors.total_xaf"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.total_xaf }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">Installments</span>
                        <Button
                            type="button"
                            label="Add installment"
                            size="small"
                            severity="secondary"
                            outlined
                            @click="addInstallment"
                        >
                            <template #icon>
                                <Plus class="size-4" />
                            </template>
                        </Button>
                    </div>

                    <Message
                        v-if="typeof form.errors.installments === 'string'"
                        severity="error"
                        :closable="false"
                        size="small"
                    >
                        {{ form.errors.installments }}
                    </Message>

                    <p
                        v-if="form.installments.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        No installments — the full total is payable as a single
                        amount.
                    </p>

                    <div
                        v-for="(row, index) in form.installments"
                        :key="index"
                        class="rounded-lg border border-border bg-muted/30 p-3"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <span
                                class="text-xs font-semibold text-muted-foreground"
                            >
                                Installment {{ index + 1 }}
                            </span>
                            <Button
                                type="button"
                                severity="danger"
                                text
                                rounded
                                size="small"
                                aria-label="Remove installment"
                                @click="removeInstallment(index)"
                            >
                                <template #icon>
                                    <X class="size-4" />
                                </template>
                            </Button>
                        </div>
                        <div
                            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[5rem_minmax(0,1fr)_10rem_11rem]"
                        >
                            <div class="space-y-1">
                                <label class="text-xs text-muted-foreground"
                                    >Sequence</label
                                >
                                <InputNumber
                                    v-model="row.sequence"
                                    :min="1"
                                    :max="20"
                                    fluid
                                    class="w-full"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-muted-foreground"
                                    >Label</label
                                >
                                <InputText
                                    v-model="row.label"
                                    placeholder="First installment"
                                    fluid
                                    class="w-full"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-muted-foreground"
                                    >Amount</label
                                >
                                <InputNumber
                                    v-model="row.amount_xaf"
                                    mode="currency"
                                    currency="XAF"
                                    :min="0"
                                    fluid
                                    class="w-full"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs text-muted-foreground"
                                    >Due date</label
                                >
                                <DatePicker
                                    v-model="row.due_date"
                                    date-format="yy-mm-dd"
                                    show-icon
                                    fluid
                                    class="w-full"
                                />
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="form.installments.length > 0"
                        class="text-right text-xs"
                        :class="
                            installmentsTotal > (form.total_xaf ?? 0)
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        Installments total: {{ formatXaf(installmentsTotal) }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="form.processing"
                        @click="dialogVisible = false"
                    />
                    <Button
                        type="submit"
                        :label="editing ? 'Save changes' : 'Create'"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>
