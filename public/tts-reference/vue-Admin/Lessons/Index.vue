<template>
    <AdminLayout :pending-count="0">
        <Head title="লেসন ও নোট ব্যবস্থাপনা" />

        <!-- Header -->
        <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2 animate-fade-in">
            <div>
                <h4 class="fw-700 mb-1" style="color:#1a2e22;"><i class="bi bi-journal-text me-2"></i>লেসন ও নোট ব্যবস্থাপনা</h4>
                <p class="text-muted mb-0" style="font-size:.9rem;">ব্যবহারকারীদের সংরক্ষিত লেসন নোটসমূহ মনিটর করুন</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar mb-3 animate-fade-in">
            <div class="search-wrap flex-grow-1">
                <i class="bi bi-search"></i>
                <input v-model="form.search" type="text" placeholder="লেসনের নাম দিয়ে খুঁজুন..." @keyup.enter="applyFilters">
            </div>
            <select v-model="form.vault_id" class="form-select form-select-sm filter-select">
                <option value="">সকল ভল্ট</option>
                <option v-for="v in vaults" :key="v.id" :value="v.id">{{ v.title }}</option>
            </select>
            <button class="btn-apply" @click="applyFilters"><i class="bi bi-funnel me-1"></i>প্রয়োগ</button>
            <button v-if="hasFilters" class="btn-clear" @click="clearFilters"><i class="bi bi-x"></i></button>
        </div>

        <!-- Table -->
        <div class="admin-card animate-slide-up">
            <div class="admin-card-hd d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-700">লেসন তালিকা</h6>
                <small class="text-muted">মোট {{ toBn(lessons.total) }}টি লেসন</small>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 admin-table">
                    <thead>
                        <tr>
                            <th>লেসন শিরোনাম</th>
                            <th>ভল্ট</th>
                            <th>ফোল্ডার</th>
                            <th>মোট নোটস</th>
                            <th>ইন্টারভিউ প্রশ্ন</th>
                            <th>অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in lessons.data" :key="l.id">
                            <td>
                                <div class="fw-600" style="font-size:.875rem;">{{ l.title }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border py-1 px-2 fw-600" style="font-size: .75rem;">
                                    {{ l.vault_title }}
                                </span>
                            </td>
                            <td class="text-muted" style="font-size:.85rem;">{{ l.folder_title }}</td>
                            <td>
                                <span class="badge" :class="l.notes_count > 0 ? 'bg-success-light text-success' : 'bg-light text-muted'" style="font-size:.78rem; font-weight:600; padding:4px 8px; border-radius:20px;">
                                    {{ toBn(l.notes_count) }}টি নোট
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="l.interview_questions_count > 0 ? 'bg-info-light text-info-emphasis' : 'bg-light text-muted'" style="font-size:.78rem; font-weight:600; padding:4px 8px; border-radius:20px;">
                                    {{ toBn(l.interview_questions_count) }}টি প্রশ্ন
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <Link :href="route('admin.lessons.notes', l.id)" class="btn btn-sm btn-outline-success fw-600 py-1 px-2" style="font-size:.75rem;">
                                        <i class="bi bi-journal-text me-1"></i> নোটস
                                    </Link>
                                    <Link :href="route('admin.lessons.interview-questions.index', l.id)" class="btn btn-sm btn-outline-primary fw-600 py-1 px-2" style="font-size:.75rem;">
                                        <i class="bi bi-question-circle me-1"></i> প্রশ্ন
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!lessons.data.length">
                            <td colspan="6" class="text-center text-muted py-4">কোনো লেসন পাওয়া যায়নি</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="lessons.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
                <small class="text-muted">দেখানো হচ্ছে {{ toBn(lessons.from) }}–{{ toBn(lessons.to) }} মধ্যে {{ toBn(lessons.total) }} এর</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: !lessons.prev_page_url }">
                            <Link :href="lessons.prev_page_url ?? '#'" class="page-link">পূর্ব</Link>
                        </li>
                        <li class="page-item active"><span class="page-link">{{ toBn(lessons.current_page) }}</span></li>
                        <li class="page-item" :class="{ disabled: !lessons.next_page_url }">
                            <Link :href="lessons.next_page_url ?? '#'" class="page-link">পরবর্তী</Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    lessons: { type: Object, default: () => ({ data: [], total: 0 }) },
    vaults:  { type: Array,  default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({
    search:   props.filters.search   ?? '',
    vault_id: props.filters.vault_id ?? '',
});

const hasFilters = computed(() => form.search || form.vault_id);

function applyFilters() {
    router.get(route('admin.lessons.index'), form, { preserveState: true, replace: true });
}

function clearFilters() {
    form.search = '';
    form.vault_id = '';
    applyFilters();
}

function toBn(val) {
    const en = ['0','1','2','3','4','5','6','7','8','9'];
    const bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    return String(val ?? 0).replace(/[0-9]/g, d => bn[en.indexOf(d)]);
}
</script>

<style scoped>
.filter-bar { display:flex;align-items:center;gap:.5rem;flex-wrap:wrap; }
.search-wrap { position:relative;min-width:200px; }
.search-wrap i { position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#9ca3af; }
.search-wrap input { width:100%;padding:.42rem .75rem .42rem 2.1rem;border:1px solid #e2e8f0;border-radius:7px;font-size:.875rem;outline:none; }
.search-wrap input:focus { border-color:#2d6a4f; }
.filter-select { width:auto;min-width:150px;height:36px;font-size:.875rem; }
.btn-apply { background:#2d6a4f;color:#fff;border:none;border-radius:7px;padding:.4rem .9rem;font-size:.875rem;font-weight:600;cursor:pointer;white-space:nowrap; }
.btn-apply:hover { background:#245a42; }
.btn-clear { background:#f1f5f9;border:1px solid #e2e8f0;border-radius:7px;padding:.4rem .6rem;font-size:.875rem;cursor:pointer;color:#6b7280; }

.admin-card { background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden; }
.admin-card-hd { padding:.9rem 1.25rem;border-bottom:1px solid #f1f5f9; }

.admin-table th { font-size:.78rem;font-weight:600;color:#6b7280;background:#f8fafc;padding:.6rem 1rem;white-space:nowrap; }
.admin-table td { padding:.65rem 1rem;vertical-align:middle; }
.admin-table tbody tr:hover { background:#f8fafc; }

.bg-success-light { background-color: #dcfce7; }
.bg-info-light { background-color: #e0f2fe; }
</style>
