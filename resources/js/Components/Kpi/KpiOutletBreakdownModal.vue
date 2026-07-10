<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { formatKpiNumber } from '@/utils/formatKpiNumber';
import { formatKpiAchievement } from '@/utils/formatKpiAchievement';

const props = defineProps({
  evaluationId: { type: Number, required: true },
  outletCount: { type: Number, default: 0 },
  cacheVersion: { type: String, default: '' },
});

const open = ref(false);
const loading = ref(false);
const error = ref('');
const data = ref(null);
const activeItem = ref(null);
const bulkCache = ref({});
const bulkLoading = ref(false);
let bulkLoadPromise = null;

function formatNum(val) {
  return formatKpiNumber(val);
}

function formatAchievement(value, item = null) {
  return formatKpiAchievement(value, item || activeItem.value);
}

function levelBadge(level) {
  const map = {
    exceeding: 'bg-green-100 text-green-800',
    meeting: 'bg-blue-100 text-blue-800',
    below: 'bg-red-100 text-red-800',
    visited: 'bg-green-100 text-green-800',
    not_visited: 'bg-gray-100 text-gray-600',
  };
  return map[level] || 'bg-gray-100 text-gray-700';
}

function levelLabel(level) {
  return {
    exceeding: 'Achieve',
    meeting: 'Meeting',
    below: 'Below',
    visited: 'Dikunjungi',
    not_visited: 'Belum',
  }[level] || level;
}

function paramCellValue(row, col) {
  if (col.scope_type === 'employee') {
    return '—';
  }
  return formatNum(row.parameter_values?.[col.code]);
}

function resetBulkCache() {
  bulkCache.value = {};
  bulkLoadPromise = null;
}

watch(() => props.cacheVersion, () => {
  resetBulkCache();
});

async function ensureBulkLoaded() {
  if (props.outletCount < 2) {
    return;
  }

  if (Object.keys(bulkCache.value).length > 0) {
    return;
  }

  if (bulkLoadPromise) {
    await bulkLoadPromise;
    return;
  }

  bulkLoading.value = true;
  bulkLoadPromise = axios.get(
    route('kpi-evaluations.outlet-breakdowns', props.evaluationId),
    { headers: { Accept: 'application/json' }, timeout: 300000 },
  ).then(({ data: res }) => {
    bulkCache.value = res?.items ?? {};
  }).catch(() => {
    bulkCache.value = {};
  }).finally(() => {
    bulkLoading.value = false;
  });

  await bulkLoadPromise;
}

function preload() {
  if (props.outletCount >= 2) {
    ensureBulkLoaded();
  }
}

async function show(item) {
  if (!item?.formula || props.outletCount < 2) {
    return;
  }

  activeItem.value = item;
  open.value = true;
  error.value = '';

  const cached = bulkCache.value[item.id];
  if (cached) {
    data.value = cached;
    loading.value = false;
    if (!cached.available) {
      error.value = cached.message || 'Breakdown tidak tersedia.';
    }
    return;
  }

  loading.value = true;
  data.value = null;

  try {
    await ensureBulkLoaded();

    const fromBulk = bulkCache.value[item.id];
    if (fromBulk) {
      data.value = fromBulk;
      if (!fromBulk.available) {
        error.value = fromBulk.message || 'Breakdown tidak tersedia.';
      }
      return;
    }

    const { data: res } = await axios.get(
      route('kpi-evaluations.items.outlet-breakdown', {
        kpiEvaluation: props.evaluationId,
        item: item.id,
      }),
      { headers: { Accept: 'application/json' }, timeout: 180000 },
    );
    data.value = res;
    bulkCache.value[item.id] = res;
    if (!res.available) {
      error.value = res.message || 'Breakdown tidak tersedia.';
    }
  } catch {
    error.value = 'Gagal memuat detail per outlet.';
  } finally {
    loading.value = false;
  }
}

function close() {
  open.value = false;
}

defineExpose({ show, preload });
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="close" />
      <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">
        <div class="px-5 py-4 border-b flex items-start justify-between gap-3">
          <div>
            <h3 class="text-lg font-bold text-gray-900">Detail per Outlet</h3>
            <p v-if="data?.item_name" class="text-sm text-gray-600 mt-0.5">{{ data.item_name }}</p>
            <p v-if="data?.formula" class="text-xs font-mono text-gray-400 mt-1">{{ data.formula }}</p>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="close">&times;</button>
        </div>

        <div class="px-5 py-3 overflow-y-auto flex-1">
          <div v-if="loading" class="py-12 text-center text-gray-500">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i>
            Menghitung per outlet...
            <p v-if="bulkLoading" class="text-xs text-gray-400 mt-2">Memuat semua KPI sekaligus (hanya pertama kali)...</p>
          </div>

          <div v-else-if="error" class="py-8 text-center text-amber-700 bg-amber-50 rounded-xl">
            {{ error }}
          </div>

          <template v-else-if="data?.available">
            <div v-if="data.breakdown_mode === 'regional_visit'" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm">
              <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-gray-500 text-xs">Outlet Target</div>
                <div class="font-bold text-lg">{{ data.summary.configured_outlet_count ?? 0 }}</div>
              </div>
              <div class="bg-green-50 rounded-lg p-3">
                <div class="text-green-700 text-xs">Target Dikunjungi</div>
                <div class="font-bold text-lg text-green-800">{{ data.summary.visited_configured ?? 0 }}</div>
              </div>
              <div class="bg-blue-50 rounded-lg p-3">
                <div class="text-blue-700 text-xs">Total Kunjungan (Target)</div>
                <div class="font-bold text-lg text-blue-800">{{ formatNum(data.summary.total_visits) }}</div>
              </div>
              <div class="bg-amber-50 rounded-lg p-3">
                <div class="text-amber-800 text-xs">Target Total / Bulan</div>
                <div class="font-bold text-lg text-amber-900">{{ formatNum(data.summary.portfolio_target) }}</div>
              </div>
            </div>

            <div v-else-if="data.breakdown_mode === 'ticket_follow_up'" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm">
              <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-gray-500 text-xs">Total Ticket</div>
                <div class="font-bold text-lg">{{ formatNum(data.summary.total_tickets) }}</div>
              </div>
              <div class="bg-green-50 rounded-lg p-3">
                <div class="text-green-700 text-xs">Closed</div>
                <div class="font-bold text-lg text-green-800">{{ formatNum(data.summary.closed_tickets) }}</div>
              </div>
              <div class="bg-rose-50 rounded-lg p-3">
                <div class="text-rose-700 text-xs">Overdue</div>
                <div class="font-bold text-lg text-rose-800">{{ formatNum(data.summary.overdue_tickets) }}</div>
              </div>
              <div class="bg-blue-50 rounded-lg p-3">
                <div class="text-blue-700 text-xs">Compliant (D023)</div>
                <div class="font-bold text-lg text-blue-800">{{ formatNum(data.summary.compliant_tickets) }}</div>
              </div>
            </div>

            <div v-else-if="data.breakdown_mode === 'portfolio'" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm">
              <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-gray-500 text-xs">Total Outlet</div>
                <div class="font-bold text-lg">{{ data.outlet_count }}</div>
              </div>
              <div class="bg-green-50 rounded-lg p-3">
                <div class="text-green-700 text-xs">Outlet Dikunjungi</div>
                <div class="font-bold text-lg text-green-800">{{ data.summary.visited_outlets ?? 0 }}</div>
              </div>
              <div class="bg-blue-50 rounded-lg p-3">
                <div class="text-blue-700 text-xs">Total Kunjungan</div>
                <div class="font-bold text-lg text-blue-800">{{ formatNum(data.summary.total_visits) }}</div>
              </div>
              <div class="bg-amber-50 rounded-lg p-3">
                <div class="text-amber-800 text-xs">Target Total / Bulan</div>
                <div class="font-bold text-lg text-amber-900">{{ formatNum(data.summary.portfolio_target) }}</div>
              </div>
            </div>

            <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm">
              <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-gray-500 text-xs">Total Outlet</div>
                <div class="font-bold text-lg">{{ data.outlet_count }}</div>
              </div>
              <div class="bg-green-50 rounded-lg p-3">
                <div class="text-green-700 text-xs">Achieve</div>
                <div class="font-bold text-lg text-green-800">{{ data.summary.exceeding }}</div>
              </div>
              <div class="bg-blue-50 rounded-lg p-3">
                <div class="text-blue-700 text-xs">Meeting</div>
                <div class="font-bold text-lg text-blue-800">{{ data.summary.meeting }}</div>
              </div>
              <div class="bg-red-50 rounded-lg p-3">
                <div class="text-red-700 text-xs">Below</div>
                <div class="font-bold text-lg text-red-800">{{ data.summary.below }}</div>
              </div>
            </div>

            <p class="text-xs text-gray-500 mb-3">
              <template v-if="data.breakdown_mode === 'regional_visit'">
                {{ data.portfolio_note }}
                Achievement agregat evaluasi:
                <strong>{{ formatAchievement(data.aggregate_achievement) }}</strong>
                <span v-if="data.summary.total_visits != null && data.summary.portfolio_target">
                  ({{ formatNum(data.summary.total_visits) }} / {{ formatNum(data.summary.portfolio_target) }} kunjungan target)
                </span>
              </template>
              <template v-else-if="data.breakdown_mode === 'ticket_follow_up'">
                {{ data.portfolio_note }}
                Achievement agregat evaluasi:
                <strong>{{ formatAchievement(data.aggregate_achievement) }}</strong>
                <span v-if="data.summary.compliant_tickets != null && data.summary.total_tickets">
                  ({{ formatNum(data.summary.compliant_tickets) }} / {{ formatNum(data.summary.total_tickets) }} compliant)
                </span>
              </template>
              <template v-else-if="data.breakdown_mode === 'portfolio'">
                {{ data.portfolio_note }}
                Achievement agregat evaluasi:
                <strong>{{ formatAchievement(data.aggregate_achievement) }}</strong>
                <span v-if="data.summary.total_visits != null && data.summary.portfolio_target">
                  ({{ formatNum(data.summary.total_visits) }} / {{ formatNum(data.summary.portfolio_target) }} kunjungan)
                </span>
              </template>
              <template v-else>
                Nilai per outlet dihitung dari data ERP per outlet (bukan agregat scope).
                Achievement agregat evaluasi: <strong>{{ formatAchievement(data.aggregate_achievement) }}</strong>
              </template>
            </p>

            <template v-if="data.breakdown_mode === 'regional_visit'">
              <h4 class="text-sm font-semibold text-gray-800 mb-2">Outlet Target (Regional Management)</h4>
              <div class="overflow-x-auto border rounded-xl mb-6">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left">Outlet</th>
                      <th class="px-3 py-2 text-right">Target</th>
                      <th class="px-3 py-2 text-right">Aktual (hari)</th>
                      <th class="px-3 py-2 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y">
                    <tr v-for="row in data.configured_rows" :key="'cfg-' + row.outlet_id" class="hover:bg-gray-50">
                      <td class="px-3 py-2 font-medium">{{ row.outlet_label }}</td>
                      <td class="px-3 py-2 text-right">{{ formatNum(row.target_visits) }}</td>
                      <td class="px-3 py-2 text-right font-semibold">{{ formatNum(row.actual_visits) }}</td>
                      <td class="px-3 py-2 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs" :class="levelBadge(row.performance_level)">
                          {{ levelLabel(row.performance_level) }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="!data.configured_rows?.length">
                      <td colspan="4" class="px-3 py-4 text-center text-gray-500">Tidak ada outlet target.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h4 class="text-sm font-semibold text-gray-800 mb-1">
                Outlet Non Coverage
                <span class="text-xs font-normal text-gray-500">(ada absensi, bukan target regional)</span>
              </h4>
              <p v-if="data.summary.non_coverage_count" class="text-xs text-amber-700 mb-2">
                {{ data.summary.non_coverage_count }} outlet dengan kunjungan di luar setting regional.
              </p>
              <div class="overflow-x-auto border rounded-xl">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left">Outlet</th>
                      <th class="px-3 py-2 text-right">Aktual (hari)</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y">
                    <tr v-for="row in data.non_coverage_rows" :key="'nc-' + row.outlet_id" class="hover:bg-amber-50/50">
                      <td class="px-3 py-2 font-medium">{{ row.outlet_label }}</td>
                      <td class="px-3 py-2 text-right font-semibold">{{ formatNum(row.actual_visits) }}</td>
                    </tr>
                    <tr v-if="!data.non_coverage_rows?.length">
                      <td colspan="2" class="px-3 py-4 text-center text-gray-500">Tidak ada outlet non coverage.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

            <template v-else-if="data.breakdown_mode === 'ticket_follow_up'">
              <div class="overflow-x-auto border rounded-xl">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left">Outlet</th>
                      <th class="px-3 py-2 text-right">Total</th>
                      <th class="px-3 py-2 text-right">Closed</th>
                      <th class="px-3 py-2 text-right">Overdue</th>
                      <th class="px-3 py-2 text-right">Compliant</th>
                      <th class="px-3 py-2 text-right">Achievement</th>
                      <th class="px-3 py-2 text-center">Level</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y">
                    <tr v-for="row in data.rows" :key="row.outlet_id" class="hover:bg-gray-50">
                      <td class="px-3 py-2 font-medium">{{ row.outlet_label }}</td>
                      <td class="px-3 py-2 text-right">{{ formatNum(row.total_tickets) }}</td>
                      <td class="px-3 py-2 text-right text-green-700">{{ formatNum(row.closed_tickets) }}</td>
                      <td class="px-3 py-2 text-right" :class="row.overdue_tickets > 0 ? 'text-rose-700 font-semibold' : ''">
                        {{ formatNum(row.overdue_tickets) }}
                      </td>
                      <td class="px-3 py-2 text-right font-semibold">{{ formatNum(row.compliant_tickets) }}</td>
                      <td class="px-3 py-2 text-right font-semibold">
                        <template v-if="row.achievement_percent != null">{{ formatAchievement(row.achievement_percent) }}</template>
                        <template v-else>—</template>
                      </td>
                      <td class="px-3 py-2 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs" :class="levelBadge(row.performance_level)">
                          {{ levelLabel(row.performance_level) }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="!data.rows?.length">
                      <td colspan="7" class="px-3 py-4 text-center text-gray-500">Tidak ada data ticket.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

            <div v-else class="overflow-x-auto border rounded-xl">
              <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left">Outlet</th>
                    <th
                      v-for="col in data.parameter_columns"
                      :key="col.code"
                      class="px-3 py-2 text-right whitespace-nowrap"
                    >
                      {{ col.code }}
                      <div class="text-[10px] font-normal text-gray-400 truncate max-w-[8rem]">
                        {{ col.name }}
                        <span v-if="col.scope_type === 'employee'"> (total)</span>
                      </div>
                    </th>
                    <th v-if="data.breakdown_mode !== 'portfolio'" class="px-3 py-2 text-right">Achievement</th>
                    <th class="px-3 py-2 text-center">{{ data.breakdown_mode === 'portfolio' ? 'Status' : 'Level' }}</th>
                    <th v-if="data.breakdown_mode !== 'portfolio'" class="px-3 py-2 text-right">Skor</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-for="row in data.rows" :key="row.outlet_id" class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-medium">{{ row.outlet_label }}</td>
                    <td
                      v-for="col in data.parameter_columns"
                      :key="col.code"
                      class="px-3 py-2 text-right text-gray-700"
                    >
                      {{ paramCellValue(row, col) }}
                    </td>
                    <td v-if="data.breakdown_mode !== 'portfolio'" class="px-3 py-2 text-right font-semibold">
                      {{ formatAchievement(row.achievement_percent) }}
                    </td>
                    <td class="px-3 py-2 text-center">
                      <span class="px-2 py-0.5 rounded-full text-xs" :class="levelBadge(row.performance_level)">
                        {{ levelLabel(row.performance_level) }}
                      </span>
                    </td>
                    <td v-if="data.breakdown_mode !== 'portfolio'" class="px-3 py-2 text-right">{{ formatNum(row.score) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>

        <div class="px-5 py-3 border-t flex justify-end">
          <button type="button" class="px-4 py-2 rounded-lg border text-sm" @click="close">Tutup</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
