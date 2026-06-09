<script setup>
import { ref, onBeforeUnmount } from 'vue';

defineProps({
  label: { type: String, required: true },
  hint: { type: String, required: true },
  example: { type: String, default: '' },
  required: { type: Boolean, default: false },
});

const TOOLTIP_WIDTH = 288;
const GAP = 8;
const VIEWPORT_PADDING = 12;

const showTip = ref(false);
const tipStyle = ref({ top: '0px', left: '0px', width: `${TOOLTIP_WIDTH}px` });
const triggerRef = ref(null);
let scrollParents = [];

function findScrollParents(el) {
  const parents = [];
  let node = el?.parentElement;
  while (node && node !== document.body) {
    const style = window.getComputedStyle(node);
    const overflow = style.overflow + style.overflowY + style.overflowX;
    if (/auto|scroll/.test(overflow)) {
      parents.push(node);
    }
    node = node.parentElement;
  }
  return parents;
}

function updatePosition() {
  const el = triggerRef.value;
  if (!el) return;

  const rect = el.getBoundingClientRect();
  let left = rect.left;
  let top = rect.bottom + GAP;

  if (left + TOOLTIP_WIDTH > window.innerWidth - VIEWPORT_PADDING) {
    left = window.innerWidth - TOOLTIP_WIDTH - VIEWPORT_PADDING;
  }
  if (left < VIEWPORT_PADDING) {
    left = VIEWPORT_PADDING;
  }

  const spaceBelow = window.innerHeight - rect.bottom - GAP - VIEWPORT_PADDING;
  const spaceAbove = rect.top - GAP - VIEWPORT_PADDING;
  const preferBelow = spaceBelow >= 80 || spaceBelow >= spaceAbove;

  if (preferBelow) {
    top = rect.bottom + GAP;
  } else {
    top = Math.max(VIEWPORT_PADDING, rect.top - GAP);
    tipStyle.value = {
      position: 'fixed',
      top: `${top}px`,
      left: `${left}px`,
      width: `${TOOLTIP_WIDTH}px`,
      zIndex: 99999,
      transform: 'translateY(-100%)',
    };
    return;
  }

  tipStyle.value = {
    position: 'fixed',
    top: `${top}px`,
    left: `${left}px`,
    width: `${TOOLTIP_WIDTH}px`,
    zIndex: 99999,
    transform: 'none',
  };
}

function bindScrollListeners() {
  unbindScrollListeners();
  scrollParents = findScrollParents(triggerRef.value);
  scrollParents.forEach((node) => node.addEventListener('scroll', updatePosition, { passive: true }));
  window.addEventListener('scroll', updatePosition, { passive: true });
  window.addEventListener('resize', updatePosition, { passive: true });
}

function unbindScrollListeners() {
  scrollParents.forEach((node) => node.removeEventListener('scroll', updatePosition));
  scrollParents = [];
  window.removeEventListener('scroll', updatePosition);
  window.removeEventListener('resize', updatePosition);
}

function openTip() {
  updatePosition();
  showTip.value = true;
  bindScrollListeners();
}

function closeTip() {
  showTip.value = false;
  unbindScrollListeners();
}

onBeforeUnmount(() => {
  unbindScrollListeners();
});
</script>

<template>
  <div>
    <label class="flex items-start gap-1.5 text-sm font-medium text-gray-700">
      <span>{{ label }}<span v-if="required" class="text-red-500"> *</span></span>
      <button
        ref="triggerRef"
        type="button"
        class="shrink-0 mt-0.5 text-indigo-400 hover:text-indigo-600 focus:outline-none focus:text-indigo-600"
        aria-label="Bantuan pengisian field"
        @mouseenter="openTip"
        @mouseleave="closeTip"
        @focus="openTip"
        @blur="closeTip"
      >
        <i class="fa-solid fa-circle-question text-xs" aria-hidden="true"></i>
      </button>
    </label>
    <p v-if="example" class="text-xs text-gray-400 mt-0.5">Contoh: {{ example }}</p>

    <Teleport to="body">
      <div
        v-if="showTip"
        role="tooltip"
        :style="tipStyle"
        class="rounded-lg bg-gray-900 px-3 py-2.5 text-xs font-normal leading-relaxed text-white shadow-2xl ring-1 ring-black/10"
        @mouseenter="openTip"
        @mouseleave="closeTip"
      >
        {{ hint }}
        <span v-if="example" class="mt-1.5 block border-t border-gray-700 pt-1.5 text-indigo-200">
          Contoh: {{ example }}
        </span>
      </div>
    </Teleport>
  </div>
</template>
