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
    <div
      v-if="open"
      class="absolute z-40 rounded-xl border border-slate-200 bg-white shadow-xl"
      :class="panelPositionClass"
      :style="{ width: panelWidth }"
      @mousedown.prevent
    >
      <div class="flex items-center justify-between border-b border-slate-100 px-2 py-1.5">
        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Emoji</span>
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
          class="shrink-0 rounded-lg px-2 py-1 text-xs transition-colors"
          :class="activeCategoryId === cat.id
            ? 'bg-indigo-50 font-medium text-indigo-700'
            : 'text-slate-600 hover:bg-slate-50'"
          :title="cat.label"
          @click.stop="activeCategoryId = cat.id"
        >
          {{ cat.icon }}
        </button>
      </div>
      <div class="grid max-h-52 grid-cols-8 gap-0.5 overflow-y-auto p-2">
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
  </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { omniEmojiCategories } from '@/utils/omniEmojiPicker.js'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  /** Posisi panel relatif ke tombol */
  placement: { type: String, default: 'top' }, // top | bottom
  panelWidth: { type: String, default: '18rem' },
  buttonSize: { type: String, default: 'md' }, // sm | md
})

const open = defineModel('open', { type: Boolean, default: false })

const emit = defineEmits(['select'])

const rootEl = ref(null)
const activeCategoryId = ref(omniEmojiCategories[0]?.id ?? 'smileys')

const activeEmojis = computed(() => {
  const cat = omniEmojiCategories.find((c) => c.id === activeCategoryId.value)
  return cat?.emojis ?? []
})

const buttonClass = computed(() => (props.buttonSize === 'sm' ? 'h-7 w-7 text-base' : 'h-8 w-8 text-lg'))
const iconClass = computed(() => (props.buttonSize === 'sm' ? 'text-sm' : ''))

const panelPositionClass = computed(() => {
  if (props.placement === 'bottom') {
    return 'left-0 top-full mt-1'
  }
  return 'bottom-full right-0 mb-1'
})

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
  if (target instanceof Element && rootEl.value?.contains(target)) {
    return
  }
  open.value = false
}

watch(open, (isOpen) => {
  if (isOpen) {
    document.addEventListener('mousedown', onDocumentMouseDown)
  } else {
    document.removeEventListener('mousedown', onDocumentMouseDown)
  }
})

onUnmounted(() => {
  document.removeEventListener('mousedown', onDocumentMouseDown)
})
</script>
