<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <div class="p-2 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-chart-column text-white text-xl"></i>
              </div>
              <span>Report Retail Food</span>
            </h1>
            <p class="text-sm text-gray-500 mt-1 ml-14">Rekap transaksi retail food per outlet dan supplier</p>
          </div>
          <button
            type="button"
            @click="exportToExcel"
            :disabled="exporting"
            class="group inline-flex items-center gap-2 bg-gradient-to-r from-green-500 to-green-600 text-white px-5 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 font-semibold hover:from-green-600 hover:to-green-700 transform hover:-translate-y-0.5 disabled:opacity-60 disabled:pointer-events-none"
          >
            <i class="fa-solid fa-file-excel"></i>
            <span>{{ exporting ? 'Menyiapkan file...' : 'Export Excel' }}</span>
          </button>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
            <div class="p-1.5 bg-blue-100 rounded-lg">
              <i class="fa-solid fa-filter text-blue-600"></i>
            </div>
            Filter Data
          </h3>
          <button type="button" @click="resetFilters" class="text-sm font-semibold text-gray-500 hover:text-blue-600 transition-colors">
            <i class="fa-solid fa-rotate-left mr-1.5"></i>Reset
          </button>
        </div>

        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700">
              <i class="fa-solid fa-calendar-alt mr-1 text-gray-400"></i>Tanggal Dari
            </label>
            <input v-model="filters.date_from" type="date" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white" />
          </div>
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700">
              <i class="fa-solid fa-calendar-check mr-1 text-gray-400"></i>Tanggal Sampai
            </label>
            <input v-model="filters.date_to" type="date" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white" />
          </div>
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700">
              <i class="fa-solid fa-truck mr-1 text-gray-400"></i>Supplier
            </label>
            <Multiselect
              v-model="selectedSupplier"
              :options="suppliers"
              :searchable="true"
              :close-on-select="true"
              :clear-on-select="false"
              :preserve-search="true"
              :allow-empty="true"
              placeholder="Semua supplier"
              track-by="id"
              label="name"
              @select="applyFilters"
              @remove="applyFilters"
            />
          </div>
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700">
              <i class="fa-solid fa-store mr-1 text-gray-400"></i>Outlet
            </label>
            <Multiselect
              v-model="selectedOutlet"
              :options="outlets"
              :searchable="true"
              :close-on-select="true"
              :clear-on-select="false"
              :preserve-search="true"
              :allow-empty="true"
              placeholder="Semua outlet"
              track-by="id"
              label="name"
              @select="applyFilters"
              @remove="applyFilters"
            />
          </div>
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700">
              <i class="fa-solid fa-credit-card mr-1 text-gray-400"></i>Metode Pembayaran
            </label>
            <select v-model="filters.payment_method" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white">
              <option value="">Semua Metode</option>
              <option v-for="method in paymentMethods" :key="method" :value="method">{{ paymentMethodLabel(method) }}</option>
            </select>
          </div>
        </form>

        <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
          <button
            type="button"
            @click="applyFilters"
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl hover:from-blue-600 hover:to-blue-700 shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
          >
            <i class="fa-solid fa-search"></i>
            Terapkan Filter
          </button>
        </div>
      </div>

      <!-- Results Info -->
      <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-l-4 border-blue-500 rounded-xl p-4 mb-6 shadow-sm">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
          <div class="flex items-center gap-2 text-sm font-semibold text-blue-800">
            <i class="fa-solid fa-info-circle text-lg"></i>
            <span v-if="transactions.total">
              Menampilkan <span class="font-bold text-blue-900">{{ transactions.from }}-{{ transactions.to }}</span> dari <span class="font-bold text-blue-900">{{ transactions.total }}</span> transaksi
            </span>
            <span v-else>Tidak ada transaksi ditemukan</span>
          </div>
          <div class="text-sm font-semibold text-blue-900">
            Total halaman ini: <span class="text-base font-bold">Rp {{ formatCurrency(totalAmount) }}</span>
          </div>
        </div>
      </div>

      <!-- Table Section -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
              <tr>
                <th class="w-12 px-4 py-4 border-r border-gray-200"></th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2"><i class="fa-solid fa-calendar text-gray-400"></i>Tanggal</div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2"><i class="fa-solid fa-truck text-gray-400"></i>Supplier</div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2"><i class="fa-solid fa-store text-gray-400"></i>Outlet</div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-200">
                  <div class="flex items-center gap-2"><i class="fa-solid fa-credit-card text-gray-400"></i>Metode Pembayaran</div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2"><i class="fa-solid fa-money-bill-wave text-gray-400"></i>Total</div>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <template v-for="transaction in transactionRows" :key="transaction.id">
                <tr class="hover:bg-blue-50/50 transition-colors duration-150 cursor-pointer" @click="toggleTransaction(transaction.id)">
                  <td class="px-4 py-4 text-center text-gray-400">
                    <i class="fa-solid transition-transform duration-200" :class="expandedTransactions[transaction.id] ? 'fa-chevron-down text-blue-600' : 'fa-chevron-right'"></i>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-semibold text-gray-900">{{ formatDate(transaction.transaction_date) }}</div>
                    <div class="text-xs text-gray-400 font-mono mt-0.5">{{ transaction.retail_number }}</div>
                  </td>
                  <td class="px-6 py-4 text-sm font-medium text-gray-800">{{ transaction.supplier_name }}</td>
                  <td class="px-6 py-4 text-sm text-gray-700">{{ transaction.outlet_name }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="{
                      'inline-flex items-center px-3 py-1.5 text-xs font-bold rounded-full shadow-sm': true,
                      'bg-green-100 text-green-800 border border-green-200': transaction.payment_method === 'cash',
                      'bg-blue-100 text-blue-800 border border-blue-200': transaction.payment_method !== 'cash'
                    }">
                      <i :class="transaction.payment_method === 'cash' ? 'fa-solid fa-money-bill-wave mr-1.5' : 'fa-solid fa-file-invoice mr-1.5'"></i>
                      {{ paymentMethodLabel(transaction.payment_method) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-blue-700">Rp {{ formatCurrency(transaction.total_amount) }}</td>
                </tr>
                <tr v-if="expandedTransactions[transaction.id]" class="bg-slate-50">
                  <td colspan="6" class="p-0">
                    <div class="mx-6 my-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                      <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                          <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Item</th>
                            <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wide">Qty</th>
                            <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wide">Harga</th>
                            <th class="px-4 py-2.5 text-right text-xs font-bold text-gray-500 uppercase tracking-wide">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                          <tr v-for="item in transaction.items" :key="item.id">
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ item.item_name }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ formatNumber(item.qty) }} {{ item.unit }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-600">Rp {{ formatCurrency(item.price) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-gray-800">Rp {{ formatCurrency(item.subtotal) }}</td>
                          </tr>
                          <tr v-if="!transaction.items.length">
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">Tidak ada item pada transaksi ini.</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="!transactionRows.length">
                <td colspan="6" class="text-center py-16">
                  <div class="flex flex-col items-center gap-3">
                    <div class="p-4 bg-gray-100 rounded-full">
                      <i class="fa-solid fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 font-medium">Tidak ada transaksi ditemukan</p>
                    <p class="text-sm text-gray-400">Coba ubah filter pencarian</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="transactions.total" class="flex flex-col sm:flex-row justify-between items-center gap-3 mt-6">
        <label class="flex items-center gap-2 text-sm text-gray-600">
          Baris per halaman
          <select v-model="filters.per_page" @change="applyFilters" class="rounded-xl border-2 border-gray-200 py-1.5 pl-3 pr-8 text-sm font-semibold focus:border-blue-500 focus:ring-blue-500">
            <option value="15">15</option>
            <option value="30">30</option>
            <option value="50">50</option>
          </select>
        </label>
        <div class="flex flex-wrap justify-end items-center gap-2">
          <button
            v-for="link in transactions.links"
            :key="link.label"
            :disabled="!link.url"
            @click="goToPage(link.url)"
            v-html="link.label"
            class="px-4 py-2 rounded-xl border-2 text-sm font-semibold transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
            :class="[
              link.active
                ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white border-blue-600 shadow-lg'
                : 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50 hover:border-blue-300',
              !link.url ? 'cursor-not-allowed' : 'cursor-pointer'
            ]"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  transactions: { type: Object, required: true },
  suppliers: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
});

const filters = ref({ ...props.filters, per_page: String(props.transactions.per_page || 15) });
const selectedSupplier = ref(props.suppliers.find((supplier) => String(supplier.id) === String(filters.value.supplier_id)) || null);
const selectedOutlet = ref(props.outlets.find((outlet) => String(outlet.id) === String(filters.value.outlet_id)) || null);
const exporting = ref(false);
const expandedTransactions = ref({});
const transactionRows = computed(() => props.transactions.data || []);
const totalAmount = computed(() => transactionRows.value.reduce((total, transaction) => total + Number(transaction.total_amount || 0), 0));

watch(selectedSupplier, (supplier) => {
  filters.value.supplier_id = supplier ? String(supplier.id) : '';
});

watch(selectedOutlet, (outlet) => {
  filters.value.outlet_id = outlet ? String(outlet.id) : '';
});

const toggleTransaction = (id) => {
  expandedTransactions.value[id] = !expandedTransactions.value[id];
};

const applyFilters = () => {
  router.get(route('retail-food.report'), filters.value, { preserveState: true, preserveScroll: true });
};

const goToPage = (url) => {
  if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
  filters.value = { date_from: '', date_to: '', supplier_id: '', outlet_id: '', payment_method: '', per_page: '15' };
  selectedSupplier.value = null;
  selectedOutlet.value = null;
  applyFilters();
};

const exportToExcel = () => {
  exporting.value = true;
  const { per_page, ...exportFilters } = filters.value;
  const params = new URLSearchParams(exportFilters).toString();
  window.open(`${route('retail-food.report.export')}?${params}`, '_blank');
  window.setTimeout(() => { exporting.value = false; }, 2000);
};

const formatCurrency = (value) => new Intl.NumberFormat('id-ID').format(value || 0);
const formatNumber = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value || 0);
const formatDate = (value) => value ? new Date(value).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
const paymentMethodLabel = (method) => ({ cash: 'Cash', contra_bon: 'Contra Bon' }[method] || method || '-');
</script>