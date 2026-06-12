<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import ApplicationController from '@/actions/App/Http/Controllers/Applications/ApplicationController';
import { degreeLabel, statusLabel, statusSeverity } from '@/lib/statusDisplay';
import applicant from '@/routes/applicant';

type Department = { id: number; name: string; code: string };
type DocumentTypeRef = { id: number; name: string; code: string };

type ProgramOffering = {
    id: number;
    degree_program: string;
    department: Department;
};

type ApplicationDocument = {
    id: number;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    uploaded_at: string | null;
    document_type: DocumentTypeRef;
};

type Application = {
    id: number;
    status: string;
    level: number;
    first_name: string;
    last_name: string;
    contact_email: string;
    phone: string;
    date_of_birth: string | null;
    previous_institute: string | null;
    submitted_at: string | null;
    decided_at: string | null;
    decision_notes: string | null;
    program_offering: ProgramOffering;
    documents: ApplicationDocument[];
};

const props = defineProps<{ application: Application }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Applicant Dashboard', href: applicant.dashboard() },
            {
                title: 'Application',
                href: ApplicationController.show(0).url,
            },
        ],
    },
});

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}
</script>

<template>
    <Head :title="`Application #${props.application.id}`" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <FileText class="size-5" />
                        <span>Application #{{ props.application.id }}</span>
                    </div>
                    <Tag
                        :value="statusLabel(props.application.status)"
                        :severity="statusSeverity(props.application.status)"
                    />
                </div>
            </template>
            <template #content>
                <dl class="grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground uppercase">
                            Department
                        </dt>
                        <dd>
                            {{
                                props.application.program_offering.department
                                    .name
                            }}
                            <span class="text-xs text-muted-foreground">
                                ({{
                                    props.application.program_offering
                                        .department.code
                                }})
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground uppercase">
                            Programme
                        </dt>
                        <dd>
                            {{
                                degreeLabel(
                                    props.application.program_offering
                                        .degree_program,
                                )
                            }}
                            · Level {{ props.application.level }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground uppercase">
                            Applicant
                        </dt>
                        <dd>
                            {{ props.application.first_name }}
                            {{ props.application.last_name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground uppercase">
                            Date of birth
                        </dt>
                        <dd>{{ props.application.date_of_birth ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground uppercase">
                            Contact email
                        </dt>
                        <dd>{{ props.application.contact_email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground uppercase">
                            Phone
                        </dt>
                        <dd>{{ props.application.phone }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground uppercase">
                            Previous institute
                        </dt>
                        <dd>
                            {{ props.application.previous_institute ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground uppercase">
                            Submitted at
                        </dt>
                        <dd>
                            {{ formatDateTime(props.application.submitted_at) }}
                        </dd>
                    </div>
                    <div v-if="props.application.decided_at">
                        <dt class="text-xs text-muted-foreground uppercase">
                            Decided at
                        </dt>
                        <dd>
                            {{ formatDateTime(props.application.decided_at) }}
                        </dd>
                    </div>
                    <div
                        v-if="props.application.decision_notes"
                        class="md:col-span-2"
                    >
                        <dt class="text-xs text-muted-foreground uppercase">
                            Decision notes
                        </dt>
                        <dd>{{ props.application.decision_notes }}</dd>
                    </div>
                </dl>
            </template>
        </Card>

        <Card>
            <template #title>
                <span>Submitted documents</span>
            </template>
            <template #content>
                <DataTable
                    :value="props.application.documents"
                    data-key="id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column header="Document">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span>{{ data.document_type.name }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ data.document_type.code }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column field="original_filename" header="Filename" />
                    <Column header="Size" style="width: 8rem">
                        <template #body="{ data }">
                            {{ formatBytes(data.size_bytes) }}
                        </template>
                    </Column>
                    <Column header="Uploaded" style="width: 12rem">
                        <template #body="{ data }">
                            {{ formatDateTime(data.uploaded_at) }}
                        </template>
                    </Column>
                    <template #empty>
                        <p
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            No documents on file.
                        </p>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
