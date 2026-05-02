<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import Card from 'primevue/card';
import applicant from '@/routes/applicant';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Applicant Dashboard',
                href: applicant.dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Applicant Dashboard" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <FileText class="size-5" />
                    <span>Applicant Dashboard</span>
                </div>
            </template>
            <template #content>
                <p class="text-muted-foreground">
                    Placeholder. Phase 8 will replace this with the application
                    list and "Start a new application" CTA.
                </p>
            </template>
        </Card>
    </div>
</template>
