<template>
  <AppLayout>
    <div class="min-h-screen bg-slate-50 py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg">
              <i class="fa-solid fa-chart-column"></i>
            </span>
            <span>
              Sales Leads Summary
              <span class="block text-sm font-medium text-emerald-600 mt-0.5">{{ periodLabel }}</span>
            </span>
          </h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('sales-leads.index')"
            class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 text-sm font-semibold"
          >
            <i class="fa-solid fa-list mr-2"></i>List Leads
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-4">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
            <select v-model.number="localFilters.month" class="w-full rounded-xl border-gray-200 text-sm">
              <option v-for="m in 12" :key="m" :value="m">{{ monthLabel(m) }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
            <select v-model.number="localFilters.year" class="w-full rounded-xl border-gray-200 text-sm">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Justus Group Sales (opsional, utk % contribution)</label>
            <input
              v-model="localFilters.group_sales_total"
              type="number"
              min="0"
              step="1"
              class="w-full rounded-xl border-gray-200 text-sm"
              placeholder="Total sales grup bulan ini"
            />
          </div>
          <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 text-sm font-semibold">
            <i class="fa-solid fa-filter mr-1"></i> Terapkan
          </button>
        </form>
        <p class="text-xs text-gray-500 mt-2">
          Revenue pakai Real Revenue jika ada, else Revenue plan. Split &gt;15 / &lt;16 pakai Real Pax jika ada, else Pax plan. Last Month = {{ prevPeriodLabel }}.
        </p>
      </div>

      <div class="flex gap-2 mb-6">
        <button
          type="button"
          class="px-4 py-2 rounded-xl text-sm font-semibold transition"
          :class="tab === 'sales' ? 'bg-emerald-600 text-white shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'"
          @click="tab = 'sales'"
        >
          <i class="fa-solid fa-user-tie mr-1"></i> Per Sales
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded-xl text-sm font-semibold transition"
          :class="tab === 'outlet' ? 'bg-emerald-600 text-white shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'"
          @click="tab = 'outlet'"
        >
          <i class="fa-solid fa-store mr-1"></i> Outlet Contribution
        </button>
      </div>

      <!-- Per Sales -->
      <template v-if="tab === 'sales'">
        <div v-for="block in perSales" :key="block.sales_user_id" class="mb-8">
          <SummaryTable
            :title="block.sales_name"
            :rows="block.rows"
            :total="block.total"
            :achievement-pct="block.achievement_pct"
            :variance="block.variance"
            :variance-pct="block.variance_pct"
            :editable-targets="true"
            :sales-user-id="block.sales_user_id"
            :year="filters.year"
            :month="filters.month"
            @save-targets="saveTargets"
          />
        </div>

        <div v-if="!perSales.length" class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center text-gray-400 mb-8">
          Belum ada data sales lead untuk periode ini.
        </div>

        <SummaryTable
          title="SUMMARY TOTAL"
          :rows="grand.rows"
          :total="grand.total"
          :achievement-pct="grand.achievement_pct"
          :variance="grand.variance"
          :variance-pct="grand.variance_pct"
          :contribution-pct="grand.contribution_pct"
          :period-label="periodLabel"
          variant="grand"
        />
      </template>

      <!-- Outlet Contribution -->
      <template v-else>
        <div class="mb-4">
          <h2 class="text-lg font-bold text-gray-900">
            MTD Revenue {{ periodLabel }}
            <span class="block text-sm font-medium text-emerald-600">Outlet Contribution per region</span>
          </h2>
        </div>

        <div v-for="group in outletGroups" :key="group.region" class="mb-8">
          <OutletContributionTable
            :title="group.region"
            :rows="group.rows"
            :total="group.total"
            :editable-budgets="true"
            @save-budgets="saveOutletBudgets"
          />
        </div>

        <div v-if="!outletGroups.length" class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center text-gray-400 mb-8">
          Belum ada data outlet untuk periode ini.
        </div>

        <OutletContributionTable
          v-if="outletGroups.length"
          title="SUMMARY TOTAL ALL OUTLET"
          :rows="outletGrand.rows"
          :total="outletGrand.total"
          :editable-budgets="false"
        />
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import SummaryTable from './SummaryTable.vue';
import OutletContributionTable from './OutletContributionTable.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  periodLabel: { type: String, default: '' },
  prevPeriodLabel: { type: String, default: '' },
  perSales: { type: Array, default: () => [] },
  grand: { type: Object, default: () => ({ rows: [], total: {} }) },
  segments: { type: Array, default: () => [] },
  outletGroups: { type: Array, default: () => [] },
  outletGrand: { type: Object, default: () => ({ rows: [], total: {} }) },
});

const tab = ref('sales');

const localFilters = reactive({
  year: props.filters.year || new Date().getFullYear(),
  month: props.filters.month || new Date().getMonth() + 1,
  group_sales_total: props.filters.group_sales_total || '',
});

const yearOptions = computed(() => {
  const y = new Date().getFullYear();
  return [y - 1, y, y + 1];
});

const monthLabel = (m) =>
  new Date(2000, m - 1, 1).toLocaleString('id-ID', { month: 'long' });

const applyFilters = () => {
  router.get(route('sales-leads.dashboard'), { ...localFilters }, { preserveState: true, replace: true });
};

const saveTargets = async ({ sales_user_id, targets }) => {
  try {
    await axios.post(route('sales-leads.targets'), {
      year: props.filters.year,
      month: props.filters.month,
      sales_user_id,
      targets,
    });
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Target tersimpan', showConfirmButton: false, timer: 1500 });
    router.reload({ only: ['perSales', 'grand'] });
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Gagal simpan target', 'error');
  }
};

const saveOutletBudgets = async ({ budgets }) => {
  try {
    await axios.post(route('sales-leads.outlet-targets'), {
      year: props.filters.year,
      month: props.filters.month,
      budgets,
    });
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Budget outlet tersimpan', showConfirmButton: false, timer: 1500 });
    router.reload({ only: ['outletGroups', 'outletGrand'] });
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Gagal simpan budget', 'error');
  }
};
</script>
