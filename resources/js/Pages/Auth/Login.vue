<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormError from '../../Components/FormError.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const demoAccounts = [
    {
        label: 'Admin Demo',
        email: 'admin1@example.com',
        password: 'password123',
    },
    {
        label: 'User Demo',
        email: 'ariana@example.com',
        password: 'password123',
    },
];

const useDemoCredentials = (account) => {
    form.email = account.email;
    form.password = account.password;
    form.remember = true;
};

const submit = () => form.post('/login');
</script>

<template>
    <Head title="Login" />

    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-copy">
                <span class="pill-tag">Multi-tenant Workspace</span>
                <h1>Sign in to manage clients, projects, and issues.</h1>
                <p>Super Admins can also access the user management area after login.</p>

                <div class="demo-access">
                    <h2>Demo Access</h2>
                    <button
                        v-for="account in demoAccounts"
                        :key="account.email"
                        type="button"
                        class="demo-item"
                        @click="useDemoCredentials(account)"
                    >
                        <strong>{{ account.label }}</strong>
                        <span>{{ account.email }}</span>
                        <small>{{ account.password }}</small>
                    </button>
                </div>
            </div>

            <form class="auth-form" @submit.prevent="submit">
                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-control form-control-lg" :class="{ 'is-invalid-soft': form.errors.email }">
                    <FormError :message="form.errors.email" />
                </div>

                <div>
                    <label class="form-label">Password</label>
                    <input v-model="form.password" type="password" class="form-control form-control-lg" :class="{ 'is-invalid-soft': form.errors.password }">
                    <FormError :message="form.errors.password" />
                </div>

                <label class="remember-check">
                    <input v-model="form.remember" type="checkbox">
                    <span>Remember me</span>
                </label>

                <button class="btn btn-accent btn-lg w-100 rounded-pill" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                    Login
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped>
.demo-access {
    margin-top: 1.25rem;
    display: grid;
    gap: 0.65rem;
}

.demo-access h2 {
    margin: 0;
    font-size: 0.95rem;
    color: #415460;
}

.demo-item {
    text-align: left;
    border: 1px solid #d6e2df;
    background: #f7fcfb;
    border-radius: 0.9rem;
    padding: 0.65rem 0.8rem;
    display: grid;
    gap: 0.1rem;
    transition: 0.2s ease;
}

.demo-item strong {
    color: #173b51;
    font-size: 0.88rem;
}

.demo-item span {
    color: #2a3a4a;
    font-size: 0.84rem;
}

.demo-item small {
    color: #607078;
    font-size: 0.78rem;
}

.demo-item:hover {
    border-color: #9cc8bb;
    background: #eef8f5;
}
</style>
