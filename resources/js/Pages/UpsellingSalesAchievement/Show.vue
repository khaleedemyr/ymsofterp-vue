<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-blue-600"></i>
            Detail Upselling Sales Achievement
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            {{ record.outlet?.nama_outlet }} · {{ monthLabel }} {{ record.year }}
          </p>
        </div>
        <div class="flex gap-2">
          <Link :href="route('upselling-sales-achievement.index')" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
          </Link>
          <Link
            :href="route('upselling-sales-achievement.edit', record.id)"
            class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600"
          >
            <i class="fa-solid fa-pen mr-1"></i> Edit
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
          <div class="text-gray-500">Outlet</div>
          <div class="font-semibold">{{ record.outlet?.nama_outlet || '-' }}</div>
        </div>
        <div>
          <div class="text-gray-500">Periode</div>
          <div class="font-semibold">{{ monthLabel }} {{ record.year }}</div>
        </div>
        <div>
          <div class="text-gray-500">Created At</div>
          <div class="font-semibold">{{ formatDateTime(record.created_at) }}</div>
        </div>
        <div>
          <div class="text-gray-500">Created By</div>
          <div class="font-semibold">{{ record.creator?.nama_lengkap || record.creator?.name || '-' }}</div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm border-collapse">
          <thead>
            <tr class="bg-gray-900 text-white">
              <th rowspan="2" class="px-3 py-3 border border-gray-700 text-center w-12">NO</th>
              <th rowspan="2" class="px-3 py-3 border border-gray-700 text-left min-w-[200px]">FB PRODUCT PROJECTION</th>
              <th colspan="3" class="px-3 py-2 border border-gray-700 text-center bg-gray-800">SALES TARGET</th>
              <th colspan="3" class="px-3 py-2 border border-gray-700 text-center bg-gray-800">ACTUAL TARGET</th>
              <th rowspan="2" class="px-3 py-3 border border-gray-700 text-center min-w-[100px]">ACHIEVEMENT %</th>
            </tr>
            <tr class="bg-gray-800 text-white text-xs">
              <th class="px-3 py-2 border border-gray-700 text-right">AVERAGE CHECK</th>
              <th class="px-3 py-2 border border-gray-700 text-right">COVER</th>
              <th class="px-3 py-2 border border-gray-700 text-right">FB REVENUE</th>
              <th class="px-3 py-2 border border-gray-700 text-right">AVERAGE CHECK</th>
              <th class="px-3 py-2 border border-gray-700 text-right">COVER</th>
              <th class="px-3 py-2 border border-gray-700 text-right">FB REVENUE</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in detail.rows" :key="row.no" class="hover:bg-gray-50">
              <td class="px-3 py-2 border border-gray-200 text-center">{{ row.no }}</td>
              <td class="px-3 py-2 border border-gray-200">
                <div class="font-medium text-gray-900">{{ row.item_name }}</div>
                <div v-if="row.category_label" class="text-xs text-gray-500">{{ row.category_label }}</div>
              </td>
              <td class="px-3 py-2 border border-gray-200 text-right">{{ formatCurrency(row.target.average_check) }}</td>
              <td class="px-3 py-2 border border-gray-200 text-right">{{ formatNumber(row.target.cover) }}</td>
              <td class="px-3 py-2 border border-gray-200 text-right font-medium">{{ formatCurrency(row.target.fb_revenue) }}</td>
              <td class="px-3 py-2 border border-gray-200 text-right">{{ formatCurrency(row.actual.average_check) }}</td>
              <td class="px-3 py-2 border border-gray-200 text-right">{{ formatNumber(row.actual.cover) }}</td>
              <td class="px-3 py-2 border border-gray-200 text-right font-medium">{{ formatCurrency(row.actual.fb_revenue) }}</td>
              <td class="px-3 py-2 border border-gray-200 text-center">
                <span :class="achievementClass(row.achievement_percent)" class="px-2 py-1 rounded font-semibold text-xs">
                  {{ formatPercent(row.achievement_percent) }}
                </span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-100 font-bold">
              <td colspan="2" class="px-3 py-3 border border-gray-300 text-right">TOTAL</td>
              <td class="px-3 py-2 border border-gray-300"></td>
              <td class="px-3 py-2 border border-gray-300 text-right">{{ formatNumber(detail.totals.target_cover) }}</td>
              <td class="px-3 py-2 border border-gray-300 text-right">{{ formatCurrency(detail.totals.target_fb_revenue) }}</td>
              <td class="px-3 py-2 border border-gray-300"></td>
              <td class="px-3 py-2 border border-gray-300 text-right">{{ formatNumber(detail.totals.actual_cover) }}</td>
              <td class="px-3 py-2 border border-gray-300 text-right">{{ formatCurrency(detail.totals.actual_fb_revenue) }}</td>
              <td class="px-3 py-2 border border-gray-300 text-center">
                <span :class="achievementClass(detail.totals.achievement_percent)" class="px-2 py-1 rounded text-xs">
                  {{ formatPercent(detail.totals.achievement_percent) }}
                </span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <p class="text-xs text-gray-500 mt-3">
        Actual target dihitung otomatis dari data <code>order_items</code> pada periode terpilih (referensi Item Engineering).
        Achievement % = Actual FB Revenue ÷ Target FB Revenue × 100.
      </p>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
  record: { type: Object, required: true },
  detail: { type: Object, required: true },
  monthLabel: { type: String, default: '' },
});

function formatCurrency(val) {
  const n = Number(val) || 0;
  return n.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
}

function formatNumber(val) {
  const n = Number(val) || 0;
  return n.toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function formatPercent(val) {
  const n = Number(val) || 0;
  return `${n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
}

function formatDateTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function achievementClass(pct) {
  const n = Number(pct) || 0;
  if (n >= 100) return 'bg-green-100 text-green-800';
  if (n >= 75) return 'bg-yellow-100 text-yellow-800';
  return 'bg-red-100 text-red-800';
}
</script>
