<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  members: Object, // { data, links, meta }
  filters: Object,
  stats: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const pointBalanceFilter = ref(props.filters?.point_balance || '');
const perPageFilter = ref(props.filters?.per_page || '15');

// Modal state
const showTransactionModal = ref(false);
const selectedMember = ref(null);
const transactions = ref([]);
const memberStats = ref({
  total_earned: 0,
  total_redeemed: 0,
  balance: 0,
  total_earned_formatted: '0',
  total_redeemed_formatted: '0',
  balance_formatted: '0'
});
const loadingTransactions = ref(false);
const expandedTransactions = ref(new Set()); // Track which transactions are expanded

// Preferences modal state
const showPreferencesModal = ref(false);
const preferences = ref([]);
const preferencesSummary = ref({
  total_orders: 0,
  total_items: 0,
  total_spent: 0,
  total_spent_formatted: 'Rp 0',
  favorite_category: 'Tidak ada data'
});
const loadingPreferences = ref(false);
const expandedPreferences = ref(new Set()); // Track which preferences are expanded

// Activity timeline modal state
const showVoucherTimelineModal = ref(false);
const voucherTimeline = ref([]);
const voucherTimelineSummary = ref({
  total_activities: 0,
  total_point_earned: 0,
  total_point_earned_formatted: '0',
  total_point_used: 0,
  total_point_used_formatted: '0',
  total_point_transactions: 0,
  total_owned: 0,
  total_active: 0,
  total_used: 0,
  total_purchased: 0,
  total_challenges_started: 0,
  total_challenges_completed: 0,
  total_rewards_redeemed: 0,
  total_tier_changes: 0
});
const loadingVoucherTimeline = ref(false);



const debouncedSearch = debounce(() => {
  router.get('/members', {
    search: search.value,
    status: statusFilter.value,
    point_balance: pointBalanceFilter.value,
    per_page: perPageFilter.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  router.visit('/members/create');
}

function openEdit(member) {
  router.visit(`/members/${member.id}/edit`);
}

function openShow(member) {
  router.visit(`/members/${member.id}`);
}



async function toggleStatus(member) {
  // Support both old format (status_aktif: '1'/'0') and new format (is_active: true/false)
  const isActive = member.status_aktif === '1' || member.status_aktif === 1 || member.is_active === true;
  const action = isActive ? 'nonaktifkan' : 'aktifkan';
  const result = await Swal.fire({
    title: `${action.charAt(0).toUpperCase() + action.slice(1)} Member?`,
    text: `Yakin ingin ${action} member "${member.name || member.nama_lengkap}"?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  router.patch(route('members.toggle-status', member.id), {}, {
    onSuccess: () => {
      Swal.fire('Berhasil', `Member berhasil ${action}!`, 'success');
    },
  });
}



function reload() {
  router.reload({ preserveState: true, replace: true });
}

function changeSort(sortField) {
  const currentSort = props.filters?.sort || 'created_at';
  const currentDirection = props.filters?.direction || 'desc';
  
  let newDirection = 'desc';
  if (currentSort === sortField) {
    newDirection = currentDirection === 'desc' ? 'asc' : 'desc';
  }
  
  router.get('/members', {
    search: search.value,
    status: statusFilter.value,
    point_balance: pointBalanceFilter.value,
    per_page: perPageFilter.value,
    sort: sortField,
    direction: newDirection,
  }, { preserveState: true, replace: true });
}

watch([statusFilter, pointBalanceFilter, perPageFilter], () => {
  router.get('/members', {
    search: search.value,
    status: statusFilter.value,
    point_balance: pointBalanceFilter.value,
    per_page: perPageFilter.value,
  }, { preserveState: true, replace: true });
});



/**
 * Format angka dengan pemisah ribuan menggunakan format Indonesia
 * Contoh: 125836 -> 125.836
 * @param {number} number - Angka yang akan diformat
 * @returns {string} Angka yang sudah diformat
 */
function formatNumber(number) {
  return number.toLocaleString('id-ID');
}

async function changePassword(member) {
  try {
    const { value: formValues } = await Swal.fire({
      title: 'Ubah Password Member',
      html: `
        <div class="text-left">
          <p class="mb-4 text-sm text-gray-600">Member: <strong>${member.name}</strong><br><small>${member.email}</small></p>
          <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
            <input id="swal-password" type="password" class="swal2-input" placeholder="Masukkan password baru" minlength="6" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
            <input id="swal-password-confirm" type="password" class="swal2-input" placeholder="Konfirmasi password baru" minlength="6" required>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonColor: '#f97316',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Ubah Password',
      cancelButtonText: 'Batal',
      preConfirm: () => {
        const password = document.getElementById('swal-password').value;
        const passwordConfirm = document.getElementById('swal-password-confirm').value;
        
        if (!password || password.length < 6) {
          Swal.showValidationMessage('Password minimal 6 karakter');
          return false;
        }
        
        if (password !== passwordConfirm) {
          Swal.showValidationMessage('Konfirmasi password tidak cocok');
          return false;
        }
        
        return { password, password_confirmation: passwordConfirm };
      }
    });
    
    if (formValues) {
      const response = await axios.patch(`/members/${member.id}/change-password`, {
        password: formValues.password,
        password_confirmation: formValues.password_confirmation
      });
      
      if (response.data.success) {
        Swal.fire({
          title: 'Berhasil!',
          text: response.data.message || 'Password berhasil diubah',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
      } else {
        Swal.fire({
          title: 'Gagal',
          text: response.data.message || 'Gagal mengubah password',
          icon: 'error'
        });
      }
    }
  } catch (error) {
    console.error('Error changing password:', error);
    let errorMessage = 'Terjadi kesalahan saat mengubah password';
    
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      errorMessage = Object.values(errors).flat().join(', ');
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    }
    
    Swal.fire({
      title: 'Error',
      text: errorMessage,
      icon: 'error'
    });
  }
}

async function verifyEmail(member) {
  try {
    const result = await Swal.fire({
      title: 'Verifikasi Email Manual',
      html: `Apakah Anda yakin ingin memverifikasi email untuk member:<br><strong>${member.name}</strong><br><small>${member.email}</small>?<br><br><span class="text-sm text-gray-600">Ini akan langsung memverifikasi email tanpa perlu token verifikasi.</span>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#10b981',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'Ya, Verifikasi',
      cancelButtonText: 'Batal'
    });
    
    if (result.isConfirmed) {
      const response = await axios.patch(`/members/${member.id}/verify-email`);
      
      if (response.data.success) {
        Swal.fire({
          title: 'Berhasil!',
          text: response.data.message || 'Email berhasil diverifikasi',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
        
        // Reload data to reflect changes
        router.reload({ only: ['members'] });
      } else {
        Swal.fire({
          title: 'Gagal',
          text: response.data.message || 'Gagal memverifikasi email',
          icon: 'error'
        });
      }
    }
  } catch (error) {
    console.error('Error verifying email:', error);
    Swal.fire({
      title: 'Error',
      text: error.response?.data?.message || 'Terjadi kesalahan saat memverifikasi email',
      icon: 'error'
    });
  }
}

async function viewTransactions(member) {
  selectedMember.value = member;
  showTransactionModal.value = true;
  loadingTransactions.value = true;
  
  try {
    const response = await axios.get(`/api/members/${member.id}/transactions`);
    
    if (response.data.status === 'success') {
      transactions.value = response.data.transactions || [];
      memberStats.value = {
        total_earned: response.data.stats.total_earned || 0,
        total_redeemed: response.data.stats.total_redeemed || 0,
        balance: response.data.stats.balance || 0,
        total_earned_formatted: response.data.stats.total_earned_formatted || '0',
        total_redeemed_formatted: response.data.stats.total_redeemed_formatted || '0',
        balance_formatted: response.data.stats.balance_formatted || '0'
      };
    } else {
      transactions.value = [];
      memberStats.value = {
        total_earned: 0,
        total_redeemed: 0,
        balance: 0,
        total_earned_formatted: '0',
        total_redeemed_formatted: '0',
        balance_formatted: '0'
      };
    }
  } catch (error) {
    console.error('Error loading transactions:', error);
    transactions.value = [];
    memberStats.value = {
      total_earned: 0,
      total_redeemed: 0,
      balance: 0,
      total_earned_formatted: '0',
      total_redeemed_formatted: '0',
      balance_formatted: '0'
    };
  } finally {
    loadingTransactions.value = false;
  }
}

function closeTransactionModal() {
  showTransactionModal.value = false;
  selectedMember.value = null;
  transactions.value = [];
  memberStats.value = {
    total_earned: 0,
    total_redeemed: 0,
    balance: 0,
    total_earned_formatted: '0',
    total_redeemed_formatted: '0',
    balance_formatted: '0'
  };
  expandedTransactions.value.clear(); // Reset expanded transactions
}

function toggleTransactionExpansion(transactionId) {
  if (expandedTransactions.value.has(transactionId)) {
    expandedTransactions.value.delete(transactionId);
  } else {
    expandedTransactions.value.add(transactionId);
  }
}

function isTransactionExpanded(transactionId) {
  return expandedTransactions.value.has(transactionId);
}

async function viewVoucherTimeline(member) {
  selectedMember.value = member;
  showVoucherTimelineModal.value = true;
  loadingVoucherTimeline.value = true;
  
  try {
    const response = await axios.get(`/api/members/${member.id}/voucher-timeline`);
    
    if (response.data.status === 'success') {
      voucherTimeline.value = response.data.timeline || [];
      voucherTimelineSummary.value = response.data.summary || {
        total_owned: 0,
        total_active: 0,
        total_used: 0,
        total_purchased: 0
      };
    } else {
      Swal.fire('Error', response.data.message || 'Gagal mengambil data timeline aktivitas', 'error');
    }
  } catch (error) {
    console.error('Error fetching activity timeline:', error);
    Swal.fire('Error', 'Gagal mengambil data timeline aktivitas', 'error');
  } finally {
    loadingVoucherTimeline.value = false;
  }
}

function closeVoucherTimelineModal() {
  showVoucherTimelineModal.value = false;
  selectedMember.value = null;
  voucherTimeline.value = [];
  voucherTimelineSummary.value = {
    total_owned: 0,
    total_active: 0,
    total_used: 0,
    total_purchased: 0
  };
}

async function viewPreferences(member) {
  selectedMember.value = member;
  showPreferencesModal.value = true;
  loadingPreferences.value = true;
  
  try {
    const response = await axios.get(`/api/members/${member.id}/preferences`);
    
    if (response.data.status === 'success') {
      preferences.value = response.data.preferences || [];
      preferencesSummary.value = response.data.summary || {
        total_orders: 0,
        total_items: 0,
        total_spent: 0,
        total_spent_formatted: 'Rp 0',
        favorite_category: 'Tidak ada data'
      };
    } else {
      preferences.value = [];
      preferencesSummary.value = {
        total_orders: 0,
        total_items: 0,
        total_spent: 0,
        total_spent_formatted: 'Rp 0',
        favorite_category: 'Tidak ada data'
      };
    }
  } catch (error) {
    console.error('Error loading preferences:', error);
    preferences.value = [];
    preferencesSummary.value = {
      total_orders: 0,
      total_items: 0,
      total_spent: 0,
      total_spent_formatted: 'Rp 0',
      favorite_category: 'Tidak ada data'
    };
  } finally {
    loadingPreferences.value = false;
  }
}

function closePreferencesModal() {
  showPreferencesModal.value = false;
  selectedMember.value = null;
  preferences.value = [];
  preferencesSummary.value = {
    total_orders: 0,
    total_items: 0,
    total_spent: 0,
    total_spent_formatted: 'Rp 0',
    favorite_category: 'Tidak ada data'
  };
  expandedPreferences.value.clear(); // Reset expanded preferences
}

function togglePreferenceExpansion(index) {
  if (expandedPreferences.value.has(index)) {
    expandedPreferences.value.delete(index);
  } else {
    expandedPreferences.value.add(index);
  }
}

function isPreferenceExpanded(index) {
  return expandedPreferences.value.has(index);
}

function getTimelineIcon(type) {
  const iconMap = {
    // Voucher activities
    'owned': 'fa-solid fa-ticket',
    'purchased': 'fa-solid fa-shopping-cart',
    'redeemed': 'fa-solid fa-check-circle',
    
    // Challenge activities
    'challenge_start': 'fa-solid fa-flag',
    'challenge_complete': 'fa-solid fa-trophy',
    'challenge_claim': 'fa-solid fa-gift',
    'challenge_redeem': 'fa-solid fa-hand-holding-heart',
    
    // Reward activities
    'reward_redeem': 'fa-solid fa-gift',
    
    // Tier changes
    'tier_upgrade': 'fa-solid fa-arrow-trend-up',
    'tier_downgrade': 'fa-solid fa-arrow-trend-down',
    
    // Point activities
    'point_earned_purchase': 'fa-solid fa-coins',
    'point_earned_registration': 'fa-solid fa-user-plus',
    'point_earned_bonus': 'fa-solid fa-star',
    'point_earned_referral': 'fa-solid fa-users',
    'point_adjustment': 'fa-solid fa-sliders',
    'point_transaction': 'fa-solid fa-exchange-alt',
    
    // Default
    'default': 'fa-solid fa-circle'
  };
  
  return iconMap[type] || iconMap['default'];
}

function formatDate(dateString) {
  if (!dateString) return '-';
  
  const date = new Date(dateString);
  if (isNaN(date.getTime())) {
    return 'Tanggal tidak valid';
  }
  
  return date.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}
</script>

<template>
  <AppLayout title="Data Member">
    <div class="w-full py-2 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-users text-purple-500"></i> Data Member
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Member Baru
        </button>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Member -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
              <i class="fa-solid fa-users text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Total Member</p>
              <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.total_members) }}</p>
            </div>
          </div>
        </div>

        <!-- Member Aktif -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
              <i class="fa-solid fa-user-check text-green-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Member Aktif</p>
              <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.active_members) }}</p>
            </div>
          </div>
        </div>

        <!-- Member Nonaktif -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-orange-500">
          <div class="flex items-center">
            <div class="p-2 bg-orange-100 rounded-lg">
              <i class="fa-solid fa-user-clock text-orange-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Member Nonaktif</p>
              <p class="text-2xl font-bold text-gray-800">{{ formatNumber(stats.inactive_members) }}</p>
            </div>
          </div>
        </div>

        <!-- Total Saldo Point -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-lg">
              <i class="fa-solid fa-coins text-purple-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Total Saldo Point</p>
              <p class="text-2xl font-bold text-gray-800">{{ stats.total_point_balance_formatted }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Point Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Point Earned -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
              <i class="fa-solid fa-plus-circle text-green-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Total Point Diperoleh</p>
              <p class="text-xl font-bold text-gray-800">{{ stats.total_point_earned_formatted }}</p>
            </div>
          </div>
        </div>

        <!-- Point Redeemed -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-red-500">
          <div class="flex items-center">
            <div class="p-2 bg-red-100 rounded-lg">
              <i class="fa-solid fa-minus-circle text-red-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Total Point Diredeem</p>
              <p class="text-xl font-bold text-gray-800">{{ stats.total_point_redeemed_formatted }}</p>
            </div>
          </div>
        </div>

        <!-- Members with Points -->
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
              <i class="fa-solid fa-user-coins text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Member dengan Point</p>
              <p class="text-xl font-bold text-gray-800">{{ formatNumber(stats.members_with_points) }}</p>
            </div>
          </div>
        </div>
      </div>

             <!-- Filters -->
       <div class="mb-4 flex gap-4 flex-wrap">
         <select v-model="statusFilter" class="form-input rounded-xl">
           <option value="">Semua Status</option>
           <option value="active">Aktif</option>
           <option value="inactive">Tidak Aktif</option>
         </select>

         <select v-model="pointBalanceFilter" class="form-input rounded-xl">
           <option value="">Semua Saldo Point</option>
           <option value="positive">Saldo Positif</option>
           <option value="negative">Saldo Negatif</option>
           <option value="zero">Saldo Nol</option>
           <option value="high">Saldo Tinggi (≥1000)</option>
         </select>

         <select v-model="perPageFilter" class="form-input rounded-xl">
           <option value="10">10 Data</option>
           <option value="15">15 Data</option>
           <option value="25">25 Data</option>
           <option value="50">50 Data</option>
           <option value="100">100 Data</option>
         </select>

         <input
           v-model="search"
           @input="onSearchInput"
           type="text"
           placeholder="Cari ID, NIK, Nama, Email, Telepon..."
           class="flex-1 px-4 py-2 rounded-xl border border-purple-200 shadow focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
         />
       </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto w-full">
        <table class="w-full divide-y divide-purple-200">
          <thead class="bg-purple-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-purple-700 transition" @click="changeSort('member_id')">
                ID Member
                <i v-if="filters.sort === 'member_id'" :class="filters.direction === 'desc' ? 'fa-solid fa-sort-down' : 'fa-solid fa-sort-up'" class="ml-1"></i>
                <i v-else class="fa-solid fa-sort ml-1 text-purple-300"></i>
              </th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-purple-700 transition" @click="changeSort('name')">
                Informasi Member
                <i v-if="filters.sort === 'name'" :class="filters.direction === 'desc' ? 'fa-solid fa-sort-down' : 'fa-solid fa-sort-up'" class="ml-1"></i>
                <i v-else class="fa-solid fa-sort ml-1 text-purple-300"></i>
              </th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-purple-700 transition" @click="changeSort('point_balance')">
                Point & Tier
                <i v-if="filters.sort === 'point_balance'" :class="filters.direction === 'desc' ? 'fa-solid fa-sort-down' : 'fa-solid fa-sort-up'" class="ml-1"></i>
                <i v-else class="fa-solid fa-sort ml-1 text-purple-300"></i>
              </th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-purple-700 transition" @click="changeSort('total_spending')">
                Total Spending
                <i v-if="filters.sort === 'total_spending'" :class="filters.direction === 'desc' ? 'fa-solid fa-sort-down' : 'fa-solid fa-sort-up'" class="ml-1"></i>
                <i v-else class="fa-solid fa-sort ml-1 text-purple-300"></i>
              </th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-purple-700 transition" @click="changeSort('spending_last_year')">
                Spending Setahun Terakhir
                <i v-if="filters.sort === 'spending_last_year'" :class="filters.direction === 'desc' ? 'fa-solid fa-sort-down' : 'fa-solid fa-sort-up'" class="ml-1"></i>
                <i v-else class="fa-solid fa-sort ml-1 text-purple-300"></i>
              </th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-purple-700 transition" @click="changeSort('last_spending')">
                Last Spending
                <i v-if="filters.sort === 'last_spending'" :class="filters.direction === 'desc' ? 'fa-solid fa-sort-down' : 'fa-solid fa-sort-up'" class="ml-1"></i>
                <i v-else class="fa-solid fa-sort ml-1 text-purple-300"></i>
              </th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider cursor-pointer hover:bg-purple-700 transition" @click="changeSort('status_aktif')">
                Status
                <i v-if="filters.sort === 'status_aktif'" :class="filters.direction === 'desc' ? 'fa-solid fa-sort-down' : 'fa-solid fa-sort-up'" class="ml-1"></i>
                <i v-else class="fa-solid fa-sort ml-1 text-purple-300"></i>
              </th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in members.data" :key="member.id" class="hover:bg-purple-50 transition">
              <td class="px-4 py-2 whitespace-nowrap font-mono text-sm">{{ member.costumers_id }}</td>
              <td class="px-4 py-2">
                <div class="space-y-1">
                  <div class="font-semibold text-gray-900">{{ member.name }}</div>
                  <div class="text-sm text-gray-600 flex items-center gap-1">
                    <i class="fa-solid fa-envelope text-gray-400 text-xs"></i>
                    <span>{{ member.email || '-' }}</span>
                  </div>
                  <div class="text-sm text-gray-600 flex items-center gap-1">
                    <i class="fa-solid fa-phone text-gray-400 text-xs"></i>
                    <span>{{ member.telepon || '-' }}</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="space-y-2">
                  <div>
                    <span :class="[
                      'font-mono font-semibold text-sm px-2 py-1 rounded-full inline-block',
                      member.point_balance > 0 
                        ? 'bg-green-100 text-green-800' 
                        : member.point_balance < 0 
                        ? 'bg-red-100 text-red-800'
                        : 'bg-gray-100 text-gray-800'
                    ]">
                      {{ member.point_balance_formatted }}
                    </span>
                  </div>
                  <div>
                    <span :class="[
                      'font-semibold text-xs px-2 py-1 rounded-full uppercase inline-block',
                      member.tier === 'platinum' 
                        ? 'bg-purple-100 text-purple-800 border border-purple-300'
                        : member.tier === 'gold'
                        ? 'bg-yellow-100 text-yellow-800 border border-yellow-300'
                        : member.tier === 'silver'
                        ? 'bg-gray-100 text-gray-800 border border-gray-300'
                        : 'bg-blue-100 text-blue-800 border border-blue-300'
                    ]">
                      {{ member.tier_formatted || 'Silver' }}
                    </span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <span class="font-semibold text-sm text-blue-700">
                  {{ member.total_spending_formatted || 'Rp 0' }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <span class="font-semibold text-sm text-green-700">
                  {{ member.spending_last_year_formatted || 'Rp 0' }}
                </span>
              </td>
              <td class="px-4 py-2">
                <div class="space-y-1">
                  <div class="font-semibold text-sm text-indigo-700">
                    {{ member.last_spending_formatted || 'Rp 0' }}
                  </div>
                  <div v-if="member.last_spending_outlet" class="text-xs text-gray-600 flex items-center gap-1">
                    <i class="fa-solid fa-store text-gray-400"></i>
                    <span>{{ member.last_spending_outlet }}</span>
                  </div>
                  <div v-if="member.last_spending_date_formatted && member.last_spending_date_formatted !== '-'" class="text-xs text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-calendar text-gray-400"></i>
                    <span>{{ member.last_spending_date_formatted }}</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="flex items-center">
                  <div :class="[
                    'w-3 h-3 rounded-full mr-2',
                    member.status_aktif === '1' ? 'bg-green-500' : 'bg-orange-500'
                  ]"></div>
                  <span :class="[
                    'text-sm font-medium',
                    member.status_aktif === '1' ? 'text-green-700' : 'text-orange-700'
                  ]">
                    {{ member.status_aktif === '1' ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(member)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(member)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                                 <button @click="viewTransactions(member)" class="px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 transition" title="Transaksi & Point">
                   <i class="fa-solid fa-coins"></i>
                 </button>
                 <button @click="verifyEmail(member)" class="px-2 py-1 rounded bg-teal-100 text-teal-700 hover:bg-teal-200 transition" title="Verifikasi Email Manual" v-if="!member.email_verified_at">
                   <i class="fa-solid fa-envelope-circle-check"></i>
                 </button>
                 <button @click="changePassword(member)" class="px-2 py-1 rounded bg-orange-100 text-orange-700 hover:bg-orange-200 transition" title="Ubah Password">
                   <i class="fa-solid fa-key"></i>
                 </button>
                 <button @click="viewPreferences(member)" class="px-2 py-1 rounded bg-purple-100 text-purple-700 hover:bg-purple-200 transition" title="Menu Favorit">
                   <i class="fa-solid fa-heart"></i>
                 </button>
                 <button @click="viewVoucherTimeline(member)" class="px-2 py-1 rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200 transition" title="Timeline Aktivitas">
                   <i class="fa-solid fa-clock-rotate-left"></i>
                 </button>
                <button @click="toggleStatus(member)" :class="[
                  'px-2 py-1 rounded transition',
                  member.status_aktif === '1' 
                    ? 'bg-orange-100 text-orange-700 hover:bg-orange-200' 
                    : 'bg-green-100 text-green-700 hover:bg-green-200'
                ]" :title="member.status_aktif === '1' ? 'Nonaktifkan' : 'Aktifkan'">
                  <i :class="member.status_aktif === '1' ? 'fa-solid fa-user-slash' : 'fa-solid fa-user-check'"></i>
                </button>
              </td>
            </tr>
            <tr v-if="members.data.length === 0">
              <td colspan="9" class="text-center py-8 text-gray-400">Tidak ada data member</td>
            </tr>
          </tbody>
        </table>
      </div>

             <!-- Pagination Info & Controls -->
       <div class="mt-4 flex justify-between items-center">
         <!-- Data Info -->
         <div class="text-sm text-gray-600">
           Menampilkan {{ members.from || 0 }} - {{ members.to || 0 }} dari {{ members.total || 0 }} data member
         </div>
         
         <!-- Pagination -->
         <nav v-if="members.links && members.links.length > 3" class="inline-flex -space-x-px">
           <template v-for="(link, i) in members.links" :key="i">
             <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-purple-600 text-white' : 'bg-white text-purple-700 hover:bg-purple-50']" v-html="link.label"></button>
             <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
           </template>
         </nav>
       </div>

             <!-- Modal Transaksi & Point -->
       <div v-if="showTransactionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
         <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
           <!-- Header -->
           <div class="bg-gradient-to-r from-purple-600 to-purple-800 text-white p-6 rounded-t-2xl">
             <div class="flex justify-between items-center">
               <div>
                 <h2 class="text-xl font-bold">Transaksi & Point Member</h2>
                 <p class="text-purple-200">{{ selectedMember?.name }} ({{ selectedMember?.costumers_id }})</p>
               </div>
               <button @click="closeTransactionModal" class="text-white hover:text-purple-200 transition">
                 <i class="fa-solid fa-times text-xl"></i>
               </button>
             </div>
           </div>

           <!-- Content -->
           <div class="p-6">
             <!-- Summary Cards -->
             <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
               <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-green-100 rounded-lg">
                     <i class="fa-solid fa-plus-circle text-green-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-green-600">Total Point Diperoleh</p>
                     <p class="text-lg font-bold text-green-800">{{ memberStats.total_earned_formatted }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-red-100 rounded-lg">
                     <i class="fa-solid fa-minus-circle text-red-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-red-600">Total Point Diredeem</p>
                     <p class="text-lg font-bold text-red-800">{{ memberStats.total_redeemed_formatted }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-blue-100 rounded-lg">
                     <i class="fa-solid fa-wallet text-blue-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-blue-600">Saldo Point</p>
                     <p class="text-lg font-bold text-blue-800">{{ memberStats.balance_formatted }}</p>
                   </div>
                 </div>
               </div>
             </div>

             <!-- Transaction History -->
             <div class="bg-gray-50 rounded-xl p-4 mb-4">
               <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                 <i class="fa-solid fa-history text-purple-600"></i>
                 Riwayat Transaksi Point
               </h3>
               
               <div v-if="loadingTransactions" class="text-center py-8">
                 <i class="fa-solid fa-spinner fa-spin text-2xl text-purple-600 mb-2"></i>
                 <p class="text-gray-600">Memuat data transaksi...</p>
               </div>

               <div v-else-if="transactions.length === 0" class="text-center py-8">
                 <i class="fa-solid fa-inbox text-4xl text-gray-400 mb-2"></i>
                 <p class="text-gray-600">Tidak ada transaksi point</p>
               </div>

                               <div v-else class="space-y-3">
                  <div v-for="transaction in transactions" :key="transaction.id" class="bg-white rounded-lg border border-gray-200 hover:shadow-md transition-shadow overflow-hidden">
                    <!-- Transaction Header (Always Visible) -->
                    <div class="p-4 cursor-pointer" @click="toggleTransactionExpansion(transaction.id)">
                      <div class="flex justify-between items-start mb-3">
                        <div class="flex-1">
                          <div class="flex items-center gap-2 mb-2">
                            <span :class="[
                              'px-2 py-1 rounded-full text-xs font-semibold',
                              transaction.type === '1' 
                                ? 'bg-green-100 text-green-800' 
                                : 'bg-red-100 text-red-800'
                            ]">
                              {{ transaction.type_text }}
                            </span>
                            <span class="text-sm text-gray-500">{{ formatDate(transaction.created_at) }}</span>
                            <i :class="isTransactionExpanded(transaction.id) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="text-gray-400 text-sm"></i>
                          </div>
                          <p class="font-medium text-gray-800 mb-1">{{ transaction.description }}</p>
                          
                          <!-- Info Detail Transaksi -->
                          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-gray-600">
                            <div class="flex items-center gap-1">
                              <i class="fa-solid fa-store text-purple-500"></i>
                              <span class="font-medium">Outlet:</span>
                              <span>{{ transaction.outlet_name }}</span>
                            </div>
                            <div class="flex items-center gap-1" v-if="transaction.no_bill">
                              <i class="fa-solid fa-receipt text-blue-500"></i>
                              <span class="font-medium">No. Bill:</span>
                              <span class="font-mono">{{ transaction.no_bill }}</span>
                            </div>
                            <div class="flex items-center gap-1" v-if="transaction.jml_trans_formatted && transaction.jml_trans_formatted !== '-'">
                              <i class="fa-solid fa-money-bill-wave text-green-500"></i>
                              <span class="font-medium">Nilai Transaksi:</span>
                              <span class="font-semibold text-green-600">{{ transaction.jml_trans_formatted }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                              <i class="fa-solid fa-hashtag text-gray-400"></i>
                              <span class="font-medium">ID Transaksi:</span>
                              <span class="font-mono text-xs">{{ transaction.id }}</span>
                            </div>
                          </div>
                        </div>
                        <div class="text-right ml-4">
                          <div class="flex flex-col items-end">
                            <span :class="[
                              'text-lg font-bold',
                              transaction.type === '1' ? 'text-green-600' : 'text-red-600'
                            ]">
                              {{ transaction.type === '1' ? '+' : '-' }}{{ formatNumber(transaction.point) }}
                            </span>
                            <span class="text-xs text-gray-500">point</span>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Alamat Outlet (jika ada) -->
                      <div v-if="transaction.outlet_alamat" class="text-xs text-gray-500 border-t pt-2 mt-2">
                        <i class="fa-solid fa-map-marker-alt text-red-400"></i>
                        {{ transaction.outlet_alamat }}
                      </div>
                    </div>
                    
                    <!-- Order Details (Expandable) - Only for EARNED transactions -->
                    <div v-if="isTransactionExpanded(transaction.id) && transaction.type === '1' && transaction.order_details && transaction.order_details.length > 0" class="border-t border-gray-100 bg-gray-50">
                      <div class="p-4">
                        <h5 class="font-medium text-gray-700 mb-3 flex items-center gap-2">
                          <i class="fa-solid fa-utensils text-blue-500"></i>
                          Detail Order ({{ transaction.order_details.length }} item)
                        </h5>
                        <div class="space-y-3">
                          <div v-for="(item, itemIndex) in transaction.order_details" :key="itemIndex" class="bg-white rounded-lg p-3 border border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                              <!-- Item Info -->
                              <div class="space-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                  <i class="fa-solid fa-utensils text-blue-500"></i>
                                  <span class="font-medium">Menu:</span>
                                  <span class="font-semibold text-gray-800">{{ item.item_name }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                  <i class="fa-solid fa-hashtag text-gray-500"></i>
                                  <span class="font-medium">Qty:</span>
                                  <span class="font-semibold">{{ item.qty }}x</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                  <i class="fa-solid fa-money-bill-wave text-green-500"></i>
                                  <span class="font-medium">Harga:</span>
                                  <span>{{ item.price_formatted }}</span>
                                </div>
                              </div>
                              
                              <!-- Item Details -->
                              <div class="space-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                  <i class="fa-solid fa-calculator text-orange-500"></i>
                                  <span class="font-medium">Total:</span>
                                  <span class="font-semibold text-green-600">{{ item.total_price_formatted }}</span>
                                </div>
                              </div>
                            </div>
                            
                            <!-- Modifiers & Notes -->
                            <div class="mt-3 pt-3 border-t border-gray-100">
                              <div v-if="item.modifiers_formatted !== '-'" class="mb-2">
                                <div class="flex items-start gap-2 text-sm">
                                  <i class="fa-solid fa-tags text-indigo-500 mt-0.5"></i>
                                  <div>
                                    <span class="font-medium text-gray-700">Modifier:</span>
                                    <span class="text-indigo-600">{{ item.modifiers_formatted }}</span>
                                  </div>
                                </div>
                              </div>
                              <div v-if="item.notes" class="mb-2">
                                <div class="flex items-start gap-2 text-sm">
                                  <i class="fa-solid fa-sticky-note text-yellow-500 mt-0.5"></i>
                                  <div>
                                    <span class="font-medium text-gray-700">Catatan:</span>
                                    <span class="text-gray-600 italic">{{ item.notes }}</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- No Order Details Message -->
                    <div v-if="isTransactionExpanded(transaction.id) && transaction.type === '1' && (!transaction.order_details || transaction.order_details.length === 0)" class="border-t border-gray-100 bg-gray-50">
                      <div class="p-4 text-center">
                        <i class="fa-solid fa-info-circle text-gray-400 text-lg mb-2"></i>
                        <p class="text-gray-600 text-sm">Detail order tidak tersedia</p>
                      </div>
                    </div>
                  </div>
                </div>
             </div>
           </div>

           <!-- Footer -->
           <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end">
             <button @click="closeTransactionModal" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
               Tutup
             </button>
           </div>
         </div>
       </div>

       <!-- Modal Preferences -->
       <div v-if="showPreferencesModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
         <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
           <!-- Header -->
           <div class="bg-gradient-to-r from-pink-600 to-pink-800 text-white p-6 rounded-t-2xl">
             <div class="flex justify-between items-center">
               <div>
                 <h2 class="text-xl font-bold">Menu Favorit Member</h2>
                 <p class="text-pink-200">{{ selectedMember?.name }} ({{ selectedMember?.costumers_id }})</p>
               </div>
               <button @click="closePreferencesModal" class="text-white hover:text-pink-200 transition">
                 <i class="fa-solid fa-times text-xl"></i>
               </button>
             </div>
           </div>

           <!-- Content -->
           <div class="p-6">
             <!-- Summary Cards -->
             <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
               <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-blue-100 rounded-lg">
                     <i class="fa-solid fa-shopping-cart text-blue-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-blue-600">Total Order</p>
                     <p class="text-lg font-bold text-blue-800">{{ preferencesSummary.total_orders }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-green-100 rounded-lg">
                     <i class="fa-solid fa-utensils text-green-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-green-600">Total Item</p>
                     <p class="text-lg font-bold text-green-800">{{ preferencesSummary.total_items }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-orange-100 rounded-lg">
                     <i class="fa-solid fa-money-bill-wave text-orange-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-orange-600">Total Spent</p>
                     <p class="text-lg font-bold text-orange-800">{{ preferencesSummary.total_spent_formatted }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-purple-100 rounded-lg">
                     <i class="fa-solid fa-tags text-purple-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-purple-600">Kategori Favorit</p>
                     <p class="text-lg font-bold text-purple-800">{{ preferencesSummary.favorite_category }}</p>
                   </div>
                 </div>
               </div>
             </div>

             <!-- Menu Preferences -->
             <div class="bg-gray-50 rounded-xl p-4 mb-4">
               <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                 <i class="fa-solid fa-heart text-pink-600"></i>
                 Menu Favorit (Top 10)
               </h3>
               
               <div v-if="loadingPreferences" class="text-center py-8">
                 <i class="fa-solid fa-spinner fa-spin text-2xl text-pink-600 mb-2"></i>
                 <p class="text-gray-600">Memuat data menu favorit...</p>
               </div>

               <div v-else-if="preferences.length === 0" class="text-center py-8">
                 <i class="fa-solid fa-heart-broken text-4xl text-gray-400 mb-2"></i>
                 <p class="text-gray-600">Belum ada data menu favorit</p>
               </div>

                                               <div v-else class="space-y-3">
                   <div v-for="(pref, index) in preferences" :key="index" class="bg-white rounded-lg border border-gray-200 hover:shadow-md transition-shadow overflow-hidden">
                     <!-- Preference Header (Always Visible) -->
                     <div class="p-4 cursor-pointer" @click="togglePreferenceExpansion(index)">
                       <div class="flex justify-between items-start">
                         <div class="flex-1">
                           <div class="flex items-center gap-3 mb-2">
                             <span class="bg-pink-100 text-pink-800 px-2 py-1 rounded-full text-xs font-semibold">
                               #{{ index + 1 }}
                             </span>
                             <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold">
                               {{ pref.menu_category }}
                             </span>
                             <i :class="isPreferenceExpanded(index) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="text-gray-400 text-sm"></i>
                           </div>
                           <h4 class="font-bold text-gray-800 text-lg mb-2">{{ pref.menu_name }}</h4>
                           
                           <!-- Menu Details -->
                           <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm text-gray-600">
                             <div class="flex items-center gap-1">
                               <i class="fa-solid fa-shopping-cart text-blue-500"></i>
                               <span class="font-medium">Order Count:</span>
                               <span class="font-semibold text-blue-600">{{ pref.order_count }}x</span>
                             </div>
                             <div class="flex items-center gap-1">
                               <i class="fa-solid fa-utensils text-green-500"></i>
                               <span class="font-medium">Total Qty:</span>
                               <span class="font-semibold text-green-600">{{ pref.total_qty }}</span>
                             </div>
                             <div class="flex items-center gap-1">
                               <i class="fa-solid fa-money-bill-wave text-orange-500"></i>
                               <span class="font-medium">Total Spent:</span>
                               <span class="font-semibold text-orange-600">{{ pref.total_spent_formatted }}</span>
                             </div>
                           </div>
                           
                           <div class="mt-2 text-xs text-gray-500">
                             <i class="fa-solid fa-clock text-gray-400"></i>
                             Terakhir dipesan: {{ pref.last_ordered_formatted }}
                           </div>
                         </div>
                         <div class="text-right ml-4">
                           <div class="text-lg font-bold text-pink-600">
                             {{ pref.menu_price_formatted }}
                           </div>
                           <div class="text-xs text-gray-500">per item</div>
                         </div>
                       </div>
                     </div>

                     <!-- Modifier Details (Expandable) -->
                     <div v-if="isPreferenceExpanded(index)" class="bg-gray-50 p-4 border-t border-gray-200">
                       <h5 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                         <i class="fa-solid fa-tags text-indigo-500"></i>
                         Detail Modifier & Catatan:
                       </h5>
                       
                       <!-- Modifiers -->
                       <div v-if="pref.modifiers && pref.modifiers.length > 0" class="mb-4">
                         <h6 class="font-medium text-gray-600 mb-2">Modifier yang Sering Dipilih:</h6>
                         <div class="space-y-3">
                           <div v-for="(modifier, modIndex) in pref.modifiers" :key="modIndex" class="bg-white p-3 rounded-lg border border-gray-200">
                             <div class="flex items-center gap-2 mb-2">
                               <i class="fa-solid fa-tag text-indigo-500"></i>
                               <span class="font-medium text-gray-800">{{ modifier.name }}</span>
                               <span class="bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full text-xs font-semibold">
                                 {{ modifier.count }}x dipilih
                               </span>
                             </div>
                             
                             <!-- Modifier Options -->
                             <div v-if="modifier.options && Object.keys(modifier.options).length > 0" class="ml-6">
                               <div class="text-sm text-gray-600 mb-1">Opsi yang dipilih:</div>
                               <div class="space-y-1">
                                 <div v-for="(option, optKey) in modifier.options" :key="optKey" class="flex items-center gap-2 text-xs">
                                   <i class="fa-solid fa-circle text-gray-400 text-xs"></i>
                                   <span class="font-medium">{{ option.name }}</span>
                                   <span class="text-gray-500">({{ option.count }}x)</span>
                                   <span v-if="option.price > 0" class="text-green-600 font-medium">
                                     +Rp {{ formatNumber(option.price) }}
                                   </span>
                                 </div>
                               </div>
                             </div>
                           </div>
                         </div>
                       </div>
                       
                       <!-- Notes -->
                       <div v-if="pref.notes && pref.notes.length > 0" class="mb-4">
                         <h6 class="font-medium text-gray-600 mb-2">Catatan yang Sering Ditambahkan:</h6>
                         <div class="space-y-2">
                           <div v-for="(note, noteIndex) in pref.notes" :key="noteIndex" class="bg-white p-3 rounded-lg border border-gray-200">
                             <div class="flex items-start gap-2">
                               <i class="fa-solid fa-sticky-note text-yellow-500 mt-0.5"></i>
                               <span class="text-gray-700 italic">{{ note }}</span>
                             </div>
                           </div>
                         </div>
                       </div>
                       
                       <!-- No Modifiers/Notes Message -->
                       <div v-if="(!pref.modifiers || pref.modifiers.length === 0) && (!pref.notes || pref.notes.length === 0)" class="text-center py-4">
                         <i class="fa-solid fa-info-circle text-gray-400 text-lg mb-2"></i>
                         <p class="text-gray-600 text-sm">Tidak ada modifier atau catatan untuk menu ini</p>
                       </div>
                     </div>
                   </div>
                 </div>
             </div>
           </div>

           <!-- Footer -->
           <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end">
             <button @click="closePreferencesModal" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
               Tutup
             </button>
           </div>
         </div>
       </div>

       <!-- Modal Activity Timeline -->
       <div v-if="showVoucherTimelineModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
         <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
           <!-- Header -->
           <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 text-white p-6 rounded-t-2xl">
             <div class="flex justify-between items-center">
               <div>
                 <h2 class="text-xl font-bold">Timeline Aktivitas Member</h2>
                 <p class="text-indigo-200">{{ selectedMember?.name }} ({{ selectedMember?.costumers_id }})</p>
               </div>
               <button @click="closeVoucherTimelineModal" class="text-white hover:text-indigo-200 transition">
                 <i class="fa-solid fa-times text-xl"></i>
               </button>
             </div>
           </div>

           <!-- Content -->
           <div class="p-6">
             <!-- Summary Cards - Point Transactions -->
             <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
               <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-green-100 rounded-lg">
                     <i class="fa-solid fa-arrow-up text-green-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-green-600">Point Diperoleh</p>
                     <p class="text-lg font-bold text-green-800">{{ voucherTimelineSummary.total_point_earned_formatted || 0 }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-red-100 rounded-lg">
                     <i class="fa-solid fa-arrow-down text-red-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-red-600">Point Digunakan</p>
                     <p class="text-lg font-bold text-red-800">{{ voucherTimelineSummary.total_point_used_formatted || 0 }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-blue-100 rounded-lg">
                     <i class="fa-solid fa-list text-blue-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-blue-600">Total Aktivitas</p>
                     <p class="text-lg font-bold text-blue-800">{{ voucherTimelineSummary.total_activities || 0 }}</p>
                   </div>
                 </div>
               </div>
             </div>

             <!-- Summary Cards - Vouchers -->
             <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
               <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-blue-100 rounded-lg">
                     <i class="fa-solid fa-ticket text-blue-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-blue-600">Voucher Dimiliki</p>
                     <p class="text-lg font-bold text-blue-800">{{ voucherTimelineSummary.total_owned || 0 }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-green-100 rounded-lg">
                     <i class="fa-solid fa-check-circle text-green-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-green-600">Voucher Aktif</p>
                     <p class="text-lg font-bold text-green-800">{{ voucherTimelineSummary.total_active || 0 }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-orange-100 rounded-lg">
                     <i class="fa-solid fa-check-double text-orange-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-orange-600">Voucher Digunakan</p>
                     <p class="text-lg font-bold text-orange-800">{{ voucherTimelineSummary.total_used || 0 }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-purple-100 rounded-lg">
                     <i class="fa-solid fa-shopping-cart text-purple-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-purple-600">Voucher Dibeli</p>
                     <p class="text-lg font-bold text-purple-800">{{ voucherTimelineSummary.total_purchased || 0 }}</p>
                   </div>
                 </div>
               </div>
             </div>

             <!-- Challenge & Reward Summary -->
             <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
               <div class="bg-teal-50 rounded-xl p-4 border border-teal-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-teal-100 rounded-lg">
                     <i class="fa-solid fa-flag text-teal-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-teal-600">Challenge Dimulai</p>
                     <p class="text-lg font-bold text-teal-800">{{ voucherTimelineSummary.total_challenges_started || 0 }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-emerald-100 rounded-lg">
                     <i class="fa-solid fa-trophy text-emerald-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-emerald-600">Challenge Selesai</p>
                     <p class="text-lg font-bold text-emerald-800">{{ voucherTimelineSummary.total_challenges_completed || 0 }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-amber-50 rounded-xl p-4 border border-amber-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-amber-100 rounded-lg">
                     <i class="fa-solid fa-gift text-amber-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-amber-600">Reward Diredeem</p>
                     <p class="text-lg font-bold text-amber-800">{{ voucherTimelineSummary.total_rewards_redeemed || 0 }}</p>
                   </div>
                 </div>
               </div>
               <div class="bg-cyan-50 rounded-xl p-4 border border-cyan-200">
                 <div class="flex items-center">
                   <div class="p-2 bg-cyan-100 rounded-lg">
                     <i class="fa-solid fa-chart-line text-cyan-600"></i>
                   </div>
                   <div class="ml-3">
                     <p class="text-sm text-cyan-600">Perubahan Tier</p>
                     <p class="text-lg font-bold text-cyan-800">{{ voucherTimelineSummary.total_tier_changes || 0 }}</p>
                   </div>
                 </div>
               </div>
             </div>

             <!-- Timeline -->
             <div class="bg-gray-50 rounded-xl p-4">
               <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                 <i class="fa-solid fa-clock-rotate-left text-indigo-600"></i>
                 Timeline Aktivitas
               </h3>
               
               <div v-if="loadingVoucherTimeline" class="text-center py-8">
                 <i class="fa-solid fa-spinner fa-spin text-2xl text-indigo-600 mb-2"></i>
                 <p class="text-gray-600">Memuat data timeline aktivitas...</p>
               </div>

               <div v-else-if="voucherTimeline.length === 0" class="text-center py-8">
                 <i class="fa-solid fa-history text-4xl text-gray-400 mb-2"></i>
                 <p class="text-gray-600">Belum ada aktivitas</p>
               </div>

               <div v-else class="relative">
                 <!-- Timeline Line -->
                 <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gradient-to-b from-indigo-300 via-indigo-200 to-indigo-300"></div>
                 
                 <!-- Timeline Items -->
                 <div class="space-y-6">
                   <div v-for="(item, index) in voucherTimeline" :key="index" class="relative flex items-start gap-4">
                     <!-- Timeline Icon -->
                     <div :class="[
                       'absolute left-4 w-8 h-8 rounded-full border-2 border-white z-10 flex items-center justify-center shadow-lg',
                       item.type === 'owned' ? 'bg-blue-500' :
                       item.type === 'purchased' ? 'bg-purple-500' :
                       item.type === 'redeemed' ? 'bg-green-500' :
                       item.type === 'challenge_start' ? 'bg-teal-500' :
                       item.type === 'challenge_complete' ? 'bg-emerald-500' :
                       item.type === 'challenge_claim' ? 'bg-yellow-500' :
                       item.type === 'challenge_redeem' ? 'bg-orange-500' :
                       item.type === 'reward_redeem' ? 'bg-amber-500' :
                       item.type === 'tier_upgrade' ? 'bg-cyan-500' :
                       item.type === 'tier_downgrade' ? 'bg-red-500' :
                       item.type === 'point_earned_purchase' || item.type === 'point_earned_registration' || item.type === 'point_earned_bonus' || item.type === 'point_earned_referral' ? 'bg-green-500' :
                       item.type === 'point_adjustment' ? 'bg-yellow-500' :
                       'bg-gray-500'
                     ]">
                       <i :class="getTimelineIcon(item.type)" class="text-white text-sm"></i>
                     </div>
                     
                     <!-- Content -->
                     <div class="ml-16 flex-1 bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                       <div class="p-4">
                         <!-- Header -->
                         <div class="flex items-start justify-between mb-2">
                           <div class="flex-1">
                             <div class="flex items-center gap-2 mb-1">
                               <span :class="[
                                 'px-2 py-1 rounded-full text-xs font-semibold',
                                 item.type === 'owned' ? 'bg-blue-100 text-blue-800' :
                                 item.type === 'purchased' ? 'bg-purple-100 text-purple-800' :
                                 item.type === 'redeemed' ? 'bg-green-100 text-green-800' :
                                 item.type === 'challenge_start' ? 'bg-teal-100 text-teal-800' :
                                 item.type === 'challenge_complete' ? 'bg-emerald-100 text-emerald-800' :
                                 item.type === 'challenge_claim' ? 'bg-yellow-100 text-yellow-800' :
                                 item.type === 'challenge_redeem' ? 'bg-orange-100 text-orange-800' :
                                 item.type === 'reward_redeem' ? 'bg-amber-100 text-amber-800' :
                                 item.type === 'tier_upgrade' ? 'bg-cyan-100 text-cyan-800' :
                                 item.type === 'tier_downgrade' ? 'bg-red-100 text-red-800' :
                                 item.type === 'point_earned_purchase' || item.type === 'point_earned_registration' || item.type === 'point_earned_bonus' || item.type === 'point_earned_referral' ? 'bg-green-100 text-green-800' :
                                 item.type === 'point_adjustment' ? 'bg-yellow-100 text-yellow-800' :
                                 'bg-gray-100 text-gray-800'
                               ]">
                                 {{ item.title }}
                               </span>
                               <span v-if="item.status" :class="[
                                 'px-2 py-1 rounded-full text-xs font-semibold',
                                 item.status === 'active' ? 'bg-green-100 text-green-800' :
                                 item.status === 'used' ? 'bg-gray-100 text-gray-800' :
                                 item.status === 'started' ? 'bg-teal-100 text-teal-800' :
                                 item.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                 item.status === 'claimed' ? 'bg-yellow-100 text-yellow-800' :
                                 item.status === 'redeemed' ? 'bg-orange-100 text-orange-800' :
                                 'bg-yellow-100 text-yellow-800'
                               ]">
                                 {{ item.status === 'active' ? 'Aktif' : 
                                    item.status === 'used' ? 'Digunakan' : 
                                    item.status === 'started' ? 'Dimulai' :
                                    item.status === 'completed' ? 'Selesai' :
                                    item.status === 'claimed' ? 'Diklaim' :
                                    item.status === 'redeemed' ? 'Diredeem' :
                                    'Dibeli' }}
                               </span>
                               <span v-if="item.tier_change" :class="[
                                 'px-2 py-1 rounded-full text-xs font-semibold',
                                 item.type === 'tier_upgrade' ? 'bg-cyan-100 text-cyan-800' :
                                 'bg-red-100 text-red-800'
                               ]">
                                 {{ item.tier_change }}
                               </span>
                             </div>
                             <h4 class="text-lg font-bold text-gray-800">
                               {{ item.voucher_name || item.challenge_name || item.reward_name || (item.point_amount_formatted ? item.point_amount_formatted : item.title) }}
                             </h4>
                             <p class="text-sm text-gray-500 mt-1">{{ item.date_formatted }}</p>
                           </div>
                         </div>
                         
                         <!-- Info -->
                         <div class="mt-3 space-y-2">
                           <!-- Description -->
                           <div v-if="item.description || item.challenge_description || item.reward_description" class="text-sm text-gray-600">
                             {{ item.description || item.challenge_description || item.reward_description }}
                           </div>
                           
                           <!-- Voucher Discount Info -->
                           <div v-if="item.discount_info" class="flex flex-wrap gap-2 mt-2">
                             <span class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-xs font-medium">
                               {{ item.discount_info.type }}: {{ item.discount_info.value }}
                             </span>
                             <span v-if="item.discount_info.max" class="px-2 py-1 bg-gray-50 text-gray-700 rounded text-xs">
                               {{ item.discount_info.max }}
                             </span>
                             <span v-if="item.discount_info.min_purchase" class="px-2 py-1 bg-gray-50 text-gray-700 rounded text-xs">
                               {{ item.discount_info.min_purchase }}
                             </span>
                           </div>
                           
                           <!-- Challenge Reward Info -->
                           <div v-if="item.reward_info" class="flex flex-wrap gap-2 mt-2">
                             <span class="px-2 py-1 bg-yellow-50 text-yellow-700 rounded text-xs font-medium">
                               Reward: {{ item.reward_info.type }} - {{ item.reward_info.value }}
                             </span>
                           </div>
                           
                           <!-- Point Amount (for point transactions) -->
                           <div v-if="item.point_amount_formatted && !item.points_spent" class="flex items-center gap-2 text-sm mt-2" :class="item.is_earned ? 'text-green-600' : 'text-red-600'">
                             <i :class="item.is_earned ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'"></i>
                             <span class="font-semibold">{{ item.is_earned ? '+' : '-' }}{{ item.point_amount_formatted }}</span>
                           </div>
                           
                           <!-- Transaction Amount -->
                           <div v-if="item.transaction_amount_formatted" class="flex items-center gap-2 text-sm text-gray-600 mt-2">
                             <i class="fa-solid fa-money-bill-wave"></i>
                             <span>Nilai Transaksi: {{ item.transaction_amount_formatted }}</span>
                           </div>
                           
                           <!-- Transaction ID -->
                           <div v-if="item.transaction_id" class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                             <i class="fa-solid fa-hashtag"></i>
                             <span>Transaksi #{{ item.transaction_id }}</span>
                           </div>
                           
                           <!-- Outlet Name (for point transactions) -->
                           <div v-if="item.outlet_name && !item.used_outlet && !item.redeemed_outlet" class="flex items-center gap-2 text-sm text-blue-600 mt-2">
                             <i class="fa-solid fa-store"></i>
                             <span>Outlet: {{ item.outlet_name }}</span>
                           </div>
                           
                           <!-- Points Spent -->
                           <div v-if="item.points_spent" class="flex items-center gap-2 text-sm text-purple-600 mt-2">
                             <i class="fa-solid fa-coins"></i>
                             <span>Menggunakan {{ item.points_spent_formatted }} point</span>
                           </div>
                           
                           <!-- Points Required -->
                           <div v-if="item.points_required" class="flex items-center gap-2 text-sm text-amber-600 mt-2">
                             <i class="fa-solid fa-coins"></i>
                             <span>Point yang dibutuhkan: {{ formatNumber(item.points_required) }}</span>
                           </div>
                           
                           <!-- Serial Code -->
                           <div v-if="item.serial_code" class="flex items-center gap-2 text-sm text-gray-600 mt-2">
                             <i class="fa-solid fa-barcode"></i>
                             <span class="font-mono">{{ item.serial_code }}</span>
                           </div>
                           
                           <!-- Used/Redeemed Outlet -->
                           <div v-if="item.used_outlet || item.redeemed_outlet" class="flex items-center gap-2 text-sm text-green-600 mt-2">
                             <i class="fa-solid fa-store"></i>
                             <span>Digunakan di: {{ item.used_outlet || item.redeemed_outlet }}</span>
                           </div>
                           
                           <!-- Challenge Type -->
                           <div v-if="item.challenge_type" class="flex items-center gap-2 text-sm text-teal-600 mt-2">
                             <i class="fa-solid fa-tasks"></i>
                             <span>Tipe Challenge: {{ item.challenge_type }}</span>
                           </div>
                           
                           <!-- Tier Change Info -->
                           <div v-if="item.tier_change" class="mt-3 space-y-2">
                             <div class="flex items-center gap-2 text-sm">
                               <i :class="[
                                 'fa-solid',
                                 item.type === 'tier_upgrade' ? 'fa-arrow-up text-cyan-600' : 'fa-arrow-down text-red-600'
                               ]"></i>
                               <span class="font-semibold">{{ item.tier_change }}</span>
                             </div>
                             <div v-if="item.total_spending" class="flex items-center gap-2 text-sm text-gray-600">
                               <i class="fa-solid fa-money-bill-wave"></i>
                               <span>Total Spending: {{ item.total_spending_formatted }}</span>
                             </div>
                             <div v-if="item.spending_period_start && item.spending_period_end" class="flex items-center gap-2 text-sm text-gray-600">
                               <i class="fa-solid fa-calendar"></i>
                               <span>Periode: {{ formatDate(item.spending_period_start) }} - {{ formatDate(item.spending_period_end) }}</span>
                             </div>
                             <div v-if="item.change_reason" class="flex items-center gap-2 text-sm text-gray-600">
                               <i class="fa-solid fa-info-circle"></i>
                               <span>Alasan: {{ item.change_reason }}</span>
                             </div>
                           </div>
                         </div>
                       </div>
                     </div>
                   </div>
                 </div>
               </div>
             </div>
           </div>

           <!-- Footer -->
           <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end">
             <button @click="closeVoucherTimelineModal" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
               Tutup
             </button>
           </div>
         </div>
       </div>
    </div>
  </AppLayout>
</template> 