<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaConfirmDelete, jaDelete } from '@/composables/useJustAcademyUi';

const props = defineProps({
  calendarEvents: { type: Array, default: () => [] },
  year: { type: Number, required: true },
  month: { type: Number, required: true },
  filters: { type: Object, default: () => ({}) },
});

const status = ref(props.filters?.status || '');
const holidays = ref([]);
const calendarEl = ref(null);
let calendarApi = null;

const detail = reactive({ open: false, event: null });

const holidayMap = computed(() => {
  const map = {};
  holidays.value.forEach((h) => {
    const key = normalizeHolidayDate(h.tgl_libur);
    if (key) map[key] = h.keterangan || 'Libur nasional';
  });
  return map;
});

function normalizeHolidayDate(value) {
  if (!value) return '';
  const raw = String(value).trim();
  const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
  return match ? match[1] : raw.slice(0, 10);
}

function isHolidayDate(dateStr) {
  return Boolean(holidayMap.value[dateStr]);
}

const monthTitle = computed(() => {
  const d = new Date(props.year, props.month - 1, 1);
  return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

function formatDateYmd(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

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

function goCreateTraining(dateStr) {
  router.get(route('just-academy.schedules.create'), { start: dateStr });
}

async function fetchHolidays() {
  try {
    const { data } = await axios.get('/api/holidays');
    holidays.value = Array.isArray(data) ? data : [];
  } catch {
    holidays.value = [];
  }
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

function applyHolidayClass(el, dateStr) {
  const frame = el.querySelector('.fc-daygrid-day-frame');
  const holidayName = holidayMap.value[dateStr];
  const isHoliday = Boolean(holidayName);

  el.classList.toggle('ja-fc-holiday-day', isHoliday);
  if (frame) {
    frame.classList.toggle('ja-fc-holiday-frame', isHoliday);
    if (isHoliday) {
      frame.title = holidayName;
    } else {
      frame.removeAttribute('title');
    }
  }
}

function applyHolidayClassesToDom() {
  if (!calendarEl.value) return;
  calendarEl.value.querySelectorAll('.fc-daygrid-day').forEach((el) => {
    const dateStr = el.getAttribute('data-date') || '';
    if (dateStr) applyHolidayClass(el, dateStr);
  });
}

function mountDayCellExtras(info) {
  const frame = info.el.querySelector('.fc-daygrid-day-frame');
  if (!frame) return;

  const dateStr = info.el.getAttribute('data-date') || formatDateYmd(info.date);
  applyHolidayClass(info.el, dateStr);

  if (frame.querySelector('.ja-fc-add-btn')) return;

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'ja-fc-add-btn';
  btn.title = 'Tambah training plan';
  btn.setAttribute('aria-label', `Tambah training plan ${dateStr}`);
  btn.textContent = '+';
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    goCreateTraining(dateStr);
  });
  frame.appendChild(btn);
}

function resetCalendarEvents() {
  if (!calendarApi) return;
  calendarApi.getEventSources().forEach((source) => source.remove());
  calendarApi.removeAllEvents();
  const events = props.calendarEvents || [];
  if (events.length) {
    calendarApi.addEventSource(events);
  }
}

function syncCalendar() {
  if (!calendarApi) return;
  const target = initialDateStr();
  const cur = calendarApi.getDate();
  const curStr = `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-01`;
  if (curStr !== target) {
    calendarApi.gotoDate(target);
  }
  resetCalendarEvents();
  calendarApi.render();
  nextTick(applyHolidayClassesToDom);
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
    events: [],
    dayCellClassNames(arg) {
      const key = arg.dateStr || formatDateYmd(arg.date);
      return isHolidayDate(key) ? ['ja-fc-holiday-day'] : [];
    },
    dayCellDidMount: mountDayCellExtras,
    dateClick(info) {
      goCreateTraining(info.dateStr);
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
  resetCalendarEvents();
  nextTick(applyHolidayClassesToDom);
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

async function deleteSchedule() {
  const scheduleId = detail.event?.schedule_id;
  if (!scheduleId) return;

  const result = await jaConfirmDelete({
    title: 'Hapus training plan?',
    html: `Training plan <strong>${detail.event?.title || ''}</strong> akan dihapus permanen.`,
    confirmText: 'Ya, hapus',
  });
  if (!result.isConfirmed) return;

  jaDelete(route('just-academy.schedules.destroy', scheduleId), {
    onSuccess: () => closeDetail(),
  });
}

onMounted(async () => {
  loadCalendarCss();
  await fetchHolidays();
  nextTick(buildCalendar);
});

watch(
  () => [props.year, props.month, props.calendarEvents],
  () => nextTick(syncCalendar),
  { deep: true },
);

watch(holidays, () => {
  nextTick(() => {
    calendarApi?.render();
    applyHolidayClassesToDom();
  });
}, { deep: true });

onBeforeUnmount(() => {
  if (calendarApi) {
    calendarApi.destroy();
    calendarApi = null;
  }
});
</script>

<template>
  <JaLayout title="Training Plan" subtitle="Kalender rencana training" icon="fa-solid fa-calendar-days">
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
        <span class="h-3 w-3 rounded-sm bg-red-400" /> Libur nasional
      </span>
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
      <i class="fa-solid fa-plus mr-1 text-indigo-500" /> Tombol <strong>+</strong> di tiap tanggal untuk buat training plan · klik event untuk detail
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
        <div class="flex justify-between gap-2 pt-2">
          <button type="button" :class="jaUi.btnDanger" @click="deleteSchedule">Hapus</button>
          <div class="flex gap-2">
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
    </div>
  </JaLayout>
</template>

<style>
.ja-training-fc .fc .fc-daygrid-day-frame {
  position: relative;
  min-height: 6.5rem;
  cursor: pointer;
}
.ja-training-fc .fc .fc-daygrid-day-frame:not(.ja-fc-holiday-frame):hover {
  background-color: rgb(238 242 255 / 0.35);
}
.ja-training-fc .fc .fc-daygrid-day.ja-fc-holiday-day,
.ja-training-fc .fc .fc-daygrid-day.ja-fc-holiday-day .fc-daygrid-day-frame,
.ja-training-fc .fc .fc-daygrid-day-frame.ja-fc-holiday-frame {
  background-color: #f87171 !important;
}
.ja-training-fc .fc .fc-daygrid-day.ja-fc-holiday-day .fc-daygrid-day-frame:hover,
.ja-training-fc .fc .fc-daygrid-day-frame.ja-fc-holiday-frame:hover {
  background-color: #ef4444 !important;
}
.ja-training-fc .fc .fc-daygrid-day.ja-fc-holiday-day .fc-daygrid-day-number,
.ja-training-fc .fc .fc-daygrid-day-frame.ja-fc-holiday-frame .fc-daygrid-day-number {
  color: #fff !important;
  font-weight: 700;
}
.ja-training-fc .ja-fc-add-btn {
  position: absolute;
  top: 6px;
  left: 6px;
  z-index: 6;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  border: none;
  background: #4f46e5;
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.2);
}
.ja-training-fc .ja-fc-add-btn:hover {
  background: #4338ca;
  transform: scale(1.08);
}
.ja-training-fc .fc .ja-fc-holiday-day .ja-fc-add-btn {
  background: #fff;
  color: #dc2626;
}
.ja-training-fc .fc .ja-fc-holiday-day .ja-fc-add-btn:hover {
  background: #fef2f2;
}
.ja-training-fc .fc .fc-event {
  font-size: 0.72rem;
  line-height: 1.25;
  padding: 2px 4px;
  cursor: pointer;
  border-radius: 6px;
}
.ja-training-fc .fc .fc-daygrid-day-number {
  margin-top: 2px;
  margin-right: 4px;
}
</style>
