<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';
import Swal from 'sweetalert2';

const props = defineProps({
  tablesReady: { type: Boolean, default: false },
  filters: { type: Object, required: true },
  nicheCatalog: { type: Array, default: () => [] },
  payload: { type: Object, required: true },
  outlets: { type: Array, default: () => [] },
  recentRuns: { type: Array, default: () => [] },
  canScan: { type: Boolean, default: false },
  maxCases: { type: Number, default: 300 },
});

const page = usePage();
const scanning = ref(false);
const activeNiche = ref(props.payload?.active_niche || '');

const form = useForm({
  date_from: props.filters.date_from,
  date_to: props.filters.date_to,
  id_outlet: props.filters.id_outlet || '',
});

watch(
  () => props.payload?.active_niche,
  (v) => {
    activeNiche.value = v || '';
  }
);

const niches = computed(() => props.payload?.niches || []);
const hotIssues = computed(() => props.payload?.hot_issues || []);
const recommendations = computed(() => props.payload?.recommendations || null);
const items = computed(() => props.payload?.items || []);
const run = computed(() => props.payload?.run || null);

const heatClass = {
  hot: 'border-rose-300 bg-gradient-to-br from-rose-50 to-orange-50 ring-1 ring-rose-200',
  warm: 'border-amber-300 bg-gradient-to-br from-amber-50 to-yellow-50 ring-1 ring-amber-200',
  mild: 'border-sky-200 bg-gradient-to-br from-sky-50 to-indigo-50 ring-1 ring-sky-100',
  cold: 'border-slate-200 bg-white',
};

const heatBadge = {
  hot: 'bg-rose-600 text-white',
  warm: 'bg-amber-500 text-white',
  mild: 'bg-sky-600 text-white',
  cold: 'bg-slate-200 text-slate-600',
};

function loadRun(extra = {}) {
  router.get(
    route('just-academy.negative-comment-radar.index'),
    {
      date_from: form.date_from,
      date_to: form.date_to,
      id_outlet: form.id_outlet || undefined,
      run_id: run.value?.id || undefined,
      niche: activeNiche.value || undefined,
      ...extra,
    },
    { preserveState: true, preserveScroll: true }
  );
}

function selectNiche(code) {
  activeNiche.value = activeNiche.value === code ? '' : code;
  loadRun({ niche: activeNiche.value || undefined });
}

function selectHistory(id) {
  loadRun({ run_id: id, niche: undefined });
}

async function runRadar() {
  if (!props.canScan) {
    Swal.fire('Akses ditolak', 'Anda tidak punya permission untuk menjalankan radar.', 'warning');
    return;
  }
  if (!props.tablesReady) {
    Swal.fire(
      'Tabel belum siap',
      'Jalankan SQL create_negative_comment_radar_tables.sql di database terlebih dahulu.',
      'info'
    );
    return;
  }

  const confirm = await Swal.fire({
    title: 'Jalankan Negative Comment Radar?',
    html: `AI akan mengelompokkan hingga <b>${props.maxCases}</b> komentar negatif CVCC<br/>(${form.date_from} s/d ${form.date_to}) ke 6 niche training.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Jalankan AI',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;

  scanning.value = true;
  form.post(route('just-academy.negative-comment-radar.analyze'), {
    preserveScroll: true,
    onFinish: () => {
      scanning.value = false;
    },
    onError: (errors) => {
      const msg = Object.values(errors || {})[0] || 'Gagal menjalankan analisis.';
      Swal.fire('Error', String(msg), 'error');
    },
  });
}

function formatWhen(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const flashSuccess = computed(() => page.props.flash?.success || null);
const flashError = computed(() => page.props.flash?.error || null);

watch(flashSuccess, (msg) => {
  if (msg) Swal.fire({ icon: 'success', title: 'Berhasil', text: msg, timer: 3200, showConfirmButton: false });
});
watch(flashError, (msg) => {
  if (msg) Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
});
</script>

<template>
  <JaLayout
    title="Negative Comment Radar"
    subtitle="Hot issue dari CVCC — dikelompokkan AI untuk menentukan training yang harus dibuat"
    icon="fa-solid fa-satellite-dish"
  >
    <template #actions>
      <button
        type="button"
        :class="jaUi.btnPrimary"
        :disabled="scanning || !canScan"
        @click="runRadar"
      >
        <i class="fa-solid fa-wand-magic-sparkles" />
        {{ scanning ? 'Menganalisis…' : 'Jalankan Radar AI' }}
      </button>
    </template>

    <div v-if="!tablesReady" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      Tabel belum dibuat. Jalankan
      <code class="rounded bg-white px-1">database/sql/create_negative_comment_radar_tables.sql</code>
      lalu insert menu SQL.
    </div>

    <div :class="[jaUi.card, jaUi.cardBody, 'mb-6']">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <label class="text-xs font-semibold text-slate-500">
          Dari tanggal
          <input v-model="form.date_from" type="date" :class="[jaUi.input, 'mt-1']" />
        </label>
        <label class="text-xs font-semibold text-slate-500">
          Sampai tanggal
          <input v-model="form.date_to" type="date" :class="[jaUi.input, 'mt-1']" />
        </label>
        <label class="text-xs font-semibold text-slate-500 md:col-span-2">
          Outlet (opsional)
          <select v-model="form.id_outlet" :class="[jaUi.select, 'mt-1 w-full']">
            <option value="">Semua outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </label>
      </div>
      <p class="mt-3 text-xs text-slate-500">
        Sumber: kasus CVCC severity negatif (minor/major/critical). Maks {{ maxCases }} kasus per scan.
        Niche: Staff Friendliness, Speed/Serving Time, Wrong Transaction, Food Quality, Cleanliness, Grooming.
      </p>
    </div>

    <div v-if="run" class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
      <div :class="[jaUi.card, jaUi.cardBody, 'lg:col-span-2']">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rekomendasi untuk trainer</p>
        <p class="mt-2 text-sm leading-relaxed text-slate-800">
          {{ recommendations?.overview || 'Belum ada rekomendasi — jalankan radar dulu.' }}
        </p>
        <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
          <span class="rounded-full bg-slate-100 px-2.5 py-1">Run #{{ run.id }}</span>
          <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ run.date_from }} → {{ run.date_to }}</span>
          <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ run.classified_count }} / {{ run.total_cases }} kasus</span>
          <span v-if="run.ai_provider" class="rounded-full bg-indigo-50 px-2.5 py-1 text-indigo-700">AI: {{ run.ai_provider }}</span>
          <span class="rounded-full px-2.5 py-1 capitalize" :class="run.status === 'done' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
            {{ run.status }}
          </span>
        </div>
      </div>
      <div :class="[jaUi.card, jaUi.cardBody]">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Riwayat scan</p>
        <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto text-sm">
          <li v-for="r in recentRuns" :key="r.id">
            <button
              type="button"
              class="w-full rounded-lg px-2 py-1.5 text-left transition hover:bg-indigo-50"
              :class="run?.id === r.id ? 'bg-indigo-50 font-semibold text-indigo-700' : 'text-slate-600'"
              @click="selectHistory(r.id)"
            >
              #{{ r.id }} · {{ r.date_from }}→{{ r.date_to }}
              <span class="text-xs text-slate-400"> · {{ r.classified_count }} · {{ r.status }}</span>
            </button>
          </li>
          <li v-if="!recentRuns.length" class="text-slate-400">Belum ada scan.</li>
        </ul>
      </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <button
        v-for="n in niches"
        :key="n.code"
        type="button"
        class="rounded-2xl border p-5 text-left transition hover:-translate-y-0.5 hover:shadow-md"
        :class="[
          heatClass[n.heat] || heatClass.cold,
          activeNiche === n.code ? 'outline outline-2 outline-indigo-500' : '',
        ]"
        @click="selectNiche(n.code)"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-sm font-semibold text-slate-800">{{ n.label }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ n.code }}</p>
          </div>
          <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="heatBadge[n.heat] || heatBadge.cold">
            {{ n.heat }}
          </span>
        </div>
        <p class="mt-4 text-3xl font-bold text-slate-900">{{ n.count }}</p>
        <p class="text-xs text-slate-500">{{ n.pct }}% dari kasus terklasifikasi</p>
        <p v-if="recommendations?.by_niche?.[n.code]" class="mt-3 line-clamp-2 text-xs text-slate-600">
          {{ recommendations.by_niche[n.code] }}
        </p>
      </button>
    </div>

    <div v-if="hotIssues.length" :class="[jaUi.card, jaUi.cardBody, 'mb-6']">
      <h2 class="font-semibold text-slate-800">Hot issue ranking</h2>
      <ol class="mt-3 space-y-2">
        <li
          v-for="(h, i) in hotIssues"
          :key="h.code"
          class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2 text-sm"
        >
          <div class="flex items-center gap-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">{{ i + 1 }}</span>
            <div>
              <p class="font-medium text-slate-800">{{ h.label }}</p>
              <p class="text-xs text-slate-500">Klik kartu niche untuk filter detail</p>
            </div>
          </div>
          <span class="font-semibold text-slate-700">{{ h.count }} kasus</span>
        </li>
      </ol>
    </div>

    <div :class="jaUi.tableWrap">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <div>
          <h2 class="font-semibold text-slate-800">Detail komentar</h2>
          <p class="text-xs text-slate-500">
            {{ activeNiche ? `Filter niche: ${activeNiche}` : 'Semua niche pada run terpilih' }}
          </p>
        </div>
        <button
          v-if="activeNiche"
          type="button"
          :class="jaUi.btnSecondary"
          @click="selectNiche(activeNiche)"
        >
          Clear filter
        </button>
      </div>
      <div class="overflow-x-auto">
        <table :class="jaUi.table">
          <thead :class="jaUi.thead">
            <tr>
              <th :class="jaUi.th">Waktu</th>
              <th :class="jaUi.th">Outlet</th>
              <th :class="jaUi.th">Niche</th>
              <th :class="jaUi.th">Komentar</th>
              <th :class="jaUi.th">Saran training</th>
              <th :class="jaUi.th">Conf.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in items" :key="row.id" :class="jaUi.tr">
              <td :class="jaUi.td">
                <div class="whitespace-nowrap text-xs">{{ formatWhen(row.event_at) }}</div>
                <div class="mt-1 text-[11px] uppercase text-slate-400">{{ row.severity }}</div>
              </td>
              <td :class="jaUi.td">
                <div class="font-medium">{{ row.outlet_name }}</div>
                <div class="text-xs text-slate-400">#{{ row.feedback_case_id }} · {{ row.author_name || '—' }}</div>
              </td>
              <td :class="jaUi.td">
                <div class="font-medium text-indigo-700">{{ row.primary_niche_label }}</div>
                <div class="mt-1 flex flex-wrap gap-1">
                  <span
                    v-for="n in row.niches"
                    :key="n.code"
                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-600"
                  >{{ n.label }}</span>
                </div>
              </td>
              <td :class="[jaUi.td, 'max-w-md']">
                <p class="text-sm text-slate-800">{{ row.summary_text || row.raw_excerpt }}</p>
                <p v-if="row.ai_reason" class="mt-1 text-xs text-slate-500">{{ row.ai_reason }}</p>
              </td>
              <td :class="[jaUi.td, 'max-w-xs text-xs text-slate-600']">{{ row.training_hint }}</td>
              <td :class="jaUi.td">{{ row.confidence != null ? `${Math.round(row.confidence)}%` : '—' }}</td>
            </tr>
            <tr v-if="!items.length">
              <td :class="jaUi.empty" colspan="6">
                Belum ada data. Pilih rentang tanggal lalu klik <b>Jalankan Radar AI</b>.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </JaLayout>
</template>
