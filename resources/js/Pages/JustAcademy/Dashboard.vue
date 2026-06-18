<script setup>
import { Link } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

defineProps({
  stats: Object,
  canManageSchedules: { type: Boolean, default: false },
  upcomingSchedules: Array,
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

function statusLabel(status) {
  const map = {
    draft: 'Draft',
    published: 'Published',
    ongoing: 'Berlangsung',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
  };
  return map[status] || status;
}
</script>

<template>
  <JaLayout
    title="Just Academy"
    subtitle="Ringkasan training perusahaan"
    icon="fa-solid fa-graduation-cap"
  >
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
        <p class="text-sm text-slate-500">Sedang berlangsung</p>
        <p class="mt-1 text-3xl font-bold text-amber-600">{{ stats?.schedules_ongoing ?? 0 }}</p>
      </div>
    </div>

    <div :class="[jaUi.card, jaUi.cardBody]">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="font-semibold text-slate-800">Jadwal training mendatang</h2>
          <p class="mt-1 text-xs text-slate-500">Semua training plan yang dijadwalkan di perusahaan.</p>
        </div>
        <Link
          v-if="canManageSchedules"
          :href="route('just-academy.schedules.index')"
          :class="jaUi.btnLink"
        >
          Kelola kalender
        </Link>
      </div>

      <div v-if="!upcomingSchedules?.length" class="text-sm text-slate-500">Belum ada training plan mendatang.</div>
      <ul v-else class="space-y-3">
        <li
          v-for="s in upcomingSchedules"
          :key="s.id"
          class="rounded-xl border border-slate-100 bg-slate-50/50 p-3"
        >
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
              <Link
                v-if="canManageSchedules"
                :href="route('just-academy.schedules.show', s.id)"
                class="font-medium text-indigo-700 hover:underline"
              >
                {{ s.title }}
              </Link>
              <p v-else class="font-medium text-slate-800">{{ s.title }}</p>
              <p class="mt-1 text-xs text-slate-500">
                {{ s.program?.title || '—' }}
                <span v-if="s.outlet?.nama_outlet"> · {{ s.outlet.nama_outlet }}</span>
                · {{ formatScheduleWhen(s.start_at) }}
              </p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1 text-xs text-slate-500">
              <span class="rounded-full bg-slate-100 px-2 py-0.5 capitalize">{{ statusLabel(s.status) }}</span>
              <span>{{ s.participants_count ?? 0 }} peserta</span>
            </div>
          </div>
        </li>
      </ul>

      <p class="mt-4 text-xs text-slate-500">
        Untuk training yang Anda ikuti, buka menu
        <Link :href="route('just-academy.my-training.index')" class="font-medium text-indigo-600 hover:underline">
          Training Saya
        </Link>.
      </p>
    </div>
  </JaLayout>
</template>
