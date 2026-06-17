<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
    >
        <path fill="currentColor" d="M16 3 3 9l13 6 10-4.6V20h2.6V9L16 3Z" />
        <path
            fill="currentColor"
            opacity=".7"
            d="M7 14.4V20c0 2.6 4 4.8 9 4.8s9-2.2 9-4.8v-5.6l-9 4.1-9-4.1Z"
        />
    </svg>
</template>
