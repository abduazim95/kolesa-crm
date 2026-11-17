<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    FolderOpen,
    History,
    LayoutGrid,
    List,
    ShoppingCart,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { index as advertsIndex } from '@/routes/adverts';
import { index as cartIndex } from '@/routes/adverts/cart';
import { index as groupsIndex } from '@/routes/adverts/groups';
import { index as servicesIndex } from '@/routes/adverts/services';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Главная',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Объявления',
        href: advertsIndex(),
        icon: List,
    },
    {
        title: 'Группы',
        href: groupsIndex(),
        icon: FolderOpen,
    },
    {
        title: 'Корзина',
        href: cartIndex(),
        icon: ShoppingCart,
    },
    {
        title: 'История',
        href: servicesIndex(),
        icon: History,
    },
];

const footerNavItems: NavItem[] = [];
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
