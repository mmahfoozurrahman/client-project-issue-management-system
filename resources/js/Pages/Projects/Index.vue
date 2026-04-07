<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    projects: Array,
    clients: Array,
    breadcrumbs: Array,
});

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

            <div class="simple-card-grid">
                <article v-for="project in projects" :key="project.id" class="simple-card">
                    <div class="simple-card-head">
                        <div>
                            <h4>{{ project.name }}</h4>
                            <p>{{ project.client?.name }} / {{ project.issues_count }} issues</p>
                        </div>
                        <Link :href="`/projects/${project.id}`" class="btn btn-light rounded-pill">Open</Link>
                    </div>
                    <p class="card-muted">{{ project.description || 'No description added yet.' }}</p>
                    <div class="simple-card-actions">
                        <button class="btn btn-outline-dark rounded-pill" @click="openEdit(project)">Edit</button>
                        <Link :href="`/projects/${project.id}`" class="btn btn-outline-secondary rounded-pill">Details</Link>
                        <button class="btn btn-outline-danger rounded-pill" @click="destroyProject(project)">Delete</button>
                    </div>
                </article>
            </div>
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
                    <textarea v-model="form.description" rows="4" class="form-control" :class="{ 'is-invalid-soft': form.errors.description }" />
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
