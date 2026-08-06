<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDate } from '../../utils/date';

const props = defineProps({
    tags: { type: Object, default: () => ({}) },
    projects: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    breadcrumbs: { type: Array, default: () => [] },
});

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const activeTag = ref(null);

const createForm = useForm({ project_id: '', name: '' });
const editForm = useForm({ name: '' });

const tagRows = computed(() => props.tags?.data ?? []);
const defaultProjectId = computed(() => props.projects?.[0]?.id ?? '');

const filterForm = reactive({
    project_id: props.filters?.project_id ?? '',
    q: props.filters?.q ?? '',
});

const applyFilters = () => {
    router.get('/tags', filterForm, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterForm.project_id = '';
    filterForm.q = '';
    applyFilters();
};

const openCreate = () => {
    createForm.project_id = defaultProjectId.value;
    createForm.name = '';
    createModalOpen.value = true;
};

const openEdit = (tag) => {
    activeTag.value = tag;
    editForm.name = tag.name;
    editModalOpen.value = true;
};

const submitCreate = () => {
    createForm.post('/tags', {
        onSuccess: () => {
            createModalOpen.value = false;
            createForm.reset();
            createForm.project_id = defaultProjectId.value;
        },
    });
};

const submitEdit = () => {
    editForm.put(`/tags/${activeTag.value.id}`, {
        onSuccess: () => {
            editModalOpen.value = false;
        },
    });
};

const destroyTag = (tag) => {
    Swal.fire({
        title: `Delete tag "${tag.name}"?`,
        text: 'This will remove the tag from all linked issues.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            router.delete(`/tags/${tag.id}`, { preserveScroll: true });
        }
    });
};
</script>

<template>
    <Head title="Tags" />

    <AppLayout title="Tags" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Workspace Labels</p>
                    <h3 class="panel-title">Tag Management</h3>
                </div>
                <button class="btn btn-accent rounded-pill" @click="openCreate">+ Create Tag</button>
            </div>

            <form class="filters-row" @submit.prevent="applyFilters">
                <select v-model="filterForm.project_id" class="form-select" @change="applyFilters">
                    <option value="">All projects</option>
                    <option v-for="project in projects" :key="project.id" :value="project.id">
                        {{ project.name }}
                    </option>
                </select>

                <div class="d-flex gap-2 grow">
                    <input
                        v-model="filterForm.q"
                        type="search"
                        class="form-control"
                        placeholder="Search by tag name or slug..."
                    >
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                    <button type="button" class="btn btn-light border" @click="clearFilters">Clear</button>
                </div>
            </form>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Project</th>
                            <th>Issues</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(tag, index) in tagRows" :key="tag.id">
                            <td data-label="#">{{ (tags.from ?? 1) + index }}</td>
                            <td data-label="Name">
                                <strong>{{ tag.name }}</strong><br>
                                <small class="text-muted">{{ tag.slug }}</small>
                            </td>
                            <td data-label="Project">{{ tag.project?.name ?? 'Unknown project' }}</td>
                            <td data-label="Issues">
                                <Link
                                    :href="`/issues?tag_id=${tag.id}${tag.project_id ? `&project_id=${tag.project_id}` : ''}`"
                                    class="badge rounded-pill text-bg-light border px-3 py-2 text-decoration-none text-dark hover-shadow"
                                    title="View issues with this tag"
                                >
                                    {{ tag.issues_count ?? 0 }} issue(s)
                                </Link>
                            </td>
                            <td data-label="Created">{{ formatDate(tag.created_at) }}</td>
                            <td data-label="Actions" class="text-end">
                                <div class="table-actions justify-content-end">
                                    <button class="btn btn-sm btn-light rounded-pill" @click="openEdit(tag)">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill" @click="destroyTag(tag)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!tagRows.length">
                            <td colspan="6"><div class="table-empty">No tags found.</div></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="tags.links ?? []" :meta="tags" />
        </section>

        <Modal v-model="createModalOpen" title="Create Tag">
            <form class="vstack gap-3" @submit.prevent="submitCreate">
                <div>
                    <label class="form-label">Project</label>
                    <select v-model="createForm.project_id" class="form-select" :class="{ 'is-invalid-soft': createForm.errors.project_id }">
                        <option value="" disabled>Select a project</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.name }}
                        </option>
                    </select>
                    <FormError :message="createForm.errors.project_id" />
                </div>
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="createForm.name" type="text" class="form-control" :class="{ 'is-invalid-soft': createForm.errors.name }" placeholder="e.g. Backend">
                    <FormError :message="createForm.errors.name" />
                </div>
                <button class="btn btn-accent rounded-pill align-self-start" :disabled="createForm.processing">
                    <span v-if="createForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Create Tag
                </button>
            </form>
        </Modal>

        <Modal v-model="editModalOpen" title="Edit Tag">
            <form class="vstack gap-3" @submit.prevent="submitEdit">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="editForm.name" type="text" class="form-control" :class="{ 'is-invalid-soft': editForm.errors.name }">
                    <FormError :message="editForm.errors.name" />
                </div>
                <button class="btn btn-accent rounded-pill align-self-start" :disabled="editForm.processing">
                    <span v-if="editForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Save Changes
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>