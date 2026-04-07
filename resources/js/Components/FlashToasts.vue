<script setup>
import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const page = usePage();
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
const validationSummary = computed(() => {
    const errors = page.props.errors ?? {};
    const firstKey = Object.keys(errors)[0];

    return firstKey ? errors[firstKey] : null;
});

watch(success, (value) => {
    if (!value) return;

    Swal.fire({
        toast: true,
        position: 'top-end',
        timer: 2600,
        showConfirmButton: false,
        icon: 'success',
        title: value,
    });
}, { immediate: true });

watch(error, (value) => {
    if (!value) return;

    Swal.fire({
        toast: true,
        position: 'top-end',
        timer: 3200,
        showConfirmButton: false,
        icon: 'error',
        title: value,
    });
}, { immediate: true });

watch(validationSummary, (value) => {
    if (!value) return;

    Swal.fire({
        toast: true,
        position: 'top-end',
        timer: 3200,
        showConfirmButton: false,
        icon: 'error',
        title: value,
    });
}, { immediate: true });
</script>

<template>
    <span class="d-none" />
</template>
