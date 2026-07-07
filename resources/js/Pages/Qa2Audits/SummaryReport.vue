<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  filters: Object,
  outlets: Array,
  rows: Array,
});

const outletId = ref(props.filters?.outlet_id || '');
const fromMonth = ref(props.filters?.from_month || '');
const toMonth = ref(props.filters?.to_month || '');

const debouncedFilter = debounce(() => {
  router.get(route('qa2-audits.report-summary'), {
    outlet_id: outletId.value,
    from_month: fromMonth.value,
    to_month: toMonth.value,
  }, {
    preserveState: true,
    replace: true,
  });
}, 250);

watch([outletId, fromMonth, toMonth], debouncedFilter);

function formatScore(value) {
  return `${Number(value || 0).toFixed(0)}%`;
}

function shiftMonth(base, delta) {
  const [year, month] = String(base || '').split('-').map(Number);
  if (!year || !month) return '';
  const date = new Date(year, month - 1 + delta, 1);
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  return `${y}-${m}`;
}

function applyQuickRange(kind) {
  const end = toMonth.value || fromMonth.value || new Date().toISOString().slice(0, 7);
  if (kind === '3m') {
    toMonth.value = end;
    fromMonth.value = shiftMonth(end, -2);
    return;
  }
  toMonth.value = end;
  fromMonth.value = end;
}

function resetFilter() {
  outletId.value = '';
  const now = new Date().toISOString().slice(0, 7);
  fromMonth.value = now;
  toMonth.value = now;
}

function backToIndex() {
  router.visit(route('qa2-audits.index'));
}

const exportUrl = computed(() => route('qa2-audits.report-summary.export', {
  outlet_id: outletId.value || undefined,
  from_month: fromMonth.value || undefined,
  to_month: toMonth.value || undefined,
}));
</script>

<template>
  <AppLayout title="QA2 Report Summary">
    <div class="space-y-6 p-4 sm:p-6">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900">QA2 Report Summary</h1>
            <p class="text-sm text-gray-500">Rata-rata audit result per outlet dan template.</p>
          </div>
          <button
            type="button"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="backToIndex"
          >
            Back to QA2 Audits
          </button>
          <a
            :href="exportUrl"
            class="rounded-lg border border-emerald-300 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50"
          >
            Export Excel
          </a>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="grid gap-3 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Outlet</label>
            <select v-model="outletId" class="w-full rounded-lg border-gray-300 text-sm">
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="String(outlet.id_outlet)">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">From Month</label>
            <input v-model="fromMonth" type="month" class="w-full rounded-lg border-gray-300 text-sm">
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">To Month</label>
            <input v-model="toMonth" type="month" class="w-full rounded-lg border-gray-300 text-sm">
          </div>
          <div class="flex items-end gap-2">
            <button type="button" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50" @click="applyQuickRange('1m')">
              Per 1 Bulan
            </button>
            <button type="button" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50" @click="applyQuickRange('3m')">
              Per 3 Bulan
            </button>
            <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="resetFilter">
              Reset
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Outlet</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Jumlah Audit</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Rata-rata Audit Result</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="row in (rows || [])" :key="row.outlet_id">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ row.outlet_name }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ row.audit_count }}</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-indigo-700">{{ formatScore(row.avg_audit_result) }}</td>
              </tr>
              <tr v-if="!(rows || []).length">
                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada data pada periode ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
