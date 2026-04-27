<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import SkeletonCard from '../../Components/SkeletonCard.vue';
import Pagination from '../../Components/Pagination.vue';
import RichTextEditor from '../../Components/RichTextEditor.vue';
import StatusPill from '../../Components/StatusPill.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    issues: Object,
    projects: Array,
    issueTags: Array,
    filters: Object,
    breadcrumbs: Array,
});

const loading = ref(false);
const modalOpen = ref(false);
const page = usePage();
const issueRows = computed(() => props.issues?.data ?? props.issues ?? []);
const staleDays = computed(() => Number(page.props.app?.issue_stale_days ?? 3));
const criticalDays = computed(() => Number(page.props.app?.issue_critical_days ?? 7));
const formatIssueDate = (value) => {
    if (!value) return 'Recently created';

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
};

const searchFilters = reactive({
    project_id: props.filters?.project_id ?? '',
    status: props.filters?.status ?? '',
    tag_id: props.filters?.tag_id ?? '',
    q: props.filters?.q ?? '',
    at_risk: props.filters?.at_risk ? '1' : '',
});

const form = useForm({
    title: '',
    description: '',
    status: 'todo',
    project_id: props.filters?.project_id ?? '',
    parent_id: '',
    images: [],
    files: [],
    links: [],
    tag_names: [],
});

const tagInput = ref('');

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

const availableTagNames = computed(() => {
    if (!form.project_id) return [];

    return (props.issueTags ?? [])
        .filter((tag) => String(tag.project_id) === String(form.project_id))
        .map((tag) => tag.name);
});

const staleAgeDays = (issue) => {
    if (issue.status === 'done') return '';
    const updated = issue.updated_at ? new Date(issue.updated_at) : null;
    if (!updated) return '';
    const days = Math.floor((Date.now() - updated.getTime()) / (1000 * 60 * 60 * 24));
    return days > 0 ? days : 0;
};

const staleAgeLabel = (issue) => {
    const days = staleAgeDays(issue);
    return days ? `${days}d idle` : '';
};

const idleMetaClass = (issue) => {
    const days = staleAgeDays(issue);
    if (!days) return '';
    if (days >= criticalDays.value) return 'issue-idle-critical';
    if (days >= staleDays.value) return 'issue-idle-watch';
    return '';
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

                <select v-model="searchFilters.tag_id" class="form-select" @change="applyFilters">
                    <option value="">All tags</option>
                    <option v-for="tag in issueTags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                </select>

                <select v-model="searchFilters.at_risk" class="form-select" @change="applyFilters">
                    <option value="">All tempos</option>
                    <option value="1">At Risk</option>
                </select>

                <div class="d-flex gap-2 flex-grow-1">
                    <input
                        v-model="searchFilters.q"
                        type="text"
                        class="form-control"
                        placeholder="Search issue title/description..."
                        @keyup.enter="applyFilters"
                    >
                    <button type="button" class="btn btn-outline-secondary" @click="applyFilters">Search</button>
                </div>
            </div>

            <div class="compact-table-shell">
                <div v-if="loading" class="compact-skeleton-list">
                    <SkeletonCard v-for="n in 4" :key="n" />
                </div>
                <table v-else class="compact-table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Sub-issues</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="issue in issueRows" :key="issue.id">
                            <td data-label="Issue">
                                <div class="table-entity">
                                    <span class="table-avatar issue">{{ issue.title.slice(0, 1) }}</span>
                                    <div>
                                        <strong>{{ issue.title }}</strong>
                                        <small class="issue-date-meta">Created {{ formatIssueDate(issue.created_at) }}</small>
                                        <small v-if="issue.status === 'done'" class="issue-date-meta" :style="{ color: '#1f7a6e', fontWeight: '600' }">
                                            Completed {{ formatIssueDate(issue.done_at) }}
                                        </small>
                                        <small v-else-if="staleAgeLabel(issue)" class="issue-date-meta" :class="idleMetaClass(issue)">
                                            {{ staleAgeLabel(issue) }}
                                        </small>
                                        <div v-if="issue.tags?.length" class="d-flex flex-wrap gap-1 mt-2">
                                            <span v-for="tag in issue.tags" :key="tag.id" class="badge rounded-pill text-bg-light border">{{ tag.name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Client">{{ issue.project?.client?.name || 'No client' }}</td>
                            <td data-label="Project">{{ issue.project?.name || 'No project' }}</td>
                            <td data-label="Status"><StatusPill :status="issue.status" /></td>
                            <td data-label="Sub-issues">{{ issue.sub_issues_count ?? 0 }}</td>
                            <td data-label="Actions">
                                <div class="table-actions">
                                    <Link :href="`/issues/${issue.id}`" class="btn btn-sm btn-light rounded-pill">Open</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!issueRows.length">
                            <td colspan="6">
                                <div class="table-empty">No issues match the current filters.</div>
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

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Project</label>
                        <select v-model="form.project_id" class="form-select" :class="{ 'is-invalid-soft': form.errors.project_id }">
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
                    <RichTextEditor v-model="form.description" :error="form.errors.description" placeholder="Add rich details, links, notes, or acceptance context..." />
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
                    <div v-if="availableTagNames.length" class="d-flex flex-wrap gap-1 mb-2">
                        <button
                            v-for="tagName in availableTagNames"
                            :key="tagName"
                            type="button"
                            class="btn btn-sm btn-light border rounded-pill"
                            @click="addTagToForm(tagName)"
                        >
                            + {{ tagName }}
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

<style scoped>
.issue-idle-watch {
    color: #a2611d;
    font-weight: 600;
}

.issue-idle-critical {
    color: #b42323;
    font-weight: 700;
}
</style>
