<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import StatusPill from '../Components/StatusPill.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { formatIssueDate } from '../utils/date';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip
);

const props = defineProps({
    counts: Object,
    statusIssues: Object,
    statusSummary: Object,
    analytics: Object,
    pendingNudges: Object,
    breadcrumbs: Array,
});

const activeStatus = ref('inprogress');
const statusTabs = [
    { key: 'inprogress', label: 'In Progress', subtitle: 'Active focus lane' },
    { key: 'todo', label: 'Todo', subtitle: 'Upcoming commitments' },
    { key: 'done', label: 'Done', subtitle: 'Completed momentum' },
];

const activeIssueRows = computed(() => props.statusIssues?.[activeStatus.value] ?? []);
const weeklyCanvas = ref(null);
const monthlyCanvas = ref(null);
const statusCanvas = ref(null);
const chartInstances = {
    weekly: null,
    monthly: null,
    status: null,
};

const staleAgeLabel = (issue) => {
    const updated = issue.updated_at ? new Date(issue.updated_at) : null;
    if (!updated) return '-';
    const days = Math.floor((Date.now() - updated.getTime()) / (1000 * 60 * 60 * 24));
    return days > 0 ? `${days}d idle` : 'today';
};

const idleDays = (issue) => {
    const updated = issue.updated_at ? new Date(issue.updated_at) : null;
    if (!updated) return 0;
    return Math.max(Math.floor((Date.now() - updated.getTime()) / (1000 * 60 * 60 * 24)), 0);
};

const idleSeverity = (issue) => {
    const days = idleDays(issue);
    const stale = Number(props.pendingNudges?.stale_days ?? 3);
    const critical = Number(props.pendingNudges?.critical_days ?? 7);

    if (days >= critical) return 'critical';
    if (days >= stale) return 'watch';
    return 'normal';
};

const idleSeverityClass = (issue) => {
    const severity = idleSeverity(issue);
    if (severity === 'critical') return 'idle-critical';
    if (severity === 'watch') return 'idle-watch';
    return '';
};

const weeklyChart = computed(() => props.analytics?.weekly ?? []);
const monthlyChart = computed(() => props.analytics?.monthly ?? []);
const statusSummary = computed(() => props.statusSummary ?? {});

const weeklyTotals = computed(() => ({
    created: weeklyChart.value.reduce((sum, entry) => sum + Number(entry.created ?? 0), 0),
    completed: weeklyChart.value.reduce((sum, entry) => sum + Number(entry.completed ?? 0), 0),
}));

const monthlyTotals = computed(() => ({
    created: monthlyChart.value.reduce((sum, entry) => sum + Number(entry.created ?? 0), 0),
    completed: monthlyChart.value.reduce((sum, entry) => sum + Number(entry.completed ?? 0), 0),
}));

const throughputRate = computed(() => {
    if (!monthlyTotals.value.created) return 0;

    return Math.round((monthlyTotals.value.completed / monthlyTotals.value.created) * 100);
});

const momentumDelta = computed(() => {
    const latest = weeklyChart.value.at(-1);
    const previous = weeklyChart.value.at(-2);

    if (!latest || !previous) return 0;

    return (latest.completed ?? 0) - (previous.completed ?? 0);
});

const insightCards = computed(() => [
    {
        label: '7-week created',
        value: weeklyTotals.value.created,
        tone: 'created',
    },
    {
        label: '7-week completed',
        value: weeklyTotals.value.completed,
        tone: 'completed',
    },
    {
        label: 'Delivery rate',
        value: `${throughputRate.value}%`,
        tone: 'neutral',
    },
    {
        label: 'Weekly trend',
        value: `${momentumDelta.value >= 0 ? '+' : ''}${momentumDelta.value}`,
        tone: momentumDelta.value >= 0 ? 'completed' : 'alert',
    },
]);

const destroyChart = (key) => {
    if (chartInstances[key]) {
        chartInstances[key].destroy();
        chartInstances[key] = null;
    }
};

const buildWeeklyChart = () => {
    if (!weeklyCanvas.value) return;

    destroyChart('weekly');
    chartInstances.weekly = new Chart(weeklyCanvas.value, {
        type: 'line',
        data: {
            labels: weeklyChart.value.map((entry) => entry.label),
            datasets: [
                {
                    label: 'Created',
                    data: weeklyChart.value.map((entry) => entry.created ?? 0),
                    borderColor: '#7c8cf8',
                    backgroundColor: 'rgba(124, 140, 248, 0.18)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#7c8cf8',
                },
                {
                    label: 'Completed',
                    data: weeklyChart.value.map((entry) => entry.completed ?? 0),
                    borderColor: '#1f9d8b',
                    backgroundColor: 'rgba(31, 157, 139, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#1f9d8b',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#12342e',
                    padding: 12,
                    displayColors: true,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#607078' },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: '#607078',
                    },
                    grid: {
                        color: 'rgba(96, 112, 120, 0.12)',
                    },
                },
            },
        },
    });
};

const buildMonthlyChart = () => {
    if (!monthlyCanvas.value) return;

    destroyChart('monthly');
    chartInstances.monthly = new Chart(monthlyCanvas.value, {
        type: 'bar',
        data: {
            labels: monthlyChart.value.map((entry) => entry.label),
            datasets: [
                {
                    label: 'Created',
                    data: monthlyChart.value.map((entry) => entry.created ?? 0),
                    backgroundColor: '#9aa7ff',
                    borderRadius: 10,
                    borderSkipped: false,
                    maxBarThickness: 22,
                },
                {
                    label: 'Completed',
                    data: monthlyChart.value.map((entry) => entry.completed ?? 0),
                    backgroundColor: '#33b39f',
                    borderRadius: 10,
                    borderSkipped: false,
                    maxBarThickness: 22,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#12342e',
                    padding: 12,
                },
            },
            scales: {
                x: {
                    stacked: false,
                    grid: { display: false },
                    ticks: { color: '#607078' },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        color: '#607078',
                    },
                    grid: {
                        color: 'rgba(96, 112, 120, 0.12)',
                    },
                },
            },
        },
    });
};

const buildStatusChart = () => {
    if (!statusCanvas.value) return;

    destroyChart('status');
    chartInstances.status = new Chart(statusCanvas.value, {
        type: 'doughnut',
        data: {
            labels: ['In Progress', 'Todo', 'Done'],
            datasets: [
                {
                    data: [
                        statusSummary.value.inprogress ?? 0,
                        statusSummary.value.todo ?? 0,
                        statusSummary.value.done ?? 0,
                    ],
                    backgroundColor: ['#f2b84b', '#7c8cf8', '#1f9d8b'],
                    hoverOffset: 8,
                    borderWidth: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        color: '#35505a',
                    },
                },
                tooltip: {
                    backgroundColor: '#12342e',
                    padding: 12,
                },
            },
        },
    });
};

const renderCharts = async () => {
    await nextTick();
    buildWeeklyChart();
    buildMonthlyChart();
    buildStatusChart();
};

onMounted(() => {
    renderCharts();
});

watch([weeklyChart, monthlyChart, statusSummary], () => {
    renderCharts();
}, { deep: true });

onBeforeUnmount(() => {
    destroyChart('weekly');
    destroyChart('monthly');
    destroyChart('status');
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

            <div class="insight-strip">
                <article v-for="item in insightCards" :key="item.label" class="insight-card" :class="`tone-${item.tone}`">
                    <span>{{ item.label }}</span>
                    <strong>{{ item.value }}</strong>
                </article>
            </div>

            <div class="row g-4">
                <div class="col-xl-6">
                    <div class="analytics-card">
                        <div class="analytics-card-header">
                            <div>
                                <h4 class="analytics-title">Weekly Activity</h4>
                                <p class="analytics-subtitle">Created and resolved issue flow across the last seven weeks.</p>
                            </div>
                            <span class="chart-chip">Live trend</span>
                        </div>
                        <div class="chart-shell">
                            <canvas ref="weeklyCanvas" />
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="analytics-card">
                        <div class="analytics-card-header">
                            <div>
                                <h4 class="analytics-title">Monthly Activity</h4>
                                <p class="analytics-subtitle">A six-month bar view to compare intake and delivery cadence.</p>
                            </div>
                            <span class="chart-chip">6 months</span>
                        </div>
                        <div class="chart-shell">
                            <canvas ref="monthlyCanvas" />
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="analytics-card analytics-card-wide">
                        <div class="analytics-card-header">
                            <div>
                                <h4 class="analytics-title">Issue Status Mix</h4>
                                <p class="analytics-subtitle">Current distribution of all accessible issues across the delivery pipeline.</p>
                            </div>
                            <span class="chart-chip">Current backlog</span>
                        </div>
                        <div class="status-chart-layout">
                            <div class="status-chart-shell">
                                <canvas ref="statusCanvas" />
                            </div>
                            <div class="status-summary-grid">
                                <article class="status-summary-item tone-todo">
                                    <span>Todo</span>
                                    <strong>{{ statusSummary?.todo ?? 0 }}</strong>
                                </article>
                                <article class="status-summary-item tone-created">
                                    <span>In Progress</span>
                                    <strong>{{ statusSummary?.inprogress ?? 0 }}</strong>
                                </article>
                                <article class="status-summary-item tone-completed">
                                    <span>Done</span>
                                    <strong>{{ statusSummary?.done ?? 0 }}</strong>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel-card mb-4">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Gentle Nudges</p>
                    <h3 class="panel-title">Pending issues that may need attention</h3>
                </div>
                <Link href="/issues?at_risk=1" class="btn btn-outline-dark rounded-pill">Open At Risk</Link>
            </div>

            <div class="status-tabs mb-3">
                <div class="status-tab-btn nudge-watch">
                    <strong>Watch</strong>
                    <small>Early drift</small>
                    <span class="status-count">{{ pendingNudges?.watch ?? 0 }}</span>
                </div>
                <div class="status-tab-btn nudge-needs">
                    <strong>Needs Attention</strong>
                    <small>Getting stale</small>
                    <span class="status-count">{{ pendingNudges?.needs_attention ?? 0 }}</span>
                </div>
                <div class="status-tab-btn nudge-critical">
                    <strong>Critical</strong>
                    <small>Long idle</small>
                    <span class="status-count">{{ pendingNudges?.critical ?? 0 }}</span>
                </div>
            </div>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Status</th>
                            <th>Idle</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in pendingNudges?.focus_issues ?? []" :key="`nudge-${issue.id}`">
                            <td>{{ issue.title }}</td>
                            <td><StatusPill :status="issue.status" /></td>
                            <td :class="idleSeverityClass(issue)">{{ staleAgeLabel(issue) }}</td>
                            <td class="text-end"><Link :href="`/issues/${issue.id}`" class="btn btn-sm btn-light rounded-pill">Open</Link></td>
                        </tr>
                        <tr v-if="!(pendingNudges?.focus_issues?.length)">
                            <td colspan="4">
                                <div class="table-empty">No stale issues right now. Great momentum.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
                                        <small v-if="issue.status === 'done'" class="issue-date-meta" :style="{ color: '#1f7a6e', fontWeight: '600' }">
                                            Completed {{ formatIssueDate(issue.done_at) }}
                                        </small>
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

.status-tab-btn.nudge-watch {
    background: #fff8eb;
    border-color: #f2d39b;
}

.status-tab-btn.nudge-watch .status-count {
    background: #ffe9c0;
    color: #8c5a17;
}

.status-tab-btn.nudge-needs {
    background: #fff2eb;
    border-color: #efc2a8;
}

.status-tab-btn.nudge-needs .status-count {
    background: #ffd8c4;
    color: #9c3c20;
}

.status-tab-btn.nudge-critical {
    background: #fff0f0;
    border-color: #efb4b4;
}

.status-tab-btn.nudge-critical .status-count {
    background: #ffd0d0;
    color: #9f1f1f;
}

.idle-watch {
    color: #a2611d;
    font-weight: 600;
}

.idle-critical {
    color: #b42323;
    font-weight: 700;
}

.analytics-card {
    border: 1px solid #d8e2df;
    border-radius: 1rem;
    padding: 1rem;
    background: linear-gradient(180deg, #f7fbfa 0%, #eef5f3 100%);
}

.analytics-card-wide {
    padding-bottom: 1.25rem;
}

.analytics-card-header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.analytics-title {
    margin-bottom: 0.25rem;
    font-size: 1rem;
    font-weight: 700;
    color: #2a3b4c;
}

.analytics-subtitle {
    margin: 0;
    color: #607078;
    font-size: 0.87rem;
}

.chart-chip {
    border: 1px solid #d7e3df;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    color: #35505a;
    font-size: 0.78rem;
    font-weight: 600;
}

.chart-shell {
    position: relative;
    height: 290px;
}

.insight-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.85rem;
    margin-bottom: 1rem;
}

.insight-card,
.status-summary-item {
    border-radius: 1rem;
    padding: 0.95rem 1rem;
    border: 1px solid #d8e2df;
    background: rgba(255, 255, 255, 0.72);
}

.insight-card span,
.status-summary-item span {
    display: block;
    color: #607078;
    font-size: 0.8rem;
    margin-bottom: 0.3rem;
}

.insight-card strong,
.status-summary-item strong {
    font-size: 1.45rem;
    color: #203040;
}

.tone-created {
    box-shadow: inset 0 0 0 1px rgba(124, 140, 248, 0.12);
}

.tone-created strong {
    color: #6677ef;
}

.tone-completed {
    box-shadow: inset 0 0 0 1px rgba(31, 157, 139, 0.12);
}

.tone-completed strong {
    color: #1a8577;
}

.tone-neutral strong {
    color: #35505a;
}

.tone-alert strong,
.tone-todo strong {
    color: #a36a13;
}

.status-chart-layout {
    display: grid;
    grid-template-columns: minmax(260px, 360px) minmax(0, 1fr);
    gap: 1.5rem;
    align-items: center;
}

.status-chart-shell {
    position: relative;
    height: 260px;
}

.status-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.85rem;
}

@media (max-width: 991.98px) {
    .status-chart-layout {
        grid-template-columns: 1fr;
    }

    .status-chart-shell {
        height: 240px;
    }
}

@media (max-width: 767.98px) {
    .analytics-card-header {
        flex-direction: column;
    }

    .chart-shell {
        height: 250px;
    }
}
</style>
