<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    clients: Array,
    breadcrumbs: Array,
});

const modalOpen = ref(false);
const editingClient = ref(null);

const form = useForm({
    name: '',
    email: '',
});

const submitLabel = computed(() => (editingClient.value ? 'Update Client' : 'Create Client'));

const openCreate = () => {
    editingClient.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
};

const openEdit = (client) => {
    editingClient.value = client;
    form.name = client.name;
    form.email = client.email ?? '';
    form.clearErrors();
    modalOpen.value = true;
};

const submit = () => {
    if (editingClient.value) {
        form.put(`/clients/${editingClient.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
        return;
    }

    form.post('/clients', {
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
        },
    });
};

const destroyClient = (client) => {
    Swal.fire({
        title: `Delete ${client.name}?`,
        text: 'Projects and issues under this client will also be removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            form.delete(`/clients/${client.id}`);
        }
    });
};
</script>

<template>
    <Head title="Clients" />

    <AppLayout title="Clients" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Clients</p>
                    <h3 class="panel-title">Every tenant starts here</h3>
                </div>
                <button class="btn btn-accent rounded-pill" @click="openCreate">Add Client</button>
            </div>

            <div class="simple-card-grid">
                <article v-for="client in clients" :key="client.id" class="simple-card">
                    <div class="simple-card-head">
                        <div>
                            <h4>{{ client.name }}</h4>
                            <p>{{ client.email || 'No email provided' }}</p>
                        </div>
                        <span class="badge text-bg-light">{{ client.projects_count }} projects</span>
                    </div>
                    <div class="simple-card-actions">
                        <button class="btn btn-outline-dark rounded-pill" @click="openEdit(client)">Edit</button>
                        <Link href="/projects" class="btn btn-light rounded-pill">View Projects</Link>
                        <button class="btn btn-outline-danger rounded-pill" @click="destroyClient(client)">Delete</button>
                    </div>
                </article>
            </div>
        </section>

        <Modal v-model="modalOpen" :title="submitLabel">
            <form class="vstack gap-3" @submit.prevent="submit">
                <div>
                    <label class="form-label">Client name</label>
                    <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.name }" required>
                    <FormError :message="form.errors.name" />
                </div>

                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid-soft': form.errors.email }">
                    <FormError :message="form.errors.email" />
                </div>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                    {{ submitLabel }}
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>
