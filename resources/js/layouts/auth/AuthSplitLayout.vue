<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useAppearance } from '@/composables/useAppearance';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const { resolvedAppearance, updateAppearance } = useAppearance();
const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggleTheme(): void {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-2">
        <!-- ===== BRAND PANEL (left on desktop, slim strip on mobile) ===== -->
        <section
            class="relative overflow-hidden bg-primary-700 text-white lg:flex lg:flex-col lg:justify-between lg:p-12"
        >
            <div class="brand-glow absolute inset-0 opacity-90" />
            <div class="brand-pattern absolute inset-0" />

            <!-- Mobile: slim branded strip -->
            <Link
                :href="home()"
                class="relative flex items-center gap-2.5 px-5 py-4 lg:hidden"
            >
                <span
                    class="grid size-9 place-items-center rounded-xl bg-white/15 ring-1 ring-white/25"
                >
                    <AppLogoIcon class="size-5 text-white" />
                </span>
                <span class="text-lg font-extrabold tracking-tight"
                    >SchuLyf</span
                >
            </Link>

            <!-- Desktop: full brand lockup -->
            <Link :href="home()" class="relative hidden lg:block">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-11 place-items-center rounded-xl bg-white/15 ring-1 ring-white/25"
                    >
                        <AppLogoIcon class="size-6 text-white" />
                    </span>
                    <span class="text-2xl font-extrabold tracking-tight"
                        >SchuLyf</span
                    >
                </div>
            </Link>

            <div class="relative hidden max-w-md lg:block">
                <h1
                    class="text-4xl leading-tight font-extrabold tracking-tight"
                >
                    Your campus life,<br />organized.
                </h1>
                <ul class="mt-8 space-y-4 text-primary-50">
                    <li class="flex items-start gap-3">
                        <span
                            class="mt-0.5 grid size-6 flex-none place-items-center rounded-full bg-white/15"
                        >
                            <Check class="size-3.5" />
                        </span>
                        <span>Apply &amp; track admission</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span
                            class="mt-0.5 grid size-6 flex-none place-items-center rounded-full bg-white/15"
                        >
                            <Check class="size-3.5" />
                        </span>
                        <span>Pay &amp; verify receipts</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span
                            class="mt-0.5 grid size-6 flex-none place-items-center rounded-full bg-white/15"
                        >
                            <Check class="size-3.5" />
                        </span>
                        <span>Results, attendance &amp; more</span>
                    </li>
                </ul>
            </div>

            <p class="relative hidden text-sm text-primary-100/80 lg:block">
                © 2026 SchuLyf · Student Management System
            </p>
        </section>

        <!-- ===== FORM PANEL ===== -->
        <section class="flex items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-sm">
                <div v-if="title || description" class="mb-8">
                    <h2
                        v-if="title"
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        {{ title }}
                    </h2>
                    <p
                        v-if="description"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>

                <slot />

                <!-- Theme toggle (auth screens have no app shell topbar) -->
                <div class="mt-10 flex justify-center">
                    <button
                        type="button"
                        :aria-pressed="isDark"
                        class="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground shadow-sm transition hover:bg-accent hover:text-foreground"
                        @click="toggleTheme"
                    >
                        <Sun v-if="isDark" class="size-3.5" />
                        <Moon v-else class="size-3.5" />
                        <span>{{ isDark ? 'Light mode' : 'Dark mode' }}</span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
