<script setup lang="ts">
defineProps<{
    users: { data: Array<Record<string, unknown>>; meta?: Record<string, unknown> };
    filters: { status: string; roles: string[]; search: string };
    options: { roles: Array<{ value: string; label: string }>; statuses: Array<{ value: string; label: string }> };
}>();
</script>

<template>
    <div>
        <h1>Users</h1>
        <p>Phase B will replace this stub with the full PrimeVue DataTable.</p>
    </div>
</template>
