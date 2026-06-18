<script setup>
import { Link, router } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

defineProps({ schedules: Object, tab: String });

function setTab(t) {
  router.get(route('just-academy.my-training.index'), { tab: t }, { preserveState: true });
}
</script>

<template>
  <JaLayout title="Training Saya" subtitle="Jadwal training yang Anda ikuti" icon="fa-solid fa-user-graduate" narrow>
    <div class="mb-6 flex gap-2">
      <button
        type="button"
        class="rounded-xl px-4 py-2 text-sm font-medium transition"
        :class="tab === 'upcoming' ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200'"
        @click="setTab('upcoming')"
      >
        Mendatang
      </button>
      <button
        type="button"
        class="rounded-xl px-4 py-2 text-sm font-medium transition"
        :class="tab === 'past' ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200'"
        @click="setTab('past')"
      >
        Riwayat
      </button>
    </div>
    <div class="space-y-3">
      <Link
        v-for="s in schedules.data"
        :key="s.id"
        :href="route('just-academy.my-training.show', s.id)"
        :class="[jaUi.card, 'block p-4 transition hover:border-indigo-200 hover:shadow-md']"
      >
        <p class="font-semibold text-slate-800">{{ s.title }}</p>
        <p class="text-sm text-slate-500">{{ s.program?.title }} · {{ s.start_at }}</p>
      </Link>
      <p v-if="!schedules.data?.length" :class="jaUi.empty">Belum ada training.</p>
    </div>
  </JaLayout>
</template>
