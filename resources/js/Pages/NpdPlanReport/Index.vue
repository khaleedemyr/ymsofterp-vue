<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-lightbulb text-amber-500"></i>
            New Product Development Plan & Report
          </h1>
          <p class="text-sm text-gray-500 mt-1">Rencana dan laporan pengembangan produk F&B per outlet</p>
        </div>
        <Link
          :href="route('npd-plan-report.create')"
          class="inline-flex items-center gap-2 bg-amber-500 text-white px-4 py-2 rounded-lg shadow hover:bg-amber-600 transition"
        >
          <i class="fa-solid fa-plus"></i>
          Buat Report
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari</label>
            <input
              v-model="filterForm.search"
              type="text"
              placeholder="Nomor report atau outlet..."
              class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
            <input
              v-model="filterForm.month"
              type="month"
              class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet</label>
            <select v-model="filterForm.outlet_id" class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500">
              <option value="">Semua</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
            <select v-model="filterForm.status" class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500">
              <option value="">Semua</option>
              <option value="draft">Draft</option>
              <option value="submitted">Submitted</option>
              <option value="approved">Approved</option>
              <option value="rejected">Not Approved</option>
              <option value="requires_revision">Requires Revision</option>
            </select>
          </div>
          <div class="md:col-span-5 flex gap-2 justify-end">
            <button type="button" @click="resetFilters" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
              Reset
            </button>
            <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600">
              Filter
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Bulan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Outlet</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Products</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Dibuat Oleh</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="reports.data.length === 0">
              <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                <i class="fa-solid fa-inbox text-3xl mb-2 block text-gray-300"></i>
                Belum ada report.
              </td>
            </tr>
            <tr v-for="row in reports.data" :key="row.id" class="border-b hover:bg-amber-50/40 transition-colors">
              <td class="px-4 py-3 font-medium text-gray-900">{{ row.number }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatMonth(row.report_month) }}</td>
              <td class="px-4 py-3">{{ row.outlet_name }}</td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">
                  {{ row.items_count }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span :class="statusClass(row.status)" class="px-2.5 py-1 rounded-full text-xs font-semibold">
                  {{ statusLabel(row.status) }}
                </span>
              </td>
              <td class="px-4 py-3">{{ row.creator?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-center gap-2">
                  <Link
                    :href="route('npd-plan-report.show', row.id)"
                    class="px-3 py-1.5 rounded-lg bg-indigo-100 text-indigo-700 hover:bg-indigo-200 transition"
                    title="Detail"
                  >
                    <i class="fa-solid fa-eye"></i>
                  </Link>
                  <Link
                    v-if="['rejected', 'requires_revision'].includes(row.status)"
                    :href="route('npd-plan-report.edit', row.id)"
                    class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition"
                    title="Edit"
                  >
                    <i class="fa-solid fa-pen"></i>
                  </Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="reports.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
        <Link
          v-for="link in reports.links"
          :key="link.label"
          :href="link.url || '#'"
          class="px-3 py-1 rounded border text-sm"
          :class="link.active ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
  reports: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
});

const filterForm = reactive({
  search: props.filters.search || '',
  month: props.filters.month || '',
  outlet_id: props.filters.outlet_id || '',
  status: props.filters.status || '',
});

function applyFilters() {
  router.get(route('npd-plan-report.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.search = '';
  filterForm.month = '';
  filterForm.outlet_id = '';
  filterForm.status = '';
  applyFilters();
}

function formatMonth(value) {
  if (!value) return '-';
  const d = new Date(value);
  return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}

function statusClass(status) {
  const map = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    requires_revision: 'bg-amber-100 text-amber-800',
    cancelled: 'bg-slate-100 text-slate-600',
  };
  return map[status] || 'bg-gray-100 text-gray-700';
}

function statusLabel(status) {
  const map = {
    draft: 'Draft',
    submitted: 'Submitted',
    approved: 'Approved',
    rejected: 'Not Approved',
    requires_revision: 'Requires Revision',
    cancelled: 'Cancelled',
  };
  return map[status] || status;
}
</script>
