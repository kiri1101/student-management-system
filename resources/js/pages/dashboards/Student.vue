<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, GraduationCap } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import ApplicationController from '@/actions/App/Http/Controllers/Applications/ApplicationController';
import {
    degreeLabel,
    enrollmentLabel,
    enrollmentSeverity,
    statusLabel,
    statusSeverity,
} from '@/lib/statusDisplay';
import student from '@/routes/student';

type Department = { name: string; code: string } | null;

type ProgramOffering = {
    degree_program: string;
    department: Department;
} | null;

type Profile = {
    matricule: string;
    level: number;
    academic_year: string;
    status: string;
    enrolled_at: string | null;
    program_offering: ProgramOffering;
} | null;

type Application = {
    id: number;
    status: string;
    level: number;
    submitted_at: string | null;
    decided_at: string | null;
    program_offering: ProgramOffering;
};

defineProps<{ profile: Profile; applications: Application[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Student Dashboard',
                href: student.dashboard(),
            },
        ],
    },
});

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}

function programmeLine(offering: ProgramOffering): string {
    if (!offering) {
        return '—';
    }

    const degree = degreeLabel(offering.degree_program);

    return offering.department
        ? `${offering.department.name} (${offering.department.code}) — ${degree}`
        : degree;
}
</script>

<template>
    <Head title="Student Dashboard" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <GraduationCap class="size-5" />
                        <span>My enrollment</span>
                    </div>
                    <Tag
                        v-if="profile"
                        :value="enrollmentLabel(profile.status)"
                        :severity="enrollmentSeverity(profile.status)"
                        class="capitalize"
                    />
                </div>
            </template>
            <template #content>
                <dl
                    v-if="profile"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">Matricule</dt>
                        <dd class="font-mono text-sm font-semibold uppercase">
                            {{ profile.matricule }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Programme</dt>
                        <dd class="text-sm">
                            {{ programmeLine(profile.program_offering) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Level</dt>
                        <dd class="text-sm">{{ profile.level }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Academic year
                        </dt>
                        <dd class="text-sm">
                            {{ profile.academic_year }}
                            <span
                                v-if="profile.enrolled_at"
                                class="text-muted-foreground"
                            >
                                · enrolled {{ formatDate(profile.enrolled_at) }}
                            </span>
                        </dd>
                    </div>
                </dl>
                <p v-else class="text-sm text-muted-foreground">
                    No enrollment record found. Contact the student affairs
                    office if you believe this is an error.
                </p>
            </template>
        </Card>

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <FileText class="size-5" />
                    <span>Application history</span>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="applications"
                    data-key="id"
                    striped-rows
                    :paginator="applications.length > 10"
                    :rows="10"
                    responsive-layout="scroll"
                >
                    <Column header="Programme">
                        <template #body="{ data }">
                            <span>{{
                                programmeLine(data.program_offering)
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
                    <Column
                        header="Decided"
                        sortable
                        sort-field="decided_at"
                        style="width: 9rem"
                    >
                        <template #body="{ data }">
                            <span>{{ formatDate(data.decided_at) }}</span>
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
                        <p
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No applications on record.
                        </p>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
