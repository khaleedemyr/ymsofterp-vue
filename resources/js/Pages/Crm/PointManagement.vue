<template>
  <AppLayout title="Point Management">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        <i class="fa-solid fa-coins text-yellow-600 mr-2"></i>
        Point Management
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Today's Transactions -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Transaksi Hari Ini</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summary.today.total_transactions) }}</p>
              </div>
              <div class="bg-blue-100 p-3 rounded-lg">
                <i class="fa-solid fa-calendar-day text-blue-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Today's Top Up -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Top Up Hari Ini</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summary.today.top_up_count) }}</p>
                <p class="text-sm text-gray-500">{{ formatNumber(summary.today.top_up_points) }} point</p>
              </div>
              <div class="bg-green-100 p-3 rounded-lg">
                <i class="fa-solid fa-arrow-up text-green-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- Today's Redeem -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Redeem Hari Ini</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summary.today.redeem_count) }}</p>
                <p class="text-sm text-gray-500">{{ formatNumber(summary.today.redeem_points) }} point</p>
              </div>
              <div class="bg-red-100 p-3 rounded-lg">
                <i class="fa-solid fa-arrow-down text-red-600 text-xl"></i>
              </div>
            </div>
          </div>

          <!-- This Month's Total -->
          <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Bulan Ini</p>
                <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summary.this_month.total_transactions) }}</p>
                <p class="text-sm text-gray-500">{{ formatRupiah(summary.this_month.top_up_value + summary.this_month.redeem_value) }}</p>
              </div>
              <div class="bg-purple-100 p-3 rounded-lg">
                <i class="fa-solid fa-chart-line text-purple-600 text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Add New Transaction -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-plus text-green-500"></i>
            Tambah Transaksi Manual
          </h3>
          
          <form @submit.prevent="submitForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Customer Search -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-user text-gray-500 mr-1"></i>
                Pilih Member *
              </label>
              <div class="relative">
                <input
                  v-model="form.customer_search"
                  type="text"
                  placeholder="Cari nama, ID, atau telepon..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  @input="searchCustomers"
                  @focus="showCustomerDropdown = true"
                />
                <div v-if="showCustomerDropdown && customers.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                  <div
                    v-for="customer in customers"
                    :key="customer.id"
                    @click="selectCustomer(customer)"
                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0"
                  >
                    <div class="font-medium">{{ customer.name }}</div>
                    <div class="text-sm text-gray-600">{{ customer.costumers_id }} | {{ customer.telepon }}</div>
                  </div>
                </div>
              </div>
              <div v-if="form.customer_id" class="mt-2 text-sm text-green-600">
                <i class="fa-solid fa-check mr-1"></i>
                {{ selectedCustomer?.name }}
              </div>
            </div>

            <!-- Cabang Dropdown -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-building text-gray-500 mr-1"></i>
                Pilih Cabang *
              </label>
              <select
                v-model="form.cabang_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="">Pilih cabang...</option>
                <option v-for="cabang in cabangList" :key="cabang.id" :value="cabang.id">
                  {{ cabang.name }}
                </option>
              </select>
            </div>

            <!-- Transaction Type -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-tag text-gray-500 mr-1"></i>
                Tipe Transaksi *
              </label>
              <select
                v-model="form.type"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
                @change="updatePoint"
              >
                <option value="">Pilih tipe...</option>
                <option value="1">Top Up</option>
                <option value="2">Redeem</option>
              </select>
            </div>

            <!-- Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-money-bill-wave text-gray-500 mr-1"></i>
                Jumlah Transaksi *
              </label>
              <input
                v-model="form.jml_trans"
                type="number"
                min="1"
                placeholder="Masukkan jumlah..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
                @input="updatePoint"
              />
              <div v-if="form.type === '1' && form.jml_trans" class="mt-1 text-sm text-blue-600">
                Point yang didapat: {{ formatNumber(form.point) }}
              </div>
            </div>

            <!-- Bill Number -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-receipt text-gray-500 mr-1"></i>
                Nomor Bill *
              </label>
              <input
                v-model="form.no_bill"
                type="text"
                placeholder="Masukkan nomor bill..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>

            <!-- Keterangan -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-comment text-gray-500 mr-1"></i>
                Keterangan
              </label>
              <input
                v-model="form.keterangan"
                type="text"
                placeholder="Keterangan (opsional)..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Submit Button -->
            <div class="lg:col-span-3">
              <button
                type="submit"
                :disabled="loading"
                class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-save mr-2"></i>
                {{ loading ? 'Menyimpan...' : 'Simpan Transaksi' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-filter text-gray-500"></i>
            Filter Data
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
              <input
                v-model="filters.search"
                type="text"
                placeholder="Cari member, bill, cabang..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @input="debounceSearch"
              />
            </div>

            <!-- Type Filter -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tipe</label>
              <select
                v-model="filters.type"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              >
                <option value="">Semua Tipe</option>
                <option value="1">Top Up</option>
                <option value="2">Redeem</option>
              </select>
            </div>

            <!-- Sort By -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Urutkan</label>
              <select
                v-model="filters.sort_by"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              >
                <option value="created_at">Tanggal</option>
                <option value="customer_name">Nama Member</option>
                <option value="jml_trans">Jumlah Transaksi</option>
                <option value="point">Point</option>
              </select>
            </div>

            <!-- Per Page -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Per Halaman</label>
              <select
                v-model="filters.per_page"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                @change="applyFilters"
              >
                <option value="10">10 data</option>
                <option value="25">25 data</option>
                <option value="50">50 data</option>
                <option value="100">100 data</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-table text-gray-500"></i>
            Daftar Transaksi
          </h3>
          
          <!-- Pagination Info -->
          <div v-if="pagination.total > 0" class="mb-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="text-sm text-gray-600">
              Menampilkan {{ pagination.from || 0 }} - {{ pagination.to || 0 }} dari {{ pagination.total || 0 }} data
            </div>
            
            <div v-if="pagination.last_page > 1" class="flex gap-1">
              <!-- First Page -->
              <button
                @click="changePage(1)"
                :disabled="pagination.current_page === 1"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                title="Halaman Pertama"
              >
                <i class="fa-solid fa-angles-left"></i>
              </button>
              
              <!-- Previous Page -->
              <button
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                title="Halaman Sebelumnya"
              >
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              
              <!-- Page Numbers -->
              <button
                v-for="page in getPageNumbers()"
                :key="page"
                @click="page !== '...' ? changePage(page) : null"
                :class="[
                  'px-3 py-2 border rounded-md text-sm min-w-[40px]',
                  page === pagination.current_page
                    ? 'bg-blue-600 text-white border-blue-600'
                    : page === '...'
                      ? 'border-gray-300 text-gray-500 cursor-default'
                      : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                ]"
              >
                {{ page }}
              </button>
              
              <!-- Next Page -->
              <button
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                title="Halaman Selanjutnya"
              >
                <i class="fa-solid fa-chevron-right"></i>
              </button>
              
              <!-- Last Page -->
              <button
                @click="changePage(pagination.last_page)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                title="Halaman Terakhir"
              >
                <i class="fa-solid fa-angles-right"></i>
              </button>
            </div>
          </div>

          <div v-if="transactions.length === 0" class="text-center py-8 text-gray-500">
            <i class="fa-solid fa-inbox text-4xl mb-4"></i>
            <p>Tidak ada data transaksi</p>
          </div>
          
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-calendar mr-1"></i>
                    Tanggal
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-user mr-1"></i>
                    Member
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-tag mr-1"></i>
                    Tipe
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-receipt mr-1"></i>
                    No. Bill
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-money-bill-wave mr-1"></i>
                    Jumlah
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-coins mr-1"></i>
                    Point
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-building mr-1"></i>
                    Cabang
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <i class="fa-solid fa-cog mr-1"></i>
                    Aksi
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ transaction.created_at }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ transaction.customer_name }}</div>
                      <div class="text-sm text-gray-500">{{ transaction.costumers_id }}</div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="[
                      'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                      transaction.type === '1' 
                        ? 'bg-green-100 text-green-800' 
                        : 'bg-red-100 text-red-800'
                    ]">
                      {{ transaction.type_text }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ transaction.bill_number }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                    {{ transaction.jml_trans_formatted }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ transaction.point_formatted }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ transaction.cabang_name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                      @click="deleteTransaction(transaction.id)"
                      class="text-red-600 hover:text-red-900"
                      title="Hapus"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  transactions: {
    type: Array,
    default: () => []
  },
  pagination: {
    type: Object,
    default: () => ({
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
      from: 0,
      to: 0,
    })
  },
  summary: {
    type: Object,
    default: () => ({
      today: {},
      this_month: {}
    })
  },
  filters: {
    type: Object,
    default: () => ({})
  }
});

// Reactive data
const loading = ref(false);
const customers = ref([]);
const cabangList = ref([]);
const showCustomerDropdown = ref(false);
const selectedCustomer = ref(null);
const searchTimeout = ref(null);

const form = ref({
  customer_id: '',
  customer_search: '',
  cabang_id: '',
  type: '',
  jml_trans: '',
  no_bill: '',
  keterangan: '',
  point: 0, // Point yang sudah dihitung di frontend
});

const filters = ref({
  search: props.filters.search || '',
  type: props.filters.type || '',
  sort_by: props.filters.sort_by || 'created_at',
  sort_order: props.filters.sort_order || 'desc',
  per_page: props.filters.per_page || 10,
});

// Utility functions
function formatNumber(value, decimals = 0) {
  if (value === null || value === undefined) return '0';
  return new Intl.NumberFormat('id-ID').format(Number(value).toFixed(decimals));
}

function formatRupiah(value) {
  if (value === null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}

function calculatePoints(amount) {
  if (!amount) return 0;
  return Math.floor(amount / 50000) * 1250;
}

// Update point when jml_trans or type changes
function updatePoint() {
  if (form.value.type === '1' && form.value.jml_trans) {
    form.value.point = calculatePoints(form.value.jml_trans);
  } else {
    form.value.point = 0;
  }
}

function getPageNumbers() {
  const current = props.pagination.current_page;
  const last = props.pagination.last_page;
  const delta = 2;
  
  if (last <= 7) {
    return Array.from({ length: last }, (_, i) => i + 1);
  }
  
  const range = [];
  const rangeWithDots = [];
  
  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i);
  }
  
  if (current - delta > 2) {
    rangeWithDots.push(1, '...');
  } else {
    rangeWithDots.push(1);
  }
  
  rangeWithDots.push(...range);
  
  if (current + delta < last - 1) {
    rangeWithDots.push('...', last);
  } else if (last > 1) {
    rangeWithDots.push(last);
  }
  
  return rangeWithDots.filter((item, index, array) => {
    if (item === '...') return true;
    return array.indexOf(item) === index;
  });
}

// Form functions
async function searchCustomers() {
  if (form.value.customer_search.length < 2) {
    customers.value = [];
    return;
  }

  try {
    const response = await fetch(`/crm/point-management/search-customers?search=${form.value.customer_search}`);
    const data = await response.json();
    customers.value = data;
  } catch (error) {
    console.error('Error searching customers:', error);
    window.Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Gagal mencari customer',
      confirmButtonText: 'OK'
    });
  }
}



function selectCustomer(customer) {
  form.value.customer_id = customer.id;
  form.value.customer_search = customer.name;
  selectedCustomer.value = customer;
  showCustomerDropdown.value = false;
  customers.value = [];
}

async function loadCabangList() {
  try {
    const response = await fetch('/crm/point-management/cabang-list');
    const data = await response.json();
    cabangList.value = data;
  } catch (error) {
    console.error('Error loading cabang list:', error);
    window.Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Gagal memuat data cabang',
      confirmButtonText: 'OK'
    });
  }
}



async function submitForm() {
  if (!form.value.customer_id || !form.value.cabang_id || !form.value.type || !form.value.jml_trans || !form.value.no_bill) {
    window.Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Mohon lengkapi semua field yang wajib diisi',
      confirmButtonText: 'OK'
    });
    return;
  }

  // Ensure point is calculated before submit
  updatePoint();

  // Show confirmation dialog
  const result = await window.Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin menyimpan data ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
    reverseButtons: true
  });

  if (!result.isConfirmed) {
    return;
  }

  // Show loading state
  window.Swal.fire({
    title: 'Menyimpan Data...',
    text: 'Mohon tunggu sebentar',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      window.Swal.showLoading();
    }
  });

  loading.value = true;

  try {
    const response = await fetch('/crm/point-management', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
      },
      body: JSON.stringify(form.value),
    });

    const data = await response.json();

    if (data.success) {
      window.Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: data.message,
        confirmButtonText: 'OK'
      });
      resetForm();
      applyFilters(); // Refresh the table
    } else {
      window.Swal.fire({
        icon: 'error',
        title: 'Error',
        text: data.message,
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error submitting form:', error);
    window.Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat menyimpan data',
      confirmButtonText: 'OK'
    });
  } finally {
    loading.value = false;
  }
}

function resetForm() {
  form.value = {
    customer_id: '',
    customer_search: '',
    cabang_id: '',
    type: '',
    jml_trans: '',
    no_bill: '',
    keterangan: '',
    point: 0,
  };
  selectedCustomer.value = null;
}

async function deleteTransaction(id) {
  // Show confirmation dialog
  const result = await window.Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus transaksi ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#d33',
    reverseButtons: true
  });

  if (!result.isConfirmed) {
    return;
  }

  // Show loading state
  window.Swal.fire({
    title: 'Menghapus Data...',
    text: 'Mohon tunggu sebentar',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      window.Swal.showLoading();
    }
  });

  try {
    const response = await fetch(`/crm/point-management/${id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
      },
    });

    const data = await response.json();

    if (data.success) {
      window.Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: data.message,
        confirmButtonText: 'OK'
      });
      applyFilters(); // Refresh the table
    } else {
      window.Swal.fire({
        icon: 'error',
        title: 'Error',
        text: data.message,
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error deleting transaction:', error);
    window.Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat menghapus data',
      confirmButtonText: 'OK'
    });
  }
}

// Filter functions
function debounceSearch() {
  clearTimeout(searchTimeout.value);
  searchTimeout.value = setTimeout(() => {
    filters.value.page = 1; // Reset to first page when searching
    applyFilters();
  }, 500);
}

function applyFilters() {
  router.visit('/crm/point-management', {
    data: filters.value,
    preserveState: true,
    replace: true,
  });
}

function changePage(page) {
  if (page >= 1 && page <= props.pagination.last_page && page !== '...') {
    filters.value.page = page;
    applyFilters();
  }
}

// Load cabang list on mount
onMounted(() => {
  loadCabangList();
  
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.relative')) {
      showCustomerDropdown.value = false;
    }
  });
});
</script> 