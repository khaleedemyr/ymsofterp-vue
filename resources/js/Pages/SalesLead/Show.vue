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
            <div class="text-xs text-gray-500">Pax</div>
            <div class="font-medium">{{ record.pax ?? '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Outlet</div>
            <div class="font-medium">{{ record.outlet?.nama_outlet || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Revenue</div>
            <div class="font-medium">{{ formatMoney(record.revenue) }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Sales</div>
            <div class="font-medium">{{ record.sales?.nama_lengkap || '-' }}</div>
          </div>
        </div>

        <div class="mt-4">
          <div class="text-xs text-gray-500 mb-1">Remarks</div>
          <div class="text-sm whitespace-pre-wrap border rounded-lg p-3 bg-gray-50">{{ record.remarks || '-' }}</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  statuses: { type: Object, default: () => ({}) },
});

const formatDate = (d) => (d ? String(d).substring(0, 10) : '-');

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
