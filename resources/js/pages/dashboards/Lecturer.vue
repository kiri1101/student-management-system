<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import Card from 'primevue/card';
import lecturer from '@/routes/lecturer';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Lecturer Dashboard',
                href: lecturer.dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Lecturer Dashboard" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <BookOpen class="size-5" />
                    <span>Lecturer Dashboard</span>
                </div>
            </template>
            <template #content>
                <p class="text-muted-foreground">Placeholder.</p>
            </template>
        </Card>
    </div>
</template>
