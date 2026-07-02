<template>
  <AppLayout title="Report Ticket External Vendor">
    <div class="w-full py-6 px-4 space-y-6 print:py-2 print:px-2">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between print:hidden">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Report Ticket External Vendor</h1>
          <p class="mt-1 text-sm text-gray-600">
            Daftar ticket yang dikerjakan oleh external vendor ({{ total }} ticket).
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="goBack">
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </button>
          <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" @click="exportExcel">
            <i class="fa-solid fa-file-excel"></i> Export Excel
          </button>
          <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @click="printReport">
            <i class="fa-solid fa-print"></i> Print
          </button>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm print:hidden">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <input v-model="localFilters.search" type="text" placeholder="Cari ticket, vendor..." class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          <select v-model="localFilters.category" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="all">Semua Kategori</option>
            <option v-for="c in filterOptions.categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
          <select v-model="localFilters.priority" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="all">Semua Prioritas</option>
            <option v-for="p in filterOptions.priorities" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
          </select>
          <select v-model="localFilters.status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="all">Semua Status</option>
            <option v-for="s in filterOptions.statuses" :key="s.id" :value="s.slug">{{ s.name }}</option>
          </select>
          <select v-model="localFilters.outlet" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="all">Semua Outlet</option>
            <option v-for="o in filterOptions.outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
          </select>
          <input v-model="localFilters.date_from" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          <input v-model="localFilters.date_to" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" :disabled="loading" @click="reloadData">
            <i class="fa-solid fa-sync-alt" :class="{ 'fa-spin': loading }"></i> Load Data
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-300 bg-white">
        <div class="overflow-x-auto">
          <table class="min-w-full border-collapse text-sm">
            <thead>
              <tr class="bg-slate-800 text-white">
                <th class="border border-slate-600 px-2 py-2">NO</th>
                <th class="border border-slate-600 px-2 py-2">OUTLET</th>
                <th class="border border-slate-600 px-2 py-2">TICKET</th>
                <th class="min-w-[200px] border border-slate-600 px-3 py-2">FINDING PROBLEM</th>
                <th class="border border-slate-600 px-2 py-2">VENDOR</th>
                <th class="border border-slate-600 px-2 py-2">DOCUMENTATION</th>
                <th class="border border-slate-600 px-2 py-2">PRIORITY</th>
                <th class="border border-slate-600 px-2 py-2">EST EXPENSE</th>
                <th class="border border-slate-600 px-2 py-2">RESULT</th>
                <th class="border border-slate-600 px-3 py-2">STATUS</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!rows.length">
                <td colspan="10" class="border border-gray-300 px-4 py-10 text-center text-gray-500">Tidak ada ticket external vendor.</td>
              </tr>
              <tr v-for="row in rows" :key="row.ticket_id">
                <td class="border border-gray-400 px-2 py-3 text-center align-top">{{ row.no }}</td>
                <td class="border border-gray-400 px-2 py-3 align-top text-xs font-semibold">{{ row.outlet_name || '-' }}</td>
                <td class="border border-gray-400 px-2 py-3 align-top text-xs">{{ row.ticket_number }}</td>
                <td class="border border-gray-400 px-3 py-3 align-top">{{ row.finding_problem }}</td>
                <td class="border border-gray-400 px-2 py-3 align-top font-medium text-orange-800">{{ row.vendor_name || '-' }}</td>
                <td class="border border-gray-400 p-2 align-top">
                  <div v-if="rowImages(row).length" class="flex flex-wrap gap-1 justify-center">
                    <button
                      v-for="(img, idx) in rowImages(row)"
                      :key="`doc-${row.ticket_id}-${idx}`"
                      type="button"
                      class="h-14 w-14 overflow-hidden rounded border"
                      @click="openReportImages(row, 'documentation', idx)"
                    >
                      <img :src="img" alt="Doc" class="h-full w-full object-cover" />
                    </button>
                  </div>
                  <div v-else class="text-center text-xs text-gray-400">-</div>
                </td>
                <td class="border border-gray-400 px-2 py-3 text-center align-top">
                  <span class="rounded-full px-2 py-1 text-[11px] font-bold" :class="priorityBadgeClass(row.priority_level)">{{ row.priority_name || '-' }}</span>
                </td>
                <td class="border border-gray-400 px-2 py-3 text-center align-top text-xs whitespace-nowrap">{{ row.est_expense_formatted || '-' }}</td>
                <td class="border border-gray-400 p-2 align-top">
                  <button v-if="row.result_image" type="button" class="mx-auto block max-w-[120px]" @click="openReportImages(row, 'result', 0)">
                    <img :src="row.result_image" alt="Result" class="max-h-20 w-full rounded object-contain" />
                  </button>
                  <div v-else class="text-center text-xs text-gray-400">-</div>
                </td>
                <td class="border border-gray-400 px-3 py-3 align-top whitespace-pre-line text-sm">
                  <div class="font-semibold uppercase">{{ row.status_name || '-' }}</div>
                  <div v-if="row.notes" class="mt-1 text-gray-700">{{ row.notes }}</div>
                  <div v-if="row.status_slug === 'closed' && row.closed_at" class="mt-1 text-xs text-emerald-700">{{ row.closed_at }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <VueEasyLightbox :visible="visibleRef" :imgs="imgsRef" :index="indexRef" @hide="hideLightbox" />
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  total: { type: Number, default: 0 },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({ categories: [], priorities: [], statuses: [], outlets: [] }) },
});

const loading = ref(false);
const visibleRef = ref(false);
const indexRef = ref(0);
const imgsRef = ref([]);
const localFilters = reactive({
  search: props.filters.search || '',
  status: props.filters.status || 'all',
  priority: props.filters.priority || 'all',
  category: props.filters.category || 'all',
  division: props.filters.division || 'all',
  outlet: props.filters.outlet || 'all',
  issue_type: props.filters.issue_type || 'all',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
});

function rowImages(row) {
  const docs = (row.documentation_images || []).map((img) => img.url).filter(Boolean);
  if (docs.length) return docs;
  return row.complain_image ? [row.complain_image] : [];
}

function priorityBadgeClass(level) {
  if (level != null && level >= 4) return 'bg-red-100 text-red-800';
  if (level != null && level >= 3) return 'bg-orange-100 text-orange-800';
  if (level != null && level >= 2) return 'bg-yellow-100 text-yellow-800';
  return 'bg-blue-100 text-blue-800';
}

function reloadData() {
  loading.value = true;
  router.get('/tickets/report-external-vendor', { ...localFilters }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => { loading.value = false; },
  });
}

function exportExcel() {
  window.open(`/tickets/report-external-vendor/export?${new URLSearchParams({ ...localFilters }).toString()}`, '_blank');
}

function printReport() { window.print(); }
function goBack() { router.visit('/tickets'); }

function showLightbox(images, index = 0) {
  imgsRef.value = images;
  indexRef.value = index;
  visibleRef.value = true;
}
function hideLightbox() { visibleRef.value = false; }

function openReportImages(row, type, startIndex = 0) {
  const images = type === 'result' ? (row.result_image ? [row.result_image] : []) : rowImages(row);
  if (!images.length) return;
  showLightbox(images, Math.min(startIndex, images.length - 1));
}
</script>
