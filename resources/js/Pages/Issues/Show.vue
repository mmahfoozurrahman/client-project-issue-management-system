<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import RichTextEditor from '../../Components/RichTextEditor.vue';
import StatusPill from '../../Components/StatusPill.vue';
import IssueTree from '../../Components/IssueTree.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    issue: Object,
    projects: Array,
    projectIssues: Array,
    breadcrumbs: Array,
});

const nestedIssues = computed(() => props.issue.sub_issues ?? props.issue.subIssues ?? []);
const parentIssue = computed(() => props.issue.parent_issue ?? props.issue.parentIssue ?? null);

const updateForm = useForm({
    title: props.issue.title,
    description: props.issue.description ?? '',
    status: props.issue.status,
    project_id: props.issue.project_id,
    parent_id: props.issue.parent_id ?? '',
    images: [],
});
const deleteForm = useForm({});
const childModalOpen = ref(false);
const childForm = useForm({
    title: '',
    description: '',
    status: 'todo',
    project_id: props.issue.project_id,
    parent_id: props.issue.id,
    return_to_issue_id: props.issue.id,
    images: [],
});
const childIssueParent = computed(() => props.projectIssues.find((entry) => entry.id === childForm.parent_id) ?? props.issue);

const submit = () => {
    updateForm.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(`/issues/${props.issue.id}`, {
        forceFormData: true,
    });
};

const destroyIssue = () => {
    Swal.fire({
        title: `Delete ${props.issue.title}?`,
        text: 'This will remove the issue and its nested structure.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            deleteForm.delete(`/issues/${props.issue.id}`);
        }
    });
};

const onFilesChange = (event) => {
    updateForm.images = Array.from(event.target.files || []);
};

const onChildFilesChange = (event) => {
    childForm.images = Array.from(event.target.files || []);
};

const openChildModal = (parentIssue = props.issue) => {
    childModalOpen.value = true;
    childForm.reset();
    childForm.clearErrors();
    childForm.title = '';
    childForm.description = '';
    childForm.status = 'todo';
    childForm.project_id = props.issue.project_id;
    childForm.parent_id = parentIssue.id;
    childForm.return_to_issue_id = props.issue.id;
};

const resetChildForm = () => {
    childModalOpen.value = false;
    childForm.reset();
    childForm.clearErrors();
    childForm.project_id = props.issue.project_id;
    childForm.parent_id = null;
    childForm.return_to_issue_id = props.issue.id;
};

const submitChild = () => {
    childForm.post('/issues', {
        forceFormData: true,
        onSuccess: () => {
            resetChildForm();
        },
    });
};
</script>

<template>
    <Head :title="issue.title" />

    <AppLayout :title="issue.title" :breadcrumbs="breadcrumbs">
        <section class="hero-panel mb-4">
            <div>
                <span class="pill-tag">Issue Detail</span>
                <h2>{{ issue.title }}</h2>
                <div v-if="issue.description" class="hero-copy rich-display" v-html="issue.description" />
                <p v-else class="hero-copy">No description added yet.</p>
            </div>
            <div class="project-meta-block">
                <button class="btn btn-outline-dark rounded-pill" @click="openChildModal(issue)">Add Sub-Issue</button>
                <StatusPill :status="issue.status" />
                <button class="btn btn-outline-danger rounded-pill" @click="destroyIssue">Delete</button>
            </div>
        </section>

        <section class="issue-context-strip">
            <div class="context-stat">
                <span>Client</span>
                <strong>{{ issue.project?.client?.name || 'No client' }}</strong>
            </div>
            <div class="context-stat">
                <span>Project</span>
                <strong>{{ issue.project?.name || 'No project' }}</strong>
            </div>
            <div class="context-stat">
                <span>Parent Issue</span>
                <strong v-if="parentIssue">
                    <Link :href="`/issues/${parentIssue.id}`">{{ parentIssue.title }}</Link>
                </strong>
                <strong v-else>Top-level issue</strong>
            </div>
            <div class="context-stat accent">
                <span>Nested Items</span>
                <strong>{{ nestedIssues.length }}</strong>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-xl-5">
                <section class="panel-card h-100">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Edit Issue</p>
                            <h3 class="panel-title">Update details, parent links, and images</h3>
                        </div>
                    </div>

                    <form class="vstack gap-3" @submit.prevent="submit">
                        <div>
                            <label class="form-label">Title</label>
                            <input v-model="updateForm.title" type="text" class="form-control" :class="{ 'is-invalid-soft': updateForm.errors.title }" required>
                            <FormError :message="updateForm.errors.title" />
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Project</label>
                                <select v-model="updateForm.project_id" class="form-select" :class="{ 'is-invalid-soft': updateForm.errors.project_id }">
                                    <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                                </select>
                                <FormError :message="updateForm.errors.project_id" />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select v-model="updateForm.status" class="form-select" :class="{ 'is-invalid-soft': updateForm.errors.status }">
                                    <option value="todo">Todo</option>
                                    <option value="inprogress">In Progress</option>
                                    <option value="done">Done</option>
                                </select>
                                <FormError :message="updateForm.errors.status" />
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Parent Issue</label>
                            <select v-model="updateForm.parent_id" class="form-select" :class="{ 'is-invalid-soft': updateForm.errors.parent_id }">
                                <option value="">No parent</option>
                                <option v-for="parent in projectIssues" :key="parent.id" :value="parent.id">{{ parent.title }}</option>
                            </select>
                            <FormError :message="updateForm.errors.parent_id" />
                        </div>

                        <div>
                            <label class="form-label">Description</label>
                            <RichTextEditor v-model="updateForm.description" :error="updateForm.errors.description" placeholder="Capture formatted details, notes, links, and acceptance context..." />
                            <FormError :message="updateForm.errors.description" />
                        </div>

                        <div>
                            <label class="form-label">Add Images</label>
                            <input type="file" multiple accept=".jpg,.jpeg,.png" class="form-control" :class="{ 'is-invalid-soft': updateForm.errors.images || updateForm.errors['images.0'] }" @change="onFilesChange">
                            <FormError :message="updateForm.errors.images || updateForm.errors['images.0']" />
                        </div>

                        <button class="btn btn-accent rounded-pill align-self-start" :disabled="updateForm.processing">
                            <span v-if="updateForm.processing" class="spinner-border spinner-border-sm me-2" />
                            Save Changes
                        </button>
                    </form>
                </section>
            </div>

            <div class="col-xl-7">
                <section class="panel-card mb-4">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Gallery</p>
                            <h3 class="panel-title">Attached screenshots and references</h3>
                        </div>
                    </div>

                    <div class="gallery-grid">
                        <img v-for="image in issue.images" :key="image.id" :src="image.url" :alt="image.original_name || issue.title" class="gallery-thumb">
                    </div>
                </section>

                <section class="panel-card">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Nested Issues</p>
                            <h3 class="panel-title">Recursive issue tree</h3>
                        </div>
                        <button class="btn btn-light rounded-pill" @click="openChildModal(issue)">+ Add Child</button>
                    </div>

                    <div v-if="!nestedIssues.length" class="empty-state-card">
                        <strong>No sub-issues yet</strong>
                        <p>Create a child issue directly from this parent to build the recursive tree.</p>
                        <button class="btn btn-accent rounded-pill" @click="openChildModal(issue)">Create First Child</button>
                    </div>

                    <IssueTree v-else :issues="nestedIssues" @create-child="openChildModal" />
                </section>
            </div>
        </div>

        <Modal v-model="childModalOpen" title="Create Sub-Issue" size="modal-lg">
            <form class="vstack gap-3" @submit.prevent="submitChild">
                <div class="context-chip">
                    <span>Parent issue</span>
                    <strong>{{ childIssueParent?.title || issue.title }}</strong>
                </div>

                <div>
                    <label class="form-label">Title</label>
                    <input v-model="childForm.title" type="text" class="form-control" :class="{ 'is-invalid-soft': childForm.errors.title }" required>
                    <FormError :message="childForm.errors.title" />
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select v-model="childForm.status" class="form-select" :class="{ 'is-invalid-soft': childForm.errors.status }">
                            <option value="todo">Todo</option>
                            <option value="inprogress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                        <FormError :message="childForm.errors.status" />
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Parent</label>
                        <select v-model="childForm.parent_id" class="form-select" :class="{ 'is-invalid-soft': childForm.errors.parent_id }">
                            <option :value="issue.id">{{ issue.title }}</option>
                            <option v-for="parent in projectIssues" :key="parent.id" :value="parent.id">{{ parent.title }}</option>
                        </select>
                        <FormError :message="childForm.errors.parent_id" />
                    </div>
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <RichTextEditor v-model="childForm.description" :error="childForm.errors.description" placeholder="Describe the child issue with formatted notes..." />
                    <FormError :message="childForm.errors.description" />
                </div>

                <div>
                    <label class="form-label">Images</label>
                    <input type="file" multiple accept=".jpg,.jpeg,.png" class="form-control" :class="{ 'is-invalid-soft': childForm.errors.images || childForm.errors['images.0'] }" @change="onChildFilesChange">
                    <FormError :message="childForm.errors.images || childForm.errors['images.0']" />
                </div>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="childForm.processing">
                    <span v-if="childForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Create Sub-Issue
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>
