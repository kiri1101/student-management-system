<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen, Construction } from 'lucide-vue-next';
import Card from 'primevue/card';
import lecturer from '@/routes/lecturer';

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

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head title="Lecturer Dashboard" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <BookOpen class="size-5" />
                    <span>My profile</span>
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

        <Card>
            <template #content>
                <div class="flex flex-col items-center gap-2 py-8 text-center">
                    <Construction class="size-8 text-muted-foreground/40" />
                    <p class="text-sm font-medium">
                        Course management is coming soon
                    </p>
                    <p class="max-w-md text-sm text-muted-foreground">
                        Course planning, attendance, assignments and results
                        will appear here once the course-management module
                        ships.
                    </p>
                </div>
            </template>
        </Card>
    </div>
</template>
