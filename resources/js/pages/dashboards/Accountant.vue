<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Banknote, CalendarClock, Wallet } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import { deferralStatusLabel, paymentStatusLabel } from '@/lib/statusDisplay';
import accountant from '@/routes/accountant';

type Profile = {
    bank_desk: string | null;
    cashier_window: string | null;
} | null;

defineProps<{
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

const STATUSES = ['submitted', 'validated', 'rejected'];
const DEFERRAL_STATUSES = ['requested', 'approved', 'rejected'];
</script>

<template>
    <Head title="Accountant Dashboard" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <Banknote class="size-5" />
                    <span>My profile</span>
                </div>
            </template>
            <template #content>
                <dl v-if="profile" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">Bank desk</dt>
                        <dd class="text-sm">{{ profile.bank_desk ?? '—' }}</dd>
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
                    No accountant profile found. Contact an administrator if you
                    believe this is an error.
                </p>
            </template>
        </Card>

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <Wallet class="size-5" />
                    <span>Payment review queue</span>
                </div>
            </template>
            <template #content>
                <ul class="space-y-1">
                    <li
                        v-for="status in STATUSES"
                        :key="status"
                        class="flex justify-between"
                    >
                        <span>{{ paymentStatusLabel(status) }}</span>
                        <span class="font-mono">{{
                            statusCounts[status] ?? 0
                        }}</span>
                    </li>
                </ul>
            </template>
            <template #footer>
                <Link :href="accountant.payments.index().url">
                    <Button label="Review payments" size="small" />
                </Link>
            </template>
        </Card>

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <CalendarClock class="size-5" />
                    <span>Deferral requests</span>
                </div>
            </template>
            <template #content>
                <ul class="space-y-1">
                    <li
                        v-for="status in DEFERRAL_STATUSES"
                        :key="status"
                        class="flex justify-between"
                    >
                        <span>{{ deferralStatusLabel(status) }}</span>
                        <span class="font-mono">{{
                            deferralCounts[status] ?? 0
                        }}</span>
                    </li>
                </ul>
            </template>
            <template #footer>
                <Link :href="accountant.deferrals.index().url">
                    <Button label="Review deferrals" size="small" />
                </Link>
            </template>
        </Card>
    </div>
</template>
