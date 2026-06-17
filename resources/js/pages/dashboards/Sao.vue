<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, Clock, FileQuestion, Inbox } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import { computed } from 'vue';
import StatCard from '@/components/StatCard.vue';
import { statusLabel } from '@/lib/statusDisplay';
import sao from '@/routes/sao';

const props = defineProps<{ statusCounts: Record<string, number> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'SAO Dashboard', href: sao.dashboard() }],
    },
});

const DECIDED = ['admitted', 'rejected', 'waitlisted', 'withdrawn'];

const page = usePage();
const firstName = computed<string>(
    () => (page.props.auth?.user?.name ?? '').split(' ')[0] ?? '',
);

function count(status: string): number {
    return props.statusCounts[status] ?? 0;
}

const decidedTotal = computed<number>(() =>
    DECIDED.reduce((total, status) => total + count(status), 0),
);
</script>

<template>
    <Head title="SAO Dashboard" />

    <div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
        <!-- Hero -->
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Student Affairs Officer
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    Welcome back<template v-if="firstName"
                        >, {{ firstName }}</template
                    >
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Triage incoming applications, request follow-up documents,
                    and decide admissions.
                </p>
            </div>
            <Link :href="sao.applications.index().url">
                <Button label="Review applications">
                    <template #icon>
                        <Inbox class="size-4" />
                    </template>
                </Button>
            </Link>
        </section>

        <!-- Pipeline chips -->
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard
                label="Submitted"
                :value="count('submitted')"
                :icon="Inbox"
                tone="blue"
            />
            <StatCard
                label="Under review"
                :value="count('under_review')"
                :icon="Clock"
                tone="amber"
            />
            <StatCard
                label="Documents requested"
                :value="count('documents_requested')"
                :icon="FileQuestion"
                tone="violet"
            />
            <StatCard
                label="Decided"
                :value="decidedTotal"
                :icon="CircleCheck"
                tone="emerald"
            />
        </section>

        <!-- Decisions breakdown -->
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <CircleCheck class="size-5 text-muted-foreground" />
                    <span class="text-base font-semibold">Decisions</span>
                </div>
            </template>
            <template #content>
                <ul class="divide-y divide-border">
                    <li
                        v-for="status in DECIDED"
                        :key="status"
                        class="flex items-center justify-between py-2.5 text-sm"
                    >
                        <span>{{ statusLabel(status) }}</span>
                        <span class="font-mono font-semibold">{{
                            count(status)
                        }}</span>
                    </li>
                </ul>
            </template>
        </Card>
    </div>
</template>
