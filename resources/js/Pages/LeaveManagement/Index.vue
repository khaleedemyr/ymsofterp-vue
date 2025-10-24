<template>
  <AppLayout title="Manajemen Cuti">
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Manajemen Cuti Karyawan
        </h2>
        <div class="flex gap-2">
          <button @click="showStatistics" 
                  class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fa-solid fa-chart-bar"></i>
            Statistik
          </button>
          <button @click="showMonthlyCreditModal" 
                  class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fa-solid fa-plus"></i>
            Kredit Bulanan
          </button>
          <button @click="showBurningModal" 
                  class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fa-solid fa-fire"></i>
            Burning Cuti
          </button>
        </div>
      </div>
    </template>

    <div class="py-6">
      <!-- Filter Section -->
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-4">
          <div class="flex gap-4 items-end">
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-700 mb-1">Cari Karyawan</label>
              <input type="text" v-model="filters.search" 
                     placeholder="Cari nama, email, jabatan, outlet..."
                     class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="w-32">
              <label class="block text-sm font-medium text-gray-700 mb-1">Per Halaman</label>
              <select v-model="filters.per_page" @change="applyFilters"
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
            </div>
            <button @click="applyFilters" 
                    class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg">
              <i class="fa-solid fa-search mr-1"></i>
              Cari
            </button>
            <button @click="resetFilters" 
                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
              <i class="fa-solid fa-refresh mr-1"></i>
              Reset
            </button>
          </div>
        </div>
      </div>

      <!-- Users Table -->
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Karyawan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Masuk</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Cuti</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                      <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold">
                        {{ getInitials(user.nama_lengkap) }}
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900">{{ user.nama_lengkap }}</div>
                      <div class="text-sm text-gray-500">{{ user.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ user.nama_jabatan || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ user.nama_outlet || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(user.tanggal_masuk) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getLeaveBalanceClass(user.cuti)">
                    {{ user.cuti || 0 }} hari
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex gap-2">
                    <button @click="showHistory(user)" 
                            class="text-indigo-600 hover:text-indigo-900">
                      <i class="fa-solid fa-history mr-1"></i>
                      History
                    </button>
                    <button @click="openAdjustmentModal(user)" 
                            class="text-green-600 hover:text-green-900">
                      <i class="fa-solid fa-edit mr-1"></i>
                      Adjust
                    </button>
                    <button @click="openUseLeaveModal(user)" 
                            class="text-red-600 hover:text-red-900">
                      <i class="fa-solid fa-minus mr-1"></i>
                      Pakai
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.total > 0" class="px-4 py-3 border-t border-gray-200 bg-white">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Menampilkan {{ users.from }} sampai {{ users.to }} dari {{ users.total }} data
            </div>
            <div class="flex items-center gap-2">
              <button v-for="page in getVisiblePages()" :key="page"
                      @click="changePage(page)"
                      :class="[
                        'px-3 py-1 text-sm border rounded',
                        page === users.current_page
                          ? 'bg-indigo-600 text-white border-indigo-600'
                          : 'border-gray-300 hover:bg-gray-50'
                      ]">
                {{ page }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <!-- Statistics Modal -->
    <div v-if="showStatsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[80vh] overflow-y-auto">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Statistik Cuti</h3>
            <button @click="showStatsModal = false" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
        </div>
        <div class="p-6" v-if="statistics">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-blue-50 p-4 rounded-lg">
              <div class="text-sm text-blue-600 font-medium">Total Karyawan Aktif</div>
              <div class="text-2xl font-bold text-blue-900">{{ statistics.total_active_users }}</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
              <div class="text-sm text-green-600 font-medium">Total Saldo Cuti</div>
              <div class="text-2xl font-bold text-green-900">{{ statistics.total_leave_balance }} hari</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
              <div class="text-sm text-purple-600 font-medium">Rata-rata Saldo Cuti</div>
              <div class="text-2xl font-bold text-purple-900">{{ statistics.average_leave_balance }} hari</div>
            </div>
            <div class="bg-orange-50 p-4 rounded-lg">
              <div class="text-sm text-orange-600 font-medium">Kredit Bulanan Tahun Ini</div>
              <div class="text-2xl font-bold text-orange-900">{{ statistics.monthly_credits_this_year }}x</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Monthly Credit Modal -->
    <div v-if="showMonthlyCreditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Kredit Cuti Bulanan</h3>
            <button @click="showMonthlyCreditModal = false" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="processMonthlyCredit" class="p-6">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
            <input type="number" v-model="monthlyCreditForm.year" 
                   min="2020" max="2030" required
                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
            <select v-model="monthlyCreditForm.month" required
                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
              <option value="">Pilih Bulan</option>
              <option v-for="(month, index) in months" :key="index" :value="index + 1">
                {{ month }}
              </option>
            </select>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showMonthlyCreditModal = false" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
              Batal
            </button>
            <button type="submit" :disabled="isProcessing"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-400">
              <i v-if="isProcessing" class="fa-solid fa-spinner fa-spin mr-1"></i>
              Proses
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Burning Modal -->
    <div v-if="showBurningModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Burning Cuti Tahun Sebelumnya</h3>
            <button @click="showBurningModal = false" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="processBurning" class="p-6">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Saat Ini</label>
            <input type="number" v-model="burningForm.year" 
                   min="2020" max="2030" required
                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
          <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
            <div class="text-sm text-red-800">
              <strong>Peringatan:</strong> Proses ini akan menghapus sisa cuti tahun {{ burningForm.year - 1 }} untuk semua karyawan.
            </div>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showBurningModal = false" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
              Batal
            </button>
            <button type="submit" :disabled="isProcessing"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:bg-gray-400">
              <i v-if="isProcessing" class="fa-solid fa-spinner fa-spin mr-1"></i>
              Burning
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Adjustment Modal -->
    <div v-if="showAdjustmentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Penyesuaian Saldo Cuti</h3>
            <button @click="showAdjustmentModal = false" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="processAdjustment" class="p-6">
          <div class="mb-4">
            <div class="text-sm text-gray-600 mb-2">
              <strong>Karyawan:</strong> {{ selectedUser?.nama_lengkap }}<br>
              <strong>Saldo Saat Ini:</strong> {{ selectedUser?.cuti || 0 }} hari
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Penyesuaian</label>
            <input type="number" v-model="adjustmentForm.amount" step="0.5" required
                   placeholder="Contoh: +1 untuk tambah, -1 untuk kurang"
                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
            <textarea v-model="adjustmentForm.description" required
                      placeholder="Alasan penyesuaian saldo cuti..."
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      rows="3"></textarea>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showAdjustmentModal = false" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
              Batal
            </button>
            <button type="submit" :disabled="isProcessing"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-400">
              <i v-if="isProcessing" class="fa-solid fa-spinner fa-spin mr-1"></i>
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Use Leave Modal -->
    <div v-if="showUseLeaveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Penggunaan Cuti</h3>
            <button @click="showUseLeaveModal = false" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="processUseLeave" class="p-6">
          <div class="mb-4">
            <div class="text-sm text-gray-600 mb-2">
              <strong>Karyawan:</strong> {{ selectedUser?.nama_lengkap }}<br>
              <strong>Saldo Saat Ini:</strong> {{ selectedUser?.cuti || 0 }} hari
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Cuti</label>
            <input type="number" v-model="useLeaveForm.amount" step="0.5" min="0.5" required
                   placeholder="Jumlah hari cuti yang digunakan"
                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
            <textarea v-model="useLeaveForm.description" required
                      placeholder="Alasan penggunaan cuti..."
                      class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      rows="3"></textarea>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" @click="showUseLeaveModal = false" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300">
              Batal
            </button>
            <button type="submit" :disabled="isProcessing"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:bg-gray-400">
              <i v-if="isProcessing" class="fa-solid fa-spinner fa-spin mr-1"></i>
              Pakai Cuti
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- History Modal -->
    <div v-if="showHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">
            <i class="fa-solid fa-history mr-2"></i>
            History Cuti - {{ selectedUser?.nama_lengkap }}
          </h3>
          <button @click="showHistoryModal = false" class="text-gray-500 hover:text-gray-700">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        
        <div class="p-6 max-h-[70vh] overflow-y-auto">
          <div v-if="leaveHistory.length === 0" class="text-center py-8 text-gray-500">
            <i class="fa-solid fa-inbox text-4xl mb-4"></i>
            <p>Belum ada transaksi cuti</p>
          </div>
          
          <div v-else class="space-y-4">
            <div v-for="transaction in leaveHistory" :key="transaction.id" 
                 class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0">
                    <div v-if="transaction.type === 'credit'" 
                         class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                      <i class="fa-solid fa-plus text-green-600"></i>
                    </div>
                    <div v-else-if="transaction.type === 'usage'" 
                         class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                      <i class="fa-solid fa-minus text-red-600"></i>
                    </div>
                    <div v-else-if="transaction.type === 'adjustment'" 
                         class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                      <i class="fa-solid fa-edit text-blue-600"></i>
                    </div>
                    <div v-else-if="transaction.type === 'burning'" 
                         class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                      <i class="fa-solid fa-fire text-orange-600"></i>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">
                      {{ getTransactionTypeLabel(transaction.type) }}
                    </p>
                    <p class="text-sm text-gray-500">
                      {{ formatDate(transaction.transaction_date) }}
                    </p>
                    <p v-if="transaction.description" class="text-sm text-gray-600 mt-1">
                      {{ transaction.description }}
                    </p>
                  </div>
                </div>
                <div class="text-right">
                  <p :class="getAmountClass(transaction.type, transaction.amount)" 
                     class="font-semibold">
                    {{ transaction.amount > 0 ? '+' : '' }}{{ transaction.amount }} hari
                  </p>
                  <p class="text-sm text-gray-500">
                    Saldo: {{ transaction.current_balance }} hari
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <button type="button" @click="showHistoryModal = false" 
                  class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  users: Object,
  filters: Object
});

// Reactive data
const filters = ref({
  search: props.filters?.search || '',
  per_page: props.filters?.per_page || 15
});

// Modal states
const showStatsModal = ref(false);
const showMonthlyCreditModal = ref(false);
const showBurningModal = ref(false);
const showAdjustmentModal = ref(false);
const showUseLeaveModal = ref(false);
const showHistoryModal = ref(false);

// Form data
const statistics = ref(null);
const selectedUser = ref(null);
const isProcessing = ref(false);
const leaveHistory = ref([]);

const monthlyCreditForm = reactive({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1
});

const burningForm = reactive({
  year: new Date().getFullYear()
});

const adjustmentForm = reactive({
  user_id: null,
  amount: null,
  description: ''
});

const useLeaveForm = reactive({
  user_id: null,
  amount: null,
  description: ''
});

const months = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// Methods
function applyFilters() {
  router.get(route('leave-management.index'), filters.value, {
    preserveState: true,
    replace: true
  });
}

function resetFilters() {
  filters.value = {
    search: '',
    per_page: 15
  };
  applyFilters();
}

function changePage(page) {
  if (page >= 1 && page <= props.users.last_page) {
    router.get(route('leave-management.index'), { ...filters.value, page }, {
      preserveState: true,
      replace: true
    });
  }
}

function getVisiblePages() {
  const current = props.users.current_page;
  const last = props.users.last_page;
  const pages = [];
  
  const start = Math.max(1, current - 2);
  const end = Math.min(last, current + 2);
  
  for (let i = start; i <= end; i++) {
    pages.push(i);
  }
  
  return pages;
}

function getInitials(name) {
  if (!name) return '?';
  return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().substring(0, 2);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function getLeaveBalanceClass(balance) {
  if (balance >= 10) return 'bg-green-100 text-green-800';
  if (balance >= 5) return 'bg-yellow-100 text-yellow-800';
  if (balance >= 1) return 'bg-orange-100 text-orange-800';
  return 'bg-red-100 text-red-800';
}

function getTransactionTypeLabel(type) {
  const labels = {
    'credit': 'Kredit Cuti',
    'usage': 'Penggunaan Cuti',
    'adjustment': 'Penyesuaian',
    'burning': 'Burning Cuti'
  };
  return labels[type] || type;
}

function getAmountClass(type, amount) {
  if (type === 'credit' || (type === 'adjustment' && amount > 0)) {
    return 'text-green-600';
  } else if (type === 'usage' || (type === 'adjustment' && amount < 0)) {
    return 'text-red-600';
  } else if (type === 'burning') {
    return 'text-orange-600';
  }
  return 'text-gray-600';
}

async function showStatistics() {
  try {
    const response = await axios.get(route('leave-management.statistics'));
    statistics.value = response.data;
    showStatsModal.value = true;
  } catch (error) {
    Swal.fire('Error', 'Gagal mengambil statistik', 'error');
  }
}

async function showHistory(user) {
  selectedUser.value = user;
  isProcessing.value = true;
  try {
    const response = await axios.get(route('leave-management.history', user.id));
    if (response.data.success) {
      leaveHistory.value = response.data.data;
      showHistoryModal.value = true;
    } else {
      Swal.fire('Error', response.data.message || 'Gagal mengambil data history cuti', 'error');
    }
  } catch (error) {
    console.error('Error fetching leave history:', error);
    Swal.fire('Error', 'Gagal mengambil data history cuti', 'error');
  } finally {
    isProcessing.value = false;
  }
}

function openAdjustmentModal(user) {
  selectedUser.value = user;
  adjustmentForm.user_id = user.id;
  adjustmentForm.amount = null;
  adjustmentForm.description = '';
  showAdjustmentModal.value = true;
}

function openUseLeaveModal(user) {
  selectedUser.value = user;
  useLeaveForm.user_id = user.id;
  useLeaveForm.amount = null;
  useLeaveForm.description = '';
  showUseLeaveModal.value = true;
}

async function processMonthlyCredit() {
  isProcessing.value = true;
  try {
    const response = await axios.post(route('leave-management.process-monthly-credit'), monthlyCreditForm);
    
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success').then(() => {
        router.reload();
      });
    } else {
      Swal.fire('Error', response.data.message, 'error');
    }
  } catch (error) {
    Swal.fire('Error', 'Terjadi kesalahan saat memproses kredit bulanan', 'error');
  } finally {
    isProcessing.value = false;
    showMonthlyCreditModal.value = false;
  }
}

async function processBurning() {
  isProcessing.value = true;
  try {
    const response = await axios.post(route('leave-management.process-burning'), burningForm);
    
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success').then(() => {
        router.reload();
      });
    } else {
      Swal.fire('Error', response.data.message, 'error');
    }
  } catch (error) {
    Swal.fire('Error', 'Terjadi kesalahan saat memproses burning', 'error');
  } finally {
    isProcessing.value = false;
    showBurningModal.value = false;
  }
}

async function processAdjustment() {
  isProcessing.value = true;
  try {
    const response = await axios.post(route('leave-management.manual-adjustment'), adjustmentForm);
    
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success').then(() => {
        router.reload();
      });
    } else {
      Swal.fire('Error', response.data.message, 'error');
    }
  } catch (error) {
    Swal.fire('Error', 'Terjadi kesalahan saat menyesuaikan saldo', 'error');
  } finally {
    isProcessing.value = false;
    showAdjustmentModal.value = false;
  }
}

async function processUseLeave() {
  isProcessing.value = true;
  try {
    const response = await axios.post(route('leave-management.use-leave'), useLeaveForm);
    
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success').then(() => {
        router.reload();
      });
    } else {
      Swal.fire('Error', response.data.message, 'error');
    }
  } catch (error) {
    Swal.fire('Error', 'Terjadi kesalahan saat menggunakan cuti', 'error');
  } finally {
    isProcessing.value = false;
    showUseLeaveModal.value = false;
  }
}
</script>
