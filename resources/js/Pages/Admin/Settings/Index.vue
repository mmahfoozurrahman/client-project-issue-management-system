<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormError from '../../../Components/FormError.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    settings: Object,
    breadcrumbs: Array,
});

const form = useForm({
    site_name: props.settings?.site_name ?? '',
    issue_daily_target: props.settings?.issue_daily_target ?? 3,
    issue_stale_days: props.settings?.issue_stale_days ?? 7,
    issue_critical_days: props.settings?.issue_critical_days ?? 14,
});

const submit = () => {
    form.put('/admin/settings', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Site Settings" />

    <AdminLayout title="Site Settings" :breadcrumbs="breadcrumbs">
        <section class="panel-card settings-shell">
            <div class="panel-header">
                <div>
                    <p class="section-kicker">Branding</p>
                    <h3 class="panel-title">Control the workspace identity</h3>
                </div>
            </div>

            <div class="settings-grid">
                <div class="settings-preview-card">
                    <span class="pill-tag">Live preview</span>
                    <h4>{{ form.site_name || 'Issue Tracker' }}</h4>
                    <p>This name appears in the top-left sidebar brand area throughout the app.</p>
                </div>

                <form class="vstack gap-3" @submit.prevent="submit">
                    <div>
                        <label class="form-label">Site name</label>
                        <input v-model="form.site_name" type="text" class="form-control" :class="{ 'is-invalid-soft': form.errors.site_name }">
                        <FormError :message="form.errors.site_name" />
                    </div>

                    <div>
                        <label class="form-label">Issue daily target</label>
                        <input v-model.number="form.issue_daily_target" type="number" min="1" max="50" class="form-control" :class="{ 'is-invalid-soft': form.errors.issue_daily_target }">
                        <small class="text-muted d-block mt-1">Used by Kanban "Today&apos;s target" widget. Range: 1 to 50.</small>
                        <FormError :message="form.errors.issue_daily_target" />
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Stale threshold (days)</label>
                            <input v-model.number="form.issue_stale_days" type="number" min="1" max="60" class="form-control" :class="{ 'is-invalid-soft': form.errors.issue_stale_days }">
                            <small class="text-muted d-block mt-1">Issues idle beyond this are treated as at-risk.</small>
                            <FormError :message="form.errors.issue_stale_days" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Critical threshold (days)</label>
                            <input v-model.number="form.issue_critical_days" type="number" min="1" max="120" class="form-control" :class="{ 'is-invalid-soft': form.errors.issue_critical_days }">
                            <small class="text-muted d-block mt-1">Must be greater than or equal to stale threshold.</small>
                            <FormError :message="form.errors.issue_critical_days" />
                        </div>
                    </div>

                    <button class="btn btn-accent rounded-pill align-self-start" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                        Save Settings
                    </button>
                </form>
            </div>
        </section>
    </AdminLayout>
</template>
