<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Send } from 'lucide-vue-next';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed } from 'vue';
import CourseController from '@/actions/App/Http/Controllers/Lecturer/CourseController';
import {
    coursePlanStatusLabel,
    coursePlanStatusSeverity,
} from '@/lib/statusDisplay';
import lecturer from '@/routes/lecturer';

type Course = {
    id: number;
    code: string;
    title: string;
    credits: number;
    semester: number;
    level: number;
    academic_year: string;
    description: string | null;
    plan_status: string;
    plan_review_notes: string | null;
};

const props = defineProps<{ course: Course }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Lecturer Dashboard', href: lecturer.dashboard() },
            { title: 'Courses', href: lecturer.courses.index() },
            { title: 'Plan', href: lecturer.courses.index() },
        ],
    },
});

const canSubmit = computed(
    () =>
        props.course.plan_status === 'draft' ||
        props.course.plan_status === 'rejected',
);

const form = useForm({ description: props.course.description ?? '' });
const submitForm = useForm({});

function savePlan(): void {
    form.patch(CourseController.update(props.course.id).url, {
        preserveScroll: true,
    });
}

function submitForApproval(): void {
    submitForm.post(CourseController.submit(props.course.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Course plan · ${course.code}`" />

    <div class="space-y-4 p-4">
        <Link :href="lecturer.courses.index().url">
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
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <BookOpen class="size-5" />
                        <span>{{ course.code }} — {{ course.title }}</span>
                    </div>
                    <Tag
                        :value="coursePlanStatusLabel(course.plan_status)"
                        :severity="coursePlanStatusSeverity(course.plan_status)"
                    />
                </div>
            </template>
            <template #content>
                <dl class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">Level</dt>
                        <dd class="text-sm">L{{ course.level }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Year</dt>
                        <dd class="text-sm">{{ course.academic_year }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Semester</dt>
                        <dd class="text-sm">S{{ course.semester }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Credits</dt>
                        <dd class="text-sm">{{ course.credits }}</dd>
                    </div>
                </dl>
            </template>
        </Card>

        <Message
            v-if="course.plan_status === 'rejected' && course.plan_review_notes"
            severity="error"
            :closable="false"
        >
            <span class="font-medium">Review notes:</span>
            {{ course.plan_review_notes }}
        </Message>

        <Card>
            <template #title>Course plan</template>
            <template #content>
                <form class="space-y-4" @submit.prevent="savePlan">
                    <div class="space-y-1">
                        <label for="plan-description" class="text-sm font-medium">
                            Description
                        </label>
                        <Textarea
                            id="plan-description"
                            v-model="form.description"
                            rows="8"
                            class="w-full"
                            :invalid="!!form.errors.description"
                        />
                        <Message
                            v-if="form.errors.description"
                            severity="error"
                            :closable="false"
                            size="small"
                        >
                            {{ form.errors.description }}
                        </Message>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <Button
                            type="submit"
                            label="Save plan"
                            severity="secondary"
                            :loading="form.processing"
                        />
                        <Button
                            type="button"
                            label="Submit for approval"
                            :disabled="!canSubmit"
                            :loading="submitForm.processing"
                            @click="submitForApproval"
                        >
                            <template #icon>
                                <Send class="size-4" />
                            </template>
                        </Button>
                    </div>
                    <p
                        v-if="!canSubmit"
                        class="text-right text-xs text-muted-foreground"
                    >
                        This plan is
                        {{ coursePlanStatusLabel(course.plan_status) }} and
                        cannot be resubmitted.
                    </p>
                </form>
            </template>
        </Card>
    </div>
</template>
