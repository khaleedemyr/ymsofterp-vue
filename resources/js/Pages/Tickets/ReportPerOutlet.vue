<template>
  <AppLayout title="Report Ticket Per Outlet">
    <div class="w-full py-6 px-4 space-y-6 print:py-2 print:px-2">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between print:hidden">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Report Ticket Per Outlet</h1>
          <p class="mt-1 text-sm text-gray-600">
            Ringkasan temuan per outlet — dokumentasi, penanganan, prioritas ticketing, dan hasil.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            @click="goBack"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
            @click="exportExcel"
          >
            <i class="fa-solid fa-file-excel"></i> Export Excel
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            @click="printReport"
          >
            <i class="fa-solid fa-print"></i> Print
          </button>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm print:hidden">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <input
            v-model="localFilters.search"
            type="text"
            placeholder="Cari ticket, judul..."
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
          />
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
          <select v-model="localFilters.division" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="all">Semua Divisi</option>
            <option v-for="d in filterOptions.divisions" :key="d.id" :value="String(d.id)">{{ d.nama_divisi }}</option>
          </select>
          <select v-model="localFilters.outlet" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="all">Semua Outlet</option>
            <option v-for="o in filterOptions.outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
          </select>
          <input v-model="localFilters.date_from" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          <input v-model="localFilters.date_to" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            :disabled="loading"
            @click="reloadData"
          >
            <i class="fa-solid fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
            Load Data
          </button>
        </div>
      </div>

      <div id="ticket-outlet-report" class="space-y-8">
        <div v-if="!groups.length" class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
          Tidak ada data ticket untuk filter ini.
        </div>

        <section v-for="group in groups" :key="group.outlet_id" class="overflow-hidden rounded-xl border border-gray-300 bg-white">
          <div class="bg-slate-700 px-4 py-2.5 text-center text-sm font-extrabold tracking-wide text-white uppercase">
            {{ group.outlet_name || 'UNCATEGORIZED' }}
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
              <thead>
                <tr class="bg-slate-800 text-white">
                  <th rowspan="2" class="w-12 border border-slate-600 px-2 py-2 text-center font-bold">NO</th>
                  <th rowspan="2" class="min-w-[220px] border border-slate-600 px-3 py-2 text-center font-bold">FINDING PROBLEM</th>
                  <th rowspan="2" class="w-56 border border-slate-600 px-2 py-2 text-center font-bold">DOCUMENTATION</th>
                  <th colspan="2" class="border border-slate-600 px-2 py-2 text-center font-bold">HANDLED BY</th>
                  <th rowspan="2" class="w-28 border border-slate-600 px-2 py-2 text-center font-bold">EST EXPENSE</th>
                  <th rowspan="2" class="w-28 border border-slate-600 px-2 py-2 text-center font-bold">PRIORITY</th>
                  <th rowspan="2" class="w-56 border border-slate-600 px-2 py-2 text-center font-bold">RESULT</th>
                  <th rowspan="2" class="min-w-[160px] border border-slate-600 px-3 py-2 text-center font-bold">STATUS</th>
                </tr>
                <tr class="bg-slate-600 text-white text-xs">
                  <th class="border border-slate-500 px-2 py-1.5 text-center font-semibold">ENGINEERING</th>
                  <th class="border border-slate-500 px-2 py-1.5 text-center font-semibold">VENDOR</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in group.rows" :key="row.ticket_id">
                  <td class="border border-gray-400 px-2 py-3 text-center align-top">{{ row.no }}</td>
                  <td class="border border-gray-400 px-3 py-3 align-top">
                    <div class="font-medium text-gray-900">{{ row.finding_problem }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ row.ticket_number }}</div>
                    <div v-if="row.category_name" class="mt-1 text-[10px] font-semibold uppercase text-slate-500">{{ row.category_name }}</div>
                  </td>
                  <td class="border border-gray-400 p-2 align-top">
                    <div v-if="rowImages(row).length" class="flex flex-wrap justify-center gap-1">
                      <button
                        v-for="(img, idx) in rowImages(row)"
                        :key="`doc-${row.ticket_id}-${idx}`"
                        type="button"
                        class="group relative h-16 w-16 cursor-zoom-in overflow-hidden rounded border border-gray-200 bg-gray-50 print:cursor-default"
                        title="Klik untuk perbesar"
                        @click="openReportImages(row, 'documentation', idx)"
                      >
                        <img :src="img" alt="Documentation" class="h-full w-full object-cover transition group-hover:opacity-90" />
                      </button>
                    </div>
                    <div v-else class="flex h-28 items-center justify-center text-xs text-gray-400">-</div>
                  </td>
                  <td
                    class="border border-gray-400 px-2 py-3 text-center align-middle text-lg font-bold"
                    :class="row.handled_internal ? 'bg-yellow-300 text-slate-900' : ''"
                  >
                    {{ row.handled_internal ? 'v' : '' }}
                  </td>
                  <td
                    class="border border-gray-400 px-2 py-3 text-center align-middle text-lg font-bold"
                    :class="row.handled_vendor ? 'bg-yellow-300 text-slate-900' : ''"
                  >
                    {{ row.handled_vendor ? 'v' : '' }}
                  </td>
                  <td class="border border-gray-400 px-2 py-3 text-center align-top text-xs font-medium whitespace-nowrap">
                    {{ row.est_expense_formatted || '-' }}
                  </td>
                  <td class="border border-gray-400 px-2 py-3 text-center align-top">
                    <span
                      class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
                      :class="priorityBadgeClass(row.priority_level)"
                    >
                      {{ row.priority_name || '-' }}
                    </span>
                  </td>
                  <td class="border border-gray-400 p-2 align-top">
                    <div v-if="row.result_image" class="flex justify-center">
                      <button
                        type="button"
                        class="group relative max-w-[220px] cursor-zoom-in rounded border-0 bg-transparent p-0 print:cursor-default"
                        title="Klik untuk perbesar"
                        @click="openReportImages(row, 'result', 0)"
                      >
                        <img
                          :src="row.result_image"
                          alt="Result"
                          class="max-h-36 w-full rounded object-contain transition group-hover:opacity-90"
                        />
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center rounded bg-black/0 opacity-0 transition group-hover:bg-black/25 group-hover:opacity-100 print:hidden">
                          <i class="fa-solid fa-search-plus text-lg text-white drop-shadow"></i>
                        </span>
                      </button>
                    </div>
                    <div v-else class="flex h-28 items-center justify-center text-xs text-gray-400">-</div>
                  </td>
                  <td class="border border-gray-400 px-3 py-3 align-top whitespace-pre-line text-sm leading-relaxed">
                    <div class="font-semibold uppercase text-gray-900">{{ row.status_name || '-' }}</div>
                    <div v-if="row.notes" class="mt-1 text-gray-700">{{ row.notes }}</div>
                    <div v-if="row.status_slug === 'closed' && row.closed_at" class="mt-1 text-xs font-medium text-emerald-700">
                      {{ row.closed_at }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>

    <VueEasyLightbox
      :visible="visibleRef"
      :imgs="imgsRef"
      :index="indexRef"
      @hide="hideLightbox"
    />
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  groups: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  filterOptions: {
    type: Object,
    default: () => ({
      categories: [],
      priorities: [],
      statuses: [],
      divisions: [],
      outlets: [],
    }),
  },
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
  router.get('/tickets/report-per-outlet', { ...localFilters }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    },
  });
}

function exportExcel() {
  const params = new URLSearchParams({ ...localFilters });
  window.open(`/tickets/report-per-outlet/export?${params.toString()}`, '_blank');
}

function printReport() {
  window.print();
}

function goBack() {
  router.visit('/tickets');
}

function showLightbox(images, index = 0) {
  imgsRef.value = images;
  indexRef.value = index;
  visibleRef.value = true;
}

function hideLightbox() {
  visibleRef.value = false;
}

function openReportImages(row, type, startIndex = 0) {
  const docImages = rowImages(row);
  const resultImages = row.result_image ? [row.result_image] : [];
  const images = type === 'result' ? resultImages : docImages;
  if (!images.length) return;
  showLightbox(images, Math.min(startIndex, images.length - 1));
}
</script>

<style scoped>
@media print {
  :deep(header),
  :deep(nav),
  :deep(aside),
  :deep(.print\\:hidden) {
    display: none !important;
  }
}
</style>
