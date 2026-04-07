<script setup>
import { Head } from '@inertiajs/vue3';
import IssueCard from '../Components/IssueCard.vue';
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

            <div class="issue-grid">
                <IssueCard v-for="issue in recentIssues" :key="issue.id" :issue="issue" />
            </div>
        </section>
    </AppLayout>
</template>
