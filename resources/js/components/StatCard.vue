<script setup lang="ts">
import type { Component } from 'vue';

type Tone = 'blue' | 'amber' | 'emerald' | 'red' | 'violet' | 'slate';

const { tone = 'slate' } = defineProps<{
    label: string;
    value: number | string;
    icon: Component;
    tone?: Tone;
}>();

const TONES: Record<Tone, string> = {
    blue: 'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400',
    amber: 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400',
    emerald:
        'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400',
    red: 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400',
    violet: 'bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400',
    slate: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-muted-foreground">{{
                label
            }}</span>
            <span
                class="grid size-7 flex-none place-items-center rounded-lg"
                :class="TONES[tone]"
            >
                <component :is="icon" class="size-3.5" />
            </span>
        </div>
        <p class="mt-2 text-2xl font-bold">{{ value }}</p>
    </div>
</template>
