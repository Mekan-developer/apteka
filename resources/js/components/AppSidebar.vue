<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BookOpen,
    FileSpreadsheet,
    FolderGit2,
    History,
    LayoutGrid,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
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
import { useTrans } from '@/composables/useTrans';
import { dashboard, priceHistory } from '@/routes';
import { index as admins } from '@/routes/admins';
import { index as priceUploads } from '@/routes/price-uploads';
import { index as synonyms } from '@/routes/synonyms';
import type { NavItem } from '@/types';

const { t } = useTrans();
const page = usePage();

// Computed, so the titles follow a language switch: the sidebar layout is not remounted.
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: t('price_uploads.title'),
        href: priceUploads(),
        icon: FileSpreadsheet,
    },
    {
        title: t('synonyms.title'),
        href: synonyms(),
        icon: ArrowLeftRight,
    },
    {
        title: t('price_history.title'),
        href: priceHistory(),
        icon: History,
    },
    ...(page.props.auth.can.manageAdmins
        ? [{ title: t('admins.title'), href: admins(), icon: Users }]
        : []),
]);

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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
