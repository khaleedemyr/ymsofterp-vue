<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { useLoading } from '@/Composables/useLoading';

const props = defineProps({
  evaluation: Object,
  outlets: Array,
  erpScopeOptions: Array,
});

const page = usePage();
const evaluation = ref({ ...props.evaluation });

watch(
  () => page.props.evaluation,
  (val) => { if (val) evaluation.value = val; },
  { deep: true },
);

watch(
  () => props.evaluation,
  (val) => { if (val) evaluation.value = val; },
  { deep: true },
);

function applyEvaluation(data) {
  evaluation.value = data;
}

const {
  startProgressSimulation,
  finishProgress,
  failProgress,
} = useLoading();

const erpDiagnostics = ref(null);
const diagnosticsLoading = ref(false);
let diagnosticsDelayTimer = null;
let diagnosticsDebounceTimer = null;

function scheduleLoadDiagnostics() {
  if (diagnosticsDebounceTimer) {
    clearTimeout(diagnosticsDebounceTimer);
  }
  diagnosticsDebounceTimer = setTimeout(() => {
    loadDiagnostics();
  }, 350);
}

const selectedSingleOutlet = ref(
  props.outlets.find((o) => o.id === (props.evaluation.erp_scope_outlet_ids?.[0] ?? null)) ?? null,
);
const selectedMultipleOutlets = ref(
  props.outlets.filter((o) => (props.evaluation.erp_scope_outlet_ids ?? []).includes(o.id)),
);

const form = useForm({
  parameter_values: props.evaluation.parameter_values.map((pv) => ({
    id: pv.id,
    manual_value: pv.manual_value,
    is_overridden: pv.is_overridden,
    override_reason: pv.override_reason,
  })),
  items: props.evaluation.items.map((item) => ({
    id: item.id,
    improvement_plan: item.improvement_plan,
  })),
  employee_comments: props.evaluation.employee_comments || '',
  assessor_comments: props.evaluation.assessor_comments || '',
  erp_data_scope: props.evaluation.erp_data_scope || 'employee_outlet',
  erp_scope_outlet_ids: props.evaluation.erp_scope_outlet_ids || [],
});

const showSinglePicker = computed(() => form.erp_data_scope === 'single_outlet');
const showMultiplePicker = computed(() => form.erp_data_scope === 'multiple_outlets');

const scopeUnsaved = computed(() => {
  const savedScope = evaluation.value.erp_data_scope || 'employee_outlet';
  const savedIds = JSON.stringify(evaluation.value.erp_scope_outlet_ids ?? []);
  const formIds = JSON.stringify(form.erp_scope_outlet_ids ?? []);
  return form.erp_data_scope !== savedScope || formIds !== savedIds;
});

const scopeDisplayNames = computed(() => {
  if (!erpDiagnostics.value) return [];
  return erpDiagnostics.value.scope_outlet_names_preview
    ?? erpDiagnostics.value.scope_outlet_names
    ?? [];
});

watch(() => form.erp_data_scope, (scope) => {
  if (scope === 'employee_outlet' || scope === 'all_outlets') {
    form.erp_scope_outlet_ids = [];
    selectedSingleOutlet.value = null;
    selectedMultipleOutlets.value = [];
  }
  scheduleLoadDiagnostics();
});

watch(selectedSingleOutlet, (outlet) => {
  form.erp_scope_outlet_ids = outlet ? [outlet.id] : [];
  scheduleLoadDiagnostics();
});

watch(selectedMultipleOutlets, (list) => {
  form.erp_scope_outlet_ids = list.map((o) => o.id);
  scheduleLoadDiagnostics();
});

const groupedStrategies = computed(() => evaluation.value.strategies || []);

function pvRow(id) {
  return form.parameter_values.find((r) => r.id === id);
}

function itemRow(id) {
  return form.items.find((r) => r.id === id);
}

function sourceLabel(type) {
  return { erp: 'ERP', manual: 'Manual', hybrid: 'Hybrid' }[type] || type;
}

function levelBadge(level) {
  const map = {
    exceeding: 'bg-green-100 text-green-800',
    meeting: 'bg-blue-100 text-blue-800',
    below: 'bg-red-100 text-red-800',
  };
  return map[level] || 'bg-gray-100';
}

function formatNum(val) {
  if (val === null || val === undefined || val === '') return '—';
  return Number(val).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function onManualChange(pv, original) {
  const row = pvRow(pv.id);
  if (!row) return;
  if (original.source_type === 'hybrid' && row.manual_value !== '' && row.manual_value != original.erp_value) {
    row.is_overridden = true;
  }
}

function save() {
  startProgressSimulation('Menyimpan draft evaluasi...', { estimatedMs: 15000 });
  form.put(route('kpi-evaluations.update', evaluation.value.id), {
    onFinish: () => finishProgress('Draft berhasil disimpan.'),
    onError: () => failProgress('Gagal menyimpan draft.'),
  });
}

async function recalculateScores() {
  startProgressSimulation('Menghitung ulang skor KPI...', { estimatedMs: 8000 });
  try {
    const { data } = await axios.post(
      route('kpi-evaluations.recalculate', evaluation.value.id),
      {},
      { headers: { Accept: 'application/json' } },
    );
    applyEvaluation(data.evaluation);
    await finishProgress('Skor KPI diperbarui.');
  } catch {
    failProgress('Gagal menghitung ulang skor.');
  }
}

async function refreshErp() {
  const result = await Swal.fire({
    title: 'Refresh data ERP?',
    text: 'Nilai ERP akan diambil ulang dan skor KPI dihitung ulang.',
    icon: 'question',
    showCancelButton: true,
  });
  if (!result.isConfirmed) return;

  startProgressSimulation('Refresh data ERP...', {
    estimatedMs: 90000,
    steps: [
      { at: 5, message: 'Menyimpan scope outlet...' },
      { at: 18, message: 'Memuat data outlet analyzer...' },
      { at: 40, message: 'Mengambil revenue, budget, dan ticket...' },
      { at: 62, message: 'Menghitung parameter ERP...' },
      { at: 82, message: 'Menghitung skor KPI...' },
    ],
  });

  try {
    const { data } = await axios.post(
      route('kpi-evaluations.refresh-erp', evaluation.value.id),
      {
        erp_data_scope: form.erp_data_scope,
        erp_scope_outlet_ids: form.erp_scope_outlet_ids,
      },
      { headers: { Accept: 'application/json' } },
    );
    applyEvaluation(data.evaluation);
    await finishProgress('Data ERP & skor KPI diperbarui.');
    loadDiagnostics();
  } catch {
    failProgress('Gagal refresh data ERP.');
  }
}

async function submitEval() {
  const result = await Swal.fire({
    title: 'Submit evaluasi?',
    text: 'Evaluasi tidak bisa diubah setelah disubmit.',
    icon: 'warning',
    showCancelButton: true,
  });
  if (!result.isConfirmed) return;

  startProgressSimulation('Menyimpan & submit evaluasi...', { estimatedMs: 20000 });

  form.put(route('kpi-evaluations.update', evaluation.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      router.post(route('kpi-evaluations.submit', evaluation.value.id), {}, {
        onFinish: () => finishProgress('Evaluasi berhasil disubmit.'),
        onError: () => failProgress('Gagal submit evaluasi.'),
      });
    },
    onError: () => failProgress('Gagal menyimpan sebelum submit.'),
  });
}

function back() {
  router.visit(route('kpi-evaluations.index'));
}

async function loadDiagnostics() {
  if (diagnosticsDelayTimer) {
    clearTimeout(diagnosticsDelayTimer);
    diagnosticsDelayTimer = null;
  }

  diagnosticsLoading.value = true;
  let progressStarted = false;

  diagnosticsDelayTimer = setTimeout(() => {
    progressStarted = true;
    startProgressSimulation('Memuat probe ERP...', {
      estimatedMs: 12000,
      steps: [
        { at: 20, message: 'Memeriksa scope outlet...' },
        { at: 55, message: 'Menghitung revenue & order POS...' },
      ],
    });
  }, 400);

  try {
    const { data } = await axios.get(route('kpi-evaluations.erp-diagnostics', evaluation.value.id), {
      params: {
        erp_data_scope: form.erp_data_scope,
        erp_scope_outlet_ids: form.erp_scope_outlet_ids,
      },
    });
    erpDiagnostics.value = data;
    if (progressStarted) {
      await finishProgress('', 200);
    }
  } catch {
    erpDiagnostics.value = null;
    if (progressStarted) {
      failProgress('Gagal memuat probe ERP.');
    }
  } finally {
    if (diagnosticsDelayTimer) {
      clearTimeout(diagnosticsDelayTimer);
      diagnosticsDelayTimer = null;
    }
    diagnosticsLoading.value = false;
  }
}

onMounted(() => {
  loadDiagnostics();
});
</script>

<template>
  <AppLayout title="Edit KPI Evaluation">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
          <button class="text-gray-500" @click="back"><i class="fa-solid fa-arrow-left"></i></button>
          <div>
            <h1 class="text-2xl font-bold">{{ evaluation.employee_name }}</h1>
            <p class="text-sm text-gray-500 font-mono">{{ evaluation.evaluation_code }} · {{ evaluation.period_month }} · {{ evaluation.eval_status }}</p>
            <p class="text-sm text-gray-600 mt-1">{{ evaluation.jabatan_name }} · {{ evaluation.outlet_name }}</p>
          </div>
        </div>
        <div class="text-right">
          <div class="text-3xl font-bold text-rose-600">{{ evaluation.total_score ?? '—' }}</div>
          <div class="text-xs text-gray-500">Total Skor</div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow p-4 mb-4 text-sm space-y-3">
        <div class="flex flex-wrap gap-4">
          <div><span class="text-gray-500">Template:</span> <strong>{{ evaluation.template?.name }}</strong></div>
          <div><span class="text-gray-500">Periode:</span> {{ evaluation.period_start }} s/d {{ evaluation.period_end }}</div>
          <div v-if="diagnosticsLoading" class="text-gray-400 text-xs">Memuat probe ERP...</div>
          <div v-else-if="erpDiagnostics?.revenue_mtd != null"><span class="text-gray-500">Revenue MTD (probe):</span> {{ Number(erpDiagnostics.revenue_mtd).toLocaleString('id-ID') }}</div>
          <div v-else-if="erpDiagnostics"><span class="text-gray-500">Revenue MTD (probe):</span> —</div>
          <div v-if="!diagnosticsLoading && erpDiagnostics?.order_count != null"><span class="text-gray-500">Order POS:</span> {{ erpDiagnostics.order_count }}</div>
        </div>

        <div class="border-t pt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Scope Data ERP</label>
            <select v-model="form.erp_data_scope" class="w-full rounded-lg border-gray-300 text-sm">
              <option v-for="opt in erpScopeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div v-if="showSinglePicker" class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
            <Multiselect v-model="selectedSingleOutlet" :options="outlets" label="label" track-by="id" :searchable="true" :show-labels="false" />
          </div>
          <div v-if="showMultiplePicker" class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-500 mb-1">Outlet (multi)</label>
            <Multiselect v-model="selectedMultipleOutlets" :options="outlets" label="label" track-by="id" :multiple="true" :searchable="true" :close-on-select="false" :show-labels="false" />
          </div>
        </div>
        <div v-if="erpDiagnostics?.scope_label || scopeDisplayNames.length" class="text-xs text-gray-600 space-y-1">
          <div>
            Scope aktif:
            <strong>{{ erpDiagnostics?.scope_label || '—' }}</strong>
            <span v-if="erpDiagnostics?.scope_outlet_count != null"> ({{ erpDiagnostics.scope_outlet_count }} outlet)</span>
            <span v-if="scopeUnsaved" class="text-amber-600 font-medium"> — belum disimpan</span>
          </div>
          <div v-if="scopeDisplayNames.length && form.erp_data_scope !== 'all_outlets'">
            {{ scopeDisplayNames.join(', ') }}
          </div>
          <div v-else-if="form.erp_data_scope === 'all_outlets' && erpDiagnostics?.scope_outlet_count">
            {{ scopeDisplayNames.slice(0, 8).join(', ') }}
            <span v-if="erpDiagnostics.scope_outlet_count > 8">, … +{{ erpDiagnostics.scope_outlet_count - 8 }} outlet lainnya</span>
          </div>
        </div>
        <p v-if="scopeUnsaved" class="text-xs text-amber-700">
          Scope berubah — klik <strong>Simpan Draft</strong> atau <strong>Refresh ERP</strong> untuk menerapkan.
        </p>
      </div>

      <div v-if="erpDiagnostics?.issues?.length" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Masalah sumber ERP</p>
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(issue, i) in erpDiagnostics.issues" :key="'issue-' + i">{{ issue }}</li>
        </ul>
      </div>

      <div v-if="erpDiagnostics?.hints?.length" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold mb-1"><i class="fa-solid fa-lightbulb mr-1"></i> Kemungkinan penyebab nilai 0</p>
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(hint, i) in erpDiagnostics.hints" :key="'hint-' + i">{{ hint }}</li>
        </ul>
        <p class="mt-2 text-xs text-amber-800">Tip: bandingkan dengan menu <strong>Outlet Analyzer</strong> untuk outlet &amp; periode yang sama.</p>
      </div>

      <!-- Data Parameters -->
      <div class="bg-white rounded-2xl shadow mb-6 overflow-hidden">
        <div class="bg-indigo-700 text-white px-4 py-3 flex justify-between items-center gap-2 flex-wrap">
          <span class="font-semibold">Data Parameter (D*)</span>
          <div class="flex gap-2">
            <button type="button" class="text-sm bg-white/10 hover:bg-white/20 px-3 py-1 rounded-lg" @click="recalculateScores">
              <i class="fa-solid fa-calculator mr-1"></i> Hitung Ulang Skor
            </button>
            <button type="button" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded-lg disabled:opacity-50" :disabled="form.processing" @click="refreshErp">
              <i class="fa-solid fa-rotate mr-1"></i> Refresh ERP
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left">Kode</th>
                <th class="px-4 py-2 text-left">Nama</th>
                <th class="px-4 py-2 text-left">Sumber</th>
                <th class="px-4 py-2 text-right">ERP</th>
                <th class="px-4 py-2 text-right">Manual / Override</th>
                <th class="px-4 py-2 text-right">Final</th>
                <th class="px-4 py-2 text-left">Alasan Override</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="pv in evaluation.parameter_values" :key="pv.id">
                <td class="px-4 py-2 font-mono text-indigo-700">{{ pv.parameter_code }}</td>
                <td class="px-4 py-2">{{ pv.parameter_name }}</td>
                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs bg-gray-100">{{ sourceLabel(pv.source_type) }}</span></td>
                <td class="px-4 py-2 text-right text-gray-600">{{ formatNum(pv.erp_value) }}</td>
                <td class="px-4 py-2 text-right">
                  <input
                    v-if="pv.source_type !== 'erp'"
                    v-model="pvRow(pv.id).manual_value"
                    type="number"
                    step="any"
                    class="w-32 text-right rounded border-gray-300 text-sm ml-auto block"
                    @input="onManualChange(pv, pv)"
                  />
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-4 py-2 text-right font-semibold">{{ formatNum(pv.final_value) }}</td>
                <td class="px-4 py-2">
                  <input
                    v-if="pv.source_type === 'hybrid'"
                    v-model="pvRow(pv.id).override_reason"
                    type="text"
                    placeholder="Alasan override..."
                    class="w-full rounded border-gray-300 text-sm"
                  />
                </td>
              </tr>
              <tr v-if="!evaluation.parameter_values?.length">
                <td colspan="7" class="px-4 py-6 text-center text-gray-400">Tidak ada data parameter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- KPI Items by Strategy -->
      <div v-for="strategy in groupedStrategies" :key="strategy.name" class="bg-white rounded-2xl shadow mb-4 overflow-hidden">
        <div class="bg-rose-700 text-white px-4 py-3 flex justify-between">
          <span class="font-semibold">{{ strategy.name }}</span>
          <span class="text-rose-100">Bobot Strategy: {{ strategy.weight_percent }}%</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left">KPI</th>
                <th class="px-4 py-2 text-left">Target</th>
                <th class="px-4 py-2 text-left">Formula</th>
                <th class="px-4 py-2 text-right">Achievement</th>
                <th class="px-4 py-2 text-left">Level</th>
                <th class="px-4 py-2 text-right">Skor</th>
                <th class="px-4 py-2 text-right">Bobot</th>
                <th class="px-4 py-2 text-left">Improvement Plan</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="item in strategy.items" :key="item.id">
                <td class="px-4 py-2 font-medium">{{ item.item_name }}</td>
                <td class="px-4 py-2 text-gray-600">{{ item.target_value || '—' }}</td>
                <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ item.formula || '—' }}</td>
                <td class="px-4 py-2 text-right">{{ formatNum(item.achievement_percent) }}%</td>
                <td class="px-4 py-2">
                  <span v-if="item.performance_level" class="px-2 py-0.5 rounded-full text-xs" :class="levelBadge(item.performance_level)">{{ item.performance_level }}</span>
                  <span v-else>—</span>
                </td>
                <td class="px-4 py-2 text-right font-semibold">{{ formatNum(item.score) }}</td>
                <td class="px-4 py-2 text-right">{{ item.weight_percent }}%</td>
                <td class="px-4 py-2">
                  <textarea v-model="itemRow(item.id).improvement_plan" rows="2" class="w-full rounded border-gray-300 text-sm" placeholder="Rencana perbaikan..." />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Comments -->
      <div class="bg-white rounded-2xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Komentar Karyawan</label>
          <textarea v-model="form.employee_comments" rows="3" class="w-full rounded-xl border-gray-300 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Komentar Assessor</label>
          <textarea v-model="form.assessor_comments" rows="3" class="w-full rounded-xl border-gray-300 text-sm" />
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <button type="button" class="px-4 py-2 rounded-xl border" @click="back">Kembali</button>
        <button type="button" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-semibold disabled:opacity-50" :disabled="form.processing" @click="save">
          <i v-if="form.processing" class="fa-solid fa-spinner fa-spin mr-1"></i>
          Simpan Draft
        </button>
        <button type="button" class="px-4 py-2 rounded-xl bg-rose-600 text-white font-semibold disabled:opacity-50" :disabled="form.processing" @click="submitEval">
          Submit Evaluasi
        </button>
      </div>
    </div>
  </AppLayout>
</template>
