<script setup>
import { computed, reactive, ref } from 'vue';
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
const issueRows = computed(() => props.issues?.data ?? []);
const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const filterForm = reactive({
    tag_id: props.filters?.tag_id ?? '',
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
                <button v-if="canCreateIssue" class="btn btn-accent rounded-pill" @click="modalOpen = true">Add Issue</button>
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

            <div class="filters-row mb-3">
                <select v-model="filterForm.tag_id" class="form-select" @change="applyFilters">
                    <option value="">All tags</option>
                    <option v-for="tag in projectTags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                </select>
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
