<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import ChartOfAccountFormModal from './ChartOfAccountFormModal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  chartOfAccounts: Object,
  filters: Object,
  parents: Array,
  menus: Array,
  allMenus: Array,
  allCoAs: Array,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      active: 0,
      inactive: 0,
      by_type: {
        Asset: 0,
        Liability: 0,
        Equity: 0,
        Revenue: 0,
        Expense: 0,
      }
    })
  },
});

const search = ref(props.filters?.search || '');
const typeFilter = ref(props.filters?.type || '');
const statusFilter = ref(props.filters?.is_active || 'all');
const perPage = ref(props.filters?.per_page || 15);

const showModal = ref(false);
const modalMode = ref('create');
const selectedChartOfAccount = ref(null);

const debouncedSearch = debounce(() => {
  router.get('/chart-of-accounts', { 
    search: search.value, 
    type: typeFilter.value,
    is_active: statusFilter.value === 'all' ? null : (statusFilter.value === '1' ? '1' : '0'),
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

watch([statusFilter, typeFilter, perPage], () => {
  router.get('/chart-of-accounts', { 
    search: search.value, 
    type: typeFilter.value,
    is_active: statusFilter.value === 'all' ? null : (statusFilter.value === '1' ? '1' : '0'),
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) {
    const urlObj = new URL(url, window.location.origin);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('type', typeFilter.value);
    urlObj.searchParams.set('is_active', statusFilter.value === 'all' ? '' : statusFilter.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function filterByStatus(newStatus) {
  statusFilter.value = newStatus;
}

function filterByType(newType) {
  typeFilter.value = newType;
}

function openCreate() {
  modalMode.value = 'create';
  selectedChartOfAccount.value = null;
  showModal.value = true;
}

function openCreateChild(parentCoa) {
  modalMode.value = 'create';
  // Set parent untuk child yang akan dibuat
  selectedChartOfAccount.value = { parent_id: parentCoa.id };
  showModal.value = true;
}

function openEdit(coa) {
  modalMode.value = 'edit';
  selectedChartOfAccount.value = coa;
  showModal.value = true;
}

async function hapus(coa) {
  const result = await Swal.fire({
    title: 'Hapus Chart of Account?',
    text: `Yakin ingin menghapus "${coa.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmColor: '#3085d6',
    cancelColor: '#d33',
    confirmText: 'Ya, Hapus!',
    cancelText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(`/chart-of-accounts/${coa.id}`, {
    onSuccess: () => Swal.fire('Berhasil', 'Chart of Account berhasil dihapus!', 'success'),
  });
}

function closeModal() {
  showModal.value = false;
}

function onSuccess() {
  closeModal();
  router.reload({ preserveState: true });
}

// Helper function to get full code path (parent.code.child.code)
function getFullCodePath(coa) {
  if (!coa.parent) return null;
  
  const path = [coa.code];
  let current = coa.parent;
  
  while (current) {
    path.unshift(current.code);
    current = current.parent || null;
  }
  
  return path.join('.');
}

// Helper function to get level depth
function getLevel(coa) {
  if (!coa.parent) return 0;
  
  let level = 1;
  let current = coa.parent;
  
  while (current && current.parent) {
    level++;
    current = current.parent;
  }
  
  return level;
}

// Helper function to get parent path string
function getParentPath(coa) {
  if (!coa.parent) return '';
  
  const path = [coa.parent.name];
  let current = coa.parent.parent;
  
  while (current) {
    path.unshift(current.name);
    current = current.parent || null;
  }
  
  return path.join(' > ');
}

// Helper function to get mode payment label
function getModePaymentLabel(mode) {
  const labels = {
    'pr_ops': 'Purchase Requisition',
    'purchase_payment': 'Payment Application',
    'travel_application': 'Travel Application',
    'kasbon': 'Kasbon'
  };
  return labels[mode] || mode;
}

// Helper function to get mode payments as array
function getModePayments(modePayment) {
  if (!modePayment) return [];
  if (Array.isArray(modePayment)) return modePayment;
  // If it's still a string (old data), convert to array
  return [modePayment];
}

// Helper function to get menu IDs as array
function getMenuIds(menuId) {
  if (!menuId) return [];
  if (Array.isArray(menuId)) return menuId;
  // If it's still a single value (old data), convert to array
  return [menuId];
}

// Helper function to get menu name by ID
function getMenuNameById(menuId) {
  if (!props.allMenus) return `Menu #${menuId}`;
  const menu = props.allMenus.find(m => m.id === menuId);
  if (!menu) return `Menu #${menuId}`;
  if (menu.parent) {
    return `${menu.parent.name} > ${menu.name}`;
  }
  return menu.name;
}

// Helper function to get menu code by ID
function getMenuCodeById(menuId) {
  if (!props.allMenus) return '';
  const menu = props.allMenus.find(m => m.id === menuId);
  return menu ? menu.code : '';
}

// Helper function to format currency
function formatCurrency(value) {
  if (!value) return '0';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value);
}

// Toggle status aktif/non-aktif
async function toggleStatus(coa) {
  const oldStatus = coa.is_active;
  // Optimistic update
  coa.is_active = coa.is_active == 1 ? 0 : 1;
  
  try {
    const response = await axios.patch(`/chart-of-accounts/${coa.id}/toggle-status`, {}, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    if (response.data?.success) {
      // Reload data untuk memastikan konsistensi dengan database
      router.reload({ 
        preserveState: true,
        only: ['chartOfAccounts', 'statistics']
      });
      
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: 'Status berhasil diubah',
        timer: 1500,
        showConfirmButton: false
      });
    } else {
      // Revert jika tidak berhasil
      coa.is_active = oldStatus;
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Gagal mengubah status',
      });
    }
  } catch (error) {
    // Revert status jika error
    coa.is_active = oldStatus;
    console.error('Toggle status error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Gagal mengubah status',
    });
  }
}
</script>

<template>
  <AppLayout title="Chart of Account">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-blue-500"></i> Chart of Account
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat CoA Baru
        </button>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total CoA -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          statusFilter === 'all' ? 'bg-blue-50 border-blue-500 shadow-xl' : 'bg-white border-blue-500 hover:shadow-xl'
        ]" @click="filterByStatus('all')" title="Klik untuk melihat semua CoA">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total CoA</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
              <p class="text-xs text-gray-500">100% dari total</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
              <i class="fa-solid fa-chart-line text-blue-600 text-xl"></i>
            </div>
          </div>
          <div class="absolute top-2 right-2 text-xs text-gray-400">
            <i class="fa-solid fa-mouse-pointer"></i>
          </div>
        </div>

        <!-- CoA Aktif -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          statusFilter === '1' ? 'bg-green-50 border-green-500 shadow-xl' : 'bg-white border-green-500 hover:shadow-xl'
        ]" @click="filterByStatus('1')" title="Klik untuk melihat CoA aktif">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">CoA Aktif</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.active }}</p>
              <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.active / statistics.total) * 100) : 0 }}% dari total</p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
              <i class="fa-solid fa-check-circle text-green-600 text-xl"></i>
            </div>
          </div>
          <div class="absolute top-2 right-2 text-xs text-gray-400">
            <i class="fa-solid fa-mouse-pointer"></i>
          </div>
        </div>

        <!-- CoA Non-Aktif -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          statusFilter === '0' ? 'bg-red-50 border-red-500 shadow-xl' : 'bg-white border-red-500 hover:shadow-xl'
        ]" @click="filterByStatus('0')" title="Klik untuk melihat CoA non-aktif">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">CoA Non-Aktif</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.inactive }}</p>
              <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.inactive / statistics.total) * 100) : 0 }}% dari total</p>
            </div>
            <div class="bg-red-100 p-3 rounded-full">
              <i class="fa-solid fa-times-circle text-red-600 text-xl"></i>
            </div>
          </div>
          <div class="absolute top-2 right-2 text-xs text-gray-400">
            <i class="fa-solid fa-mouse-pointer"></i>
          </div>
        </div>

        <!-- CoA by Type (Asset) -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          typeFilter === 'Asset' ? 'bg-green-50 border-green-500 shadow-xl' : 'bg-white border-green-500 hover:shadow-xl'
        ]" @click="filterByType('Asset')" title="Klik untuk melihat CoA Asset">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Asset</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.by_type.Asset }}</p>
              <p class="text-xs text-gray-500">Tipe Asset</p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
              <i class="fa-solid fa-wallet text-green-600 text-xl"></i>
            </div>
          </div>
          <div class="absolute top-2 right-2 text-xs text-gray-400">
            <i class="fa-solid fa-mouse-pointer"></i>
          </div>
        </div>
      </div>

      <div class="mb-4 flex gap-4 flex-wrap">
        <select v-model="statusFilter" class="form-input rounded-xl">
          <option value="all">Semua Status</option>
          <option value="1">Aktif</option>
          <option value="0">Non-Aktif</option>
        </select>
        <select v-model="typeFilter" class="form-input rounded-xl">
          <option value="">Semua Tipe</option>
          <option value="Asset">Asset</option>
          <option value="Liability">Liability</option>
          <option value="Equity">Equity</option>
          <option value="Revenue">Revenue</option>
          <option value="Expense">Expense</option>
        </select>
        <select v-model="perPage" class="form-input rounded-xl">
          <option value="10">10 per halaman</option>
          <option value="15">15 per halaman</option>
          <option value="25">25 per halaman</option>
          <option value="50">50 per halaman</option>
          <option value="100">100 per halaman</option>
        </select>
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari berdasarkan code, name, atau description..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition min-w-64"
        />
      </div>
      
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Code</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Name</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Parent</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Type</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Description</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Budget Limit</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Static/Dynamic</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Menu</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Payment</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="coa in chartOfAccounts.data" :key="coa.id" class="hover:bg-blue-50 transition" :class="{ 'bg-blue-50': coa.parent_id }">
              <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                <span v-if="getFullCodePath(coa)" class="text-gray-600">{{ getFullCodePath(coa) }}</span>
                <span v-else class="font-semibold">{{ coa.code }}</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                <div class="flex items-center">
                  <span v-if="coa.parent_id" :style="{ paddingLeft: (getLevel(coa) * 20) + 'px' }" class="text-gray-600">
                    <span v-for="i in getLevel(coa)" :key="i" class="inline-block mr-1">│</span>└─
                  </span>
                  <span class="font-semibold">{{ coa.name }}</span>
                  <span v-if="coa.parent" class="text-xs text-gray-400 ml-2">({{ getParentPath(coa) }})</span>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                <span v-if="coa.parent" class="text-gray-600">{{ getParentPath(coa) }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm">
                <span class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-green-100 text-green-800': coa.type === 'Asset',
                    'bg-red-100 text-red-800': coa.type === 'Liability',
                    'bg-blue-100 text-blue-800': coa.type === 'Equity',
                    'bg-yellow-100 text-yellow-800': coa.type === 'Revenue',
                    'bg-purple-100 text-purple-800': coa.type === 'Expense',
                  }">
                  {{ coa.type }}
                </span>
              </td>
              <td class="px-4 py-2 text-sm text-gray-500">{{ coa.description || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                <span v-if="coa.budget_limit" class="font-medium">
                  Rp {{ formatCurrency(coa.budget_limit) }}
                </span>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-sm">
                <span v-if="coa.static_or_dynamic" class="px-2 py-1 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-blue-100 text-blue-800': coa.static_or_dynamic === 'static',
                    'bg-purple-100 text-purple-800': coa.static_or_dynamic === 'dynamic',
                  }">
                  {{ coa.static_or_dynamic === 'static' ? 'Static' : 'Dynamic' }}
                </span>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 text-sm text-gray-500">
                <div v-if="getMenuIds(coa.menu_id).length > 0" class="flex flex-col gap-1">
                  <div
                    v-for="menuId in getMenuIds(coa.menu_id)"
                    :key="menuId"
                    class="flex flex-col"
                  >
                    <span class="font-medium text-gray-900">{{ getMenuNameById(menuId) }}</span>
                    <span class="text-xs text-gray-500">{{ getMenuCodeById(menuId) }}</span>
                  </div>
                </div>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 text-sm">
                <div v-if="coa.show_in_menu_payment" class="flex flex-col gap-1">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                    Show in Payment
                  </span>
                  <div v-if="getModePayments(coa.mode_payment).length > 0" class="flex flex-wrap gap-1">
                    <span
                      v-for="mode in getModePayments(coa.mode_payment)"
                      :key="mode"
                      class="px-2 py-1 text-xs font-semibold rounded-full"
                      :class="{
                        'bg-blue-100 text-blue-800': mode === 'pr_ops',
                        'bg-green-100 text-green-800': mode === 'purchase_payment',
                        'bg-purple-100 text-purple-800': mode === 'travel_application',
                        'bg-orange-100 text-orange-800': mode === 'kasbon',
                      }">
                      {{ getModePaymentLabel(mode) }}
                    </span>
                  </div>
                </div>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <button
                  @click="toggleStatus(coa)"
                  type="button"
                  :class="[
                    'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                    coa.is_active == 1 ? 'bg-blue-600' : 'bg-gray-200'
                  ]"
                >
                  <span
                    :class="[
                      'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                      coa.is_active == 1 ? 'translate-x-6' : 'translate-x-1'
                    ]"
                  />
                </button>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openCreateChild(coa)" class="px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 transition" title="Tambah Child">
                  <i class="fa-solid fa-plus"></i>
                </button>
                <button @click="openEdit(coa)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="hapus(coa)" class="px-2 py-1 rounded bg-red-500 text-white hover:bg-red-600 transition" title="Hapus">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="chartOfAccounts.data.length === 0">
              <td colspan="11" class="text-center py-8 text-gray-400">Tidak ada data</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div class="mt-4 flex justify-between items-center">
        <div class="text-sm text-gray-600">
          Menampilkan {{ chartOfAccounts.from || 0 }} - {{ chartOfAccounts.to || 0 }} dari {{ chartOfAccounts.total || 0 }} data
        </div>
        <nav v-if="chartOfAccounts.links && chartOfAccounts.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in chartOfAccounts.links" :key="i">
            <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 hover:bg-blue-50']" v-html="link.label"></button>
            <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
    
    <ChartOfAccountFormModal
      :show="showModal"
      :mode="modalMode"
      :chartOfAccount="selectedChartOfAccount"
      :parents="parents"
      :menus="menus"
      :allCoAs="allCoAs"
      @close="closeModal"
      @success="onSuccess"
    />
  </AppLayout>
</template>

