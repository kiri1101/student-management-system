<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { ref } from 'vue';
import StandingController from '@/actions/App/Http/Controllers/StandingController';
import { standingLabel, standingSeverity } from '@/lib/statusDisplay';

type Result =
    | { found: false }
    | {
          found: true;
          student: {
              matricule: string;
              name: string | null;
              level: number;
              academic_year: string;
              programme: string | null;
          };
          standing: {
              standing: string;
              has_schedule: boolean;
              total_xaf: number;
              required_so_far: number;
              validated_paid: number;
              shortfall: number;
              active_deferral_deadline: string | null;
          };
      }
    | null;

const props = defineProps<{
    query: string | null;
    result: Result;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Standing check', href: StandingController() }],
    },
});

const matricule = ref(props.query ?? '');

const xaf = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XAF',
    maximumFractionDigits: 0,
});

function formatXaf(value: number): string {
    return xaf.format(value);
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function search(): void {
    router.get(
        StandingController().url,
        { matricule: matricule.value },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Standing check" />

    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6">
        <section>
            <p
                class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
            >
                Staff
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">
                Standing check
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Enter a student's matricule to confirm whether they are cleared
                for exams and facilities.
            </p>
        </section>

        <Card>
            <template #content>
                <form class="flex items-end gap-2" @submit.prevent="search">
                    <div class="flex-1 space-y-1">
                        <label for="matricule" class="text-sm font-medium">
                            Matricule
                        </label>
                        <InputText
                            id="matricule"
                            v-model="matricule"
                            placeholder="stm-2026-0001"
                            class="w-full"
                        />
                    </div>
                    <Button type="submit" label="Check">
                        <template #icon>
                            <Search class="size-4" />
                        </template>
                    </Button>
                </form>
            </template>
        </Card>

        <Message
            v-if="result && result.found === false"
            severity="warn"
            :closable="false"
        >
            No student found for that matricule.
        </Message>

        <Card v-else-if="result && result.found">
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <span>{{ result.student.name ?? '—' }}</span>
                    <Tag
                        :value="standingLabel(result.standing.standing)"
                        :severity="standingSeverity(result.standing.standing)"
                    />
                </div>
            </template>
            <template #content>
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">Matricule</dt>
                        <dd class="font-mono text-sm">
                            {{ result.student.matricule }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Programme</dt>
                        <dd class="text-sm">
                            {{ result.student.programme ?? '—' }} · L{{
                                result.student.level
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Academic year
                        </dt>
                        <dd class="text-sm">
                            {{ result.student.academic_year }}
                        </dd>
                    </div>
                </dl>

                <dl
                    v-if="result.standing.has_schedule"
                    class="mt-4 grid gap-4 rounded-md bg-muted/40 p-4 sm:grid-cols-4"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Due so far
                        </dt>
                        <dd class="text-sm font-medium">
                            {{ formatXaf(result.standing.required_so_far) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Validated paid
                        </dt>
                        <dd class="text-sm font-medium text-green-600">
                            {{ formatXaf(result.standing.validated_paid) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Shortfall</dt>
                        <dd class="text-sm font-medium">
                            {{ formatXaf(result.standing.shortfall) }}
                        </dd>
                    </div>
                    <div v-if="result.standing.active_deferral_deadline">
                        <dt class="text-xs text-muted-foreground">
                            Deferred until
                        </dt>
                        <dd class="text-sm font-medium">
                            {{
                                formatDate(
                                    result.standing.active_deferral_deadline,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    No fee schedule is configured for this enrollment, so
                    nothing is owed yet.
                </p>
            </template>
        </Card>
    </div>
</template>
