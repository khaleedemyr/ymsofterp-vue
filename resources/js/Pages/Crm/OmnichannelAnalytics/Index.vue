<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  summary: { type: Object, default: () => ({}) },
  series: { type: Object, default: () => ({}) },
  channelOptions: { type: Array, default: () => [] },
});

const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const channel = ref(props.filters?.channel || 'all');

watch(
  () => props.filters,
  (f) => {
    if (f?.date_from) dateFrom.value = f.date_from;
    if (f?.date_to) dateTo.value = f.date_to;
    channel.value = f?.channel || 'all';
  },
  { deep: true }
);

function applyFilters() {
  router.get(
    '/crm/omnichannel-chat-analytics',
    {
      date_from: dateFrom.value,
      date_to: dateTo.value,
      channel: channel.value === 'all' ? undefined : channel.value,
    },
    { preserveState: true, replace: true }
  );
}

function formatDayLabel(iso) {
  if (!iso) return '';
  const d = new Date(`${iso}T12:00:00`);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

const chartLabels = computed(() => (props.series?.labels || []).map(formatDayLabel));

const newChatsSeries = computed(() => [
  { name: 'Chat masuk', data: props.series?.new_chats || [] },
]);

const messagesSeries = computed(() => [
  { name: 'Diterima', data: props.series?.inbound_messages || [] },
  { name: 'Terkirim', data: props.series?.outbound_messages || [] },
]);

const frtSeries = computed(() => [
  {
    name: 'Rata-rata balas pertama (menit)',
    data: (props.series?.avg_first_response_minutes || []).map((v) =>
      v === null || v === undefined ? null : Number(v)
    ),
  },
]);

const channelBreakdownSeries = computed(() => {
  const rows = props.summary?.by_channel || [];
  return [
    { name: 'Chat masuk', data: rows.map((r) => r.new_chats) },
    { name: 'Diterima', data: rows.map((r) => r.inbound) },
    { name: 'Terkirim', data: rows.map((r) => r.outbound) },
  ];
});

const channelBreakdownCategories = computed(() =>
  (props.summary?.by_channel || []).map((r) => r.label)
);

const baseChartOptions = {
  chart: { toolbar: { show: false }, zoom: { enabled: false } },
  dataLabels: { enabled: false },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  legend: { position: 'top', fontSize: '12px' },
};

const newChatsOptions = computed(() => ({
  ...baseChartOptions,
  chart: { ...baseChartOptions.chart, type: 'bar' },
  colors: ['#6366f1'],
  plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
  xaxis: { categories: chartLabels.value },
  yaxis: { labels: { formatter: (v) => Math.round(v) } },
  tooltip: { y: { formatter: (v) => `${v} chat` } },
}));

const messagesOptions = computed(() => ({
  ...baseChartOptions,
  chart: { ...baseChartOptions.chart, type: 'area', stacked: false },
  colors: ['#0ea5e9', '#10b981'],
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
  xaxis: { categories: chartLabels.value },
  yaxis: { labels: { formatter: (v) => Math.round(v) } },
}));

const frtOptions = computed(() => ({
  ...baseChartOptions,
  chart: { ...baseChartOptions.chart, type: 'line' },
  colors: ['#f59e0b'],
  stroke: { curve: 'smooth', width: 3 },
  markers: { size: 4 },
  xaxis: { categories: chartLabels.value },
  yaxis: {
    labels: { formatter: (v) => `${Number(v).toFixed(0)} mnt` },
    title: { text: 'Menit' },
  },
  tooltip: {
    y: {
      formatter: (v) => (v == null ? '—' : `${Number(v).toFixed(1)} menit`),
    },
  },
}));

const channelOptionsChart = computed(() => ({
  ...baseChartOptions,
  chart: { ...baseChartOptions.chart, type: 'bar' },
  plotOptions: { bar: { horizontal: false, columnWidth: '65%', borderRadius: 4 } },
  colors: ['#6366f1', '#0ea5e9', '#10b981'],
  xaxis: { categories: channelBreakdownCategories.value },
  yaxis: { labels: { formatter: (v) => Math.round(v) } },
}));

function n(v) {
  return Number(v || 0).toLocaleString('id-ID');
}
</script>

<template>
  <AppLayout>
    <div class="mx-auto max-w-7xl space-y-6 p-4 md:p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700"
          >
            <i class="fa-solid fa-chart-line text-2xl" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-slate-900">Analisis Chat Omnichannel</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">
              Ringkasan chat masuk, volume pesan, dan waktu rata-rata membalas chat pertama per periode.
            </p>
          </div>
        </div>
      </div>

      <!-- Filter -->
      <form
        class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        @submit.prevent="applyFilters"
      >
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Dari tanggal</label>
          <input
            v-model="dateFrom"
            type="date"
            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Sampai tanggal</label>
          <input
            v-model="dateTo"
            type="date"
            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Kanal</label>
          <select
            v-model="channel"
            class="min-w-[160px] rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
          >
            <option
              v-for="opt in channelOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>
        <button
          type="submit"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
        >
          <i class="fa-solid fa-filter" />
          Terapkan
        </button>
      </form>

      <!-- KPI cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-800">Chat masuk</p>
          <p class="mt-2 text-3xl font-bold text-indigo-950">{{ n(summary.new_chats) }}</p>
          <p class="mt-1 text-xs text-indigo-700">Percakapan baru (pesan masuk pertama pelanggan)</p>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-sky-800">Pesan diterima</p>
          <p class="mt-2 text-3xl font-bold text-sky-950">{{ n(summary.inbound_messages) }}</p>
          <p class="mt-1 text-xs text-sky-700">Inbound dari pelanggan</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Pesan terkirim</p>
          <p class="mt-2 text-3xl font-bold text-emerald-950">{{ n(summary.outbound_messages) }}</p>
          <p class="mt-1 text-xs text-emerald-700">Outbound ke pelanggan</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Balas chat pertama</p>
          <p class="mt-2 text-2xl font-bold leading-tight text-amber-950">
            {{ summary.avg_first_response_label || '—' }}
          </p>
          <p class="mt-1 text-xs text-amber-700">
            Rata-rata dari {{ n(summary.conversations_with_reply) }} chat yang sudah dibalas
          </p>
        </div>
      </div>

      <!-- Charts row 1 -->
      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Chat masuk per hari</h2>
          <p class="mt-0.5 text-xs text-slate-500">Jumlah percakapan baru berdasarkan hari pesan masuk pertama</p>
          <div class="mt-4">
            <apexchart
              type="bar"
              height="300"
              :options="newChatsOptions"
              :series="newChatsSeries"
            />
          </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Pesan diterima & terkirim</h2>
          <p class="mt-0.5 text-xs text-slate-500">Volume pesan inbound vs outbound per hari</p>
          <div class="mt-4">
            <apexchart
              type="area"
              height="300"
              :options="messagesOptions"
              :series="messagesSeries"
            />
          </div>
        </section>
      </div>

      <!-- Charts row 2 -->
      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Waktu balas chat pertama</h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Rata-rata menit dari pesan masuk pertama pelanggan hingga balasan outbound pertama
          </p>
          <div class="mt-4">
            <apexchart
              type="line"
              height="300"
              :options="frtOptions"
              :series="frtSeries"
            />
          </div>
        </section>

        <section
          v-if="(summary.by_channel || []).length"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <h2 class="text-base font-semibold text-slate-900">Per kanal (periode ini)</h2>
          <p class="mt-0.5 text-xs text-slate-500">Perbandingan chat masuk dan volume pesan per kanal</p>
          <div class="mt-4">
            <apexchart
              type="bar"
              height="300"
              :options="channelOptionsChart"
              :series="channelBreakdownSeries"
            />
          </div>
        </section>
        <section
          v-else
          class="flex items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500"
        >
          Tidak ada data per kanal pada filter ini.
        </section>
      </div>
    </div>
  </AppLayout>
</template>
