<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
  stats: Object,
  upcomingSchedules: Array,
  mySchedules: Array,
});
</script>

<template>
  <AppLayout title="Just Academy">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="mb-8">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-graduation-cap text-indigo-600"></i>
          Just Academy
        </h1>
        <p class="text-sm text-gray-600 mt-1">Platform training offline perusahaan.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-2xl shadow p-6">
          <p class="text-sm text-gray-500">Program aktif</p>
          <p class="text-3xl font-bold text-indigo-600">{{ stats?.programs_published ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-6">
          <p class="text-sm text-gray-500">Jadwal mendatang</p>
          <p class="text-3xl font-bold text-emerald-600">{{ stats?.schedules_upcoming ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow p-6">
          <p class="text-sm text-gray-500">Training saya</p>
          <p class="text-3xl font-bold text-amber-600">{{ stats?.my_upcoming ?? 0 }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="font-semibold">Jadwal mendatang</h2>
            <Link :href="route('just-academy.schedules.index')" class="text-indigo-600 text-sm">Lihat semua</Link>
          </div>
          <div v-if="!upcomingSchedules?.length" class="text-gray-500 text-sm">Belum ada jadwal.</div>
          <ul v-else class="space-y-3">
            <li v-for="s in upcomingSchedules" :key="s.id" class="border rounded-xl p-3">
              <p class="font-medium">{{ s.title }}</p>
              <p class="text-xs text-gray-500">{{ s.program?.title }} · {{ s.start_at }}</p>
            </li>
          </ul>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="font-semibold">Training saya</h2>
            <Link :href="route('just-academy.my-training.index')" class="text-indigo-600 text-sm">Buka</Link>
          </div>
          <div v-if="!mySchedules?.length" class="text-gray-500 text-sm">Belum ada undangan training.</div>
          <ul v-else class="space-y-3">
            <li v-for="s in mySchedules" :key="s.id" class="border rounded-xl p-3">
              <Link :href="route('just-academy.my-training.show', s.id)" class="font-medium text-indigo-700 hover:underline">{{ s.title }}</Link>
              <p class="text-xs text-gray-500">{{ s.start_at }}</p>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
