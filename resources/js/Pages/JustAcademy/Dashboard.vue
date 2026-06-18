<script setup>
import { Link } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

defineProps({
  stats: Object,
  upcomingSchedules: Array,
  mySchedules: Array,
  myPastSchedules: { type: Array, default: () => [] },
});

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
  <JaLayout title="Just Academy" subtitle="Platform training offline perusahaan" icon="fa-solid fa-graduation-cap">
    <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
      <div :class="[jaUi.card, jaUi.cardBody]">
        <p class="text-sm text-slate-500">Program aktif</p>
        <p class="mt-1 text-3xl font-bold text-indigo-600">{{ stats?.programs_published ?? 0 }}</p>
      </div>
      <div :class="[jaUi.card, jaUi.cardBody]">
        <p class="text-sm text-slate-500">Training plan mendatang</p>
        <p class="mt-1 text-3xl font-bold text-emerald-600">{{ stats?.schedules_upcoming ?? 0 }}</p>
      </div>
      <div :class="[jaUi.card, jaUi.cardBody]">
        <p class="text-sm text-slate-500">Training saya</p>
        <p class="mt-1 text-3xl font-bold text-amber-600">{{ stats?.my_upcoming ?? 0 }}</p>
        <p v-if="stats?.my_total > stats?.my_upcoming" class="mt-1 text-xs text-slate-400">
          +{{ stats.my_total - stats.my_upcoming }} riwayat
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div :class="[jaUi.card, jaUi.cardBody]">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Training plan mendatang</h2>
          <Link :href="route('just-academy.schedules.index')" :class="jaUi.btnLink">Lihat kalender</Link>
        </div>
        <div v-if="!upcomingSchedules?.length" class="text-sm text-slate-500">Belum ada training plan.</div>
        <ul v-else class="space-y-3">
          <li v-for="s in upcomingSchedules" :key="s.id" class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
            <p class="font-medium text-slate-800">{{ s.title }}</p>
            <p class="text-xs text-slate-500">{{ s.program?.title }} · {{ formatScheduleWhen(s.start_at) }}</p>
          </li>
        </ul>
      </div>

      <div :class="[jaUi.card, jaUi.cardBody]">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Training saya</h2>
          <Link :href="route('just-academy.my-training.index')" :class="jaUi.btnLink">Buka</Link>
        </div>
        <div v-if="!mySchedules?.length && !myPastSchedules?.length" class="text-sm text-slate-500">
          Belum ada undangan training untuk akun Anda.
        </div>
        <ul v-else-if="mySchedules?.length" class="space-y-3">
          <li v-for="s in mySchedules" :key="s.id" class="rounded-xl border border-slate-100 bg-slate-50/50 p-3 transition hover:border-indigo-200 hover:bg-indigo-50/30">
            <Link :href="route('just-academy.my-training.show', s.id)" class="font-medium text-indigo-700 hover:underline">{{ s.title }}</Link>
            <p class="text-xs text-slate-500">{{ s.program?.title }} · {{ formatScheduleWhen(s.start_at) }}</p>
          </li>
        </ul>
        <div v-else class="space-y-3">
          <p class="text-sm text-slate-500">Tidak ada training mendatang.</p>
          <div v-if="myPastSchedules?.length">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Riwayat terakhir</p>
            <ul class="space-y-2">
              <li v-for="s in myPastSchedules" :key="s.id" class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                <Link :href="route('just-academy.my-training.show', s.id)" class="font-medium text-slate-700 hover:underline">{{ s.title }}</Link>
                <p class="text-xs text-slate-500">{{ formatScheduleWhen(s.start_at) }}</p>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </JaLayout>
</template>
