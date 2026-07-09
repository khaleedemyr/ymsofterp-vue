<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

const props = defineProps({
  schedules: Object,
  tab: String,
  stats: { type: Object, default: () => ({}) },
});

const isPastTab = computed(() => props.tab === 'past');

function setTab(t) {
  router.get(route('just-academy.my-training.index'), { tab: t }, { preserveState: true });
}

function formatDateParts(value) {
  if (!value) return { day: '—', month: '', weekday: '' };
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return { day: '—', month: '', weekday: '' };
  return {
    day: d.toLocaleDateString('id-ID', { day: '2-digit' }),
    month: d.toLocaleDateString('id-ID', { month: 'short' }),
    weekday: d.toLocaleDateString('id-ID', { weekday: 'short' }),
  };
}

function formatTimeRange(start, end) {
  const fmt = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  };
  return `${fmt(start)} – ${fmt(end)}`;
}

function formatDateLong(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

function statusBadgeClass(card) {
  if (!card) return 'bg-slate-100 text-slate-600';
  if (card.is_live) return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200';
  if (card.status_label === 'Selesai') return 'bg-indigo-100 text-indigo-800';
  if (card.status_label === 'Sedang dikerjakan') return 'bg-amber-100 text-amber-800';
  if (card.status_label === 'Sudah check-in') return 'bg-sky-100 text-sky-800';
  if (card.status_label === 'Tidak hadir') return 'bg-rose-100 text-rose-800';
  if (card.is_past) return 'bg-slate-100 text-slate-600';
  return 'bg-violet-100 text-violet-800';
}

function dateBoxClass(card) {
  if (!card) return 'from-indigo-500 to-violet-600';
  if (card.is_live) return 'from-emerald-500 to-teal-600';
  if (card.is_today && !card.is_past) return 'from-amber-500 to-orange-600';
  if (card.is_past) return 'from-slate-500 to-slate-600';
  return 'from-indigo-500 to-violet-600';
}

function progressBarClass(card) {
  if (!card) return 'from-indigo-500 to-violet-600';
  if (card.progress_percent >= 100) return 'from-emerald-500 to-teal-500';
  if (card.is_live) return 'from-emerald-500 to-teal-500';
  return 'from-indigo-500 to-violet-600';
}
</script>

<template>
  <JaLayout title="Training Saya" subtitle="Undangan, jadwal aktif, dan riwayat training Anda" icon="fa-solid fa-user-graduate" narrow>
    <!-- Stat cards -->
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
      <div class="relative overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-white p-4 shadow-sm">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-indigo-600/80">Mendatang</p>
            <p class="mt-1 text-3xl font-bold text-indigo-700">{{ stats?.upcoming ?? 0 }}</p>
            <p class="mt-1 text-xs text-slate-500">Jadwal yang belum selesai</p>
          </div>
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600">
            <i class="fa-solid fa-calendar-day" />
          </div>
        </div>
      </div>

      <div class="relative overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-sm">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-emerald-600/80">Riwayat</p>
            <p class="mt-1 text-3xl font-bold text-emerald-700">{{ stats?.past ?? 0 }}</p>
            <p class="mt-1 text-xs text-slate-500">Training yang sudah lewat</p>
          </div>
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-600">
            <i class="fa-solid fa-clock-rotate-left" />
          </div>
        </div>
      </div>

      <div class="relative overflow-hidden rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-white p-4 shadow-sm">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-amber-700/80">Total</p>
            <p class="mt-1 text-3xl font-bold text-amber-700">{{ stats?.total ?? 0 }}</p>
            <p class="mt-1 text-xs text-slate-500">Semua undangan training</p>
          </div>
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-600/10 text-amber-600">
            <i class="fa-solid fa-graduation-cap" />
          </div>
        </div>
      </div>
    </div>

    <!-- Tab switcher -->
    <div class="mb-6 inline-flex rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
      <button
        type="button"
        class="rounded-xl px-5 py-2.5 text-sm font-semibold transition"
        :class="!isPastTab ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md' : 'text-slate-600 hover:text-slate-800'"
        @click="setTab('upcoming')"
      >
        <i class="fa-solid fa-bolt mr-1.5" />
        Mendatang
      </button>
      <button
        type="button"
        class="rounded-xl px-5 py-2.5 text-sm font-semibold transition"
        :class="isPastTab ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md' : 'text-slate-600 hover:text-slate-800'"
        @click="setTab('past')"
      >
        <i class="fa-solid fa-history mr-1.5" />
        Riwayat
      </button>
    </div>

    <!-- Schedule list -->
    <div class="space-y-4">
      <Link
        v-for="s in schedules.data"
        :key="s.id"
        :href="route('just-academy.my-training.show', s.id)"
        class="group block overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-100/50"
      >
        <div class="flex flex-col sm:flex-row">
          <!-- Date badge -->
          <div
            class="flex shrink-0 flex-row items-center gap-3 bg-gradient-to-br px-5 py-4 text-white sm:w-28 sm:flex-col sm:justify-center sm:gap-0 sm:py-6"
            :class="dateBoxClass(s.card)"
          >
            <p class="text-[11px] font-medium uppercase tracking-wider opacity-90 sm:mb-1">
              {{ formatDateParts(s.start_at).weekday }}
            </p>
            <p class="text-3xl font-bold leading-none">{{ formatDateParts(s.start_at).day }}</p>
            <p class="text-sm font-medium capitalize opacity-95">{{ formatDateParts(s.start_at).month }}</p>
          </div>

          <!-- Content -->
          <div class="min-w-0 flex-1 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                    :class="statusBadgeClass(s.card)"
                  >
                    <span v-if="s.card?.is_live" class="mr-1.5 inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                    {{ s.card?.status_label || 'Terjadwal' }}
                  </span>
                  <span v-if="s.card?.is_today && !s.card?.is_past" class="text-[11px] font-medium text-amber-600">
                    Hari ini
                  </span>
                </div>
                <h3 class="truncate text-lg font-bold text-slate-800 group-hover:text-indigo-700">
                  {{ s.title }}
                </h3>
                <p class="mt-0.5 text-sm text-slate-500">{{ s.program?.title }}</p>
              </div>
              <div class="hidden shrink-0 text-slate-300 transition group-hover:text-indigo-400 sm:block">
                <i class="fa-solid fa-chevron-right text-lg" />
              </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
              <span class="inline-flex items-center gap-1.5">
                <i class="fa-regular fa-clock text-slate-400" />
                {{ formatTimeRange(s.start_at, s.end_at) }}
              </span>
              <span v-if="s.outlet?.nama_outlet" class="inline-flex items-center gap-1.5">
                <i class="fa-solid fa-store text-slate-400" />
                {{ s.outlet.nama_outlet }}
              </span>
              <span v-if="s.location" class="inline-flex items-center gap-1.5">
                <i class="fa-solid fa-location-dot text-slate-400" />
                {{ s.location }}
              </span>
            </div>

            <p class="mt-1 text-xs text-slate-400">{{ formatDateLong(s.start_at) }}</p>

            <!-- Progress -->
            <div v-if="s.card?.steps_total > 0" class="mt-4">
              <div class="mb-1.5 flex items-center justify-between text-xs">
                <span class="font-medium text-slate-600">Progress training</span>
                <span class="font-semibold text-slate-700">{{ s.card.progress_percent }}%</span>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full bg-gradient-to-r transition-all duration-500"
                  :class="progressBarClass(s.card)"
                  :style="{ width: `${Math.min(100, s.card.progress_percent)}%` }"
                />
              </div>
            </div>

            <!-- Chips -->
            <div class="mt-3 flex flex-wrap gap-2">
              <span
                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-medium"
                :class="s.card?.checked_in ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
              >
                <i :class="s.card?.checked_in ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle'" />
                {{ s.card?.checked_in ? 'Sudah check-in' : 'Belum check-in' }}
              </span>
              <span v-if="s.card?.materials_total > 0" class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1 text-[11px] font-medium text-blue-700">
                <i class="fa-solid fa-book-open" />
                Materi {{ s.card.materials_completed }}/{{ s.card.materials_total }}
              </span>
              <span v-if="s.card?.quizzes_total > 0" class="inline-flex items-center gap-1 rounded-lg bg-violet-50 px-2.5 py-1 text-[11px] font-medium text-violet-700">
                <i class="fa-solid fa-circle-question" />
                Quiz {{ s.card.quizzes_completed }}/{{ s.card.quizzes_total }}
                <span v-if="s.card.quizzes_passed > 0" class="text-emerald-600">· {{ s.card.quizzes_passed }} lulus</span>
              </span>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
              <span class="text-sm font-semibold text-indigo-600 group-hover:text-indigo-700">
                {{ s.card?.action_label || 'Lihat detail' }}
                <i class="fa-solid fa-arrow-right ml-1 text-xs transition group-hover:translate-x-0.5" />
              </span>
            </div>
          </div>
        </div>
      </Link>

      <!-- Empty state -->
      <div
        v-if="!schedules.data?.length"
        :class="[jaUi.card, 'px-6 py-14 text-center']"
      >
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-2xl text-indigo-500">
          <i :class="isPastTab ? 'fa-solid fa-clock-rotate-left' : 'fa-solid fa-inbox'" />
        </div>
        <p class="text-base font-semibold text-slate-700">
          {{ isPastTab ? 'Belum ada riwayat training' : 'Belum ada jadwal mendatang' }}
        </p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
          {{ isPastTab
            ? 'Training yang sudah selesai akan muncul di sini beserta progress Anda.'
            : 'Undangan training dari trainer akan tampil di halaman ini.' }}
        </p>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="schedules.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-1">
      <Link
        v-for="(link, i) in schedules.links"
        :key="i"
        :href="link.url || '#'"
        class="rounded-lg px-3 py-2 text-sm font-medium transition"
        :class="[
          link.active ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50',
          !link.url ? 'pointer-events-none opacity-40' : '',
        ]"
        v-html="link.label"
      />
    </div>
  </JaLayout>
</template>
