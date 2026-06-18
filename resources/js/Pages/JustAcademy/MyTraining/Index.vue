<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ schedules: Object, tab: String });

function setTab(t) {
  router.get(route('just-academy.my-training.index'), { tab: t }, { preserveState: true });
}
</script>

<template>
  <AppLayout title="My Training">
    <div class="max-w-4xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6">Training Saya</h1>
      <div class="flex gap-2 mb-6">
        <button type="button" class="px-4 py-2 rounded-xl" :class="tab === 'upcoming' ? 'bg-indigo-600 text-white' : 'bg-gray-100'" @click="setTab('upcoming')">Mendatang</button>
        <button type="button" class="px-4 py-2 rounded-xl" :class="tab === 'past' ? 'bg-indigo-600 text-white' : 'bg-gray-100'" @click="setTab('past')">Riwayat</button>
      </div>
      <div class="space-y-3">
        <Link
          v-for="s in schedules.data"
          :key="s.id"
          :href="route('just-academy.my-training.show', s.id)"
          class="block bg-white rounded-2xl shadow p-4 hover:ring-2 ring-indigo-200"
        >
          <p class="font-semibold">{{ s.title }}</p>
          <p class="text-sm text-gray-500">{{ s.program?.title }} · {{ s.start_at }}</p>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
