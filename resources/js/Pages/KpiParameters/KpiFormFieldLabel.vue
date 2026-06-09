<script setup>
defineProps({
  label: { type: String, required: true },
  hint: { type: String, required: true },
  example: { type: String, default: '' },
  required: { type: Boolean, default: false },
});
</script>

<template>
  <div>
    <label class="flex items-start gap-1.5 text-sm font-medium text-gray-700">
      <span>{{ label }}<span v-if="required" class="text-red-500"> *</span></span>
      <span class="relative group shrink-0 cursor-help mt-0.5">
        <i class="fa-solid fa-circle-question text-indigo-400 text-xs" aria-hidden="true"></i>
        <span
          role="tooltip"
          class="pointer-events-none absolute left-1/2 -translate-x-1/2 bottom-full z-[70] mb-2 hidden w-64 rounded-lg bg-gray-900 px-3 py-2 text-xs font-normal leading-relaxed text-white shadow-xl group-hover:block group-focus-within:block"
        >
          {{ hint }}
          <span v-if="example" class="mt-1.5 block border-t border-gray-700 pt-1.5 text-indigo-200">
            Contoh: {{ example }}
          </span>
          <span class="absolute left-1/2 -translate-x-1/2 top-full border-4 border-transparent border-t-gray-900"></span>
        </span>
      </span>
    </label>
    <p v-if="example" class="text-xs text-gray-400 mt-0.5">Contoh: {{ example }}</p>
  </div>
</template>
