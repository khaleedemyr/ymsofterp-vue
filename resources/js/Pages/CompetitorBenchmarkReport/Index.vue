<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-teal-600"></i>
            Competitor Benchmark Report
          </h1>
          <p class="text-sm text-gray-500 mt-1">Laporan benchmark kompetitor</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('report.competitor-benchmark-report.index')"
            class="inline-flex items-center gap-2 bg-white border border-teal-300 text-teal-700 px-4 py-2 rounded-lg shadow-sm hover:bg-teal-50 transition"
          >
            <i class="fa-solid fa-chart-bar"></i>
            Report
          </Link>
          <Link
            :href="route('competitor-benchmark-report.create')"
            class="inline-flex items-center gap-2 bg-teal-600 text-white px-4 py-2 rounded-lg shadow hover:bg-teal-700 transition"
          >
            <i class="fa-solid fa-plus"></i>
            Buat Report
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari</label>
            <input
              v-model="filterForm.search"
              type="text"
              placeholder="Nomor report, brand, atau PIC..."
              class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
            <input v-model="filterForm.month" type="month" class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500" />
          </div>
          <div class="md:col-span-3 flex gap-2 justify-end">
            <button type="button" @click="resetFilters" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">Reset</button>
            <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700">Filter</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Bulan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Baris</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Dibuat Oleh</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="reports.data.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada report.</td>
            </tr>
            <tr v-for="row in reports.data" :key="row.id" class="border-b hover:bg-teal-50/40 transition-colors">
              <td class="px-4 py-3 font-medium text-gray-900">{{ row.number }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatMonth(row.report_month) }}</td>
              <td class="px-4 py-3">{{ row.items_count }}</td>
              <td class="px-4 py-3">{{ row.creator?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <Link :href="route('competitor-benchmark-report.show', row.id)" class="text-teal-600 hover:text-teal-800" title="Detail"><i class="fa-solid fa-eye"></i></Link>
                  <Link :href="route('competitor-benchmark-report.edit', row.id)" class="text-amber-600 hover:text-amber-800" title="Edit"><i class="fa-solid fa-pen"></i></Link>
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
          :class="link.active ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
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
});

const filterForm = reactive({
  search: props.filters.search || '',
  month: props.filters.month || '',
});

function applyFilters() {
  router.get(route('competitor-benchmark-report.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.search = '';
  filterForm.month = '';
  applyFilters();
}

function formatMonth(value) {
  if (!value) return '-';
  const d = new Date(value);
  return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
}
</script>
