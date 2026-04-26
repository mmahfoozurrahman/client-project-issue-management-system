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
    projectTags: Array,
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
    files: [],
    links: [],
    tag_names: [],
});
const tagInput = ref('');

const submit = () => {
    form.post('/issues', {
        forceFormData: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.reset('title', 'description', 'status', 'parent_id', 'images', 'files', 'tag_names');
            form.project_id = props.project.id;
        },
    });
};

const onImageChange = (event) => {
    form.images = Array.from(event.target.files || []);
};

const onFileChange = (event) => {
    form.files = Array.from(event.target.files || []);
};

const addLink = () => {
    form.links.push({ label: '', url: '' });
};

const removeLink = (index) => {
    form.links.splice(index, 1);
};

const addTagToForm = (value = tagInput.value) => {
    const normalized = String(value || '').trim();
    if (!normalized) return;
    const exists = form.tag_names.some((entry) => entry.toLowerCase() === normalized.toLowerCase());
    if (!exists) {
        form.tag_names.push(normalized);
    }
    tagInput.value = '';
};

const removeTagFromForm = (index) => {
    form.tag_names.splice(index, 1);
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
                                        <div v-if="issue.tags?.length" class="d-flex flex-wrap gap-1 mt-2">
                                            <span v-for="tag in issue.tags" :key="tag.id" class="badge rounded-pill text-bg-light border">{{ tag.name }}</span>
                                        </div>
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
                    <input v-model="form.title" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.title }">
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
                    <input type="file" multiple accept=".jpg,.jpeg,.png" class="form-control" :class="{ 'is-invalid-soft': form.errors.images || form.errors['images.0'] }" @change="onImageChange">
                    <FormError :message="form.errors.images || form.errors['images.0']" />
                </div>

                <div>
                    <label class="form-label">Files</label>
                    <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.rtf,.ppt,.pptx,.zip,.rar" class="form-control" :class="{ 'is-invalid-soft': form.errors.files || form.errors['files.0'] }" @change="onFileChange">
                    <FormError :message="form.errors.files || form.errors['files.0']" />
                </div>

                <div>
                    <label class="form-label">Links</label>
                    <div v-if="!form.links.length" class="text-muted small mb-2">Add internal or external links for this issue.</div>
                    <div v-for="(link, index) in form.links" :key="index" class="row g-2 align-items-center mb-2">
                        <div class="col-5">
                            <input v-model="link.label" type="text" class="form-control" placeholder="Label (optional)" :class="{ 'is-invalid-soft': form.errors[`links.${index}.label`] }">
                        </div>
                        <div class="col-6">
                            <input v-model="link.url" type="text" class="form-control" placeholder="https:// or /internal/path" :class="{ 'is-invalid-soft': form.errors[`links.${index}.url`] }">
                        </div>
                        <div class="col-1 d-grid">
                            <button type="button" class="btn btn-outline-danger" @click="removeLink(index)">×</button>
                        </div>
                        <div class="col-12">
                            <FormError :message="form.errors[`links.${index}.url`] || form.errors[`links.${index}.label`]" />
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="addLink">+ Add Link</button>
                </div>

                <div>
                    <label class="form-label">Tags</label>
                    <small class="text-muted d-block mb-2">Project-based tags for searching and grouping issues.</small>
                    <div class="d-flex gap-2 mb-2">
                        <input
                            v-model="tagInput"
                            type="text"
                            class="form-control"
                            placeholder="Type tag and press Enter"
                            @keyup.enter.prevent="addTagToForm()"
                        >
                        <button type="button" class="btn btn-outline-secondary" @click="addTagToForm()">Add</button>
                    </div>
                    <div v-if="projectTags?.length" class="d-flex flex-wrap gap-1 mb-2">
                        <button
                            v-for="tag in projectTags"
                            :key="tag.id"
                            type="button"
                            class="btn btn-sm btn-light border rounded-pill"
                            @click="addTagToForm(tag.name)"
                        >
                            + {{ tag.name }}
                        </button>
                    </div>
                    <div v-if="form.tag_names.length" class="d-flex flex-wrap gap-1">
                        <span v-for="(tag, index) in form.tag_names" :key="`${tag}-${index}`" class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1 px-2 py-1">
                            {{ tag }}
                            <button type="button" class="btn btn-sm p-0 border-0 bg-transparent" @click="removeTagFromForm(index)">x</button>
                        </span>
                    </div>
                    <FormError :message="form.errors.tag_names || form.errors['tag_names.0']" />
                </div>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                    Create Issue
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>
