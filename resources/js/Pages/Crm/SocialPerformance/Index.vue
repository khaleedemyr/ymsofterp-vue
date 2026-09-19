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
const syncErrors = ref([]);

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
  syncErrors.value = [];
  try {
    const { data } = await axios.post('/crm/social-performance/sync');
    syncMessage.value = data?.message || 'Sync selesai.';
    syncErrors.value = data?.result?.error_details || [];
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
  if (!iso) return 'Belum pernah sync';
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
  chart: {
    toolbar: { show: false },
    zoom: { enabled: false },
    type: 'area',
    fontFamily: 'inherit',
    animations: { enabled: true, speed: 600 },
  },
  colors: ['#0f766e', '#c2410c'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2.5 },
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 90, 100] },
  },
  grid: { borderColor: '#e7e5e4', strokeDashArray: 3, padding: { left: 8, right: 8 } },
  legend: { position: 'top', fontSize: '12px', fontWeight: 600, markers: { radius: 12 } },
  xaxis: {
    categories: chartLabels.value,
    labels: { style: { colors: '#78716c', fontSize: '11px' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: {
      style: { colors: '#78716c', fontSize: '11px' },
      formatter: (v) => Math.round(v).toLocaleString('id-ID'),
    },
  },
  tooltip: { theme: 'light', y: { formatter: (v) => n(v) } },
}));

const engagementBreakdownSeries = computed(() => [
  props.totals?.likes || 0,
  props.totals?.comments || 0,
  props.totals?.shares || 0,
  props.totals?.saved || 0,
]);

const engagementBreakdownOptions = computed(() => ({
  chart: { type: 'donut', toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: true } },
  labels: ['Likes', 'Comments', 'Shares', 'Saved'],
  colors: ['#c2410c', '#0369a1', '#0f766e', '#a16207'],
  legend: { position: 'bottom', fontSize: '12px', fontWeight: 600 },
  dataLabels: { enabled: false },
  stroke: { width: 0 },
  plotOptions: {
    pie: {
      donut: {
        size: '72%',
        labels: {
          show: true,
          name: { show: true, fontSize: '12px', color: '#78716c' },
          value: {
            show: true,
            fontSize: '22px',
            fontWeight: 700,
            color: '#1c1917',
            formatter: (v) => Number(v).toLocaleString('id-ID'),
          },
          total: {
            show: true,
            label: 'Total',
            fontSize: '12px',
            color: '#78716c',
            formatter: () => n(props.totals?.engagement || 0),
          },
        },
      },
    },
  },
}));

function topBarOptions(categories, color) {
  return {
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: true } },
    colors: [color],
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '62%' } },
    dataLabels: { enabled: false },
    grid: { borderColor: '#e7e5e4', strokeDashArray: 3 },
    xaxis: {
      categories,
      labels: {
        style: { colors: '#78716c', fontSize: '11px' },
        formatter: (v) => Math.round(v).toLocaleString('id-ID'),
      },
    },
    yaxis: {
      labels: {
        style: { colors: '#44403c', fontSize: '11px', fontWeight: 600 },
        maxWidth: 140,
      },
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
  topBarOptions(topEngagementCategories.value, '#0f766e')
);

const topReachCategories = computed(() => (props.top?.reach || []).map((r) => r.title || '—'));
const topReachSeries = computed(() => [
  { name: 'Reach', data: (props.top?.reach || []).map((r) => r.reach) },
]);
const topReachOptions = computed(() => topBarOptions(topReachCategories.value, '#0369a1'));

const topCommentsCategories = computed(() =>
  (props.top?.comments || []).map((r) => r.title || '—')
);
const topCommentsSeries = computed(() => [
  { name: 'Comments', data: (props.top?.comments || []).map((r) => r.comments) },
]);
const topCommentsOptions = computed(() => topBarOptions(topCommentsCategories.value, '#c2410c'));

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

const kpiCards = computed(() => [
  {
    key: 'engagement',
    label: 'Engagement',
    value: props.totals?.engagement,
    hint: 'Likes + comments + shares + saved',
    icon: 'fa-solid fa-bolt',
    tone: 'teal',
  },
  {
    key: 'impressions',
    label: 'Impressions',
    value: props.totals?.impressions,
    hint: 'Total views / impressions',
    icon: 'fa-solid fa-eye',
    tone: 'sky',
  },
  {
    key: 'reach',
    label: 'Reach',
    value: props.totals?.reach,
    hint: 'Unique accounts reached',
    icon: 'fa-solid fa-users',
    tone: 'stone',
  },
  {
    key: 'likes',
    label: 'Likes',
    value: props.totals?.likes,
    hint: 'Total reactions / likes',
    icon: 'fa-solid fa-heart',
    tone: 'orange',
  },
]);
</script>

<template>
  <AppLayout>
    <div class="sp-page">
      <!-- Header -->
      <header class="sp-header">
        <div class="sp-header-left">
          <div class="sp-brand-icon">
            <i class="fa-solid fa-chart-column" />
          </div>
          <div>
            <p class="sp-eyebrow">CRM · Omnichannel</p>
            <h1>Social Media Performance</h1>
            <p class="sp-sub">
              Performa konten Instagram & Facebook · sync otomatis Meta Graph
            </p>
          </div>
        </div>
        <div class="sp-header-actions">
          <button type="button" class="sp-btn sp-btn-ghost" :disabled="syncing" @click="runSync">
            <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': syncing }" />
            {{ syncing ? 'Syncing…' : 'Sync Now' }}
          </button>
          <button type="button" class="sp-btn sp-btn-primary" @click="exportCsv">
            <i class="fa-solid fa-download" />
            Export CSV
          </button>
        </div>
      </header>

      <div v-if="syncMessage" class="sp-alert" :class="{ 'sp-alert-warn': syncErrors.length }">
        <div class="sp-alert-main">
          <i class="fa-solid" :class="syncErrors.length ? 'fa-triangle-exclamation' : 'fa-circle-check'" />
          <span>{{ syncMessage }}</span>
        </div>
        <ul v-if="syncErrors.length" class="sp-alert-list">
          <li v-for="(err, i) in syncErrors" :key="i">{{ err }}</li>
        </ul>
      </div>

      <!-- Toolbar -->
      <section class="sp-toolbar">
        <form class="sp-filters" @submit.prevent="applyFilters">
          <div class="sp-presets">
            <button
              v-for="d in [7, 14, 30, 60, 90]"
              :key="d"
              type="button"
              class="sp-chip"
              @click="setPreset(d)"
            >
              {{ d }}d
            </button>
          </div>
          <div class="sp-date-group">
            <label>
              <span>Dari</span>
              <input v-model="dateFrom" type="date" />
            </label>
            <span class="sp-date-sep">—</span>
            <label>
              <span>Sampai</span>
              <input v-model="dateTo" type="date" />
            </label>
          </div>
          <button type="submit" class="sp-btn sp-btn-dark">Apply</button>
        </form>

        <div class="sp-sync-meta">
          <span class="sp-pulse" />
          <div>
            <strong>Data Synced</strong>
            <p>
              {{ formatSyncAt(sync?.last_synced_at) }} · filter {{ n(sync?.post_count) }}
              · DB IG {{ n(sync?.ig_total) }} / FB {{ n(sync?.fb_total) }}
            </p>
          </div>
        </div>
      </section>

      <!-- Platform tabs -->
      <div class="sp-tabs" role="tablist">
        <button
          v-for="opt in platformOptions"
          :key="opt.value"
          type="button"
          role="tab"
          class="sp-tab"
          :class="{ active: platform === opt.value }"
          :aria-selected="platform === opt.value"
          @click="setPlatform(opt.value)"
        >
          <i
            v-if="opt.value === 'instagram'"
            class="fa-brands fa-instagram"
          />
          <i
            v-else-if="opt.value === 'facebook'"
            class="fa-brands fa-facebook"
          />
          <i v-else class="fa-solid fa-layer-group" />
          {{ opt.label }}
        </button>
      </div>

      <!-- KPI -->
      <section class="sp-kpi-grid">
        <article
          v-for="card in kpiCards"
          :key="card.key"
          class="sp-kpi"
          :data-tone="card.tone"
        >
          <div class="sp-kpi-top">
            <span>{{ card.label }}</span>
            <i :class="card.icon" />
          </div>
          <p class="sp-kpi-value">{{ n(card.value) }}</p>
          <p class="sp-kpi-hint">{{ card.hint }}</p>
        </article>
      </section>

      <!-- Content table -->
      <section class="sp-panel">
        <div class="sp-panel-head">
          <div>
            <h2>Content Data</h2>
            <p>Konten terbaru pada rentang tanggal & platform terpilih</p>
          </div>
        </div>
        <div class="sp-table-wrap">
          <table class="sp-table">
            <thead>
              <tr>
                <th>Platform</th>
                <th>Tanggal</th>
                <th>Konten</th>
                <th class="num">Likes</th>
                <th class="num">Comments</th>
                <th class="num">Shares</th>
                <th class="num">Saved</th>
                <th class="num">Imp.</th>
                <th class="num">Reach</th>
                <th class="num">Eng.</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!content.length">
                <td colspan="10" class="sp-empty">
                  <template v-if="platform === 'facebook'">
                    Belum ada data Facebook. Klik <strong>Sync Now</strong> lalu cek detail error di atas
                    (pastikan <code>META_PAGE_TOKENS</code> terisi Page ID, bukan hanya IG ID).
                  </template>
                  <template v-else>
                    Belum ada data. Klik <strong>Sync Now</strong> atau tunggu cron
                    <code>meta:sync-social-content</code>.
                  </template>
                </td>
              </tr>
              <tr v-for="row in content" :key="row.id">
                <td>
                  <span class="sp-badge" :data-platform="row.platform">
                    {{ platformLabel(row.platform) }}
                  </span>
                </td>
                <td class="muted">{{ formatDayLabel(row.posted_date) }}</td>
                <td>
                  <div class="sp-content-cell">
                    <img
                      v-if="row.thumbnail_url"
                      :src="row.thumbnail_url"
                      alt=""
                      loading="lazy"
                    />
                    <div class="sp-content-text">
                      <a
                        v-if="row.permalink"
                        :href="row.permalink"
                        target="_blank"
                        rel="noopener"
                      >{{ row.title }}</a>
                      <span v-else>{{ row.title }}</span>
                      <small>{{ row.account_label || row.account_id }}</small>
                    </div>
                  </div>
                </td>
                <td class="num">{{ n(row.likes) }}</td>
                <td class="num">{{ n(row.comments) }}</td>
                <td class="num">{{ n(row.shares) }}</td>
                <td class="num">{{ n(row.saved) }}</td>
                <td class="num">{{ n(row.impressions) }}</td>
                <td class="num">{{ n(row.reach) }}</td>
                <td class="num eng">{{ n(row.engagement) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Highlights -->
      <section class="sp-panel">
        <div class="sp-panel-head">
          <div>
            <h2>Content Performance Highlights</h2>
            <p>Konten terbaik & metrik tertinggi di periode ini</p>
          </div>
        </div>
        <div class="sp-highlights">
          <article v-for="item in highlightItems" :key="item.key" class="sp-hl">
            <p class="sp-hl-label">{{ item.label }}</p>
            <template v-if="item.data">
              <p class="sp-hl-title">{{ item.data.title }}</p>
              <p class="sp-hl-value">{{ n(item.data.value) }}</p>
              <p class="sp-hl-metric">{{ item.metric }}</p>
            </template>
            <p v-else class="sp-hl-empty">—</p>
          </article>
        </div>
      </section>

      <!-- Charts -->
      <div class="sp-charts-2">
        <section class="sp-panel">
          <div class="sp-panel-head">
            <div>
              <h2>Daily Trend</h2>
              <p>Impressions & likes berdasarkan tanggal publish</p>
            </div>
          </div>
          <apexchart type="area" height="320" :options="trendOptions" :series="trendSeries" />
        </section>
        <section class="sp-panel">
          <div class="sp-panel-head">
            <div>
              <h2>Engagement Breakdown</h2>
              <p>Komposisi likes, comments, shares, saved</p>
            </div>
          </div>
          <apexchart
            type="donut"
            height="320"
            :options="engagementBreakdownOptions"
            :series="engagementBreakdownSeries"
          />
        </section>
      </div>

      <div class="sp-charts-3">
        <section class="sp-panel">
          <div class="sp-panel-head"><div><h2>Top 5 Engagement</h2></div></div>
          <apexchart
            v-if="(top.engagement || []).length"
            type="bar"
            height="280"
            :options="topEngagementOptions"
            :series="topEngagementSeries"
          />
          <p v-else class="sp-empty soft">Tidak ada data</p>
        </section>
        <section class="sp-panel">
          <div class="sp-panel-head"><div><h2>Top 5 Reach</h2></div></div>
          <apexchart
            v-if="(top.reach || []).length"
            type="bar"
            height="280"
            :options="topReachOptions"
            :series="topReachSeries"
          />
          <p v-else class="sp-empty soft">Tidak ada data</p>
        </section>
        <section class="sp-panel">
          <div class="sp-panel-head"><div><h2>Top 5 Comments</h2></div></div>
          <apexchart
            v-if="(top.comments || []).length"
            type="bar"
            height="280"
            :options="topCommentsOptions"
            :series="topCommentsSeries"
          />
          <p v-else class="sp-empty soft">Tidak ada data</p>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.sp-page {
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 1.25rem 1.5rem 2.5rem;
  background:
    radial-gradient(1200px 400px at 0% -10%, rgba(15, 118, 110, 0.08), transparent 55%),
    radial-gradient(900px 320px at 100% 0%, rgba(194, 65, 12, 0.06), transparent 50%),
    #f5f5f4;
  min-height: 100%;
  box-sizing: border-box;
}

.sp-header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.sp-header-left {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.sp-brand-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: linear-gradient(145deg, #0f766e, #115e59);
  color: #fff;
  font-size: 1.25rem;
  box-shadow: 0 10px 24px rgba(15, 118, 110, 0.28);
}

.sp-eyebrow {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #78716c;
}

.sp-header h1 {
  margin: 0.15rem 0 0;
  font-size: 1.65rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #1c1917;
}

.sp-sub {
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
  color: #78716c;
}

.sp-header-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.sp-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border-radius: 0.85rem;
  padding: 0.65rem 1rem;
  font-size: 0.875rem;
  font-weight: 700;
  border: 1px solid transparent;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
}

.sp-btn:hover:not(:disabled) {
  transform: translateY(-1px);
}

.sp-btn:disabled {
  opacity: 0.65;
  cursor: wait;
}

.sp-btn-ghost {
  background: #fff;
  border-color: #d6d3d1;
  color: #44403c;
  box-shadow: 0 1px 2px rgba(28, 25, 23, 0.04);
}

.sp-btn-ghost:hover:not(:disabled) {
  border-color: #a8a29e;
  background: #fafaf9;
}

.sp-btn-primary {
  background: #0f766e;
  color: #fff;
  box-shadow: 0 8px 18px rgba(15, 118, 110, 0.25);
}

.sp-btn-primary:hover {
  background: #0d9488;
}

.sp-btn-dark {
  background: #1c1917;
  color: #fff;
}

.sp-btn-dark:hover {
  background: #292524;
}

.sp-alert {
  margin-bottom: 1rem;
  border-radius: 1rem;
  border: 1px solid #d6d3d1;
  background: #fff;
  padding: 0.85rem 1rem;
  box-shadow: 0 1px 2px rgba(28, 25, 23, 0.04);
}

.sp-alert-warn {
  border-color: #fdba74;
  background: #fff7ed;
}

.sp-alert-main {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  font-size: 0.9rem;
  color: #44403c;
  font-weight: 600;
}

.sp-alert-list {
  margin: 0.55rem 0 0 1.5rem;
  padding: 0;
  font-size: 0.8rem;
  color: #9a3412;
  font-weight: 500;
}

.sp-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1rem;
  border-radius: 1.15rem;
  background: rgba(255, 255, 255, 0.9);
  border: 1px solid #e7e5e4;
  backdrop-filter: blur(8px);
  box-shadow: 0 1px 2px rgba(28, 25, 23, 0.04);
  margin-bottom: 0.85rem;
}

.sp-filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem;
}

.sp-presets {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.sp-chip {
  border: 1px solid #e7e5e4;
  background: #fafaf9;
  color: #57534e;
  border-radius: 999px;
  padding: 0.35rem 0.7rem;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sp-chip:hover {
  background: #1c1917;
  border-color: #1c1917;
  color: #fff;
}

.sp-date-group {
  display: flex;
  align-items: end;
  gap: 0.4rem;
}

.sp-date-group label {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #a8a29e;
}

.sp-date-group input {
  border: 1px solid #d6d3d1;
  border-radius: 0.7rem;
  padding: 0.45rem 0.65rem;
  font-size: 0.85rem;
  color: #1c1917;
  background: #fff;
}

.sp-date-sep {
  color: #a8a29e;
  padding-bottom: 0.55rem;
}

.sp-sync-meta {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 220px;
}

.sp-pulse {
  width: 0.65rem;
  height: 0.65rem;
  border-radius: 999px;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.55);
  animation: spPulse 2s infinite;
}

@keyframes spPulse {
  0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45); }
  70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.sp-sync-meta strong {
  display: block;
  font-size: 0.8rem;
  color: #1c1917;
}

.sp-sync-meta p {
  margin: 0.1rem 0 0;
  font-size: 0.75rem;
  color: #78716c;
}

.sp-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 1rem;
}

.sp-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border-radius: 999px;
  border: 1px solid #e7e5e4;
  background: #fff;
  color: #57534e;
  padding: 0.55rem 1rem;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.18s ease;
}

.sp-tab:hover {
  border-color: #a8a29e;
}

.sp-tab.active {
  background: #1c1917;
  border-color: #1c1917;
  color: #fff;
  box-shadow: 0 8px 18px rgba(28, 25, 23, 0.18);
}

.sp-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.85rem;
  margin-bottom: 1rem;
}

.sp-kpi {
  position: relative;
  overflow: hidden;
  border-radius: 1.15rem;
  padding: 1.1rem 1.15rem;
  background: #fff;
  border: 1px solid #e7e5e4;
  box-shadow: 0 1px 2px rgba(28, 25, 23, 0.04);
  transition: transform 0.18s ease, box-shadow 0.2s ease;
}

.sp-kpi::before {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 4px;
}

.sp-kpi[data-tone='teal']::before { background: #0f766e; }
.sp-kpi[data-tone='sky']::before { background: #0369a1; }
.sp-kpi[data-tone='stone']::before { background: #57534e; }
.sp-kpi[data-tone='orange']::before { background: #c2410c; }

.sp-kpi:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(28, 25, 23, 0.08);
}

.sp-kpi-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #78716c;
}

.sp-kpi-top i {
  opacity: 0.7;
}

.sp-kpi-value {
  margin: 0.55rem 0 0.2rem;
  font-size: 1.85rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #1c1917;
}

.sp-kpi-hint {
  margin: 0;
  font-size: 0.75rem;
  color: #a8a29e;
}

.sp-panel {
  background: #fff;
  border: 1px solid #e7e5e4;
  border-radius: 1.25rem;
  padding: 1rem 1.1rem 1.15rem;
  box-shadow: 0 1px 2px rgba(28, 25, 23, 0.04);
  margin-bottom: 1rem;
}

.sp-panel-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.85rem;
}

.sp-panel-head h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  color: #1c1917;
}

.sp-panel-head p {
  margin: 0.2rem 0 0;
  font-size: 0.8rem;
  color: #a8a29e;
}

.sp-table-wrap {
  overflow-x: auto;
  border-radius: 0.9rem;
  border: 1px solid #f5f5f4;
}

.sp-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 980px;
  font-size: 0.85rem;
}

.sp-table th {
  text-align: left;
  padding: 0.7rem 0.75rem;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #a8a29e;
  background: #fafaf9;
  border-bottom: 1px solid #e7e5e4;
  position: sticky;
  top: 0;
}

.sp-table td {
  padding: 0.7rem 0.75rem;
  border-bottom: 1px solid #f5f5f4;
  color: #44403c;
  vertical-align: middle;
}

.sp-table tbody tr {
  transition: background 0.15s ease;
}

.sp-table tbody tr:hover {
  background: #fafaf9;
}

.sp-table .num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.sp-table .eng {
  font-weight: 800;
  color: #0f766e;
}

.sp-table .muted {
  color: #78716c;
  white-space: nowrap;
}

.sp-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.2rem 0.55rem;
  font-size: 0.7rem;
  font-weight: 800;
}

.sp-badge[data-platform='instagram'] {
  background: #fce7f3;
  color: #9d174d;
}

.sp-badge[data-platform='facebook'] {
  background: #dbeafe;
  color: #1d4ed8;
}

.sp-content-cell {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 220px;
}

.sp-content-cell img {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.7rem;
  object-fit: cover;
  background: #f5f5f4;
  flex-shrink: 0;
}

.sp-content-text {
  min-width: 0;
}

.sp-content-text a,
.sp-content-text > span {
  display: block;
  font-weight: 700;
  color: #1c1917;
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 320px;
}

.sp-content-text a:hover {
  color: #0f766e;
}

.sp-content-text small {
  display: block;
  margin-top: 0.1rem;
  color: #a8a29e;
  font-size: 0.72rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sp-empty {
  text-align: center;
  padding: 2.5rem 1rem !important;
  color: #78716c;
}

.sp-empty.soft {
  border: none;
  padding: 2rem 1rem !important;
}

.sp-empty code {
  background: #f5f5f4;
  padding: 0.1rem 0.35rem;
  border-radius: 0.35rem;
  font-size: 0.8em;
}

.sp-highlights {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 0.65rem;
}

.sp-hl {
  border-radius: 1rem;
  border: 1px solid #e7e5e4;
  background: linear-gradient(180deg, #fafaf9, #fff);
  padding: 0.85rem;
  min-height: 7.5rem;
  transition: transform 0.15s ease, border-color 0.15s ease;
}

.sp-hl:hover {
  transform: translateY(-2px);
  border-color: #d6d3d1;
}

.sp-hl-label {
  margin: 0;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #a8a29e;
}

.sp-hl-title {
  margin: 0.55rem 0 0;
  font-size: 0.82rem;
  font-weight: 700;
  color: #1c1917;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.4em;
}

.sp-hl-value {
  margin: 0.45rem 0 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f766e;
}

.sp-hl-metric {
  margin: 0.1rem 0 0;
  font-size: 0.7rem;
  color: #a8a29e;
}

.sp-hl-empty {
  margin: 1.4rem 0 0;
  color: #d6d3d1;
  font-size: 1.25rem;
}

.sp-charts-2 {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 1rem;
  margin-bottom: 0;
}

.sp-charts-3 {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

@media (max-width: 1280px) {
  .sp-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sp-highlights { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .sp-charts-2,
  .sp-charts-3 { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
  .sp-page { padding: 1rem; }
  .sp-highlights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sp-header h1 { font-size: 1.35rem; }
}
</style>
