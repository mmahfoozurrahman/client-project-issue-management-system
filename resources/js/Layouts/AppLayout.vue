<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { BriefcaseBusiness, Building2, LayoutDashboard, LogOut, Menu, PanelsTopLeft, PanelLeftClose, PanelLeftOpen, Settings, ShieldCheck, Tag, UserCircle2, X } from 'lucide-vue-next';
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

const canAccessProjects = computed(() => Boolean(page.props.auth?.can_access_projects));
const canAccessClients = computed(() => Boolean(page.props.auth?.can_access_clients));
const canAccessTags = computed(() => Boolean(page.props.auth?.can_access_tags));

const showProjectNav = computed(() => isAdmin.value || canAccessProjects.value);

const page = usePage();
const sidebarOpen = ref(false);
const logoutForm = useForm({});

// Desktop sidebar collapse state, remembered across page loads.
const SIDEBAR_COLLAPSED_KEY = 'sidebar-collapsed';
const sidebarCollapsed = ref(
    typeof window !== 'undefined' && window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true',
);

watch(sidebarCollapsed, (collapsed) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(collapsed));
    }
});

const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => Boolean(user.value?.is_admin));
const hasOwnProjects = computed(() => Boolean(page.props.auth?.has_own_projects));
// const showProjectNav = computed(() => isAdmin.value || hasOwnProjects.value);
const currentUrl = computed(() => page.url);
const siteName = computed(() => page.props.app?.site_name || 'Issue Tracker');
const pendingNudgeCount = computed(() => Number(page.props.app?.pending_nudge_count ?? 0));
const pendingCriticalCount = computed(() => Number(page.props.app?.pending_critical_count ?? 0));

const navItems = computed(() => [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    // শুধু অ্যাডমিন হলে Clients লিংক দেখাবে
    ...(isAdmin.value || canAccessClients.value ? [
        { label: 'Clients', href: route('clients.index'), icon: Building2 },
    ] : []),
    ...(showProjectNav.value ? [
        { label: 'Projects', href: '/projects', icon: BriefcaseBusiness },
    ] : []),
    ...(canAccessTags.value ? [
        { label: 'Tags', href: '/tags', icon: Tag },
    ] : []),
    { label: 'Issues', href: '/issues', icon: PanelsTopLeft },
    { label: 'Kanban', href: '/kanban', icon: PanelsTopLeft },
    { label: 'Daily Activity', href: '/issues/daily-activity', icon: PanelsTopLeft },
    { label: 'Profile', href: '/profile', icon: UserCircle2 },
    ...(isAdmin.value
        ? [
            { label: 'Users', href: '/admin/users', icon: ShieldCheck },
            { label: 'Roles', href: '/admin/roles', icon: ShieldCheck },
            { label: 'Permissions', href: '/admin/permissions', icon: Settings },
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
    <div class="app-shell" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
        <FlashToasts />

        <aside class="sidebar-panel" :class="{ open: sidebarOpen }">
            <div class="sidebar-brand">
                <div>
                    <p class="sidebar-eyebrow">Workspace</p>
                    <h1>{{ siteName }}</h1>
                </div>
                <button class="btn btn-light d-xl-none rounded-circle" aria-label="Close navigation" @click="sidebarOpen = false">
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

        <div v-if="sidebarOpen" class="sidebar-backdrop d-xl-none" @click="sidebarOpen = false" />

        <div class="main-panel">
            <header class="topbar">
                <div class="topbar-heading">
                    <button class="btn btn-light rounded-circle d-xl-none" aria-label="Open navigation" @click="sidebarOpen = true">
                        <Menu :size="18" />
                    </button>
                    <button
                        class="btn btn-light rounded-circle d-none d-xl-inline-flex"
                        :aria-expanded="!sidebarCollapsed"
                        :aria-label="sidebarCollapsed ? 'Show sidebar' : 'Hide sidebar'"
                        :title="sidebarCollapsed ? 'Show sidebar' : 'Hide sidebar'"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        <PanelLeftOpen v-if="sidebarCollapsed" :size="18" />
                        <PanelLeftClose v-else :size="18" />
                    </button>
                    <div>
                        <p class="section-kicker">{{ title || 'Workspace' }}</p>
                        <Breadcrumbs :items="breadcrumbs" />
                    </div>
                </div>
                <Link
                    v-if="pendingNudgeCount > 0"
                    href="/issues?at_risk=1"
                    class="btn btn-sm rounded-pill ms-auto topbar-alert"
                    :class="pendingCriticalCount > 0 ? 'btn-danger' : 'btn-warning'"
                >
                    <template v-if="pendingCriticalCount > 0">
                        {{ pendingCriticalCount }} critical idle
                    </template>
                    <template v-else>
                        {{ pendingNudgeCount }} aging issues
                    </template>
                </Link>
            </header>

            <main class="page-body">
                <slot />
            </main>
        </div>
    </div>
</template>
