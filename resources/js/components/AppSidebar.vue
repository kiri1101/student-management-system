<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    BookOpen,
    CalendarCheck,
    CalendarClock,
    Database,
    FilePlus2,
    Inbox,
    LayoutGrid,
    ScrollText,
    ShieldQuestion,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
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
import { check as standingCheck } from '@/routes/standing';
import { index as studentAttendanceIndex } from '@/routes/student/attendance';
import { index as studentPaymentsIndex } from '@/routes/student/payments';
import type { NavItem } from '@/types';

const page = usePage();
const roles = computed<string[]>(() => page.props.auth?.roles ?? []);

/**
 * Role-aware main navigation, computed from the shared `auth.roles` array.
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
                title: 'My attendance',
                href: studentAttendanceIndex(),
                icon: CalendarCheck,
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
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
