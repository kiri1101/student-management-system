<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CloudUpload, Send } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import DatePicker from 'primevue/datepicker';
import FileUpload from 'primevue/fileupload';
import InputMask from 'primevue/inputmask';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import { computed, ref, watch } from 'vue';
import ApplicationController from '@/actions/App/Http/Controllers/Applications/ApplicationController';
import applicant from '@/routes/applicant';

type DegreeProgramOption = { value: string; label: string };
type Department = { id: number; name: string; code: string };
type DocumentTypeRef = { id: number; name: string; code: string };

type ProgramOffering = {
    id: number;
    department_id: number;
    department: Department;
    degree_program: string;
    min_level: number;
    max_level: number;
};

type LevelRequirement = {
    id: number;
    level: number;
    required: boolean;
    document_type: DocumentTypeRef;
};

const props = defineProps<{
    degreePrograms: DegreeProgramOption[];
    departments: Department[];
    alwaysRequiredDocumentTypes: DocumentTypeRef[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Applicant Dashboard', href: applicant.dashboard() },
            {
                title: 'New application',
                href: ApplicationController.create().url,
            },
        ],
    },
});

const offerings = ref<ProgramOffering[]>([]);
const offeringsLoading = ref(false);
const offeringsError = ref(false);
const levelRequirements = ref<LevelRequirement[]>([]);
const levelRequirementsLoading = ref(false);
const levelRequirementsError = ref(false);

const form = useForm({
    program_offering_id: null as number | null,
    level: null as number | null,
    first_name: '',
    last_name: '',
    contact_email: '',
    phone: '',
    date_of_birth: null as Date | null,
    previous_institute: '',
    degree_program: '' as string,
    department_id: null as number | null,
    documents: {} as Record<string, File | null>,
});

const availableDepartments = computed<Department[]>(() => {
    if (!form.degree_program) {
        return [];
    }

    const ids = new Set(
        offerings.value.map((offering) => offering.department_id),
    );

    return props.departments.filter((department) => ids.has(department.id));
});

const selectedOffering = computed<ProgramOffering | null>(() => {
    if (!form.degree_program || !form.department_id) {
        return null;
    }

    return (
        offerings.value.find(
            (offering) => offering.department_id === form.department_id,
        ) ?? null
    );
});

const requiredDocumentTypes = computed<DocumentTypeRef[]>(() => {
    const seen = new Set<string>();
    const documents: DocumentTypeRef[] = [];

    for (const documentType of props.alwaysRequiredDocumentTypes) {
        if (!seen.has(documentType.code)) {
            seen.add(documentType.code);
            documents.push(documentType);
        }
    }

    for (const requirement of levelRequirements.value) {
        const documentType = requirement.document_type;

        if (!seen.has(documentType.code)) {
            seen.add(documentType.code);
            documents.push(documentType);
        }
    }

    return documents;
});

async function fetchJson<T>(
    path: string,
    params: Record<string, string | number>,
): Promise<T> {
    const url = new URL(path, window.location.origin);

    for (const [key, value] of Object.entries(params)) {
        url.searchParams.set(key, String(value));
    }

    const response = await fetch(url.toString(), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    return (await response.json()) as T;
}

async function loadOfferings(): Promise<void> {
    if (!form.degree_program) {
        return;
    }

    offeringsLoading.value = true;
    offeringsError.value = false;

    try {
        offerings.value = await fetchJson<ProgramOffering[]>(
            '/api/v1/program-offerings',
            { degree_program: form.degree_program },
        );
    } catch {
        offeringsError.value = true;
    } finally {
        offeringsLoading.value = false;
    }
}

async function loadLevelRequirements(): Promise<void> {
    if (!form.level || !form.program_offering_id) {
        return;
    }

    levelRequirementsLoading.value = true;
    levelRequirementsError.value = false;

    try {
        levelRequirements.value = await fetchJson<LevelRequirement[]>(
            '/api/v1/level-requirements',
            { offering: form.program_offering_id, level: form.level },
        );
    } catch {
        levelRequirementsError.value = true;
    } finally {
        levelRequirementsLoading.value = false;
    }
}

watch(
    () => form.degree_program,
    async (next) => {
        form.department_id = null;
        form.program_offering_id = null;
        form.level = null;
        levelRequirements.value = [];
        form.documents = {};
        offeringsError.value = false;

        if (!next) {
            offerings.value = [];

            return;
        }

        await loadOfferings();
    },
);

watch(
    () => form.department_id,
    (next) => {
        form.level = null;
        levelRequirements.value = [];
        form.documents = {};

        if (!next || !selectedOffering.value) {
            form.program_offering_id = null;

            return;
        }

        form.program_offering_id = selectedOffering.value.id;
    },
);

watch(
    () => form.level,
    async (next) => {
        form.documents = {};
        levelRequirementsError.value = false;

        if (!next || !form.program_offering_id) {
            levelRequirements.value = [];

            return;
        }

        await loadLevelRequirements();
    },
);

function onFileSelect(code: string, event: { files: File[] }): void {
    const file = event.files?.[0];

    if (!file) {
        return;
    }

    form.documents = { ...form.documents, [code]: file };
}

function onFileClear(code: string): void {
    const next = { ...form.documents };
    delete next[code];
    form.documents = next;
}

/**
 * Format from local date components: `toISOString()` converts the picker's
 * local-midnight Date to UTC, shifting every UTC+ birth date (Cameroon is
 * UTC+1) one day early (AUDIT.md AUD-015).
 */
function toLocalDateString(value: Date | string): string {
    const date = new Date(value);
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function submit(): void {
    form.transform((data) => ({
        program_offering_id: data.program_offering_id,
        level: data.level,
        first_name: data.first_name,
        last_name: data.last_name,
        contact_email: data.contact_email,
        phone: data.phone,
        date_of_birth: data.date_of_birth
            ? toLocalDateString(data.date_of_birth)
            : null,
        previous_institute: data.previous_institute || null,
        documents: data.documents,
    })).post(ApplicationController.store().url, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="New application" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <span>New application</span>
            </template>
            <template #subtitle>
                <span class="text-sm text-muted-foreground">
                    Pick the programme you're applying for, then upload the
                    documents required for the level you're entering.
                </span>
            </template>
            <template #content>
                <form class="space-y-6" @submit.prevent="submit">
                    <section class="space-y-4">
                        <h2
                            class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Programme
                        </h2>
                        <Message
                            v-if="offeringsError"
                            severity="error"
                            :closable="false"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span>
                                    Could not load the departments for this
                                    programme. Check your connection and try
                                    again.
                                </span>
                                <Button
                                    label="Retry"
                                    size="small"
                                    severity="secondary"
                                    :loading="offeringsLoading"
                                    @click="() => loadOfferings()"
                                />
                            </div>
                        </Message>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-1">
                                <label
                                    for="degree-program"
                                    class="text-sm font-medium"
                                >
                                    Degree programme
                                </label>
                                <Select
                                    id="degree-program"
                                    v-model="form.degree_program"
                                    :options="degreePrograms"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Select a programme"
                                    class="w-full"
                                />
                            </div>

                            <div class="space-y-1">
                                <label
                                    for="department"
                                    class="text-sm font-medium"
                                >
                                    Department
                                </label>
                                <Select
                                    id="department"
                                    v-model="form.department_id"
                                    :options="availableDepartments"
                                    option-label="name"
                                    option-value="id"
                                    placeholder="Select a department"
                                    class="w-full"
                                    :disabled="
                                        !form.degree_program || offeringsLoading
                                    "
                                    :loading="offeringsLoading"
                                    :invalid="!!form.errors.program_offering_id"
                                />
                                <p
                                    v-if="form.errors.program_offering_id"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.program_offering_id }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <label for="level" class="text-sm font-medium">
                                    Level
                                </label>
                                <InputNumber
                                    v-model="form.level"
                                    input-id="level"
                                    :min="selectedOffering?.min_level ?? 1"
                                    :max="selectedOffering?.max_level ?? 10"
                                    show-buttons
                                    class="w-full"
                                    :disabled="!selectedOffering"
                                    :invalid="!!form.errors.level"
                                />
                                <p
                                    v-if="selectedOffering"
                                    class="text-xs text-muted-foreground"
                                >
                                    Allowed: {{ selectedOffering.min_level }} –
                                    {{ selectedOffering.max_level }}
                                </p>
                                <p
                                    v-if="form.errors.level"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.level }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h2
                            class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Personal details
                        </h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1">
                                <label
                                    for="first-name"
                                    class="text-sm font-medium"
                                    >First name</label
                                >
                                <InputText
                                    id="first-name"
                                    v-model="form.first_name"
                                    class="w-full"
                                    :invalid="!!form.errors.first_name"
                                />
                                <p
                                    v-if="form.errors.first_name"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.first_name }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="last-name"
                                    class="text-sm font-medium"
                                    >Last name</label
                                >
                                <InputText
                                    id="last-name"
                                    v-model="form.last_name"
                                    class="w-full"
                                    :invalid="!!form.errors.last_name"
                                />
                                <p
                                    v-if="form.errors.last_name"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.last_name }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="contact-email"
                                    class="text-sm font-medium"
                                    >Contact email</label
                                >
                                <InputText
                                    id="contact-email"
                                    v-model="form.contact_email"
                                    type="email"
                                    class="w-full"
                                    :invalid="!!form.errors.contact_email"
                                />
                                <p
                                    v-if="form.errors.contact_email"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.contact_email }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <label for="phone" class="text-sm font-medium"
                                    >Phone</label
                                >
                                <InputMask
                                    id="phone"
                                    v-model="form.phone"
                                    mask="999999999"
                                    placeholder="6XXXXXXXX"
                                    class="w-full"
                                    :invalid="!!form.errors.phone"
                                />
                                <p
                                    v-if="form.errors.phone"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.phone }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <label for="dob" class="text-sm font-medium"
                                    >Date of birth</label
                                >
                                <DatePicker
                                    v-model="form.date_of_birth"
                                    input-id="dob"
                                    date-format="yy-mm-dd"
                                    show-icon
                                    icon-display="input"
                                    :max-date="new Date()"
                                    class="w-full"
                                    :invalid="!!form.errors.date_of_birth"
                                />
                                <p
                                    v-if="form.errors.date_of_birth"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.date_of_birth }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <label
                                    for="previous-institute"
                                    class="text-sm font-medium"
                                >
                                    Previous institute
                                    <span class="text-muted-foreground"
                                        >(optional)</span
                                    >
                                </label>
                                <InputText
                                    id="previous-institute"
                                    v-model="form.previous_institute"
                                    class="w-full"
                                    :invalid="!!form.errors.previous_institute"
                                />
                                <p
                                    v-if="form.errors.previous_institute"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.previous_institute }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h2
                            class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Required documents
                        </h2>
                        <Message
                            v-if="!form.level && !levelRequirementsLoading"
                            severity="info"
                            :closable="false"
                        >
                            Pick a programme and level to see the level-specific
                            documents required. Identity and birth-certificate
                            uploads are required for every application.
                        </Message>
                        <Message
                            v-if="levelRequirementsError"
                            severity="error"
                            :closable="false"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span>
                                    Could not load the documents required for
                                    this level. Check your connection and try
                                    again.
                                </span>
                                <Button
                                    label="Retry"
                                    size="small"
                                    severity="secondary"
                                    :loading="levelRequirementsLoading"
                                    @click="() => loadLevelRequirements()"
                                />
                            </div>
                        </Message>

                        <div class="space-y-3">
                            <div
                                v-for="documentType in requiredDocumentTypes"
                                :key="documentType.code"
                                class="space-y-1"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-medium">
                                            {{ documentType.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Code: {{ documentType.code }} · PDF
                                            / JPG / PNG up to 8 MB
                                        </p>
                                    </div>
                                    <span
                                        v-if="form.documents[documentType.code]"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            form.documents[documentType.code]
                                                ?.name
                                        }}
                                    </span>
                                </div>
                                <FileUpload
                                    mode="basic"
                                    :name="`documents[${documentType.code}]`"
                                    accept="application/pdf,image/jpeg,image/png"
                                    :max-file-size="8 * 1024 * 1024"
                                    :auto="false"
                                    choose-label="Choose file"
                                    :invalid="
                                        !!form.errors[
                                            `documents.${documentType.code}`
                                        ]
                                    "
                                    @select="
                                        (event) =>
                                            onFileSelect(
                                                documentType.code,
                                                event,
                                            )
                                    "
                                    @clear="
                                        () => onFileClear(documentType.code)
                                    "
                                >
                                    <template #chooseicon>
                                        <CloudUpload class="size-4" />
                                    </template>
                                </FileUpload>
                                <p
                                    v-if="
                                        form.errors[
                                            `documents.${documentType.code}`
                                        ]
                                    "
                                    class="text-xs text-destructive"
                                >
                                    {{
                                        form.errors[
                                            `documents.${documentType.code}`
                                        ]
                                    }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <Button
                            type="submit"
                            label="Submit application"
                            :loading="form.processing"
                        >
                            <template #icon>
                                <Send class="size-4" />
                            </template>
                        </Button>
                    </div>
                </form>
            </template>
        </Card>
    </div>
</template>
