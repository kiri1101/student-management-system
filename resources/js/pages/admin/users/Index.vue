<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Mail,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    ShieldCheck,
    Trash2,
    Users,
} from 'lucide-vue-next';
import Card from 'primevue/card';
import type { DataTablePageEvent } from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import MultiSelect from 'primevue/multiselect';
import Tag from 'primevue/tag';
import { ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import admin from '@/routes/admin';
import usersRoutes from '@/routes/admin/users';

type RoleOption = { value: string; label: string };
type StatusOption = { value: string; label: string };

type UserRow = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    deleted_at: string | null;
    roles: string[];
};

type Paginator = {
    data: UserRow[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
};

type Filters = {
    status: string;
    roles: string[];
    search: string;
};

const props = defineProps<{
    users: Paginator;
    filters: Filters;
    options: { roles: RoleOption[]; statuses: StatusOption[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Users', href: usersRoutes.index() },
        ],
    },
});

const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    sao: 'SAO',
    lecturer: 'Lecturer',
    accountant: 'Accountant',
    student: 'Student',
    applicant: 'Applicant',
};

const ROLE_SEVERITY: Record<
    string,
    'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'
> = {
    admin: 'danger',
    sao: 'info',
    lecturer: 'success',
    accountant: 'warn',
    student: 'secondary',
    applicant: 'secondary',
};

const selectedRoles = ref<string[]>([...props.filters.roles]);
const selectedStatus = ref<string>(props.filters.status);
const searchTerm = ref<string>(props.filters.search);

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

function roleLabel(role: string): string {
    return ROLE_LABELS[role] ?? role;
}

function roleSeverity(
    role: string,
): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    return ROLE_SEVERITY[role] ?? 'secondary';
}

function reload(overrides: Record<string, FormDataConvertible> = {}): void {
    const data: Record<string, FormDataConvertible> = {
        status: selectedStatus.value,
        roles: selectedRoles.value,
        search: searchTerm.value,
        page: props.users.current_page,
        ...overrides,
    };

    router.get(usersRoutes.index().url, data, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onPage(event: DataTablePageEvent): void {
    reload({ page: event.page + 1 });
}

function onSearchInput(): void {
    if (searchDebounce !== null) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = setTimeout(() => reload({ page: 1 }), 250);
}

watch(selectedRoles, () => reload({ page: 1 }));
watch(selectedStatus, () => reload({ page: 1 }));

function deactivate(row: UserRow): void {
    if (
        !window.confirm(
            `Deactivate ${row.name}? They will no longer be able to log in until restored.`,
        )
    ) {
        return;
    }

    router.delete(UserController.destroy(row.id).url, {
        preserveScroll: true,
    });
}

function restore(row: UserRow): void {
    router.post(
        UserController.restore(row.id).url,
        {},
        { preserveScroll: true },
    );
}

function resendInvite(row: UserRow): void {
    if (!window.confirm(`Resend the invitation email to ${row.email}?`)) {
        return;
    }

    router.post(
        UserController.resendInvite(row.id).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Users" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Users class="size-5" />
                        <span>Users</span>
                    </div>
                    <Link :href="usersRoutes.create().url">
                        <Button label="New user" size="small">
                            <template #icon>
                                <Plus class="size-4" />
                            </template>
                        </Button>
                    </Link>
                </div>
            </template>
            <template #content>
                <div class="mb-4 grid gap-3 sm:grid-cols-3">
                    <MultiSelect
                        v-model="selectedRoles"
                        :options="options.roles"
                        option-label="label"
                        option-value="value"
                        placeholder="Filter by role"
                        :max-selected-labels="3"
                        class="w-full"
                    />
                    <Select
                        v-model="selectedStatus"
                        :options="options.statuses"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                    <IconField>
                        <InputIcon>
                            <Search class="size-4" />
                        </InputIcon>
                        <InputText
                            v-model="searchTerm"
                            placeholder="Search name or email"
                            class="w-full"
                            @input="onSearchInput"
                        />
                    </IconField>
                </div>

                <DataTable
                    :value="users.data"
                    data-key="id"
                    striped-rows
                    lazy
                    paginator
                    :rows="users.per_page"
                    :total-records="users.total"
                    :first="(users.current_page - 1) * users.per_page"
                    responsive-layout="scroll"
                    @page="onPage"
                >
                    <Column header="Name">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{ data.name }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ data.email }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Roles" style="width: 16rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Tag
                                    v-for="role in data.roles"
                                    :key="role"
                                    :value="roleLabel(role)"
                                    :severity="roleSeverity(role)"
                                />
                                <span
                                    v-if="data.roles.length === 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    No role
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Status" style="width: 9rem">
                        <template #body="{ data }">
                            <Tag
                                v-if="data.deleted_at"
                                value="Deactivated"
                                severity="danger"
                            />
                            <Tag
                                v-else-if="!data.email_verified_at"
                                value="Pending invite"
                                severity="warn"
                            />
                            <Tag v-else value="Active" severity="success" />
                        </template>
                    </Column>
                    <Column header="" style="width: 14rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    v-if="!data.deleted_at"
                                    :href="UserController.edit(data.id).url"
                                >
                                    <Button
                                        severity="secondary"
                                        text
                                        rounded
                                        aria-label="Edit"
                                        title="Edit"
                                    >
                                        <template #icon>
                                            <Pencil class="size-4" />
                                        </template>
                                    </Button>
                                </Link>
                                <Button
                                    v-if="!data.deleted_at"
                                    severity="secondary"
                                    text
                                    rounded
                                    aria-label="Resend invite"
                                    title="Resend invite"
                                    @click="resendInvite(data)"
                                >
                                    <template #icon>
                                        <Mail class="size-4" />
                                    </template>
                                </Button>
                                <Button
                                    v-if="!data.deleted_at"
                                    severity="danger"
                                    text
                                    rounded
                                    aria-label="Deactivate"
                                    title="Deactivate"
                                    @click="deactivate(data)"
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
                        <div class="flex flex-col items-center gap-2 py-6">
                            <ShieldCheck
                                class="size-8 text-muted-foreground/40"
                            />
                            <p class="text-sm text-muted-foreground">
                                No users match the current filters.
                            </p>
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
