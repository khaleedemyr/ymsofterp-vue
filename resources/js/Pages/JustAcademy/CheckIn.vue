<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ schedule: Object, success: Boolean, message: String });
</script>

<template>
  <AppLayout title="Check-in">
    <div class="max-w-md mx-auto py-16 px-4 text-center">
      <div class="bg-white rounded-2xl shadow p-8">
        <i class="fa-solid fa-qr-code text-4xl mb-4" :class="success ? 'text-emerald-600' : 'text-red-600'"></i>
        <h1 class="text-xl font-bold mb-2">{{ success ? 'Check-in Berhasil' : 'Check-in Gagal' }}</h1>
        <p class="text-gray-600 mb-4">{{ message }}</p>
        <p v-if="schedule" class="text-sm font-medium">{{ schedule.title }}</p>
      </div>
    </div>
  </AppLayout>
</template>
