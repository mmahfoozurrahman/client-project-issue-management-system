<script setup>
import { reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import IssueCard from '../../Components/IssueCard.vue';
import SkeletonCard from '../../Components/SkeletonCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    issues: Array,
    projects: Array,
    filters: Object,
    breadcrumbs: Array,
});

const loading = ref(false);
const modalOpen = ref(false);

const searchFilters = reactive({
    project_id: props.filters?.project_id ?? '',
    status: props.filters?.status ?? '',
});

const form = useForm({
    title: '',
    description: '',
    status: 'todo',
    project_id: props.filters?.project_id ?? '',
    parent_id: '',
    images: [],
});

const applyFilters = () => {
    loading.value = true;
    router.get('/issues', searchFilters, {
        preserveState: true,
        replace: true,
        onFinish: () => {
            loading.value = false;
        },
    });
};

const submit = () => {
    form.post('/issues', {
        forceFormData: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
        },
    });
};

const onFilesChange = (event) => {
    form.images = Array.from(event.target.files || []);
};
</script>

<template>
    <Head title="Issues" />

    <AppLayout title="Issues" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header align-items-end">
                <div>
                    <p class="section-kicker">Issue Library</p>
                    <h3 class="panel-title">Card-first issue browsing with project and status filters</h3>
                </div>
                <button class="btn btn-accent rounded-pill" @click="modalOpen = true">Add Issue</button>
            </div>

            <div class="filters-row">
                <select v-model="searchFilters.project_id" class="form-select" @change="applyFilters">
                    <option value="">All projects</option>
                    <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                </select>

                <select v-model="searchFilters.status" class="form-select" @change="applyFilters">
                    <option value="">All statuses</option>
                    <option value="todo">Todo</option>
                    <option value="inprogress">In Progress</option>
                    <option value="done">Done</option>
                </select>
            </div>

            <div class="issue-grid">
                <SkeletonCard v-if="loading" v-for="n in 6" :key="n" />
                <IssueCard v-else v-for="issue in issues" :key="issue.id" :issue="issue" />
            </div>
        </section>

        <Modal v-model="modalOpen" title="Create Issue">
            <form class="vstack gap-3" @submit.prevent="submit">
                <div>
                    <label class="form-label">Title</label>
                    <input v-model="form.title" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.title }" required>
                    <FormError :message="form.errors.title" />
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Project</label>
                        <select v-model="form.project_id" class="form-select" :class="{ 'is-invalid-soft': form.errors.project_id }" required>
                            <option value="">Select project</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                        </select>
                        <FormError :message="form.errors.project_id" />
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select v-model="form.status" class="form-select" :class="{ 'is-invalid-soft': form.errors.status }">
                            <option value="todo">Todo</option>
                            <option value="inprogress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <textarea v-model="form.description" rows="4" class="form-control" :class="{ 'is-invalid-soft': form.errors.description }" />
                    <FormError :message="form.errors.description" />
                </div>

                <div>
                    <label class="form-label">Images</label>
                    <input type="file" multiple accept=".jpg,.jpeg,.png" class="form-control" :class="{ 'is-invalid-soft': form.errors.images || form.errors['images.0'] }" @change="onFilesChange">
                    <FormError :message="form.errors.images || form.errors['images.0']" />
                </div>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                    Create Issue
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>
