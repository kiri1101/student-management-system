<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, UserPlus } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import admin from '@/routes/admin';
import usersRoutes from '@/routes/admin/users';

type RoleOption = { value: string; label: string };
type Department = { id: number; name: string; code: string };

defineProps<{
    options: { roles: RoleOption[]; departments: Department[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Users', href: usersRoutes.index() },
            { title: 'New user', href: usersRoutes.create() },
        ],
    },
});

const form = useForm({
    name: '',
    email: '',
    employee_id: '',
    role: '',
    profile: {
        // Lecturer
        department_id: null as number | null,
        specialization: '',
        hired_at: null as Date | null,
        // Accountant
        bank_desk: '',
        cashier_window: '',
        // SAO
        scope: '',
    },
});

const isLecturer = computed(() => form.role === 'lecturer');
const isAccountant = computed(() => form.role === 'accountant');
const isSao = computed(() => form.role === 'sao');

function submit(): void {
    form.transform((data) => ({
        name: data.name,
        email: data.email,
        employee_id: data.employee_id || null,
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
    })).post(UserController.store().url, {
        preserveScroll: true,
    });
}

function formatHiredAt(value: Date): string {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}
</script>

<template>
    <Head title="New user" />

    <div class="space-y-4 p-4">
        <Link :href="usersRoutes.index().url">
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

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <UserPlus class="size-5" />
                    <span>New user</span>
                </div>
            </template>
            <template #content>
                <p class="mb-4 text-sm text-muted-foreground">
                    The new user will receive an email with a single-use link to
                    set their password and sign in. They are pre-verified — no
                    additional verification step is required.
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
                                autofocus
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label for="user-email" class="text-sm font-medium">
                                Email
                            </label>
                            <InputText
                                id="user-email"
                                v-model="form.email"
                                type="email"
                                class="w-full"
                                :invalid="!!form.errors.email"
                            />
                            <p
                                v-if="form.errors.email"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.email }}
                            </p>
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

                    <div class="space-y-1">
                        <label for="user-role" class="text-sm font-medium">
                            Role
                        </label>
                        <Select
                            id="user-role"
                            v-model="form.role"
                            :options="options.roles"
                            option-label="label"
                            option-value="value"
                            placeholder="Select a role"
                            class="w-full"
                            :invalid="!!form.errors.role"
                        />
                        <p
                            v-if="form.errors.role"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.role }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Applicants self-register; students are admitted via
                            the SAO admission flow.
                        </p>
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
                                    <span class="text-muted-foreground"
                                        >(optional)</span
                                    >
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
                                    <span class="text-muted-foreground"
                                        >(optional)</span
                                    >
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
                                    <span class="text-muted-foreground"
                                        >(optional)</span
                                    >
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
                                    <span class="text-muted-foreground"
                                        >(optional)</span
                                    >
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
                                <span class="text-muted-foreground"
                                    >(optional)</span
                                >
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

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <Link :href="usersRoutes.index().url">
                            <Button
                                type="button"
                                label="Cancel"
                                severity="secondary"
                                text
                                :disabled="form.processing"
                            />
                        </Link>
                        <Button
                            type="submit"
                            label="Create user"
                            :loading="form.processing"
                        />
                    </div>
                </form>
            </template>
        </Card>
    </div>
</template>
