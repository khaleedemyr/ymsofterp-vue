<template>
  <AppLayout title="Member Visit Frequency">
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-simple text-sky-600"></i>
            Member Visit Frequency & Repeat Ratio
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Formula utama: <span class="font-medium text-gray-700">Total Member ÷ Total Pax</span>
            — dari bill member berstatus paid.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-white shadow hover:bg-emerald-700 disabled:opacity-60"
          :disabled="exporting"
          @click="exportExcel"
        >
          <i :class="exporting ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-file-excel'"></i>
          {{ exporting ? 'Exporting...' : 'Export Excel' }}
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
          <div>
            <label class="mb-1 block text-xs text-gray-500">Dari tanggal</label>
            <input v-model="filterForm.date_from" type="date" class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-500">Sampai tanggal</label>
            <input v-model="filterForm.date_to" type="date" class="w-full rounded-lg border-gray-300" />
          </div>
          <div class="lg:col-span-2">
            <label class="mb-1 block text-xs text-gray-500">Outlet</label>
            <select v-model="filterForm.outlet" class="w-full rounded-lg border-gray-300">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700">Cari</button>
            <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Unique Member</div>
          <div class="text-xl font-bold text-gray-900">{{ formatNumber(summary.unique_members) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Total Pax</div>
          <div class="text-xl font-bold text-gray-900">{{ formatNumber(summary.total_pax) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-sky-200 shadow-sm px-4 py-3 ring-1 ring-sky-100">
          <div class="text-xs text-sky-700">Member ÷ Pax</div>
          <div class="text-xl font-bold text-sky-800">{{ summary.member_per_pax }}</div>
          <div class="text-xs text-sky-600">{{ summary.member_per_pax_pct }}%</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Visit Frequency</div>
          <div class="text-xl font-bold text-indigo-700">{{ summary.visit_frequency }}</div>
          <div class="text-xs text-gray-500">visits / member</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Repeat Ratio</div>
          <div class="text-xl font-bold text-emerald-700">{{ summary.repeat_ratio }}%</div>
          <div class="text-xs text-gray-500">{{ formatNumber(summary.repeat_members) }} repeat members</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Total Visits / Revenue</div>
          <div class="text-lg font-bold text-gray-900">{{ formatNumber(summary.total_visits) }}</div>
          <div class="text-xs text-purple-700 font-medium">{{ summary.revenue_formatted }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4 xl:col-span-1">
          <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-layer-group text-indigo-500"></i>
            Distribusi Frekuensi Visit
          </h2>
          <div class="space-y-2">
            <div
              v-for="bucket in frequencyBuckets"
              :key="bucket.key"
              class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2"
            >
              <div>
                <div class="text-sm font-medium text-gray-800">{{ bucket.label }}</div>
                <div class="text-xs text-gray-500">{{ bucket.pct }}% dari member</div>
              </div>
              <div class="text-lg font-bold text-indigo-700">{{ formatNumber(bucket.members) }}</div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow overflow-x-auto xl:col-span-2">
          <div class="px-4 py-3 border-b">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-store text-sky-500"></i>
              Breakdown per Outlet
            </h2>
          </div>
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-gray-700">Outlet</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">Member</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">Pax</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">Member÷Pax</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">Visits</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">Visit Freq</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">Repeat %</th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!byOutlet.length">
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">Tidak ada data untuk filter ini.</td>
              </tr>
              <tr v-for="row in byOutlet" :key="row.kode_outlet" class="border-b border-gray-100 hover:bg-slate-50">
                <td class="px-3 py-2">
                  <div class="font-medium text-gray-900">{{ row.outlet_name }}</div>
                  <div class="text-xs text-gray-500 font-mono">{{ row.kode_outlet }}</div>
                </td>
                <td class="px-3 py-2 text-right">{{ formatNumber(row.unique_members) }}</td>
                <td class="px-3 py-2 text-right">{{ formatNumber(row.total_pax) }}</td>
                <td class="px-3 py-2 text-right font-semibold text-sky-700">{{ row.member_per_pax }}</td>
                <td class="px-3 py-2 text-right">{{ formatNumber(row.total_visits) }}</td>
                <td class="px-3 py-2 text-right text-indigo-700">{{ row.visit_frequency }}</td>
                <td class="px-3 py-2 text-right text-emerald-700">{{ row.repeat_ratio }}%</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ row.revenue_formatted }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
  summary: {
    type: Object,
    default: () => ({
      unique_members: 0,
      total_visits: 0,
      total_pax: 0,
      revenue: 0,
      revenue_formatted: 'Rp 0',
      member_per_pax: 0,
      member_per_pax_pct: 0,
      visit_frequency: 0,
      repeat_members: 0,
      repeat_ratio: 0,
    }),
  },
  byOutlet: { type: Array, default: () => [] },
  frequencyBuckets: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
});

const filterForm = reactive({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  outlet: props.filters.outlet || '',
});

const exporting = ref(false);
const formatNumber = (n) => Number(n || 0).toLocaleString('id-ID');

const applyFilters = () => {
  router.get(route('crm.member-visit-frequency-report'), { ...filterForm }, { preserveState: true, replace: true });
};

const resetFilters = () => {
  filterForm.date_from = '';
  filterForm.date_to = '';
  filterForm.outlet = '';
  applyFilters();
};

const exportExcel = () => {
  exporting.value = true;
  const params = new URLSearchParams();
  Object.entries(filterForm).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.append(key, String(value));
    }
  });
  window.location.href = `${route('crm.member-visit-frequency-report.export')}?${params.toString()}`;
  setTimeout(() => {
    exporting.value = false;
  }, 2000);
};
</script>
