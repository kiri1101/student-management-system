<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import Card from 'primevue/card';
import Textarea from 'primevue/textarea';
import { ref } from 'vue';
import DepartmentController from '@/actions/App/Http/Controllers/Admin/References/DepartmentController';
import admin from '@/routes/admin';

type Department = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    program_offerings_count: number;
};

defineProps<{ departments: Department[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Reference data', href: admin.references.index() },
            {
                title: 'Departments',
                href: admin.references.departments.index(),
            },
        ],
    },
});

const dialogVisible = ref(false);
const editing = ref<Department | null>(null);

const form = useForm({
    name: '',
    code: '',
    description: '',
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogVisible.value = true;
}

function openEdit(row: Department): void {
    editing.value = row;
    form.clearErrors();
    form.defaults({
        name: row.name,
        code: row.code,
        description: row.description ?? '',
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
        form.patch(DepartmentController.update(editing.value.id).url, {
            preserveScroll: true,
            onSuccess,
        });
    } else {
        form.post(DepartmentController.store().url, {
            preserveScroll: true,
            onSuccess,
        });
    }
}

function destroy(row: Department): void {
    if (!window.confirm(`Delete department "${row.name}"?`)) {
        return;
    }

    router.delete(DepartmentController.destroy(row.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Departments" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between">
                    <span>Departments</span>
                    <Button
                        label="New department"
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
                    :value="departments"
                    data-key="id"
                    striped-rows
                    :paginator="departments.length > 10"
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                >
                    <Column field="name" header="Name" sortable />
                    <Column
                        field="code"
                        header="Code"
                        sortable
                        style="width: 8rem"
                    />
                    <Column field="description" header="Description">
                        <template #body="{ data }">
                            <span class="text-sm text-muted-foreground">{{
                                data.description ?? '—'
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="program_offerings_count"
                        header="Offerings"
                        sortable
                        style="width: 8rem"
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
                            No departments yet.
                        </p>
                    </template>
                </DataTable>
            </template>
        </Card>

        <Dialog
            v-model:visible="dialogVisible"
            :header="editing ? 'Edit department' : 'New department'"
            modal
            :style="{ width: '32rem' }"
            :dismissable-mask="!form.processing"
            :closable="!form.processing"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1">
                    <label for="dept-name" class="text-sm font-medium"
                        >Name</label
                    >
                    <InputText
                        id="dept-name"
                        v-model="form.name"
                        class="w-full"
                        :invalid="!!form.errors.name"
                        autofocus
                    />
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="dept-code" class="text-sm font-medium"
                        >Code</label
                    >
                    <InputText
                        id="dept-code"
                        v-model="form.code"
                        class="w-full"
                        :invalid="!!form.errors.code"
                    />
                    <p v-if="form.errors.code" class="text-xs text-destructive">
                        {{ form.errors.code }}
                    </p>
                </div>

                <div class="space-y-1">
                    <label for="dept-description" class="text-sm font-medium">
                        Description
                        <span class="text-muted-foreground">(optional)</span>
                    </label>
                    <Textarea
                        id="dept-description"
                        v-model="form.description"
                        rows="3"
                        class="w-full"
                        :invalid="!!form.errors.description"
                    />
                    <p
                        v-if="form.errors.description"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.description }}
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
