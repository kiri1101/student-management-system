<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import Card from 'primevue/card';
import sao from '@/routes/sao';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'SAO Dashboard',
                href: sao.dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="SAO Dashboard" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <Users class="size-5" />
                    <span>Student Affairs Officer Dashboard</span>
                </div>
            </template>
            <template #content>
                <p class="text-muted-foreground">Placeholder.</p>
            </template>
        </Card>
    </div>
</template>
