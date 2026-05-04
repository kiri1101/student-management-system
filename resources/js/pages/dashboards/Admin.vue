<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Database, Shield } from 'lucide-vue-next';
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

const shortcuts = [
    {
        title: 'Reference data',
        description:
            'Departments, program offerings, document types, level requirements.',
        href: admin.references.index(),
        icon: Database,
    },
];
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="space-y-4 p-4">
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

        <div class="grid gap-4 md:grid-cols-2">
            <Link
                v-for="card in shortcuts"
                :key="card.title"
                :href="card.href"
                class="block focus:outline-none"
            >
                <Card class="transition-colors hover:bg-muted/50">
                    <template #title>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <component :is="card.icon" class="size-5" />
                                <span>{{ card.title }}</span>
                            </div>
                            <ChevronRight
                                class="size-4 text-muted-foreground"
                            />
                        </div>
                    </template>
                    <template #content>
                        <p class="text-sm text-muted-foreground">
                            {{ card.description }}
                        </p>
                    </template>
                </Card>
            </Link>
        </div>
    </div>
</template>
