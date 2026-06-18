<script setup>
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

defineProps({ schedule: Object, success: Boolean, message: String });
</script>

<template>
  <JaLayout title="Check-in" icon="fa-solid fa-qrcode" narrow>
    <div :class="[jaUi.card, jaUi.cardBody, 'text-center']">
      <div
        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl"
        :class="success ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
      >
        <i class="fa-solid text-3xl" :class="success ? 'fa-circle-check' : 'fa-circle-xmark'" />
      </div>
      <h1 class="mb-2 text-xl font-bold text-slate-800">{{ success ? 'Check-in Berhasil' : 'Check-in Gagal' }}</h1>
      <p class="mb-4 text-slate-600">{{ message }}</p>
      <p v-if="schedule" class="text-sm font-medium text-slate-800">{{ schedule.title }}</p>
    </div>
  </JaLayout>
</template>
