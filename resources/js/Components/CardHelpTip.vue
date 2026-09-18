<template>
  <span
    class="group/help relative inline-flex shrink-0 align-middle"
    @click.stop.prevent
  >
    <button
      type="button"
      class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-300 bg-white text-[10px] font-bold leading-none text-slate-500 hover:border-slate-400 hover:bg-slate-50 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-200"
      :aria-label="ariaLabel"
    >
      ?
    </button>
    <span
      role="tooltip"
      class="pointer-events-none absolute left-0 top-full z-[70] mt-2 w-72 max-w-[min(18rem,70vw)] rounded-xl bg-slate-900 px-3 py-2.5 text-left text-[11px] font-normal normal-case tracking-normal leading-relaxed text-white opacity-0 shadow-xl transition-opacity duration-150 group-hover/help:opacity-100 group-focus-within/help:opacity-100 whitespace-pre-line"
    >
      {{ text }}
    </span>
  </span>
</template>

<script setup>
defineProps({
  text: { type: String, required: true },
  ariaLabel: { type: String, default: 'Penjelasan' },
})
</script>
