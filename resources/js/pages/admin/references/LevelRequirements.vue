<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import Card from 'primevue/card';
import InputNumber from 'primevue/inputnumber';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import { computed, ref } from 'vue';
import LevelCredentialRequirementController from '@/actions/App/Http/Controllers/Admin/References/LevelCredentialRequirementController';
import admin from '@/routes/admin';

type DepartmentRef = { id: number; name: string; code: string };
type DocumentTypeRef = { id: number; name: string; code: string };

type OfferingRef = {
    id: number;
    department_id: number;
    degree_program: string;
    min_level: number;
    max_level: number;
    department: DepartmentRef;
};

type Requirement = {
    id: number;
    program_offering_id: number;
    program_offering: OfferingRef;
    level: number;
    document_type_id: number;
    document_type: DocumentTypeRef;
    required: boolean;
    notes: string | null;
};

const props = defineProps<{
    requirements: Requirement[];
    offerings: OfferingRef[];
    documentTypes: DocumentTypeRef[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Reference data', href: admin.references.index() },
            {
                title: 'Level requirements',
                href: admin.references.levelRequirements.index(),
            },
        ],
    },
});

const offeringOptions = computed(() =>
    props.offerings.map((offering) => ({
        ...offering,
        label: `${offering.department.name} · ${offering.degree_program} (L${offering.min_level}–L${offering.max_level})`,
    })),
);

const dialogVisible = ref(false);
const editing = ref<Requirement | null>(null);

const form = useForm({
    program_offering_id: null as number | null,
    level: 1,
    document_type_id: null as number | null,
    required: true,
    notes: '',
});

const selectedOffering = computed<OfferingRef | null>(() => {
    if (form.program_offering_id === null) {
        return null;
    }

    return (
        props.offerings.find((o) => o.id === form.program_offering_id) ?? null
    );
});

function openCreate(): void {
    editing.value = null;
    form.defaults({
        program_offering_id: null,
        level: 1,
        document_type_id: null,
        required: true,
        notes: '',
    });
    form.reset();
    form.clearErrors();
    dialogVisible.value = true;
}

function openEdit(row: Requirement): void {
    editing.value = row;
    form.clearErrors();
    form.defaults({
        program_offering_id: row.program_offering_id,
        level: row.level,
        document_type_id: row.document_type_id,
        required: row.required,
        notes: row.notes ?? '',
    });
    form.reset();
    dialogVisible.value = true;
}

function onOfferingChange(): void {
    const offering = selectedOffering.value;

    if (offering === null) {
        return;
    }

    if (form.level < offering.min_level) {
        form.level = offering.min_level;
    } else if (form.level > offering.max_level) {
        form.level = offering.max_level;
    }
}

function submit(): void {
    const onSuccess = (): void => {
        dialogVisible.value = false;
        editing.value = null;
        form.reset();
    };

    if (editing.value) {
        form.patch(
            LevelCredentialRequirementController.update(editing.value.id).url,
            {
                preserveScroll: true,
                onSuccess,
            },
        );
    } else {
        form.post(LevelCredentialRequirementController.store().url, {
            preserveScroll: true,
            onSuccess,
        });
    }
}

function destroy(row: Requirement): void {
    const label = `${row.program_offering.department.name} · ${row.program_offering.degree_program} L${row.level} → ${row.document_type.name}`;

    if (!window.confirm(`Delete level requirement "${label}"?`)) {
        return;
    }

    router.delete(LevelCredentialRequirementController.destroy(row.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Level requirements" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between">
                    <span>Level credential requirements</span>
                    <Button
                        label="New requirement"
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
                    :value="requirements"
                    data-key="id"
                    striped-rows
                    :paginator="requirements.length > 10"
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                >
                    <Column header="Offering">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{
                                    data.program_offering.department.name
                                }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ data.program_offering.degree_program }}
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
                    <Column header="Document type">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{ data.document_type.name }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    data.document_type.code
                                }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Required" style="width: 8rem">
                        <template #body="{ data }">
                            <Tag
                                :value="data.required ? 'Required' : 'Optional'"
                                :severity="
                                    data.required ? 'success' : 'secondary'
                                "
                            />
                        </template>
                    </Column>
                    <Column field="notes" header="Notes">
                        <template #body="{ data }">
                            <span class="text-sm text-muted-foreground">{{
                                data.notes ?? '—'
                            }}</span>
                        </template>
                    </Column>
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
                            No level requirements yet.
                        </p>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="dialogVisible"
            :header="
                editing ? 'Edit level requirement' : 'New level requirement'
            "
            modal
            :style="{ width: '36rem' }"
            :dismissable-mask="!form.processing"
            :closable="!form.processing"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1">
                    <label for="lr-offering" class="text-sm font-medium"
                        >Program offering</label
                    >
                    <Select
                        id="lr-offering"
                        v-model="form.program_offering_id"
                        :options="offeringOptions"
                        option-label="label"
                        option-value="id"
                        placeholder="Select a program offering"
                        class="w-full"
                        :invalid="!!form.errors.program_offering_id"
                        @change="onOfferingChange"
                    />
                    <p
                        v-if="form.errors.program_offering_id"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.program_offering_id }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="lr-level" class="text-sm font-medium"
                            >Level</label
                        >
                        <InputNumber
                            v-model="form.level"
                            input-id="lr-level"
                            :min="selectedOffering?.min_level ?? 1"
                            :max="selectedOffering?.max_level ?? 10"
                            show-buttons
                            class="w-full"
                            :disabled="selectedOffering === null"
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
                        <label for="lr-doctype" class="text-sm font-medium"
                            >Document type</label
                        >
                        <Select
                            id="lr-doctype"
                            v-model="form.document_type_id"
                            :options="documentTypes"
                            option-label="name"
                            option-value="id"
                            placeholder="Select a document type"
                            class="w-full"
                            :invalid="!!form.errors.document_type_id"
                        />
                        <p
                            v-if="form.errors.document_type_id"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.document_type_id }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between rounded-md border p-3"
                >
                    <div>
                        <label for="lr-required" class="text-sm font-medium"
                            >Required</label
                        >
                        <p class="text-xs text-muted-foreground">
                            Applicants must upload this document at the chosen
                            level.
                        </p>
                    </div>
                    <ToggleSwitch
                        v-model="form.required"
                        input-id="lr-required"
                        :invalid="!!form.errors.required"
                    />
                </div>

                <div class="space-y-1">
                    <label for="lr-notes" class="text-sm font-medium">
                        Notes
                        <span class="text-muted-foreground">(optional)</span>
                    </label>
                    <Textarea
                        id="lr-notes"
                        v-model="form.notes"
                        rows="2"
                        class="w-full"
                        :invalid="!!form.errors.notes"
                    />
                    <p
                        v-if="form.errors.notes"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.notes }}
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
