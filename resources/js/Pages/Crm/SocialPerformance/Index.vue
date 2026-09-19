<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  sync: { type: Object, default: () => ({}) },
  content: { type: Array, default: () => [] },
  highlights: { type: Object, default: () => ({}) },
  series: { type: Object, default: () => ({}) },
  top: { type: Object, default: () => ({}) },
  totals: { type: Object, default: () => ({}) },
  platformOptions: { type: Array, default: () => [] },
});

const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const platform = ref(props.filters?.platform || 'all');
const syncing = ref(false);
const syncMessage = ref('');

watch(
  () => props.filters,
  (f) => {
    if (f?.date_from) dateFrom.value = f.date_from;
    if (f?.date_to) dateTo.value = f.date_to;
    platform.value = f?.platform || 'all';
  },
  { deep: true }
);

function applyFilters() {
  router.get(
    '/crm/social-performance',
    {
      date_from: dateFrom.value,
      date_to: dateTo.value,
      platform: platform.value === 'all' ? undefined : platform.value,
    },
    { preserveState: true, replace: true }
  );
}

function setPlatform(value) {
  platform.value = value;
  applyFilters();
}

function setPreset(days) {
  const to = new Date();
  const from = new Date();
  from.setDate(to.getDate() - (days - 1));
  dateTo.value = to.toISOString().slice(0, 10);
  dateFrom.value = from.toISOString().slice(0, 10);
  applyFilters();
}

async function runSync() {
  syncing.value = true;
  syncMessage.value = '';
  try {
    const { data } = await axios.post('/crm/social-performance/sync');
    syncMessage.value = data?.message || 'Sync selesai.';
    applyFilters();
  } catch (e) {
    syncMessage.value =
      e?.response?.data?.message || e?.message || 'Gagal sync data Meta.';
  } finally {
    syncing.value = false;
  }
}

function exportCsv() {
  const params = new URLSearchParams();
  if (dateFrom.value) params.set('date_from', dateFrom.value);
  if (dateTo.value) params.set('date_to', dateTo.value);
  if (platform.value && platform.value !== 'all') params.set('platform', platform.value);
  window.location.href = `/crm/social-performance/export.csv?${params.toString()}`;
}

function n(v) {
  return Number(v || 0).toLocaleString('id-ID');
}

function formatDayLabel(iso) {
  if (!iso) return '';
  const d = new Date(`${iso}T12:00:00`);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

function formatSyncAt(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function platformLabel(p) {
  if (p === 'instagram') return 'Instagram';
  if (p === 'facebook') return 'Facebook';
  return p || '—';
}

const chartLabels = computed(() => (props.series?.labels || []).map(formatDayLabel));

const trendSeries = computed(() => [
  { name: 'Impressions', data: props.series?.impressions || [] },
  { name: 'Likes', data: props.series?.likes || [] },
]);

const trendOptions = computed(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false }, type: 'area' },
  colors: ['#be185d', '#6366f1'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  legend: { position: 'top', fontSize: '12px' },
  xaxis: { categories: chartLabels.value },
  yaxis: { labels: { formatter: (v) => Math.round(v).toLocaleString('id-ID') } },
}));

const engagementBreakdownSeries = computed(() => [
  props.totals?.likes || 0,
  props.totals?.comments || 0,
  props.totals?.shares || 0,
  props.totals?.saved || 0,
]);

const engagementBreakdownOptions = computed(() => ({
  chart: { type: 'donut', toolbar: { show: false } },
  labels: ['Likes', 'Comments', 'Shares', 'Saved'],
  colors: ['#e11d48', '#0ea5e9', '#10b981', '#f59e0b'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true },
  plotOptions: {
    pie: {
      donut: { size: '58%', labels: { show: true, total: { show: true, label: 'Engagement' } } },
    },
  },
}));

function topBarOptions(categories, color) {
  return {
    chart: { type: 'bar', toolbar: { show: false } },
    colors: [color],
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '65%' } },
    dataLabels: { enabled: false },
    grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
    xaxis: {
      categories,
      labels: { formatter: (v) => Math.round(v).toLocaleString('id-ID') },
    },
    tooltip: { y: { formatter: (v) => n(v) } },
  };
}

const topEngagementCategories = computed(() =>
  (props.top?.engagement || []).map((r) => r.title || '—')
);
const topEngagementSeries = computed(() => [
  { name: 'Engagement', data: (props.top?.engagement || []).map((r) => r.engagement) },
]);
const topEngagementOptions = computed(() =>
  topBarOptions(topEngagementCategories.value, '#be185d')
);

const topReachCategories = computed(() => (props.top?.reach || []).map((r) => r.title || '—'));
const topReachSeries = computed(() => [
  { name: 'Reach', data: (props.top?.reach || []).map((r) => r.reach) },
]);
const topReachOptions = computed(() => topBarOptions(topReachCategories.value, '#6366f1'));

const topCommentsCategories = computed(() =>
  (props.top?.comments || []).map((r) => r.title || '—')
);
const topCommentsSeries = computed(() => [
  { name: 'Comments', data: (props.top?.comments || []).map((r) => r.comments) },
]);
const topCommentsOptions = computed(() => topBarOptions(topCommentsCategories.value, '#0ea5e9'));

const highlightItems = computed(() => [
  { key: 'best', label: 'Best Content', metric: 'Engagement', data: props.highlights?.best },
  { key: 'worst', label: 'Worst Content', metric: 'Engagement', data: props.highlights?.worst },
  {
    key: 'most_impressions',
    label: 'Most Views/Imp.',
    metric: 'Impressions',
    data: props.highlights?.most_impressions,
  },
  { key: 'most_likes', label: 'Most Likes', metric: 'Likes', data: props.highlights?.most_likes },
  {
    key: 'most_comments',
    label: 'Most Comments',
    metric: 'Comments',
    data: props.highlights?.most_comments,
  },
  { key: 'most_shares', label: 'Most Shares', metric: 'Shares', data: props.highlights?.most_shares },
  { key: 'most_saved', label: 'Most Saved', metric: 'Saved', data: props.highlights?.most_saved },
]);
</script>

<template>
  <AppLayout>
    <div class="mx-auto max-w-7xl space-y-6 p-4 md:p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-fuchsia-100 text-fuchsia-700"
          >
            <i class="fa-solid fa-chart-column text-2xl" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-slate-900">Social Media Performance</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">
              Dashboard performa konten Instagram & Facebook dari sync Meta Omnichannel.
            </p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
            :disabled="syncing"
            @click="runSync"
          >
            <i class="fa-solid fa-cloud-arrow-down" :class="{ 'fa-spin': syncing }" />
            {{ syncing ? 'Syncing…' : 'Sync Now' }}
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
            @click="exportCsv"
          >
            <i class="fa-solid fa-file-csv" />
            Export CSV
          </button>
        </div>
      </div>

      <p v-if="syncMessage" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
        {{ syncMessage }}
      </p>

      <!-- Filters -->
      <form
        class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        @submit.prevent="applyFilters"
      >
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Preset</label>
          <div class="flex flex-wrap gap-1">
            <button
              v-for="d in [7, 14, 30, 60, 90]"
              :key="d"
              type="button"
              class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="setPreset(d)"
            >
              {{ d }}d
            </button>
          </div>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Dari tanggal</label>
          <input
            v-model="dateFrom"
            type="date"
            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-fuchsia-500 focus:ring-fuchsia-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-500">Sampai tanggal</label>
          <input
            v-model="dateTo"
            type="date"
            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-fuchsia-500 focus:ring-fuchsia-500"
          />
        </div>
        <button
          type="submit"
          class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900"
        >
          <i class="fa-solid fa-filter" />
          Apply
        </button>
        <div class="ml-auto text-right text-xs text-slate-500">
          <div>
            <span
              class="mr-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800"
            >
              Data Synced
            </span>
            Last update: {{ formatSyncAt(sync?.last_synced_at) }}
          </div>
          <div class="mt-0.5">{{ n(sync?.post_count) }} konten pada filter ini</div>
        </div>
      </form>

      <!-- Platform tabs -->
      <div class="flex flex-wrap gap-2" role="tablist">
        <button
          v-for="opt in platformOptions"
          :key="opt.value"
          type="button"
          role="tab"
          class="rounded-xl px-4 py-2 text-sm font-semibold transition"
          :class="
            platform === opt.value
              ? 'bg-fuchsia-700 text-white shadow-sm'
              : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
          "
          :aria-selected="platform === opt.value"
          @click="setPlatform(opt.value)"
        >
          {{ opt.label }}
        </button>
      </div>

      <!-- KPI -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-fuchsia-200 bg-fuchsia-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-fuchsia-800">Engagement</p>
          <p class="mt-2 text-3xl font-bold text-fuchsia-950">{{ n(totals.engagement) }}</p>
        </div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-800">Impressions</p>
          <p class="mt-2 text-3xl font-bold text-indigo-950">{{ n(totals.impressions) }}</p>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-sky-800">Reach</p>
          <p class="mt-2 text-3xl font-bold text-sky-950">{{ n(totals.reach) }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-rose-800">Likes</p>
          <p class="mt-2 text-3xl font-bold text-rose-950">{{ n(totals.likes) }}</p>
        </div>
      </div>

      <!-- Content table -->
      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h2 class="text-base font-semibold text-slate-900">Content Data</h2>
          <span class="text-xs text-slate-500">Swipe / scroll untuk detail</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2">Platform</th>
                <th class="px-3 py-2">Tanggal</th>
                <th class="px-3 py-2">Konten</th>
                <th class="px-3 py-2 text-right">Likes</th>
                <th class="px-3 py-2 text-right">Comments</th>
                <th class="px-3 py-2 text-right">Shares</th>
                <th class="px-3 py-2 text-right">Saved</th>
                <th class="px-3 py-2 text-right">Imp.</th>
                <th class="px-3 py-2 text-right">Reach</th>
                <th class="px-3 py-2 text-right">Eng.</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!content.length">
                <td colspan="10" class="px-4 py-10 text-center text-slate-500">
                  Belum ada data. Klik <strong>Sync Now</strong> atau tunggu cron
                  <code class="rounded bg-slate-100 px-1">meta:sync-social-content</code>.
                </td>
              </tr>
              <tr
                v-for="row in content"
                :key="row.id"
                class="border-t border-slate-100 hover:bg-slate-50/80"
              >
                <td class="px-3 py-2 whitespace-nowrap">
                  <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="
                      row.platform === 'instagram'
                        ? 'bg-pink-100 text-pink-800'
                        : 'bg-blue-100 text-blue-800'
                    "
                  >
                    {{ platformLabel(row.platform) }}
                  </span>
                </td>
                <td class="px-3 py-2 whitespace-nowrap text-slate-600">
                  {{ formatDayLabel(row.posted_date) }}
                </td>
                <td class="max-w-xs px-3 py-2">
                  <div class="flex items-center gap-2">
                    <img
                      v-if="row.thumbnail_url"
                      :src="row.thumbnail_url"
                      alt=""
                      class="h-10 w-10 rounded-lg object-cover"
                    />
                    <div class="min-w-0">
                      <a
                        v-if="row.permalink"
                        :href="row.permalink"
                        target="_blank"
                        rel="noopener"
                        class="block truncate font-medium text-slate-900 hover:text-fuchsia-700"
                      >
                        {{ row.title }}
                      </a>
                      <span v-else class="block truncate font-medium text-slate-900">{{ row.title }}</span>
                      <span class="block truncate text-xs text-slate-500">{{
                        row.account_label || row.account_id
                      }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2 text-right tabular-nums">{{ n(row.likes) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ n(row.comments) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ n(row.shares) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ n(row.saved) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ n(row.impressions) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">{{ n(row.reach) }}</td>
                <td class="px-3 py-2 text-right font-semibold tabular-nums text-fuchsia-800">
                  {{ n(row.engagement) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Highlights -->
      <section>
        <h2 class="mb-3 text-base font-semibold text-slate-900">Content Performance Highlights</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
          <div
            v-for="item in highlightItems"
            :key="item.key"
            class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"
          >
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
              {{ item.label }}
            </p>
            <template v-if="item.data">
              <p class="mt-2 line-clamp-2 text-sm font-semibold text-slate-900">{{ item.data.title }}</p>
              <p class="mt-1 text-lg font-bold text-fuchsia-800">{{ n(item.data.value) }}</p>
              <p class="text-[11px] text-slate-500">{{ item.metric }}</p>
            </template>
            <template v-else>
              <p class="mt-2 text-sm text-slate-400">—</p>
            </template>
          </div>
        </div>
      </section>

      <!-- Charts -->
      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Daily Trend: Impressions & Likes</h2>
          <p class="mt-0.5 text-xs text-slate-500">
            Agregat metrik berdasarkan tanggal publish konten
          </p>
          <div class="mt-4">
            <apexchart type="area" height="300" :options="trendOptions" :series="trendSeries" />
          </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Engagement Breakdown</h2>
          <p class="mt-0.5 text-xs text-slate-500">Likes, comments, shares, saved</p>
          <div class="mt-4">
            <apexchart
              type="donut"
              height="300"
              :options="engagementBreakdownOptions"
              :series="engagementBreakdownSeries"
            />
          </div>
        </section>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Top 5 Engagement</h2>
          <div class="mt-4">
            <apexchart
              v-if="(top.engagement || []).length"
              type="bar"
              height="280"
              :options="topEngagementOptions"
              :series="topEngagementSeries"
            />
            <p v-else class="py-10 text-center text-sm text-slate-400">Tidak ada data</p>
          </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Top 5 Reach</h2>
          <div class="mt-4">
            <apexchart
              v-if="(top.reach || []).length"
              type="bar"
              height="280"
              :options="topReachOptions"
              :series="topReachSeries"
            />
            <p v-else class="py-10 text-center text-sm text-slate-400">Tidak ada data</p>
          </div>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Top 5 Comments</h2>
          <div class="mt-4">
            <apexchart
              v-if="(top.comments || []).length"
              type="bar"
              height="280"
              :options="topCommentsOptions"
              :series="topCommentsSeries"
            />
            <p v-else class="py-10 text-center text-sm text-slate-400">Tidak ada data</p>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
