<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import StatusPill from '../../Components/StatusPill.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    issues: Array,
    statusCounts: Object,
    summary: Object,
    projects: Array,
    filters: Object,
    breadcrumbs: Array,
});

const loading = ref(false);

const filterState = reactive({
    date: props.filters?.date ?? '',
    project_id: props.filters?.project_id ?? '',
    status: props.filters?.status ?? 'inprogress',
});

const statusCards = computed(() => [
    { key: 'inprogress', label: 'In Progress', hint: 'Active tasks', count: props.statusCounts?.inprogress ?? 0 },
    { key: 'todo', label: 'Todo', hint: 'Planned tasks', count: props.statusCounts?.todo ?? 0 },
    { key: 'done', label: 'Done', hint: 'Completed tasks', count: props.statusCounts?.done ?? 0 },
]);

const issueRows = computed(() => props.issues ?? []);

const applyFilters = () => {
    loading.value = true;
    router.get('/issues/daily-activity', filterState, {
        preserveState: true,
        replace: true,
        onFinish: () => {
            loading.value = false;
        },
    });
};

const chooseStatus = (status) => {
    filterState.status = status;
    applyFilters();
};

const formatIssueDate = (value) => {
    if (!value) return 'Recently created';

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
};
</script>

<template>
    <Head title="Daily Activity" />

    <AppLayout title="Daily Activity" :breadcrumbs="breadcrumbs">
        <section class="activity-hero mb-4">
            <div>
                <span class="pill-tag">Daily Drilldown</span>
                <h2>See exactly what you created in a day.</h2>
                <p>Filter by date and project, then click status lanes to inspect issue-level detail.</p>
            </div>
            <div class="activity-hero-stats">
                <article class="hero-stat">
                    <span>Created on date</span>
                    <strong>{{ summary?.created_total ?? 0 }}</strong>
                </article>
                <article class="hero-stat done">
                    <span>Completed on date</span>
                    <strong>{{ summary?.completed_total ?? 0 }}</strong>
                </article>
            </div>
        </section>

        <section class="panel-card mb-4">
            <div class="panel-header align-items-end">
                <div>
                    <p class="section-kicker">Filters</p>
                    <h3 class="panel-title">Date and project scope</h3>
                </div>
            </div>
            <div class="filters-grid">
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="filterState.date" type="date" class="form-control" @change="applyFilters">
                </div>
                <div>
                    <label class="form-label">Project</label>
                    <select v-model="filterState.project_id" class="form-select" @change="applyFilters">
                        <option value="">All projects</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="panel-card mb-4">
            <div class="panel-header align-items-end">
                <div>
                    <p class="section-kicker">Status Lanes</p>
                    <h3 class="panel-title">Click a lane to inspect issues</h3>
                </div>
            </div>
            <div class="status-card-grid">
                <button
                    v-for="card in statusCards"
                    :key="card.key"
                    type="button"
                    class="status-card"
                    :class="{ active: filterState.status === card.key }"
                    @click="chooseStatus(card.key)"
                >
                    <span>{{ card.label }}</span>
                    <strong>{{ card.count }}</strong>
                    <small>{{ card.hint }}</small>
                </button>
            </div>
        </section>

        <section class="panel-card">
            <div class="panel-header align-items-end">
                <div>
                    <p class="section-kicker">Issues</p>
                    <h3 class="panel-title">Created on {{ filterState.date }} · {{ filterState.status }}</h3>
                </div>
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
                        <tr v-if="loading">
                            <td colspan="5"><div class="table-empty">Loading activity...</div></td>
                        </tr>
                        <tr v-for="issue in issueRows" v-else :key="issue.id">
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
                        <tr v-if="!loading && !issueRows.length">
                            <td colspan="5">
                                <div class="table-empty">No issues created on this day for the selected lane.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.activity-hero {
    background: linear-gradient(125deg, #f7fbfa 0%, #eef7f4 100%);
    border: 1px solid #d8e5e1;
    border-radius: 1.25rem;
    padding: 1.2rem;
    display: grid;
    gap: 1rem;
    grid-template-columns: 1.6fr 1fr;
}

.activity-hero p {
    color: #5f6f78;
    margin-bottom: 0;
}

.activity-hero-stats {
    display: grid;
    gap: 0.75rem;
}

.hero-stat {
    border: 1px solid #d8e5e1;
    border-radius: 0.9rem;
    background: #f8fcfb;
    padding: 0.8rem;
}

.hero-stat.done {
    background: #eefbf6;
    border-color: #cfeadc;
}

.hero-stat span {
    display: block;
    color: #5f6f78;
    font-size: 0.8rem;
}

.hero-stat strong {
    color: #213446;
    font-size: 1.4rem;
}

.filters-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.status-card-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.status-card {
    text-align: left;
    border: 1px solid #d8e5e1;
    background: #f7fcfa;
    border-radius: 1rem;
    padding: 0.85rem 1rem;
    display: grid;
    gap: 0.2rem;
}

.status-card strong {
    font-size: 1.3rem;
    color: #203447;
}

.status-card small {
    color: #607078;
}

.status-card.active {
    border-color: #2a9d8f;
    background: linear-gradient(135deg, #e8f8f3 0%, #f5fbf9 100%);
    box-shadow: 0 8px 18px rgba(36, 80, 70, 0.11);
}

@media (max-width: 1000px) {
    .activity-hero {
        grid-template-columns: 1fr;
    }
}
</style>
