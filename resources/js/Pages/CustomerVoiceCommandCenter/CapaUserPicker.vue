<template>
  <div class="relative">
    <input
      v-model="q"
      type="text"
      autocomplete="off"
      autocorrect="off"
      class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
      :placeholder="placeholder"
      :disabled="disabled"
      @focus="open = true"
      @input="open = true"
      @keydown.escape.prevent="open = false"
    />
    <p v-if="selectedLabel" class="mt-1.5 rounded-lg bg-slate-50 px-2 py-1.5 text-xs leading-snug text-slate-700">
      <span class="font-semibold text-slate-800">{{ selectedLabel }}</span>
      <span v-if="selectedJabatan" class="text-slate-500"> · {{ selectedJabatan }}</span>
    </p>
    <div
      v-show="open && !disabled"
      class="absolute left-0 right-0 z-30 mt-1 max-h-52 overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
      role="listbox"
    >
      <button
        v-if="clearable && modelValue != null"
        type="button"
        class="block w-full px-3 py-2.5 text-left text-xs font-medium text-slate-500 hover:bg-slate-50"
        @mousedown.prevent="chooseClear"
      >
        Kosongkan
      </button>
      <button
        v-for="u in filtered"
        :key="u.id"
        type="button"
        class="block w-full px-3 py-2.5 text-left text-sm hover:bg-indigo-50"
        @mousedown.prevent="choose(u)"
      >
        <span class="font-medium text-slate-900">{{ u.nama_lengkap }}</span>
        <span class="mt-0.5 block text-[11px] text-slate-500">{{ u.nama_jabatan || '—' }}</span>
      </button>
      <div v-if="filtered.length === 0" class="px-3 py-4 text-center text-xs text-slate-400">Tidak ada hasil</div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Number, default: null },
  assignees: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Cari nama…' },
  clearable: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const q = ref('')
const open = ref(false)

const selectedUser = computed(() => {
  const id = props.modelValue
  if (id == null) return null

  return props.assignees.find((x) => x.id === id) || null
})

const selectedLabel = computed(() => {
  if (selectedUser.value) return selectedUser.value.nama_lengkap || ''
  if (props.modelValue != null) return `User #${props.modelValue}`

  return ''
})
const selectedJabatan = computed(() => selectedUser.value?.nama_jabatan || '')

const filtered = computed(() => {
  const list = props.assignees || []
  const term = (q.value || '').trim().toLowerCase()
  if (!term) {
    return list.slice(0, 80)
  }
  return list
    .filter((u) => {
      const name = (u.nama_lengkap || '').toLowerCase()
      const jab = (u.nama_jabatan || '').toLowerCase()

      return name.includes(term) || jab.includes(term)
    })
    .slice(0, 80)
})

watch(
  () => props.modelValue,
  () => {
    q.value = ''
  },
)

function choose(u) {
  emit('update:modelValue', u.id)
  open.value = false
  q.value = ''
}

function chooseClear() {
  emit('update:modelValue', null)
  open.value = false
  q.value = ''
}
</script>
