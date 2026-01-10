<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-book"></i> Buku Bank
        </h1>
        <div class="flex gap-3">
          <button @click="exportReport" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-file-excel mr-2"></i> Export Report
          </button>
          <button @click="goToCreatePage" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Tambah Entri
          </button>
        </div>
      </div>
      
      <!-- Filters -->
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <div class="relative" @click.stop>
          <div class="relative">
            <input 
              type="text" 
              v-model="bankSearchInput"
              @focus="showBankDropdown = true"
              @blur="handleBankBlur"
              @input="handleBankInput"
              :placeholder="selectedBankName || 'Cari atau pilih bank...'"
              class="w-80 px-4 py-2 pr-10 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            />
            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
          </div>
          <!-- Dropdown Bank -->
          <div 
            v-if="showBankDropdown" 
            class="absolute z-50 mt-1 w-80 bg-white border border-gray-300 rounded-xl shadow-lg max-h-60 overflow-y-auto"
            @mousedown.prevent
          >
            <div class="p-2 border-b border-gray-200 sticky top-0 bg-white">
              <input 
                type="text" 
                v-model="bankSearch"
                @input="filterBankAccounts"
                @focus="keepDropdownOpen = true"
                @blur="keepDropdownOpen = false"
                placeholder="Cari bank..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
              />
            </div>
            <div class="py-1">
              <div 
                @mousedown.prevent
                @click="selectBank('')"
                :class="['px-4 py-2 cursor-pointer hover:bg-blue-50', filters.bank_account_id === '' ? 'bg-blue-100 font-semibold' : '']"
              >
                Semua Bank
              </div>
              <div 
                v-for="bank in filteredBankAccounts" 
                :key="bank.id"
                @mousedown.prevent
                @click="selectBank(bank.id)"
                :class="['px-4 py-2 cursor-pointer hover:bg-blue-50', filters.bank_account_id == bank.id ? 'bg-blue-100 font-semibold' : '']"
              >
                {{ bank.bank_name }} - {{ bank.account_number }} ({{ bank.outlet_name }})
              </div>
              <div v-if="filteredBankAccounts.length === 0" class="px-4 py-2 text-gray-500 text-sm">
                Bank tidak ditemukan
              </div>
            </div>
          </div>
        </div>
        
        <input 
          type="date" 
          v-model="filters.date_from" 
          @change="onFilterChange" 
          class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" 
          placeholder="Dari Tanggal" 
        />
        
        <input 
          type="date" 
          v-model="filters.date_to" 
          @change="onFilterChange" 
          class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" 
          placeholder="Sampai Tanggal" 
        />
        
        <select v-model="filters.transaction_type" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Tipe</option>
          <option value="credit">Credit (Masuk)</option>
          <option value="debit">Debit (Keluar)</option>
        </select>
        
        <select v-model="filters.per_page" @change="onFilterChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="20">20 per halaman</option>
          <option value="50">50 per halaman</option>
          <option value="100">100 per halaman</option>
        </select>
      </div>
      
      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-100 to-blue-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Bank</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tipe</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Keterangan</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Jumlah</th>
              <th class="px-6 py-3 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Saldo</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Referensi</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Created By</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="!bankBooks.data || !bankBooks.data.length">
              <td colspan="9" class="text-center py-10 text-gray-400">Belum ada data buku bank.</td>
            </tr>
            <tr v-for="entry in bankBooks.data" :key="entry.id" class="hover:bg-blue-50 transition">
              <td class="px-6 py-3">
                <div class="text-sm font-medium text-gray-900">{{ formatDate(entry.transaction_date) }}</div>
                <div class="text-xs text-gray-500">{{ formatTime(entry.created_at || entry.updated_at) }}</div>
              </td>
              <td class="px-6 py-3">
                <div class="text-sm text-gray-900">{{ entry.bank_account?.bank_name }}</div>
                <div class="text-xs text-gray-500">{{ entry.bank_account?.account_number }}</div>
                <div class="text-xs text-gray-400">{{ entry.bank_account?.outlet?.nama_outlet || 'Head Office' }}</div>
              </td>
              <td class="px-6 py-3 whitespace-nowrap">
                <span 
                  :class="entry.transaction_type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                >
                  {{ entry.transaction_type === 'credit' ? 'Credit' : 'Debit' }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="text-sm text-gray-900">{{ entry.description || '-' }}</div>
              </td>
              <td class="px-6 py-3 text-right whitespace-nowrap">
                <span 
                  :class="entry.transaction_type === 'credit' ? 'text-green-600' : 'text-red-600'"
                  class="text-sm font-semibold"
                >
                  {{ entry.transaction_type === 'credit' ? '+' : '-' }}{{ formatCurrency(entry.amount) }}
                </span>
              </td>
              <td class="px-6 py-3 text-right whitespace-nowrap">
                <span class="text-sm font-bold text-blue-600">{{ formatCurrency(entry.balance) }}</span>
              </td>
              <td class="px-6 py-3">
                <div v-if="entry.reference_type" class="text-xs">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    {{ entry.reference_type }} #{{ entry.reference_id }}
                  </span>
                </div>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-6 py-3">
                <div v-if="entry.kasir_name" class="text-sm text-gray-900">
                  <i class="fa-solid fa-user mr-1 text-blue-500"></i>
                  {{ entry.kasir_name }}
                </div>
                <div v-else-if="entry.creator" class="text-sm text-gray-900">
                  <i class="fa-solid fa-user mr-1 text-blue-500"></i>
                  {{ entry.creator?.nama_lengkap || entry.creator?.nama_panggilan || entry.creator?.email || '-' }}
                </div>
                <span v-else class="text-gray-400 text-sm">-</span>
              </td>
              <td class="px-6 py-3 whitespace-nowrap">
                <div class="flex gap-2">
                  <button @click="viewEntry(entry)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button @click="editEntry(entry)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-pencil-alt mr-1"></i> Edit
                  </button>
                  <button @click="deleteEntry(entry)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa fa-trash mr-1"></i> Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div v-if="bankBooks.links && bankBooks.links.length > 3" class="mt-4 flex justify-center">
        <div class="flex gap-1">
          <button
            v-for="(link, index) in bankBooks.links"
            :key="index"
            @click="goToPage(link.url)"
            :disabled="!link.url"
            :class="[
              'px-3 py-2 rounded-lg text-sm font-medium transition',
              link.active 
                ? 'bg-blue-600 text-white' 
                : link.url 
                  ? 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300' 
                  : 'bg-gray-100 text-gray-400 cursor-not-allowed'
            ]"
            v-html="link.label"
          ></button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  bankBooks: Object,
  bankAccounts: Array,
  filters: Object,
});

const filters = ref({
  bank_account_id: props.filters?.bank_account_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  transaction_type: props.filters?.transaction_type || '',
  per_page: props.filters?.per_page || 20,
});

const bankSearch = ref('');
const bankSearchInput = ref('');
const showBankDropdown = ref(false);
const keepDropdownOpen = ref(false);

const selectedBankName = computed(() => {
  if (!filters.value.bank_account_id) {
    return '';
  }
  const bank = props.bankAccounts.find(b => b.id == filters.value.bank_account_id);
  return bank ? `${bank.bank_name} - ${bank.account_number} (${bank.outlet_name})` : '';
});

const filteredBankAccounts = computed(() => {
  if (!bankSearch.value) {
    return props.bankAccounts;
  }
  const searchLower = bankSearch.value.toLowerCase();
  return props.bankAccounts.filter(bank => {
    const bankName = (bank.bank_name || '').toLowerCase();
    const accountNumber = (bank.account_number || '').toLowerCase();
    const accountName = (bank.account_name || '').toLowerCase();
    const outletName = (bank.outlet_name || '').toLowerCase();
    return bankName.includes(searchLower) || 
           accountNumber.includes(searchLower) || 
           accountName.includes(searchLower) ||
           outletName.includes(searchLower);
  });
});

const selectBank = (bankId) => {
  filters.value.bank_account_id = bankId;
  bankSearchInput.value = bankId ? selectedBankName.value : '';
  bankSearch.value = '';
  showBankDropdown.value = false;
  keepDropdownOpen.value = false;
  onFilterChange();
};

const handleBankBlur = () => {
  // Delay to allow click event to fire first
  setTimeout(() => {
    if (!keepDropdownOpen.value) {
      showBankDropdown.value = false;
      bankSearchInput.value = selectedBankName.value;
      bankSearch.value = '';
    }
  }, 200);
};

const handleBankInput = () => {
  // When typing in main input, show dropdown and sync search
  showBankDropdown.value = true;
  bankSearch.value = bankSearchInput.value;
};

const filterBankAccounts = () => {
  // Keep dropdown open when searching
  showBankDropdown.value = true;
};

function onFilterChange() {
  router.get('/bank-books', filters.value, {
    preserveState: true,
    replace: true,
  });
}

function goToPage(url) {
  if (!url) return;
  
  const params = { ...filters.value };
  const urlObj = new URL(url);
  const page = urlObj.searchParams.get('page');
  if (page) {
    params.page = page;
  }
  
  router.get('/bank-books', params, {
    preserveState: false,
    preserveScroll: false,
    replace: true,
  });
}

function goToCreatePage() {
  router.visit('/bank-books/create');
}

function viewEntry(entry) {
  router.visit(`/bank-books/${entry.id}`);
}

function editEntry(entry) {
  router.visit(`/bank-books/${entry.id}/edit`);
}

async function deleteEntry(entry) {
  const result = await Swal.fire({
    title: 'Hapus Entri?',
    text: 'Yakin ingin menghapus entri ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    router.delete(`/bank-books/${entry.id}`, {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Entri berhasil dihapus',
          timer: 1500,
          showConfirmButton: false,
        });
      },
      onError: () => {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: 'Gagal menghapus entri',
        });
      },
    });
  }
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function formatCurrency(amount) {
  if (!amount) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
}

function exportReport() {
  // Build export URL with current filters
  const params = new URLSearchParams();
  if (filters.value.bank_account_id) {
    params.append('bank_account_id', filters.value.bank_account_id);
  }
  if (filters.value.date_from) {
    params.append('date_from', filters.value.date_from);
  }
  if (filters.value.date_to) {
    params.append('date_to', filters.value.date_to);
  }
  if (filters.value.transaction_type) {
    params.append('transaction_type', filters.value.transaction_type);
  }
  
  const url = `/bank-books/export${params.toString() ? '?' + params.toString() : ''}`;
  window.open(url, '_blank');
}
</script>
