<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Banknote } from 'lucide-vue-next';
import Card from 'primevue/card';
import accountant from '@/routes/accountant';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Accountant Dashboard',
                href: accountant.dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Accountant Dashboard" />

    <div class="p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <Banknote class="size-5" />
                    <span>Accountant Dashboard</span>
                </div>
            </template>
            <template #content>
                <p class="text-muted-foreground">Placeholder.</p>
            </template>
        </Card>
    </div>
</template>
