<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  filters: Object,
  canChooseOutlet: Boolean,
  outlets: { type: Array, default: () => [] },
  lockedOutlet: { type: Object, default: null },
  selectedOutlet: { type: Object, default: null },
  summary: Object,
  rows: { type: Array, default: () => [] },
  trend: { type: Array, default: () => [] },
  outletRanking: { type: Array, default: () => [] },
  issueInsights: { type: Object, default: () => ({}) },
});

const month = ref(props.filters?.month || new Date().toISOString().slice(0, 7));
const idOutlet = ref(
  props.filters?.id_outlet !== null && props.filters?.id_outlet !== undefined
    ? String(props.filters.id_outlet)
    : ''
);

function applyFilters() {
  const q = { month: month.value };
  if (props.canChooseOutlet && idOutlet.value) {
    q.id_outlet = idOutlet.value;
  }
  router.get(route('guest-comment-forms.gsi-dashboard'), q, { preserveState: true, replace: true });
}

function pct(v) {
  if (v === null || v === undefined || Number.isNaN(Number(v))) return '-';
  return `${Number(v).toFixed(2)}%`;
}

const trendSeries = computed(() => [
  {
    name: 'GSI (%)',
    data: props.trend.map((r) => (r.gsi_pct == null ? null : Number(r.gsi_pct))),
  },
]);

const trendOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
  stroke: { width: 3, curve: 'smooth' },
  markers: { size: 4 },
  dataLabels: { enabled: false },
  grid: { borderColor: '#E5E7EB' },
  xaxis: { categories: props.trend.map((r) => r.label) },
  yaxis: { min: 0, max: 100, labels: { formatter: (val) => `${val.toFixed(0)}%` } },
  tooltip: { y: { formatter: (val) => `${Number(val).toFixed(2)}%` } },
  colors: ['#0EA5E9'],
}));

const subjectBarOptions = computed(() => ({
  chart: { type: 'bar', stacked: false, toolbar: { show: false } },
  plotOptions: { bar: { horizontal: false, columnWidth: '55%' } },
  dataLabels: { enabled: false },
  xaxis: { categories: props.rows.map((r) => r.subject) },
  yaxis: { labels: { formatter: (v) => `${Math.round(v)}` } },
  legend: { position: 'top' },
  colors: ['#60A5FA', '#F59E0B', '#A78BFA', '#EF4444'],
}));

const subjectBarSeries = computed(() => [
  { name: 'Excellent', data: props.rows.map((r) => r.excellent || 0) },
  { name: 'Good', data: props.rows.map((r) => r.good || 0) },
  { name: 'Average', data: props.rows.map((r) => r.average || 0) },
  { name: 'Poor', data: props.rows.map((r) => r.poor || 0) },
]);

const gsiToneClass = computed(() => {
  const v = Number(props.summary?.overall_mtd_pct ?? 0);
  if (v >= 85) return 'text-emerald-700 bg-emerald-50 border-emerald-200';
  if (v >= 75) return 'text-amber-700 bg-amber-50 border-amber-200';
  return 'text-red-700 bg-red-50 border-red-200';
});

const showIssueDetailModal = ref(false);
const selectedIssueTopic = ref(null);

const issueTopicDetailMap = computed(() => {
  const entries = props.issueInsights?.topic_details || [];
  return entries.reduce((acc, item) => {
    acc[item.topic] = item;
    return acc;
  }, {});
});

const negativeTopicSections = computed(() => {
  const topics = props.issueInsights?.topic_details || [];
  return topics
    .map((topic) => ({
      topic: topic.topic,
      label: topic.label,
      comments: (topic.comments || []).filter((comment) => isNegativeSeverity(comment.severity)),
    }))
    .filter((topic) => topic.comments.length > 0);
});

const totalNegativeComments = computed(() => {
  return negativeTopicSections.value.reduce((sum, topic) => sum + topic.comments.length, 0);
});

function formatDateTime(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function normalizeSeverity(value) {
  return String(value || 'neutral').trim().toLowerCase();
}

function isNegativeSeverity(value) {
  return ['mild_negative', 'negative', 'severe'].includes(normalizeSeverity(value));
}

function severityBadgeClass(value) {
  const severity = normalizeSeverity(value);
  if (severity === 'severe') {
    return 'bg-red-100 text-red-700';
  }
  if (severity === 'negative') {
    return 'bg-amber-100 text-amber-700';
  }
  if (severity === 'mild_negative') {
    return 'bg-orange-100 text-orange-700';
  }
  if (severity === 'positive') {
    return 'bg-emerald-100 text-emerald-700';
  }
  return 'bg-slate-100 text-slate-600';
}

function commentCardClass(value) {
  const severity = normalizeSeverity(value);
  if (severity === 'severe') {
    return 'bg-red-50 border-red-200 ring-1 ring-red-100';
  }
  if (severity === 'negative') {
    return 'bg-amber-50 border-amber-200 ring-1 ring-amber-100';
  }
  if (severity === 'mild_negative') {
    return 'bg-orange-50 border-orange-200 ring-1 ring-orange-100';
  }
  return 'bg-slate-50 border-slate-200';
}

function openIssueTopicDetail(topicKey) {
  if (!topicKey) return;
  const topicMeta = (props.issueInsights?.top_topics || []).find((item) => item.topic === topicKey);
  const detail = issueTopicDetailMap.value[topicKey];
  selectedIssueTopic.value = {
    topic: topicKey,
    label: detail?.label || topicMeta?.label || topicKey,
    count: detail?.count || topicMeta?.count || 0,
    comments: detail?.comments || [],
  };
  showIssueDetailModal.value = true;
}

function closeIssueTopicDetail() {
  showIssueDetailModal.value = false;
  selectedIssueTopic.value = null;
}

const issueTopicBarOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    events: {
      dataPointSelection: (_event, _chartContext, config) => {
        const topic = props.issueInsights?.top_topics?.[config?.dataPointIndex]?.topic;
        openIssueTopicDetail(topic);
      },
    },
  },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  dataLabels: { enabled: false },
  xaxis: { categories: (props.issueInsights?.top_topics || []).map((t) => t.label) },
  tooltip: {
    y: { formatter: (val) => `${Math.round(val)} komentar` },
  },
  colors: ['#8B5CF6'],
}));

const issueTopicBarSeries = computed(() => [
  { name: 'Mentions', data: (props.issueInsights?.top_topics || []).map((t) => t.count || 0) },
]);
</script>

<template>
  <AppLayout>
    <div class="w-full max-w-[1400px] mx-auto px-4 py-6 space-y-5">
      <div class="flex flex-wrap justify-between items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-column text-emerald-500"></i>
          GSI Dashboard (Guest Satisfaction Index)
        </h1>
        <div class="flex items-center gap-2">
          <Link :href="route('guest-comment-forms.index')" class="px-3 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">
            <i class="fa-solid fa-arrow-left mr-1"></i>Kembali ke Guest Comment
          </Link>
          <Link :href="route('guest-comment-forms.create')" class="px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
            + Unggah Formulir
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap items-end gap-3">
        <div>
          <label class="text-xs font-semibold text-slate-500 uppercase">Month</label>
          <input v-model="month" type="month" class="mt-1 block rounded-lg border-slate-300 shadow-sm" @change="applyFilters" />
        </div>
        <div v-if="canChooseOutlet">
          <label class="text-xs font-semibold text-slate-500 uppercase">Outlet</label>
          <select v-model="idOutlet" class="mt-1 block rounded-lg border-slate-300 shadow-sm min-w-[250px]" @change="applyFilters">
            <option value="">Semua Outlet</option>
            <option v-for="o in outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
          </select>
        </div>
        <div v-else class="text-sm px-3 py-2 rounded-lg bg-blue-50 border border-blue-200 text-blue-900">
          Outlet terkunci:
          <span class="font-semibold">{{ lockedOutlet?.nama_outlet || selectedOutlet?.nama_outlet || '-' }}</span>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
          <div class="text-xs uppercase text-slate-500 font-semibold">GSI MTD</div>
          <div class="text-3xl font-extrabold mt-1">{{ pct(summary?.overall_mtd_pct) }}</div>
          <div class="text-xs text-slate-500 mt-2">Target minimum: {{ summary?.min_target_pct }}%</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
          <div class="text-xs uppercase text-slate-500 font-semibold">GSI Last Month</div>
          <div class="text-3xl font-extrabold mt-1">{{ pct(summary?.overall_last_month_pct) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
          <div class="text-xs uppercase text-slate-500 font-semibold">Delta</div>
          <div class="text-3xl font-extrabold mt-1">{{ pct(summary?.overall_delta_pct) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-4" :class="gsiToneClass">
          <div class="text-xs uppercase font-semibold">Performance</div>
          <div class="text-lg font-bold mt-1">
            <span v-if="Number(summary?.overall_mtd_pct || 0) >= Number(summary?.min_target_pct || 85)">On Target</span>
            <span v-else>Below Target</span>
          </div>
          <div class="text-xs mt-1">Total forms: {{ summary?.total_forms || 0 }}</div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4 overflow-x-auto">
        <h2 class="font-bold text-slate-800 mb-3">Guest Satisfaction Survey Summary</h2>
        <table class="w-full min-w-[1020px] text-sm">
          <thead class="bg-slate-100 text-slate-700">
            <tr>
              <th class="px-3 py-2 text-left">No</th>
              <th class="px-3 py-2 text-left">Subject</th>
              <th class="px-3 py-2 text-right">Excellent</th>
              <th class="px-3 py-2 text-right">Good</th>
              <th class="px-3 py-2 text-right">Average</th>
              <th class="px-3 py-2 text-right">Poor</th>
              <th class="px-3 py-2 text-right">Abstain</th>
              <th class="px-3 py-2 text-right">Total Responses</th>
              <th class="px-3 py-2 text-right">MTD (%)</th>
              <th class="px-3 py-2 text-right">Last Month (%)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in rows" :key="r.subject" class="border-b border-slate-100">
              <td class="px-3 py-2">{{ i + 1 }}</td>
              <td class="px-3 py-2 font-medium">{{ r.subject }}</td>
              <td class="px-3 py-2 text-right">{{ r.excellent }}</td>
              <td class="px-3 py-2 text-right">{{ r.good }}</td>
              <td class="px-3 py-2 text-right">{{ r.average }}</td>
              <td class="px-3 py-2 text-right">{{ r.poor }}</td>
              <td class="px-3 py-2 text-right">{{ r.abstain }}</td>
              <td class="px-3 py-2 text-right font-semibold">{{ r.total_responses }}</td>
              <td class="px-3 py-2 text-right font-semibold">{{ pct(r.mtd_pct) }}</td>
              <td class="px-3 py-2 text-right">{{ pct(r.last_month_pct) }}</td>
            </tr>
            <tr class="bg-slate-900 text-white font-bold">
              <td colspan="8" class="px-3 py-2 text-right">GSI Total</td>
              <td class="px-3 py-2 text-right">{{ pct(summary?.overall_mtd_pct) }}</td>
              <td class="px-3 py-2 text-right">{{ pct(summary?.overall_last_month_pct) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
          <h3 class="font-semibold text-slate-800 mb-2">Trend GSI 6 Bulan</h3>
          <apexchart type="line" height="320" :options="trendOptions" :series="trendSeries" />
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
          <h3 class="font-semibold text-slate-800 mb-2">Distribusi Rating per Subject (Bulan Ini)</h3>
          <apexchart type="bar" height="320" :options="subjectBarOptions" :series="subjectBarSeries" />
        </div>
      </div>

      <div v-if="canChooseOutlet && !idOutlet" class="bg-white rounded-xl border border-slate-200 p-4">
        <h3 class="font-semibold text-slate-800 mb-2">Top Outlet GSI (Bulan Ini)</h3>
        <div v-if="!outletRanking.length" class="text-sm text-slate-500">Belum ada data outlet untuk periode ini.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[620px] text-sm">
            <thead class="bg-slate-100 text-slate-700">
              <tr>
                <th class="px-3 py-2 text-left">Rank</th>
                <th class="px-3 py-2 text-left">Outlet</th>
                <th class="px-3 py-2 text-right">Responses</th>
                <th class="px-3 py-2 text-right">GSI (%)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, idx) in outletRanking" :key="r.outlet_id" class="border-b border-slate-100">
                <td class="px-3 py-2">{{ idx + 1 }}</td>
                <td class="px-3 py-2 font-medium">{{ r.outlet_name }}</td>
                <td class="px-3 py-2 text-right">{{ r.responses }}</td>
                <td class="px-3 py-2 text-right font-semibold">{{ pct(r.gsi_pct) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
          <h3 class="font-semibold text-slate-800">AI Issue Insights (Komentar)</h3>
          <div class="flex flex-wrap items-center justify-end gap-2">
            <span class="text-xs text-slate-500">
              Dataset: {{ issueInsights?.total_comments || 0 }} komentar terverifikasi
            </span>
            <div class="flex flex-wrap items-center gap-2 text-[11px]">
              <span class="font-semibold text-slate-500 uppercase tracking-wide">Legend</span>
              <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-red-700">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                Severe
              </span>
              <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-amber-700">
                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                Negative
              </span>
              <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-1 text-orange-700">
                <span class="h-2 w-2 rounded-full bg-orange-500"></span>
                Mild Negative
              </span>
              <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-slate-600">
                <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                Neutral
              </span>
              <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-emerald-700">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Positive
              </span>
            </div>
          </div>
        </div>

        <div v-if="issueInsights?.status === 'error'" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ issueInsights?.message || 'AI issue analysis gagal diproses.' }}
        </div>
        <div v-else-if="issueInsights?.status === 'empty'" class="text-sm text-slate-500">
          {{ issueInsights?.message || 'Belum ada komentar untuk dianalisis.' }}
        </div>
        <div v-else class="space-y-4">
          <div class="border border-slate-200 rounded-lg p-3">
            <div class="flex items-center justify-between gap-2 mb-2">
              <h4 class="text-sm font-semibold text-slate-700">Top Issues</h4>
              <span class="text-xs text-slate-400">Klik bar untuk lihat komentar</span>
            </div>
            <apexchart type="bar" height="380" :options="issueTopicBarOptions" :series="issueTopicBarSeries" />
          </div>

          <div class="border border-slate-200 rounded-lg p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
              <div>
                <h4 class="text-sm font-semibold text-slate-700">Komentar Negatif Terdeteksi</h4>
                <div class="text-xs text-slate-500 mt-1">
                  Menampilkan {{ totalNegativeComments }} komentar dengan severity negatif dari issue yang terdeteksi.
                </div>
              </div>
            </div>

            <div v-if="!negativeTopicSections.length" class="text-sm text-slate-500">
              Belum ada komentar negatif pada periode ini.
            </div>

            <div v-else class="space-y-5">
              <div v-for="topic in negativeTopicSections" :key="topic.topic" class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                  <div class="font-semibold text-slate-700">{{ topic.label }}</div>
                  <button
                    type="button"
                    class="text-sm font-semibold text-violet-700 hover:text-violet-900"
                    @click="openIssueTopicDetail(topic.topic)"
                  >
                    Lihat semua komentar {{ topic.label }}
                  </button>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                  <div
                    v-for="(comment, idx) in topic.comments"
                    :key="`${topic.topic}-${idx}`"
                    class="border rounded-lg p-3"
                    :class="commentCardClass(comment.severity)"
                  >
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                      <div class="text-xs text-slate-500">{{ comment.author || '-' }}</div>
                      <div class="flex items-center gap-2">
                        <span
                          class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase"
                          :class="severityBadgeClass(comment.severity)"
                        >
                          {{ comment.severity || 'neutral' }}
                        </span>
                        <span class="text-[11px] text-slate-400">{{ formatDateTime(comment.created_at) }}</span>
                      </div>
                    </div>
                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-red-600">
                      Negative comment highlighted
                    </div>
                    <div class="text-sm text-slate-700 whitespace-pre-wrap">"{{ comment.text || '-' }}"</div>
                    <div v-if="comment.summary_id" class="text-xs text-violet-700 mt-2">AI: {{ comment.summary_id }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <Teleport to="body">
        <div v-if="showIssueDetailModal && selectedIssueTopic" class="fixed inset-0 z-[200] flex items-center justify-center px-4 py-6">
          <div class="absolute inset-0 bg-slate-900/55" @click="closeIssueTopicDetail"></div>
          <div class="relative w-full max-w-4xl max-h-[85vh] overflow-hidden rounded-2xl bg-white shadow-2xl border border-slate-200">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4 bg-slate-50">
              <div>
                <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Issue Detail</div>
                <h3 class="text-xl font-bold text-slate-800 mt-1">{{ selectedIssueTopic.label }}</h3>
                <div class="text-sm text-slate-500 mt-1">{{ selectedIssueTopic.count }} komentar terdeteksi pada periode ini</div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                  <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-red-700">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    Severe
                  </span>
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-amber-700">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    Negative
                  </span>
                  <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-1 text-orange-700">
                    <span class="h-2 w-2 rounded-full bg-orange-500"></span>
                    Mild Negative
                  </span>
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-slate-600">
                    <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                    Neutral
                  </span>
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-emerald-700">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Positive
                  </span>
                </div>
              </div>
              <button class="h-10 w-10 rounded-full border border-slate-200 bg-white text-slate-500 hover:text-slate-700 hover:bg-slate-100" @click="closeIssueTopicDetail">
                <i class="fa-solid fa-times"></i>
              </button>
            </div>

            <div class="max-h-[calc(85vh-96px)] overflow-y-auto px-6 py-5">
              <div v-if="!(selectedIssueTopic.comments || []).length" class="text-sm text-slate-500">
                Belum ada komentar detail untuk issue ini.
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="(comment, idx) in selectedIssueTopic.comments"
                  :key="`${selectedIssueTopic.topic}-${idx}`"
                  class="rounded-xl border p-4"
                  :class="commentCardClass(comment.severity)"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="text-sm font-semibold text-slate-800">{{ comment.author || '-' }}</div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                      <span
                        class="rounded-full px-2.5 py-1 font-semibold uppercase"
                        :class="severityBadgeClass(comment.severity)"
                      >
                        {{ comment.severity || 'neutral' }}
                      </span>
                      <span class="text-slate-400">{{ formatDateTime(comment.created_at) }}</span>
                    </div>
                  </div>
                  <div v-if="isNegativeSeverity(comment.severity)" class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-red-600">
                    Negative comment highlighted
                  </div>
                  <div class="text-sm leading-6 text-slate-700 whitespace-pre-wrap">{{ comment.text || '-' }}</div>
                  <div v-if="comment.summary_id" class="mt-2 text-xs text-violet-700">AI: {{ comment.summary_id }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AppLayout>
</template>
