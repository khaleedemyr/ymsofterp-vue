<script setup>
import { Link, router } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

defineProps({
  schedules: Object,
  tab: String,
  stats: { type: Object, default: () => ({}) },
});

function setTab(t) {
  router.get(route('just-academy.my-training.index'), { tab: t }, { preserveState: true });
}

function formatScheduleWhen(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<template>
  <JaLayout title="Training Saya" subtitle="Undangan dan jadwal training Anda" icon="fa-solid fa-user-graduate" narrow>
    <div class="mb-6 grid grid-cols-2 gap-3">
      <div :class="[jaUi.card, jaUi.cardBody, '!py-3']">
        <p class="text-xs text-slate-500">Mendatang</p>
        <p class="text-2xl font-bold text-indigo-600">{{ stats?.upcoming ?? 0 }}</p>
      </div>
      <div :class="[jaUi.card, jaUi.cardBody, '!py-3']">
        <p class="text-xs text-slate-500">Total diikuti</p>
        <p class="text-2xl font-bold text-amber-600">{{ stats?.total ?? 0 }}</p>
      </div>
    </div>

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
        <p class="text-sm text-slate-500">
          {{ s.program?.title }}
          <span v-if="s.outlet?.nama_outlet"> · {{ s.outlet.nama_outlet }}</span>
          · {{ formatScheduleWhen(s.start_at) }}
        </p>
      </Link>
      <p v-if="!schedules.data?.length" :class="jaUi.empty">
        {{ tab === 'past' ? 'Belum ada riwayat training.' : 'Belum ada undangan training untuk akun Anda.' }}
      </p>
    </div>
  </JaLayout>
</template>
