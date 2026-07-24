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
                <button v-if="canCreateIssue" class="btn btn-accent rounded-pill" @click="modalOpen = true">Add Issue</button>
            </div>
        </section>

        <section v-if="pinnedIssues.length" class="panel-card mb-4">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Pinned Issues</p>
                    <h3 class="panel-title">Your project bookmarks</h3>
                </div>
            </div>

            <div v-if="pinnedIssues.length" class="row g-3">
                <div v-for="issue in pinnedIssues" :key="issue.id" class="col-md-6 col-xl-4">
                    <div class="border rounded-4 p-3 h-100 bg-light-subtle d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-2">
                            <span class="table-avatar issue flex-shrink-0">{{ issue.title.slice(0, 1) }}</span>
                            <div class="min-w-0">
                                <Link :href="`/issues/${issue.id}`" class="fw-semibold text-decoration-none text-dark d-block">{{ issue.title }}</Link>
                                <div v-if="issue.tags?.length" class="d-flex flex-wrap gap-1 mt-2">
                                    <span v-for="tag in issue.tags" :key="tag.id" class="badge rounded-pill text-bg-light border">{{ tag.name }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between gap-2 mt-auto">
                            <StatusPill :status="issue.status" />
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-light rounded-pill" @click="openQuickRead(issue)">Quick read</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" @click="togglePin(issue)">Unpin</button>
                            </div>
                        </div>
                    </div>
                </div>
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

            <form class="filters-row mb-3" @submit.prevent="applyFilters">
                <select v-model="filterForm.status" class="form-select" @change="applyFilters">
                    <option value="">All statuses</option>
                    <option value="todo">Todo</option>
                    <option value="inprogress">In Progress</option>
                    <option value="done">Done</option>
                </select>

                <details ref="tagFilterDropdown" class="tag-filter-dropdown">
                    <summary class="form-select">{{ tagFilterLabel }}</summary>
                    <div class="tag-filter-menu">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="small text-muted">Select one or more tags</span>
                            <button type="button" class="btn btn-link btn-sm p-0" @click="clearTagFilters">Clear</button>
                        </div>
                        <label v-for="tag in projectTags" :key="tag.id" class="tag-filter-option">
                            <input
                                type="checkbox"
                                :checked="filterForm.tag_ids.includes(String(tag.id))"
                                @change="toggleTagFilter(tag.id)"
                            >
                            <span>{{ tag.name }}</span>
                        </label>
                        <p v-if="!projectTags?.length" class="small text-muted mb-0">No tags have been added to this project yet.</p>
                    </div>
                </details>

                <div class="d-flex gap-2 flex-grow-1">
                    <input
                        v-model="filterForm.q"
                        type="search"
                        class="form-control"
                        placeholder="Search title or description..."
                        aria-label="Search issue title or description"
                    >
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </div>
            </form>

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
                                        <!-- <small>{{ plainText(issue.description) || 'No description added yet.' }}</small> -->
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
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" @click="togglePin(issue)">
                                        {{ issue.is_pinned ? 'Unpin' : 'Pin' }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light rounded-pill" @click="openQuickRead(issue)">Quick read</button>
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

        <section v-if="canManageMembers || projectMembers.length" class="panel-card mt-4">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Project Members</p>
                    <h3 class="panel-title">Users with access to this project</h3>
                </div>
                <button v-if="canManageMembers" class="btn btn-accent rounded-pill" @click="memberModalOpen = true">Add Member</button>
            </div>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Role</th>
                            <th v-if="canManageMembers" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in projectMembers" :key="member.id">
                            <td data-label="Member">
                                <div class="table-entity">
                                    <span class="table-avatar">
                                        {{ member.user?.name?.slice(0, 1) }} 
                                    </span>
                                    <div>
                                        <div class="d-flex align-items-center flex-wrap gap-2">
                                            <strong class="mb-0">{{ member.user?.name }}</strong> 
                                            <span v-if="member.user_id === project.user_id" class="badge bg-warning text-dark rounded-pill shadow-sm" style="font-size: 0.7em; font-weight: 600; letter-spacing: 0.5px;">
                                                👑 Project Creator
                                            </span>
                                        </div>
                                        <small class="d-block text-muted mt-1">{{ member.user?.email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Role">
                                <select
                                    v-if="canEditRoles"
                                    :value="member.role_id"
                                    class="form-select form-select-sm w-auto"
                                    @change="updateMemberRole(member, $event.target.value)"
                                >
                                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                </select>
                                <span v-else class="badge rounded-pill text-bg-light border px-3 py-2">{{ member.role?.name }}</span>
                            </td>
                            <td v-if="canManageMembers" data-label="Actions">
                                <div class="table-actions">
                                    <button
                                        v-if="canEditRoles || member.user_id !== project.user_id"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger rounded-pill"
                                        @click="removeMember(member)"
                                    >
                                        Remove
                                    </button>
                                    <span v-else class="text-muted small">Owner</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!projectMembers.length">
                            <td :colspan="canManageMembers ? 3 : 2">
                                <div class="table-empty">No members yet. Add the first member to collaborate.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Modal v-model="memberModalOpen" title="Add Member">
            <form class="vstack gap-3" @submit.prevent="addMember">
                <div>
                    <label class="form-label">User</label>
                    <select v-model="memberForm.user_id" class="form-select" :class="{ 'is-invalid-soft': memberForm.errors.user_id }">
                        <option value="">Select user</option>
                        <option v-for="user in addableUsers" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.email }})
                        </option>
                    </select>
                    <FormError :message="memberForm.errors.user_id" />
                </div>
                <div>
                    <label class="form-label">Role</label>
                    <select v-model="memberForm.role_id" class="form-select" :class="{ 'is-invalid-soft': memberForm.errors.role_id }">
                        <option value="">Select role</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                    </select>
                    <FormError :message="memberForm.errors.role_id" />
                </div>
                <button class="btn btn-accent rounded-pill align-self-start" :disabled="memberForm.processing">
                    <span v-if="memberForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Add Member
                </button>
            </form>
        </Modal>

        <Modal v-model="quickReadModalOpen" :title="activeIssue?.title || 'Issue quick read'" size="modal-lg">
            <article v-if="activeIssue" class="vstack gap-4">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <StatusPill :status="activeIssue.status" />
                    <span v-if="activeIssue.parent_issue" class="badge rounded-pill text-bg-light border">Parent: {{ activeIssue.parent_issue.title }}</span>
                    <span v-if="activeIssue.user?.name" class="text-muted small">Created by {{ activeIssue.user.name }}</span>
                    <span class="text-muted small">Created {{ formatIssueDate(activeIssue.created_at) }}</span>
                    <span v-if="activeIssue.updated_at" class="text-muted small">Updated {{ formatIssueDate(activeIssue.updated_at) }}</span>
                </div>

                <div>
                    <p class="section-kicker mb-1">Issue details</p>
                    <div v-if="activeIssue.description" class="rich-display" v-html="activeIssue.description" />
                    <p v-else class="text-muted mb-0">No description added yet.</p>
                </div>

                <div v-if="activeIssue.tags?.length">
                    <p class="section-kicker mb-2">Tags</p>
                    <div class="d-flex flex-wrap gap-1">
                        <span v-for="tag in activeIssue.tags" :key="tag.id" class="badge rounded-pill text-bg-light border">{{ tag.name }}</span>
                    </div>
                </div>

                <div class="row g-3 text-center">
                    <div class="col-4"><div class="border rounded-3 p-2"><strong class="d-block">{{ activeIssue.sub_issues_count ?? 0 }}</strong><small class="text-muted">Sub-issues</small></div></div>
                    <div class="col-4"><div class="border rounded-3 p-2"><strong class="d-block">{{ activeIssue.images?.length ?? activeIssue.images_count ?? 0 }}</strong><small class="text-muted">Images</small></div></div>
                    <div class="col-4"><div class="border rounded-3 p-2"><strong class="d-block">{{ activeIssue.files?.length ?? activeIssue.files_count ?? 0 }}</strong><small class="text-muted">Files</small></div></div>
                </div>

                <div v-if="activeIssue.images?.length">
                    <p class="section-kicker mb-2">Images</p>
                    <div class="row g-2">
                        <div v-for="image in activeIssue.images" :key="image.id" class="col-6 col-md-4">
                            <a :href="image.url" target="_blank" rel="noopener noreferrer"><img :src="image.url" :alt="image.original_name || activeIssue.title" class="img-fluid rounded-3 border" /></a>
                        </div>
                    </div>
                </div>

                <div v-if="activeIssue.files?.length">
                    <p class="section-kicker mb-2">Files</p>
                    <div class="list-group list-group-flush border rounded-3">
                        <a v-for="file in activeIssue.files" :key="file.id" :href="file.url" target="_blank" rel="noopener noreferrer" class="list-group-item list-group-item-action">{{ file.original_name || 'Attachment' }}</a>
                    </div>
                </div>

                <div v-if="activeIssue.links?.length">
                    <p class="section-kicker mb-2">Links</p>
                    <div class="list-group list-group-flush border rounded-3">
                        <a v-for="link in activeIssue.links" :key="link.id" :href="link.url" target="_blank" rel="noopener noreferrer" class="list-group-item list-group-item-action">{{ link.label || link.url }}</a>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <Link :href="`/issues/${activeIssue.id}`" class="btn btn-outline-secondary rounded-pill">Open full issue</Link>
                </div>
            </article>
        </Modal>

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

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import RichTextEditor from '../../Components/RichTextEditor.vue';
import StatusPill from '../../Components/StatusPill.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    project: Object,
    issues: Object,
    pinnedIssues: { type: Array, default: () => [] },
    projectTags: Array,
    filters: Object,
    breadcrumbs: Array,
    userRole:          { type: String,  default: null },
    canManageMembers:  { type: Boolean, default: false },
    canCreateIssue:    { type: Boolean, default: false },
    projectMembers:    { type: Array,   default: () => [] },
    addableUsers:      { type: Array,   default: () => [] },
    roles:             { type: Array,   default: () => [] },
    canEditRoles:      { type: Boolean, default: false }, // এটি যুক্ত করুন
});

const modalOpen = ref(false);
const quickReadModalOpen = ref(false);
const activeIssue = ref(null);
const issueRows = computed(() => props.issues?.data ?? []);
const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const openQuickRead = (issue) => {
    activeIssue.value = issue;
    quickReadModalOpen.value = true;
};
const formatIssueDate = (value) => value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
    : 'Unknown date';
const filterForm = reactive({
    status: props.filters?.status ?? '',
    tag_ids: Array.isArray(props.filters?.tag_ids) ? props.filters.tag_ids.map(String) : [],
    q: props.filters?.q ?? '',
});
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
const tagFilterDropdown = ref(null);

const memberModalOpen  = ref(false);
const memberForm = useForm({ user_id: '', role_id: '' });

const addMember = () => {
    memberForm.post(`/projects/${props.project.id}/members`, {
        onSuccess: () => {
            memberModalOpen.value = false;
            memberForm.reset();
        },
    });
};

const updateMemberRole = (member, roleId) => {
    router.put(`/projects/${props.project.id}/members/${member.id}`, { role_id: roleId }, {
        preserveScroll: true,
    });
};

const removeMember = (member) => {
    Swal.fire({
        title: `Remove ${member.user?.name}?`,
        text: 'They will lose access to this project.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Remove',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            router.delete(`/projects/${props.project.id}/members/${member.id}`, {
                preserveScroll: true,
            });
        }
    });
};

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

const togglePin = (issue) => {
    router.post(`/issues/${issue.id}/pin`, {}, { preserveScroll: true });
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

const applyFilters = () => {
    router.get(`/projects/${props.project.id}`, filterForm, {
        preserveState: true,
        replace: true,
    });
};

const closeTagFilterDropdown = () => {
    if (tagFilterDropdown.value) {
        tagFilterDropdown.value.open = false;
    }
};

const closeTagFilterOnOutsideClick = (event) => {
    if (tagFilterDropdown.value?.open && !tagFilterDropdown.value.contains(event.target)) {
        closeTagFilterDropdown();
    }
};

const toggleTagFilter = (tagId) => {
    const normalizedId = String(tagId);
    const index = filterForm.tag_ids.indexOf(normalizedId);

    if (index === -1) {
        filterForm.tag_ids.push(normalizedId);
    } else {
        filterForm.tag_ids.splice(index, 1);
    }

    applyFilters();
};

const clearTagFilters = () => {
    if (!filterForm.tag_ids.length) return;

    filterForm.tag_ids = [];
    closeTagFilterDropdown();
    applyFilters();
};

const tagFilterLabel = computed(() => {
    const count = filterForm.tag_ids.length;
    if (!count) return 'All tags';
    return count === 1 ? '1 tag selected' : `${count} tags selected`;
});

onMounted(() => document.addEventListener('click', closeTagFilterOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', closeTagFilterOnOutsideClick));
</script>
