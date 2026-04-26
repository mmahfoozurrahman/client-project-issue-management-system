<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SkeletonCard from '../../Components/SkeletonCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    columns: Object,
    todayTarget: Object,
    projects: Array,
    filters: Object,
    breadcrumbs: Array,
});

const loading = ref(false);
const searchFilters = reactive({
    project_id: props.filters?.project_id ?? '',
});

const applyFilters = () => {
    loading.value = true;
    router.get('/kanban', searchFilters, {
        preserveState: true,
        replace: true,
        onFinish: () => {
            loading.value = false;
        },
    });
};

const kanbanColumns = [
    { key: 'todo', title: 'Todo', subtitle: 'Plan next moves' },
    { key: 'inprogress', title: 'In Progress', subtitle: 'Deep work zone' },
    { key: 'done', title: 'Done', subtitle: 'Completed wins' },
];

const formatIssueDate = (value) => {
    if (!value) return 'Recently created';

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
};

const formatDoneDate = (value) => {
    if (!value) return 'Recently completed';

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
};

const totalIssues = computed(() => kanbanColumns.reduce((sum, column) => sum + (props.columns?.[column.key]?.length ?? 0), 0));
const doneCount = computed(() => props.columns?.done?.length ?? 0);
const inProgressCount = computed(() => props.columns?.inprogress?.length ?? 0);
const todoCount = computed(() => props.columns?.todo?.length ?? 0);
const completionRate = computed(() => {
    if (!totalIssues.value) return 0;
    return Math.round((doneCount.value / totalIssues.value) * 100);
});

const recentlyCompletedCount = computed(() => {
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

    return (props.columns?.done ?? []).filter((issue) => issue.done_at && new Date(issue.done_at) >= sevenDaysAgo).length;
});

const todayTargetRate = computed(() => {
    const target = Math.max(Number(props.todayTarget?.target ?? 1), 1);
    const completed = Number(props.todayTarget?.completed ?? 0);

    return Math.min(Math.round((completed / target) * 100), 100);
});
</script>

<template>
    <Head title="Kanban" />

    <AppLayout title="Kanban" :breadcrumbs="breadcrumbs">
        <section class="kanban-hero mb-4">
            <div class="kanban-hero-copy">
                <span class="pill-tag">Execution Board</span>
                <h2>Turn issue flow into shipping momentum.</h2>
                <p>Focus in progress, clear todo friction, and stack visible wins every day.</p>
                <div class="hero-progress">
                    <div class="hero-progress-top">
                        <span>Completion Rate</span>
                        <strong>{{ completionRate }}%</strong>
                    </div>
                    <div class="progress-track">
                        <span class="progress-fill" :style="{ width: `${completionRate}%` }" />
                    </div>
                </div>
            </div>

            <div class="kanban-metrics">
                <article class="metric-card">
                    <span>Total Issues</span>
                    <strong>{{ totalIssues }}</strong>
                </article>
                <article class="metric-card accent">
                    <span>In Progress</span>
                    <strong>{{ inProgressCount }}</strong>
                </article>
                <article class="metric-card success">
                    <span>Completed (7d)</span>
                    <strong>{{ recentlyCompletedCount }}</strong>
                </article>
                <article class="metric-card soft">
                    <span>Todo Queue</span>
                    <strong>{{ todoCount }}</strong>
                </article>
            </div>
        </section>

        <section class="panel-card mb-4">
            <div class="panel-header align-items-center">
                <div>
                    <p class="section-kicker">Today&apos;s Target</p>
                    <h3 class="panel-title">Complete {{ todayTarget?.target ?? 3 }} issues today</h3>
                </div>
                <Link href="/issues/daily-activity" class="btn btn-outline-dark rounded-pill">Open Daily Activity</Link>
            </div>
            <div class="target-widget">
                <div class="target-ring">
                    <svg viewBox="0 0 120 120" class="ring-svg">
                        <circle cx="60" cy="60" r="48" class="ring-track" />
                        <circle
                            cx="60"
                            cy="60"
                            r="48"
                            class="ring-progress"
                            :stroke-dasharray="`${todayTargetRate * 3.01} 301`"
                        />
                    </svg>
                    <div class="ring-center">
                        <strong>{{ todayTargetRate }}%</strong>
                        <small>Daily Goal</small>
                    </div>
                </div>
                <div class="target-meta">
                    <div class="target-stat">
                        <span>Completed Today</span>
                        <strong>{{ todayTarget?.completed ?? 0 }}</strong>
                    </div>
                    <div class="target-stat">
                        <span>Remaining</span>
                        <strong>{{ todayTarget?.remaining ?? 0 }}</strong>
                    </div>
                    <p class="target-copy">Keep pushing. Every issue moved to done compounds delivery momentum.</p>
                </div>
            </div>
        </section>

        <section class="panel-card">
            <div class="panel-header align-items-end">
                <div>
                    <p class="section-kicker">Kanban Board</p>
                    <h3 class="panel-title">Visualize issue flow by status</h3>
                </div>
                <select v-model="searchFilters.project_id" class="form-select kanban-filter" @change="applyFilters">
                    <option value="">All projects</option>
                    <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                </select>
            </div>

            <div class="kanban-board">
                <section v-for="column in kanbanColumns" :key="column.key" class="kanban-column" :class="`kanban-column-${column.key}`">
                    <div class="kanban-column-head">
                        <div>
                            <h4>{{ column.title }}</h4>
                            <small>{{ column.subtitle }}</small>
                        </div>
                        <span class="kanban-count-pill">{{ columns[column.key]?.length || 0 }}</span>
                    </div>

                    <div class="kanban-stack compact-kanban-stack">
                        <SkeletonCard v-if="loading" v-for="n in 3" :key="`${column.key}-${n}`" />
                        <Link
                            v-else
                            v-for="issue in columns[column.key]"
                            :key="issue.id"
                            :href="`/issues/${issue.id}`"
                            class="kanban-row-card"
                        >
                            <div>
                                <strong>{{ issue.title }}</strong>
                                <span>{{ issue.project?.client?.name || 'No client' }} / {{ issue.project?.name || 'No project' }}</span>
                                <small v-if="column.key === 'done'" class="kanban-date-meta">Completed {{ formatDoneDate(issue.done_at) }}</small>
                                <small v-else class="kanban-date-meta">Created {{ formatIssueDate(issue.created_at) }}</small>
                                <div v-if="issue.tags?.length" class="d-flex flex-wrap gap-1 mt-2">
                                    <span v-for="tag in issue.tags" :key="tag.id" class="badge rounded-pill text-bg-light border">{{ tag.name }}</span>
                                </div>
                            </div>
                            <small class="kanban-side-meta">{{ issue.sub_issues_count ?? 0 }} children</small>
                        </Link>
                        <div v-if="!loading && !(columns[column.key]?.length)" class="kanban-empty">No issues</div>
                    </div>
                </section>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.kanban-hero {
    background: linear-gradient(130deg, #f5fbf9 0%, #edf6f3 45%, #e4efeb 100%);
    border: 1px solid #d7e5e0;
    border-radius: 1.25rem;
    padding: 1.25rem;
    display: grid;
    gap: 1rem;
    grid-template-columns: 1.4fr 1fr;
}

.kanban-hero-copy h2 {
    margin-bottom: 0.35rem;
}

.kanban-hero-copy p {
    margin-bottom: 0.8rem;
    color: #5f6f78;
}

.hero-progress-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
    color: #415460;
    margin-bottom: 0.35rem;
}

.hero-progress-top strong {
    color: #0f5f53;
}

.progress-track {
    width: 100%;
    height: 10px;
    border-radius: 999px;
    background: #d7e5e0;
    overflow: hidden;
}

.progress-fill {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #1f9a87 0%, #54bfae 100%);
}

.kanban-metrics {
    display: grid;
    gap: 0.6rem;
    grid-template-columns: repeat(2, 1fr);
}

.metric-card {
    border-radius: 0.9rem;
    border: 1px solid #d4e3de;
    background: #f7fcfa;
    padding: 0.75rem;
}

.metric-card span {
    display: block;
    color: #5f6f78;
    font-size: 0.8rem;
}

.metric-card strong {
    font-size: 1.25rem;
    color: #223548;
}

.metric-card.accent {
    background: #edf4ff;
    border-color: #d7e3ff;
}

.metric-card.success {
    background: #ecfbf5;
    border-color: #cfeedd;
}

.metric-card.soft {
    background: #faf6ef;
    border-color: #efe0ca;
}

.kanban-column-head small {
    color: #6f7f87;
    font-size: 0.75rem;
}

.kanban-column-todo .kanban-column-head {
    border-bottom: 2px solid #f1be60;
    padding-bottom: 0.35rem;
}

.kanban-column-inprogress .kanban-column-head {
    border-bottom: 2px solid #6ca0ff;
    padding-bottom: 0.35rem;
}

.kanban-column-done .kanban-column-head {
    border-bottom: 2px solid #3cb8a3;
    padding-bottom: 0.35rem;
}

.kanban-row-card {
    border: 1px solid #deebe7;
    background: #fbfdfc;
    border-radius: 0.9rem;
    padding: 0.85rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.kanban-row-card:hover {
    transform: translateY(-1px);
    border-color: #bfdbd3;
    box-shadow: 0 8px 18px rgba(36, 79, 70, 0.11);
}

.target-widget {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 1rem;
    align-items: center;
}

.target-ring {
    position: relative;
    width: 120px;
    height: 120px;
}

.ring-svg {
    width: 120px;
    height: 120px;
    transform: rotate(-90deg);
}

.ring-track {
    fill: none;
    stroke: #d9e7e2;
    stroke-width: 10;
}

.ring-progress {
    fill: none;
    stroke: #2a9d8f;
    stroke-width: 10;
    stroke-linecap: round;
}

.ring-center {
    position: absolute;
    inset: 0;
    display: grid;
    place-content: center;
    text-align: center;
}

.ring-center strong {
    font-size: 1.3rem;
    color: #1b4f47;
    line-height: 1.1;
}

.ring-center small {
    color: #64727b;
}

.target-meta {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(2, minmax(150px, 1fr));
}

.target-stat {
    background: #f8fcfb;
    border: 1px solid #d9e7e2;
    border-radius: 0.85rem;
    padding: 0.7rem 0.85rem;
}

.target-stat span {
    display: block;
    color: #62717a;
    font-size: 0.8rem;
}

.target-stat strong {
    color: #213446;
    font-size: 1.2rem;
}

.target-copy {
    grid-column: 1 / -1;
    margin: 0;
    color: #5d6d76;
}

@media (max-width: 1200px) {
    .kanban-hero {
        grid-template-columns: 1fr;
    }

    .target-widget {
        grid-template-columns: 1fr;
    }

    .target-meta {
        grid-template-columns: 1fr;
    }
}
</style>
