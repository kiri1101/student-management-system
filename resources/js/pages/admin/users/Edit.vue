<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    History,
    Mail,
    ShieldCheck,
    Trash2,
    UserCog,
} from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import AuditLogModal from '@/components/admin/AuditLogModal.vue';
import { roleLabel, roleSeverity } from '@/lib/statusDisplay';
import admin from '@/routes/admin';
import usersRoutes from '@/routes/admin/users';

type RoleOption = { value: string; label: string };
type Department = { id: number; name: string; code: string };

type LecturerProfile = {
    id: number;
    user_id: number;
    department_id: number | null;
    specialization: string | null;
    hired_at: string | null;
};
type AccountantProfile = {
    id: number;
    user_id: number;
    bank_desk: string | null;
    cashier_window: string | null;
};
type SaoProfile = { id: number; user_id: number; scope: string | null };

type UserModel = {
    id: number;
    name: string;
    email: string;
    employee_id: string | null;
    email_verified_at: string | null;
    deleted_at: string | null;
    roles: string[];
    profiles: {
        lecturer: LecturerProfile | null;
        accountant: AccountantProfile | null;
        sao: SaoProfile | null;
    };
};

const props = defineProps<{
    user: UserModel;
    options: { roles: RoleOption[]; departments: Department[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Users', href: usersRoutes.index() },
            { title: 'Edit', href: '#' },
        ],
    },
});

const primaryRole = computed<string>(() => {
    for (const role of ['lecturer', 'accountant', 'sao', 'admin']) {
        if (props.user.roles.includes(role)) {
            return role;
        }
    }

    return '';
});

const isLecturer = computed(() => primaryRole.value === 'lecturer');
const isAccountant = computed(() => primaryRole.value === 'accountant');
const isSao = computed(() => primaryRole.value === 'sao');
const isAdmin = computed(() => primaryRole.value === 'admin');

function parseDate(value: string | null): Date | null {
    if (!value) {
        return null;
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function formatHiredAt(value: Date): string {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const form = useForm({
    name: props.user.name,
    employee_id: props.user.employee_id ?? '',
    profile: {
        department_id: props.user.profiles.lecturer?.department_id ?? null,
        specialization: props.user.profiles.lecturer?.specialization ?? '',
        hired_at: parseDate(props.user.profiles.lecturer?.hired_at ?? null),
        bank_desk: props.user.profiles.accountant?.bank_desk ?? '',
        cashier_window: props.user.profiles.accountant?.cashier_window ?? '',
        scope: props.user.profiles.sao?.scope ?? '',
    },
});

function submit(): void {
    form.transform((data) => ({
        name: data.name,
        employee_id: data.employee_id || null,
        profile: {
            department_id: data.profile.department_id,
            specialization: data.profile.specialization || null,
            hired_at: data.profile.hired_at
                ? formatHiredAt(data.profile.hired_at)
                : null,
            bank_desk: data.profile.bank_desk || null,
            cashier_window: data.profile.cashier_window || null,
            scope: data.profile.scope || null,
        },
    })).patch(UserController.update(props.user.id).url, {
        preserveScroll: true,
    });
}

function resendInvite(): void {
    if (
        !window.confirm(`Resend the invitation email to ${props.user.email}?`)
    ) {
        return;
    }

    router.post(
        UserController.resendInvite(props.user.id).url,
        {},
        { preserveScroll: true },
    );
}

function deactivate(): void {
    if (
        !window.confirm(
            `Deactivate ${props.user.name}? They will no longer be able to log in until restored.`,
        )
    ) {
        return;
    }

    router.delete(UserController.destroy(props.user.id).url);
}

// Per-user audit-log drill-down
const auditModalVisible = ref(false);

// Change-role dialog
const roleDialogVisible = ref(false);

const roleForm = useForm({
    role: primaryRole.value,
    profile: {
        department_id: null as number | null,
        specialization: '',
        hired_at: null as Date | null,
        bank_desk: '',
        cashier_window: '',
        scope: '',
    },
});

const roleFormIsLecturer = computed(() => roleForm.role === 'lecturer');
const roleFormIsAccountant = computed(() => roleForm.role === 'accountant');
const roleFormIsSao = computed(() => roleForm.role === 'sao');

function openRoleDialog(): void {
    roleForm.clearErrors();
    roleForm.defaults({
        role: primaryRole.value,
        profile: {
            department_id: null,
            specialization: '',
            hired_at: null,
            bank_desk: '',
            cashier_window: '',
            scope: '',
        },
    });
    roleForm.reset();
    roleDialogVisible.value = true;
}

function submitRole(): void {
    roleForm
        .transform((data) => ({
            role: data.role,
            profile: {
                department_id: data.profile.department_id,
                specialization: data.profile.specialization || null,
                hired_at: data.profile.hired_at
                    ? formatHiredAt(data.profile.hired_at)
                    : null,
                bank_desk: data.profile.bank_desk || null,
                cashier_window: data.profile.cashier_window || null,
                scope: data.profile.scope || null,
            },
        }))
        .patch(UserController.changeRole(props.user.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                roleDialogVisible.value = false;
            },
        });
}
</script>

<template>
    <Head :title="`Edit · ${user.name}`" />

    <div class="mx-auto max-w-3xl space-y-6 p-4 sm:p-6">
        <Link :href="usersRoutes.index().url" class="inline-block">
            <Button
                label="Back to users"
                severity="secondary"
                text
                size="small"
            >
                <template #icon>
                    <ArrowLeft class="size-4" />
                </template>
            </Button>
        </Link>

        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Users
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    {{ user.name }}
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Tag
                    v-for="role in user.roles"
                    :key="role"
                    :value="roleLabel(role)"
                    :severity="roleSeverity(role)"
                />
                <Tag
                    v-if="user.deleted_at"
                    value="Deactivated"
                    severity="danger"
                />
                <Tag
                    v-else-if="!user.email_verified_at"
                    value="Pending invite"
                    severity="warn"
                />
            </div>
        </section>

        <Card>
            <template #content>
                <p class="mb-4 text-sm text-muted-foreground">
                    Email is managed by the user via password reset / email
                    verification flows. Use “Change role” to reassign this user
                    to a different staff role.
                </p>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label for="user-name" class="text-sm font-medium">
                                Full name
                            </label>
                            <InputText
                                id="user-name"
                                v-model="form.name"
                                class="w-full"
                                :invalid="!!form.errors.name"
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium">Email</label>
                            <InputText
                                :model-value="user.email"
                                class="w-full"
                                disabled
                            />
                        </div>

                        <div class="space-y-1">
                            <label
                                for="user-employee-id"
                                class="text-sm font-medium"
                            >
                                Employee ID
                                <span class="text-muted-foreground"
                                    >(optional)</span
                                >
                            </label>
                            <InputText
                                id="user-employee-id"
                                v-model="form.employee_id"
                                placeholder="e.g. emp-0042"
                                class="w-full"
                                :invalid="!!form.errors.employee_id"
                            />
                            <p
                                v-if="form.errors.employee_id"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.employee_id }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Lets the user sign in with this ID instead of
                                their email.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="isLecturer"
                        class="rounded-md border border-border p-4"
                    >
                        <h3 class="mb-3 text-sm font-semibold">
                            Lecturer profile
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1">
                                <label
                                    for="lecturer-department"
                                    class="text-sm font-medium"
                                >
                                    Department
                                </label>
                                <Select
                                    id="lecturer-department"
                                    v-model="form.profile.department_id"
                                    :options="options.departments"
                                    option-label="name"
                                    option-value="id"
                                    placeholder="Select a department"
                                    class="w-full"
                                    :invalid="
                                        !!form.errors['profile.department_id']
                                    "
                                />
                                <p
                                    v-if="form.errors['profile.department_id']"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors['profile.department_id'] }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <label
                                    for="lecturer-specialization"
                                    class="text-sm font-medium"
                                >
                                    Specialization
                                </label>
                                <InputText
                                    id="lecturer-specialization"
                                    v-model="form.profile.specialization"
                                    class="w-full"
                                    :invalid="
                                        !!form.errors['profile.specialization']
                                    "
                                />
                                <p
                                    v-if="form.errors['profile.specialization']"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors['profile.specialization'] }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <label
                                    for="lecturer-hired"
                                    class="text-sm font-medium"
                                >
                                    Hired on
                                </label>
                                <DatePicker
                                    id="lecturer-hired"
                                    v-model="form.profile.hired_at"
                                    :max-date="new Date()"
                                    show-icon
                                    icon-display="input"
                                    date-format="yy-mm-dd"
                                    class="w-full"
                                    :invalid="!!form.errors['profile.hired_at']"
                                />
                                <p
                                    v-if="form.errors['profile.hired_at']"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors['profile.hired_at'] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="isAccountant"
                        class="rounded-md border border-border p-4"
                    >
                        <h3 class="mb-3 text-sm font-semibold">
                            Accountant profile
                        </h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1">
                                <label
                                    for="accountant-bank-desk"
                                    class="text-sm font-medium"
                                >
                                    Bank desk
                                </label>
                                <InputText
                                    id="accountant-bank-desk"
                                    v-model="form.profile.bank_desk"
                                    class="w-full"
                                    :invalid="
                                        !!form.errors['profile.bank_desk']
                                    "
                                />
                                <p
                                    v-if="form.errors['profile.bank_desk']"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors['profile.bank_desk'] }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <label
                                    for="accountant-window"
                                    class="text-sm font-medium"
                                >
                                    Cashier window
                                </label>
                                <InputText
                                    id="accountant-window"
                                    v-model="form.profile.cashier_window"
                                    class="w-full"
                                    :invalid="
                                        !!form.errors['profile.cashier_window']
                                    "
                                />
                                <p
                                    v-if="form.errors['profile.cashier_window']"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors['profile.cashier_window'] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="isSao"
                        class="rounded-md border border-border p-4"
                    >
                        <h3 class="mb-3 text-sm font-semibold">SAO profile</h3>
                        <div class="space-y-1">
                            <label for="sao-scope" class="text-sm font-medium">
                                Scope
                            </label>
                            <InputText
                                id="sao-scope"
                                v-model="form.profile.scope"
                                placeholder="e.g. Admissions, Records"
                                class="w-full"
                                :invalid="!!form.errors['profile.scope']"
                            />
                            <p
                                v-if="form.errors['profile.scope']"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors['profile.scope'] }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="isAdmin"
                        class="rounded-md border border-dashed border-border p-4 text-sm text-muted-foreground"
                    >
                        Administrator accounts have no extra profile fields.
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <Button
                            type="submit"
                            label="Save changes"
                            :loading="form.processing"
                        />
                    </div>
                </form>
            </template>
        </Card>

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <ShieldCheck class="size-5" />
                    <span>Account actions</span>
                </div>
            </template>
            <template #content>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        label="Resend invite"
                        severity="secondary"
                        size="small"
                        :disabled="!!user.deleted_at"
                        @click="resendInvite"
                    >
                        <template #icon>
                            <Mail class="size-4" />
                        </template>
                    </Button>
                    <Button
                        label="Change role"
                        severity="secondary"
                        size="small"
                        :disabled="!!user.deleted_at"
                        @click="openRoleDialog"
                    >
                        <template #icon>
                            <UserCog class="size-4" />
                        </template>
                    </Button>
                    <Button
                        label="View audit log"
                        severity="secondary"
                        size="small"
                        @click="auditModalVisible = true"
                    >
                        <template #icon>
                            <History class="size-4" />
                        </template>
                    </Button>
                    <Button
                        label="Deactivate"
                        severity="danger"
                        size="small"
                        :disabled="!!user.deleted_at"
                        @click="deactivate"
                    >
                        <template #icon>
                            <Trash2 class="size-4" />
                        </template>
                    </Button>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Resending the invite invalidates the previous link.
                    Deactivation is reversible from the user list.
                </p>
            </template>
        </Card>

        <AuditLogModal
            v-model:visible="auditModalVisible"
            :scoped-user="{ id: user.id, name: user.name }"
        />

        <Dialog
            v-model:visible="roleDialogVisible"
            header="Change role"
            modal
            :style="{ width: '34rem' }"
            :dismissable-mask="!roleForm.processing"
            :closable="!roleForm.processing"
        >
            <form class="space-y-4" @submit.prevent="submitRole">
                <p class="text-sm text-muted-foreground">
                    Reassigning the role will detach the prior role and remove
                    its profile. Fill the new profile below.
                </p>

                <div class="space-y-1">
                    <label for="role-select" class="text-sm font-medium">
                        New role
                    </label>
                    <Select
                        id="role-select"
                        v-model="roleForm.role"
                        :options="options.roles"
                        option-label="label"
                        option-value="value"
                        placeholder="Select a role"
                        class="w-full"
                        :invalid="!!roleForm.errors.role"
                    />
                    <p
                        v-if="roleForm.errors.role"
                        class="text-xs text-destructive"
                    >
                        {{ roleForm.errors.role }}
                    </p>
                </div>

                <div
                    v-if="roleFormIsLecturer"
                    class="rounded-md border border-border p-4"
                >
                    <h3 class="mb-3 text-sm font-semibold">Lecturer profile</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label
                                for="role-lecturer-department"
                                class="text-sm font-medium"
                            >
                                Department
                            </label>
                            <Select
                                id="role-lecturer-department"
                                v-model="roleForm.profile.department_id"
                                :options="options.departments"
                                option-label="name"
                                option-value="id"
                                placeholder="Select a department"
                                class="w-full"
                                :invalid="
                                    !!roleForm.errors['profile.department_id']
                                "
                            />
                            <p
                                v-if="roleForm.errors['profile.department_id']"
                                class="text-xs text-destructive"
                            >
                                {{ roleForm.errors['profile.department_id'] }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <label
                                for="role-lecturer-specialization"
                                class="text-sm font-medium"
                            >
                                Specialization
                            </label>
                            <InputText
                                id="role-lecturer-specialization"
                                v-model="roleForm.profile.specialization"
                                class="w-full"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                for="role-lecturer-hired"
                                class="text-sm font-medium"
                            >
                                Hired on
                            </label>
                            <DatePicker
                                id="role-lecturer-hired"
                                v-model="roleForm.profile.hired_at"
                                :max-date="new Date()"
                                show-icon
                                icon-display="input"
                                date-format="yy-mm-dd"
                                class="w-full"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-if="roleFormIsAccountant"
                    class="rounded-md border border-border p-4"
                >
                    <h3 class="mb-3 text-sm font-semibold">
                        Accountant profile
                    </h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label
                                for="role-accountant-bank-desk"
                                class="text-sm font-medium"
                            >
                                Bank desk
                            </label>
                            <InputText
                                id="role-accountant-bank-desk"
                                v-model="roleForm.profile.bank_desk"
                                class="w-full"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                for="role-accountant-window"
                                class="text-sm font-medium"
                            >
                                Cashier window
                            </label>
                            <InputText
                                id="role-accountant-window"
                                v-model="roleForm.profile.cashier_window"
                                class="w-full"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-if="roleFormIsSao"
                    class="rounded-md border border-border p-4"
                >
                    <h3 class="mb-3 text-sm font-semibold">SAO profile</h3>
                    <div class="space-y-1">
                        <label for="role-sao-scope" class="text-sm font-medium">
                            Scope
                        </label>
                        <InputText
                            id="role-sao-scope"
                            v-model="roleForm.profile.scope"
                            placeholder="e.g. Admissions, Records"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        :disabled="roleForm.processing"
                        @click="roleDialogVisible = false"
                    />
                    <Button
                        type="submit"
                        label="Update role"
                        :loading="roleForm.processing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>
