<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Banknote,
    BookOpen,
    CalendarCheck,
    CalendarClock,
    Database,
    FilePlus2,
    FileText,
    Gavel,
    GraduationCap,
    Inbox,
    LayoutGrid,
    ScrollText,
    ShieldQuestion,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import { dashboard } from '@/routes';
import { index as accountantDeferralsIndex } from '@/routes/accountant/deferrals';
import { index as accountantPaymentsIndex } from '@/routes/accountant/payments';
import { index as adminAuditLogsIndex } from '@/routes/admin/audit-logs';
import { index as adminFeesIndex } from '@/routes/admin/fees';
import { index as adminReferencesIndex } from '@/routes/admin/references';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { create as applicationCreate } from '@/routes/application';
import { index as lecturerCoursesIndex } from '@/routes/lecturer/courses';
import { index as saoApplicationsIndex } from '@/routes/sao/applications';
import { index as saoCoursesIndex } from '@/routes/sao/courses';
import { index as saoDisputesIndex } from '@/routes/sao/disputes';
import { check as standingCheck } from '@/routes/standing';
import { index as studentAssignmentsIndex } from '@/routes/student/assignments';
import { index as studentAttendanceIndex } from '@/routes/student/attendance';
import { index as studentCoursesIndex } from '@/routes/student/courses';
import { index as studentPaymentsIndex } from '@/routes/student/payments';
import { index as studentResultsIndex } from '@/routes/student/results';
import type { NavItem } from '@/types';

const page = usePage();
const roles = computed<string[]>(() => page.props.auth?.roles ?? []);

/**
 * Role-aware main navigation, computed from the shared `auth.roles` array.
 *
 * This component is intentionally split out of AppSidebar and loaded
 * asynchronously: it carries every Wayfinder route barrel the nav links to,
 * which would otherwise accrete into the entry chunk as features grow it past
 * the 500 kB budget. The sidebar shell and page content render eagerly; the nav
 * links populate from this code-split chunk a tick later (once, on first load).
 *
 * Every entry links to a route that already exists; "coming soon" dashboards
 * (lecturer/accountant/student) only expose the shared Dashboard link. The
 * list is assembled in a fixed priority order, then de-duplicated by title so
 * a user holding several roles gets a stable, gap-free union (B13, #34).
 */
const mainNavItems = computed<NavItem[]>(() => {
    const hasRole = (role: string): boolean => roles.value.includes(role);

    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (hasRole('admin')) {
        items.push(
            {
                title: 'Users',
                href: adminUsersIndex(),
                icon: Users,
            },
            {
                title: 'Reference data',
                href: adminReferencesIndex(),
                icon: Database,
            },
            {
                title: 'Fees',
                href: adminFeesIndex(),
                icon: Banknote,
            },
            {
                title: 'Audit logs',
                href: adminAuditLogsIndex(),
                icon: ScrollText,
            },
        );
    }

    // SAO routes are also reachable by admins (role:sao,admin), so surface the
    // review queue for either role.
    if (hasRole('sao') || hasRole('admin')) {
        items.push(
            {
                title: 'Application review',
                href: saoApplicationsIndex(),
                icon: Inbox,
            },
            {
                title: 'Courses',
                href: saoCoursesIndex(),
                icon: BookOpen,
            },
            {
                title: 'Disputes',
                href: saoDisputesIndex(),
                icon: Gavel,
            },
        );
    }

    if (hasRole('lecturer')) {
        items.push({
            title: 'My courses',
            href: lecturerCoursesIndex(),
            icon: BookOpen,
        });
    }

    // Payment review is reachable by accountants and admins (role:accountant,admin).
    if (hasRole('accountant') || hasRole('admin')) {
        items.push(
            {
                title: 'Payment review',
                href: accountantPaymentsIndex(),
                icon: Wallet,
            },
            {
                title: 'Deferrals',
                href: accountantDeferralsIndex(),
                icon: CalendarClock,
            },
        );
    }

    // Staff payment-standing lookup (role:sao,accountant,admin).
    if (hasRole('sao') || hasRole('accountant') || hasRole('admin')) {
        items.push({
            title: 'Standing check',
            href: standingCheck(),
            icon: ShieldQuestion,
        });
    }

    if (hasRole('student')) {
        items.push(
            {
                title: 'My payments',
                href: studentPaymentsIndex(),
                icon: Wallet,
            },
            {
                title: 'My courses',
                href: studentCoursesIndex(),
                icon: BookOpen,
            },
            {
                title: 'My attendance',
                href: studentAttendanceIndex(),
                icon: CalendarCheck,
            },
            {
                title: 'My assignments',
                href: studentAssignmentsIndex(),
                icon: FileText,
            },
            {
                title: 'My results',
                href: studentResultsIndex(),
                icon: GraduationCap,
            },
        );
    }

    if (hasRole('applicant')) {
        items.push({
            title: 'New application',
            href: applicationCreate(),
            icon: FilePlus2,
        });
    }

    const seen = new Set<string>();

    return items.filter((item) => {
        if (seen.has(item.title)) {
            return false;
        }

        seen.add(item.title);

        return true;
    });
});
</script>

<template>
    <NavMain :items="mainNavItems" />
</template>
