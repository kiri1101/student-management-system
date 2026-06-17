<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    Clock,
    FileSearch,
    FileText,
    Plus,
    Send,
} from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import ApplicationController from '@/actions/App/Http/Controllers/Applications/ApplicationController';
import StatCard from '@/components/StatCard.vue';
import { degreeLabel, statusLabel, statusSeverity } from '@/lib/statusDisplay';
import applicant from '@/routes/applicant';

type Department = { id: number; name: string; code: string };

type ProgramOffering = {
    id: number;
    degree_program: string;
    department: Department;
};

type Application = {
    id: number;
    status: string;
    level: number;
    submitted_at: string | null;
    created_at: string | null;
    program_offering: ProgramOffering;
};

const props = defineProps<{ applications: Application[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Applicant Dashboard', href: applicant.dashboard() },
        ],
    },
});

const page = usePage();

const firstName = computed<string>(() => {
    const name = page.props.auth?.user?.name ?? '';

    return name.split(' ')[0] ?? '';
});

const summary = computed(() => {
    const counts = { submitted: 0, inReview: 0, admitted: 0, rejected: 0 };

    for (const application of props.applications) {
        if (application.status === 'submitted') {
            counts.submitted += 1;
        } else if (
            application.status === 'under_review' ||
            application.status === 'documents_requested'
        ) {
            counts.inReview += 1;
        } else if (application.status === 'admitted') {
            counts.admitted += 1;
        } else if (application.status === 'rejected') {
            counts.rejected += 1;
        }
    }

    return counts;
});

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head title="Applicant Dashboard" />

    <div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
        <!-- Hero -->
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Welcome back<template v-if="firstName"
                        >, {{ firstName }}</template
                    >
                    👋
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Track your admission applications below.
                </p>
            </div>
            <Link :href="ApplicationController.create().url">
                <Button label="New application">
                    <template #icon>
                        <Plus class="size-4" />
                    </template>
                </Button>
            </Link>
        </section>

        <!-- Status-summary chips -->
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard
                label="Submitted"
                :value="summary.submitted"
                :icon="Send"
                tone="blue"
            />
            <StatCard
                label="In review"
                :value="summary.inReview"
                :icon="Clock"
                tone="amber"
            />
            <StatCard
                label="Admitted"
                :value="summary.admitted"
                :icon="CircleCheck"
                tone="emerald"
            />
            <StatCard
                label="Rejected"
                :value="summary.rejected"
                :icon="CircleX"
                tone="red"
            />
        </section>

        <!-- Applications table -->
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <FileText class="size-5 text-muted-foreground" />
                    <span class="text-base font-semibold">My applications</span>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="applications"
                    data-key="id"
                    striped-rows
                    :paginator="applications.length > 10"
                    :rows="10"
                    :rows-per-page-options="[10, 25, 50]"
                    responsive-layout="scroll"
                >
                    <Column
                        header="Department"
                        sortable
                        sort-field="program_offering.department.name"
                    >
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{
                                    data.program_offering.department.name
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    data.program_offering.department.code
                                }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column
                        header="Programme"
                        sortable
                        sort-field="program_offering.degree_program"
                    >
                        <template #body="{ data }">
                            <span>{{
                                degreeLabel(
                                    data.program_offering.degree_program,
                                )
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="level"
                        header="Level"
                        sortable
                        style="width: 6rem"
                    />
                    <Column
                        header="Status"
                        sortable
                        sort-field="status"
                        style="width: 11rem"
                    >
                        <template #body="{ data }">
                            <Tag
                                :value="statusLabel(data.status)"
                                :severity="statusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column
                        header="Submitted"
                        sortable
                        sort-field="submitted_at"
                        style="width: 9rem"
                    >
                        <template #body="{ data }">
                            <span class="text-muted-foreground">{{
                                formatDate(data.submitted_at)
                            }}</span>
                        </template>
                    </Column>
                    <Column header="" style="width: 6rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end">
                                <Link
                                    :href="
                                        ApplicationController.show(data.id).url
                                    "
                                >
                                    <Button
                                        label="View"
                                        severity="secondary"
                                        text
                                        size="small"
                                    />
                                </Link>
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div
                            class="flex flex-col items-center gap-4 py-12 text-center"
                        >
                            <span
                                class="grid size-16 place-items-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400"
                            >
                                <FileSearch class="size-8" />
                            </span>
                            <div>
                                <p class="text-base font-semibold">
                                    You haven't submitted any applications yet
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Start your first application to apply for
                                    admission.
                                </p>
                            </div>
                            <Link :href="ApplicationController.create().url">
                                <Button label="Start your first application">
                                    <template #icon>
                                        <Plus class="size-4" />
                                    </template>
                                </Button>
                            </Link>
                        </div>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
