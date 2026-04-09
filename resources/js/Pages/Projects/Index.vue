<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import RichTextEditor from '../../Components/RichTextEditor.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    projects: Object,
    clients: Array,
    breadcrumbs: Array,
});

const projectRows = computed(() => props.projects?.data ?? props.projects ?? []);
const plainText = (value) => String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

const modalOpen = ref(false);
const editingProject = ref(null);

const form = useForm({
    name: '',
    description: '',
    client_id: '',
});

const submitLabel = computed(() => (editingProject.value ? 'Update Project' : 'Create Project'));

const openCreate = () => {
    editingProject.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
};

const openEdit = (project) => {
    editingProject.value = project;
    form.name = project.name;
    form.description = project.description ?? '';
    form.client_id = project.client_id;
    form.clearErrors();
    modalOpen.value = true;
};

const submit = () => {
    if (editingProject.value) {
        form.put(`/projects/${editingProject.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
        return;
    }

    form.post('/projects', {
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
        },
    });
};

const destroyProject = (project) => {
    Swal.fire({
        title: `Delete ${project.name}?`,
        text: 'Issues inside this project will also be removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            form.delete(`/projects/${project.id}`);
        }
    });
};
</script>

<template>
    <Head title="Projects" />

    <AppLayout title="Projects" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Projects</p>
                    <h3 class="panel-title">Organize work by client-owned project spaces</h3>
                </div>
                <button class="btn btn-accent rounded-pill" @click="openCreate">Add Project</button>
            </div>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Issues</th>
                            <th>Description</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="project in projectRows" :key="project.id">
                            <td data-label="Project">
                                <div class="table-entity">
                                    <span class="table-avatar alt">{{ project.name.slice(0, 1) }}</span>
                                    <div>
                                        <strong>{{ project.name }}</strong>
                                        <small>Project workspace</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Client">{{ project.client?.name || 'No client' }}</td>
                            <td data-label="Issues"><span class="table-pill">{{ project.issues_count }} issues</span></td>
                            <td data-label="Description" class="table-muted-cell">{{ plainText(project.description) || 'No description added yet.' }}</td>
                            <td data-label="Actions">
                                <div class="table-actions">
                                    <Link :href="`/projects/${project.id}`" class="btn btn-sm btn-light rounded-pill">Open</Link>
                                    <button class="btn btn-sm btn-outline-dark rounded-pill" @click="openEdit(project)">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill" @click="destroyProject(project)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!projectRows.length">
                            <td colspan="5">
                                <div class="table-empty">No projects yet. Create a project and connect it to a client.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="projects.links" :meta="projects" />
        </section>

        <Modal v-model="modalOpen" :title="submitLabel">
            <form class="vstack gap-3" @submit.prevent="submit">
                <div>
                    <label class="form-label">Project name</label>
                    <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.name }" required>
                    <FormError :message="form.errors.name" />
                </div>

                <div>
                    <label class="form-label">Client</label>
                    <select v-model="form.client_id" class="form-select" :class="{ 'is-invalid-soft': form.errors.client_id }" required>
                        <option value="">Select a client</option>
                        <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option>
                    </select>
                    <FormError :message="form.errors.client_id" />
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <RichTextEditor v-model="form.description" :error="form.errors.description" placeholder="Describe goals, scope, and important project notes..." />
                    <FormError :message="form.errors.description" />
                </div>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                    {{ submitLabel }}
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>
