<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  evaluation: Object,
});

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
});

const groupedStrategies = computed(() => props.evaluation.strategies || []);

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
  form.put(route('kpi-evaluations.update', props.evaluation.id));
}

async function refreshErp() {
  const result = await Swal.fire({
    title: 'Refresh data ERP?',
    text: 'Nilai ERP akan diambil ulang dari sistem.',
    icon: 'question',
    showCancelButton: true,
  });
  if (!result.isConfirmed) return;
  router.post(route('kpi-evaluations.refresh-erp', props.evaluation.id));
}

async function submitEval() {
  const result = await Swal.fire({
    title: 'Submit evaluasi?',
    text: 'Evaluasi tidak bisa diubah setelah disubmit.',
    icon: 'warning',
    showCancelButton: true,
  });
  if (!result.isConfirmed) return;

  form.put(route('kpi-evaluations.update', props.evaluation.id), {
    preserveScroll: true,
    onSuccess: () => {
      router.post(route('kpi-evaluations.submit', props.evaluation.id));
    },
  });
}

function back() {
  router.visit(route('kpi-evaluations.index'));
}
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

      <div class="bg-white rounded-2xl shadow p-4 mb-4 text-sm flex flex-wrap gap-4">
        <div><span class="text-gray-500">Template:</span> <strong>{{ evaluation.template?.name }}</strong></div>
        <div><span class="text-gray-500">Periode:</span> {{ evaluation.period_start }} s/d {{ evaluation.period_end }}</div>
      </div>

      <!-- Data Parameters -->
      <div class="bg-white rounded-2xl shadow mb-6 overflow-hidden">
        <div class="bg-indigo-700 text-white px-4 py-3 flex justify-between items-center">
          <span class="font-semibold">Data Parameter (D*)</span>
          <button type="button" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded-lg" @click="refreshErp">
            <i class="fa-solid fa-rotate mr-1"></i> Refresh ERP
          </button>
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
          Simpan Draft
        </button>
        <button type="button" class="px-4 py-2 rounded-xl bg-rose-600 text-white font-semibold" @click="submitEval">
          Submit Evaluasi
        </button>
      </div>
    </div>
  </AppLayout>
</template>
