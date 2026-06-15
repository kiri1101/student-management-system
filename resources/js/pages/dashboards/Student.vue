<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bell,
    CalendarClock,
    CalendarX,
    Check,
    FileText,
    GraduationCap,
} from 'lucide-vue-next';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Tag from 'primevue/tag';
import ApplicationController from '@/actions/App/Http/Controllers/Applications/ApplicationController';
import {
    degreeLabel,
    enrollmentLabel,
    enrollmentSeverity,
    statusLabel,
    statusSeverity,
} from '@/lib/statusDisplay';
import student from '@/routes/student';

type Department = { name: string; code: string } | null;

type ProgramOffering = {
    degree_program: string;
    department: Department;
} | null;

type Profile = {
    matricule: string;
    level: number;
    academic_year: string;
    status: string;
    enrolled_at: string | null;
    program_offering: ProgramOffering;
} | null;

type Application = {
    id: number;
    status: string;
    level: number;
    submitted_at: string | null;
    decided_at: string | null;
    program_offering: ProgramOffering;
};

type NotificationData = {
    type: 'cancelled' | 'rescheduled';
    course_id: number;
    course_code: string;
    course_title: string;
    session_id: number;
    scheduled_for: string;
    reason: string | null;
    previous_scheduled_for: string | null;
};

type AppNotification = {
    id: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
};

defineProps<{
    profile: Profile;
    applications: Application[];
    notifications: AppNotification[];
    unreadCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Student Dashboard',
                href: student.dashboard(),
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

function programmeLine(offering: ProgramOffering): string {
    if (!offering) {
        return '—';
    }

    const degree = degreeLabel(offering.degree_program);

    return offering.department
        ? `${offering.department.name} (${offering.department.code}) — ${degree}`
        : degree;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

function notificationTitle(item: AppNotification): string {
    return item.data.type === 'cancelled'
        ? `${item.data.course_code} session cancelled`
        : `${item.data.course_code} session rescheduled`;
}

function markRead(item: AppNotification): void {
    if (item.read_at) {
        return;
    }

    router.post(
        `/student/notifications/${item.id}/read`,
        {},
        { preserveScroll: true },
    );
}

function markAllRead(): void {
    router.post('/student/notifications/read-all', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Student Dashboard" />

    <div class="space-y-4 p-4">
        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Bell class="size-5" />
                        <span>Notifications</span>
                        <Badge
                            v-if="unreadCount > 0"
                            :value="unreadCount"
                            severity="danger"
                        />
                    </div>
                    <Button
                        label="Mark all read"
                        severity="secondary"
                        text
                        size="small"
                        :disabled="unreadCount === 0"
                        @click="markAllRead"
                    >
                        <template #icon>
                            <Check class="size-4" />
                        </template>
                    </Button>
                </div>
            </template>
            <template #content>
                <ul v-if="notifications.length" class="space-y-1">
                    <li
                        v-for="item in notifications"
                        :key="item.id"
                        class="flex items-start gap-3 rounded-md border border-transparent p-3 transition-colors"
                        :class="
                            item.read_at
                                ? 'opacity-70'
                                : 'cursor-pointer bg-muted/50 hover:bg-muted'
                        "
                        @click="markRead(item)"
                    >
                        <span class="mt-0.5 shrink-0 text-muted-foreground">
                            <CalendarX
                                v-if="item.data.type === 'cancelled'"
                                class="size-5"
                            />
                            <CalendarClock v-else class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1 space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="!item.read_at"
                                    class="size-2 shrink-0 rounded-full bg-primary"
                                    aria-hidden="true"
                                />
                                <p class="text-sm font-medium">
                                    {{ notificationTitle(item) }}
                                </p>
                            </div>
                            <p
                                v-if="item.data.type === 'cancelled'"
                                class="text-xs text-muted-foreground"
                            >
                                {{ formatDateTime(item.data.scheduled_for) }}
                                <template v-if="item.data.reason">
                                    · {{ item.data.reason }}
                                </template>
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                Moved from
                                {{
                                    formatDateTime(
                                        item.data.previous_scheduled_for,
                                    )
                                }}
                                to
                                {{ formatDateTime(item.data.scheduled_for) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDateTime(item.created_at) }}
                            </p>
                        </div>
                        <Button
                            v-if="!item.read_at"
                            label="Mark read"
                            severity="secondary"
                            text
                            size="small"
                            class="shrink-0"
                            @click.stop="markRead(item)"
                        />
                    </li>
                </ul>
                <p v-else class="py-6 text-center text-sm text-muted-foreground">
                    No notifications yet.
                </p>
            </template>
        </Card>

        <Card>
            <template #title>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <GraduationCap class="size-5" />
                        <span>My enrollment</span>
                    </div>
                    <Tag
                        v-if="profile"
                        :value="enrollmentLabel(profile.status)"
                        :severity="enrollmentSeverity(profile.status)"
                        class="capitalize"
                    />
                </div>
            </template>
            <template #content>
                <dl
                    v-if="profile"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">Matricule</dt>
                        <dd class="font-mono text-sm font-semibold uppercase">
                            {{ profile.matricule }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Programme</dt>
                        <dd class="text-sm">
                            {{ programmeLine(profile.program_offering) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Level</dt>
                        <dd class="text-sm">{{ profile.level }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Academic year
                        </dt>
                        <dd class="text-sm">
                            {{ profile.academic_year }}
                            <span
                                v-if="profile.enrolled_at"
                                class="text-muted-foreground"
                            >
                                · enrolled {{ formatDate(profile.enrolled_at) }}
                            </span>
                        </dd>
                    </div>
                </dl>
                <p v-else class="text-sm text-muted-foreground">
                    No enrollment record found. Contact the student affairs
                    office if you believe this is an error.
                </p>
            </template>
        </Card>

        <Card>
            <template #title>
                <div class="flex items-center gap-2">
                    <FileText class="size-5" />
                    <span>Application history</span>
                </div>
            </template>
            <template #content>
                <DataTable
                    :value="applications"
                    data-key="id"
                    striped-rows
                    :paginator="applications.length > 10"
                    :rows="10"
                    responsive-layout="scroll"
                >
                    <Column header="Programme">
                        <template #body="{ data }">
                            <span>{{
                                programmeLine(data.program_offering)
                            }}</span>
                        </template>
                    </Column>
                    <Column
                        field="level"
                        header="Level"
                        sortable
                        style="width: 6rem"
                    />
                    <Column
                        header="Status"
                        sortable
                        sort-field="status"
                        style="width: 11rem"
                    >
                        <template #body="{ data }">
                            <Tag
                                :value="statusLabel(data.status)"
                                :severity="statusSeverity(data.status)"
                            />
                        </template>
                    </Column>
                    <Column
                        header="Submitted"
                        sortable
                        sort-field="submitted_at"
                        style="width: 9rem"
                    >
                        <template #body="{ data }">
                            <span>{{ formatDate(data.submitted_at) }}</span>
                        </template>
                    </Column>
                    <Column
                        header="Decided"
                        sortable
                        sort-field="decided_at"
                        style="width: 9rem"
                    >
                        <template #body="{ data }">
                            <span>{{ formatDate(data.decided_at) }}</span>
                        </template>
                    </Column>
                    <Column header="" style="width: 6rem">
                        <template #body="{ data }">
                            <div class="flex items-center justify-end">
                                <Link
                                    :href="
                                        ApplicationController.show(data.id).url
                                    "
                                >
                                    <Button
                                        label="View"
                                        severity="secondary"
                                        text
                                        size="small"
                                    />
                                </Link>
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <p
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No applications on record.
                        </p>
                    </template>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
