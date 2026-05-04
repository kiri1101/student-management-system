<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import Card from 'primevue/card';
import InputNumber from 'primevue/inputnumber';
import { ref } from 'vue';
import ProgramOfferingController from '@/actions/App/Http/Controllers/Admin/References/ProgramOfferingController';
import admin from '@/routes/admin';

type DepartmentRef = { id: number; name: string; code: string };

type DegreeProgram = string;

type Offering = {
    id: number;
    department_id: number;
    department: DepartmentRef;
    degree_program: DegreeProgram;
    min_level: number;
    max_level: number;
    level_credential_requirements_count: number;
};

type DegreeProgramOption = { value: string; label: string };

defineProps<{
    offerings: Offering[];
    departments: DepartmentRef[];
    degreePrograms: DegreeProgramOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Reference data', href: admin.references.index() },
            {
                title: 'Program offerings',
                href: admin.references.programOfferings.index(),
            },
        ],
    },
});

const dialogVisible = ref(false);
const editing = ref<Offering | null>(null);

const form = useForm({
    department_id: null as number | null,
    degree_program: '' as DegreeProgram,
    min_level: 1,
    max_level: 1,
});

function openCreate(): void {
    editing.value = null;
    form.defaults({
        department_id: null,
        degree_program: '',
        min_level: 1,
        max_level: 1,
    });
    form.reset();
    form.clearErrors();
    dialogVisible.value = true;
}

function openEdit(row: Offering): void {
    editing.value = row;
    form.clearErrors();
    form.defaults({
        department_id: row.department_id,
        degree_program: row.degree_program,
        min_level: row.min_level,
        max_level: row.max_level,
    });
    form.reset();
    dialogVisible.value = true;
}

function submit(): void {
    const onSuccess = (): void => {
        dialogVisible.value = false;
        editing.value = null;
        form.reset();
    };

    if (editing.value) {
        form.patch(ProgramOfferingController.update(editing.value.id).url, {
            preserveScroll: true,
            onSuccess,
        });
    } else {
        form.post(ProgramOfferingController.store().url, {
            preserveScroll: true,
            onSuccess,
        });
    }
}

function destroy(row: Offering): void {
    const label = `${row.department.name} · ${row.degree_program}`;

    if (!window.confirm(`Delete program offering "${label}"?`)) {
        return;
    }

    router.delete(ProgramOfferingController.destroy(row.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Program offerings" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between">
                    <span>Program offerings</span>
                    <Button
                        label="New offering"
                        size="small"
                        @click="openCreate"
                    >
                        <template #icon>
                            <Plus class="size-4" />
                        </template>
                    </Button>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="offerings"
                    data-key="id"
                    striped-rows
                    :paginator="offerings.length > 10"
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                >
                    <Column
                        header="Department"
                        sortable
                        sort-field="department.name"
                    >
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{ data.department.name }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    data.department.code
                                }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="degree_program"
                        header="Degree program"
                        sortable
                    />
                    <Column header="Levels" style="width: 8rem">
                        <template #body="{ data }">
                            <span
                                >{{ data.min_level }} –
                                {{ data.max_level }}</span
                            >
                        </template>
                    </Column>
                    <Column
                        field="level_credential_requirements_count"
                        header="Requirements"
                        sortable
                        style="width: 9rem"
                    />
                    <Column header="" style="width: 8rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end gap-2">
                                <Button
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
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <p
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No program offerings yet.
                        </p>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="dialogVisible"
            :header="editing ? 'Edit program offering' : 'New program offering'"
            modal
            :style="{ width: '34rem' }"
            :dismissable-mask="!form.processing"
            :closable="!form.processing"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1">
                    <label for="po-department" class="text-sm font-medium"
                        >Department</label
                    >
                    <Select
                        id="po-department"
                        v-model="form.department_id"
                        :options="departments"
                        option-label="name"
                        option-value="id"
                        placeholder="Select a department"
                        class="w-full"
                        :invalid="!!form.errors.department_id"
                    />
                    <p
                        v-if="form.errors.department_id"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.department_id }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="po-degree" class="text-sm font-medium"
                        >Degree program</label
                    >
                    <Select
                        id="po-degree"
                        v-model="form.degree_program"
                        :options="degreePrograms"
                        option-label="label"
                        option-value="value"
                        placeholder="Select a degree program"
                        class="w-full"
                        :invalid="!!form.errors.degree_program"
                    />
                    <p
                        v-if="form.errors.degree_program"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.degree_program }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="po-min-level" class="text-sm font-medium"
                            >Min level</label
                        >
                        <InputNumber
                            v-model="form.min_level"
                            input-id="po-min-level"
                            :min="1"
                            :max="10"
                            show-buttons
                            class="w-full"
                            :invalid="!!form.errors.min_level"
                        />
                        <p
                            v-if="form.errors.min_level"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.min_level }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <label for="po-max-level" class="text-sm font-medium"
                            >Max level</label
                        >
                        <InputNumber
                            v-model="form.max_level"
                            input-id="po-max-level"
                            :min="1"
                            :max="10"
                            show-buttons
                            class="w-full"
                            :invalid="!!form.errors.max_level"
                        />
                        <p
                            v-if="form.errors.max_level"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.max_level }}
                        </p>
                    </div>
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
