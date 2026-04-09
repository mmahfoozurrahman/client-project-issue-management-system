<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../../Components/FormError.vue';
import Modal from '../../../Components/Modal.vue';
import Pagination from '../../../Components/Pagination.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    users: Object,
    breadcrumbs: Array,
});

const page = usePage();
const modalOpen = ref(false);
const editingUser = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    is_admin: false,
});

const authUserId = computed(() => page.props.auth?.user?.id);
const submitLabel = computed(() => (editingUser.value ? 'Update User' : 'Create User'));
const userRows = computed(() => props.users?.data ?? props.users ?? []);

const openCreate = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
};

const openEdit = (user) => {
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.is_admin = Boolean(user.is_admin);
    form.clearErrors();
    modalOpen.value = true;
};

const submit = () => {
    if (editingUser.value) {
        form.put(`/admin/users/${editingUser.value.id}`, {
            onSuccess: () => {
                modalOpen.value = false;
                form.reset();
            },
        });
        return;
    }

    form.post('/admin/users', {
        onSuccess: () => {
            modalOpen.value = false;
            form.reset();
        },
    });
};

const destroyUser = (user) => {
    Swal.fire({
        title: `Delete ${user.name}?`,
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            form.delete(`/admin/users/${user.id}`);
        }
    });
};
</script>

<template>
    <Head title="User Management" />

    <AdminLayout title="User Management" :breadcrumbs="breadcrumbs">
        <section class="panel-card">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Super Admin</p>
                    <h3 class="panel-title">Create and manage tenant owners</h3>
                </div>
                <button class="btn btn-accent rounded-pill" @click="openCreate">Add User</button>
            </div>

            <div class="compact-table-shell">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in userRows" :key="user.id">
                            <td data-label="User">
                                <div class="table-entity">
                                    <span class="table-avatar">{{ user.name.slice(0, 1) }}</span>
                                    <div>
                                        <strong>{{ user.name }}</strong>
                                        <small>{{ user.id === authUserId ? 'Current account' : 'Managed tenant owner' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Email">{{ user.email }}</td>
                            <td data-label="Role">
                                <span class="table-pill" :class="{ dark: user.is_admin }">
                                    {{ user.is_admin ? 'Admin' : 'User' }}
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="table-actions">
                                    <button class="btn btn-sm btn-outline-dark rounded-pill" @click="openEdit(user)">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill" :disabled="user.id === authUserId" @click="destroyUser(user)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!userRows.length">
                            <td colspan="4">
                                <div class="table-empty">No users found.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :links="users.links" :meta="users" />
        </section>

        <Modal v-model="modalOpen" :title="submitLabel">
            <form class="vstack gap-3" @submit.prevent="submit">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.name }" required>
                    <FormError :message="form.errors.name" />
                </div>

                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid-soft': form.errors.email }" required>
                    <FormError :message="form.errors.email" />
                </div>

                <div>
                    <label class="form-label">{{ editingUser ? 'New Password (optional)' : 'Password' }}</label>
                    <input v-model="form.password" type="password" class="form-control" :class="{ 'is-invalid-soft': form.errors.password }" :required="!editingUser">
                    <FormError :message="form.errors.password" />
                </div>

                <label class="remember-check">
                    <input v-model="form.is_admin" type="checkbox">
                    <span>Grant super admin access</span>
                </label>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                    {{ submitLabel }}
                </button>
            </form>
        </Modal>
    </AdminLayout>
</template>
