<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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
    parentIssueOptions: Array,
    breadcrumbs: Array,
});

const nestedIssues = computed(() => props.issue.sub_issues ?? props.issue.subIssues ?? []);
const parentIssue = computed(() => props.issue.parent_issue ?? props.issue.parentIssue ?? null);
const issueImages = ref([...(props.issue.images ?? [])]);
const issueFiles = ref([...(props.issue.files ?? [])]);
const issueLinks = ref([...(props.issue.links ?? [])]);

const updateForm = useForm({
    title: props.issue.title,
    description: props.issue.description ?? '',
    status: props.issue.status,
    project_id: props.issue.project_id,
    parent_id: props.issue.parent_id ?? '',
    images: [],
    files: [],
    links: (props.issue.links ?? []).map((link) => ({
        label: link.label ?? '',
        url: link.url ?? '',
    })),
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
    files: [],
    links: [],
});
const childIssueParent = computed(() => props.projectIssues.find((entry) => entry.id === childForm.parent_id) ?? props.issue);
const imageModalOpen = ref(false);
const activeImageIndex = ref(0);
const formatFileSize = (value) => {
    if (!value && value !== 0) {
        return 'Unknown size';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
};

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

const onImageChange = (event) => {
    updateForm.images = Array.from(event.target.files || []);
};

const onFileChange = (event) => {
    updateForm.files = Array.from(event.target.files || []);
};

const onChildImageChange = (event) => {
    childForm.images = Array.from(event.target.files || []);
};

const onChildFileChange = (event) => {
    childForm.files = Array.from(event.target.files || []);
};

const addUpdateLink = () => {
    updateForm.links.push({ label: '', url: '' });
};

const removeUpdateLink = (index) => {
    updateForm.links.splice(index, 1);
};

const addChildLink = () => {
    childForm.links.push({ label: '', url: '' });
};

const removeChildLink = (index) => {
    childForm.links.splice(index, 1);
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

const openImageModal = (index) => {
    activeImageIndex.value = index;
    imageModalOpen.value = true;
};

const showNextImage = () => {
    if (!issueImages.value.length) return;
    activeImageIndex.value = (activeImageIndex.value + 1) % issueImages.value.length;
};

const showPrevImage = () => {
    if (!issueImages.value.length) return;
    activeImageIndex.value = (activeImageIndex.value - 1 + issueImages.value.length) % issueImages.value.length;
};

const deleteImage = (image) => {
    Swal.fire({
        title: 'Delete image?',
        text: 'This will remove the image from this issue.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;

        router.delete(`/issues/images/${image.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                issueImages.value = issueImages.value.filter((entry) => entry.id !== image.id);
                if (activeImageIndex.value >= issueImages.value.length) {
                    activeImageIndex.value = Math.max(issueImages.value.length - 1, 0);
                }
            },
        });
    });
};

const deleteFile = (file) => {
    Swal.fire({
        title: 'Delete file?',
        text: 'This will remove the file from this issue.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;

        router.delete(`/issues/files/${file.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                issueFiles.value = issueFiles.value.filter((entry) => entry.id !== file.id);
            },
        });
    });
};

const deleteLink = (link) => {
    Swal.fire({
        title: 'Delete link?',
        text: 'This will remove the link from this issue.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;

        router.delete(`/issues/links/${link.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                issueLinks.value = issueLinks.value.filter((entry) => entry.id !== link.id);
            },
        });
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
                <!-- <div v-if="issue.description" class="hero-copy rich-display" v-html="issue.description" />
                <p v-else class="hero-copy">No description added yet.</p> -->
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
                            <h3 class="panel-title">Update details, parent links, and attachments</h3>
                        </div>
                    </div>

                    <form class="vstack gap-3" @submit.prevent="submit">
                        <div>
                            <label class="form-label">Title</label>
                            <input v-model="updateForm.title" type="text" class="form-control" :class="{ 'is-invalid-soft': updateForm.errors.title }">
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
                                    <option v-for="parent in parentIssueOptions" :key="parent.id" :value="parent.id">{{ parent.label }}</option>
                                </select>
                                <small v-if="!parentIssueOptions.length" class="text-muted d-block mt-2">No valid parent issues available. This issue already sits at the top of its branch.</small>
                                <FormError :message="updateForm.errors.parent_id" />
                            </div>

                        <div>
                            <label class="form-label">Description</label>
                            <RichTextEditor v-model="updateForm.description" :error="updateForm.errors.description" placeholder="Capture formatted details, notes, links, and acceptance context..." />
                            <FormError :message="updateForm.errors.description" />
                        </div>

                        <div>
                            <label class="form-label">Add Images</label>
                            <input type="file" multiple accept=".jpg,.jpeg,.png" class="form-control" :class="{ 'is-invalid-soft': updateForm.errors.images || updateForm.errors['images.0'] }" @change="onImageChange">
                            <FormError :message="updateForm.errors.images || updateForm.errors['images.0']" />
                        </div>

                        <div>
                            <label class="form-label">Add Files</label>
                            <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.rtf,.ppt,.pptx,.zip,.rar" class="form-control" :class="{ 'is-invalid-soft': updateForm.errors.files || updateForm.errors['files.0'] }" @change="onFileChange">
                            <FormError :message="updateForm.errors.files || updateForm.errors['files.0']" />
                        </div>

                        <div>
                            <label class="form-label">Links</label>
                            <div v-if="!updateForm.links.length" class="text-muted small mb-2">Add internal or external links for this issue.</div>
                            <div v-for="(link, index) in updateForm.links" :key="index" class="row g-2 align-items-center mb-2">
                                <div class="col-5">
                                    <input v-model="link.label" type="text" class="form-control" placeholder="Label (optional)" :class="{ 'is-invalid-soft': updateForm.errors[`links.${index}.label`] }">
                                </div>
                                <div class="col-6">
                                    <input v-model="link.url" type="text" class="form-control" placeholder="https:// or /internal/path" :class="{ 'is-invalid-soft': updateForm.errors[`links.${index}.url`] }">
                                </div>
                                <div class="col-1 d-grid">
                                    <button type="button" class="btn btn-outline-danger" @click="removeUpdateLink(index)">×</button>
                                </div>
                                <div class="col-12">
                                    <FormError :message="updateForm.errors[`links.${index}.url`] || updateForm.errors[`links.${index}.label`]" />
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="addUpdateLink">+ Add Link</button>
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
                        <div v-for="(image, index) in issueImages" :key="image.id" class="position-relative">
                            <img
                                :src="image.url"
                                :alt="image.original_name || issue.title"
                                class="gallery-thumb"
                                role="button"
                                tabindex="0"
                                @click="openImageModal(index)"
                            >
                            <button type="button" class="btn btn-light btn-sm position-absolute top-0 end-0 m-2" @click.stop="deleteImage(image)">Delete</button>
                        </div>
                    </div>
                </section>

                <section class="panel-card mb-4">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Attachments</p>
                            <h3 class="panel-title">Supporting files for this issue</h3>
                        </div>
                    </div>

                    <div v-if="!issueFiles.length" class="empty-state-card">
                        <strong>No files attached</strong>
                        <p>Add PDFs, spreadsheets, notes, or archives from the edit form.</p>
                    </div>

                    <div v-else class="list-group list-group-flush">
                        <div v-for="file in issueFiles" :key="file.id" class="list-group-item d-flex justify-content-between align-items-center">
                            <a :href="file.url" target="_blank" rel="noopener noreferrer" class="text-decoration-none">{{ file.original_name || 'Attachment' }}</a>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted">{{ formatFileSize(file.size) }}</small>
                                <button type="button" class="btn btn-outline-danger btn-sm" @click="deleteFile(file)">Delete</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel-card mb-4">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Links</p>
                            <h3 class="panel-title">Internal and external references</h3>
                        </div>
                    </div>

                    <div v-if="!issueLinks.length" class="empty-state-card">
                        <strong>No links added</strong>
                        <p>Add internal or external links from the edit form.</p>
                    </div>

                    <div v-else class="list-group list-group-flush">
                        <div v-for="link in issueLinks" :key="link.id" class="list-group-item d-flex justify-content-between align-items-center">
                            <a :href="link.url" target="_blank" rel="noopener noreferrer" class="text-decoration-none">{{ link.label || link.url }}</a>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted">{{ link.is_external ? 'External' : 'Internal' }}</small>
                                <button type="button" class="btn btn-outline-danger btn-sm" @click="deleteLink(link)">Delete</button>
                            </div>
                        </div>
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
                    <input v-model="childForm.title" type="text" class="form-control" :class="{ 'is-invalid-soft': childForm.errors.title }">
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
                            <option v-for="parent in projectIssues" :key="parent.id" :value="parent.id">{{ parent.label }}</option>
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
                    <input type="file" multiple accept=".jpg,.jpeg,.png" class="form-control" :class="{ 'is-invalid-soft': childForm.errors.images || childForm.errors['images.0'] }" @change="onChildImageChange">
                    <FormError :message="childForm.errors.images || childForm.errors['images.0']" />
                </div>

                <div>
                    <label class="form-label">Files</label>
                    <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.rtf,.ppt,.pptx,.zip,.rar" class="form-control" :class="{ 'is-invalid-soft': childForm.errors.files || childForm.errors['files.0'] }" @change="onChildFileChange">
                    <FormError :message="childForm.errors.files || childForm.errors['files.0']" />
                </div>

                <div>
                    <label class="form-label">Links</label>
                    <div v-if="!childForm.links.length" class="text-muted small mb-2">Add internal or external links for this issue.</div>
                    <div v-for="(link, index) in childForm.links" :key="index" class="row g-2 align-items-center mb-2">
                        <div class="col-5">
                            <input v-model="link.label" type="text" class="form-control" placeholder="Label (optional)" :class="{ 'is-invalid-soft': childForm.errors[`links.${index}.label`] }">
                        </div>
                        <div class="col-6">
                            <input v-model="link.url" type="text" class="form-control" placeholder="https:// or /internal/path" :class="{ 'is-invalid-soft': childForm.errors[`links.${index}.url`] }">
                        </div>
                        <div class="col-1 d-grid">
                            <button type="button" class="btn btn-outline-danger" @click="removeChildLink(index)">×</button>
                        </div>
                        <div class="col-12">
                            <FormError :message="childForm.errors[`links.${index}.url`] || childForm.errors[`links.${index}.label`]" />
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="addChildLink">+ Add Link</button>
                </div>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="childForm.processing">
                    <span v-if="childForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Create Sub-Issue
                </button>
            </form>
        </Modal>
    </AppLayout>

    <Modal v-model="imageModalOpen" title="Issue Images" size="modal-lg">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <button type="button" class="btn btn-outline-secondary" @click="showPrevImage">Prev</button>
            <div class="flex-grow-1 text-center">
                <img
                    v-if="issueImages.length"
                    :src="issueImages[activeImageIndex]?.url"
                    :alt="issueImages[activeImageIndex]?.original_name || issue.title"
                    class="img-fluid rounded"
                >
                <div v-else class="text-muted">No images to display.</div>
                <div class="text-muted small mt-2">{{ activeImageIndex + 1 }} / {{ issueImages.length }}</div>
            </div>
            <button type="button" class="btn btn-outline-secondary" @click="showNextImage">Next</button>
        </div>
    </Modal>
</template>
