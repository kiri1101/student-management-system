<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, History } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
import ApplicationReviewController from '@/actions/App/Http/Controllers/Sao/ApplicationReviewController';
import { degreeLabel, statusLabel, statusSeverity } from '@/lib/statusDisplay';
import application_routes from '@/routes/application';
import sao from '@/routes/sao';

type Department = { id: number; name: string; code: string };
type ProgramOffering = {
    id: number;
    degree_program: string;
    department: Department;
};
type DocumentType = { id: number; name: string; code: string };
type DocumentRow = {
    id: number;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    uploaded_at: string | null;
    document_type: DocumentType;
};
type Applicant = { id: number; name: string; email: string };
type DecidedBy = { id: number; name: string } | null;

type ApplicationDetail = {
    id: number;
    status: string;
    is_terminal: boolean;
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
    applicant: Applicant;
    decided_by: DecidedBy;
    program_offering: ProgramOffering;
    documents: DocumentRow[];
};

type PriorProfile = {
    id: number;
    matricule: string;
    level: number;
    academic_year: string | null;
    status: string;
    enrolled_at: string | null;
    deleted_at: string | null;
};

type PriorApplication = {
    id: number;
    status: string;
    submitted_at: string | null;
    decided_at: string | null;
    decision_notes: string | null;
};

type PriorHistory = {
    profiles: PriorProfile[];
    applications: PriorApplication[];
};

const props = defineProps<{
    application: ApplicationDetail;
    priorHistory: PriorHistory;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'SAO Dashboard', href: sao.dashboard() },
            { title: 'Applications', href: sao.applications.index() },
        ],
    },
});

const TRIAGE_OPTIONS = [
    { value: 'submitted', label: 'Submitted' },
    { value: 'under_review', label: 'Under review' },
    { value: 'documents_requested', label: 'Documents requested' },
];

const DECIDE_OPTIONS = [
    { value: 'admitted', label: 'Admit' },
    { value: 'rejected', label: 'Reject' },
    { value: 'waitlisted', label: 'Waitlist' },
];

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

const hasPriorHistory = computed(
    () =>
        props.priorHistory.profiles.length > 0 ||
        props.priorHistory.applications.length > 0,
);

const trashedProfiles = computed(() =>
    props.priorHistory.profiles.filter((p) => p.deleted_at !== null),
);

const triageForm = useForm({
    status: props.application.status,
    notes: '',
});

const decideForm = useForm({
    status: 'admitted',
    notes: '',
    acknowledged_prior_history: false,
});

const restoreForm = useForm({
    prior_profile_id: trashedProfiles.value[0]?.id ?? null,
    notes: '',
});

const decideNotesRequired = computed(() =>
    ['rejected', 'waitlisted'].includes(decideForm.status),
);

const triageNotesRequired = computed(
    () => triageForm.status === 'documents_requested',
);

const decideRef = ref<HTMLElement | null>(null);

function submitTriage(): void {
    triageForm.post(
        ApplicationReviewController.triage(props.application.id).url,
        {
            preserveScroll: true,
            onSuccess: () => triageForm.reset('notes'),
        },
    );
}

function submitDecide(): void {
    decideForm.post(
        ApplicationReviewController.decide(props.application.id).url,
        {
            preserveScroll: true,
        },
    );
}

function submitRestore(): void {
    restoreForm.post(
        ApplicationReviewController.restorePrior(props.application.id).url,
        { preserveScroll: true },
    );
}

function focusDecide(): void {
    decideForm.acknowledged_prior_history = true;
    decideRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head :title="`Review · #${application.id}`" />

    <div class="space-y-4 p-4">
        <div class="flex items-center justify-between">
            <Link :href="sao.applications.index().url">
                <Button label="Back to queue" text size="small">
                    <template #icon>
                        <ArrowLeft class="size-4" />
                    </template>
                </Button>
            </Link>
            <Tag
                :value="statusLabel(application.status)"
                :severity="statusSeverity(application.status)"
            />
        </div>

        <Card>
            <template #title>Applicant</template>
            <template #content>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <div class="text-xs text-muted-foreground">Name</div>
                        <div>
                            {{ application.first_name }}
                            {{ application.last_name }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">
                            Account email
                        </div>
                        <div>{{ application.applicant.email }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">
                            Contact email
                        </div>
                        <div>{{ application.contact_email }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Phone</div>
                        <div>{{ application.phone }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">
                            Date of birth
                        </div>
                        <div>{{ formatDate(application.date_of_birth) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">
                            Previous institute
                        </div>
                        <div>{{ application.previous_institute ?? '—' }}</div>
                    </div>
                </div>
            </template>
        </Card>

        <Message
            v-if="hasPriorHistory"
            severity="warn"
            :closable="false"
            class="!my-0"
        >
            <div class="space-y-3">
                <div class="flex items-center gap-2 font-medium">
                    <History class="size-4" />
                    <span>This applicant has prior history</span>
                </div>

                <div
                    v-if="priorHistory.profiles.length > 0"
                    class="space-y-1 text-sm"
                >
                    <div class="font-medium">Previous enrollment(s)</div>
                    <ul class="ml-4 list-disc">
                        <li
                            v-for="profile in priorHistory.profiles"
                            :key="profile.id"
                        >
                            <span class="font-mono">{{
                                profile.matricule
                            }}</span>
                            · level {{ profile.level }} · {{ profile.status }}
                            <span v-if="profile.deleted_at">
                                · archived {{ formatDate(profile.deleted_at) }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="priorHistory.applications.length > 0"
                    class="space-y-1 text-sm"
                >
                    <div class="font-medium">Previous application(s)</div>
                    <ul class="ml-4 list-disc">
                        <li
                            v-for="prior in priorHistory.applications"
                            :key="prior.id"
                        >
                            #{{ prior.id }} · {{ statusLabel(prior.status) }} ·
                            decided {{ formatDate(prior.decided_at) }}
                        </li>
                    </ul>
                </div>

                <div
                    v-if="
                        trashedProfiles.length > 0 && !application.is_terminal
                    "
                    class="flex flex-wrap items-end gap-2"
                >
                    <div>
                        <div class="text-xs">Restore which profile?</div>
                        <Select
                            v-model="restoreForm.prior_profile_id"
                            :options="trashedProfiles"
                            option-label="matricule"
                            option-value="id"
                            class="w-64"
                        />
                    </div>
                    <Button
                        label="Restore prior enrollment"
                        size="small"
                        :loading="restoreForm.processing"
                        :disabled="!restoreForm.prior_profile_id"
                        @click="submitRestore"
                    />
                    <Button
                        label="Admit as new student"
                        severity="secondary"
                        size="small"
                        @click="focusDecide"
                    />
                </div>
            </div>
        </Message>

        <Card>
            <template #title>Programme</template>
            <template #content>
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <div class="text-xs text-muted-foreground">
                            Department
                        </div>
                        <div>
                            {{ application.program_offering.department.name }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">
                            Programme
                        </div>
                        <div>
                            {{
                                degreeLabel(
                                    application.program_offering.degree_program,
                                )
                            }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Level</div>
                        <div>{{ application.level }}</div>
                    </div>
                </div>
            </template>
        </Card>

        <Card>
            <template #title>Documents</template>
            <template #content>
                <DataTable
                    :value="application.documents"
                    data-key="id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column header="Type">
                        <template #body="{ data }">
                            <span class="font-mono text-xs">{{
                                data.document_type.code
                            }}</span>
                            ·
                            <span>{{ data.document_type.name }}</span>
                        </template>
                    </Column>
                    <Column field="original_filename" header="Filename" />
                    <Column header="Size" style="width: 8rem">
                        <template #body="{ data }">
                            {{ formatSize(data.size_bytes) }}
                        </template>
                    </Column>
                    <Column header="" style="width: 8rem">
                        <template #body="{ data }">
                            <a
                                :href="
                                    application_routes.documents.download({
                                        application: application.id,
                                        document: data.id,
                                    }).url
                                "
                            >
                                <Button
                                    label="Download"
                                    severity="secondary"
                                    text
                                    size="small"
                                >
                                    <template #icon>
                                        <Download class="size-4" />
                                    </template>
                                </Button>
                            </a>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <Card v-if="application.is_terminal">
            <template #title>Decision</template>
            <template #content>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-muted-foreground">Outcome:</span>
                        <Tag
                            class="ml-2"
                            :value="statusLabel(application.status)"
                            :severity="statusSeverity(application.status)"
                        />
                    </div>
                    <div>
                        <span class="text-muted-foreground">Decided at:</span>
                        {{ formatDateTime(application.decided_at) }}
                    </div>
                    <div v-if="application.decided_by">
                        <span class="text-muted-foreground">Decided by:</span>
                        {{ application.decided_by.name }}
                    </div>
                    <div v-if="application.decision_notes">
                        <div class="text-muted-foreground">Notes</div>
                        <div class="whitespace-pre-wrap">
                            {{ application.decision_notes }}
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <div v-else class="grid gap-4 md:grid-cols-2">
            <Card>
                <template #title>Triage</template>
                <template #content>
                    <form class="space-y-3" @submit.prevent="submitTriage">
                        <div class="space-y-1">
                            <label class="text-sm">Status</label>
                            <Select
                                v-model="triageForm.status"
                                :options="TRIAGE_OPTIONS"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm">
                                Notes
                                <span
                                    v-if="triageNotesRequired"
                                    class="text-red-500"
                                >
                                    (required)
                                </span>
                            </label>
                            <Textarea
                                v-model="triageForm.notes"
                                rows="4"
                                class="w-full"
                            />
                            <div
                                v-if="triageForm.errors.notes"
                                class="text-xs text-red-500"
                            >
                                {{ triageForm.errors.notes }}
                            </div>
                        </div>
                        <Button
                            type="submit"
                            label="Update status"
                            size="small"
                            :loading="triageForm.processing"
                        />
                    </form>
                </template>
            </Card>

            <Card>
                <template #title>
                    <span ref="decideRef">Decide</span>
                </template>
                <template #content>
                    <form class="space-y-3" @submit.prevent="submitDecide">
                        <div class="space-y-1">
                            <label class="text-sm">Decision</label>
                            <Select
                                v-model="decideForm.status"
                                :options="DECIDE_OPTIONS"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm">
                                Notes
                                <span
                                    v-if="decideNotesRequired"
                                    class="text-red-500"
                                >
                                    (required)
                                </span>
                            </label>
                            <Textarea
                                v-model="decideForm.notes"
                                rows="4"
                                class="w-full"
                            />
                            <div
                                v-if="decideForm.errors.notes"
                                class="text-xs text-red-500"
                            >
                                {{ decideForm.errors.notes }}
                            </div>
                        </div>
                        <label
                            v-if="hasPriorHistory"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="decideForm.acknowledged_prior_history"
                                type="checkbox"
                            />
                            <span>I have reviewed the prior history</span>
                        </label>
                        <Button
                            type="submit"
                            label="Submit decision"
                            size="small"
                            severity="primary"
                            :loading="decideForm.processing"
                        />
                    </form>
                </template>
            </Card>
        </div>
    </div>
</template>
