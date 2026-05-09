<script setup lang="ts">
defineProps<{
    options: {
        roles: Array<{ value: string; label: string }>;
        departments: Array<{ id: number; name: string; code: string }>;
    };
}>();
</script>

<template>
    <div>
        <h1>Create user</h1>
        <p>Phase B will replace this stub with the full create form.</p>
    </div>
</template>
