<template>
  <AppLayout title="Kalender Ticket">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="relative overflow-hidden rounded-2xl border border-violet-100 bg-gradient-to-r from-violet-600 to-indigo-700 p-6 text-white shadow-lg mb-6">
        <div class="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-2xl font-bold flex items-center gap-2">
              <i class="fa-solid fa-calendar-days text-violet-100"></i>
              Kalender ticket
            </h1>
            <p class="mt-1 text-sm text-violet-100">
              Due date (warna status) & tanggal ticket dibuat (ungu). Klik event untuk buka detail.
            </p>
          </div>
          <button
            type="button"
            @click="goToList"
            class="shrink-0 rounded-xl bg-white/95 px-4 py-2 text-sm font-semibold text-indigo-800 shadow hover:bg-white"
          >
            <i class="fa-solid fa-table-list mr-2"></i>Daftar ticket
          </button>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
            @click="prevMonth"
          >
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <h2 class="text-lg font-bold text-gray-900 min-w-[12rem] text-center capitalize">
            {{ monthTitle }}
          </h2>
          <button
            type="button"
            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
            @click="nextMonth"
          >
            <i class="fa-solid fa-chevron-right"></i>
          </button>
          <button
            type="button"
            class="ml-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700"
            @click="goThisMonth"
          >
            Bulan ini
          </button>
        </div>
        <div class="flex flex-wrap gap-3 text-xs">
          <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1">
            <span class="h-3 w-3 rounded-sm bg-red-100 border border-red-600"></span> Due lewat (belum selesai)
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1">
            <span class="h-3 w-3 rounded-sm bg-indigo-100 border border-indigo-500"></span> Ticket baru (tgl buat)
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1">
            <span class="h-3 w-3 rounded-sm bg-blue-100 border border-blue-600"></span> Open / progres / resolved
          </span>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <div ref="calendarEl" class="ticket-fc-min"></div>
      </div>
    </div>

    <div
      v-if="detailModal.open"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
      @click.self="closeDetailModal"
    >
      <div class="w-full max-w-3xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-3 bg-gradient-to-r from-white to-gray-50">
          <div class="min-w-0">
            <h3 class="text-lg font-bold text-gray-900 truncate">
              <i class="fa-solid fa-ticket mr-2 text-indigo-600"></i>{{ detailModal.ticket?.ticket_number || '-' }}
            </h3>
            <p class="text-sm text-gray-700 truncate">{{ detailModal.ticket?.title_full || '-' }}</p>
          </div>
          <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeDetailModal">
            <i class="fa-solid fa-times text-lg"></i>
          </button>
        </div>

        <div class="p-5 overflow-auto max-h-[calc(90vh-8rem)] space-y-4">
          <div class="flex flex-wrap gap-2">
            <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold', statusBadgeClass(detailModal.ticket?.status_slug)]">
              <i class="fa-solid fa-circle-info mr-1"></i>{{ detailModal.ticket?.status || '-' }}
            </span>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-violet-100 text-violet-800">
              <i class="fa-solid fa-flag mr-1"></i>{{ detailModal.ticket?.priority || '-' }}
            </span>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-cyan-100 text-cyan-800">
              <i class="fa-solid fa-store mr-1"></i>{{ detailModal.ticket?.outlet_name || '-' }}
            </span>
            <span
              v-if="detailModal.ticket?.overdue"
              class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-red-100 text-red-700"
            >
              <i class="fa-solid fa-triangle-exclamation mr-1"></i>Overdue
            </span>
            <span
              v-if="detailModal.ticket?.issue_type_label"
              :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold', issueTypeBadgeClass(detailModal.ticket?.issue_type_variant)]"
            >
              <i class="fa-solid fa-tag mr-1"></i>{{ detailModal.ticket.issue_type_label }}
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl border border-gray-200 p-3">
              <p class="text-xs text-gray-500 mb-1"><i class="fa-solid fa-user-pen mr-1"></i>Dibuat oleh</p>
              <p class="font-semibold text-gray-800">{{ detailModal.ticket?.creator_name || '—' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3">
              <p class="text-xs text-gray-500 mb-1"><i class="fa-regular fa-calendar mr-1"></i>Tanggal dibuat</p>
              <p class="font-semibold text-gray-800">{{ detailModal.ticket?.created_at_label || '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 md:col-span-2">
              <p class="text-xs text-gray-500 mb-1"><i class="fa-regular fa-clock mr-1"></i>Due date</p>
              <p class="font-semibold text-gray-800">{{ detailModal.ticket?.due_date_label || '-' }}</p>
            </div>
          </div>

          <div class="rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 mb-1"><i class="fa-solid fa-users mr-1"></i>Team Assigned</p>
            <div v-if="assignedTeam.length" class="flex flex-wrap gap-2">
              <span
                v-for="member in assignedTeam"
                :key="member.id"
                class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1"
              >
                <img
                  v-if="member.avatar"
                  :src="member.avatar"
                  :alt="member.name"
                  class="h-5 w-5 rounded-full object-cover"
                />
                <span
                  v-else
                  class="h-5 w-5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold flex items-center justify-center"
                >
                  {{ getInitials(member.name) }}
                </span>
                <span class="text-xs font-medium text-gray-800">{{ member.name }}</span>
              </span>
            </div>
            <p v-else class="text-xs text-gray-500">Belum ada team yang di-assign.</p>
          </div>

          <div class="rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 mb-2"><i class="fa-solid fa-paperclip mr-1"></i>Attachments</p>
            <div v-if="attachments.length" class="grid grid-cols-2 md:grid-cols-4 gap-2">
              <button
                v-for="(file, idx) in attachments"
                :key="file.id"
                type="button"
                class="text-left rounded-lg border border-gray-200 p-2 hover:bg-gray-50"
                @click="openAttachment(file, idx)"
              >
                <img
                  v-if="isImage(file)"
                  :src="normalizeAttachmentUrl(file.path)"
                  :alt="file.name"
                  class="h-20 w-full object-cover rounded"
                />
                <div v-else class="h-20 w-full rounded bg-gray-100 flex items-center justify-center text-gray-500">
                  <i class="fa-solid fa-file-lines text-xl"></i>
                </div>
                <p class="mt-1 text-[11px] text-gray-700 truncate">{{ file.name }}</p>
              </button>
            </div>
            <p v-else class="text-xs text-gray-500">Tidak ada attachment.</p>
          </div>

          <div class="rounded-xl border border-gray-200 p-3">
            <p class="text-xs text-gray-500 mb-2"><i class="fa-solid fa-money-bill-wave mr-1"></i>PR / Payment</p>
            <div v-if="paymentInfo.total_pr > 0" class="space-y-2">
              <div class="text-xs text-gray-600">
                Total PR: <span class="font-semibold text-gray-800">{{ paymentInfo.total_pr }}</span> |
                Paid: <span class="font-semibold text-emerald-700">{{ paymentInfo.total_paid_pr || 0 }}</span> |
                On Process: <span class="font-semibold text-amber-700">{{ paymentInfo.total_processing_pr || 0 }}</span>
              </div>
              <div
                v-for="pr in paymentInfo.prs || []"
                :key="pr.id"
                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
              >
                <div class="flex items-center justify-between gap-2">
                  <p class="text-xs font-semibold text-gray-800">{{ pr.pr_number || '-' }}</p>
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold', getPaymentStatusClass(pr.payment_status)]">
                    {{ getPaymentStatusLabel(pr.payment_status) }}
                  </span>
                </div>
                <p class="text-[11px] text-gray-600 mt-1">
                  PR Status: {{ pr.status || '-' }} | Mode: {{ pr.mode || '-' }}
                </p>
                <p class="text-[11px] text-gray-500">
                  Paid {{ pr.paid_payments || 0 }} dari {{ pr.total_payments || 0 }} payment
                </p>
              </div>
            </div>
            <p v-else class="text-xs text-gray-500">Belum ada PR/Payment terkait.</p>
          </div>
        </div>
      </div>
    </div>

    <VueEasyLightbox
      :visible="lightbox.visible"
      :imgs="lightbox.images"
      :index="lightbox.index"
      @hide="lightbox.visible = false"
    />
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import VueEasyLightbox from 'vue-easy-lightbox';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  calendarEvents: { type: Array, default: () => [] },
  year: { type: Number, required: true },
  month: { type: Number, required: true },
});

const calendarEl = ref(null);
let calendarApi = null;
const detailModal = reactive({
  open: false,
  ticket: null,
});
const lightbox = reactive({
  visible: false,
  images: [],
  index: 0,
});

const monthTitle = computed(() => {
  const d = new Date(props.year, props.month - 1, 1);
  return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});
const assignedTeam = computed(() => detailModal.ticket?.assigned_team || []);
const attachments = computed(() => detailModal.ticket?.attachments || []);
const paymentInfo = computed(() => detailModal.ticket?.payment_info || { total_pr: 0, total_paid_pr: 0, total_processing_pr: 0, prs: [] });

function normalizeAttachmentUrl(path) {
  if (!path) return '';
  return path.startsWith('/storage/') ? path : `/storage/${path}`;
}

function isImage(file) {
  return (file?.mime || '').startsWith('image/');
}

function getInitials(name) {
  if (!name) return 'U';
  const parts = name.trim().split(/\s+/).slice(0, 2);
  return parts.map((p) => p[0]?.toUpperCase() || '').join('');
}

function statusBadgeClass(slug) {
  switch ((slug || '').toLowerCase()) {
    case 'open':
      return 'bg-blue-100 text-blue-800';
    case 'in_progress':
      return 'bg-amber-100 text-amber-800';
    case 'resolved':
      return 'bg-green-100 text-green-800';
    case 'closed':
      return 'bg-gray-100 text-gray-700';
    default:
      return 'bg-slate-100 text-slate-700';
  }
}

function getPaymentStatusLabel(status) {
  if (status === 'PAID') return 'Sudah Paid';
  if (status === 'ON_PROCESS') return 'Payment Proses';
  return 'Belum Payment';
}

function getPaymentStatusClass(status) {
  if (status === 'PAID') return 'bg-emerald-100 text-emerald-700';
  if (status === 'ON_PROCESS') return 'bg-amber-100 text-amber-700';
  return 'bg-gray-100 text-gray-700';
}

function issueTypeBadgeClass(variant) {
  switch (variant) {
    case 'defect':
      return 'bg-rose-100 text-rose-800 ring-1 ring-rose-200';
    case 'ops_issue':
      return 'bg-cyan-100 text-cyan-800 ring-1 ring-cyan-200';
    case 'custom':
      return 'bg-slate-100 text-slate-800 ring-1 ring-slate-200';
    default:
      return 'bg-gray-100 text-gray-700 ring-1 ring-gray-200';
  }
}

function closeDetailModal() {
  detailModal.open = false;
  detailModal.ticket = null;
}

function openAttachment(file, index) {
  if (!isImage(file)) {
    window.open(normalizeAttachmentUrl(file.path), '_blank');
    return;
  }
  const imageFiles = attachments.value.filter((x) => isImage(x));
  const imageUrls = imageFiles.map((x) => normalizeAttachmentUrl(x.path));
  const picked = normalizeAttachmentUrl(file.path);
  const idx = imageUrls.findIndex((x) => x === picked);
  lightbox.images = imageUrls;
  lightbox.index = idx >= 0 ? idx : index;
  lightbox.visible = true;
}

function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function loadFullCalendarCss() {
  if (document.getElementById('fc-ticket-css-core')) return;
  const a = document.createElement('link');
  a.id = 'fc-ticket-css-core';
  a.rel = 'stylesheet';
  a.href = 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.css';
  document.head.appendChild(a);
  const b = document.createElement('link');
  b.id = 'fc-ticket-css-daygrid';
  b.rel = 'stylesheet';
  b.href = 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.css';
  document.head.appendChild(b);
}

function initialDateStr() {
  return `${props.year}-${String(props.month).padStart(2, '0')}-01`;
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
    locale: 'en',
    firstDay: 1,
    fixedWeekCount: false,
    dayMaxEvents: 4,
    moreLinkText: (n) => '+' + n + ' lagi',
    events: props.calendarEvents || [],
    eventContent(arg) {
      const p = arg.event.extendedProps || {};
      const number = escapeHtml(arg.event.title || '');
      const status = p.status
        ? `<span class="fc-badge fc-badge-status" data-slug="${escapeHtml(p.status_slug || '')}">${escapeHtml(p.status)}</span>`
        : '';
      const priority = p.priority
        ? `<span class="fc-badge fc-badge-priority">${escapeHtml(p.priority)}</span>`
        : '';
      const outlet = p.outlet_name
        ? `<span class="fc-badge fc-badge-outlet">${escapeHtml(p.outlet_name)}</span>`
        : '';
      return {
        html: `<div class="fc-ticket-wrap"><span class="fc-ticket-number">${number}</span><div class="fc-ticket-badges">${status}${priority}${outlet}</div></div>`,
      };
    },
    eventClick(info) {
      info.jsEvent.preventDefault();
      detailModal.ticket = { ...(info.event.extendedProps || {}) };
      detailModal.open = true;
    },
    eventDidMount(info) {
      const p = info.event.extendedProps || {};
      const tip = [
        p.ticket_number || '',
        p.title_full || '',
        p.creator_name ? 'Pembuat: ' + p.creator_name : '',
        p.issue_type_label ? 'Issue: ' + p.issue_type_label : '',
        p.status ? 'Status: ' + p.status : '',
        p.priority ? 'Prioritas: ' + p.priority : '',
        p.kind === 'due' ? 'Tipe: Due date' : 'Tipe: Tanggal dibuat',
      ]
        .filter(Boolean)
        .join('\n');
      info.el.setAttribute('title', tip);
    },
  });
  calendarApi.render();
}

onMounted(() => {
  loadFullCalendarCss();
  nextTick(() => {
    buildCalendar();
  });
});

watch(
  () => [props.year, props.month, props.calendarEvents],
  () => {
    nextTick(syncCalendar);
  },
  { deep: true }
);

onBeforeUnmount(() => {
  if (calendarApi) {
    calendarApi.destroy();
    calendarApi = null;
  }
});

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
  router.get(
    '/tickets/calendar',
    { year: y, month: m },
    { preserveState: true, replace: true, only: ['calendarEvents', 'year', 'month'] }
  );
}

function prevMonth() {
  navigateMonth(-1);
}

function nextMonth() {
  navigateMonth(1);
}

function goThisMonth() {
  const n = new Date();
  router.get(
    '/tickets/calendar',
    { year: n.getFullYear(), month: n.getMonth() + 1 },
    { preserveState: true, replace: true, only: ['calendarEvents', 'year', 'month'] }
  );
}

function goToList() {
  router.visit('/tickets');
}
</script>

<style>
.ticket-fc-min .fc .fc-daygrid-day-frame {
  min-height: 6.5rem;
}
.ticket-fc-min .fc .fc-event {
  font-size: 0.7rem;
  line-height: 1.2;
  padding: 2px 4px;
  cursor: pointer;
}
.ticket-fc-min .fc-ticket-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.ticket-fc-min .fc-ticket-number {
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ticket-fc-min .fc-ticket-badges {
  display: flex;
  gap: 2px;
  flex-wrap: wrap;
}
.ticket-fc-min .fc-badge {
  display: inline-block;
  border-radius: 4px;
  padding: 0 4px;
  font-size: 0.55rem;
  line-height: 1.25;
  font-weight: 700;
  white-space: nowrap;
}
.ticket-fc-min .fc-badge-status {
  background: #e2e8f0;
  color: #334155;
}
.ticket-fc-min .fc-badge-status[data-slug='open'] {
  background: #dbeafe;
  color: #1e40af;
}
.ticket-fc-min .fc-badge-status[data-slug='in_progress'] {
  background: #fef3c7;
  color: #92400e;
}
.ticket-fc-min .fc-badge-status[data-slug='resolved'] {
  background: #dcfce7;
  color: #166534;
}
.ticket-fc-min .fc-badge-status[data-slug='closed'] {
  background: #e5e7eb;
  color: #374151;
}
.ticket-fc-min .fc-badge-priority {
  background: #ede9fe;
  color: #5b21b6;
}
.ticket-fc-min .fc-badge-outlet {
  background: #cffafe;
  color: #155e75;
}
.ticket-fc-min .fc-col-header-cell {
  font-size: 0.75rem;
  font-weight: 600;
  color: #4b5563;
}
</style>
