<template>
  <div ref="rootEl" class="relative min-w-[10rem]">
    <input
      v-model="q"
      type="text"
      autocomplete="off"
      autocorrect="off"
      class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
      :placeholder="placeholder"
      :disabled="disabled"
      @focus="open = true"
      @input="open = true"
      @keydown.escape.prevent="open = false"
    />
    <div v-if="chips.length" class="mt-1 flex max-h-16 flex-wrap gap-1 overflow-y-auto">
      <span
        v-for="c in chips"
        :key="c.id"
        class="inline-flex max-w-full items-center gap-0.5 rounded-md border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[10px] font-medium text-violet-900"
      >
        <span class="truncate">{{ c.label }}</span>
        <button
          v-if="!disabled"
          type="button"
          class="shrink-0 rounded px-0.5 text-violet-600 hover:bg-violet-100 hover:text-violet-900"
          title="Hapus"
          @click.stop="removeId(c.id)"
        >
          ×
        </button>
      </span>
    </div>
    <div
      v-show="open && !disabled"
      class="absolute left-0 right-0 z-40 mt-1 max-h-[min(24rem,70vh)] overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
      role="listbox"
    >
      <button
        v-for="u in filtered"
        :key="u.id"
        type="button"
        class="flex w-full items-start gap-2 px-3 py-2 text-left text-xs hover:bg-indigo-50"
        @mousedown.prevent="toggle(u)"
      >
        <span class="mt-0.5 font-mono text-[10px] text-slate-400">{{ selectedSet.has(u.id) ? '✓' : '·' }}</span>
        <span>
          <span class="font-medium text-slate-900">{{ u.nama_lengkap }}</span>
          <span class="mt-0.5 block text-[10px] text-slate-500">{{ u.nama_jabatan || '—' }}</span>
        </span>
      </button>
      <div v-if="filtered.length === 0" class="px-3 py-3 text-center text-[11px] text-slate-400">Tidak ada hasil</div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  assignees: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Cari user…' },
  disabled: { type: Boolean, default: false },
  max: { type: Number, default: 30 },
})

const emit = defineEmits(['update:modelValue'])

const q = ref('')
const open = ref(false)
const rootEl = ref(null)

const idList = computed(() => {
  const raw = props.modelValue || []
  const out = []
  for (const x of raw) {
    const n = typeof x === 'number' ? x : parseInt(String(x), 10)
    if (Number.isFinite(n) && n > 0) {
      out.push(n)
    }
  }
  return out
})

const selectedSet = computed(() => new Set(idList.value))

const chips = computed(() => {
  const list = props.assignees || []
  const byId = new Map(list.map((u) => [u.id, u]))
  return idList.value.map((id) => {
    const u = byId.get(id)
    return {
      id,
      label: u?.nama_lengkap || `User #${id}`,
    }
  })
})

const filtered = computed(() => {
  const list = props.assignees || []
  const term = (q.value || '').trim().toLowerCase()
  if (!term) {
    return list
  }
  return list.filter((u) => {
    const name = (u.nama_lengkap || '').toLowerCase()
    const jab = (u.nama_jabatan || '').toLowerCase()
    return name.includes(term) || jab.includes(term)
  })
})

function emitNext(nextIds) {
  emit('update:modelValue', nextIds)
}

function toggle(u) {
  const id = u.id
  const cur = [...idList.value]
  const i = cur.indexOf(id)
  if (i >= 0) {
    cur.splice(i, 1)
    emitNext(cur)
    return
  }
  if (cur.length >= props.max) {
    return
  }
  cur.push(id)
  emitNext(cur)
  q.value = ''
}

function removeId(id) {
  emitNext(idList.value.filter((x) => x !== id))
}

function onDocClick(e) {
  if (!open.value) return
  const el = rootEl.value
  if (el && !el.contains(e.target)) {
    open.value = false
  }
}

watch(
  () => props.modelValue,
  () => {
    q.value = ''
  },
)

onMounted(() => {
  document.addEventListener('click', onDocClick, true)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick, true)
})
</script>
