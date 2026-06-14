<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed } from 'vue';
import CourseController from '@/actions/App/Http/Controllers/Sao/CourseController';
import sao from '@/routes/sao';

type CourseModel = {
    id: number;
    code: string;
    title: string;
    credits: number;
    semester: number;
    level: number;
    academic_year: string;
    plan_status: string;
    offering_label: string;
    lecturer_name: string | null;
    description: string | null;
    program_offering_id: number;
    lecturer_profile_id: number | null;
};

type ProgramOffering = {
    id: number;
    label: string;
    min_level: number;
    max_level: number;
};

type LecturerOption = { id: number; name: string };

const props = defineProps<{
    course: CourseModel | null;
    programOfferings: ProgramOffering[];
    lecturers: LecturerOption[];
}>();

const isEdit = computed(() => props.course !== null);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'SAO Dashboard', href: sao.dashboard() },
            { title: 'Courses', href: sao.courses.index() },
            { title: 'Course', href: sao.courses.index() },
        ],
    },
});

const semesterOptions = [
    { value: 1, label: 'Semester 1' },
    { value: 2, label: 'Semester 2' },
];

const form = useForm({
    program_offering_id: props.course?.program_offering_id ?? null,
    level: props.course?.level ?? null,
    academic_year: props.course?.academic_year ?? '',
    code: props.course?.code ?? '',
    title: props.course?.title ?? '',
    credits: props.course?.credits ?? null,
    semester: props.course?.semester ?? null,
    lecturer_profile_id: props.course?.lecturer_profile_id ?? null,
});

const selectedOffering = computed<ProgramOffering | null>(() => {
    return (
        props.programOfferings.find(
            (offering) => offering.id === form.program_offering_id,
        ) ?? null
    );
});

function submit(): void {
    if (props.course) {
        form.patch(CourseController.update(props.course.id).url, {
            preserveScroll: true,
        });

        return;
    }

    form.post(CourseController.store().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="isEdit ? 'Edit course' : 'New course'" />

    <div class="space-y-4 p-4">
        <Link :href="sao.courses.index().url">
            <Button
                label="Back to courses"
                severity="secondary"
                text
                size="small"
            >
                <template #icon>
                    <ArrowLeft class="size-4" />
                </template>
            </Button>
        </Link>

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <BookOpen class="size-5" />
                    <span>{{ isEdit ? 'Edit course' : 'New course' }}</span>
                </div>
            </template>
            <template #content>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label
                                for="course-offering"
                                class="text-sm font-medium"
                            >
                                Programme offering
                            </label>
                            <Select
                                id="course-offering"
                                v-model="form.program_offering_id"
                                :options="props.programOfferings"
                                option-label="label"
                                option-value="id"
                                placeholder="Select an offering"
                                class="w-full"
                                filter
                                :invalid="!!form.errors.program_offering_id"
                            />
                            <p
                                v-if="form.errors.program_offering_id"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.program_offering_id }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label
                                for="course-level"
                                class="text-sm font-medium"
                            >
                                Level
                            </label>
                            <InputNumber
                                v-model="form.level"
                                input-id="course-level"
                                :min="selectedOffering?.min_level ?? 1"
                                :max="selectedOffering?.max_level ?? 10"
                                show-buttons
                                class="w-full"
                                :invalid="!!form.errors.level"
                            />
                            <p
                                v-if="selectedOffering"
                                class="text-xs text-muted-foreground"
                            >
                                Allowed: L{{ selectedOffering.min_level }}–L{{
                                    selectedOffering.max_level
                                }}
                            </p>
                            <p
                                v-if="form.errors.level"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.level }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label
                                for="course-year"
                                class="text-sm font-medium"
                            >
                                Academic year
                            </label>
                            <InputText
                                id="course-year"
                                v-model="form.academic_year"
                                placeholder="e.g. 2025/2026"
                                class="w-full"
                                :invalid="!!form.errors.academic_year"
                            />
                            <p
                                v-if="form.errors.academic_year"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.academic_year }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label
                                for="course-code"
                                class="text-sm font-medium"
                            >
                                Code
                            </label>
                            <InputText
                                id="course-code"
                                v-model="form.code"
                                placeholder="e.g. CS101"
                                class="w-full"
                                :invalid="!!form.errors.code"
                            />
                            <p
                                v-if="form.errors.code"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.code }}
                            </p>
                        </div>

                        <div class="space-y-1 md:col-span-2">
                            <label
                                for="course-title"
                                class="text-sm font-medium"
                            >
                                Title
                            </label>
                            <InputText
                                id="course-title"
                                v-model="form.title"
                                class="w-full"
                                :invalid="!!form.errors.title"
                            />
                            <p
                                v-if="form.errors.title"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label
                                for="course-credits"
                                class="text-sm font-medium"
                            >
                                Credits
                            </label>
                            <InputNumber
                                v-model="form.credits"
                                input-id="course-credits"
                                :min="0"
                                show-buttons
                                class="w-full"
                                :invalid="!!form.errors.credits"
                            />
                            <p
                                v-if="form.errors.credits"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.credits }}
                            </p>
                        </div>

                        <div class="space-y-1">
                            <label
                                for="course-semester"
                                class="text-sm font-medium"
                            >
                                Semester
                            </label>
                            <Select
                                id="course-semester"
                                v-model="form.semester"
                                :options="semesterOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Select a semester"
                                class="w-full"
                                :invalid="!!form.errors.semester"
                            />
                            <p
                                v-if="form.errors.semester"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.semester }}
                            </p>
                        </div>

                        <div class="space-y-1 md:col-span-2">
                            <label
                                for="course-lecturer"
                                class="text-sm font-medium"
                            >
                                Lecturer
                                <span class="text-muted-foreground"
                                    >(optional)</span
                                >
                            </label>
                            <Select
                                id="course-lecturer"
                                v-model="form.lecturer_profile_id"
                                :options="props.lecturers"
                                option-label="name"
                                option-value="id"
                                placeholder="Unassigned"
                                class="w-full"
                                filter
                                show-clear
                                :invalid="!!form.errors.lecturer_profile_id"
                            />
                            <p
                                v-if="form.errors.lecturer_profile_id"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.lecturer_profile_id }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <Link :href="sao.courses.index().url">
                            <Button
                                type="button"
                                label="Cancel"
                                severity="secondary"
                                text
                                :disabled="form.processing"
                            />
                        </Link>
                        <Button
                            type="submit"
                            :label="isEdit ? 'Save changes' : 'Create course'"
                            :loading="form.processing"
                        />
                    </div>
                </form>
            </template>
        </Card>
    </div>
</template>
