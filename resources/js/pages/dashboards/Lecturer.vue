<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import { computed } from 'vue';
import lecturer from '@/routes/lecturer';
import { index as lecturerCoursesIndex } from '@/routes/lecturer/courses';

type Profile = {
    specialization: string | null;
    hired_at: string | null;
    department: { name: string; code: string } | null;
} | null;

defineProps<{ profile: Profile }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Lecturer Dashboard',
                href: lecturer.dashboard(),
            },
        ],
    },
});

const page = usePage();
const firstName = computed<string>(
    () => (page.props.auth?.user?.name ?? '').split(' ')[0] ?? '',
);

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head title="Lecturer Dashboard" />

    <div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
        <!-- Hero -->
        <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Lecturer
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">
                    Welcome back<template v-if="firstName"
                        >, {{ firstName }}</template
                    >
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Plan your courses, record attendance, manage assignments and
                    publish results.
                </p>
            </div>
            <Link :href="lecturerCoursesIndex().url">
                <Button label="My courses">
                    <template #icon>
                        <BookOpen class="size-4" />
                    </template>
                </Button>
            </Link>
        </section>

        <!-- Profile -->
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <BookOpen class="size-5 text-muted-foreground" />
                    <span class="text-base font-semibold">My profile</span>
                </div>
            </template>
            <template #content>
                <dl
                    v-if="profile"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Department
                        </dt>
                        <dd class="text-sm">
                            {{
                                profile.department
                                    ? `${profile.department.name} (${profile.department.code})`
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Specialization
                        </dt>
                        <dd class="text-sm">
                            {{ profile.specialization ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Hired on</dt>
                        <dd class="text-sm">
                            {{ formatDate(profile.hired_at) }}
                        </dd>
                    </div>
                </dl>
                <p v-else class="text-sm text-muted-foreground">
                    No lecturer profile found. Contact an administrator if you
                    believe this is an error.
                </p>
            </template>
        </Card>
    </div>
</template>
