<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldAlert, ShieldCheck } from 'lucide-vue-next';

type VerifiedSemester = {
    academic_year: string;
    semester: number;
    gpa: number;
    credits_earned: number;
    credits_attempted: number;
};

type VerifiedTranscript = {
    transcript_number: string;
    student_name: string | null;
    matricule: string | null;
    programme: string | null;
    level: number | null;
    cgpa: number;
    credits_earned: number;
    credits_attempted: number;
    issued_at: string | null;
    semesters: VerifiedSemester[];
};

defineProps<{
    transcriptNumber: string;
    valid: boolean;
    transcript: VerifiedTranscript | null;
}>();

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}
</script>

<template>
    <Head title="Verify transcript" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 p-4 dark:bg-gray-950">
        <div class="w-full max-w-lg overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-900">
            <div
                v-if="valid && transcript"
                class="flex flex-col items-center gap-1 bg-green-50 p-6 text-center dark:bg-green-950/40"
            >
                <ShieldCheck class="size-10 text-green-600" />
                <h1 class="text-lg font-semibold text-green-800 dark:text-green-300">Authentic transcript</h1>
                <p class="font-mono text-sm text-gray-500">{{ transcript.transcript_number }}</p>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-1 bg-red-50 p-6 text-center dark:bg-red-950/40"
            >
                <ShieldAlert class="size-10 text-red-600" />
                <h1 class="text-lg font-semibold text-red-800 dark:text-red-300">Invalid transcript</h1>
                <p class="font-mono text-sm text-gray-500">{{ transcriptNumber }}</p>
            </div>

            <div v-if="valid && transcript" class="space-y-4 p-6">
                <p class="text-sm text-muted-foreground">
                    This transcript is genuine and was issued as shown below. Confirm these details
                    match the document presented.
                </p>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Student</dt>
                        <dd>{{ transcript.student_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Matricule</dt>
                        <dd class="font-mono">{{ transcript.matricule ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Programme</dt>
                        <dd>{{ transcript.programme ?? '—' }} · L{{ transcript.level }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Issued</dt>
                        <dd>{{ formatDate(transcript.issued_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">CGPA</dt>
                        <dd class="font-medium">{{ transcript.cgpa.toFixed(2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Credits earned</dt>
                        <dd>{{ transcript.credits_earned }} / {{ transcript.credits_attempted }}</dd>
                    </div>
                </dl>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="p-2">Year</th>
                                <th class="p-2">Semester</th>
                                <th class="p-2">GPA</th>
                                <th class="p-2">Credits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(s, i) in transcript.semesters" :key="i" class="border-t">
                                <td class="p-2">{{ s.academic_year }}</td>
                                <td class="p-2">{{ s.semester }}</td>
                                <td class="p-2">{{ s.gpa.toFixed(2) }}</td>
                                <td class="p-2">{{ s.credits_earned }}/{{ s.credits_attempted }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-else class="p-6">
                <p class="text-sm text-muted-foreground">
                    We could not verify this transcript. It may have been altered, forged, or the code
                    may be incorrect. Do not accept it as an official record — contact student affairs.
                </p>
            </div>
        </div>
    </div>
</template>
