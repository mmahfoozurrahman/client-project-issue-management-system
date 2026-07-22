<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Highlight from '@tiptap/extension-highlight';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Write a clear description...' },
    error: { type: [String, Boolean], default: '' },
});

const emit = defineEmits(['update:modelValue']);
const sourceMode = ref(false);
const sourceHtml = ref('');

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        Link.configure({
            openOnClick: false,
            autolink: true,
            linkOnPaste: true,
            HTMLAttributes: { rel: 'nofollow noopener noreferrer' },
        }),
        Highlight,
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    onUpdate: ({ editor: instance }) => {
        if (!sourceMode.value) {
            emit('update:modelValue', instance.getHTML());
        }
    },
});

const canUndo = computed(() => editor.value?.can().undo() ?? false);
const canRedo = computed(() => editor.value?.can().redo() ?? false);

watch(() => props.modelValue, (value) => {
    const html = value ?? '';

    if (sourceMode.value) {
        if (sourceHtml.value !== html) sourceHtml.value = html;
        return;
    }

    if (editor.value && editor.value.getHTML() !== html) {
        editor.value.commands.setContent(html, false);
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function toggleSource() {
    if (sourceMode.value) {
        emit('update:modelValue', sourceHtml.value);
        editor.value?.commands.setContent(sourceHtml.value, false);
    } else {
        sourceHtml.value = editor.value?.getHTML() ?? '';
    }

    sourceMode.value = !sourceMode.value;
}

function updateSource() {
    emit('update:modelValue', sourceHtml.value);
}

function setLink() {
    const previousUrl = editor.value?.getAttributes('link').href ?? '';
    const url = window.prompt('Enter the link URL:', previousUrl);

    if (url === null) return;

    const chain = editor.value?.chain().focus();
    if (!chain) return;

    if (url.trim() === '') {
        chain.unsetLink().run();
        return;
    }

    try {
        const normalizedUrl = new URL(url, window.location.origin);
        if (!['http:', 'https:'].includes(normalizedUrl.protocol)) {
            throw new Error('Unsupported protocol');
        }
        chain.setLink({ href: normalizedUrl.href }).run();
    } catch {
        window.alert('Please enter a valid http:// or https:// URL.');
    }
}
</script>

<template>
    <div class="rich-editor" :class="{ 'is-invalid-soft': error }">
        <div v-if="editor" class="rich-editor-toolbar" role="toolbar" aria-label="Text formatting">
            <div class="editor-tool-group">
                <button type="button" :class="{ active: editor.isActive('bold') }" title="Bold" aria-label="Bold" @click="editor.chain().focus().toggleBold().run()"><strong>B</strong></button>
                <button type="button" :class="{ active: editor.isActive('italic') }" title="Italic" aria-label="Italic" @click="editor.chain().focus().toggleItalic().run()"><em>I</em></button>
                <button type="button" :class="{ active: editor.isActive('underline') }" title="Underline" aria-label="Underline" @click="editor.chain().focus().toggleUnderline().run()"><u>U</u></button>
                <button type="button" :class="{ active: editor.isActive('strike') }" title="Strikethrough" aria-label="Strikethrough" @click="editor.chain().focus().toggleStrike().run()"><s>S</s></button>
                <button type="button" :class="{ active: editor.isActive('highlight') }" title="Yellow highlight" aria-label="Yellow highlight" @click="editor.chain().focus().toggleHighlight().run()"><mark>Highlight</mark></button>
            </div>

            <div class="editor-tool-group">
                <button type="button" :class="{ active: editor.isActive('heading', { level: 2 }) }" title="Heading 2" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
                <button type="button" :class="{ active: editor.isActive('heading', { level: 3 }) }" title="Heading 3" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>
            </div>

            <div class="editor-tool-group">
                <button type="button" :class="{ active: editor.isActive('bulletList') }" title="Bulleted list" aria-label="Bulleted list" @click="editor.chain().focus().toggleBulletList().run()">• List</button>
                <button type="button" :class="{ active: editor.isActive('orderedList') }" title="Numbered list" aria-label="Numbered list" @click="editor.chain().focus().toggleOrderedList().run()">1. List</button>
            </div>

            <div class="editor-tool-group">
                <button type="button" :class="{ active: editor.isActive('code') }" title="Inline code" aria-label="Inline code" @click="editor.chain().focus().toggleCode().run()">&lt;/&gt;</button>
                <button type="button" :class="{ active: editor.isActive('codeBlock') }" title="Code block" aria-label="Code block" @click="editor.chain().focus().toggleCodeBlock().run()">Code</button>
                <button type="button" :class="{ active: editor.isActive('blockquote') }" title="Block quote" aria-label="Block quote" @click="editor.chain().focus().toggleBlockquote().run()">❝</button>
            </div>

            <div class="editor-tool-group">
                <button type="button" :class="{ active: editor.isActive('link') }" title="Add or edit link" aria-label="Add or edit link" @click="setLink">Link</button>
                <button v-if="editor.isActive('link')" type="button" title="Remove link" aria-label="Remove link" @click="editor.chain().focus().unsetLink().run()">Unlink</button>
            </div>

            <div class="editor-tool-group editor-tool-group-end">
                <button type="button" :disabled="!canUndo" title="Undo" aria-label="Undo" @click="editor.chain().focus().undo().run()">↶</button>
                <button type="button" :disabled="!canRedo" title="Redo" aria-label="Redo" @click="editor.chain().focus().redo().run()">↷</button>
                <button type="button" :class="{ active: sourceMode }" title="Toggle HTML source" aria-label="Toggle HTML source" @click="toggleSource">HTML</button>
            </div>
        </div>

        <EditorContent v-show="!sourceMode" :editor="editor" class="rich-editor-surface" />
        <textarea v-if="sourceMode" v-model="sourceHtml" class="rich-editor-source" :placeholder="placeholder" spellcheck="false" @input="updateSource" />
    </div>
</template>

<style scoped>
.rich-editor-toolbar { gap: 6px; }
.editor-tool-group { display: flex; gap: 4px; padding-right: 6px; border-right: 1px solid rgba(148, 163, 184, .3); }
.editor-tool-group-end { margin-left: auto; border-right: 0; padding-right: 0; }
.rich-editor-toolbar button.active { background: var(--accent); color: #fff; }
.rich-editor-toolbar button:disabled { cursor: not-allowed; opacity: .45; }
.rich-editor-source { display: block; width: 100%; min-height: 180px; padding: 14px 16px; border: 0; outline: 0; resize: vertical; background: #f8fafc; color: #1f2937; font: .85rem/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; }
@media (max-width: 640px) { .editor-tool-group-end { margin-left: 0; } }
</style>

<style>
.rich-editor-surface .ProseMirror { min-height: 130px; padding: 14px 16px; outline: none; color: #1f2937; }
.rich-editor-surface .ProseMirror p.is-editor-empty:first-child::before { color: #94a3b8; content: attr(data-placeholder); float: left; height: 0; pointer-events: none; }
.rich-editor-surface .ProseMirror h2 { margin: .75rem 0 .4rem; font-size: 1.3rem; font-weight: 700; }
.rich-editor-surface .ProseMirror h3 { margin: .65rem 0 .35rem; font-size: 1.1rem; font-weight: 700; }
.rich-editor-surface .ProseMirror ul, .rich-editor-surface .ProseMirror ol { margin: .4rem 0; padding-left: 1.5rem; }
.rich-editor-surface .ProseMirror blockquote { margin: .6rem 0; padding-left: 1rem; border-left: 3px solid var(--accent); color: #64748b; }
.rich-editor-surface .ProseMirror code { padding: .1rem .3rem; border-radius: 4px; background: #e2e8f0; color: #9f1239; font-size: .88em; }
.rich-editor-surface .ProseMirror pre { margin: .75rem 0; padding: 1rem; overflow-x: auto; border-radius: 8px; background: #1e293b; color: #e2e8f0; }
.rich-editor-surface .ProseMirror pre code { padding: 0; background: transparent; color: inherit; }
.rich-editor-surface .ProseMirror a { color: var(--accent); text-decoration: underline; }
.rich-editor-surface .ProseMirror mark, .rich-display mark { padding: 0 .1em; border-radius: .15em; background: #fef08a; color: inherit; }
</style>
