<template>
  <AppLayout>
    <div class="py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <div class="p-2 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-money-bill-wave text-white text-xl"></i>
              </div>
              <span>Retail Non Food Payment</span>
            </h1>
            <p class="text-sm text-gray-500 mt-1 ml-14">Payment untuk transaksi retail non food (cash)</p>
          </div>
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
        </div>
        
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Search -->
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
              <i class="fa-solid fa-search mr-1 text-gray-400"></i>Cari
            </label>
            <input 
              type="text" 
              v-model="filters.search" 
              placeholder="No. transaksi, outlet, supplier..."
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white"
            />
          </div>

          <!-- Date From -->
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
              <i class="fa-solid fa-calendar-alt mr-1 text-gray-400"></i>Tanggal Dari
            </label>
            <input 
              type="date" 
              v-model="filters.date_from" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white"
            />
          </div>

          <!-- Date To -->
          <div class="space-y-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1">
              <i class="fa-solid fa-calendar-check mr-1 text-gray-400"></i>Tanggal Sampai
            </label>
            <input 
              type="date" 
              v-model="filters.date_to" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white"
            />
          </div>
        </form>

        <!-- Filter Actions -->
        <div class="flex justify-end gap-3 mt-6 pt-6 border-t border-gray-200">
          <button 
            @click="clearFilters" 
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-gray-700 bg-gray-100 border-2 border-gray-200 rounded-xl hover:bg-gray-200 hover:border-gray-300 transition-all duration-200"
          >
            <i class="fa-solid fa-times"></i>
            Reset Filter
          </button>
          <button 
            @click="applyFilters" 
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl hover:from-blue-600 hover:to-blue-700 shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
          >
            <i class="fa-solid fa-search"></i>
            Terapkan Filter
          </button>
        </div>
      </div>

      <!-- Table Section -->
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th scope="col" class="px-3 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider w-12">
                  #
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  No. Transaksi
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Tanggal
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Outlet
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Supplier
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Category
                </th>
                <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Total Amount
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Payment Method
                </th>
                <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Status Jurnal
                </th>
                <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                  COA
                </th>
                <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                  Aksi
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="retailNonFoods.data.length === 0">
                <td colspan="10" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center justify-center text-gray-400">
                    <i class="fa-solid fa-inbox text-6xl mb-4"></i>
                    <p class="text-lg font-semibold">Tidak ada data</p>
                    <p class="text-sm mt-1">Semua transaksi retail non food cash sudah memiliki jurnal</p>
                  </div>
                </td>
              </tr>
              <template v-for="rnf in retailNonFoods.data" :key="rnf.id">
                <tr class="hover:bg-blue-50 transition-colors duration-150">
                  <td class="px-3 py-4 text-center">
                    <button 
                      @click="toggleRow(rnf.id)"
                      class="text-gray-600 hover:text-blue-600 transition-colors duration-150"
                    >
                      <i :class="expandedRows[rnf.id] ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"></i>
                    </button>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-semibold text-blue-600">{{ rnf.retail_number }}</div>
                    <div class="text-xs text-gray-500">oleh: {{ rnf.creator?.nama_lengkap || '-' }}</div>
                  </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ formatDate(rnf.transaction_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ rnf.outlet?.nama_outlet || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ rnf.supplier?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-700">{{ rnf.category_budget?.name || '-' }}</div>
                  <div class="text-xs text-gray-500">{{ rnf.category_budget?.division || '-' }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <span class="text-sm font-bold text-gray-900">{{ formatRupiah(rnf.total_amount) }}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                    CASH
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div v-if="!rnf.jurnal_created">
                    <Multiselect
                      v-model="selectedCoas[rnf.id]"
                      :options="props.coas"
                      :searchable="true"
                      :close-on-select="true"
                      :clear-on-select="false"
                      :preserve-search="true"
                      placeholder="Pilih COA..."
                      track-by="id"
                      label="display_name"
                      :preselect-first="false"
                      class="w-96"
                    />
                  </div>
                  <div v-else class="space-y-1">
                    <div v-if="rnf.jurnal && rnf.jurnal.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                      <div class="flex items-center gap-2 mb-2">
                        <span class="px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800">
                          <i class="fa-solid fa-receipt mr-1"></i>{{ rnf.jurnal[0].no_jurnal }}
                        </span>
                      </div>
                      <div class="text-xs text-gray-700 space-y-1">
                        <div class="flex items-start">
                          <span class="font-semibold w-16">Debit:</span>
                          <span class="flex-1">{{ rnf.jurnal[0].coa_debit?.code }} - {{ rnf.jurnal[0].coa_debit?.name }}</span>
                        </div>
                        <div class="flex items-start">
                          <span class="font-semibold w-16">Kredit:</span>
                          <span class="flex-1">{{ rnf.jurnal[0].coa_kredit?.code }} - {{ rnf.jurnal[0].coa_kredit?.name }}</span>
                        </div>
                      </div>
                    </div>
                    <span v-else class="text-sm text-gray-500 italic">Jurnal sudah dibuat</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <button 
                      v-if="!rnf.jurnal_created"
                      @click="createJurnal(rnf)"
                      :disabled="!selectedCoas[rnf.id] || processing[rnf.id]"
                      class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg hover:from-blue-600 hover:to-blue-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
                    >
                      <i class="fa-solid fa-check-circle"></i>
                      <span v-if="processing[rnf.id]">Processing...</span>
                      <span v-else>Buat Jurnal</span>
                    </button>
                    <button 
                      v-else
                      @click="rollbackJurnal(rnf)"
                      :disabled="processing[rnf.id]"
                      class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-red-500 to-red-600 rounded-lg hover:from-red-600 hover:to-red-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
                    >
                      <i class="fa-solid fa-trash-undo"></i>
                      <span v-if="processing[rnf.id]">Processing...</span>
                      <span v-else>Rollback</span>
                  </button>
                </td>
              </tr>
              
              <!-- Detail Items Row -->
              <tr v-show="expandedRows[rnf.id]" class="bg-gray-50">
                <td colspan="10" class="px-6 py-4">
                  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                      <i class="fa-solid fa-list text-blue-600"></i>
                      Detail Items
                    </h4>
                    <div class="overflow-x-auto">
                      <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                          <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">No</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Item Name</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600">Qty</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600">Unit</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Price</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                          <tr v-for="(item, index) in rnf.items" :key="item.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-600">{{ index + 1 }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900 font-medium">{{ item.item_name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 text-center">{{ item.qty }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 text-center">{{ item.unit }}</td>
                            <td class="px-4 py-2 text-sm text-gray-600 text-right">{{ formatRupiah(item.price) }}</td>
                            <td class="px-4 py-2 text-sm font-semibold text-gray-900 text-right">{{ formatRupiah(item.subtotal) }}</td>
                          </tr>
                          <tr v-if="!rnf.items || rnf.items.length === 0">
                            <td colspan="6" class="px-4 py-3 text-sm text-gray-500 text-center italic">
                              Tidak ada item
                            </td>
                          </tr>
                        </tbody>
                        <tfoot class="bg-gray-50">
                          <tr>
                            <td colspan="5" class="px-4 py-2 text-sm font-bold text-gray-700 text-right">Total:</td>
                            <td class="px-4 py-2 text-sm font-bold text-gray-900 text-right">{{ formatRupiah(rnf.total_amount) }}</td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                    <div v-if="rnf.notes" class="mt-3 pt-3 border-t border-gray-200">
                      <p class="text-xs font-semibold text-gray-600 mb-1">Notes:</p>
                      <p class="text-sm text-gray-700">{{ rnf.notes }}</p>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="retailNonFoods.data.length > 0" class="bg-gray-50 px-6 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Menampilkan <span class="font-semibold">{{ retailNonFoods.from }}</span> sampai 
              <span class="font-semibold">{{ retailNonFoods.to }}</span> dari 
              <span class="font-semibold">{{ retailNonFoods.total }}</span> transaksi
            </div>
            <div class="flex gap-2">
              <button 
                v-for="link in retailNonFoods.links" 
                :key="link.label"
                @click="changePage(link.url)"
                :disabled="!link.url"
                v-html="link.label"
                :class="[
                  'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200',
                  link.active 
                    ? 'bg-blue-600 text-white shadow-md' 
                    : link.url 
                      ? 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300' 
                      : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                ]"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';
import Swal from 'sweetalert2';

const props = defineProps({
  user: Object,
  retailNonFoods: Object,
  coas: Array,
  filters: Object,
});

const filters = reactive({
  search: props.filters.search || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
});

const selectedCoas = ref({});
const processing = ref({});
const expandedRows = ref({});

const toggleRow = (rnfId) => {
  expandedRows.value[rnfId] = !expandedRows.value[rnfId];
};

const applyFilters = () => {
  router.get(route('retail-non-food-payment.index'), filters, {
    preserveState: true,
    preserveScroll: true,
  });
};

const clearFilters = () => {
  filters.search = '';
  filters.date_from = '';
  filters.date_to = '';
  applyFilters();
};

const changePage = (url) => {
  if (!url) return;
  router.get(url, {}, {
    preserveState: true,
    preserveScroll: true,
  });
};

const createJurnal = async (rnf) => {
  if (!selectedCoas.value[rnf.id]) {
    Swal.fire({
      icon: 'warning',
      title: 'COA Belum Dipilih',
      text: 'Silakan pilih COA terlebih dahulu',
      confirmButtonColor: '#3b82f6',
    });
    return;
  }

  const result = await Swal.fire({
    title: 'Konfirmasi Buat Jurnal',
    html: `
      <div class="text-left">
        <p><strong>Transaksi:</strong> ${rnf.retail_number}</p>
        <p><strong>Total:</strong> ${formatRupiah(rnf.total_amount)}</p>
        <p><strong>COA:</strong> ${selectedCoas.value[rnf.id].display_name}</p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3b82f6',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Buat Jurnal',
    cancelButtonText: 'Batal',
  });

  if (!result.isConfirmed) {
    return;
  }

  processing.value[rnf.id] = true;

  try {
    const response = await axios.post(route('retail-non-food-payment.store'), {
      retail_non_food_id: rnf.id,
      coa_id: selectedCoas.value[rnf.id].id,
    });

    processing.value[rnf.id] = false;

    await Swal.fire({
      icon: 'success',
      title: 'Berhasil!',
      text: response.data.message || 'Jurnal berhasil dibuat',
      confirmButtonColor: '#3b82f6',
    });
    
    // Reload page to refresh list
    window.location.reload();
  } catch (error) {
    console.error('Error creating jurnal:', error);
    processing.value[rnf.id] = false;
    await Swal.fire({
      icon: 'error',
      title: 'Gagal!',
      text: error.response?.data?.message || 'Gagal membuat jurnal',
      confirmButtonColor: '#3b82f6',
    });
  }
};

const rollbackJurnal = async (rnf) => {
  const result = await Swal.fire({
    title: 'Konfirmasi Rollback Jurnal',
    html: `
      <div class="text-left">
        <p><strong>Transaksi:</strong> ${rnf.retail_number}</p>
        <p><strong>Total:</strong> ${formatRupiah(rnf.total_amount)}</p>
        <p class="text-red-600 mt-2"><strong>Perhatian:</strong> Jurnal akan dihapus dan transaksi dapat di-posting ulang</p>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Rollback',
    cancelButtonText: 'Batal',
  });

  if (!result.isConfirmed) {
    return;
  }

  processing.value[rnf.id] = true;

  try {
    const response = await axios.post(route('retail-non-food-payment.rollback'), {
      retail_non_food_id: rnf.id,
    });

    processing.value[rnf.id] = false;

    await Swal.fire({
      icon: 'success',
      title: 'Berhasil!',
      text: response.data.message || 'Jurnal berhasil di-rollback',
      confirmButtonColor: '#3b82f6',
    });
    
    // Reload page to refresh list
    window.location.reload();
  } catch (error) {
    console.error('Error rollback jurnal:', error);
    processing.value[rnf.id] = false;
    await Swal.fire({
      icon: 'error',
      title: 'Gagal!',
      text: error.response?.data?.message || 'Gagal rollback jurnal',
      confirmButtonColor: '#3b82f6',
    });
  }
};

const formatDate = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  });
};

const formatRupiah = (amount) => {
  if (!amount) return 'Rp 0';
  return 'Rp ' + parseFloat(amount).toLocaleString('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  });
};
</script>

<style scoped>
/* Custom styles if needed */
</style>
