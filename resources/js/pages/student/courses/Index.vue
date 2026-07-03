<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { computed } from 'vue';
import { degreeLabel } from '@/lib/statusDisplay';
import student from '@/routes/student';

type CourseRow = {
    id: number;
    code: string;
    title: string;
    credits: number;
    semester: number;
    description: string | null;
    lecturer_name: string | null;
    sessions_count: number;
    assignments_count: number;
};

type Cohort = {
    level: number;
    academic_year: string;
    program_offering: {
        degree_program: string;
        department: { name: string; code: string } | null;
    } | null;
};

const props = defineProps<{ courses: CourseRow[]; cohort: Cohort | null }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My courses', href: student.courses.index() }],
    },
});

/**
 * Courses arrive ordered by semester then code; group them into per-semester
 * sections so each semester renders as its own table.
 */
const semesters = computed(() => {
    const groups = new Map<number, CourseRow[]>();

    for (const course of props.courses) {
        const list = groups.get(course.semester) ?? [];
        list.push(course);
        groups.set(course.semester, list);
    }

    return [...groups.entries()]
        .sort(([a], [b]) => a - b)
        .map(([semester, courses]) => ({ semester, courses }));
});

const cohortLine = computed(() => {
    if (!props.cohort) {
        return null;
    }

    const parts: string[] = [];
    const offering = props.cohort.program_offering;

    if (offering) {
        const degree = degreeLabel(offering.degree_program);
        parts.push(
            offering.department
                ? `${offering.department.name} (${offering.department.code}) — ${degree}`
                : degree,
        );
    }

    parts.push(`Level ${props.cohort.level}`, props.cohort.academic_year);

    return parts.join(' · ');
});

function totalCredits(courses: CourseRow[]): number {
    return courses.reduce((sum, course) => sum + course.credits, 0);
}
</script>

<template>
    <Head title="My courses" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <section>
            <p
                class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
            >
                Student
            </p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">My courses</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                The courses you'll be taking each semester in your programme.
            </p>
            <p
                v-if="cohortLine"
                class="mt-2 text-sm font-medium text-primary-700 dark:text-primary-400"
            >
                {{ cohortLine }}
            </p>
        </section>

        <Card v-for="group in semesters" :key="group.semester">
            <template #title>
                <div class="flex flex-wrap items-center gap-2">
                    <BookOpen class="size-5" />
                    <span>Semester {{ group.semester }}</span>
                    <span class="text-sm font-normal text-muted-foreground">
                        {{ group.courses.length }}
                        {{ group.courses.length === 1 ? 'course' : 'courses' }}
                        · {{ totalCredits(group.courses) }} credits
                    </span>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="group.courses"
                    data-key="id"
                    striped-rows
                    responsive-layout="scroll"
                >
                    <Column field="code" header="Code" style="width: 8rem" />
                    <Column header="Course">
                        <template #body="{ data }">
                            <div class="font-medium">{{ data.title }}</div>
                            <div
                                v-if="data.description"
                                class="mt-0.5 line-clamp-2 text-xs text-muted-foreground"
                            >
                                {{ data.description }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Credits" style="width: 6.5rem">
                        <template #body="{ data }">
                            {{ data.credits }}
                        </template>
                    </Column>
                    <Column header="Lecturer" style="width: 12rem">
                        <template #body="{ data }">
                            <span v-if="data.lecturer_name">
                                {{ data.lecturer_name }}
                            </span>
                            <span v-else class="text-muted-foreground">
                                Not yet assigned
                            </span>
                        </template>
                    </Column>
                    <Column header="Sessions" style="width: 6.5rem">
                        <template #body="{ data }">
                            {{ data.sessions_count }}
                        </template>
                    </Column>
                    <Column header="Assignments" style="width: 7.5rem">
                        <template #body="{ data }">
                            {{ data.assignments_count }}
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>

        <Card v-if="courses.length === 0">
            <template #content>
                <div class="py-6 text-center text-sm text-muted-foreground">
                    No approved courses have been published for your programme
                    yet. Check back once your department finalises the course
                    plans.
                </div>
            </template>
        </Card>
    </div>
</template>
