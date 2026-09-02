<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex flex-col gap-3 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-column text-blue-500"></i>
          Report Retail Food
        </h1>
        <button
          type="button"
          @click="exportToExcel"
          :disabled="exporting"
          class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 disabled:opacity-60 font-semibold"
        >
          <i class="fa-solid fa-file-excel mr-2"></i>
          {{ exporting ? 'Exporting...' : 'Export Excel' }}
        </button>
      </div>

      <form @submit.prevent="applyFilters" class="bg-white rounded-lg shadow p-5 mb-6 grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
          <input v-model="filters.date_from" type="date" class="w-full input input-bordered" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
          <input v-model="filters.date_to" type="date" class="w-full input input-bordered" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
          <select v-model="filters.supplier_id" class="w-full select select-bordered">
            <option value="">Semua Supplier</option>
            <option v-for="supplier in suppliers" :key="supplier.id" :value="String(supplier.id)">{{ supplier.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
          <select v-model="filters.outlet_id" class="w-full select select-bordered">
            <option value="">Semua Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="String(outlet.id)">{{ outlet.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Metode Pembayaran</label>
          <select v-model="filters.payment_method" class="w-full select select-bordered">
            <option value="">Semua Metode</option>
            <option v-for="method in paymentMethods" :key="method" :value="method">{{ paymentMethodLabel(method) }}</option>
          </select>
        </div>
        <div class="flex items-end gap-2">
          <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-semibold">
            <i class="fa-solid fa-filter mr-2"></i>Filter
          </button>
          <button type="button" @click="resetFilters" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600" title="Reset filter">
            <i class="fa-solid fa-rotate-left"></i>
          </button>
        </div>
      </form>

      <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full min-w-[900px]">
          <thead class="bg-gray-100 border-b border-gray-200">
            <tr>
              <th class="w-12 px-4 py-3"></th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Supplier</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Outlet</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Metode Pembayaran</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <template v-for="transaction in transactions" :key="transaction.id">
              <tr class="hover:bg-blue-50 cursor-pointer" @click="toggleTransaction(transaction.id)">
                <td class="px-4 py-3 text-center text-gray-500">
                  <i class="fa-solid" :class="expandedTransactions[transaction.id] ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                </td>
                <td class="px-4 py-3 text-sm text-gray-800">{{ formatDate(transaction.transaction_date) }}</td>
                <td class="px-4 py-3 text-sm text-gray-800">{{ transaction.supplier_name }}</td>
                <td class="px-4 py-3 text-sm text-gray-800">{{ transaction.outlet_name }}</td>
                <td class="px-4 py-3 text-sm text-gray-800">{{ paymentMethodLabel(transaction.payment_method) }}</td>
                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800">Rp {{ formatCurrency(transaction.total_amount) }}</td>
              </tr>
              <tr v-if="expandedTransactions[transaction.id]" class="bg-gray-50">
                <td colspan="6" class="p-4">
                  <p class="text-sm font-semibold text-gray-700 mb-2">{{ transaction.retail_number }}</p>
                  <table class="w-full text-sm bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                      <tr>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Harga</th>
                        <th class="px-3 py-2 text-right">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                      <tr v-for="item in transaction.items" :key="item.id">
                        <td class="px-3 py-2">{{ item.item_name }}</td>
                        <td class="px-3 py-2 text-right">{{ formatNumber(item.qty) }} {{ item.unit }}</td>
                        <td class="px-3 py-2 text-right">Rp {{ formatCurrency(item.price) }}</td>
                        <td class="px-3 py-2 text-right font-semibold">Rp {{ formatCurrency(item.subtotal) }}</td>
                      </tr>
                      <tr v-if="!transaction.items.length">
                        <td colspan="4" class="px-3 py-4 text-center text-gray-500">Tidak ada item.</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
            <tr v-if="!transactions.length">
              <td colspan="6" class="py-12 text-center text-gray-500">Tidak ada data ditemukan.</td>
            </tr>
          </tbody>
          <tfoot v-if="transactions.length" class="bg-gray-100 border-t-2 border-gray-300">
            <tr>
              <td colspan="5" class="px-4 py-3 text-right font-semibold text-gray-700">Total</td>
              <td class="px-4 py-3 text-right font-bold text-gray-900">Rp {{ formatCurrency(totalAmount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  transactions: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
});

const filters = ref({ ...props.filters });
const exporting = ref(false);
const expandedTransactions = ref({});
const totalAmount = computed(() => props.transactions.reduce((total, transaction) => total + Number(transaction.total_amount || 0), 0));

const toggleTransaction = (id) => {
  expandedTransactions.value[id] = !expandedTransactions.value[id];
};

const applyFilters = () => {
  router.get(route('retail-food.report'), filters.value, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
  filters.value = { date_from: '', date_to: '', supplier_id: '', outlet_id: '', payment_method: '' };
  applyFilters();
};

const exportToExcel = () => {
  exporting.value = true;
  const params = new URLSearchParams(filters.value).toString();
  window.open(`${route('retail-food.report.export')}?${params}`, '_blank');
  window.setTimeout(() => { exporting.value = false; }, 2000);
};

const formatCurrency = (value) => new Intl.NumberFormat('id-ID').format(value || 0);
const formatNumber = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value || 0);
const formatDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
const paymentMethodLabel = (method) => ({ cash: 'Cash', contra_bon: 'Contra Bon' }[method] || method || '-');
</script>