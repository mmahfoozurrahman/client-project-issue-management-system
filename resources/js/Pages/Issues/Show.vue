<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import FormError from '../../Components/FormError.vue';
import Modal from '../../Components/Modal.vue';
import RichTextEditor from '../../Components/RichTextEditor.vue';
import StatusPill from '../../Components/StatusPill.vue';
import IssueTree from '../../Components/IssueTree.vue';
import IssueQuickReadModal from '../../Components/IssueQuickReadModal.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDate } from '../../utils/date';
import { convertBlobToJpegFile, getScreenshotFilename, formatFileSize } from '../../utils/image';

const props = defineProps({
    issue: Object,
    projects: Array,
    projectIssues: Array,
    parentIssueOptions: Array,
    projectTags: Array,
    matchingIssues: { type: Array, default: () => [] },
    breadcrumbs: Array,
    userProjectRole:     { type: String,  default: null },
    canCreate:           { type: Boolean, default: false },
    canEdit:             { type: Boolean, default: false },
    canDelete:           { type: Boolean, default: false },
    canChangeStatus:     { type: Boolean, default: false },
    canUploadAttachment: { type: Boolean, default: false },
    canDeleteAttachment: { type: Boolean, default: false },
});

const nestedIssues = computed(() => props.issue.sub_issues ?? props.issue.subIssues ?? []);
const parentIssue = computed(() => props.issue.parent_issue ?? props.issue.parentIssue ?? null);
const issueImages = ref([...(props.issue.images ?? [])]);
const issueFiles = ref([...(props.issue.files ?? [])]);
const issueLinks = ref([...(props.issue.links ?? [])]);
const issueTags = ref([...(props.issue.tags ?? [])]);
const isDoneIssue = computed(() => props.issue.status === 'done');
const formatMetaDate = (value) => (value ? formatDate(value, null) : null);
const createdDateLabel = computed(() => formatMetaDate(props.issue.created_at));
const completedDateLabel = computed(() => (isDoneIssue.value ? formatMetaDate(props.issue.done_at) : null));

const updateForm = useForm({
    title: props.issue.title,
    description: props.issue.description ?? '',
    status: props.issue.status,
    project_id: props.issue.project_id,
    parent_id: props.issue.parent_id ?? '',
    images: [],
    files: [],
    links: (props.issue.links ?? []).map((link) => ({
        label: link.label ?? '',
        url: link.url ?? '',
    })),
    tag_names: (props.issue.tags ?? []).map((tag) => tag.name),
});
const deleteForm = useForm({});
const quickReadModalOpen = ref(false);
const activeQuickReadIssue = ref(null);

const openQuickRead = (issue) => {
    activeQuickReadIssue.value = issue;
    quickReadModalOpen.value = true;
};

const childModalOpen = ref(false);
const childForm = useForm({
    title: '',
    description: '',
    status: 'todo',
    project_id: props.issue.project_id,
    parent_id: props.issue.id,
    return_to_issue_id: props.issue.id,
    images: [],
    files: [],
    links: [],
    tag_names: [],
});
const childIssueParent = computed(() => props.projectIssues.find((entry) => entry.id === childForm.parent_id) ?? props.issue);
const imageModalOpen = ref(false);
const activeImageIndex = ref(0);
const updateTagInput = ref('');
const childTagInput = ref('');
const updateTagSuggestions = computed(() => {
    if (!updateTagInput.value.trim()) return [];
    const query = updateTagInput.value.trim().toLowerCase();
    return (props.projectTags ?? []).filter(
        (tag) => tag.name.toLowerCase().includes(query) && !updateForm.tag_names.some((t) => t.toLowerCase() === tag.name.toLowerCase())
    );
});
const childTagSuggestions = computed(() => {
    if (!childTagInput.value.trim()) return [];
    const query = childTagInput.value.trim().toLowerCase();
    return (props.projectTags ?? []).filter(
        (tag) => tag.name.toLowerCase().includes(query) && !childForm.tag_names.some((t) => t.toLowerCase() === tag.name.toLowerCase())
    );
});
const stagedUpdateImages = ref([]);
const stagedChildImages = ref([]);
const updateFileInputRef = ref(null);
const childFileInputRef = ref(null);
const isUploadingDirectly = ref(false);

const clearStagedUpdateImages = () => {
    stagedUpdateImages.value.forEach((item) => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
    });
    stagedUpdateImages.value = [];
    updateForm.images = [];
    if (updateFileInputRef.value) {
        updateFileInputRef.value.value = '';
    }
};

const clearStagedChildImages = () => {
    stagedChildImages.value.forEach((item) => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
    });
    stagedChildImages.value = [];
    childForm.images = [];
    if (childFileInputRef.value) {
        childFileInputRef.value.value = '';
    }
};

const removeStagedUpdateImage = (index) => {
    const item = stagedUpdateImages.value[index];
    if (item?.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
    }
    stagedUpdateImages.value.splice(index, 1);
    updateForm.images = stagedUpdateImages.value.map((i) => i.file);
};

const removeStagedChildImage = (index) => {
    const item = stagedChildImages.value[index];
    if (item?.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
    }
    stagedChildImages.value.splice(index, 1);
    childForm.images = stagedChildImages.value.map((i) => i.file);
};

const uploadStagedImagesNow = () => {
    if (!stagedUpdateImages.value.length || isUploadingDirectly.value) return;

    isUploadingDirectly.value = true;
    const formData = new FormData();
    stagedUpdateImages.value.forEach((item) => {
        formData.append('images[]', item.file);
    });

    router.post(`/issues/${props.issue.id}/images`, formData, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            clearStagedUpdateImages();
            isUploadingDirectly.value = false;
        },
        onError: (errors) => {
            isUploadingDirectly.value = false;
            Swal.fire({
                toast: true,
                position: 'top-end',
                timer: 3500,
                showConfirmButton: false,
                icon: 'error',
                title: Object.values(errors)[0] || 'Failed to upload image.',
            });
        },
    });
};

const uploadDirectFiles = (files) => {
    if (!files.length || isUploadingDirectly.value) return;

    isUploadingDirectly.value = true;
    const formData = new FormData();
    files.forEach((file) => {
        formData.append('images[]', file);
    });

    router.post(`/issues/${props.issue.id}/images`, formData, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isUploadingDirectly.value = false;
        },
        onError: (errors) => {
            isUploadingDirectly.value = false;
            Swal.fire({
                toast: true,
                position: 'top-end',
                timer: 3500,
                showConfirmButton: false,
                icon: 'error',
                title: Object.values(errors)[0] || 'Failed to upload image.',
            });
        },
    });
};

const handlePaste = async (event) => {
    if (!props.canEdit && !props.canUploadAttachment && !props.canCreate) {
        return;
    }

    const clipboardData = event.clipboardData || window.clipboardData;
    if (!clipboardData) return;

    const items = Array.from(clipboardData.items || []);
    const imageItems = items.filter(
        (item) => item.kind === 'file' && item.type && item.type.startsWith('image/')
    );

    if (imageItems.length === 0) {
        return;
    }

    event.preventDefault();

    const newStagedList = [];
    let counter = 1;

    for (const item of imageItems) {
        const rawBlob = item.getAsFile();
        if (!rawBlob) continue;

        const filename = getScreenshotFilename(counter, imageItems.length);
        counter++;

        const jpegFile = await convertBlobToJpegFile(rawBlob, filename);
        newStagedList.push({
            id: 'paste_' + Math.random().toString(36).substring(2, 9),
            file: jpegFile,
            previewUrl: URL.createObjectURL(jpegFile),
            isPasted: true,
        });
    }

    if (!newStagedList.length) return;

    if (childModalOpen.value) {
        stagedChildImages.value.push(...newStagedList);
        childForm.images = stagedChildImages.value.map((i) => i.file);
        Swal.fire({
            toast: true,
            position: 'top-end',
            timer: 2600,
            showConfirmButton: false,
            icon: 'success',
            title: newStagedList.length > 1
                ? `📸 ${newStagedList.length} screenshots added to sub-issue (JPEG)`
                : '📸 Screenshot added to sub-issue (JPEG)',
        });
    } else if (props.canEdit) {
        stagedUpdateImages.value.push(...newStagedList);
        updateForm.images = stagedUpdateImages.value.map((i) => i.file);
        Swal.fire({
            toast: true,
            position: 'top-end',
            timer: 2800,
            showConfirmButton: false,
            icon: 'success',
            title: newStagedList.length > 1
                ? `📸 ${newStagedList.length} screenshots ready to upload (JPEG)`
                : '📸 Screenshot captured as JPEG! Ready to upload',
        });
    } else if (props.canUploadAttachment) {
        uploadDirectFiles(newStagedList.map((i) => i.file));
    }
};

onMounted(() => {
    window.addEventListener('paste', handlePaste);
});

onBeforeUnmount(() => {
    window.removeEventListener('paste', handlePaste);
    clearStagedUpdateImages();
    clearStagedChildImages();
});

const submit = () => {
    updateForm.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(`/issues/${props.issue.id}`, {
        forceFormData: true,
        onSuccess: () => {
            clearStagedUpdateImages();
        },
    });
};

const destroyIssue = () => {
    Swal.fire({
        title: `Delete ${props.issue.title}?`,
        text: 'This will remove the issue and its nested structure.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (isConfirmed) {
            deleteForm.delete(`/issues/${props.issue.id}`);
        }
    });
};

const togglePin = () => {
    router.post(`/issues/${props.issue.id}/pin`, {}, { preserveScroll: true });
};

const onImageChange = async (event) => {
    const files = Array.from(event.target.files || []);
    for (const file of files) {
        let finalFile = file;
        if (file.type !== 'image/jpeg' && file.type.startsWith('image/')) {
            finalFile = await convertBlobToJpegFile(file, file.name);
        }
        stagedUpdateImages.value.push({
            id: 'file_' + Math.random().toString(36).substring(2, 9),
            file: finalFile,
            previewUrl: URL.createObjectURL(finalFile),
            isPasted: false,
        });
    }
    updateForm.images = stagedUpdateImages.value.map((i) => i.file);
    if (updateFileInputRef.value) {
        updateFileInputRef.value.value = '';
    }
};

const onImageDrop = async (event) => {
    const files = Array.from(event.dataTransfer?.files || []).filter(
        (f) => f.type && f.type.startsWith('image/')
    );
    if (!files.length) return;

    for (const file of files) {
        let finalFile = file;
        if (file.type !== 'image/jpeg') {
            finalFile = await convertBlobToJpegFile(file, file.name);
        }
        stagedUpdateImages.value.push({
            id: 'drop_' + Math.random().toString(36).substring(2, 9),
            file: finalFile,
            previewUrl: URL.createObjectURL(finalFile),
            isPasted: false,
        });
    }
    updateForm.images = stagedUpdateImages.value.map((i) => i.file);
};

const onFileChange = (event) => {
    updateForm.files = Array.from(event.target.files || []);
};

const onChildImageChange = async (event) => {
    const files = Array.from(event.target.files || []);
    for (const file of files) {
        let finalFile = file;
        if (file.type !== 'image/jpeg' && file.type.startsWith('image/')) {
            finalFile = await convertBlobToJpegFile(file, file.name);
        }
        stagedChildImages.value.push({
            id: 'child_file_' + Math.random().toString(36).substring(2, 9),
            file: finalFile,
            previewUrl: URL.createObjectURL(finalFile),
            isPasted: false,
        });
    }
    childForm.images = stagedChildImages.value.map((i) => i.file);
    if (childFileInputRef.value) {
        childFileInputRef.value.value = '';
    }
};

const onChildFileChange = (event) => {
    childForm.files = Array.from(event.target.files || []);
};

const addUpdateLink = () => {
    updateForm.links.push({ label: '', url: '' });
};

const removeUpdateLink = (index) => {
    updateForm.links.splice(index, 1);
};

const addChildLink = () => {
    childForm.links.push({ label: '', url: '' });
};

const removeChildLink = (index) => {
    childForm.links.splice(index, 1);
};

const addUpdateTag = (value = updateTagInput.value) => {
    const normalized = String(value || '').trim();
    if (!normalized) return;
    const exists = updateForm.tag_names.some((entry) => entry.toLowerCase() === normalized.toLowerCase());
    if (!exists) {
        updateForm.tag_names.push(normalized);
    }
    updateTagInput.value = '';
};

const removeUpdateTag = (index) => {
    updateForm.tag_names.splice(index, 1);
};

const addChildTag = (value = childTagInput.value) => {
    const normalized = String(value || '').trim();
    if (!normalized) return;
    const exists = childForm.tag_names.some((entry) => entry.toLowerCase() === normalized.toLowerCase());
    if (!exists) {
        childForm.tag_names.push(normalized);
    }
    childTagInput.value = '';
};

const removeChildTag = (index) => {
    childForm.tag_names.splice(index, 1);
};

const openChildModal = (parentIssue = props.issue) => {
    childModalOpen.value = true;
    childForm.reset();
    childForm.clearErrors();
    childForm.title = '';
    childForm.description = '';
    childForm.status = 'todo';
    childForm.project_id = props.issue.project_id;
    childForm.parent_id = parentIssue.id;
    childForm.return_to_issue_id = props.issue.id;
    childForm.tag_names = [];
};

const resetChildForm = () => {
    childModalOpen.value = false;
    clearStagedChildImages();
    childForm.reset();
    childForm.clearErrors();
    childForm.project_id = props.issue.project_id;
    childForm.parent_id = null;
    childForm.return_to_issue_id = props.issue.id;
};

const submitChild = () => {
    childForm.post('/issues', {
        forceFormData: true,
        onSuccess: () => {
            resetChildForm();
        },
    });
};

watch(
    () => props.issue.images,
    (next) => {
        issueImages.value = [...(next ?? [])];
    },
    { immediate: true }
);

watch(
    () => props.issue.files,
    (next) => {
        issueFiles.value = [...(next ?? [])];
    },
    { immediate: true }
);

watch(
    () => props.issue.links,
    (next) => {
        issueLinks.value = [...(next ?? [])];
        updateForm.links = (next ?? []).map((link) => ({
            label: link.label ?? '',
            url: link.url ?? '',
        }));
    },
    { immediate: true }
);

watch(
    () => props.issue.tags,
    (next) => {
        issueTags.value = [...(next ?? [])];
        updateForm.tag_names = (next ?? []).map((tag) => tag.name);
    },
    { immediate: true }
);

const modalFeedRef = ref(null);

const scrollToModalImage = (index) => {
    activeImageIndex.value = index;
    const feed = modalFeedRef.value;
    const target = document.getElementById(`modal-image-entry-${index}`);
    if (feed && target) {
        feed.scrollTo({
            top: target.offsetTop - feed.offsetTop - 10,
            behavior: 'smooth',
        });
    } else if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const openImageModal = (index = 0) => {
    activeImageIndex.value = index;
    imageModalOpen.value = true;
    nextTick(() => {
        setTimeout(() => {
            const feed = modalFeedRef.value;
            const target = document.getElementById(`modal-image-entry-${index}`);
            if (feed && target) {
                feed.scrollTo({
                    top: target.offsetTop - feed.offsetTop - 10,
                    behavior: 'auto',
                });
            } else if (target) {
                target.scrollIntoView({ behavior: 'auto', block: 'start' });
            }
        }, 100);
    });
};

const handleModalFeedScroll = (event) => {
    const feed = event.target;
    if (!feed) return;
    const cards = feed.querySelectorAll('.image-feed-card');
    const feedTop = feed.scrollTop;
    cards.forEach((card, idx) => {
        if (card.offsetTop - feed.offsetTop <= feedTop + 140) {
            activeImageIndex.value = idx;
        }
    });
};

const deleteImage = (image) => {
    Swal.fire({
        title: 'Delete image?',
        text: 'This will remove the image from this issue.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;

        router.delete(`/issues/images/${image.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                issueImages.value = issueImages.value.filter((entry) => entry.id !== image.id);
                if (issueImages.value.length === 0) {
                    imageModalOpen.value = false;
                } else if (activeImageIndex.value >= issueImages.value.length) {
                    activeImageIndex.value = Math.max(issueImages.value.length - 1, 0);
                }
            },
        });
    });
};

const deleteFile = (file) => {
    Swal.fire({
        title: 'Delete file?',
        text: 'This will remove the file from this issue.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;

        router.delete(`/issues/files/${file.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                issueFiles.value = issueFiles.value.filter((entry) => entry.id !== file.id);
            },
        });
    });
};

const deleteLink = (link) => {
    Swal.fire({
        title: 'Delete link?',
        text: 'This will remove the link from this issue.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;

        router.delete(`/issues/links/${link.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                issueLinks.value = issueLinks.value.filter((entry) => entry.id !== link.id);
            },
        });
    });
};
</script>

<template>
    <Head :title="issue.title" />

    <AppLayout :title="issue.title" :breadcrumbs="breadcrumbs">
        <section class="hero-panel issue-hero-panel mb-4">
            <div class="issue-hero-copy">
                <span class="pill-tag">Issue Detail</span>
                <h3 class="issue-hero-title">{{ issue.title }}</h3>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span v-if="createdDateLabel" class="badge rounded-pill text-bg-light border">
                        Created {{ createdDateLabel }}
                    </span>
                    <span v-if="completedDateLabel" class="badge rounded-pill border" style="background:#e6f5ee; color:#176848; border-color:#b7e3cc !important;">
                        Completed {{ completedDateLabel }}
                    </span>
                </div>
                <!-- <div v-if="issue.description" class="hero-copy rich-display" v-html="issue.description" />
                <p v-else class="hero-copy">No description added yet.</p> -->
            </div>
            <div class="project-meta-block issue-hero-actions">
                <div class="issue-hero-actions-top">
                    <button
                        v-if="canCreate"
                        class="btn rounded-pill issue-hero-action-btn issue-hero-add-btn"
                        @click="openChildModal(issue)"
                    >
                        Add Sub-Issue
                    </button>
                    <StatusPill :status="issue.status" class="issue-hero-status" />
                    <button
                        type="button"
                        class="btn rounded-pill issue-hero-action-btn issue-hero-add-btn"
                        @click="togglePin"
                    >
                        {{ issue.is_pinned ? 'Unpin Issue' : 'Pin Issue' }}
                    </button>
                    <button
                        v-if="canDelete"
                        class="btn rounded-pill issue-hero-action-btn issue-delete-btn"
                        @click="destroyIssue"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </section>

        <section class="issue-context-strip">
            <div class="context-stat">
                <span>Client</span>
                <strong>{{ issue.project?.client?.name || 'No client' }}</strong>
            </div>
            <div class="context-stat">
                <span>Project</span>
                <strong>{{ issue.project?.name || 'No project' }}</strong>
            </div>
            <div class="context-stat">
                <span>Parent Issue</span>
                <strong v-if="parentIssue">
                    <Link :href="`/issues/${parentIssue.id}`">{{ parentIssue.title }}</Link>
                </strong>
                <strong v-else>Top-level issue</strong>
            </div>
            <div class="context-stat accent">
                <span>Nested Items</span>
                <strong>{{ nestedIssues.length }}</strong>
            </div>
            <div class="context-stat">
                <span>Tags</span>
                <div v-if="issueTags.length" class="d-flex flex-wrap gap-1 mt-1">
                    <Link
                        v-for="tag in issueTags"
                        :key="tag.id"
                        :href="`/issues?tag_id=${tag.id}&project_id=${issue.project_id}`"
                        class="badge rounded-pill text-bg-light border text-decoration-none"
                    >
                        {{ tag.name }}
                    </Link>
                </div>
                <strong v-else>No tags</strong>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <section v-if="canEdit" class="panel-card h-100">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Edit Issue</p>
                            <h4 class="panel-title">Update details, parent links, and attachments</h4>
                        </div>
                    </div>

                    <form class="vstack gap-3" @submit.prevent="submit">
                        <div>
                            <label class="form-label">Title</label>
                            <input v-model="updateForm.title" type="text" class="form-control" :class="{ 'is-invalid-soft': updateForm.errors.title }">
                            <FormError :message="updateForm.errors.title" />
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Project</label>
                                <select v-model="updateForm.project_id" class="form-select" :class="{ 'is-invalid-soft': updateForm.errors.project_id }">
                                    <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                                </select>
                                <FormError :message="updateForm.errors.project_id" />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select v-model="updateForm.status" class="form-select" :class="{ 'is-invalid-soft': updateForm.errors.status }">
                                    <option value="todo">Todo</option>
                                    <option value="inprogress">In Progress</option>
                                    <option value="done">Done</option>
                                </select>
                                <FormError :message="updateForm.errors.status" />
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Parent Issue</label>
                                <select v-model="updateForm.parent_id" class="form-select" :class="{ 'is-invalid-soft': updateForm.errors.parent_id }">
                                    <option value="">No parent</option>
                                    <option v-for="parent in parentIssueOptions" :key="parent.id" :value="parent.id">{{ parent.label }}</option>
                                </select>
                                <small v-if="!parentIssueOptions.length" class="text-muted d-block mt-2">No valid parent issues available. This issue already sits at the top of its branch.</small>
                                <FormError :message="updateForm.errors.parent_id" />
                            </div>

                        <div>
                            <label class="form-label">Description</label>
                            <RichTextEditor v-model="updateForm.description" :error="updateForm.errors.description" placeholder="Capture formatted details, notes, links, and acceptance context..." />
                            <FormError :message="updateForm.errors.description" />
                        </div>

                        <div @dragover.prevent @drop.prevent="onImageDrop">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label mb-0">Add Images</label>
                                <span class="badge text-bg-light border text-muted small">
                                    💡 Tip: Press <kbd>Ctrl+V</kbd> to paste screenshot (JPEG)
                                </span>
                            </div>
                            <input
                                ref="updateFileInputRef"
                                type="file"
                                multiple
                                accept=".jpg,.jpeg,.png"
                                class="form-control"
                                :class="{ 'is-invalid-soft': updateForm.errors.images || updateForm.errors['images.0'] }"
                                @change="onImageChange"
                            >
                            <FormError :message="updateForm.errors.images || updateForm.errors['images.0']" />

                            <div v-if="stagedUpdateImages.length" class="staged-images-container mt-2 p-2 border rounded bg-light">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small font-monospace text-muted fw-semibold">
                                        {{ stagedUpdateImages.length }} image{{ stagedUpdateImages.length > 1 ? 's' : '' }} staged
                                    </span>
                                    <div class="d-flex gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-accent rounded-pill px-3 d-inline-flex align-items-center gap-1"
                                            :disabled="isUploadingDirectly"
                                            @click="uploadStagedImagesNow"
                                        >
                                            <span v-if="isUploadingDirectly" class="spinner-border spinner-border-sm me-1" />
                                            <span>Upload Now</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary rounded-pill"
                                            :disabled="isUploadingDirectly"
                                            @click="clearStagedUpdateImages"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                </div>

                                <div class="staged-images-grid">
                                    <div
                                        v-for="(item, idx) in stagedUpdateImages"
                                        :key="item.id"
                                        class="staged-image-card"
                                    >
                                        <img :src="item.previewUrl" :alt="item.file.name" class="staged-image-thumb">
                                        <div class="staged-image-info">
                                            <span class="staged-image-name text-truncate" :title="item.file.name">{{ item.file.name }}</span>
                                            <div class="d-flex align-items-center justify-content-between mt-1">
                                                <span class="badge rounded-pill bg-white border text-dark font-monospace extra-small">
                                                    {{ formatFileSize(item.file.size) }}
                                                </span>
                                                <span v-if="item.isPasted" class="badge rounded-pill text-bg-success extra-small">
                                                    Pasted (JPEG)
                                                </span>
                                                <span v-else class="badge rounded-pill text-bg-secondary extra-small">
                                                    JPEG
                                                </span>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="staged-image-remove-btn"
                                            title="Remove image"
                                            @click="removeStagedUpdateImage(idx)"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Add Files</label>
                            <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.rtf,.ppt,.pptx,.zip,.rar" class="form-control" :class="{ 'is-invalid-soft': updateForm.errors.files || updateForm.errors['files.0'] }" @change="onFileChange">
                            <FormError :message="updateForm.errors.files || updateForm.errors['files.0']" />
                        </div>

                        <div>
                            <label class="form-label">Links</label>
                            <div v-if="!updateForm.links.length" class="text-muted small mb-2">Add internal or external links for this issue.</div>
                            <div v-for="(link, index) in updateForm.links" :key="index" class="row g-2 align-items-center mb-2">
                                <div class="col-5">
                                    <input v-model="link.label" type="text" class="form-control" placeholder="Label (optional)" :class="{ 'is-invalid-soft': updateForm.errors[`links.${index}.label`] }">
                                </div>
                                <div class="col-6">
                                    <input v-model="link.url" type="text" class="form-control" placeholder="https:// or /internal/path" :class="{ 'is-invalid-soft': updateForm.errors[`links.${index}.url`] }">
                                </div>
                                <div class="col-1 d-grid">
                                    <button type="button" class="btn btn-outline-danger" @click="removeUpdateLink(index)">×</button>
                                </div>
                                <div class="col-12">
                                    <FormError :message="updateForm.errors[`links.${index}.url`] || updateForm.errors[`links.${index}.label`]" />
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="addUpdateLink">+ Add Link</button>
                        </div>

                        <div>
                            <label class="form-label">Tags</label>
                            <small class="text-muted d-block mb-2">Project-based tags for search and categorization.</small>
                            <div class="d-flex gap-2 mb-2 position-relative">
                                <input
                                    v-model="updateTagInput"
                                    type="text"
                                    class="form-control"
                                    placeholder="Type tag and press Enter"
                                    @keyup.enter.prevent="addUpdateTag()"
                                    autocomplete="off"
                                >
                                <button type="button" class="btn btn-outline-secondary" @click="addUpdateTag()">Add</button>
                                <div v-if="updateTagSuggestions.length" class="position-absolute top-100 start-0 mt-1 w-100 bg-white border rounded shadow-sm" style="z-index: 1000; max-height: 200px; overflow-y: auto;">
                                    <button
                                        v-for="tag in updateTagSuggestions"
                                        :key="tag.id"
                                        type="button"
                                        class="w-100 text-start px-3 py-2 border-0 bg-white text-decoration-none d-flex align-items-center gap-2"
                                        style="cursor: pointer; font-size: 0.9rem;"
                                        @click.prevent="addUpdateTag(tag.name)"
                                        @mouseover="$event.currentTarget.style.backgroundColor = '#f5f5f5'"
                                        @mouseout="$event.currentTarget.style.backgroundColor = 'white'"
                                    >
                                        <span class="badge bg-light text-dark">{{ tag.name }}</span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="updateForm.tag_names.length" class="d-flex flex-wrap gap-1">
                                <span v-for="(tag, index) in updateForm.tag_names" :key="`${tag}-${index}`" class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1 px-2 py-1">
                                    {{ tag }}
                                    <button type="button" class="btn btn-sm p-0 border-0 bg-transparent" @click="removeUpdateTag(index)">x</button>
                                </span>
                            </div>
                            <FormError :message="updateForm.errors.tag_names || updateForm.errors['tag_names.0']" />
                        </div>

                        <button class="btn btn-accent rounded-pill align-self-start" :disabled="updateForm.processing">
                            <span v-if="updateForm.processing" class="spinner-border spinner-border-sm me-2" />
                            Save Changes
                        </button>
                    </form>
                </section>

                <section v-else-if="canChangeStatus" class="panel-card h-100">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Issue Status</p>
                            <h3 class="panel-title">Update the current status of this issue</h3>
                        </div>
                    </div>
                    <form class="vstack gap-3" @submit.prevent="$inertia.patch(`/issues/${issue.id}/status`, { status: updateForm.status })">
                        <div>
                            <label class="form-label">Status</label>
                            <select v-model="updateForm.status" class="form-select">
                                <option value="todo">Todo</option>
                                <option value="inprogress">In Progress</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                        <button class="btn btn-accent rounded-pill align-self-start">Update Status</button>
                    </form>
                </section>
            </div>

            <div class="col-12 col-xl-5">
                <section class="panel-card mb-4">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Matching Tags</p>
                            <h4 class="panel-title">Related issues</h4>
                        </div>
                        <span v-if="issueTags.length" class="badge rounded-pill text-bg-light border">
                            {{ issueTags.length }} {{ issueTags.length === 1 ? 'tag' : 'tags' }} on this issue
                        </span>
                    </div>

                    <div v-if="!matchingIssues.length" class="empty-state-card">
                        <strong>No related issues yet</strong>
                        <p>Issues in this project that share all of these tags will appear here.</p>
                    </div>

                    <div v-else class="list-group list-group-flush">
                        <div
                            v-for="relatedIssue in matchingIssues"
                            :key="relatedIssue.id"
                            class="list-group-item d-flex justify-content-between align-items-start gap-3"
                        >
                            <div class="min-w-0 flex-grow-1">
                                <Link :href="`/issues/${relatedIssue.id}`" class="fw-semibold text-decoration-none text-dark d-block text-truncate">
                                    {{ relatedIssue.title }}
                                </Link>
                                <div v-if="(relatedIssue.matching_tags || relatedIssue.tags)?.length" class="d-flex flex-wrap gap-1 mt-2">
                                    <Link v-for="tag in (relatedIssue.matching_tags || relatedIssue.tags)" :key="tag.id" :href="`/issues?tag_id=${tag.id}&project_id=${relatedIssue.project_id}`" class="badge rounded-pill text-bg-light border text-decoration-none">
                                        {{ tag.name }}
                                    </Link>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <StatusPill :status="relatedIssue.status" size="sm" />
                                    <button type="button" class="btn btn-sm btn-light rounded-pill" @click="openQuickRead(relatedIssue)">Quick read</button>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    {{ relatedIssue.matching_tag_count }} {{ relatedIssue.matching_tag_count === 1 ? 'matching tag' : 'matching tags' }}
                                </small>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel-card mb-4">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Gallery</p>
                            <h4 class="panel-title">Attached screenshots and references</h4>
                        </div>
                    </div>

                    <div class="gallery-grid">
                        <div v-for="(image, index) in issueImages" :key="image.id" class="position-relative">
                            <img
                                :src="image.url"
                                :alt="image.original_name || issue.title"
                                class="gallery-thumb"
                                role="button"
                                tabindex="0"
                                @click="openImageModal(index)"
                            >
                            <button v-if="canDeleteAttachment" type="button" class="btn btn-light btn-sm position-absolute top-0 end-0 m-2" @click.stop="deleteImage(image)">Delete</button>
                        </div>
                    </div>
                </section>

                <section class="panel-card mb-4">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Attachments</p>
                            <h4 class="panel-title">Supporting files for this issue</h4>
                        </div>
                    </div>

                    <div v-if="!issueFiles.length" class="empty-state-card">
                        <strong>No files attached</strong>
                        <p>Add PDFs, spreadsheets, notes, or archives from the edit form.</p>
                    </div>

                    <div v-else class="list-group list-group-flush">
                        <div v-for="file in issueFiles" :key="file.id" class="list-group-item d-flex justify-content-between align-items-center">
                            <a :href="file.url" target="_blank" rel="noopener noreferrer" class="text-decoration-none">{{ file.original_name || 'Attachment' }}</a>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted">{{ formatFileSize(file.size) }}</small>
                                <button v-if="canDeleteAttachment" type="button" class="btn btn-outline-danger btn-sm" @click="deleteFile(file)">Delete</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel-card mb-4">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Links</p>
                            <h4 class="panel-title">Internal and external references</h4>
                        </div>
                    </div>

                    <div v-if="!issueLinks.length" class="empty-state-card">
                        <strong>No links added</strong>
                        <p>Add internal or external links from the edit form.</p>
                    </div>

                    <div v-else class="list-group list-group-flush">
                        <div v-for="link in issueLinks" :key="link.id" class="list-group-item d-flex justify-content-between align-items-center">
                            <a :href="link.url" target="_blank" rel="noopener noreferrer" class="text-decoration-none">{{ link.label || link.url }}</a>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted">{{ link.is_external ? 'External' : 'Internal' }}</small>
                                <button v-if="canDeleteAttachment" type="button" class="btn btn-outline-danger btn-sm" @click="deleteLink(link)">Delete</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel-card">
                    <div class="panel-header">
                        <div>
                            <p class="section-kicker">Nested Issues</p>
                            <h4 class="panel-title">Recursive issue tree</h4>
                        </div>
                        <button v-if="canCreate" class="btn btn-light rounded-pill" @click="openChildModal(issue)">+ Add Child</button>
                    </div>

                    <div v-if="!nestedIssues.length" class="empty-state-card">
                        <strong>No sub-issues yet</strong>
                        <p>Create a child issue directly from this parent to build the recursive tree.</p>
                        <button v-if="canCreate" class="btn btn-accent rounded-pill" @click="openChildModal(issue)">Create First Child</button>
                    </div>

                    <IssueTree v-else :issues="nestedIssues" @create-child="openChildModal" @quick-read="openQuickRead" />
                </section>
            </div>
        </div>

        <Modal v-model="childModalOpen" title="Create Sub-Issue" size="modal-lg">
            <form class="vstack gap-3" @submit.prevent="submitChild">
                <div class="context-chip">
                    <span>Parent issue</span>
                    <strong>{{ childIssueParent?.title || issue.title }}</strong>
                </div>

                <div>
                    <label class="form-label">Title</label>
                    <input v-model="childForm.title" type="text" class="form-control" :class="{ 'is-invalid-soft': childForm.errors.title }">
                    <FormError :message="childForm.errors.title" />
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select v-model="childForm.status" class="form-select" :class="{ 'is-invalid-soft': childForm.errors.status }">
                            <option value="todo">Todo</option>
                            <option value="inprogress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                        <FormError :message="childForm.errors.status" />
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Parent</label>
                        <select v-model="childForm.parent_id" class="form-select" :class="{ 'is-invalid-soft': childForm.errors.parent_id }">
                            <option :value="issue.id">{{ issue.title }}</option>
                            <option v-for="parent in projectIssues" :key="parent.id" :value="parent.id">{{ parent.label }}</option>
                        </select>
                        <FormError :message="childForm.errors.parent_id" />
                    </div>
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <RichTextEditor v-model="childForm.description" :error="childForm.errors.description" placeholder="Describe the child issue with formatted notes..." />
                    <FormError :message="childForm.errors.description" />
                </div>

                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label mb-0">Images</label>
                        <span class="badge text-bg-light border text-muted small">
                            💡 Press <kbd>Ctrl+V</kbd> to paste screenshot (JPEG)
                        </span>
                    </div>
                    <input
                        ref="childFileInputRef"
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png"
                        class="form-control"
                        :class="{ 'is-invalid-soft': childForm.errors.images || childForm.errors['images.0'] }"
                        @change="onChildImageChange"
                    >
                    <FormError :message="childForm.errors.images || childForm.errors['images.0']" />

                    <div v-if="stagedChildImages.length" class="staged-images-container mt-2 p-2 border rounded bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small font-monospace text-muted fw-semibold">
                                {{ stagedChildImages.length }} image{{ stagedChildImages.length > 1 ? 's' : '' }} attached
                            </span>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary rounded-pill"
                                @click="clearStagedChildImages"
                            >
                                Clear
                            </button>
                        </div>

                        <div class="staged-images-grid">
                            <div
                                v-for="(item, idx) in stagedChildImages"
                                :key="item.id"
                                class="staged-image-card"
                            >
                                <img :src="item.previewUrl" :alt="item.file.name" class="staged-image-thumb">
                                <div class="staged-image-info">
                                    <span class="staged-image-name text-truncate" :title="item.file.name">{{ item.file.name }}</span>
                                    <div class="d-flex align-items-center justify-content-between mt-1">
                                        <span class="badge rounded-pill bg-white border text-dark font-monospace extra-small">
                                            {{ formatFileSize(item.file.size) }}
                                        </span>
                                        <span v-if="item.isPasted" class="badge rounded-pill text-bg-success extra-small">
                                            Pasted (JPEG)
                                        </span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="staged-image-remove-btn"
                                    title="Remove image"
                                    @click="removeStagedChildImage(idx)"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="form-label">Files</label>
                    <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.rtf,.ppt,.pptx,.zip,.rar" class="form-control" :class="{ 'is-invalid-soft': childForm.errors.files || childForm.errors['files.0'] }" @change="onChildFileChange">
                    <FormError :message="childForm.errors.files || childForm.errors['files.0']" />
                </div>

                <div>
                    <label class="form-label">Links</label>
                    <div v-if="!childForm.links.length" class="text-muted small mb-2">Add internal or external links for this issue.</div>
                    <div v-for="(link, index) in childForm.links" :key="index" class="row g-2 align-items-center mb-2">
                        <div class="col-5">
                            <input v-model="link.label" type="text" class="form-control" placeholder="Label (optional)" :class="{ 'is-invalid-soft': childForm.errors[`links.${index}.label`] }">
                        </div>
                        <div class="col-6">
                            <input v-model="link.url" type="text" class="form-control" placeholder="https:// or /internal/path" :class="{ 'is-invalid-soft': childForm.errors[`links.${index}.url`] }">
                        </div>
                        <div class="col-1 d-grid">
                            <button type="button" class="btn btn-outline-danger" @click="removeChildLink(index)">×</button>
                        </div>
                        <div class="col-12">
                            <FormError :message="childForm.errors[`links.${index}.url`] || childForm.errors[`links.${index}.label`]" />
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="addChildLink">+ Add Link</button>
                </div>

                <div>
                    <label class="form-label">Tags</label>
                    <small class="text-muted d-block mb-2">Project-based tags for search and categorization.</small>
                    <div class="d-flex gap-2 mb-2 position-relative">
                        <input
                            v-model="childTagInput"
                            type="text"
                            class="form-control"
                            placeholder="Type tag and press Enter"
                            @keyup.enter.prevent="addChildTag()"
                            autocomplete="off"
                        >
                        <button type="button" class="btn btn-outline-secondary" @click="addChildTag()">Add</button>
                        <div v-if="childTagSuggestions.length" class="position-absolute top-100 start-0 mt-1 w-100 bg-white border rounded shadow-sm" style="z-index: 1000; max-height: 200px; overflow-y: auto;">
                            <button
                                v-for="tag in childTagSuggestions"
                                :key="`child-suggest-${tag.id}`"
                                type="button"
                                class="w-100 text-start px-3 py-2 border-0 bg-white text-decoration-none d-flex align-items-center gap-2"
                                style="cursor: pointer; font-size: 0.9rem;"
                                @click.prevent="addChildTag(tag.name)"
                                @mouseover="$event.currentTarget.style.backgroundColor = '#f5f5f5'"
                                @mouseout="$event.currentTarget.style.backgroundColor = 'white'"
                            >
                                <span class="badge bg-light text-dark">{{ tag.name }}</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="childForm.tag_names.length" class="d-flex flex-wrap gap-1">
                        <span v-for="(tag, index) in childForm.tag_names" :key="`child-${tag}-${index}`" class="badge rounded-pill text-bg-light border d-inline-flex align-items-center gap-1 px-2 py-1">
                            {{ tag }}
                            <button type="button" class="btn btn-sm p-0 border-0 bg-transparent" @click="removeChildTag(index)">x</button>
                        </span>
                    </div>
                    <FormError :message="childForm.errors.tag_names || childForm.errors['tag_names.0']" />
                </div>

                <button class="btn btn-accent rounded-pill align-self-start" :disabled="childForm.processing">
                    <span v-if="childForm.processing" class="spinner-border spinner-border-sm me-2" />
                    Create Sub-Issue
                </button>
            </form>
        </Modal>
    </AppLayout>

    <Modal v-model="imageModalOpen" title="Issue Images" size="modal-xl">
        <div v-if="!issueImages.length" class="text-center py-5 text-muted">
            <p class="mb-0">No images attached to this issue.</p>
        </div>

        <div v-else class="issue-modal-gallery">
            <!-- Navigation jump pills bar when there are multiple images -->
            <div v-if="issueImages.length > 1" class="modal-gallery-jumpbar d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill bg-dark-subtle text-dark-emphasis px-2.5 py-1.5 font-monospace">
                        {{ issueImages.length }} Images
                    </span>
                    <span class="text-muted small">Scroll vertically or click to jump:</span>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-1">
                    <button
                        v-for="(image, index) in issueImages"
                        :key="`jump-${image.id || index}`"
                        type="button"
                        class="btn btn-sm rounded-pill px-2.5 py-1 fw-medium"
                        :class="activeImageIndex === index ? 'btn-accent' : 'btn-outline-secondary'"
                        @click="scrollToModalImage(index)"
                    >
                        #{{ index + 1 }}
                    </button>
                </div>
            </div>

            <!-- Vertically scrollable feed list (like FB feed single images) -->
            <div ref="modalFeedRef" class="modal-image-feed" @scroll.passive="handleModalFeedScroll">
                <article
                    v-for="(image, index) in issueImages"
                    :key="`feed-img-${image.id || index}`"
                    :id="`modal-image-entry-${index}`"
                    class="image-feed-card"
                >
                    <!-- Feed Card Header: Meta + Actions -->
                    <div class="image-feed-header d-flex align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <span class="badge rounded-pill bg-light border text-dark font-monospace flex-shrink-0">
                                {{ index + 1 }} / {{ issueImages.length }}
                            </span>
                            <span class="text-truncate fw-medium text-dark small" :title="image.original_name || `Image #${index + 1}`">
                                {{ image.original_name || `Image #${index + 1}` }}
                            </span>
                            <span v-if="image.size" class="badge rounded-pill text-bg-light border text-muted extra-small flex-shrink-0 font-monospace">
                                {{ formatFileSize(image.size) }}
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <a
                                :href="image.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-sm btn-light border rounded-pill d-inline-flex align-items-center gap-1 px-2.5 py-1 text-secondary"
                                title="Open full-size in new tab"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
                                    <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
                                </svg>
                                <span>Full Size</span>
                            </a>
                            <a
                                :href="image.url"
                                :download="image.original_name || `issue-image-${index + 1}`"
                                class="btn btn-sm btn-light border rounded-pill d-inline-flex align-items-center gap-1 px-2.5 py-1 text-secondary"
                                title="Download image"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                </svg>
                                <span>Download</span>
                            </a>
                            <button
                                v-if="canDeleteAttachment"
                                type="button"
                                class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1"
                                title="Delete image"
                                @click="deleteImage(image)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Image Canvas Container (Facebook Feed Post Style) -->
                    <div class="image-feed-canvas">
                        <a
                            :href="image.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="image-feed-link d-block"
                            title="Click to open original size in new tab"
                        >
                            <img
                                :src="image.url"
                                :alt="image.original_name || issue.title"
                                class="image-feed-img"
                                loading="lazy"
                            >
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </Modal>

    <IssueQuickReadModal v-model="quickReadModalOpen" :issue="activeQuickReadIssue" />
</template>

<style scoped>
.hero-panel {
    align-items: flex-start;
}

.issue-hero-panel {
    gap: 1.5rem;
}

.issue-hero-copy {
    flex: 1 1 32rem;
    min-width: 0;
}

.issue-hero-title {
    margin-bottom: 0;
    line-height: 1.12;
    overflow-wrap: anywhere;
}

.issue-hero-actions {
    display: flex;
    flex: 0 0 auto;
    min-width: min(100%, 18rem);
    justify-content: flex-end;
    align-items: center;
}

.issue-hero-actions-top {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 0.65rem;
}

.issue-hero-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 3rem;
    padding: 0.58rem 1.05rem;
    border-width: 1px;
    font-size: 0.92rem;
    font-weight: 500;
    line-height: 1.1;
    white-space: nowrap;
}

.issue-hero-add-btn {
    border-color: rgba(31, 41, 55, 0.35);
    background: rgba(255, 255, 255, 0.78);
    color: #374151;
}

.issue-hero-add-btn:hover,
.issue-hero-add-btn:focus {
    border-color: rgba(31, 41, 55, 0.55);
    background: rgba(255, 255, 255, 0.96);
    color: #111827;
}

.issue-delete-btn {
    border-color: rgba(239, 68, 68, 0.7);
    background: rgba(255, 255, 255, 0.82);
    color: #ef4444;
}

.issue-delete-btn:hover,
.issue-delete-btn:focus {
    border-color: rgba(220, 38, 38, 0.78);
    background: rgba(254, 242, 242, 0.96);
    color: #dc2626;
}

.issue-hero-status {
    min-height: 2.95rem;
    padding-inline: 1rem;
    font-size: 0.92rem;
}

.issue-delete-btn {
    align-self: auto;
}

:deep(.issue-hero-actions .status-pill),
:deep(.issue-hero-actions .badge),
:deep(.issue-hero-actions .btn) {
    flex: 0 0 auto;
}

:deep(.issue-hero-status.status-pill) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
}

@media (max-width: 1199.98px) {
    .issue-hero-copy {
        flex: initial;
    }

    .issue-hero-actions {
        width: 100%;
        min-width: 0;
        justify-content: flex-start;
        align-items: flex-start;
    }

    .issue-hero-actions-top {
        justify-content: flex-start;
        width: 100%;
    }
}

@media (max-width: 991.98px) {
    .issue-hero-panel {
        flex-direction: column;
        gap: 1rem;
    }

    .issue-hero-copy {
        flex: initial;
    }

    .issue-hero-actions {
        width: 100%;
        min-width: 0;
        justify-content: flex-start;
        align-items: stretch;
    }

    .issue-hero-actions-top {
        justify-content: flex-start;
        width: 100%;
    }

    .issue-hero-action-btn {
        width: 100%;
    }

    :deep(.issue-hero-status.status-pill) {
        width: 100%;
        min-height: 3rem;
        justify-content: center;
    }
}

@media (max-width: 575.98px) {
    .issue-hero-title {
        font-size: clamp(2rem, 6vw, 2.5rem);
    }

    .issue-hero-actions-top {
        flex-direction: column;
        align-items: stretch;
    }
}

.staged-images-container {
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
}

.staged-images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.75rem;
}

.staged-image-card {
    position: relative;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.staged-image-thumb {
    width: 100%;
    height: 90px;
    object-fit: cover;
    background: #f1f5f9;
}

.staged-image-info {
    padding: 0.4rem 0.5rem;
    display: flex;
    flex-direction: column;
}

.staged-image-name {
    font-size: 0.75rem;
    font-weight: 500;
    color: #334155;
}

.extra-small {
    font-size: 0.68rem;
    padding: 0.15rem 0.4rem;
}

.staged-image-remove-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 22px;
    height: 22px;
    line-height: 20px;
    text-align: center;
    border-radius: 50%;
    border: none;
    background: rgba(0, 0, 0, 0.65);
    color: #ffffff;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease;
}

.staged-image-remove-btn:hover {
    background: #ef4444;
}

/* Modal Image Feed (Facebook Feed Style) */
.modal-gallery-jumpbar {
    position: sticky;
    top: -12px;
    z-index: 10;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(8px);
    padding: 0.6rem 0.5rem;
    margin-bottom: 1.25rem;
    border-radius: 12px;
}

.modal-image-feed {
    display: flex;
    flex-direction: column;
    gap: 2.25rem;
    max-height: calc(85vh - 130px);
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0.25rem 0.5rem 2rem 0.25rem;
    scroll-behavior: smooth;
}

.modal-image-feed::-webkit-scrollbar {
    width: 8px;
}

.modal-image-feed::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.05);
    border-radius: 999px;
}

.modal-image-feed::-webkit-scrollbar-thumb {
    background: rgba(15, 118, 110, 0.3);
    border-radius: 999px;
}

.modal-image-feed::-webkit-scrollbar-thumb:hover {
    background: rgba(15, 118, 110, 0.5);
}

.image-feed-card {
    flex: 0 0 auto !important;
    width: 100%;
    min-height: fit-content;
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 16px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.image-feed-card:hover {
    border-color: rgba(15, 118, 110, 0.28);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

.image-feed-header {
    padding: 0.8rem 1.15rem;
    background: linear-gradient(180deg, #fcfcfc 0%, #f3f4f6 100%);
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.image-feed-canvas {
    display: block;
    width: 100%;
    background: #0f172a08;
    padding: 0;
}

.image-feed-link {
    display: block;
    width: 100%;
    cursor: zoom-in;
    text-align: center;
}

.image-feed-img {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto !important;
    margin: 0 auto;
    border-radius: 0 0 16px 16px;
}
</style>
