<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-file-lines"></i> Laporan Pindah Outlet
        </h1>
        <Link
          :href="route('outlet-transfer.index')"
          class="bg-gray-100 text-gray-700 px-4 py-2 rounded-xl shadow hover:bg-gray-200 transition font-semibold"
        >
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali ke Pindah Outlet
        </Link>
      </div>

      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="filters.from"
          @change="applyFilters"
          type="date"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          placeholder="Dari tanggal"
        />
        <span class="text-gray-500">–</span>
        <input
          v-model="filters.to"
          @change="applyFilters"
          type="date"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          placeholder="Sampai tanggal"
        />
        <select
          v-model="filters.outlet_from_id"
          @change="applyFilters"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="">Semua Outlet Asal</option>
          <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
        </select>
        <select
          v-model="filters.outlet_to_id"
          @change="applyFilters"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="">Semua Outlet Tujuan</option>
          <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
        </select>
        <select
          v-model="filters.status"
          @change="applyFilters"
          class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="submitted">Menunggu Approval</option>
          <option value="approved">Disetujui</option>
          <option value="rejected">Ditolak</option>
        </select>
        <button
          @click="applyFilters"
          class="px-4 py-2 rounded-xl bg-blue-500 text-white font-semibold hover:bg-blue-600 transition shadow"
        >
          <i class="fa-solid fa-filter mr-2"></i> Filter
        </button>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all mb-4">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No. Transfer</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet Asal</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">WH Asal</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet Tujuan</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">WH Tujuan</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Harga (MAC)</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Nilai</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows || !rows.length">
              <td colspan="12" class="text-center py-10 text-gray-400">Tidak ada data untuk filter yang dipilih.</td>
            </tr>
            <tr v-for="(r, idx) in rows" :key="idx" class="hover:bg-blue-50 transition">
              <td class="px-4 py-2 font-mono text-sm font-semibold text-blue-700">{{ r.transfer_number }}</td>
              <td class="px-4 py-2 text-sm">{{ formatDate(r.transfer_date) }}</td>
              <td class="px-4 py-2 text-sm">{{ r.outlet_from_name }}</td>
              <td class="px-4 py-2 text-sm">{{ r.warehouse_from_name }}</td>
              <td class="px-4 py-2 text-sm">{{ r.outlet_to_name }}</td>
              <td class="px-4 py-2 text-sm">{{ r.warehouse_to_name }}</td>
              <td class="px-4 py-2 text-sm">{{ r.item_name }} <span v-if="r.item_sku" class="text-gray-400">({{ r.item_sku }})</span></td>
              <td class="px-4 py-2 text-sm text-right">{{ formatNumber(r.quantity) }}</td>
              <td class="px-4 py-2 text-sm">{{ r.unit_name }}</td>
              <td class="px-4 py-2 text-sm text-right">{{ r.cost_per_small != null ? formatMoney(r.cost_per_small) : '-' }}</td>
              <td class="px-4 py-2 text-sm text-right font-medium">{{ r.nilai != null ? formatMoney(r.nilai) : '-' }}</td>
              <td class="px-4 py-2">
                <span class="px-2 py-1 text-xs font-semibold rounded-full" :class="getStatusClass(r.status)">
                  {{ getStatusText(r.status) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-end items-center gap-4 bg-blue-50 rounded-xl px-4 py-3 shadow">
        <span class="font-bold text-gray-700">Total Nilai (MAC):</span>
        <span class="text-xl font-bold text-blue-700">{{ formatMoney(grandTotalNilai) }}</span>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  rows: Array,
  filters: Object,
  outlets: Array,
  grandTotalNilai: Number,
});

const filters = ref({
  from: props.filters?.from || '',
  to: props.filters?.to || '',
  outlet_from_id: props.filters?.outlet_from_id || '',
  outlet_to_id: props.filters?.outlet_to_id || '',
  status: props.filters?.status || '',
});

watch(
  () => props.filters,
  (f) => {
    filters.value = {
      from: f?.from || '',
      to: f?.to || '',
      outlet_from_id: f?.outlet_from_id || '',
      outlet_to_id: f?.outlet_to_id || '',
      status: f?.status || '',
    };
  },
  { immediate: true }
);

function applyFilters() {
  router.get(route('outlet-transfer.report'), {
    from: filters.value.from || undefined,
    to: filters.value.to || undefined,
    outlet_from_id: filters.value.outlet_from_id || undefined,
    outlet_to_id: filters.value.outlet_to_id || undefined,
    status: filters.value.status || undefined,
  }, { preserveState: true });
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatNumber(n) {
  if (n == null) return '-';
  return Number(n).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
}

function formatMoney(n) {
  if (n == null) return '-';
  return 'Rp ' + Number(n).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function getStatusText(status) {
  const m = { draft: 'Draft', submitted: 'Menunggu Approval', approved: 'Disetujui', rejected: 'Ditolak' };
  return m[status] || status;
}

function getStatusClass(status) {
  const c = {
    draft: 'bg-gray-100 text-gray-800',
    submitted: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
  };
  return c[status] || 'bg-gray-100 text-gray-800';
}
</script>
