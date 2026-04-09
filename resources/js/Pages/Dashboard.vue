<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Pagination from '../Components/Pagination.vue';
import StatusPill from '../Components/StatusPill.vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    counts: Object,
    recentIssues: Object,
    breadcrumbs: Array,
});

const recentIssueRows = computed(() => props.recentIssues?.data ?? props.recentIssues ?? []);
const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
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

        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Recent Issues</p>
                    <h3 class="panel-title">Latest activity across your projects</h3>
                </div>
                <Link href="/kanban" class="btn btn-outline-dark rounded-pill">Open Kanban</Link>
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
                        <tr v-for="issue in recentIssueRows" :key="issue.id">
                            <td data-label="Issue">
                                <div class="table-entity">
                                    <span class="table-avatar issue">{{ issue.title.slice(0, 1) }}</span>
                                    <div>
                                        <strong>{{ issue.title }}</strong>
                                        <small>{{ plainText(issue.description) || 'No description added yet.' }}</small>
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
                        <tr v-if="!recentIssueRows.length">
                            <td colspan="5">
                                <div class="table-empty">No recent issues yet. Create an issue to see activity here.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="recentIssues.links" :meta="recentIssues" />
        </section>
    </AppLayout>
</template>
