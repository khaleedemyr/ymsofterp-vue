<template>
  <div
    v-if="hasChanges"
    class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-4"
    :class="compact ? 'text-sm' : ''"
  >
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <h3 class="font-semibold text-amber-900">
          <i class="fa-solid fa-pen-to-square mr-2"></i>
          Perubahan setelah edit
        </h3>
        <p v-if="editReason" class="text-amber-800 mt-1">
          Alasan: <span class="font-medium">{{ editReason }}</span>
        </p>
        <p v-if="editedMeta" class="text-xs text-amber-700 mt-1">{{ editedMeta }}</p>
      </div>
      <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-200 text-amber-900">
        Highlighted
      </span>
    </div>

    <div v-if="headerEntries.length > 0" class="space-y-2">
      <div
        v-for="entry in headerEntries"
        :key="entry.key"
        class="rounded-lg bg-white/80 border border-amber-100 p-3"
      >
        <div class="text-xs font-semibold text-gray-600 mb-2">{{ entry.label }}</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div>
            <div class="text-xs font-medium text-red-600 mb-1">Sebelum</div>
            <div class="p-2 bg-red-50 rounded border-l-2 border-red-300 text-gray-800 whitespace-pre-wrap">
              {{ displayValue(entry.old) }}
            </div>
          </div>
          <div>
            <div class="text-xs font-medium text-green-600 mb-1">Sesudah</div>
            <div class="p-2 bg-green-50 rounded border-l-2 border-green-300 text-gray-800 whitespace-pre-wrap">
              {{ displayValue(entry.new) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="(changes.items || []).length > 0" class="space-y-2">
      <div class="text-xs font-semibold uppercase tracking-wide text-amber-800">Item lembur</div>
      <div
        v-for="(item, idx) in changes.items"
        :key="`item-change-${idx}`"
        class="rounded-lg bg-white/80 border border-amber-100 p-3"
      >
        <div class="flex flex-wrap items-center gap-2 mb-2">
          <span class="font-medium text-gray-900">{{ item.user_name || '-' }}</span>
          <span
            class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
            :class="actionClass(item.action)"
          >
            {{ actionLabel(item.action) }}
          </span>
          <span v-if="item.overtime_date" class="text-xs text-gray-500">
            {{ formatDate(item.overtime_date) }}
          </span>
        </div>

        <div v-if="item.fields" class="space-y-2">
          <div
            v-for="(fieldDiff, fieldKey) in item.fields"
            :key="`${idx}-${fieldKey}`"
            class="grid grid-cols-1 sm:grid-cols-2 gap-2"
          >
            <div>
              <div class="text-xs font-medium text-red-600 mb-1">
                {{ fieldLabel(fieldKey) }} · Sebelum
              </div>
              <div class="p-2 bg-red-50 rounded border-l-2 border-red-300 text-gray-800">
                {{ formatField(fieldKey, fieldDiff?.old) }}
              </div>
            </div>
            <div>
              <div class="text-xs font-medium text-green-600 mb-1">
                {{ fieldLabel(fieldKey) }} · Sesudah
              </div>
              <div class="p-2 bg-green-50 rounded border-l-2 border-green-300 text-gray-800">
                {{ formatField(fieldKey, fieldDiff?.new) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="changes.approvers_changed"
      class="rounded-lg bg-white/80 border border-amber-100 p-3 text-sm text-amber-900"
    >
      <i class="fa-solid fa-user-check mr-1"></i>
      Urutan / daftar approver diubah.
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  changes: { type: Object, default: null },
  editReason: { type: String, default: '' },
  editedAt: { type: String, default: null },
  editorName: { type: String, default: '' },
  compact: { type: Boolean, default: false },
});

const hasChanges = computed(() => {
  const c = props.changes;
  if (!c || typeof c !== 'object') return false;
  const headerCount = c.header ? Object.keys(c.header).length : 0;
  const itemCount = Array.isArray(c.items) ? c.items.length : 0;
  return headerCount > 0 || itemCount > 0 || !!c.approvers_changed;
});

const headerEntries = computed(() => {
  const header = props.changes?.header || {};
  return Object.entries(header).map(([key, diff]) => ({
    key,
    label: key === 'submission_date' ? 'Tanggal Pengajuan' : key === 'notes' ? 'Catatan' : key,
    old: diff?.old,
    new: diff?.new,
  }));
});

const editedMeta = computed(() => {
  const parts = [];
  if (props.editorName) parts.push(`oleh ${props.editorName}`);
  if (props.editedAt) {
    parts.push(
      new Date(props.editedAt).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    );
  }
  return parts.length ? parts.join(' · ') : '';
});

function displayValue(value) {
  if (value === null || value === undefined || value === '') return '-';
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
    return formatDate(value);
  }
  return String(value);
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function fieldLabel(key) {
  if (key === 'requested_hours') return 'Jam';
  if (key === 'overtime_date') return 'Tanggal';
  if (key === 'notes') return 'Catatan';
  return key;
}

function formatField(key, value) {
  if (value === null || value === undefined || value === '') return '-';
  if (key === 'requested_hours') return `${value} jam`;
  if (key === 'overtime_date') return formatDate(value);
  return String(value);
}

function actionLabel(action) {
  if (action === 'added') return 'Ditambah';
  if (action === 'removed') return 'Dihapus';
  return 'Diubah';
}

function actionClass(action) {
  if (action === 'added') return 'bg-green-100 text-green-800';
  if (action === 'removed') return 'bg-red-100 text-red-800';
  return 'bg-amber-100 text-amber-800';
}
</script>
