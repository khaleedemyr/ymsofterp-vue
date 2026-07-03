<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { formatKpiNumber } from '@/utils/formatKpiNumber';

const props = defineProps({
  evaluationId: { type: Number, required: true },
  outletCount: { type: Number, default: 0 },
  cacheVersion: { type: String, default: '' },
});

const open = ref(false);
const loading = ref(false);
const error = ref('');
const data = ref(null);
const bulkCache = ref({});
const bulkLoading = ref(false);
let bulkLoadPromise = null;

function formatNum(val) {
  return formatKpiNumber(val);
}

function levelBadge(level) {
  const map = {
    exceeding: 'bg-green-100 text-green-800',
    meeting: 'bg-blue-100 text-blue-800',
    below: 'bg-red-100 text-red-800',
  };
  return map[level] || 'bg-gray-100 text-gray-700';
}

function levelLabel(level) {
  return { exceeding: 'Achieve', meeting: 'Meeting', below: 'Below' }[level] || level;
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
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm">
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
              Nilai per outlet dihitung dari data ERP per outlet (bukan agregat scope).
              Achievement agregat evaluasi: <strong>{{ formatNum(data.aggregate_achievement) }}%</strong>
            </p>

            <div class="overflow-x-auto border rounded-xl">
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
                      <div class="text-[10px] font-normal text-gray-400 truncate max-w-[8rem]">{{ col.name }}</div>
                    </th>
                    <th class="px-3 py-2 text-right">Achievement</th>
                    <th class="px-3 py-2 text-center">Level</th>
                    <th class="px-3 py-2 text-right">Skor</th>
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
                      {{ formatNum(row.parameter_values?.[col.code]) }}
                    </td>
                    <td class="px-3 py-2 text-right font-semibold">
                      {{ formatNum(row.achievement_percent) }}<template v-if="row.achievement_percent != null">%</template>
                    </td>
                    <td class="px-3 py-2 text-center">
                      <span class="px-2 py-0.5 rounded-full text-xs" :class="levelBadge(row.performance_level)">
                        {{ levelLabel(row.performance_level) }}
                      </span>
                    </td>
                    <td class="px-3 py-2 text-right">{{ formatNum(row.score) }}</td>
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
