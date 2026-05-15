<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid md:grid-cols-2">
        <div
            class="flex min-h-svh w-full flex-col items-center justify-center gap-6 p-6 md:p-10"
        >
            <div class="w-full max-w-md">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col items-center gap-4">
                        <Link
                            :href="home()"
                            class="flex flex-col items-center gap-2 font-medium"
                        >
                            <div
                                class="mb-1 flex items-center justify-center rounded-md"
                            >
                                <AppLogoIcon
                                    class="size-16 fill-current text-foreground dark:text-white"
                                />
                            </div>
                            <span class="sr-only">{{ title }}</span>
                        </Link>

                        <div class="space-y-2 text-center">
                            <h1 class="text-xl font-medium">
                                {{ title }}
                            </h1>
                            <p
                                class="text-center text-sm text-muted-foreground"
                            >
                                {{ description }}
                            </p>
                        </div>
                    </div>

                    <slot />
                </div>
            </div>
        </div>

        <div
            class="global hidden justify-center md:flex"
            style="clip-path: polygon(25% 0, 100% 0, 100% 100%, 0 100%)"
        />
    </div>
</template>

<style scoped>
.global {
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    background-image: url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22a%22%20width%3D%2258%22%20height%3D%22100.23%22%20patternTransform%3D%22scale(2)%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%23111827%22%2F%3E%3Cpath%20fill%3D%22none%22%20stroke%3D%22%236b7280%22%20stroke-linecap%3D%22square%22%20d%3D%22m12.127%2073.813.013%205.643%204.893-2.81zm-6.01-3.47%2016.928%209.773-16.93%209.777zm-6.02-3.476%2028.967%2016.725L.13%20100.262zm16.97%2023.616-.015%205.643-4.893-2.81zm6.01-3.472L6.148%2096.786l16.93%209.776zM.128%20100.261l28.935%2016.669m16.874-23.67-.013-5.644-4.894%202.81zm6.01%203.47L35.02%2086.956l16.931-9.775zM29%2083.482l28.936-16.669.032%2033.393m-28.904%202.834%204.881-2.834-4.88-2.832zm0%206.94V90.433l16.932%209.773zm0%206.952v-33.45l28.904%2016.724zM58%2013.915l-4.882%202.833L58%2019.582zm0-6.94v19.548l-16.932-9.774zm0-6.951v33.448L29.096%2016.748m0-13.89%204.88-2.833-4.88-2.833zm0%206.94V-9.749L46.026.025zm0%206.95v-33.449L58%20.024zM16.94%2023.696l-4.894-2.81-.014%205.643zm6.01-3.47L6.02%2029.998v-19.55zM0%2033.472.033.08%2028.97%2016.75m-5.99-29.92L6.053-3.393l16.93%209.776zm5.99%2029.92L29-16.644.033.08m40.966%2023.615.013%205.643%204.893-2.81zm-6.01-3.47%2016.928%209.774-16.93%209.775zm22.947%2013.248L29%2050.143l-.031-33.394M16.97%2043.197l-.013-5.643-4.894%202.81zm6.01%203.47L6.053%2036.894l16.93-9.776zM29%2050.143.032%2033.419l28.936-16.67m16.937%2057.008-4.893-2.81-.014%205.644zm6.01-3.47L34.988%2080.06V60.51zM29%2064.035l-4.88%202.832L29%2069.7zm0-6.942v19.549l-16.93-9.775zM.097%2066.867%2029%2050.143v33.449m16.938-43.228-.013%205.644-4.894-2.811zm6.01-3.47L35.02%2046.667l16.931%209.777zM29%2050.143l28.936%2016.67.032-33.394M.097%2052.975l4.88-2.832-4.88-2.833zm0%206.942V40.368l16.931%209.775zm0%206.95V33.42%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22800%25%22%20height%3D%22800%25%22%20fill%3D%22url(%23a)%22%2F%3E%3C%2Fsvg%3E');
}
</style>
