<template>
  <TransitionRoot appear :show="show" as="template">
    <Dialog as="div" @close="close" class="relative z-[99999]">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black bg-opacity-25" />
      </TransitionChild>
      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel class="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Pilih Item PR untuk Bidding</h3>
                <div class="flex items-center gap-2">
                  <button @click="goToBiddingList" class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded hover:bg-green-200">
                    <i class="fas fa-list"></i> Lihat Daftar Bidding
                  </button>
                  <button @click="close" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
              <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="flex items-center gap-2">
                  <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                  <span>Loading PR...</span>
                </div>
              </div>
              <div v-else>
                <div v-if="prs.length === 0" class="text-center text-gray-500 py-8">
                  Tidak ada PR yang tersedia untuk bidding.
                </div>
                <div v-else class="space-y-4 max-h-[60vh] overflow-y-auto">
                  <div v-for="pr in prs" :key="pr.id" class="border rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2 cursor-pointer" @click="toggleExpand(pr.id)">
                      <div class="flex items-center gap-2">
                        <button @click.stop="toggleExpand(pr.id)" class="text-gray-500 hover:text-blue-600 focus:outline-none">
                          <i :class="expandedPrIds.includes(pr.id) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                        </button>
                        <div>
                          <div class="font-semibold">{{ pr.pr_number }}</div>
                          <div class="text-xs text-gray-500">{{ formatDate(pr.created_at) }}</div>
                        </div>
                      </div>
                      <div class="text-xs text-gray-500">Status: {{ pr.status }}</div>
                    </div>
                    <div v-if="expandedPrIds.includes(pr.id) && pr.items?.length">
                      <table class="w-full text-sm mb-2">
                        <thead>
                          <tr>
                            <th></th>
                            <th>Item</th>
                            <th>Spesifikasi</th>
                            <th>Qty</th>
                            <th>Unit</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="item in pr.items" :key="item.id">
                            <td>
                              <input type="checkbox" v-model="selectedItemIds" :value="item.id" />
                            </td>
                            <td>{{ item.item_name }}</td>
                            <td>{{ item.specifications || '-' }}</td>
                            <td>{{ item.quantity }}</td>
                            <td>{{ item.unit_name || '-' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <div class="flex justify-end gap-2 mt-6">
                <button @click="close" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Batal</button>
                <button @click="proceedBidding" :disabled="selectedItemIds.length === 0" class="px-4 py-2 text-white bg-blue-500 rounded hover:bg-blue-600">
                  Lanjut Bidding
                </button>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
  <BiddingPrintPreviewModal
    :show="showPrintPreview"
    :items="previewItems"
    @close="showPrintPreview = false"
    @printed="() => emit('bidding-started', selectedItemIds.value)"
  />
  <BiddingList
    v-if="showBiddingListModal"
    :task-id="props.taskId"
    @close="showBiddingListModal = false"
    @input-bidding="handleInputBidding"
  />
  <BiddingSupplierInputModal
    :show="showBiddingSupplierModal"
    :items="previewItems"
    @close="showBiddingSupplierModal = false"
    @supplier-input-done="handleSupplierInputDone"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
} from '@headlessui/vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import BiddingPrintPreviewModal from './BiddingPrintPreviewModal.vue';
import BiddingList from './BiddingList.vue';
import BiddingSupplierInputModal from './BiddingSupplierInputModal.vue';

const props = defineProps({
  show: Boolean,
  taskId: [String, Number],
});
const emit = defineEmits(['close', 'bidding-started']);

const loading = ref(false);
const prs = ref([]);
const selectedItemIds = ref([]);
const showPrintPreview = ref(false);
const previewItems = ref([]);
const showBiddingListModal = ref(false);
const showBiddingSupplierModal = ref(false);
const expandedPrIds = ref([]);

onMounted(fetchPRs);

async function fetchPRs() {
  loading.value = true;
  try {
    console.log('Fetching PRs for taskId:', props.taskId);
    const res = await axios.get(`/api/maintenance-tasks/${props.taskId}/purchase-requisitions`);
    console.log('API Response PRs:', res.data);
    prs.value = res.data.filter(pr => pr.status === 'APPROVED');
  } catch (error) {
    console.error('Error fetching PRs:', error);
    prs.value = [];
  } finally {
    loading.value = false;
  }
}

function close() {
  emit('close');
}

async function proceedBidding() {
  // Ambil data item terpilih dari PR yang sudah di-load
  const items = [];
  prs.value.forEach(pr => {
    (pr.items || []).forEach(item => {
      if (selectedItemIds.value.includes(item.id)) {
        items.push({
          ...item,
          pr_number: pr.pr_number
        });
      }
    });
  });
  previewItems.value = items;
  showPrintPreview.value = true;
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function goToBiddingList() {
  emit('close');
  setTimeout(() => {
    showBiddingListModal.value = true;
  }, 300); // beri jeda agar animasi close selesai
}

function handleInputBidding() {
  console.log('previewItems saat input bidding:', previewItems.value);
  if (!previewItems.value.length) {
    // Jika kosong, ambil ulang dari selectedItemIds dan prs
    const items = [];
    prs.value.forEach(pr => {
      (pr.items || []).forEach(item => {
        if (selectedItemIds.value.includes(item.id)) {
          items.push({
            ...item,
            pr_number: pr.pr_number
          });
        }
      });
    });
    previewItems.value = items;
  }
  showBiddingListModal.value = false;
  setTimeout(() => {
    showBiddingSupplierModal.value = true;
  }, 300);
}

function handleSupplierInputDone(data) {
  showBiddingSupplierModal.value = false;
  setTimeout(() => {
    showBiddingListModal.value = true;
  }, 300);
  // proses data jika perlu
}

function toggleExpand(prId) {
  const idx = expandedPrIds.value.indexOf(prId);
  if (idx === -1) {
    expandedPrIds.value.push(prId);
  } else {
    expandedPrIds.value.splice(idx, 1);
  }
}
</script> 