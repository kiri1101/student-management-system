<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { GraduationCap } from 'lucide-vue-next';
import Card from 'primevue/card';
import student from '@/routes/student';

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
</script>

<template>
    <Head title="Student Dashboard" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <GraduationCap class="size-5" />
                    <span>Student Dashboard</span>
                </div>
            </template>
            <template #content>
                <p class="text-muted-foreground">Placeholder.</p>
            </template>
        </Card>
    </div>
</template>
