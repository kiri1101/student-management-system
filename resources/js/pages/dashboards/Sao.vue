<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, Inbox, Users } from 'lucide-vue-next';
import Card from 'primevue/card';
import sao from '@/routes/sao';

defineProps<{ statusCounts: Record<string, number> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'SAO Dashboard', href: sao.dashboard() }],
    },
});

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    submitted: 'Submitted',
    under_review: 'Under review',
    documents_requested: 'Documents requested',
    admitted: 'Admitted',
    rejected: 'Rejected',
    waitlisted: 'Waitlisted',
    withdrawn: 'Withdrawn',
};

const ACTIONABLE = ['submitted', 'under_review', 'documents_requested'];
const DECIDED = ['admitted', 'rejected', 'waitlisted', 'withdrawn'];
</script>

<template>
    <Head title="SAO Dashboard" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <Users class="size-5" />
                    <span>Student Affairs Officer</span>
                </div>
            </template>
            <template #content>
                <p class="text-muted-foreground">
                    Triage incoming applications, request follow-up documents,
                    and decide admissions.
                </p>
            </template>
        </Card>

        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <Inbox class="size-5" />
                        <span>Pending review</span>
                    </div>
                </template>
                <template #content>
                    <ul class="space-y-1">
                        <li
                            v-for="status in ACTIONABLE"
                            :key="status"
                            class="flex justify-between"
                        >
                            <span>{{ STATUS_LABELS[status] }}</span>
                            <span class="font-mono">{{
                                statusCounts[status] ?? 0
                            }}</span>
                        </li>
                    </ul>
                </template>
                <template #footer>
                    <Link :href="sao.applications.index().url">
                        <Button label="Review applications" size="small" />
                    </Link>
                </template>
            </Card>

            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <ClipboardList class="size-5" />
                        <span>Decided</span>
                    </div>
                </template>
                <template #content>
                    <ul class="space-y-1">
                        <li
                            v-for="status in DECIDED"
                            :key="status"
                            class="flex justify-between"
                        >
                            <span>{{ STATUS_LABELS[status] }}</span>
                            <span class="font-mono">{{
                                statusCounts[status] ?? 0
                            }}</span>
                        </li>
                    </ul>
                </template>
            </Card>
        </div>
    </div>
</template>
