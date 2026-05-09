<script setup lang="ts">
defineProps<{
    user: Record<string, unknown>;
    options: {
        roles: Array<{ value: string; label: string }>;
        departments: Array<{ id: number; name: string; code: string }>;
    };
}>();
</script>

<template>
    <div>
        <h1>Edit user</h1>
        <p>Phase B will replace this stub with the full edit form.</p>
    </div>
</template>
