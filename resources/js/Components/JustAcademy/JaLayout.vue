<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useJustAcademyFlash, jaUi } from '@/composables/useJustAcademyUi';

defineProps({
  title: { type: String, required: true },
  pageTitle: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: 'fa-solid fa-graduation-cap' },
  narrow: { type: Boolean, default: false },
});

useJustAcademyFlash();
</script>

<template>
  <AppLayout :title="pageTitle || title">
    <div :class="jaUi.page">
      <div :class="narrow ? jaUi.containerNarrow : jaUi.container">
        <header :class="jaUi.header">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
              <div v-if="icon" :class="jaUi.iconBadge">
                <i :class="icon" />
              </div>
              <div>
                <h1 :class="jaUi.title">{{ title }}</h1>
                <p v-if="subtitle" :class="jaUi.subtitle">{{ subtitle }}</p>
              </div>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
              <slot name="actions" />
            </div>
          </div>
        </header>
        <slot />
      </div>
    </div>
  </AppLayout>
</template>

<style>
.ja-swal-popup {
  border-radius: 1rem !important;
  padding: 1.5rem !important;
  font-family: inherit;
}

.ja-swal-title {
  font-size: 1.125rem !important;
  font-weight: 700 !important;
  color: #1e293b !important;
}

.ja-swal-text {
  font-size: 0.875rem !important;
  color: #64748b !important;
}

.ja-swal-confirm {
  border-radius: 0.75rem !important;
  background: linear-gradient(to right, #4f46e5, #7c3aed) !important;
  color: #fff !important;
  font-weight: 600 !important;
  font-size: 0.875rem !important;
  padding: 0.625rem 1.25rem !important;
  margin: 0 0.25rem !important;
  box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35) !important;
}

.ja-swal-cancel {
  border-radius: 0.75rem !important;
  background: #f8fafc !important;
  color: #475569 !important;
  font-weight: 600 !important;
  font-size: 0.875rem !important;
  padding: 0.625rem 1.25rem !important;
  margin: 0 0.25rem !important;
  border: 1px solid #e2e8f0 !important;
}
</style>
