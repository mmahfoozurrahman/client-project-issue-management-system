<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import RichTextEditor from '../../Components/RichTextEditor.vue';
import StatusPill from '../../Components/StatusPill.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    project: Object,
    issues: Object,
    breadcrumbs: Array,
});

const modalOpen = ref(false);
const issueRows = computed(() => props.issues?.data ?? []);
const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const form = useForm({
    title: '',
    description: '',
    status: 'todo',
    project_id: props.project.id,
    parent_id: '',
    images: [],
});

const submit = () => {
    form.post('/issues', {
        forceFormData: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset('title', 'description', 'status', 'parent_id', 'images');
            form.project_id = props.project.id;
        },
    });
};

const onFilesChange = (event) => {
    form.images = Array.from(event.target.files || []);
};
</script>

<template>
    <Head :title="project.name" />

    <AppLayout :title="project.name" :breadcrumbs="breadcrumbs">
        <section class="hero-panel mb-4">
            <div>
                <span class="pill-tag">Project Space</span>
                <h2>{{ project.name }}</h2>
                <div v-if="project.description" class="hero-copy rich-display" v-html="project.description" />
                <p v-else class="hero-copy">No description added yet.</p>
            </div>
            <div class="project-meta-block">
                <span class="badge text-bg-light rounded-pill px-3 py-2">{{ project.client?.name }}</span>
                <button class="btn btn-accent rounded-pill" @click="modalOpen = true">Add Issue</button>
            </div>
        </section>

        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Issue Snapshot</p>
                    <h3 class="panel-title">Top-level issues in this project</h3>
                </div>
                <Link href="/kanban" class="btn btn-outline-dark rounded-pill">Open Kanban</Link>
            </div>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Status</th>
                            <th>Sub-issues</th>
                            <th>Images</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in issueRows" :key="issue.id">
                            <td data-label="Issue">
                                <div class="table-entity">
                                    <span class="table-avatar issue">{{ issue.title.slice(0, 1) }}</span>
                                    <div>
                                        <strong>{{ issue.title }}</strong>
                                        <small>{{ plainText(issue.description) || 'No description added yet.' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Status"><StatusPill :status="issue.status" /></td>
                            <td data-label="Sub-issues">{{ issue.sub_issues_count ?? 0 }}</td>
                            <td data-label="Images">{{ issue.images_count ?? 0 }}</td>
                            <td data-label="Action">
                                <div class="table-actions">
                                    <Link :href="`/issues/${issue.id}`" class="btn btn-sm btn-light rounded-pill">Open</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!issueRows.length">
                            <td colspan="5">
                                <div class="table-empty">No top-level issues yet. Add the first issue for this project.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="issues.links" :meta="issues" />
        </section>

        <Modal v-model="modalOpen" title="Create Issue">
            <form class="vstack gap-3" @submit.prevent="submit">
                <div>
                    <label class="form-label">Title</label>
                    <input v-model="form.title" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.title }" required>
                    <FormError :message="form.errors.title" />
                </div>

                <div>
                    <label class="form-label">Status</label>
                    <select v-model="form.status" class="form-select" :class="{ 'is-invalid-soft': form.errors.status }">
                        <option value="todo">Todo</option>
                        <option value="inprogress">In Progress</option>
                        <option value="done">Done</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <RichTextEditor v-model="form.description" :error="form.errors.description" placeholder="Add rich issue details, reproduction notes, or acceptance criteria..." />
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
