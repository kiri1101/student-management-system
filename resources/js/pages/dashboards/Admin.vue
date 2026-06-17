<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Database,
    FileText,
    GraduationCap,
    History,
    Users,
} from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { computed, ref } from 'vue';
import AuditLogModal from '@/components/admin/AuditLogModal.vue';
import StatCard from '@/components/StatCard.vue';
import { degreeLabel } from '@/lib/statusDisplay';
import admin from '@/routes/admin';
import adminUsers from '@/routes/admin/users';

type RoleCount = { role: string; label: string; count: number };
type StatusCount = { status: string; label: string; count: number };

type RecentAdmission = {
    id: number;
    matricule: string;
    level: number;
    academic_year: string | null;
    enrolled_at: string | null;
    user: { id: number; name: string; email: string };
    program_offering: {
        id: number;
        degree_program: string;
        department: { id: number; name: string; code: string };
    };
};

defineProps<{
    totals: { users: number; applications: number; student_profiles: number };
    usersByRole: RoleCount[];
    applicationsByStatus: StatusCount[];
    recentAdmissions: RecentAdmission[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin Dashboard', href: admin.dashboard() }],
    },
});

const page = usePage();
const firstName = computed<string>(
    () => (page.props.auth?.user?.name ?? '').split(' ')[0] ?? '',
);

const auditModalVisible = ref(false);

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}

function openAuditLog(): void {
    auditModalVisible.value = true;
}
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
        <!-- Hero -->
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Administrator
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    Welcome back<template v-if="firstName"
                        >, {{ firstName }}</template
                    >
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Manage reference data, monitor admissions, and review every
                    audited event across the system.
                </p>
            </div>
            <Button
                label="Open audit log"
                severity="secondary"
                @click="openAuditLog"
            >
                <template #icon>
                    <History class="size-4" />
                </template>
            </Button>
        </section>

        <!-- Totals -->
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <StatCard
                label="Users"
                :value="totals.users"
                :icon="Users"
                tone="blue"
            />
            <StatCard
                label="Applications"
                :value="totals.applications"
                :icon="FileText"
                tone="violet"
            />
            <StatCard
                label="Student profiles"
                :value="totals.student_profiles"
                :icon="GraduationCap"
                tone="emerald"
            />
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <Users class="size-5 text-muted-foreground" />
                        <span class="text-base font-semibold"
                            >Users by role</span
                        >
                    </div>
                </template>
                <template #content>
                    <ul class="divide-y divide-border">
                        <li
                            v-for="row in usersByRole"
                            :key="row.role"
                            class="flex items-center justify-between py-2.5 text-sm"
                        >
                            <span>{{ row.label }}</span>
                            <span class="font-mono font-semibold">{{
                                row.count
                            }}</span>
                        </li>
                    </ul>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <FileText class="size-5 text-muted-foreground" />
                        <span class="text-base font-semibold"
                            >Applications by status</span
                        >
                    </div>
                </template>
                <template #content>
                    <ul class="divide-y divide-border">
                        <li
                            v-for="row in applicationsByStatus"
                            :key="row.status"
                            class="flex items-center justify-between py-2.5 text-sm"
                        >
                            <span>{{ row.label }}</span>
                            <span class="font-mono font-semibold">{{
                                row.count
                            }}</span>
                        </li>
                    </ul>
                </template>
            </Card>
        </div>

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <GraduationCap class="size-5 text-muted-foreground" />
                    <span class="text-base font-semibold"
                        >Recent admissions</span
                    >
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="recentAdmissions"
                    data-key="id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column header="Matricule" style="width: 11rem">
                        <template #body="{ data }">
                            <span class="font-mono text-sm">
                                {{ data.matricule }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Student">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{ data.user.name }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ data.user.email }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Programme">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>
                                    {{ data.program_offering.department.name }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        degreeLabel(
                                            data.program_offering
                                                .degree_program,
                                        )
                                    }}
                                    · L{{ data.level }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column
                        header="Academic year"
                        field="academic_year"
                        style="width: 9rem"
                    >
                        <template #body="{ data }">
                            <span>{{ data.academic_year ?? '—' }}</span>
                        </template>
                    </Column>
                    <Column header="Enrolled" style="width: 9rem">
                        <template #body="{ data }">
                            <span class="text-muted-foreground">{{
                                formatDate(data.enrolled_at)
                            }}</span>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No student profiles yet — admissions will appear
                            here.
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>

        <!-- Quick links -->
        <div class="grid gap-4 md:grid-cols-3">
            <Link
                :href="adminUsers.index().url"
                class="block focus:outline-none"
            >
                <Card class="h-full transition-colors hover:bg-muted/50">
                    <template #title>
                        <div class="flex items-center gap-2">
                            <Users class="size-5 text-primary-600" />
                            <span class="text-base font-semibold"
                                >Manage users</span
                            >
                        </div>
                    </template>
                    <template #content>
                        <p class="text-sm text-muted-foreground">
                            Provision lecturers, accountants, SAOs, and other
                            admins. Send invites, change roles, deactivate.
                        </p>
                    </template>
                </Card>
            </Link>

            <Link
                :href="admin.references.index().url"
                class="block focus:outline-none"
            >
                <Card class="h-full transition-colors hover:bg-muted/50">
                    <template #title>
                        <div class="flex items-center gap-2">
                            <Database class="size-5 text-primary-600" />
                            <span class="text-base font-semibold"
                                >Reference data</span
                            >
                        </div>
                    </template>
                    <template #content>
                        <p class="text-sm text-muted-foreground">
                            Departments, program offerings, document types,
                            level requirements.
                        </p>
                    </template>
                </Card>
            </Link>

            <Card class="h-full">
                <template #title>
                    <div class="flex items-center gap-2">
                        <BookOpen class="size-5 text-primary-600" />
                        <span class="text-base font-semibold">Audit log</span>
                    </div>
                </template>
                <template #content>
                    <p class="text-sm text-muted-foreground">
                        Inspect every recorded event with filters by actor,
                        action, subject type, and date range.
                    </p>
                </template>
                <template #footer>
                    <Button
                        label="Open audit log"
                        size="small"
                        @click="openAuditLog"
                    >
                        <template #icon>
                            <History class="size-4" />
                        </template>
                    </Button>
                </template>
            </Card>
        </div>

        <AuditLogModal v-model:visible="auditModalVisible" />
    </div>
</template>
