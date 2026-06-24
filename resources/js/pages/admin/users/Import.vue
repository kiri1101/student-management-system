<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CloudUpload,
    Download,
    ListChecks,
    RotateCcw,
    Upload,
} from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import FileUpload from 'primevue/fileupload';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { ref } from 'vue';
import { roleLabel } from '@/lib/statusDisplay';
import admin from '@/routes/admin';
import usersRoutes from '@/routes/admin/users';

type CsvRow = Record<string, string>;

type PreviewRow = {
    line: number;
    data: CsvRow;
    status: 'ok' | 'error';
    errors: string[];
};

type PreviewShape = {
    fatal: string | null;
    rows: PreviewRow[];
    summary: { total: number; valid: number; invalid: number };
};

type ImportedRow = {
    line: number;
    name: string;
    email: string;
    role: string;
};

type SkippedRow = {
    line: number;
    data: CsvRow;
    status: 'error';
    errors: string[];
};

type ResultShape = {
    fatal: string | null;
    imported: ImportedRow[];
    skipped: SkippedRow[];
    summary: { total: number; imported: number; skipped: number };
};

defineProps<{
    preview?: PreviewShape;
    result?: ResultShape;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Users', href: usersRoutes.index() },
            { title: 'Import', href: usersRoutes.import() },
        ],
    },
});

const selectedFile = ref<File | null>(null);
const processing = ref<boolean>(false);

const expectedColumns = [
    { name: 'name', required: true, note: 'Required' },
    { name: 'email', required: true, note: 'Required' },
    {
        name: 'role',
        required: true,
        note: 'Required — one of lecturer, accountant, sao, admin',
    },
    { name: 'employee_id', required: false, note: 'Optional' },
    {
        name: 'department_code',
        required: false,
        note: 'Required for lecturer',
    },
    { name: 'specialization', required: false, note: 'Optional' },
    { name: 'hired_at', required: false, note: 'Optional (YYYY-MM-DD)' },
    { name: 'bank_desk', required: false, note: 'Optional' },
    { name: 'cashier_window', required: false, note: 'Optional' },
    { name: 'scope', required: false, note: 'Optional' },
];

function onFileSelect(event: { files: File[] }): void {
    selectedFile.value = event.files?.[0] ?? null;
}

function onFileClear(): void {
    selectedFile.value = null;
}

function preview_(): void {
    if (!selectedFile.value || processing.value) {
        return;
    }

    router.post(
        usersRoutes.import.preview.url(),
        { file: selectedFile.value },
        {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function confirmImport(): void {
    if (!selectedFile.value || processing.value) {
        return;
    }

    router.post(
        usersRoutes.import.store.url(),
        { file: selectedFile.value },
        {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function reset(): void {
    selectedFile.value = null;
    router.get(usersRoutes.import.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Import users" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
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

        <div>
            <p
                class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
            >
                Users
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">
                Bulk staff import
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Upload a CSV to create multiple staff accounts at once. Each new
                user receives a single-use link to set their password. Validate
                the file first, then confirm to import only the valid rows.
            </p>
        </div>

        <!-- Step 1: pick file + actions -->
        <Card>
            <template #content>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-3">
                        <div class="space-y-1">
                            <label class="text-sm font-medium">CSV file</label>
                            <FileUpload
                                mode="basic"
                                name="file"
                                accept=".csv,text/csv"
                                :max-file-size="512000"
                                :auto="false"
                                custom-upload
                                choose-label="Choose CSV"
                                @select="onFileSelect"
                                @clear="onFileClear"
                            >
                                <template #chooseicon>
                                    <CloudUpload class="size-4" />
                                </template>
                            </FileUpload>
                            <p
                                v-if="selectedFile"
                                class="text-xs text-muted-foreground"
                            >
                                Selected:
                                <span class="font-medium">{{
                                    selectedFile.name
                                }}</span>
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                No file selected yet.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                label="Validate / Preview"
                                :disabled="!selectedFile || processing"
                                :loading="processing"
                                @click="preview_"
                            >
                                <template #icon>
                                    <ListChecks class="size-4" />
                                </template>
                            </Button>
                            <a :href="usersRoutes.import.template.url()">
                                <Button
                                    label="Download template"
                                    severity="secondary"
                                    outlined
                                >
                                    <template #icon>
                                        <Download class="size-4" />
                                    </template>
                                </Button>
                            </a>
                        </div>
                    </div>

                    <div
                        class="rounded-md border border-border bg-muted/30 p-4 text-sm"
                    >
                        <h3 class="mb-2 font-semibold">Expected columns</h3>
                        <ul class="space-y-1">
                            <li
                                v-for="col in expectedColumns"
                                :key="col.name"
                                class="flex items-baseline justify-between gap-3"
                            >
                                <code class="font-mono text-xs">{{
                                    col.name
                                }}</code>
                                <span
                                    class="text-xs"
                                    :class="
                                        col.required
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ col.note }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Step 2: preview -->
        <Card v-if="preview && !result">
            <template #title>
                <span>Preview</span>
            </template>
            <template #content>
                <Message
                    v-if="preview.fatal"
                    severity="error"
                    :closable="false"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4" />
                        <span>{{ preview.fatal }}</span>
                    </div>
                </Message>

                <template v-else>
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <Tag
                            :value="`${preview.summary.valid} valid`"
                            severity="success"
                        />
                        <Tag
                            :value="`${preview.summary.invalid} invalid`"
                            :severity="
                                preview.summary.invalid > 0
                                    ? 'danger'
                                    : 'secondary'
                            "
                        />
                        <span class="text-xs text-muted-foreground">
                            {{ preview.summary.total }} rows total
                        </span>
                    </div>

                    <DataTable
                        :value="preview.rows"
                        data-key="line"
                        striped-rows
                        responsive-layout="scroll"
                        :rows="25"
                        paginator
                    >
                        <Column header="Line" style="width: 6rem">
                            <template #body="{ data }">
                                <span class="font-mono text-sm">{{
                                    data.line
                                }}</span>
                            </template>
                        </Column>
                        <Column header="Name">
                            <template #body="{ data }">
                                {{ data.data.name || '—' }}
                            </template>
                        </Column>
                        <Column header="Email">
                            <template #body="{ data }">
                                {{ data.data.email || '—' }}
                            </template>
                        </Column>
                        <Column header="Role" style="width: 10rem">
                            <template #body="{ data }">
                                {{
                                    data.data.role
                                        ? roleLabel(data.data.role)
                                        : '—'
                                }}
                            </template>
                        </Column>
                        <Column header="Status" style="width: 8rem">
                            <template #body="{ data }">
                                <Tag
                                    v-if="data.status === 'ok'"
                                    value="OK"
                                    severity="success"
                                />
                                <Tag v-else value="Error" severity="danger" />
                            </template>
                        </Column>
                        <Column header="Errors">
                            <template #body="{ data }">
                                <ul
                                    v-if="data.errors.length"
                                    class="list-inside list-disc text-xs text-destructive"
                                >
                                    <li
                                        v-for="(err, i) in data.errors"
                                        :key="i"
                                    >
                                        {{ err }}
                                    </li>
                                </ul>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >—</span
                                >
                            </template>
                        </Column>
                    </DataTable>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <p
                            v-if="!selectedFile"
                            class="text-xs text-destructive"
                        >
                            Re-pick the file above to confirm the import.
                        </p>
                        <Button
                            :label="`Confirm import (${preview.summary.valid} valid)`"
                            :disabled="
                                preview.summary.valid === 0 ||
                                processing ||
                                !selectedFile
                            "
                            :loading="processing"
                            @click="confirmImport"
                        >
                            <template #icon>
                                <Upload class="size-4" />
                            </template>
                        </Button>
                    </div>
                </template>
            </template>
        </Card>

        <!-- Step 3: result -->
        <Card v-if="result">
            <template #title>
                <span>Import result</span>
            </template>
            <template #content>
                <Message v-if="result.fatal" severity="error" :closable="false">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4" />
                        <span>{{ result.fatal }}</span>
                    </div>
                </Message>

                <template v-else>
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <Tag
                            :value="`Imported ${result.summary.imported}`"
                            severity="success"
                        />
                        <Tag
                            :value="`Skipped ${result.summary.skipped}`"
                            :severity="
                                result.summary.skipped > 0
                                    ? 'warn'
                                    : 'secondary'
                            "
                        />
                        <span class="text-xs text-muted-foreground">
                            {{ result.summary.total }} rows processed
                        </span>
                    </div>

                    <div v-if="result.skipped.length" class="mb-4">
                        <h3 class="mb-2 text-sm font-semibold">Skipped rows</h3>
                        <DataTable
                            :value="result.skipped"
                            data-key="line"
                            striped-rows
                            responsive-layout="scroll"
                        >
                            <Column header="Line" style="width: 6rem">
                                <template #body="{ data }">
                                    <span class="font-mono text-sm">{{
                                        data.line
                                    }}</span>
                                </template>
                            </Column>
                            <Column header="Email">
                                <template #body="{ data }">
                                    {{ data.data.email || '—' }}
                                </template>
                            </Column>
                            <Column header="Errors">
                                <template #body="{ data }">
                                    <ul
                                        class="list-inside list-disc text-xs text-destructive"
                                    >
                                        <li
                                            v-for="(err, i) in data.errors"
                                            :key="i"
                                        >
                                            {{ err }}
                                        </li>
                                    </ul>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <div v-if="result.imported.length">
                        <h3 class="mb-2 text-sm font-semibold">
                            Imported users
                        </h3>
                        <DataTable
                            :value="result.imported"
                            data-key="line"
                            striped-rows
                            responsive-layout="scroll"
                            :rows="25"
                            paginator
                        >
                            <Column header="Line" style="width: 6rem">
                                <template #body="{ data }">
                                    <span class="font-mono text-sm">{{
                                        data.line
                                    }}</span>
                                </template>
                            </Column>
                            <Column field="name" header="Name" />
                            <Column field="email" header="Email" />
                            <Column header="Role" style="width: 10rem">
                                <template #body="{ data }">
                                    {{ roleLabel(data.role) }}
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <Button
                            label="Import another file"
                            severity="secondary"
                            outlined
                            @click="reset"
                        >
                            <template #icon>
                                <RotateCcw class="size-4" />
                            </template>
                        </Button>
                        <Link :href="usersRoutes.index().url">
                            <Button label="Back to users">
                                <template #icon>
                                    <ArrowLeft class="size-4" />
                                </template>
                            </Button>
                        </Link>
                    </div>
                </template>
            </template>
        </Card>
    </div>
</template>
