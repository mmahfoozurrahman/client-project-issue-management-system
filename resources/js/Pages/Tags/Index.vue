<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    tags: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
});

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const activeTag = ref(null);

const createForm = useForm({ project_id: '', name: '' });
const editForm = useForm({ name: '' });

const defaultProjectId = computed(() => props.projects?.[0]?.id ?? '');

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
                        <tr v-for="(tag, index) in tags" :key="tag.id">
                            <td>{{ index + 1 }}</td>
                            <td>
                                <strong>{{ tag.name }}</strong><br>
                                <small class="text-muted">{{ tag.slug }}</small>
                            </td>
                            <td>{{ tag.project?.name ?? 'Unknown project' }}</td>
                            <td>
                                <span class="badge rounded-pill text-bg-light border px-3 py-2">
                                    {{ tag.issues_count ?? 0 }} issue(s)
                                </span>
                            </td>
                            <td>{{ new Date(tag.created_at).toLocaleDateString() }}</td>
                            <td class="text-end">
                                <div class="table-actions justify-content-end">
                                    <button class="btn btn-sm btn-light rounded-pill" @click="openEdit(tag)">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill" @click="destroyTag(tag)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!tags.length">
                            <td colspan="6"><div class="table-empty">No tags found.</div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
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