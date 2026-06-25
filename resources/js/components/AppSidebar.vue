<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, FolderGit2, LayoutGrid, Server, Settings, GraduationCap, Network, ShoppingBag, Ruler } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
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
import type { NavItem } from '@/types';

const page = usePage();
const auth = computed(() => page.props.auth as any);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    const currentChurch = auth.value?.currentChurch;
    if (currentChurch) {
        const role = currentChurch.pivot?.role;
        const modules = currentChurch.pivot?.modules || [];
        
        // Admins and Managers get all modules. Users get specific modules.
        const hasRacksAccess = ['Admin', 'Manager'].includes(role) || modules.includes('racks');
        
        if (hasRacksAccess) {
            items.push({
                title: 'Rack Builder',
                href: '/racks',
                icon: Server,
            });
        }

        const hasTrainingsAccess = ['Admin', 'Manager'].includes(role) || modules.includes('trainings');
        if (hasTrainingsAccess) {
            items.push({
                title: 'Trainings',
                href: '/trainings',
                icon: GraduationCap,
            });
        }

        const hasDiagramsAccess = ['Admin', 'Manager'].includes(role) || modules.includes('diagrams');
        if (hasDiagramsAccess) {
            items.push({
                title: 'Technical Diagrams',
                href: '/diagrams',
                icon: Network,
            });
        }

        const hasShoppingAccess = ['Admin', 'Manager'].includes(role) || modules.includes('shopping_lists');
        if (hasShoppingAccess) {
            items.push({
                title: 'Shopping Lists',
                href: '/shopping-lists',
                icon: ShoppingBag,
            });
        }

        const hasCablesAccess = ['Admin', 'Manager'].includes(role) || modules.includes('cables');
        if (hasCablesAccess) {
            items.push({
                title: 'Cable Calculator',
                href: '/cable-plans',
                icon: Ruler,
            });
        }

        // Admins and Managers get Church Settings
        const hasSettingsAccess = ['Admin', 'Manager'].includes(role);
        if (hasSettingsAccess) {
            items.push({
                title: 'Church Settings',
                href: '/church/settings',
                icon: Settings,
            });
        }
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
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
            <NavFooter :items="footerNavItems" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
