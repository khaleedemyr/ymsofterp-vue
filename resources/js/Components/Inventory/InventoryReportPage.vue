<script setup>
import { computed } from 'vue';

const props = defineProps({
  eyebrow: { type: String, default: 'Inventory' },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  variant: { type: String, default: 'outlet' },
});

const heroClass = computed(() => {
  if (props.variant === 'warehouse') {
    return 'from-indigo-950 via-indigo-900 to-violet-900';
  }
  return 'from-sky-950 via-sky-900 to-teal-900';
});

const accentTextClass = computed(() => {
  return props.variant === 'warehouse' ? 'text-indigo-200/90' : 'text-sky-200/90';
});
</script>

<template>
  <div class="w-full min-h-full py-5 px-3 sm:px-4 lg:px-5 xl:px-6">
    <div
      class="mb-5 rounded-2xl bg-gradient-to-br p-5 text-white shadow-xl sm:p-6"
      :class="heroClass"
    >
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 flex-1">
          <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" :class="accentTextClass">
            {{ eyebrow }}
          </p>
          <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ title }}</h1>
          <p v-if="subtitle" class="mt-1.5 max-w-4xl text-sm leading-relaxed text-white/85">
            {{ subtitle }}
          </p>
        </div>
        <div v-if="$slots.badges" class="flex shrink-0 flex-wrap items-center gap-2">
          <slot name="badges" />
        </div>
      </div>
      <div v-if="$slots.stats" class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <slot name="stats" />
      </div>
    </div>

    <div
      v-if="$slots.filters"
      class="mb-5 rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm backdrop-blur sm:p-5"
    >
      <slot name="filters" />
    </div>

    <slot />
  </div>
</template>
