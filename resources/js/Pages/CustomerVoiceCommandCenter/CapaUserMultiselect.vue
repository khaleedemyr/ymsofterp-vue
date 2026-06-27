<template>
  <Multiselect
    :model-value="selectedUsers"
    :options="userOptions"
    :multiple="multiple"
    :searchable="true"
    :internal-search="true"
    :close-on-select="!multiple"
    :disabled="disabled"
    :placeholder="placeholder"
    label="display_label"
    track-by="id"
    select-label=""
    selected-label=""
    deselect-label="×"
    @update:model-value="onUpdate"
  >
    <template #option="{ option }">
      <div class="py-0.5">
        <div class="text-sm font-medium text-slate-900">{{ option.nama_lengkap }}</div>
        <div class="text-[11px] text-indigo-600">{{ option.nama_jabatan || '—' }}</div>
      </div>
    </template>
    <template #singleLabel="{ option }">
      <span>{{ option.nama_lengkap }}<span v-if="option.nama_jabatan" class="text-slate-500"> · {{ option.nama_jabatan }}</span></span>
    </template>
    <template #tag="{ option, remove }">
      <span class="multiselect__tag">
        <span>{{ option.nama_lengkap }}</span>
        <i aria-hidden="true" tabindex="1" class="multiselect__tag-icon" @click="remove(option)" />
      </span>
    </template>
  </Multiselect>
</template>

<script setup>
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  assignees: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: true },
  placeholder: { type: String, default: 'Cari nama…' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const userOptions = computed(() =>
  (props.assignees || []).map((u) => ({
    id: u.id,
    nama_lengkap: u.nama_lengkap || `User #${u.id}`,
    nama_jabatan: u.nama_jabatan || '',
    display_label: `${u.nama_lengkap || ''}${u.nama_jabatan ? ` · ${u.nama_jabatan}` : ''}`,
  })),
)

const selectedUsers = computed(() => {
  const ids = new Set(
    (props.modelValue || [])
      .map((x) => Number(x))
      .filter((n) => Number.isFinite(n) && n > 0),
  )
  if (!ids.size) return props.multiple ? [] : null

  const matched = userOptions.value.filter((u) => ids.has(u.id))
  return props.multiple ? matched : matched[0] || null
})

function onUpdate(val) {
  if (props.multiple) {
    const arr = Array.isArray(val) ? val : []
    emit('update:modelValue', arr.map((u) => u.id))
  } else {
    emit('update:modelValue', val?.id ? [val.id] : [])
  }
}
</script>

<style scoped>
:deep(.multiselect) {
  min-height: 2.75rem;
  font-size: 0.875rem;
}
:deep(.multiselect__tags) {
  min-height: 2.75rem;
  padding-top: 0.4rem;
  border-radius: 0.75rem;
  border-color: #e2e8f0;
}
:deep(.multiselect__input),
:deep(.multiselect__single) {
  font-size: 0.875rem;
}
</style>
