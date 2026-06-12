<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Plus } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import ApplicationController from '@/actions/App/Http/Controllers/Applications/ApplicationController';
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

defineProps<{ applications: Application[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Applicant Dashboard', href: applicant.dashboard() },
        ],
    },
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

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <FileText class="size-5" />
                        <span>My applications</span>
                    </div>
                    <Link :href="ApplicationController.create().url">
                        <Button label="Start a new application" size="small">
                            <template #icon>
                                <Plus class="size-4" />
                            </template>
                        </Button>
                    </Link>
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
                            <span>{{ formatDate(data.submitted_at) }}</span>
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
                        <div class="space-y-2 py-6 text-center">
                            <p class="text-sm text-muted-foreground">
                                You have not submitted any applications yet.
                            </p>
                            <Link :href="ApplicationController.create().url">
                                <Button
                                    label="Start a new application"
                                    size="small"
                                >
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
