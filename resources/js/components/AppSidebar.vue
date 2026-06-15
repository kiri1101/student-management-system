<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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

/**
 * The role-aware nav links carry every per-feature Wayfinder route barrel,
 * which doesn't tree-shake and would otherwise accrete into the entry chunk as
 * features grow it past the 500 kB budget. Loading it asynchronously keeps the
 * sidebar shell (logo, footer) and page content eager while those barrels are
 * code-split out of the entry; the nav links populate a tick later, on first
 * load. The logo's dashboard() link stays eager — it's the only route the shell
 * itself needs.
 */
const AppSidebarNav = defineAsyncComponent(() => import('@/components/AppSidebarNav.vue'));
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
            <AppSidebarNav />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
