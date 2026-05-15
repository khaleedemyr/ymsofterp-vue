<template>
  <AppLayout title="Report Kasbon">
    <div class="py-8 px-4">
      <div class="max-w-7xl mx-auto">
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-money-bill-transfer text-orange-500"></i>
            Report Kasbon
          </h1>
          <p class="text-gray-600 mt-2">
            Pelacakan kasbon yang sudah disetujui: total cicilan, sudah terbayar berapa kali, dan sisa termin.
          </p>
        </div>

        <div
          v-if="tableMissing"
          class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900"
        >
          <p class="font-medium">Tabel <code class="text-sm bg-amber-100 px-1 rounded">pr_kasbons</code> belum ada.</p>
          <p class="mt-2 text-sm">
            Jalankan SQL <code class="text-sm bg-amber-100 px-1 rounded">database/sql/create_pr_kasbons.sql</code> di database, lalu backfill jika perlu.
          </p>
        </div>

        <template v-else>
          <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <form class="grid grid-cols-1 md:grid-cols-4 gap-4" @submit.prevent="applyFilters">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status tracker</label>
                <select
                  v-model="form.status"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-orange-500"
                >
                  <option value="all">Semua</option>
                  <option value="active">Aktif (masih ada cicilan)</option>
                  <option value="completed">Selesai</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
                <select
                  v-model="form.division_id"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-orange-500"
                >
                  <option value="">Semua</option>
                  <option v-for="d in divisions" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                <select
                  v-model="form.outlet_id"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-orange-500"
                >
                  <option value="">Semua</option>
                  <option v-for="o in outlets" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Per halaman</label>
                <select
                  v-model.number="form.per_page"
                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-orange-500"
                >
                  <option :value="15">15</option>
                  <option :value="25">25</option>
                  <option :value="50">50</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal approve dari</label>
                <input v-model="form.date_from" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal approve s/d</label>
                <input v-model="form.date_to" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2" />
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
                <input
                  v-model="form.search"
                  type="text"
                  placeholder="Nomor PR / nama karyawan"
                  class="w-full border border-gray-300 rounded-md px-3 py-2"
                />
              </div>
              <div class="md:col-span-4 flex gap-2">
                <button type="submit" class="bg-orange-600 text-white px-6 py-2 rounded-md hover:bg-orange-700">
                  <i class="fa fa-filter mr-2"></i>Filter
                </button>
                <button type="button" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600" @click="resetFilters">
                  <i class="fa fa-redo mr-2"></i>Reset
                </button>
              </div>
            </form>
          </div>

          <div v-if="summary" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow p-4">
              <p class="text-sm text-gray-600">Total baris</p>
              <p class="text-2xl font-bold text-gray-900">{{ summary.total_rows }}</p>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
              <p class="text-sm text-gray-600">Status aktif</p>
              <p class="text-2xl font-bold text-orange-600">{{ summary.active_count }}</p>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
              <p class="text-sm text-gray-600">Selesai</p>
              <p class="text-2xl font-bold text-green-600">{{ summary.completed_count }}</p>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
              <p class="text-sm text-gray-600">Jumlah nominal total</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatRp(summary.sum_total_amount) }}</p>
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">PR</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Karyawan</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Outlet</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600">Total</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Termin</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Sudah bayar</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600">Per termin</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Approve</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="row in kasbons" :key="row.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs">{{ row.pr_number }}</td>
                    <td class="px-4 py-3">{{ row.employee_name || '—' }}</td>
                    <td class="px-4 py-3">{{ row.outlet_name || '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ formatRp(row.total_amount) }}</td>
                    <td class="px-4 py-3 text-center">{{ row.termin_total }}x</td>
                    <td class="px-4 py-3 text-center font-semibold">{{ row.paid_installments }} / {{ row.termin_total }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">{{ formatRp(row.installment_amount) }}</td>
                    <td class="px-4 py-3 text-center">
                      <span
                        class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                        :class="row.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'"
                      >
                        {{ row.status }}
                      </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">{{ formatDate(row.approved_at) }}</td>
                    <td class="px-4 py-3">
                      <a
                        v-if="row.purchase_requisition_id"
                        :href="`/purchase-requisitions/${row.purchase_requisition_id}`"
                        class="text-orange-600 hover:underline"
                      >
                        Lihat PR
                      </a>
                      <span v-else class="text-gray-400">—</span>
                    </td>
                  </tr>
                  <tr v-if="!kasbons.length">
                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">Tidak ada data untuk filter ini.</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600">
              <span>
                {{ pagination.from }}–{{ pagination.to }} dari {{ pagination.total }}
              </span>
              <div class="flex gap-2 items-center">
                <button
                  type="button"
                  class="px-3 py-1 rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40"
                  :disabled="pagination.current_page <= 1"
                  @click="goPage(pagination.current_page - 1)"
                >
                  Sebelumnya
                </button>
                <span>Hal. {{ pagination.current_page }} / {{ pagination.last_page }}</span>
                <button
                  type="button"
                  class="px-3 py-1 rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40"
                  :disabled="pagination.current_page >= pagination.last_page"
                  @click="goPage(pagination.current_page + 1)"
                >
                  Berikutnya
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  tableMissing: { type: Boolean, default: false },
  kasbons: { type: Array, default: () => [] },
  summary: { type: Object, default: null },
  divisions: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
  pagination: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const form = reactive({
  status: props.filters.status ?? 'all',
  division_id: props.filters.division_id != null && props.filters.division_id !== '' ? String(props.filters.division_id) : '',
  outlet_id: props.filters.outlet_id != null && props.filters.outlet_id !== '' ? String(props.filters.outlet_id) : '',
  date_from: props.filters.date_from ?? '',
  date_to: props.filters.date_to ?? '',
  search: props.filters.search ?? '',
  per_page: Number(props.filters.per_page) || 15,
});

watch(
  () => props.filters,
  (f) => {
    form.status = f.status ?? 'all';
    form.division_id = f.division_id != null && f.division_id !== '' ? String(f.division_id) : '';
    form.outlet_id = f.outlet_id != null && f.outlet_id !== '' ? String(f.outlet_id) : '';
    form.date_from = f.date_from ?? '';
    form.date_to = f.date_to ?? '';
    form.search = f.search ?? '';
    form.per_page = Number(f.per_page) || 15;
  },
  { deep: true }
);

function formatRp(n) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(n) || 0);
}

function formatDate(v) {
  if (!v) return '—';
  const d = new Date(v);
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('id-ID', { dateStyle: 'medium' });
}

function applyFilters() {
  router.get('/report-kasbon', { ...form, page: 1 }, { preserveState: true, replace: true });
}

function resetFilters() {
  router.get('/report-kasbon', {}, { replace: true });
}

function goPage(page) {
  const last = props.pagination.last_page || 1;
  if (page < 1 || page > last) return;
  router.get('/report-kasbon', { ...form, page }, { preserveState: true, replace: true });
}
</script>
