<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
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

const monthTitle = computed(() => {
  const d = new Date(props.year, props.month - 1, 1);
  return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

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
  router.get(route('fb-product-calibration.create'), { date: dateStr });
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

function mountDayCellExtras(info) {
  const frame = info.el.querySelector('.fc-daygrid-day-frame');
  if (!frame || frame.querySelector('.fbc-fc-add-btn')) return;

  const dateStr = info.el.getAttribute('data-date') || '';
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'fbc-fc-add-btn';
  btn.title = 'Tambah jadwal calibration';
  btn.textContent = '+';
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    goCreate(dateStr);
  });
  frame.appendChild(btn);
}

function resetCalendarEvents() {
  if (!calendarApi) return;
  calendarApi.removeAllEvents();
  (props.calendarEvents || []).forEach((event) => calendarApi.addEvent(event));
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
    dayCellDidMount: mountDayCellExtras,
    dateClick(info) {
      goCreate(info.dateStr);
    },
    eventClick(info) {
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
}

function syncCalendar() {
  if (!calendarApi) return;
  calendarApi.gotoDate(initialDateStr());
  resetCalendarEvents();
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
    text: `Hapus calibration ${detail.event?.outlet_name || ''}?`,
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

onMounted(() => {
  loadCalendarCss();
  nextTick(buildCalendar);
  if (page.props.flash?.success) {
    Swal.fire({ icon: 'success', title: 'Berhasil', text: page.props.flash.success, timer: 2000, showConfirmButton: false });
  }
});

watch(() => [props.year, props.month, props.calendarEvents], () => nextTick(syncCalendar), { deep: true });

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
          :href="route('fb-product-calibration.create')"
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
              v-if="detail.event?.status !== 'completed'"
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
:deep(.fbc-fc-add-btn) {
  position: absolute;
  top: 4px;
  right: 4px;
  z-index: 2;
  width: 22px;
  height: 22px;
  border-radius: 9999px;
  border: 1px solid #c4b5fd;
  background: #ede9fe;
  color: #6d28d9;
  font-size: 14px;
  line-height: 1;
  cursor: pointer;
}
:deep(.fbc-fc-add-btn:hover) {
  background: #ddd6fe;
}
:deep(.fc-daygrid-day-frame) {
  position: relative;
  min-height: 88px;
}
</style>
