<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import Card from 'primevue/card';
import admin from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin Dashboard',
                href: admin.dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <Shield class="size-5" />
                    <span>Admin Dashboard</span>
                </div>
            </template>
            <template #content>
                <p class="text-muted-foreground">Placeholder.</p>
            </template>
        </Card>
    </div>
</template>
