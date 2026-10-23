<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, FileSpreadsheet, History, Users } from '@lucide/vue';
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
import { useTrans } from '@/composables/useTrans';
import { priceHistory } from '@/routes';
import { index as admins } from '@/routes/admins';
import { index as priceUploads } from '@/routes/price-uploads';
import { index as synonyms } from '@/routes/synonyms';
import type { NavItem } from '@/types';

const { t } = useTrans();
const page = usePage();

// Computed, so the titles follow a language switch: the sidebar layout is not remounted.
const mainNavItems = computed<NavItem[]>(() => [
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
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="priceUploads()">
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
