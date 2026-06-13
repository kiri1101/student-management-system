<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed } from 'vue';
import StaffProfileController from '@/actions/App/Http/Controllers/Settings/StaffProfileController';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/staff-profile';

type Department = { id: number; name: string; code: string };

type Profile = {
    department_id?: number | null;
    specialization?: string | null;
    hired_at?: string | null;
    bank_desk?: string | null;
    cashier_window?: string | null;
    scope?: string | null;
};

const props = defineProps<{
    role: 'lecturer' | 'accountant' | 'sao';
    roleLabel: string;
    profile: Profile;
    options: { departments: Department[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Staff profile settings',
                href: edit(),
            },
        ],
    },
});

const isLecturer = computed(() => props.role === 'lecturer');
const isAccountant = computed(() => props.role === 'accountant');
const isSao = computed(() => props.role === 'sao');

function parseDate(value: string | null | undefined): Date | null {
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
    department_id: props.profile.department_id ?? null,
    specialization: props.profile.specialization ?? '',
    hired_at: parseDate(props.profile.hired_at),
    bank_desk: props.profile.bank_desk ?? '',
    cashier_window: props.profile.cashier_window ?? '',
    scope: props.profile.scope ?? '',
});

function submit(): void {
    form.transform((data) => {
        if (props.role === 'lecturer') {
            return {
                department_id: data.department_id,
                specialization: data.specialization || null,
                hired_at: data.hired_at ? formatHiredAt(data.hired_at) : null,
            };
        }

        if (props.role === 'accountant') {
            return {
                bank_desk: data.bank_desk || null,
                cashier_window: data.cashier_window || null,
            };
        }

        return {
            scope: data.scope || null,
        };
    }).patch(StaffProfileController.update().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Staff profile settings" />

    <h1 class="sr-only">Staff profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="`${roleLabel} profile`"
            description="Update the profile fields for your staff role. Your name and email are managed on the Profile tab."
        />

        <form class="space-y-6" @submit.prevent="submit">
            <template v-if="isLecturer">
                <div class="grid gap-2">
                    <label
                        for="lecturer-department"
                        class="text-sm font-medium"
                    >
                        Department
                    </label>
                    <Select
                        id="lecturer-department"
                        v-model="form.department_id"
                        :options="options.departments"
                        option-label="name"
                        option-value="id"
                        placeholder="Select a department"
                        class="w-full"
                        show-clear
                        :invalid="!!form.errors.department_id"
                    />
                    <p
                        v-if="form.errors.department_id"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.department_id }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <label
                        for="lecturer-specialization"
                        class="text-sm font-medium"
                    >
                        Specialization
                    </label>
                    <InputText
                        id="lecturer-specialization"
                        v-model="form.specialization"
                        class="w-full"
                        :invalid="!!form.errors.specialization"
                    />
                    <p
                        v-if="form.errors.specialization"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.specialization }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <label for="lecturer-hired" class="text-sm font-medium">
                        Hired on
                    </label>
                    <DatePicker
                        id="lecturer-hired"
                        v-model="form.hired_at"
                        :max-date="new Date()"
                        show-icon
                        icon-display="input"
                        date-format="yy-mm-dd"
                        class="w-full"
                        :invalid="!!form.errors.hired_at"
                    />
                    <p
                        v-if="form.errors.hired_at"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.hired_at }}
                    </p>
                </div>
            </template>

            <template v-else-if="isAccountant">
                <div class="grid gap-2">
                    <label
                        for="accountant-bank-desk"
                        class="text-sm font-medium"
                    >
                        Bank desk
                    </label>
                    <InputText
                        id="accountant-bank-desk"
                        v-model="form.bank_desk"
                        class="w-full"
                        :invalid="!!form.errors.bank_desk"
                    />
                    <p
                        v-if="form.errors.bank_desk"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.bank_desk }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <label for="accountant-window" class="text-sm font-medium">
                        Cashier window
                    </label>
                    <InputText
                        id="accountant-window"
                        v-model="form.cashier_window"
                        class="w-full"
                        :invalid="!!form.errors.cashier_window"
                    />
                    <p
                        v-if="form.errors.cashier_window"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.cashier_window }}
                    </p>
                </div>
            </template>

            <template v-else-if="isSao">
                <div class="grid gap-2">
                    <label for="sao-scope" class="text-sm font-medium">
                        Scope
                    </label>
                    <InputText
                        id="sao-scope"
                        v-model="form.scope"
                        placeholder="e.g. Admissions, Records"
                        class="w-full"
                        :invalid="!!form.errors.scope"
                    />
                    <p
                        v-if="form.errors.scope"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.scope }}
                    </p>
                </div>
            </template>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    label="Save"
                    :loading="form.processing"
                    data-test="update-staff-profile-button"
                />
            </div>
        </form>
    </div>
</template>
