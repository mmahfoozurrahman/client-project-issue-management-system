<script setup>
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SkeletonCard from '../../Components/SkeletonCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    columns: Object,
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
    { key: 'todo', title: 'Todo' },
    { key: 'inprogress', title: 'In Progress' },
    { key: 'done', title: 'Done' },
];
</script>

<template>
    <Head title="Kanban" />

    <AppLayout title="Kanban" :breadcrumbs="breadcrumbs">
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
                <section v-for="column in kanbanColumns" :key="column.key" class="kanban-column">
                    <div class="kanban-column-head">
                        <h4>{{ column.title }}</h4>
                        <span class="badge rounded-pill text-bg-light">{{ columns[column.key]?.length || 0 }}</span>
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
                            </div>
                            <small>{{ issue.sub_issues_count ?? 0 }} children</small>
                        </Link>
                        <div v-if="!loading && !(columns[column.key]?.length)" class="kanban-empty">No issues</div>
                    </div>
                </section>
            </div>
        </section>
    </AppLayout>
</template>
