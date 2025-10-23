<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-2xl font-bold mb-6">Laporan Invoice Outlet</h1>
      
      <!-- Info Box -->
      <div v-if="!props.hasFilters" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
          <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
          <div>
            <h3 class="text-blue-800 font-semibold mb-1">Cara menggunakan laporan ini:</h3>
            <p class="text-blue-700 text-sm">
              <span v-if="isSuperuser">
                Isi minimal satu filter di bawah ini (pencarian, tanggal, atau outlet) kemudian klik tombol "Load Data" untuk melihat data laporan invoice outlet.
              </span>
              <span v-else>
                Isi minimal satu filter di bawah ini (pencarian atau tanggal) kemudian klik tombol "Load Data" untuk melihat data laporan invoice outlet. Data akan otomatis difilter berdasarkan outlet Anda.
              </span>
            </p>
          </div>
        </div>
      </div>
      
      <!-- Info Outlet untuk Non-Superuser -->
      <div v-if="!isSuperuser && !props.hasFilters" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
          <i class="fas fa-building text-green-500 mt-1 mr-3"></i>
          <div>
            <h3 class="text-green-800 font-semibold mb-1">Outlet Aktif:</h3>
            <p class="text-green-700 text-sm">
              Anda sedang melihat data untuk outlet: <strong>{{ currentOutletName }}</strong>
            </p>
          </div>
        </div>
      </div>
      
      <!-- Filter & Searchbar -->
      <form @submit.prevent="applyFilter" class="flex flex-wrap gap-2 mb-4 items-end">
        <!-- Superuser: bisa pilih outlet -->
        <div v-if="isSuperuser" class="flex items-center gap-2">
          <label class="text-sm">Outlet</label>
          <select v-model="filterOutlet" class="border border-gray-300 rounded-lg px-2 py-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua</option>
            <option v-for="o in props.outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        
        <!-- Non-superuser: tampilkan outlet yang bersangkutan -->
        <div v-else class="flex items-center gap-2">
          <label class="text-sm font-semibold text-gray-700">Outlet:</label>
          <span class="px-3 py-2 bg-blue-100 text-blue-800 rounded-lg font-medium">
            {{ currentOutletName }}
          </span>
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Cari</label>
          <input v-model="filterSearch" type="text" class="form-input rounded border px-2 py-1" placeholder="Cari nomor invoice, GR, outlet..." />
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Tanggal Dari</label>
          <input v-model="filterFrom" type="date" class="form-input rounded border px-2 py-1" />
        </div>
        <div>
          <label class="block text-xs font-bold mb-1">Tanggal Sampai</label>
          <input v-model="filterTo" type="date" class="form-input rounded border px-2 py-1" />
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-bold flex items-center gap-2">
          <i class="fas fa-search"></i>
          Load Data
        </button>
      </form>
      <!-- Report Table -->
      <div class="overflow-x-auto bg-white rounded-xl shadow-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th></th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tgl Invoice</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipe</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No GR/RWS</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tgl GR/RWS</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Total</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="row in props.data" :key="row.gr_id">
              <tr class="hover:bg-blue-50 transition-colors duration-200 group">
                <td class="px-2 py-2 align-top">
                  <button @click="toggleExpand(row.gr_id)" class="focus:outline-none transition-transform duration-200 group-hover:scale-110">
                    <span v-if="expanded[row.gr_id]">▼</span>
                    <span v-else>▶</span>
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(row.invoice_date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.outlet_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatWarehouse(row) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span :class="row.transaction_type === 'GR' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'" class="px-2 py-1 rounded-full text-xs font-semibold">
                    {{ row.transaction_type }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ row.gr_number }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(row.gr_receive_date) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ formatRupiah(row.payment_total) }}</td>
              </tr>
              <transition name="fade-expand">
                <tr v-if="expanded[row.gr_id]" :key="'detail-'+row.gr_id">
                  <td></td>
                  <td colspan="6" class="bg-gray-50 px-0 py-0">
                    <div class="rounded-lg border border-blue-100 bg-blue-50/60 shadow-inner mx-4 my-2 overflow-x-auto">
                      <div v-if="!props.details[row.gr_id] || !props.details[row.gr_id].length" class="text-gray-400 py-6 text-center">Tidak ada detail.</div>
                      <div v-else>
                        <table class="w-full text-xs">
                          <thead class="sticky top-0 z-10 bg-blue-100/80">
                            <tr>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Item</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Qty</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Unit</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Harga</th>
                              <th class="px-4 py-2 border-b font-semibold text-gray-700">Subtotal</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="item in props.details[row.gr_id]" :key="item.item_name">
                              <td class="px-4 py-2 border-b">{{ item.item_name }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ item.qty }}</td>
                              <td class="px-4 py-2 border-b">{{ item.unit_name }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(item.price) }}</td>
                              <td class="px-4 py-2 border-b text-right">{{ formatRupiah(item.subtotal) }}</td>
                            </tr>
                            <tr>
                              <td colspan="4" class="px-4 py-2 border-b font-bold text-right bg-blue-50">Grand Total</td>
                              <td class="px-4 py-2 border-b font-bold text-right bg-blue-50">{{ formatRupiah(row.payment_total) }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
              </transition>
            </template>
            <tr v-if="!props.hasFilters">
              <td colspan="8" class="text-center py-12">
                <div class="flex flex-col items-center space-y-4">
                  <div class="text-gray-500 text-lg">
                    <i class="fas fa-filter text-4xl mb-4"></i>
                    <p class="font-semibold">Silakan isi filter terlebih dahulu</p>
                    <p class="text-sm">Pilih outlet, tanggal, atau masukkan kata kunci pencarian untuk melihat data laporan invoice outlet.</p>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-else-if="!props.data.length">
              <td colspan="8" class="text-center py-8 text-gray-400">Tidak ada data yang sesuai dengan filter yang dipilih.</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Grand Total Section -->
      <div v-if="props.hasFilters && props.data.length > 0" class="mt-6 bg-gray-50 rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-xl font-bold text-gray-800">Grand Total Summary</h3>
          <div class="text-right">
            <div class="text-3xl font-bold text-gray-900">{{ formatRupiah(grandTotal) }}</div>
            <div class="text-sm text-gray-500">Total {{ props.data.length }} transaksi</div>
          </div>
        </div>
        
        <!-- Breakdown Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- GR Breakdown -->
          <div class="bg-white rounded-lg p-4 border-l-4 border-green-500">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-semibold">
                  GR (Good Receive)
                </span>
                <span class="text-sm text-gray-600">{{ grCount }} transaksi</span>
              </div>
            </div>
            <div class="text-2xl font-bold text-green-700">{{ formatRupiah(grTotal) }}</div>
          </div>
          
          <!-- RWS Breakdown -->
          <div class="bg-white rounded-lg p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold">
                  RWS (Retail Warehouse Sales)
                </span>
                <span class="text-sm text-gray-600">{{ rwsCount }} transaksi</span>
              </div>
            </div>
            <div class="text-2xl font-bold text-blue-700">{{ formatRupiah(rwsTotal) }}</div>
          </div>
        </div>
        
        <!-- Percentage Breakdown -->
        <div class="mt-4 pt-4 border-t border-gray-200">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600">Persentase:</span>
            <div class="flex items-center gap-4">
              <span class="text-green-700 font-semibold">
                GR: {{ grPercentage }}%
              </span>
              <span class="text-blue-700 font-semibold">
                RWS: {{ rwsPercentage }}%
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  data: Array,
  details: Object,
  outlets: Array,
  filters: Object,
  user_id_outlet: Number,
  hasFilters: Boolean
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const isSuperuser = computed(() => props.user_id_outlet == 1);

// Computed property untuk mendapatkan nama outlet yang bersangkutan
const currentOutletName = computed(() => {
  if (isSuperuser.value) return '';
  const currentOutlet = props.outlets.find(o => o.id == props.user_id_outlet);
  return currentOutlet ? currentOutlet.name : 'Outlet Tidak Ditemukan';
});

// Computed property untuk grand total
const grandTotal = computed(() => {
  return props.data.reduce((total, row) => {
    return total + (parseFloat(row.payment_total) || 0);
  }, 0);
});

// Computed property untuk menghitung jumlah GR
const grCount = computed(() => {
  return props.data.filter(row => row.transaction_type === 'GR').length;
});

// Computed property untuk menghitung jumlah RWS
const rwsCount = computed(() => {
  return props.data.filter(row => row.transaction_type === 'RWS').length;
});

// Computed property untuk total GR
const grTotal = computed(() => {
  return props.data
    .filter(row => row.transaction_type === 'GR')
    .reduce((total, row) => total + (parseFloat(row.payment_total) || 0), 0);
});

// Computed property untuk total RWS
const rwsTotal = computed(() => {
  return props.data
    .filter(row => row.transaction_type === 'RWS')
    .reduce((total, row) => total + (parseFloat(row.payment_total) || 0), 0);
});


// Computed property untuk persentase GR
const grPercentage = computed(() => {
  return grandTotal.value > 0 ? Math.round((grTotal.value / grandTotal.value) * 100) : 0;
});

// Computed property untuk persentase RWS
const rwsPercentage = computed(() => {
  return grandTotal.value > 0 ? Math.round((rwsTotal.value / grandTotal.value) * 100) : 0;
});

const filterOutlet = ref(props.filters?.outlet_id || '');
const filterSearch = ref(props.filters?.search || '');
const filterFrom = ref(props.filters?.from || '');
const filterTo = ref(props.filters?.to || '');

const expanded = ref({});
function toggleExpand(id) {
  expanded.value[id] = !expanded.value[id];
}

function applyFilter() {
  // Validasi minimal ada satu filter yang diisi
  const hasAnyFilter = filterSearch.value || filterFrom.value || filterTo.value || 
                      (isSuperuser.value && filterOutlet.value);
  
  if (!hasAnyFilter) {
    alert('Silakan isi minimal satu filter (pencarian, tanggal, atau outlet) untuk melihat data.');
    return;
  }
  
  router.get(route('report-invoice-outlet'), {
    outlet_id: isSuperuser.value ? filterOutlet.value : props.user_id_outlet,
    search: filterSearch.value || undefined,
    from: filterFrom.value || undefined,
    to: filterTo.value || undefined,
  }, {
    preserveState: true,
    replace: true
  });
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}
function formatRupiah(val) {
  if (val === null || val === undefined || isNaN(val)) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0 });
}

function formatWarehouse(row) {
  if (row.warehouse_name && row.warehouse_division_name) return row.warehouse_name + ' - ' + row.warehouse_division_name;
  if (row.warehouse_name) return row.warehouse_name;
  if (row.warehouse_division_name) return row.warehouse_division_name;
  return '-';
}
</script>

<style scoped>
.fade-expand-enter-active, .fade-expand-leave-active {
  transition: all 0.3s cubic-bezier(.4,2,.6,1);
  overflow: hidden;
}
.fade-expand-enter-from, .fade-expand-leave-to {
  opacity: 0;
  max-height: 0;
}
.fade-expand-enter-to, .fade-expand-leave-from {
  opacity: 1;
  max-height: 500px;
}
</style> 