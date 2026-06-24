<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarClock,
    CircleCheck,
    CircleX,
    Inbox,
    Wallet,
} from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import { computed } from 'vue';
import StatCard from '@/components/StatCard.vue';
import { deferralStatusLabel } from '@/lib/statusDisplay';
import accountant from '@/routes/accountant';

type Profile = {
    bank_desk: string | null;
    cashier_window: string | null;
} | null;

const props = defineProps<{
    profile: Profile;
    statusCounts: Record<string, number>;
    deferralCounts: Record<string, number>;
}>();

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

const DEFERRAL_STATUSES = ['requested', 'approved', 'rejected'];

const page = usePage();
const firstName = computed<string>(
    () => (page.props.auth?.user?.name ?? '').split(' ')[0] ?? '',
);

function paymentCount(status: string): number {
    return props.statusCounts[status] ?? 0;
}

function deferralCount(status: string): number {
    return props.deferralCounts[status] ?? 0;
}
</script>

<template>
    <Head title="Accountant Dashboard" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <!-- Hero -->
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Accountant
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    Welcome back<template v-if="firstName"
                        >, {{ firstName }}</template
                    >
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Validate payment receipts and decide tuition deferral
                    requests.
                </p>
            </div>
            <Link :href="accountant.payments.index().url">
                <Button label="Review payments">
                    <template #icon>
                        <Wallet class="size-4" />
                    </template>
                </Button>
            </Link>
        </section>

        <!-- Chips -->
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard
                label="Submitted"
                :value="paymentCount('submitted')"
                :icon="Inbox"
                tone="blue"
            />
            <StatCard
                label="Validated"
                :value="paymentCount('validated')"
                :icon="CircleCheck"
                tone="emerald"
            />
            <StatCard
                label="Rejected"
                :value="paymentCount('rejected')"
                :icon="CircleX"
                tone="red"
            />
            <StatCard
                label="Deferrals requested"
                :value="deferralCount('requested')"
                :icon="CalendarClock"
                tone="amber"
            />
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Profile -->
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <Banknote class="size-5 text-muted-foreground" />
                        <span class="text-base font-semibold">My profile</span>
                    </div>
                </template>
                <template #content>
                    <dl v-if="profile" class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Bank desk
                            </dt>
                            <dd class="text-sm">
                                {{ profile.bank_desk ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Cashier window
                            </dt>
                            <dd class="text-sm">
                                {{ profile.cashier_window ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                    <p v-else class="text-sm text-muted-foreground">
                        No accountant profile found. Contact an administrator if
                        you believe this is an error.
                    </p>
                </template>
            </Card>

            <!-- Deferral requests -->
            <Card>
                <template #title>
                    <div class="flex items-center gap-2">
                        <CalendarClock class="size-5 text-muted-foreground" />
                        <span class="text-base font-semibold"
                            >Deferral requests</span
                        >
                    </div>
                </template>
                <template #content>
                    <ul class="divide-y divide-border">
                        <li
                            v-for="status in DEFERRAL_STATUSES"
                            :key="status"
                            class="flex items-center justify-between py-2.5 text-sm"
                        >
                            <span>{{ deferralStatusLabel(status) }}</span>
                            <span class="font-mono font-semibold">{{
                                deferralCount(status)
                            }}</span>
                        </li>
                    </ul>
                </template>
                <template #footer>
                    <Link :href="accountant.deferrals.index().url">
                        <Button
                            label="Review deferrals"
                            size="small"
                            severity="secondary"
                        />
                    </Link>
                </template>
            </Card>
        </div>
    </div>
</template>
