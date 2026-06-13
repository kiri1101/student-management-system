<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Database,
    FilePlus2,
    Inbox,
    LayoutGrid,
    ScrollText,
    Users,
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
import admin from '@/routes/admin';
import application from '@/routes/application';
import sao from '@/routes/sao';
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
                href: admin.users.index(),
                icon: Users,
            },
            {
                title: 'Reference data',
                href: admin.references.index(),
                icon: Database,
            },
            {
                title: 'Fees',
                href: admin.fees.index(),
                icon: Banknote,
            },
            {
                title: 'Audit logs',
                href: admin.auditLogs.index(),
                icon: ScrollText,
            },
        );
    }

    // SAO routes are also reachable by admins (role:sao,admin), so surface the
    // review queue for either role.
    if (hasRole('sao') || hasRole('admin')) {
        items.push({
            title: 'Application review',
            href: sao.applications.index(),
            icon: Inbox,
        });
    }

    if (hasRole('applicant')) {
        items.push({
            title: 'New application',
            href: application.create(),
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
