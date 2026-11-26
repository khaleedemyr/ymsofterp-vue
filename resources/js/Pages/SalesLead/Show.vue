<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-handshake text-emerald-600"></i>
            {{ record.number }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">{{ record.company_name }}</p>
        </div>
        <div class="flex gap-2">
          <Link
            :href="route('sales-leads.index')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
          <Link
            v-if="canEdit"
            :href="route('sales-leads.edit', record.id)"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
          >
            <i class="fa-solid fa-pen"></i> Edit
          </Link>
          <button
            v-if="canDelete"
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700"
            @click="confirmDelete"
          >
            <i class="fa-solid fa-trash"></i> Hapus
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <div class="text-xs text-gray-500">Company Name</div>
            <div class="font-medium">{{ record.company_name }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Status</div>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(record.status)">
              {{ statuses[record.status] || record.status }}
            </span>
          </div>
          <div>
            <div class="text-xs text-gray-500">Segment 1</div>
            <div class="font-medium">{{ record.segment_1 || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Segment 2</div>
            <div class="font-medium">{{ record.segment_2 || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Type of Event</div>
            <div class="font-medium">{{ record.type_of_event || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Date</div>
            <div class="font-medium">{{ formatDate(record.event_date) }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Pax (Plan)</div>
            <div class="font-medium">{{ record.pax ?? '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Real Pax</div>
            <div class="font-medium">{{ record.real_pax ?? '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Outlet</div>
            <div class="font-medium">{{ record.outlet?.nama_outlet || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Room Requirement</div>
            <div class="font-medium">{{ record.room_requirement || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Revenue (Plan)</div>
            <div class="font-medium">{{ formatMoney(record.revenue) }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Real Revenue</div>
            <div class="font-medium">{{ formatMoney(record.real_revenue) }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Sales</div>
            <div class="font-medium">{{ record.sales?.nama_lengkap || '-' }}</div>
          </div>
        </div>

        <div class="mt-4">
          <div class="text-xs text-gray-500 mb-1">Attachment</div>
          <a
            v-if="record.attachment_url"
            :href="record.attachment_url"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-2 text-sm text-emerald-700 hover:text-emerald-900"
          >
            <i class="fa-solid fa-paperclip"></i>
            {{ record.attachment_original_name || 'Download file' }}
          </a>
          <div v-else class="text-sm text-gray-400">-</div>
        </div>

        <div class="mt-4">
          <div class="text-xs text-gray-500 mb-1">Foto Bill</div>
          <button
            v-if="record.bill_photo_url"
            type="button"
            class="block rounded-lg overflow-hidden border border-gray-200 hover:ring-2 hover:ring-emerald-400"
            title="Lihat foto bill"
            @click="openBillPreview(record.bill_photo_url)"
          >
            <img :src="record.bill_photo_url" alt="Bill" class="w-40 h-40 object-cover" />
          </button>
          <div v-else class="text-sm text-gray-400">-</div>
        </div>

        <div class="mt-4">
          <div class="text-xs text-gray-500 mb-1">Remarks</div>
          <div class="text-sm whitespace-pre-wrap border rounded-lg p-3 bg-gray-50">{{ record.remarks || '-' }}</div>
        </div>
        <div class="mt-4">
          <div class="text-xs text-gray-500 mb-1">Closing Remarks</div>
          <div class="text-sm whitespace-pre-wrap border rounded-lg p-3 bg-gray-50">{{ record.closing_remarks || '-' }}</div>
        </div>
      </div>
    </div>

    <div
      v-if="billPreviewUrl"
      class="fixed inset-0 z-[80] bg-black/80 flex flex-col"
    >
      <div class="flex items-center justify-between gap-3 px-4 py-3 text-white shrink-0">
        <div class="text-sm font-medium">Foto Bill</div>
        <div class="flex items-center gap-2">
          <button type="button" class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25" title="Zoom out" @click="zoomBill(-0.25)">
            <i class="fa-solid fa-minus"></i>
          </button>
          <span class="text-xs w-12 text-center tabular-nums">{{ Math.round(billZoom * 100) }}%</span>
          <button type="button" class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25" title="Zoom in" @click="zoomBill(0.25)">
            <i class="fa-solid fa-plus"></i>
          </button>
          <button type="button" class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25" title="Reset" @click="resetBillZoom">
            <i class="fa-solid fa-compress"></i>
          </button>
          <button type="button" class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25" title="Tutup" @click="closeBillPreview">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
      </div>
      <div
        class="flex-1 overflow-hidden cursor-grab active:cursor-grabbing select-none"
        @wheel.prevent="onBillWheel"
        @pointerdown="onBillPointerDown"
        @pointermove="onBillPointerMove"
        @pointerup="onBillPointerUp"
        @pointercancel="onBillPointerUp"
        @click.self="closeBillPreview"
      >
        <div class="w-full h-full flex items-center justify-center p-4">
          <img
            :src="billPreviewUrl"
            alt="Bill preview"
            class="max-w-full max-h-full object-contain transition-transform duration-75 will-change-transform shadow-2xl rounded"
            :style="{ transform: `translate(${billOffset.x}px, ${billOffset.y}px) scale(${billZoom})` }"
            draggable="false"
            @click.stop
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  statuses: { type: Object, default: () => ({}) },
});

const formatDate = (d) => {
  if (!d) return '-'
  const s = String(d)
  if (s.includes('T')) {
    const dt = new Date(s)
    if (!Number.isNaN(dt.getTime())) {
      const y = dt.getFullYear()
      const m = String(dt.getMonth() + 1).padStart(2, '0')
      const day = String(dt.getDate()).padStart(2, '0')
      return `${y}-${m}-${day}`
    }
  }
  const m = s.match(/^(\d{4}-\d{2}-\d{2})/)
  return m ? m[1] : s.substring(0, 10)
}

const formatMoney = (v) => {
  if (v === null || v === undefined || v === '') return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v));
};

const statusClass = (s) => {
  const map = {
    new_leads: 'bg-blue-100 text-blue-800',
    connected: 'bg-sky-100 text-sky-800',
    quotation_send: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-emerald-100 text-emerald-800',
    no_respond: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-800',
  };
  return map[s] || 'bg-gray-100 text-gray-700';
};

const billPreviewUrl = ref(null);
const billZoom = ref(1);
const billOffset = reactive({ x: 0, y: 0 });
const billDragging = ref(false);
const billDragStart = reactive({ x: 0, y: 0, ox: 0, oy: 0 });

const openBillPreview = (url) => {
  if (!url) return;
  billPreviewUrl.value = url;
  resetBillZoom();
};
const closeBillPreview = () => {
  billPreviewUrl.value = null;
  resetBillZoom();
};
const resetBillZoom = () => {
  billZoom.value = 1;
  billOffset.x = 0;
  billOffset.y = 0;
};
const zoomBill = (delta) => {
  billZoom.value = Math.min(5, Math.max(0.5, Number((billZoom.value + delta).toFixed(2))));
};
const onBillWheel = (e) => zoomBill(e.deltaY < 0 ? 0.15 : -0.15);
const onBillPointerDown = (e) => {
  if (e.button !== 0) return;
  billDragging.value = true;
  billDragStart.x = e.clientX;
  billDragStart.y = e.clientY;
  billDragStart.ox = billOffset.x;
  billDragStart.oy = billOffset.y;
  e.currentTarget?.setPointerCapture?.(e.pointerId);
};
const onBillPointerMove = (e) => {
  if (!billDragging.value) return;
  billOffset.x = billDragStart.ox + (e.clientX - billDragStart.x);
  billOffset.y = billDragStart.oy + (e.clientY - billDragStart.y);
};
const onBillPointerUp = () => { billDragging.value = false; };

const confirmDelete = async () => {
  const result = await Swal.fire({
    title: 'Hapus Sales Lead?',
    text: `${props.record.number} — ${props.record.company_name}`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  });
  if (!result.isConfirmed) return;
  router.delete(route('sales-leads.destroy', props.record.id));
};
</script>
