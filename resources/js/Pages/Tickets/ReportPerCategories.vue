<template>
  <AppLayout title="Report Ticket Per Categories">
    <div class="w-full py-6 px-4 space-y-6 print:py-2 print:px-2">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between print:hidden">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Report Ticket Per Categories</h1>
          <p class="mt-1 text-sm text-gray-600">
            Ringkasan temuan per kategori dengan foto complain dan hasil penyelesaian.
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

      <div id="ticket-category-report" class="space-y-8">
        <div v-if="!groups.length" class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
          Tidak ada data ticket untuk filter ini.
        </div>

        <section v-for="group in groups" :key="group.category_id" class="overflow-hidden rounded-xl border border-gray-300 bg-white">
          <div class="bg-yellow-300 px-4 py-2 text-center text-sm font-extrabold tracking-wide text-gray-900">
            [{{ String(group.category_name || 'UNCATEGORIZED').toUpperCase() }}]
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
              <thead>
                <tr class="bg-white">
                  <th class="w-12 border border-gray-400 px-2 py-2 text-center font-bold">NO</th>
                  <th class="w-24 border border-gray-400 px-2 py-2 text-center font-bold">TANGGAL</th>
                  <th class="min-w-[220px] border border-gray-400 px-3 py-2 text-center font-bold">FINDING PROBLEM</th>
                  <th class="w-56 border border-gray-400 px-2 py-2 text-center font-bold">COMPLAIN</th>
                  <th class="w-56 border border-gray-400 px-2 py-2 text-center font-bold">RESULT</th>
                  <th class="min-w-[160px] border border-gray-400 px-3 py-2 text-center font-bold">REMARK</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in group.rows" :key="row.ticket_id">
                  <td class="border border-gray-400 px-2 py-3 text-center align-top">{{ row.no }}</td>
                  <td class="border border-gray-400 px-2 py-3 text-center align-top whitespace-nowrap">{{ row.tanggal }}</td>
                  <td class="border border-gray-400 px-3 py-3 align-top">
                    <div class="font-medium text-gray-900">{{ row.finding_problem }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ row.ticket_number }}</div>
                  </td>
                  <td class="border border-gray-400 p-2 align-top">
                    <div v-if="row.complain_image" class="flex justify-center">
                      <button
                        type="button"
                        class="group relative max-w-[220px] cursor-zoom-in rounded border-0 bg-transparent p-0 print:cursor-default"
                        title="Klik untuk perbesar"
                        @click="openReportImage(row, 'complain')"
                      >
                        <img
                          :src="row.complain_image"
                          alt="Complain"
                          class="max-h-36 w-full rounded object-contain transition group-hover:opacity-90"
                        />
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center rounded bg-black/0 opacity-0 transition group-hover:bg-black/25 group-hover:opacity-100 print:hidden">
                          <i class="fa-solid fa-search-plus text-lg text-white drop-shadow"></i>
                        </span>
                      </button>
                    </div>
                    <div v-else class="flex h-28 items-center justify-center text-xs text-gray-400">-</div>
                  </td>
                  <td class="border border-gray-400 p-2 align-top">
                    <div v-if="row.result_image" class="flex justify-center">
                      <button
                        type="button"
                        class="group relative max-w-[220px] cursor-zoom-in rounded border-0 bg-transparent p-0 print:cursor-default"
                        title="Klik untuk perbesar"
                        @click="openReportImage(row, 'result')"
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
                    <div class="font-semibold text-gray-900">{{ row.status_name || '-' }}</div>
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

function reloadData() {
  loading.value = true;
  router.get('/tickets/report-per-categories', { ...localFilters }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    },
  });
}

function exportExcel() {
  const params = new URLSearchParams({ ...localFilters });
  window.open(`/tickets/report-per-categories/export?${params.toString()}`, '_blank');
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

function openReportImage(row, type) {
  const images = [row.complain_image, row.result_image].filter(Boolean);
  if (!images.length) return;

  const target = type === 'complain' ? row.complain_image : row.result_image;
  const index = Math.max(0, images.indexOf(target));
  showLightbox(images, index);
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
