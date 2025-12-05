<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="closeModal"></div>
    
    <!-- Modal -->
    <div class="flex min-h-full items-center justify-center p-4">
      <div class="relative w-full max-w-4xl max-h-[90vh] bg-white rounded-xl shadow-xl flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h3 class="text-xl font-semibold text-gray-900 flex items-center gap-2">
              <i class="fa-solid fa-coins text-orange-500"></i>
              Detail Redeem - {{ cabangName }}
            </h3>
            <p class="text-sm text-gray-600 mt-1">
              Menampilkan semua transaksi redeem untuk cabang ini
            </p>
          </div>
          <button
            @click="closeModal"
            class="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="p-8 text-center">
          <div class="inline-flex items-center gap-2">
            <i class="fa-solid fa-spinner fa-spin text-blue-500"></i>
            <span class="text-gray-600">Memuat data redeem...</span>
          </div>
        </div>

        <!-- Content -->
        <div v-else class="p-6 flex-1 overflow-hidden flex flex-col">
          <!-- Summary Stats -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 flex-shrink-0">
            <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
              <div class="flex items-center gap-3">
                <div class="bg-orange-100 p-2 rounded-lg">
                  <i class="fa-solid fa-receipt text-orange-600"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-orange-600">Total Redeem</p>
                  <p class="text-2xl font-bold text-orange-900">{{ summary.total_redeem }}</p>
                </div>
              </div>
            </div>
            
            <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
              <div class="flex items-center gap-3">
                <div class="bg-orange-100 p-2 rounded-lg">
                  <i class="fa-solid fa-coins text-orange-600"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-orange-600">Total Point</p>
                  <p class="text-2xl font-bold text-orange-900">{{ formatNumber(summary.total_points) }}</p>
                </div>
              </div>
            </div>
            
            <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
              <div class="flex items-center gap-3">
                <div class="bg-orange-100 p-2 rounded-lg">
                  <i class="fa-solid fa-money-bill-wave text-orange-600"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-orange-600">Total Nilai</p>
                  <p class="text-lg font-bold text-orange-900">{{ formatRupiah(summary.total_value) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Redeem List -->
          <div class="bg-gray-50 rounded-lg p-4 flex-1 overflow-hidden flex flex-col">
            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2 flex-shrink-0">
              <i class="fa-solid fa-list text-gray-600"></i>
              Daftar Transaksi Redeem
              <span v-if="redeemDetails.length > 0" class="text-sm text-gray-500 font-normal">
                ({{ redeemDetails.length }} transaksi terbaru)
              </span>
            </h4>
            <p v-if="redeemDetails.length >= 50" class="text-xs text-gray-500 mb-3 flex-shrink-0">
              <i class="fa-solid fa-info-circle mr-1"></i>
              Menampilkan 50 transaksi terbaru. Gunakan filter tanggal untuk melihat data lebih spesifik.
            </p>
            
            <div v-if="redeemDetails.length === 0" class="text-center py-8 text-gray-500 flex-1 flex items-center justify-center">
              <div>
                <i class="fa-solid fa-coins text-4xl mb-4"></i>
                <p>Tidak ada data redeem untuk cabang ini</p>
              </div>
            </div>
            
            <div v-else class="space-y-3 overflow-y-auto flex-1 pr-2 custom-scrollbar">
              <div
                v-for="redeem in redeemDetails"
                :key="redeem.id"
                class="bg-white rounded-lg p-4 shadow-sm border border-gray-200"
              >
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                      <i class="fa-solid fa-minus-circle text-orange-600"></i>
                    </div>
                    <div>
                      <h5 class="font-semibold text-gray-900">{{ redeem.customer_name }}</h5>
                      <p class="text-sm text-gray-600">ID: {{ redeem.customer_id }}</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <span class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-sm font-medium">
                      Redeem
                    </span>
                  </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                  <div>
                    <span class="text-gray-500">Point Redeem:</span>
                    <span class="ml-1 font-semibold text-gray-900">{{ redeem.point_formatted }}</span>
                  </div>
                  <div>
                    <span class="text-gray-500">Nilai Transaksi:</span>
                    <span class="ml-1 font-semibold text-gray-900">{{ redeem.jml_trans_formatted }}</span>
                  </div>
                  <div>
                    <span class="text-gray-500">Bill Number:</span>
                    <span class="ml-1 text-gray-900">{{ redeem.bill_number }}</span>
                  </div>
                </div>
                
                <div class="mt-3 pt-3 border-t border-gray-200">
                  <div class="flex justify-between text-xs text-gray-500">
                    <span>
                      <i class="fa-solid fa-calendar mr-1"></i>
                      {{ redeem.created_at }}
                    </span>
                    <span>
                      <i class="fa-solid fa-clock mr-1"></i>
                      {{ redeem.created_at_full }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 p-6 border-t border-gray-200 flex-shrink-0">
          <button
            @click="closeModal"
            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  isOpen: Boolean,
  cabangId: Number,
  cabangName: String,
  startDate: String,
  endDate: String,
});

const emit = defineEmits(['close']);

const loading = ref(false);
const redeemDetails = ref([]);
const summary = ref({
  total_redeem: 0,
  total_points: 0,
  total_value: 0,
});

function formatNumber(number) {
  return number.toLocaleString('id-ID');
}

function formatRupiah(amount) {
  return 'Rp ' + amount.toLocaleString('id-ID');
}

function closeModal() {
  emit('close');
}

async function loadRedeemDetails() {
  if (!props.cabangId) return;
  
  loading.value = true;
  try {
    const params = new URLSearchParams({
      cabang_id: props.cabangId,
    });
    
    if (props.startDate) {
      params.append('start_date', props.startDate);
    }
    
    if (props.endDate) {
      params.append('end_date', props.endDate);
    }
    
    const response = await fetch(`/api/crm/redeem-details?${params}`);
    const data = await response.json();
    
    redeemDetails.value = data.redeem_details;
    summary.value = {
      total_redeem: data.total_redeem,
      total_points: data.total_points,
      total_value: data.total_value,
    };
  } catch (error) {
    console.error('Error loading redeem details:', error);
  } finally {
    loading.value = false;
  }
}

// Watch for modal open and load data
watch(() => props.isOpen, (newValue) => {
  if (newValue && props.cabangId) {
    loadRedeemDetails();
  }
});

// Watch for date changes and reload data
watch([() => props.startDate, () => props.endDate], () => {
  if (props.isOpen && props.cabangId) {
    loadRedeemDetails();
  }
});
</script>

<style scoped>
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: #f59e0b #f3f4f6;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: #f3f4f6;
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #f59e0b;
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #d97706;
}
</style> 