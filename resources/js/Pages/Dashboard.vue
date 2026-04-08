<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({
    counts: Object,
    recentIssues: Array,
    breadcrumbs: Array,
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard" :breadcrumbs="breadcrumbs">
        <section class="hero-panel mb-4">
            <div>
                <span class="pill-tag">Command Center</span>
                <h2>Track each tenant-owned workflow from client intake to final issue resolution.</h2>
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
                        <tr v-for="issue in recentIssues" :key="issue.id">
                            <td>
                                <div class="table-entity">
                                    <span class="table-avatar issue">{{ issue.title.slice(0, 1) }}</span>
                                    <div>
                                        <strong>{{ issue.title }}</strong>
                                        <small>{{ issue.description || 'No description added yet.' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ issue.project?.client?.name || 'No client' }}</td>
                            <td>{{ issue.project?.name || 'No project' }}</td>
                            <td><span class="table-pill status-pill">{{ issue.status }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <Link :href="`/issues/${issue.id}`" class="btn btn-sm btn-light rounded-pill">Open</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!recentIssues.length">
                            <td colspan="5">
                                <div class="table-empty">No recent issues yet. Create an issue to see activity here.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
