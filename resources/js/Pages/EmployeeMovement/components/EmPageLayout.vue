<script setup>
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  showBack: { type: Boolean, default: false },
});

defineEmits(['back']);
</script>

<template>
  <div class="em-page">
    <div class="em-page-inner">
      <header v-if="title || $slots.actions" class="em-page-header">
        <div class="em-page-header-row">
          <div class="em-page-header-text">
            <button
              v-if="showBack"
              type="button"
              class="em-back-btn"
              @click="$emit('back')"
            >
              <i class="fas fa-arrow-left"></i>
              <span>Kembali</span>
            </button>
            <h1 class="em-page-title">{{ title }}</h1>
            <p v-if="subtitle" class="em-page-subtitle">{{ subtitle }}</p>
          </div>
          <div v-if="$slots.actions" class="em-page-actions">
            <slot name="actions" />
          </div>
        </div>
      </header>
      <slot />
    </div>
  </div>
</template>
