<template>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-4 py-2.5 font-bold tracking-wide text-sm bg-amber-400 text-slate-900">
      {{ title }}
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-xs md:text-sm">
        <thead>
          <tr class="bg-slate-800 text-white">
            <th class="px-3 py-2 text-center font-semibold w-10">NO</th>
            <th class="px-3 py-2 text-left font-semibold">OUTLET</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">BUDGET</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">REVENUE &gt;15</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">REVENUE &lt;16</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">TOTAL</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">PERFORMANCE %</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">LAST MONTH</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">VARIANCE</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">GROWTH</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in localRows" :key="row.outlet_id" class="border-b border-gray-100 hover:bg-slate-50">
            <td class="px-3 py-2 text-center text-gray-600">{{ idx + 1 }}</td>
            <td class="px-3 py-2 font-medium text-gray-800">{{ row.outlet_name }}</td>
            <td class="px-3 py-2 text-right">
              <input
                v-if="editableBudgets"
                v-model.number="row.budget"
                type="number"
                min="0"
                step="1"
                class="w-32 text-right rounded border-gray-200 text-xs py-1"
              />
              <span v-else class="tabular-nums">{{ formatMoney(row.budget) }}</span>
            </td>
            <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.rev_gt_15) }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.rev_lt_16) }}</td>
            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatMoney(row.total) }}</td>
            <td class="px-3 py-2 text-right tabular-nums" :class="perfClass(row)">
              {{ livePerf(row) == null ? '-' : formatPct(livePerf(row)) }}
            </td>
            <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.last_month) }}</td>
            <td class="px-3 py-2 text-right tabular-nums" :class="numClass(row.variance)">{{ formatMoney(row.variance) }}</td>
            <td class="px-3 py-2 text-right tabular-nums" :class="numClass(row.growth)">{{ formatPct(row.growth) }}</td>
          </tr>

          <tr class="bg-amber-300 text-slate-900 font-semibold">
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2 uppercase">TOTAL</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(budgetTotal) }}</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.rev_gt_15) }}</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.rev_lt_16) }}</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.total) }}</td>
            <td class="px-3 py-2 text-right">{{ liveTotalPerf == null ? '-' : formatPct(liveTotalPerf) }}</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.last_month) }}</td>
            <td class="px-3 py-2 text-right" :class="numClass(total.variance)">{{ formatMoney(total.variance) }}</td>
            <td class="px-3 py-2 text-right" :class="numClass(total.growth)">{{ formatPct(total.growth) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="editableBudgets" class="px-4 py-3 border-t border-gray-100 bg-slate-50 flex justify-end">
      <button
        type="button"
        class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700"
        @click="emitSave"
      >
        <i class="fa-solid fa-save mr-1"></i> Simpan Budget {{ title }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  rows: { type: Array, default: () => [] },
  total: { type: Object, default: () => ({}) },
  editableBudgets: { type: Boolean, default: false },
});

const emit = defineEmits(['save-budgets']);

const localRows = ref([]);
watch(
  () => props.rows,
  (rows) => {
    localRows.value = (rows || []).map((r) => ({ ...r }));
  },
  { immediate: true, deep: true }
);

const budgetTotal = computed(() =>
  localRows.value.reduce((sum, r) => sum + (Number(r.budget) || 0), 0)
);

const liveTotalPerf = computed(() => {
  const b = budgetTotal.value;
  if (!b) return null;
  return Math.round(((Number(props.total.total) || 0) / b) * 10000) / 100;
});

const livePerf = (row) => {
  const b = Number(row.budget) || 0;
  if (!b) return null;
  return Math.round(((Number(row.total) || 0) / b) * 10000) / 100;
};

const formatMoney = (v) => {
  const n = Number(v) || 0;
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(n);
};

const formatPct = (v) => {
  if (v === null || v === undefined || Number.isNaN(Number(v))) return '-';
  return `${Number(v).toFixed(2)}%`;
};

const numClass = (v) => {
  const n = Number(v) || 0;
  if (n < 0) return 'text-red-600';
  if (n > 0) return 'text-emerald-700';
  return 'text-gray-700';
};

const perfClass = (row) => {
  const p = livePerf(row);
  if (p == null) return 'text-gray-500';
  if (p >= 100) return 'text-emerald-700 font-semibold';
  if (p >= 70) return 'text-amber-600 font-medium';
  return 'text-red-600';
};

const emitSave = () => {
  emit('save-budgets', {
    budgets: localRows.value.map((r) => ({
      outlet_id: r.outlet_id,
      budget_amount: Number(r.budget) || 0,
    })),
  });
};
</script>
