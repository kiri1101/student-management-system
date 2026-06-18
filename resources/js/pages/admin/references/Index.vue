<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    ChevronRight,
    FileText,
    GraduationCap,
    ListChecks,
} from 'lucide-vue-next';
import Card from 'primevue/card';
import admin from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin Dashboard', href: admin.dashboard() },
            { title: 'Reference data', href: admin.references.index() },
        ],
    },
});

const cards = [
    {
        title: 'Departments',
        description: 'Academic departments offered by the institution.',
        href: admin.references.departments.index(),
        icon: Building2,
    },
    {
        title: 'Program offerings',
        description:
            'Degree programs each department offers and the level range for each.',
        href: admin.references.programOfferings.index(),
        icon: GraduationCap,
    },
    {
        title: 'Document types',
        description:
            'Credentials and identity documents the application form can request.',
        href: admin.references.documentTypes.index(),
        icon: FileText,
    },
    {
        title: 'Level requirements',
        description: 'Per-level required documents for each program offering.',
        href: admin.references.levelRequirements.index(),
        icon: ListChecks,
    },
];
</script>

<template>
    <Head title="Reference data" />

    <div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
        <section>
            <p
                class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
            >
                Administration
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">
                Reference data
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Manage the lookup tables the application form depends on.
            </p>
        </section>

        <div class="grid gap-4 md:grid-cols-2">
            <Link
                v-for="card in cards"
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
