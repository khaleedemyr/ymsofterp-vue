<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatKpiNumber } from '@/utils/formatKpiNumber';
import KpiOutletBreakdownModal from '@/Components/Kpi/KpiOutletBreakdownModal.vue';

const props = defineProps({
  evaluation: Object,
  outlets: Array,
});

const breakdownModal = ref(null);

const groupedStrategies = computed(() => props.evaluation.strategies || []);

const scopeOutletCount = computed(() => {
  if (props.evaluation.scope_outlet_count != null) {
    return props.evaluation.scope_outlet_count;
  }
  if (props.evaluation.erp_data_scope === 'all_outlets') {
    return props.outlets?.length ?? 0;
  }
  return Math.max(1, props.evaluation.erp_scope_outlet_ids?.length ?? 1);
});

function openOutletBreakdown(item) {
  breakdownModal.value?.show(item);
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
  return formatKpiNumber(val);
}

function back() {
  router.visit(route('kpi-evaluations.index'));
}

onMounted(() => {
  if (scopeOutletCount.value >= 2) {
    setTimeout(() => breakdownModal.value?.preload(), 500);
  }
});
</script>

<template>
  <AppLayout title="KPI Evaluation">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
          <button class="text-gray-500" @click="back"><i class="fa-solid fa-arrow-left"></i></button>
          <div>
            <h1 class="text-2xl font-bold">{{ evaluation.employee_name }}</h1>
            <p class="text-sm text-gray-500 font-mono">{{ evaluation.evaluation_code }} · {{ evaluation.period_month }}</p>
            <p class="text-sm text-gray-600 mt-1">{{ evaluation.jabatan_name }} · {{ evaluation.outlet_name }}</p>
          </div>
        </div>
        <div class="text-right">
          <span class="px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">{{ evaluation.eval_status }}</span>
          <div class="text-3xl font-bold text-rose-600 mt-2">{{ evaluation.total_score ?? '—' }}</div>
          <div class="text-xs text-gray-500">Total Skor</div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow p-4 mb-4 text-sm flex flex-wrap gap-4">
        <div><span class="text-gray-500">Template:</span> <strong>{{ evaluation.template?.name }}</strong></div>
        <div><span class="text-gray-500">Evaluasi:</span> {{ evaluation.period_info?.evaluation_label || evaluation.period_month }}</div>
        <div><span class="text-gray-500">Data KPI:</span> {{ evaluation.period_start }} s/d {{ evaluation.period_end }}
          <span v-if="evaluation.period_info?.data_label" class="text-gray-500">({{ evaluation.period_info.data_label }})</span>
        </div>
        <div v-if="evaluation.period_info?.attendance_label">
          <span class="text-gray-500">Attendance:</span> {{ evaluation.period_info.attendance_start }} s/d {{ evaluation.period_info.attendance_end }}
        </div>
        <div v-if="evaluation.submitted_at"><span class="text-gray-500">Disubmit:</span> {{ evaluation.submitted_at }}</div>
      </div>

      <div class="bg-white rounded-2xl shadow mb-6 overflow-hidden">
        <div class="bg-indigo-700 text-white px-4 py-3 font-semibold">Data Parameter</div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left">Kode</th>
                <th class="px-4 py-2 text-left">Nama</th>
                <th class="px-4 py-2 text-left">Sumber</th>
                <th class="px-4 py-2 text-right">ERP</th>
                <th class="px-4 py-2 text-right">Manual</th>
                <th class="px-4 py-2 text-right">Final</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="pv in evaluation.parameter_values" :key="pv.id">
                <td class="px-4 py-2 font-mono text-indigo-700">{{ pv.parameter_code }}</td>
                <td class="px-4 py-2">{{ pv.parameter_name }}</td>
                <td class="px-4 py-2">{{ sourceLabel(pv.source_type) }}</td>
                <td class="px-4 py-2 text-right">{{ formatNum(pv.erp_value) }}</td>
                <td class="px-4 py-2 text-right">{{ formatNum(pv.manual_value) }}</td>
                <td class="px-4 py-2 text-right font-semibold">{{ formatNum(pv.final_value) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-for="strategy in groupedStrategies" :key="strategy.name" class="bg-white rounded-2xl shadow mb-4 overflow-hidden">
        <div class="bg-rose-700 text-white px-4 py-3 flex justify-between">
          <span class="font-semibold">{{ strategy.name }}</span>
          <span class="text-rose-100">{{ strategy.weight_percent }}%</span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left">KPI</th>
                <th class="px-4 py-2 text-left">Target</th>
                <th class="px-4 py-2 text-right">Achievement</th>
                <th class="px-4 py-2 text-left">Level</th>
                <th class="px-4 py-2 text-right">Skor</th>
                <th class="px-4 py-2 text-right">Bobot</th>
                <th class="px-4 py-2 text-center w-24">Detail</th>
                <th class="px-4 py-2 text-left">Improvement Plan</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="item in strategy.items" :key="item.id">
                <td class="px-4 py-2 font-medium">{{ item.item_name }}</td>
                <td class="px-4 py-2">{{ item.target_value || '—' }}</td>
                <td class="px-4 py-2 text-right">{{ formatNum(item.achievement_percent) }}%</td>
                <td class="px-4 py-2">
                  <span v-if="item.performance_level" class="px-2 py-0.5 rounded-full text-xs" :class="levelBadge(item.performance_level)">{{ item.performance_level }}</span>
                </td>
                <td class="px-4 py-2 text-right font-semibold">{{ formatNum(item.score) }}</td>
                <td class="px-4 py-2 text-right">{{ item.weight_percent }}%</td>
                <td class="px-4 py-2 text-center">
                  <button
                    v-if="item.formula && scopeOutletCount >= 2"
                    type="button"
                    class="text-xs px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700"
                    title="Lihat achievement per outlet"
                    @click="openOutletBreakdown(item)"
                  >
                    <i class="fa-solid fa-store mr-1"></i> Outlet
                  </button>
                  <span v-else class="text-xs text-gray-300">—</span>
                </td>
                <td class="px-4 py-2 text-gray-600 whitespace-pre-wrap">{{ item.improvement_plan || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="evaluation.employee_comments || evaluation.assessor_comments" class="bg-white rounded-2xl shadow p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div v-if="evaluation.employee_comments">
          <h3 class="font-semibold mb-1">Komentar Karyawan</h3>
          <p class="text-gray-600 whitespace-pre-wrap">{{ evaluation.employee_comments }}</p>
        </div>
        <div v-if="evaluation.assessor_comments">
          <h3 class="font-semibold mb-1">Komentar Assessor</h3>
          <p class="text-gray-600 whitespace-pre-wrap">{{ evaluation.assessor_comments }}</p>
        </div>
      </div>
    </div>

    <KpiOutletBreakdownModal
      ref="breakdownModal"
      :evaluation-id="evaluation.id"
      :outlet-count="scopeOutletCount"
      :cache-version="evaluation.updated_at || ''"
    />
  </AppLayout>
</template>
