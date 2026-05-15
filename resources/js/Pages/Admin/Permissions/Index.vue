<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../../Components/FormError.vue';
import Modal from '../../../Components/Modal.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    permissions: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
});

const createModalOpen = ref(false);
const editModalOpen   = ref(false);
const activePerm      = ref(null);

const createForm = useForm({ name: '', slug: '' });
const editForm   = useForm({ name: '', slug: '' });

const openCreate = () => {
    createForm.reset();
    createModalOpen.value = true;
};

const openEdit = (perm) => {
    activePerm.value = perm;
    editForm.name    = perm.name;
    editForm.slug    = perm.slug;
    editModalOpen.value = true;
};

const submitCreate = () => {
    createForm.post('/admin/permissions', {
        onSuccess: () => { createModalOpen.value = false; createForm.reset(); },
    });
};

const submitEdit = () => {
    editForm.put(`/admin/permissions/${activePerm.value.id}`, {
        onSuccess: () => { editModalOpen.value = false; },
    });
};

const destroyPermission = (perm) => {
    Swal.fire({
        title: `Delete "${perm.name}"?`,
        text: 'This will remove the permission from all roles.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            router.delete(`/admin/permissions/${perm.id}`, { preserveScroll: true });
        }
    });
};
</script>

<template>
    <Head title="Permissions" />

    <AppLayout title="Permissions" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Access Control</p>
                    <h3 class="panel-title">Permissions Management</h3>
                </div>
                <button class="btn btn-accent rounded-pill" @click="openCreate">+ Create Permission</button>
            </div>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Roles Count</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(perm, index) in permissions" :key="perm.id">
                            <td>{{ index + 1 }}</td>
                            <td>
                                <strong>{{ perm.name }}</strong><br>
                                <small class="text-muted">{{ perm.slug }}</small>
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-light border px-3 py-2">
                                    {{ perm.roles_count ?? 0 }} role(s)
                                </span>
                            </td>
                            <td>{{ new Date(perm.created_at).toLocaleDateString() }}</td>
                            <td class="text-end">
                                <div class="table-actions justify-content-end">
                                    <button class="btn btn-sm btn-light rounded-pill" @click="openEdit(perm)">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill" @click="destroyPermission(perm)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!permissions.length">
                            <td colspan="5"><div class="table-empty">No permissions found.</div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Modal v-model="createModalOpen" title="Create Permission">
            <form class="vstack gap-3" @submit.prevent="submitCreate">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="createForm.name" type="text" class="form-control" :class="{ 'is-invalid-soft': createForm.errors.name }" placeholder="e.g. Create Issues">
                    <FormError :message="createForm.errors.name" />
                </div>
                <div>
                    <label class="form-label">Slug</label>
                    <input v-model="createForm.slug" type="text" class="form-control" :class="{ 'is-invalid-soft': createForm.errors.slug }" placeholder="e.g. issue.create">
                    <FormError :message="createForm.errors.slug" />
                </div>
                <button class="btn btn-accent rounded-pill align-self-start" :disabled="createForm.processing">
                    <span v-if="createForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Create Permission
                </button>
            </form>
        </Modal>

        <Modal v-model="editModalOpen" title="Edit Permission">
            <form class="vstack gap-3" @submit.prevent="submitEdit">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="editForm.name" type="text" class="form-control" :class="{ 'is-invalid-soft': editForm.errors.name }">
                    <FormError :message="editForm.errors.name" />
                </div>
                <div>
                    <label class="form-label">Slug</label>
                    <input v-model="editForm.slug" type="text" class="form-control" :class="{ 'is-invalid-soft': editForm.errors.slug }">
                    <FormError :message="editForm.errors.slug" />
                </div>
                <button class="btn btn-accent rounded-pill align-self-start" :disabled="editForm.processing">
                    <span v-if="editForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Save Changes
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>
