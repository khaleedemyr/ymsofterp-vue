<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  calendarEvents: { type: Array, default: () => [] },
  year: { type: Number, required: true },
  month: { type: Number, required: true },
});

const page = usePage();

const calendarEl = ref(null);
let calendarApi = null;
const detail = reactive({ open: false, event: null });
const holidays = ref([]);

const todayStr = computed(() => formatDateYmd(new Date()));

const holidayMap = computed(() => {
  const map = {};
  holidays.value.forEach((h) => {
    const key = normalizeHolidayDate(h.tgl_libur);
    if (key) map[key] = h.keterangan || 'Libur nasional';
  });
  return map;
});

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

function normalizeHolidayDate(value) {
  if (!value) return '';
  const raw = String(value).trim();
  const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
  return match ? match[1] : raw.slice(0, 10);
}

function isHolidayDate(dateStr) {
  return Boolean(holidayMap.value[dateStr]);
}

function isPastDate(dateStr) {
  return dateStr < todayStr.value;
}

function canCreateOnDate(dateStr) {
  return dateStr && !isPastDate(dateStr);
}

function initialDateStr() {
  return `${props.year}-${String(props.month).padStart(2, '0')}-01`;
}

function statusLabel(status) {
  const map = {
    scheduled: 'Scheduled',
    in_progress: 'In Progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
  };
  return map[status] || status;
}

function goCreate(dateStr) {
  if (!canCreateOnDate(dateStr)) return;
  router.get(route('fb-product-calibration.create'), { date: dateStr });
}

async function fetchHolidays() {
  try {
    const { data } = await axios.get('/api/holidays');
    holidays.value = (Array.isArray(data) ? data : []).map((h) => ({
      ...h,
      tgl_libur: normalizeHolidayDate(h.tgl_libur),
    }));
  } catch {
    holidays.value = [];
  }
}

function loadCalendarCss() {
  if (document.getElementById('fc-fbc-css')) return;
  const core = document.createElement('link');
  core.id = 'fc-fbc-css';
  core.rel = 'stylesheet';
  core.href = 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.css';
  document.head.appendChild(core);
  const grid = document.createElement('link');
  grid.rel = 'stylesheet';
  grid.href = 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.css';
  document.head.appendChild(grid);
}

function getDayCellDateStr(el, fallbackDate) {
  const fromAttr = el.getAttribute('data-date') || el.dataset?.date || '';
  if (fromAttr) return fromAttr;
  if (fallbackDate) return formatDateYmd(fallbackDate);
  return '';
}

function upsertHolidayLabel(frame, dateStr) {
  const holidayName = holidayMap.value[dateStr];
  const existing = frame.querySelector('.fbc-fc-holiday-label');

  if (!holidayName) {
    existing?.remove();
    return;
  }

  if (existing) {
    existing.textContent = holidayName;
    return;
  }

  const label = document.createElement('div');
  label.className = 'fbc-fc-holiday-label';
  label.textContent = holidayName;
  frame.appendChild(label);
}

function applyHolidayClass(el, dateStr) {
  const frame = el.querySelector('.fc-daygrid-day-frame');
  const holidayName = holidayMap.value[dateStr];
  const isHoliday = Boolean(holidayName);

  el.classList.toggle('fbc-fc-holiday-day', isHoliday);
  if (frame) {
    frame.classList.toggle('fbc-fc-holiday-frame', isHoliday);
    if (isHoliday) {
      frame.title = holidayName;
      upsertHolidayLabel(frame, dateStr);
    } else {
      frame.removeAttribute('title');
      frame.querySelector('.fbc-fc-holiday-label')?.remove();
    }
  }
}

function applyHolidayClassesToDom() {
  if (!calendarEl.value) return;
  calendarEl.value.querySelectorAll('.fc-daygrid-day').forEach((el) => {
    const dateStr = getDayCellDateStr(el);
    if (dateStr) applyHolidayClass(el, dateStr);
  });
}

function buildHolidayEvents() {
  return holidays.value
    .map((h) => {
      const date = normalizeHolidayDate(h.tgl_libur);
      if (!date) return null;

      return {
        id: `fbc-holiday-${h.id || date}`,
        start: date,
        allDay: true,
        display: 'background',
        backgroundColor: '#fecaca',
        borderColor: '#ef4444',
        classNames: ['fbc-fc-holiday-event'],
        extendedProps: {
          isHoliday: true,
          keterangan: h.keterangan || 'Libur nasional',
        },
      };
    })
    .filter(Boolean);
}

function buildCalendarEvents() {
  return [...(props.calendarEvents || []), ...buildHolidayEvents()];
}

function mountDayCellExtras(info) {
  const frame = info.el.querySelector('.fc-daygrid-day-frame');
  if (!frame) return;

  const dateStr = getDayCellDateStr(info.el, info.date);
  applyHolidayClass(info.el, dateStr);

  frame.querySelector('.fbc-fc-add-btn')?.remove();

  if (!canCreateOnDate(dateStr)) return;

  const top = frame.querySelector('.fc-daygrid-day-top');
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'fbc-fc-add-btn';
  btn.title = 'Tambah jadwal calibration';
  btn.setAttribute('aria-label', `Tambah jadwal ${dateStr}`);
  btn.textContent = '+';
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    goCreate(dateStr);
  });

  if (top) {
    top.insertBefore(btn, top.firstChild);
  } else {
    frame.appendChild(btn);
  }
}

function resetCalendarEvents() {
  if (!calendarApi) return;
  calendarApi.removeAllEvents();
  buildCalendarEvents().forEach((event) => calendarApi.addEvent(event));
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
    dayMaxEvents: 4,
    moreLinkText: (n) => `+${n} lagi`,
    dayCellClassNames(arg) {
      const key = arg.dateStr || formatDateYmd(arg.date);
      return isHolidayDate(key) ? ['fbc-fc-holiday-day'] : [];
    },
    dayCellDidMount: mountDayCellExtras,
    dateClick(info) {
      if (!canCreateOnDate(info.dateStr)) return;
      goCreate(info.dateStr);
    },
    eventClick(info) {
      if (info.event.extendedProps?.isHoliday) return;
      info.jsEvent.preventDefault();
      detail.event = {
        title: info.event.title,
        ...(info.event.extendedProps || {}),
      };
      detail.open = true;
    },
  });
  calendarApi.render();
  resetCalendarEvents();
  nextTick(applyHolidayClassesToDom);
}

function syncCalendar() {
  if (!calendarApi) return;
  calendarApi.gotoDate(initialDateStr());
  resetCalendarEvents();
  nextTick(applyHolidayClassesToDom);
}

function navigateMonth(delta) {
  let y = props.year;
  let m = props.month + delta;
  while (m < 1) { m += 12; y -= 1; }
  while (m > 12) { m -= 12; y += 1; }
  router.get(route('fb-product-calibration.index'), { year: y, month: m }, {
    preserveState: true,
    replace: true,
    only: ['calendarEvents', 'year', 'month'],
  });
}

function goThisMonth() {
  const n = new Date();
  router.get(route('fb-product-calibration.index'), {
    year: n.getFullYear(),
    month: n.getMonth() + 1,
  }, { preserveState: true, replace: true, only: ['calendarEvents', 'year', 'month'] });
}

function closeDetail() {
  detail.open = false;
  detail.event = null;
}

function confirmDelete() {
  const id = detail.event?.calibration_id;
  if (!id) return;
  Swal.fire({
    title: 'Hapus jadwal?',
    text: `Jadwal ${detail.event?.outlet_name || ''} akan dihapus dari kalender. Data tetap tersimpan di sistem.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('fb-product-calibration.destroy', id), {
        onSuccess: () => closeDetail(),
      });
    }
  });
}

onMounted(async () => {
  loadCalendarCss();
  await fetchHolidays();
  nextTick(buildCalendar);
  if (page.props.flash?.success) {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: page.props.flash.success, timer: 2000, showConfirmButton: false });
  }
});

watch(() => [props.year, props.month, props.calendarEvents], () => nextTick(syncCalendar), { deep: true });

watch(holidays, () => {
  nextTick(() => {
    if (calendarApi) {
      resetCalendarEvents();
      calendarApi.render();
    }
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
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-utensils text-violet-600"></i>
            F&B Product Calibration
          </h1>
          <p class="text-sm text-gray-500 mt-1">Kalender jadwal calibration product F&B per outlet</p>
        </div>
        <Link
          :href="route('fb-product-calibration.create', { date: todayStr })"
          class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg shadow hover:bg-violet-700"
        >
          <i class="fa-solid fa-plus"></i> Tambah Jadwal
        </Link>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold" @click="navigateMonth(-1)">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <h2 class="text-lg font-bold text-gray-900 min-w-[12rem] text-center capitalize">{{ monthTitle }}</h2>
          <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold" @click="navigateMonth(1)">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
          <button type="button" class="ml-2 rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white" @click="goThisMonth">
            Bulan ini
          </button>
        </div>
        <div class="flex flex-wrap gap-3 text-xs">
          <span class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 bg-white">
            <span class="h-3 w-3 rounded-sm bg-violet-600"></span> Scheduled
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 bg-white">
            <span class="h-3 w-3 rounded-sm bg-blue-600"></span> In Progress
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 bg-white">
            <span class="h-3 w-3 rounded-sm bg-green-600"></span> Completed
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 bg-white">
            <span class="h-3 w-3 rounded-sm bg-red-300 border border-red-400"></span> Hari Libur
          </span>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <div ref="calendarEl" class="fbc-calendar"></div>
      </div>
    </div>

    <div v-if="detail.open" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="closeDetail">
      <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="px-5 py-4 border-b bg-gradient-to-r from-violet-50 to-white flex justify-between items-start">
          <div>
            <h3 class="text-lg font-bold text-gray-900">{{ detail.event?.outlet_name || detail.event?.title }}</h3>
            <p class="text-sm text-gray-600">Conducted by: {{ detail.event?.conductor_name || '-' }}</p>
          </div>
          <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeDetail">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
        <div class="p-5 space-y-3 text-sm">
          <div>
            <span class="text-gray-500">Status:</span>
            <span class="ml-2 font-semibold">{{ statusLabel(detail.event?.status) }}</span>
          </div>
          <div>
            <span class="text-gray-500">Products ({{ detail.event?.product_count || 0 }}):</span>
            <ul class="mt-1 list-disc list-inside text-gray-800">
              <li v-for="(p, idx) in (detail.event?.products || [])" :key="idx">{{ p }}</li>
            </ul>
          </div>
          <div class="flex flex-wrap gap-2 pt-2">
            <Link
              :href="route('fb-product-calibration.show', detail.event?.calibration_id)"
              class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
            >
              <i class="fa-solid fa-eye mr-1"></i> Detail
            </Link>
            <Link
              v-if="detail.event?.status !== 'completed'"
              :href="route('fb-product-calibration.edit', detail.event?.calibration_id)"
              class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600"
            >
              <i class="fa-solid fa-pen mr-1"></i> Edit
            </Link>
            <button
              v-if="detail.event?.can_delete"
              type="button"
              class="px-4 py-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200"
              @click="confirmDelete"
            >
              <i class="fa-solid fa-trash mr-1"></i> Hapus
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
:deep(.fc-daygrid-day-top) {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  gap: 4px;
  padding: 4px 6px 0;
  position: relative;
  z-index: 4;
}
:deep(.fc-daygrid-day-number) {
  margin: 0 !important;
  padding: 0 !important;
  line-height: 1.2;
  pointer-events: none;
}
:deep(.fbc-fc-add-btn) {
  position: relative;
  z-index: 10;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: 1px solid #c4b5fd;
  background: #ede9fe;
  color: #6d28d9;
  font-size: 16px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
  pointer-events: auto;
}
:deep(.fbc-fc-add-btn:hover) {
  background: #ddd6fe;
  transform: scale(1.05);
}
:deep(.fbc-fc-holiday-day .fbc-fc-add-btn) {
  background: #fff;
  color: #dc2626;
  border-color: #fca5a5;
}
:deep(.fc-daygrid-day-frame) {
  position: relative;
  min-height: 88px;
}
:deep(.fbc-fc-holiday-day .fc-daygrid-day-frame) {
  background: #fef2f2;
}
:deep(.fbc-fc-holiday-label) {
  position: absolute;
  left: 6px;
  right: 6px;
  bottom: 6px;
  z-index: 3;
  margin-top: 0;
  padding: 2px 4px;
  font-size: 10px;
  line-height: 1.2;
  font-weight: 600;
  color: #b91c1c;
  text-align: left;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  border-radius: 4px;
  background: rgb(255 255 255 / 0.92);
  pointer-events: none;
}
:deep(.fbc-fc-holiday-event) {
  opacity: 0.35;
}
</style>
