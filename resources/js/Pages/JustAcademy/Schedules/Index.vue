<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

const props = defineProps({
  calendarEvents: { type: Array, default: () => [] },
  year: { type: Number, required: true },
  month: { type: Number, required: true },
  filters: { type: Object, default: () => ({}) },
});

const status = ref(props.filters?.status || '');
const calendarEl = ref(null);
let calendarApi = null;

const detail = reactive({ open: false, event: null });

const monthTitle = computed(() => {
  const d = new Date(props.year, props.month - 1, 1);
  return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

function initialDateStr() {
  return `${props.year}-${String(props.month).padStart(2, '0')}-01`;
}

function statusLabel(value) {
  const map = {
    draft: 'Draft',
    published: 'Published',
    ongoing: 'Berlangsung',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
  };
  return map[value] || value;
}

function loadCalendarCss() {
  if (document.getElementById('fc-ja-training-css')) return;
  const core = document.createElement('link');
  core.id = 'fc-ja-training-css';
  core.rel = 'stylesheet';
  core.href = 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.css';
  document.head.appendChild(core);
  const grid = document.createElement('link');
  grid.rel = 'stylesheet';
  grid.href = 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.css';
  document.head.appendChild(grid);
}

function syncCalendar() {
  if (!calendarApi) return;
  const target = initialDateStr();
  const cur = calendarApi.getDate();
  const curStr = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-01`;
  if (curStr !== target) {
    calendarApi.gotoDate(target);
  }
  calendarApi.removeAllEvents();
  calendarApi.addEventSource(props.calendarEvents || []);
}

function buildCalendar() {
  if (!calendarEl.value || calendarApi) return;

  calendarApi = new Calendar(calendarEl.value, {
    plugins: [dayGridPlugin],
    initialView: 'dayGridMonth',
    initialDate: initialDateStr(),
    headerToolbar: false,
    height: 'auto',
    locale: 'id',
    firstDay: 1,
    fixedWeekCount: false,
    dayMaxEvents: 3,
    moreLinkText: (n) => `+${n} lagi`,
    events: props.calendarEvents || [],
    dateClick(info) {
      router.get(route('just-academy.schedules.create'), { start: info.dateStr });
    },
    eventClick(info) {
      info.jsEvent.preventDefault();
      detail.event = {
        title: info.event.title,
        ...(info.event.extendedProps || {}),
      };
      detail.open = true;
    },
    eventDidMount(info) {
      const p = info.event.extendedProps || {};
      const tip = [p.program, p.location, p.start_label, `Status: ${statusLabel(p.status)}`]
        .filter(Boolean)
        .join('\n');
      info.el.setAttribute('title', tip);
    },
  });
  calendarApi.render();
}

function navigateMonth(delta) {
  let y = props.year;
  let m = props.month + delta;
  while (m < 1) {
    m += 12;
    y -= 1;
  }
  while (m > 12) {
    m -= 12;
    y += 1;
  }
  reloadCalendar(y, m);
}

function reloadCalendar(year, month) {
  router.get(
    route('just-academy.schedules.index'),
    { year, month, status: status.value || undefined },
    { preserveState: true, replace: true, only: ['calendarEvents', 'year', 'month', 'filters'] },
  );
}

function applyStatusFilter() {
  reloadCalendar(props.year, props.month);
}

function goThisMonth() {
  const n = new Date();
  reloadCalendar(n.getFullYear(), n.getMonth() + 1);
}

function closeDetail() {
  detail.open = false;
  detail.event = null;
}

onMounted(() => {
  loadCalendarCss();
  nextTick(buildCalendar);
});

watch(
  () => [props.year, props.month, props.calendarEvents],
  () => nextTick(syncCalendar),
  { deep: true },
);

onBeforeUnmount(() => {
  if (calendarApi) {
    calendarApi.destroy();
    calendarApi = null;
  }
});
</script>

<template>
  <JaLayout title="Training Plan" subtitle="Kalender rencana training — klik tanggal untuk tambah" icon="fa-solid fa-calendar-days">
    <template #actions>
      <Link :href="route('just-academy.schedules.create')" :class="jaUi.btnPrimary">
        <i class="fa-solid fa-plus text-xs" /> Training Plan Baru
      </Link>
    </template>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <button type="button" :class="jaUi.btnSecondary" class="!px-3" @click="navigateMonth(-1)">
          <i class="fa-solid fa-chevron-left" />
        </button>
        <h2 class="min-w-[10rem] text-center text-lg font-bold capitalize text-slate-800">{{ monthTitle }}</h2>
        <button type="button" :class="jaUi.btnSecondary" class="!px-3" @click="navigateMonth(1)">
          <i class="fa-solid fa-chevron-right" />
        </button>
        <button type="button" :class="jaUi.btnLink" @click="goThisMonth">Bulan ini</button>
      </div>
      <select v-model="status" :class="jaUi.select" @change="applyStatusFilter">
        <option value="">Semua status</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="ongoing">Berlangsung</option>
        <option value="completed">Selesai</option>
        <option value="cancelled">Dibatalkan</option>
      </select>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-xs">
      <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1">
        <span class="h-3 w-3 rounded-sm border border-slate-500 bg-slate-50" /> Draft
      </span>
      <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1">
        <span class="h-3 w-3 rounded-sm border border-indigo-600 bg-indigo-50" /> Published
      </span>
      <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1">
        <span class="h-3 w-3 rounded-sm border border-emerald-600 bg-emerald-50" /> Berlangsung
      </span>
      <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-1">
        <span class="h-3 w-3 rounded-sm border border-gray-500 bg-gray-100" /> Selesai
      </span>
    </div>

    <p class="mb-3 text-xs text-slate-500">
      <i class="fa-regular fa-hand-pointer mr-1" /> Klik tanggal kosong untuk buat training plan · klik event untuk detail
    </p>

    <div :class="[jaUi.card, jaUi.cardBody]">
      <div ref="calendarEl" class="ja-training-fc" />
    </div>

    <div v-if="detail.open" :class="jaUi.modalOverlay" @click.self="closeDetail">
      <div :class="jaUi.modal" class="!max-w-md">
        <h2 class="text-lg font-bold text-slate-800">{{ detail.event?.title }}</h2>
        <dl class="space-y-2 text-sm text-slate-600">
          <div><dt class="font-medium text-slate-500">Program</dt><dd>{{ detail.event?.program || '—' }}</dd></div>
          <div><dt class="font-medium text-slate-500">Waktu</dt><dd>{{ detail.event?.start_label }} — {{ detail.event?.end_label }}</dd></div>
          <div><dt class="font-medium text-slate-500">Lokasi</dt><dd>{{ detail.event?.location || '—' }}</dd></div>
          <div><dt class="font-medium text-slate-500">Peserta</dt><dd>{{ detail.event?.participants_count ?? 0 }}</dd></div>
          <div><dt class="font-medium text-slate-500">Status</dt><dd class="capitalize">{{ statusLabel(detail.event?.status) }}</dd></div>
        </dl>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="jaUi.btnSecondary" @click="closeDetail">Tutup</button>
          <Link
            v-if="detail.event?.schedule_id"
            :href="route('just-academy.schedules.show', detail.event.schedule_id)"
            :class="jaUi.btnPrimary"
          >
            Buka Detail
          </Link>
        </div>
      </div>
    </div>
  </JaLayout>
</template>

<style>
.ja-training-fc .fc .fc-daygrid-day-frame {
  min-height: 6.5rem;
  cursor: pointer;
}
.ja-training-fc .fc .fc-daygrid-day-frame:hover {
  background-color: rgb(238 242 255 / 0.35);
}
.ja-training-fc .fc .fc-event {
  font-size: 0.72rem;
  line-height: 1.25;
  padding: 2px 4px;
  cursor: pointer;
  border-radius: 6px;
}
</style>
