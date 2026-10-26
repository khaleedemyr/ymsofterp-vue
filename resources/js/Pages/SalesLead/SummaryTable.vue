<template>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <div
      class="px-4 py-2.5 font-bold tracking-wide text-sm"
      :class="variant === 'grand' ? 'bg-amber-500 text-white' : 'bg-amber-400 text-slate-900'"
    >
      {{ title }}
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-xs md:text-sm">
        <thead>
          <tr class="bg-slate-800 text-white">
            <th class="px-3 py-2 text-left font-semibold">SEGMENTATION</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">TARGET</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">REVENUE &gt;15</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">REVENUE &lt;15</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">TOTAL</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">% SEGMENT</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">LAST MONTH</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">LM VS MTD</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">GROWTH</th>
            <th class="px-3 py-2 text-right font-semibold whitespace-nowrap">TARGET VS MTD</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in localRows" :key="row.segment_1" class="border-b border-gray-100 hover:bg-slate-50">
            <td class="px-3 py-2 font-medium text-gray-800 uppercase">{{ row.segment_1 }}</td>
            <td class="px-3 py-2 text-right">
              <input
                v-if="editableTargets"
                v-model.number="row.target"
                type="number"
                min="0"
                step="1"
                class="w-28 text-right rounded border-gray-200 text-xs py-1"
              />
              <span v-else>{{ formatMoney(row.target) }}</span>
            </td>
            <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.rev_gt_15) }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.rev_lt_15) }}</td>
            <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatMoney(row.total) }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-emerald-700 font-medium">{{ formatPct(row.segment_pct) }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.last_month) }}</td>
            <td class="px-3 py-2 text-right tabular-nums" :class="numClass(row.lm_vs_mtd)">{{ formatMoney(row.lm_vs_mtd) }}</td>
            <td class="px-3 py-2 text-right tabular-nums" :class="numClass(row.growth)">{{ formatPct(row.growth) }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ row.target_vs_mtd == null ? '-' : formatPct(row.target_vs_mtd) }}</td>
          </tr>

          <tr class="bg-slate-800 text-white font-semibold">
            <td class="px-3 py-2">TOTAL</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(computedTargetTotal) }}</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.rev_gt_15) }}</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.rev_lt_15) }}</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.total) }}</td>
            <td class="px-3 py-2 text-right">100%</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.last_month) }}</td>
            <td class="px-3 py-2 text-right">{{ formatMoney(total.lm_vs_mtd) }}</td>
            <td class="px-3 py-2 text-right">{{ formatPct(total.growth) }}</td>
            <td class="px-3 py-2 text-right">{{ total.target_vs_mtd == null && !editableTargets ? '-' : formatPct(liveTargetVsMtd) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="px-4 py-3 border-t border-gray-100 bg-slate-50 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
      <div>
        <div class="text-xs text-gray-500 uppercase font-semibold">Target</div>
        <div class="font-bold text-gray-900">{{ formatMoney(computedTargetTotal) }}</div>
        <div class="text-emerald-700 font-semibold">{{ liveAchievement == null ? '-' : formatPct(liveAchievement) }}</div>
      </div>
      <div>
        <div class="text-xs text-gray-500 uppercase font-semibold">Variance</div>
        <div class="font-bold" :class="numClass(liveVariance)">{{ formatMoney(liveVariance) }}</div>
        <div :class="numClass(liveVariancePct)">{{ liveVariancePct == null ? '-' : formatPct(liveVariancePct) }}</div>
      </div>
      <div v-if="contributionPct != null">
        <div class="text-xs text-gray-500 uppercase font-semibold">Contribution to Justus Group Sales {{ periodLabel }}</div>
        <div class="font-bold text-amber-600 text-lg">{{ formatPct(contributionPct) }}</div>
      </div>
      <div v-if="editableTargets" class="md:col-span-3 flex justify-end">
        <button
          type="button"
          class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700"
          @click="emitSave"
        >
          <i class="fa-solid fa-save mr-1"></i> Simpan Target {{ title }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  rows: { type: Array, default: () => [] },
  total: { type: Object, default: () => ({}) },
  achievementPct: { type: Number, default: null },
  variance: { type: Number, default: 0 },
  variancePct: { type: Number, default: null },
  contributionPct: { type: Number, default: null },
  periodLabel: { type: String, default: '' },
  variant: { type: String, default: 'sales' },
  editableTargets: { type: Boolean, default: false },
  salesUserId: { type: [Number, String], default: null },
  year: { type: Number, default: null },
  month: { type: Number, default: null },
});

const emit = defineEmits(['save-targets']);

const localRows = ref([]);
watch(
  () => props.rows,
  (rows) => {
    localRows.value = (rows || []).map((r) => ({ ...r }));
  },
  { immediate: true, deep: true }
);

const computedTargetTotal = computed(() =>
  localRows.value.reduce((sum, r) => sum + (Number(r.target) || 0), 0)
);

const liveAchievement = computed(() => {
  const t = computedTargetTotal.value;
  if (!t) return null;
  return Math.round(((Number(props.total.total) || 0) / t) * 10000) / 100;
});

const liveVariance = computed(() => (Number(props.total.total) || 0) - computedTargetTotal.value);

const liveVariancePct = computed(() => {
  const t = computedTargetTotal.value;
  if (!t) return null;
  return Math.round((liveVariance.value / t) * 10000) / 100;
});

const liveTargetVsMtd = computed(() => liveAchievement.value);

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

const emitSave = () => {
  emit('save-targets', {
    sales_user_id: props.salesUserId,
    targets: localRows.value.map((r) => ({
      segment_1: r.segment_1,
      target_amount: Number(r.target) || 0,
    })),
  });
};
</script>
