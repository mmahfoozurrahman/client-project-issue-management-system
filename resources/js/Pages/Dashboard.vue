<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import StatusPill from '../Components/StatusPill.vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    counts: Object,
    statusIssues: Object,
    analytics: Object,
    breadcrumbs: Array,
});

const activeStatus = ref('inprogress');
const statusTabs = [
    { key: 'inprogress', label: 'In Progress', subtitle: 'Active focus lane' },
    { key: 'todo', label: 'Todo', subtitle: 'Upcoming commitments' },
    { key: 'done', label: 'Done', subtitle: 'Completed momentum' },
];

const activeIssueRows = computed(() => props.statusIssues?.[activeStatus.value] ?? []);

const formatIssueDate = (value) => {
    if (!value) return 'Recently created';

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
};

const weeklyChart = computed(() => props.analytics?.weekly ?? []);
const monthlyChart = computed(() => props.analytics?.monthly ?? []);

const weeklyMax = computed(() => {
    const peak = Math.max(1, ...weeklyChart.value.map((entry) => Math.max(entry.created ?? 0, entry.completed ?? 0)));

    return peak;
});

const monthlyMax = computed(() => {
    const peak = Math.max(1, ...monthlyChart.value.map((entry) => Math.max(entry.created ?? 0, entry.completed ?? 0)));

    return peak;
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard" :breadcrumbs="breadcrumbs">
        <section class="hero-panel dashboard-hero mb-4">
            <div class="dashboard-hero-copy">
                <span class="pill-tag">Command Center</span>
                <h2>Tenant workflow, at a glance.</h2>
                <p>Monitor client intake, project movement, and issue resolution from one calm command view.</p>
            </div>
            <div class="stats-grid">
                <article class="metric-card">
                    <span>Clients</span>
                    <strong>{{ counts.clients }}</strong>
                </article>
                <article class="metric-card">
                    <span>Projects</span>
                    <strong>{{ counts.projects }}</strong>
                </article>
                <article class="metric-card">
                    <span>Issues</span>
                    <strong>{{ counts.issues }}</strong>
                </article>
            </div>
        </section>

        <section class="panel-card mb-4">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Progress Analytics</p>
                    <h3 class="panel-title">Weekly and monthly momentum</h3>
                </div>
                <span class="badge text-bg-light rounded-pill">Created vs Completed</span>
            </div>

            <div class="row g-4">
                <div class="col-xl-6">
                    <div class="analytics-card">
                        <h4 class="analytics-title">Weekly Activity</h4>
                        <div class="trend-chart">
                            <div v-for="point in weeklyChart" :key="`w-${point.label}`" class="trend-column">
                                <div class="bars">
                                    <span
                                        class="bar created"
                                        :style="{ height: `${Math.max(6, ((point.created ?? 0) / weeklyMax) * 100)}%` }"
                                        :title="`Created: ${point.created ?? 0}`"
                                    />
                                    <span
                                        class="bar completed"
                                        :style="{ height: `${Math.max(6, ((point.completed ?? 0) / weeklyMax) * 100)}%` }"
                                        :title="`Completed: ${point.completed ?? 0}`"
                                    />
                                </div>
                                <small>{{ point.label }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="analytics-card">
                        <h4 class="analytics-title">Monthly Activity</h4>
                        <div class="trend-chart">
                            <div v-for="point in monthlyChart" :key="`m-${point.label}`" class="trend-column">
                                <div class="bars">
                                    <span
                                        class="bar created"
                                        :style="{ height: `${Math.max(6, ((point.created ?? 0) / monthlyMax) * 100)}%` }"
                                        :title="`Created: ${point.created ?? 0}`"
                                    />
                                    <span
                                        class="bar completed"
                                        :style="{ height: `${Math.max(6, ((point.completed ?? 0) / monthlyMax) * 100)}%` }"
                                        :title="`Completed: ${point.completed ?? 0}`"
                                    />
                                </div>
                                <small>{{ point.label }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Issue Lanes</p>
                    <h3 class="panel-title">Status-focused execution board</h3>
                </div>
                <Link href="/kanban" class="btn btn-outline-dark rounded-pill">Open Kanban</Link>
            </div>

            <div class="status-tabs mb-3">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    class="status-tab-btn"
                    :class="{ active: activeStatus === tab.key }"
                    @click="activeStatus = tab.key"
                >
                    <strong>{{ tab.label }}</strong>
                    <small>{{ tab.subtitle }}</small>
                    <span class="status-count">{{ statusIssues?.[tab.key]?.length ?? 0 }}</span>
                </button>
            </div>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in activeIssueRows" :key="issue.id">
                            <td data-label="Issue">
                                <div class="table-entity">
                                    <span class="table-avatar issue">{{ issue.title.slice(0, 1) }}</span>
                                    <div>
                                        <strong>{{ issue.title }}</strong>
                                        <small class="issue-date-meta">Created {{ formatIssueDate(issue.created_at) }}</small>
                                        <div v-if="issue.tags?.length" class="d-flex flex-wrap gap-1 mt-2">
                                            <span v-for="tag in issue.tags" :key="tag.id" class="badge rounded-pill text-bg-light border">{{ tag.name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Client">{{ issue.project?.client?.name || 'No client' }}</td>
                            <td data-label="Project">{{ issue.project?.name || 'No project' }}</td>
                            <td data-label="Status"><StatusPill :status="issue.status" /></td>
                            <td data-label="Action">
                                <div class="table-actions">
                                    <Link :href="`/issues/${issue.id}`" class="btn btn-sm btn-light rounded-pill">Open</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!activeIssueRows.length">
                            <td colspan="5">
                                <div class="table-empty">No issues in this lane yet. Try another status tab.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.status-tabs {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
}

.status-tab-btn {
    position: relative;
    text-align: left;
    border: 1px solid #d8e2df;
    background: #f3f8f7;
    color: #1f2d3d;
    border-radius: 1rem;
    padding: 0.9rem 1rem;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.status-tab-btn small {
    color: #607078;
}

.status-tab-btn .status-count {
    position: absolute;
    right: 0.9rem;
    top: 0.8rem;
    background: #dbe9e6;
    color: #12483f;
    border-radius: 999px;
    padding: 0.15rem 0.6rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-tab-btn.active {
    border-color: #1f7a6e;
    background: linear-gradient(135deg, #e6f6f1 0%, #f4fbf9 100%);
    box-shadow: 0 10px 24px rgba(25, 80, 71, 0.12);
    transform: translateY(-1px);
}

.analytics-card {
    border: 1px solid #d8e2df;
    border-radius: 1rem;
    padding: 1rem;
    background: linear-gradient(180deg, #f7fbfa 0%, #eef5f3 100%);
}

.analytics-title {
    margin-bottom: 0.75rem;
    font-size: 1rem;
    font-weight: 700;
    color: #2a3b4c;
}

.trend-chart {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(64px, 1fr));
    gap: 0.65rem;
    align-items: end;
}

.trend-column {
    text-align: center;
}

.trend-column small {
    display: block;
    margin-top: 0.4rem;
    color: #607078;
    font-size: 0.72rem;
}

.bars {
    height: 120px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 0.25rem;
}

.bar {
    width: 14px;
    border-radius: 999px 999px 0 0;
}

.bar.created {
    background: #95a4ff;
}

.bar.completed {
    background: #2a9d8f;
}
</style>
