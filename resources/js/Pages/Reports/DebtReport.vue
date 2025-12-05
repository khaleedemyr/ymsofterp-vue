<template>
  <AppLayout title="Report Hutang">
    <div class="py-8 px-4">
      <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa fa-file-invoice-dollar text-red-500"></i>
            Report Hutang Purchase Order
          </h1>
          <p class="text-gray-600 mt-2">Laporan hutang untuk PO yang belum dibayar atau masih ada sisa pembayaran termin</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
              <select v-model="filters.supplier_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Semua Supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
              <input type="date" v-model="filters.date_from" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
              <input type="date" v-model="filters.date_to" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="filters.status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="all">Semua</option>
                <option value="unpaid">Belum Dibayar</option>
                <option value="partial">Pembayaran Termin (Belum Lunas)</option>
              </select>
            </div>
            <div class="md:col-span-4 flex gap-2">
              <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fa fa-filter mr-2"></i>Filter
              </button>
              <button type="button" @click="resetFilters" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fa fa-redo mr-2"></i>Reset
              </button>
              <button type="button" @click="exportReport" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition">
                <i class="fa fa-file-excel mr-2"></i>Export Excel
              </button>
            </div>
          </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600">Total PO</p>
                <p class="text-2xl font-bold text-gray-800">{{ summary.total_po }}</p>
              </div>
              <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fa fa-file-invoice text-blue-600 text-xl"></i>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600">Total Hutang</p>
                <p class="text-2xl font-bold text-red-600">{{ formatCurrency(summary.total_debt) }}</p>
              </div>
              <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                <i class="fa fa-money-bill-wave text-red-600 text-xl"></i>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600">Sudah Dibayar</p>
                <p class="text-2xl font-bold text-green-600">{{ formatCurrency(summary.total_paid) }}</p>
              </div>
              <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fa fa-check-circle text-green-600 text-xl"></i>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600">Sisa Hutang</p>
                <p class="text-2xl font-bold text-orange-600">{{ formatCurrency(summary.total_remaining) }}</p>
              </div>
              <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                <i class="fa fa-exclamation-triangle text-orange-600 text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabs: By PO or By Supplier -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <div class="flex border-b border-gray-200 mb-4">
            <button
              @click="activeTab = 'byPO'"
              :class="activeTab === 'byPO' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-800'"
              class="px-4 py-2 font-medium transition"
            >
              <i class="fa fa-file-invoice mr-2"></i>Per Purchase Order
            </button>
            <button
              @click="activeTab = 'bySupplier'"
              :class="activeTab === 'bySupplier' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-800'"
              class="px-4 py-2 font-medium transition"
            >
              <i class="fa fa-building mr-2"></i>Per Supplier
            </button>
          </div>

          <!-- Tab Content: By PO -->
          <div v-if="activeTab === 'byPO'" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode Pembayaran</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sudah Dibayar</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="debt in debtData" :key="debt.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ debt.po_number }}</div>
                    <div v-if="debt.source_pr_number" class="text-xs text-gray-500">PR: {{ debt.source_pr_number }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(debt.po_date) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ debt.supplier_name }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          :class="debt.payment_type === 'lunas' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                      <i :class="debt.payment_type === 'lunas' ? 'fa fa-check-circle mr-1' : 'fa fa-calendar-alt mr-1'"></i>
                      {{ debt.payment_type === 'lunas' ? 'Lunas' : 'Termin' }}
                    </span>
                    <div v-if="debt.payment_type === 'termin' && debt.payment_terms" class="text-xs text-gray-500 mt-1">
                      {{ debt.payment_terms }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">{{ formatCurrency(debt.grand_total) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">{{ formatCurrency(debt.total_paid) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-red-600">{{ formatCurrency(debt.remaining) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          :class="debt.status === 'unpaid' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'">
                      {{ debt.status === 'unpaid' ? 'Belum Dibayar' : 'Pembayaran Termin' }}
                    </span>
                    <div v-if="debt.payment_count > 0" class="text-xs text-gray-500 mt-1">
                      {{ debt.payment_count }} pembayaran
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-center">
                    <Link :href="`/po-ops/${debt.id}`" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                      <i class="fa fa-eye mr-1"></i>Detail
                    </Link>
                  </td>
                </tr>
                <tr v-if="debtData.length === 0">
                  <td colspan="9" class="px-6 py-4 text-center text-gray-500">Tidak ada data hutang</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab Content: By Supplier -->
          <div v-if="activeTab === 'bySupplier'">
            <div v-for="supplier in bySupplier" :key="supplier.supplier_id" class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
              <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                  <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ supplier.supplier_name }}</h3>
                    <p class="text-sm text-gray-600">{{ supplier.po_count }} Purchase Order</p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm text-gray-600">Total Hutang</p>
                    <p class="text-xl font-bold text-red-600">{{ formatCurrency(supplier.total_remaining) }}</p>
                  </div>
                </div>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sudah Dibayar</th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="po in supplier.pos" :key="po.id" class="hover:bg-gray-50">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ po.po_number }}</div>
                        <div v-if="po.source_pr_number" class="text-xs text-gray-500">PR: {{ po.source_pr_number }}</div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(po.po_date) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">{{ formatCurrency(po.grand_total) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">{{ formatCurrency(po.total_paid) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-red-600">{{ formatCurrency(po.remaining) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                              :class="po.status === 'unpaid' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'">
                          {{ po.status === 'unpaid' ? 'Belum Dibayar' : 'Pembayaran Termin' }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-center">
                        <Link :href="`/po-ops/${po.id}`" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                          <i class="fa fa-eye mr-1"></i>Detail
                        </Link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-if="bySupplier.length === 0" class="text-center py-8 text-gray-500">
              Tidak ada data hutang
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  debtData: Array,
  bySupplier: Array,
  summary: Object,
  suppliers: Array,
  filters: Object
});

const activeTab = ref('byPO');
const filters = reactive({
  supplier_id: props.filters?.supplier_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  status: props.filters?.status || 'all'
});

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function applyFilters() {
  router.get('/debt-report', filters, {
    preserveState: true,
    preserveScroll: true
  });
}

function resetFilters() {
  filters.supplier_id = '';
  filters.date_from = '';
  filters.date_to = '';
  filters.status = 'all';
  applyFilters();
}

function exportReport() {
  // TODO: Implement export
  alert('Export feature coming soon');
}
</script>

