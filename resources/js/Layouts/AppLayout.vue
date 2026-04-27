<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { BriefcaseBusiness, Building2, LayoutDashboard, LogOut, Menu, PanelsTopLeft, Settings, ShieldCheck, UserCircle2, X } from 'lucide-vue-next';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import FlashToasts from '../Components/FlashToasts.vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const sidebarOpen = ref(false);
const logoutForm = useForm({});

const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => Boolean(user.value?.is_admin));
const currentUrl = computed(() => page.url);
const siteName = computed(() => page.props.app?.site_name || 'Issue Tracker');
const pendingNudgeCount = computed(() => Number(page.props.app?.pending_nudge_count ?? 0));

const navItems = computed(() => [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Clients', href: '/clients', icon: Building2 },
    { label: 'Projects', href: '/projects', icon: BriefcaseBusiness },
    { label: 'Issues', href: '/issues', icon: PanelsTopLeft },
    { label: 'Kanban', href: '/kanban', icon: PanelsTopLeft },
    { label: 'Daily Activity', href: '/issues/daily-activity', icon: PanelsTopLeft },
    { label: 'Profile', href: '/profile', icon: UserCircle2 },
    ...(isAdmin.value
        ? [
            { label: 'Users', href: '/admin/users', icon: ShieldCheck },
            { label: 'Settings', href: '/admin/settings', icon: Settings },
        ]
        : []),
]);

const logout = () => logoutForm.post('/logout');

const isItemActive = (href) => {
    if (href === '/issues') {
        return currentUrl.value.startsWith('/issues') && !currentUrl.value.startsWith('/issues/daily-activity');
    }

    return currentUrl.value.startsWith(href);
};
</script>

<template>
    <div class="app-shell">
        <FlashToasts />

        <aside class="sidebar-panel" :class="{ open: sidebarOpen }">
            <div class="sidebar-brand">
                <div>
                    <p class="sidebar-eyebrow">Workspace</p>
                    <h1>{{ siteName }}</h1>
                </div>
                <button class="btn btn-light d-lg-none rounded-circle" @click="sidebarOpen = false">
                    <X :size="18" />
                </button>
            </div>

            <nav class="sidebar-nav">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="sidebar-link"
                    :class="{ active: isItemActive(item.href) }"
                    @click="sidebarOpen = false"
                >
                    <component :is="item.icon" :size="18" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="sidebar-user">
                <div class="sidebar-user-card">
                    <div class="sidebar-avatar">
                        <img v-if="user?.avatar_url" :src="user.avatar_url" :alt="user?.name">
                        <UserCircle2 v-else :size="30" />
                    </div>
                    <div>
                        <strong>{{ user?.name }}</strong>
                        <p>{{ user?.email }}</p>
                    </div>
                </div>
                <button class="btn btn-outline-light w-100 rounded-pill" :disabled="logoutForm.processing" @click="logout">
                    <span v-if="logoutForm.processing" class="spinner-border spinner-border-sm me-2" />
                    <LogOut :size="16" class="me-2" />
                    Logout
                </button>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="sidebar-backdrop d-lg-none" @click="sidebarOpen = false" />

        <div class="main-panel">
            <header class="topbar">
                <button class="btn btn-light rounded-circle d-lg-none" @click="sidebarOpen = true">
                    <Menu :size="18" />
                </button>
                <div>
                    <p class="section-kicker">{{ title || 'Workspace' }}</p>
                    <Breadcrumbs :items="breadcrumbs" />
                </div>
                <Link
                    v-if="pendingNudgeCount > 0"
                    href="/issues?status=inprogress"
                    class="btn btn-sm btn-light border rounded-pill ms-auto"
                >
                    {{ pendingNudgeCount }} aging issues
                </Link>
            </header>

            <main class="page-body">
                <slot />
            </main>
        </div>
    </div>
</template>
