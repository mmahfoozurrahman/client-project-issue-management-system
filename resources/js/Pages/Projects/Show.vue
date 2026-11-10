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
            <div class="panel-header pinned-issues-header">
                <div>
                    <p class="section-kicker">Pinned Issues</p>
                    <h3 class="panel-title">Your project bookmarks</h3>
                </div>
                <div v-if="showPinnedControls" class="pinned-issues-controls" aria-label="Pinned issues navigation">
                    <button
                        type="button"
                        class="pinned-scroll-button"
                        :disabled="!canScrollPinnedLeft"
                        aria-label="Show earlier pinned issues"
                        @click="scrollPinnedIssues(-1)"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                    </button>
                    <button
                        type="button"
                        class="pinned-scroll-button"
                        :disabled="!canScrollPinnedRight"
                        aria-label="Show more pinned issues"
                        @click="scrollPinnedIssues(1)"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                    </button>
                </div>
            </div>

            <div v-if="pinnedIssues.length" ref="pinnedIssuesTrack" class="pinned-issues-track" @scroll.passive="updatePinnedScrollState">
                <article v-for="issue in pinnedIssues" :key="issue.id" class="pinned-issue-slide">
                    <div class="pinned-issue-card border rounded-4 p-3 bg-light-subtle d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-2">
                            <span class="table-avatar issue flex-shrink-0">{{ issue.title.slice(0, 1) }}</span>
                            <div class="min-w-0">
                                <Link :href="issueUrl(issue)" class="fw-semibold text-decoration-none text-dark d-block">{{ issue.title }}</Link>
                                <div v-if="issue.tags?.length" class="d-flex flex-wrap gap-1 mt-2">
                                    <Link v-for="tag in issue.tags" :key="tag.id" :href="`/issues?tag_id=${tag.id}&project_id=${issue.project_id}`" class="badge rounded-pill text-bg-light border text-decoration-none">{{ tag.name }}</Link>
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
                </article>
            </div>
        </section>

        <section class="panel-card mb-4">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Project Progress</p>
                    <h3 class="panel-title">Completion overview</h3>
                </div>
                <span class="badge rounded-pill text-bg-light border">{{ progressPercent }}% complete</span>
            </div>

            <div class="project-progress-hero">
                <div class="project-progress-figure">
                    <strong>{{ progressPercent }}%</strong>
                    <span>{{ progressDone }} of {{ progressTotal }} top-level issues done</span>
                </div>

                <div
                    class="project-progress-meter"
                    role="img"
                    :aria-label="`${progressPercent}% of top-level issues completed`"
                >
                    <span class="project-progress-segment is-todo" :style="{ width: progressTodoWidth }" />
                    <span class="project-progress-segment is-inprogress" :style="{ width: progressInProgressWidth }" />
                    <span class="project-progress-segment is-done" :style="{ width: progressDoneWidth }" />
                </div>
            </div>

            <div class="project-progress-stats">
                <article class="project-progress-stat is-todo">
                    <span>Todo</span>
                    <strong>{{ progressTodo }}</strong>
                    <small>{{ progressTodoShare }}% of all issues</small>
                </article>
                <article class="project-progress-stat is-inprogress">
                    <span>In Progress</span>
                    <strong>{{ progressInProgress }}</strong>
                    <small>{{ progressInProgressShare }}% of all issues</small>
                </article>
                <article class="project-progress-stat is-done">
                    <span>Done</span>
                    <strong>{{ progressDone }}</strong>
                    <small>{{ progressDoneShare }}% of all issues</small>
                </article>
            </div>

            <p v-if="progressTotal === 0" class="project-progress-empty">
                No issues in this project yet — progress will appear once issues are created.
            </p>
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

                <TagFilterAutocomplete
                    v-model="filterForm.tag_ids"
                    :tags="projectTags"
                    placeholder="Filter tags..."
                    @change="applyTagFilter"
                />

                <div class="d-flex gap-2 flex-grow-1">
                    <IssueSearchAutocomplete
                        v-model="filterForm.q"
                        :project-id="project.id"
                        :status="filterForm.status"
                        :tag-ids="filterForm.tag_ids"
                        placeholder="Search title, description, or link..."
                        aria-label="Search issue title or description"
                        @search="applyFilters"
                        @select="selectIssueSuggestion"
                    />
                    <button type="submit" class="btn btn-outline-secondary rounded-pill px-4">Search</button>
                </div>
            </form>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Status</th>
                            <th>Sub-issues</th>
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
                                        <small class="issue-date-meta">Created {{ formatIssueDate(issue.created_at) }}</small>
                                        <small v-if="issue.updated_at" class="issue-date-meta" :style="{ color: '#1f7a6e', fontWeight: '600' }">
                                            Updated {{ formatIssueDate(issue.updated_at) }}
                                        </small>
                                        <div v-if="issue.tags?.length" class="d-flex flex-wrap gap-1 mt-2">
                                            <Link v-for="tag in issue.tags" :key="tag.id" :href="`/issues?tag_id=${tag.id}&project_id=${issue.project_id}`" class="badge rounded-pill text-bg-light border text-decoration-none">{{ tag.name }}</Link>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Status"><StatusPill :status="issue.status" /></td>
                            <td data-label="Sub-issues">{{ issue.sub_issues_count ?? 0 }}</td>
                            <td data-label="Action">
                                <div class="table-actions">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" @click="togglePin(issue)">
                                        {{ issue.is_pinned ? 'Unpin' : 'Pin' }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light rounded-pill" @click="openQuickRead(issue)">Quick read</button>
                                    <Link :href="issueUrl(issue)" class="btn btn-sm btn-light rounded-pill">Open</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!issueRows.length">
                            <td colspan="4">
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

        <IssueQuickReadModal v-model="quickReadModalOpen" :issue="activeIssue" />

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

                <div @dragover.prevent @drop.prevent="onImageDrop">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label mb-0">Images</label>
                        <span class="badge text-bg-light border text-muted small">
                            💡 Press <kbd>Ctrl+V</kbd> to paste screenshot (JPEG)
                        </span>
                    </div>
                    <input
                        ref="fileInputRef"
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png"
                        class="form-control"
                        :class="{ 'is-invalid-soft': form.errors.images || form.errors['images.0'] }"
                        @change="onImageChange"
                    >
                    <FormError :message="form.errors.images || form.errors['images.0']" />

                    <div v-if="stagedImages.length" class="staged-images-container mt-2 p-2 border rounded bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small font-monospace text-muted fw-semibold">
                                {{ stagedImages.length }} image{{ stagedImages.length > 1 ? 's' : '' }} attached
                            </span>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary rounded-pill"
                                @click="clearStagedImages"
                            >
                                Clear
                            </button>
                        </div>

                        <div class="staged-images-grid">
                            <div
                                v-for="(item, idx) in stagedImages"
                                :key="item.id"
                                class="staged-image-card"
                            >
                                <img :src="item.previewUrl" :alt="item.file.name" class="staged-image-thumb">
                                <div class="staged-image-info">
                                    <span class="staged-image-name text-truncate" :title="item.file.name">{{ item.file.name }}</span>
                                    <div class="d-flex align-items-center justify-content-between mt-1">
                                        <span class="badge rounded-pill bg-white border text-dark font-monospace extra-small">
                                            {{ formatFileSize(item.file.size) }}
                                        </span>
                                        <span v-if="item.isPasted" class="badge rounded-pill text-bg-success extra-small">
                                            Pasted (JPEG)
                                        </span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="staged-image-remove-btn"
                                    title="Remove image"
                                    @click="removeStagedImage(idx)"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    </div>
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
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import IssueQuickReadModal from '../../Components/IssueQuickReadModal.vue';
import IssueSearchAutocomplete from '../../Components/IssueSearchAutocomplete.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import RichTextEditor from '../../Components/RichTextEditor.vue';
import StatusPill from '../../Components/StatusPill.vue';
import TagFilterAutocomplete from '../../Components/TagFilterAutocomplete.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatIssueDate } from '../../utils/date';
import { convertBlobToJpegFile, getScreenshotFilename, formatFileSize } from '../../utils/image';
import { issueUrl } from '../../utils/issue';
import { projectUrl } from '../../utils/project';

const props = defineProps({
    project: Object,
    issues: Object,
    progress: { type: Object, default: () => ({}) },
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
const pinnedIssuesTrack = ref(null);
const canScrollPinnedLeft = ref(false);
const canScrollPinnedRight = ref(false);
const showPinnedControls = ref(false);
const issueRows = computed(() => props.issues?.data ?? []);

/* Project completion analytics ------------------------------------------------- */
const progressTotal = computed(() => Number(props.progress?.total ?? 0));
const progressTodo = computed(() => Number(props.progress?.todo ?? 0));
const progressInProgress = computed(() => Number(props.progress?.inprogress ?? 0));
const progressDone = computed(() => Number(props.progress?.done ?? 0));
const progressPercent = computed(() => Number(props.progress?.percent ?? 0));

const progressRatio = (count) => (progressTotal.value > 0 ? (count / progressTotal.value) * 100 : 0);

const progressTodoWidth = computed(() => `${progressRatio(progressTodo.value)}%`);
const progressInProgressWidth = computed(() => `${progressRatio(progressInProgress.value)}%`);
const progressDoneWidth = computed(() => `${progressRatio(progressDone.value)}%`);

const progressTodoShare = computed(() => Math.round(progressRatio(progressTodo.value)));
const progressInProgressShare = computed(() => Math.round(progressRatio(progressInProgress.value)));
const progressDoneShare = computed(() => Math.round(progressRatio(progressDone.value)));

const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const openQuickRead = (issue) => {
    activeIssue.value = issue;
    quickReadModalOpen.value = true;
};
const filterForm = reactive({
    status: props.filters?.status ?? '',
    tag_ids: Array.isArray(props.filters?.tag_ids) ? props.filters.tag_ids.map(String) : [],
    q: props.filters?.q ?? '',
});
const selectIssueSuggestion = (issue) => {
    filterForm.q = issue.title;
    applyFilters();
};
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
            clearStagedImages();
            form.reset('title', 'description', 'status', 'parent_id', 'images', 'files', 'tag_names');
            form.project_id = props.project.id;
        },
    });
};

const togglePin = (issue) => {
    router.post(`/issues/${issue.id}/pin`, {}, { preserveScroll: true });
};

const updatePinnedScrollState = () => {
    const track = pinnedIssuesTrack.value;
    if (!track) return;

    const scrollable = track.scrollWidth > track.clientWidth + 1;
    showPinnedControls.value = scrollable;
    canScrollPinnedLeft.value = scrollable && track.scrollLeft > 1;
    canScrollPinnedRight.value = scrollable && track.scrollLeft + track.clientWidth < track.scrollWidth - 1;
};

const scrollPinnedIssues = (direction) => {
    const track = pinnedIssuesTrack.value;
    if (!track) return;

    track.scrollBy({ left: direction * Math.round(track.clientWidth * 0.85), behavior: 'smooth' });
};

const handlePinnedIssuesResize = () => nextTick(updatePinnedScrollState);

const stagedImages = ref([]);
const fileInputRef = ref(null);

const clearStagedImages = () => {
    stagedImages.value.forEach((item) => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
    });
    stagedImages.value = [];
    form.images = [];
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const removeStagedImage = (index) => {
    const item = stagedImages.value[index];
    if (item?.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
    }
    stagedImages.value.splice(index, 1);
    form.images = stagedImages.value.map((i) => i.file);
};

const onImageChange = async (event) => {
    const files = Array.from(event.target.files || []);
    for (const file of files) {
        let finalFile = file;
        if (file.type !== 'image/jpeg' && file.type.startsWith('image/')) {
            finalFile = await convertBlobToJpegFile(file, file.name);
        }
        stagedImages.value.push({
            id: 'file_' + Math.random().toString(36).substring(2, 9),
            file: finalFile,
            previewUrl: URL.createObjectURL(finalFile),
            isPasted: false,
        });
    }
    form.images = stagedImages.value.map((i) => i.file);
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const onImageDrop = async (event) => {
    const files = Array.from(event.dataTransfer?.files || []).filter(
        (f) => f.type && f.type.startsWith('image/')
    );
    if (!files.length) return;

    for (const file of files) {
        let finalFile = file;
        if (file.type !== 'image/jpeg') {
            finalFile = await convertBlobToJpegFile(file, file.name);
        }
        stagedImages.value.push({
            id: 'drop_' + Math.random().toString(36).substring(2, 9),
            file: finalFile,
            previewUrl: URL.createObjectURL(finalFile),
            isPasted: false,
        });
    }
    form.images = stagedImages.value.map((i) => i.file);
};

const handlePaste = async (event) => {
    if (!modalOpen.value) {
        return;
    }

    const clipboardData = event.clipboardData || window.clipboardData;
    if (!clipboardData) return;

    const items = Array.from(clipboardData.items || []);
    const imageItems = items.filter(
        (item) => item.kind === 'file' && item.type && item.type.startsWith('image/')
    );

    if (imageItems.length === 0) {
        return;
    }

    event.preventDefault();

    const newStagedList = [];
    let counter = 1;

    for (const item of imageItems) {
        const rawBlob = item.getAsFile();
        if (!rawBlob) continue;

        const filename = getScreenshotFilename(counter, imageItems.length);
        counter++;

        const jpegFile = await convertBlobToJpegFile(rawBlob, filename);
        newStagedList.push({
            id: 'paste_' + Math.random().toString(36).substring(2, 9),
            file: jpegFile,
            previewUrl: URL.createObjectURL(jpegFile),
            isPasted: true,
        });
    }

    if (!newStagedList.length) return;

    stagedImages.value.push(...newStagedList);
    form.images = stagedImages.value.map((i) => i.file);

    Swal.fire({
        toast: true,
        position: 'top-end',
        timer: 2600,
        showConfirmButton: false,
        icon: 'success',
        title: newStagedList.length > 1
            ? `📸 ${newStagedList.length} screenshots added to issue (JPEG)`
            : '📸 Screenshot added to issue (JPEG)',
    });
};

watch(modalOpen, (isOpen) => {
    if (!isOpen) {
        clearStagedImages();
        form.reset('title', 'description', 'status', 'parent_id', 'images', 'files', 'tag_names');
        form.project_id = props.project.id;
        form.clearErrors();
    }
});

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
    router.get(projectUrl(props.project), filterForm, {
        preserveState: true,
        replace: true,
    });
};

const applyTagFilter = (tagIds) => {
    filterForm.tag_ids = Array.isArray(tagIds) ? tagIds.map(String) : [];
    applyFilters();
};

onMounted(() => {
    window.addEventListener('resize', handlePinnedIssuesResize);
    window.addEventListener('paste', handlePaste);
    nextTick(updatePinnedScrollState);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', handlePinnedIssuesResize);
    window.removeEventListener('paste', handlePaste);
    clearStagedImages();
});
</script>
