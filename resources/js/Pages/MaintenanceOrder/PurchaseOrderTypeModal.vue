<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
        <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        
        <h2 class="text-xl font-bold mb-6 text-center">Pilih Tipe Purchase Order</h2>
        
        <div class="grid grid-cols-2 gap-4">
          <button 
            @click="selectType('direct')"
            class="p-4 border-2 border-blue-500 rounded-lg hover:bg-blue-50 transition-colors flex flex-col items-center gap-2"
          >
            <i class="fas fa-file-invoice-dollar text-3xl text-blue-500"></i>
            <span class="font-medium">Direct PO</span>
            <span class="text-sm text-gray-500 text-center">Buat PO langsung ke supplier yang sudah ditentukan</span>
          </button>
          
          <button 
            @click="selectType('bidding')"
            class="p-4 border-2 border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex flex-col items-center gap-2"
          >
            <i class="fas fa-gavel text-3xl text-gray-500"></i>
            <span class="font-medium">Bidding</span>
            <span class="text-sm text-gray-500 text-center">Buat tender untuk mendapatkan penawaran terbaik</span>
          </button>
        </div>
        <BiddingStartModal
          :show="showBiddingModal"
          :task-id="taskId"
          @close="showBiddingModal = false"
          @bidding-started="handleBiddingStarted"
        />
        <BiddingSupplierInputModal
          :show="showBiddingSupplierModal"
          :items="biddingItems"
          @close="showBiddingSupplierModal = false"
          @supplier-input-done="handleSupplierInputDone"
        />
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref } from 'vue';
import BiddingStartModal from './po/BiddingStartModal.vue';
import BiddingSupplierInputModal from './po/BiddingSupplierInputModal.vue';

const props = defineProps({
  show: Boolean,
  taskId: [String, Number],
});

const emit = defineEmits(['close', 'select-type', 'open-direct-po']);

const showBiddingModal = ref(false);
const showBiddingSupplierModal = ref(false);
const selectedBiddingItems = ref([]);
const biddingItems = ref([]);
const biddingSuppliers = ref([]);
const biddingFile = ref(null);

function selectType(type) {
  if (type === 'direct') {
    emit('open-direct-po');
  } else if (type === 'bidding') {
    showBiddingModal.value = true;
    return;
  }
  emit('select-type', type);
  emit('close');
}

function handleBiddingStarted(itemIds) {
  biddingItems.value = getSelectedItems(itemIds);
  showBiddingModal.value = false;
  showBiddingSupplierModal.value = true;
}

function handleSupplierInputDone({ suppliers, file }) {
  biddingSuppliers.value = suppliers;
  biddingFile.value = file;
  showBiddingSupplierModal.value = false;
  // TODO: lanjut ke input penawaran supplier
}

function getSelectedItems(itemIds) {
  // Ambil data item dari PR yang sudah di-load di BiddingStartModal
  // (Untuk demo, return array kosong. Implementasi nyata: ambil dari state/props/PR data)
  return [];
}
</script>

<style scoped>
.bg-blue-50 { background-color: #eff6ff; }
</style> 