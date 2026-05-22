<template>
  <div ref="rootEl" class="relative inline-block" data-omni-emoji-picker>
    <button
      type="button"
      class="flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
      :class="buttonClass"
      :disabled="disabled"
      title="Emoji"
      @click.stop="toggle"
    >
      <i class="fa-regular fa-face-smile" :class="iconClass" />
    </button>
    <Teleport to="body" :disabled="!teleport">
      <div
        v-if="open"
        data-omni-emoji-picker-panel
        class="rounded-xl border border-slate-200 bg-white shadow-xl"
        :class="panelClassList"
        :style="panelStyle"
        @mousedown.prevent
      >
        <div class="flex items-center justify-between border-b border-slate-100 px-2 py-1.5">
          <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            Emoji — {{ activeCategoryLabel }}
          </span>
          <button
            type="button"
            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            @click.stop="open = false"
          >
            <i class="fa-solid fa-xmark text-xs" />
          </button>
        </div>
        <div class="flex gap-0.5 overflow-x-auto border-b border-slate-100 px-1 py-1">
          <button
            v-for="cat in omniEmojiCategories"
            :key="cat.id"
            type="button"
            class="flex shrink-0 flex-col items-center rounded-lg px-1.5 py-0.5 transition-colors"
            :class="activeCategoryId === cat.id
              ? 'bg-indigo-50 text-indigo-700'
              : 'text-slate-600 hover:bg-slate-50'"
            :title="`${cat.label} (${cat.emojis.length})`"
            @click.stop="activeCategoryId = cat.id"
          >
            <span class="text-base leading-none">{{ cat.icon }}</span>
            <span class="max-w-[3rem] truncate text-[9px] font-medium">{{ cat.label }}</span>
          </button>
        </div>
        <p class="border-b border-slate-50 px-2 py-0.5 text-[9px] text-slate-400">
          {{ activeEmojis.length }} emoji · gulir untuk lihat semua
        </p>
        <div class="grid gap-0.5 overflow-y-auto p-2" :class="gridClass">
          <button
            v-for="(em, idx) in activeEmojis"
            :key="`${activeCategoryId}-${idx}`"
            type="button"
            class="rounded p-1 text-xl leading-none hover:bg-slate-100"
            @mousedown.prevent="pick(em)"
          >
            {{ em }}
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { omniEmojiCategories } from '@/utils/omniEmojiPicker.js'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  /** Posisi panel relatif ke tombol (non-teleport) atau preferensi arah (teleport) */
  placement: { type: String, default: 'top' },
  panelWidth: { type: String, default: '18rem' },
  buttonSize: { type: String, default: 'md' },
  /** Render panel ke body agar tidak terpotong overflow parent */
  teleport: { type: Boolean, default: false },
  /** md = standar inbox, lg = panel lebih tinggi/lebar */
  panelSize: { type: String, default: 'md' },
})

const open = defineModel('open', { type: Boolean, default: false })

const emit = defineEmits(['select'])

const rootEl = ref(null)
const activeCategoryId = ref(omniEmojiCategories[0]?.id ?? 'smileys')
const panelFixedStyle = ref({})

const activeEmojis = computed(() => {
  const cat = omniEmojiCategories.find((c) => c.id === activeCategoryId.value)
  return cat?.emojis ?? []
})

const activeCategoryLabel = computed(() => {
  const cat = omniEmojiCategories.find((c) => c.id === activeCategoryId.value)
  return cat?.label ?? 'Emoji'
})

const buttonClass = computed(() => (props.buttonSize === 'sm' ? 'h-7 w-7 text-base' : 'h-8 w-8 text-lg'))
const iconClass = computed(() => (props.buttonSize === 'sm' ? 'text-sm' : ''))

const isLarge = computed(() => props.panelSize === 'lg')

const gridClass = computed(() =>
  isLarge.value
    ? 'max-h-[min(22rem,55vh)] grid-cols-9'
    : 'max-h-[min(18rem,45vh)] grid-cols-8'
)

const panelClassList = computed(() => {
  if (props.teleport) {
    return 'z-[9999]'
  }
  return ['absolute z-40', props.placement === 'bottom' ? 'left-0 top-full mt-1' : 'bottom-full right-0 mb-1']
})

const panelStyle = computed(() => {
  const base = { width: props.panelWidth }
  if (props.teleport) {
    return { ...base, ...panelFixedStyle.value }
  }
  return base
})

async function updatePanelPosition() {
  await nextTick()
  const el = rootEl.value
  if (!el || !props.teleport) return

  const rect = el.getBoundingClientRect()
  const widthPx = parsePanelWidthPx(props.panelWidth)
  let left = props.placement === 'top' ? rect.right - widthPx : rect.left
  left = Math.max(8, Math.min(left, window.innerWidth - widthPx - 8))

  if (props.placement === 'top') {
    panelFixedStyle.value = {
      position: 'fixed',
      left: `${left}px`,
      bottom: `${window.innerHeight - rect.top + 6}px`,
    }
  } else {
    panelFixedStyle.value = {
      position: 'fixed',
      left: `${left}px`,
      top: `${rect.bottom + 6}px`,
    }
  }
}

function parsePanelWidthPx(width) {
  const m = String(width).match(/^([\d.]+)rem$/)
  if (m) return parseFloat(m[1]) * 16
  const px = parseInt(width, 10)
  return Number.isFinite(px) ? px : 288
}

function toggle() {
  if (props.disabled) return
  open.value = !open.value
}

function pick(emoji) {
  emit('select', emoji)
  open.value = false
}

function onDocumentMouseDown(e) {
  if (!open.value) return
  const target = e.target
  if (target instanceof Element) {
    if (rootEl.value?.contains(target)) return
    if (target.closest('[data-omni-emoji-picker-panel]')) return
  }
  open.value = false
}

watch(open, async (isOpen) => {
  if (isOpen) {
    if (props.teleport) {
      await updatePanelPosition()
    }
    document.addEventListener('mousedown', onDocumentMouseDown)
    window.addEventListener('resize', updatePanelPosition)
    window.addEventListener('scroll', updatePanelPosition, true)
  } else {
    document.removeEventListener('mousedown', onDocumentMouseDown)
    window.removeEventListener('resize', updatePanelPosition)
    window.removeEventListener('scroll', updatePanelPosition, true)
  }
})

onUnmounted(() => {
  document.removeEventListener('mousedown', onDocumentMouseDown)
  window.removeEventListener('resize', updatePanelPosition)
  window.removeEventListener('scroll', updatePanelPosition, true)
})
</script>
