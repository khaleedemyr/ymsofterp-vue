<template>
  <div class="rich-text-editor overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
    <div class="flex flex-wrap items-center gap-1 border-b border-gray-200 bg-gray-50 px-2 py-1.5">
      <button
        v-for="btn in toolbar"
        :key="btn.cmd"
        type="button"
        class="inline-flex h-8 min-w-8 items-center justify-center rounded px-2 text-sm text-gray-700 hover:bg-gray-200"
        :title="btn.title"
        :class="{ 'bg-indigo-100 text-indigo-700': isActive(btn) }"
        @mousedown.prevent
        @click="run(btn)"
      >
        <i :class="btn.icon"></i>
      </button>
    </div>
    <div
      ref="editorEl"
      class="rich-text-editor__body min-h-[120px] px-3 py-2 text-sm text-gray-800 outline-none"
      :style="{ minHeight: minHeight }"
      :data-placeholder="placeholder"
      contenteditable="true"
      @input="onInput"
      @blur="onInput"
      @paste="onPaste"
    />
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  minHeight: { type: String, default: '140px' },
  placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const editorEl = ref(null);
const syncing = ref(false);

const toolbar = [
  { cmd: 'bold', title: 'Bold', icon: 'fa-solid fa-bold' },
  { cmd: 'italic', title: 'Italic', icon: 'fa-solid fa-italic' },
  { cmd: 'underline', title: 'Underline', icon: 'fa-solid fa-underline' },
  { cmd: 'justifyLeft', title: 'Rata kiri', icon: 'fa-solid fa-align-left' },
  { cmd: 'justifyCenter', title: 'Rata tengah', icon: 'fa-solid fa-align-center' },
  { cmd: 'justifyRight', title: 'Rata kanan', icon: 'fa-solid fa-align-right' },
  { cmd: 'insertUnorderedList', title: 'Bullet list', icon: 'fa-solid fa-list-ul' },
  { cmd: 'insertOrderedList', title: 'Numbered list', icon: 'fa-solid fa-list-ol' },
  { cmd: 'removeFormat', title: 'Hapus format', icon: 'fa-solid fa-eraser' },
];

function looksLikeHtml(value) {
  return /<[a-z][\s\S]*>/i.test(String(value || ''));
}

function plainToHtml(text) {
  const raw = String(text || '').trim();
  if (!raw) return '';
  if (looksLikeHtml(raw)) return raw;
  return raw
    .split(/\n\s*\n/)
    .map((block) => {
      const lines = block
        .split('\n')
        .map((line) => escapeHtml(line))
        .join('<br>');
      return `<p>${lines}</p>`;
    })
    .join('');
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function normalizeHtml(html) {
  const value = String(html || '').trim();
  if (!value || value === '<br>' || value === '<div><br></div>' || value === '<p><br></p>') {
    return '';
  }
  return value;
}

function setEditorHtml(html) {
  if (!editorEl.value) return;
  syncing.value = true;
  editorEl.value.innerHTML = plainToHtml(html);
  syncing.value = false;
}

function onInput() {
  if (syncing.value || !editorEl.value) return;
  emit('update:modelValue', normalizeHtml(editorEl.value.innerHTML));
}

function run(btn) {
  editorEl.value?.focus();
  document.execCommand(btn.cmd, false, null);
  onInput();
}

function isActive(btn) {
  try {
    if (['bold', 'italic', 'underline'].includes(btn.cmd)) {
      return document.queryCommandState(btn.cmd);
    }
  } catch {
    // ignore
  }
  return false;
}

function onPaste(event) {
  event.preventDefault();
  const text = event.clipboardData?.getData('text/plain') || '';
  document.execCommand('insertText', false, text);
  onInput();
}

watch(
  () => props.modelValue,
  (value) => {
    if (!editorEl.value) return;
    const current = normalizeHtml(editorEl.value.innerHTML);
    const next = normalizeHtml(plainToHtml(value));
    if (current !== next) {
      setEditorHtml(value);
    }
  }
);

onMounted(async () => {
  await nextTick();
  setEditorHtml(props.modelValue);
});

onBeforeUnmount(() => {
  editorEl.value = null;
});
</script>

<style scoped>
.rich-text-editor__body:empty:before {
  content: attr(data-placeholder);
  color: #9ca3af;
  pointer-events: none;
}
.rich-text-editor__body :deep(p) {
  margin: 0 0 0.75rem;
}
.rich-text-editor__body :deep(p:last-child) {
  margin-bottom: 0;
}
.rich-text-editor__body :deep(ul),
.rich-text-editor__body :deep(ol) {
  margin: 0.5rem 0 0.75rem;
  padding-left: 1.25rem;
}
.rich-text-editor__body :deep(li) {
  margin: 0.15rem 0;
}
</style>
