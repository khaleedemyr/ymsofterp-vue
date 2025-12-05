<template>
  <TransitionRoot appear :show="show" as="template">
    <Dialog as="div" @close="closeForm" class="relative z-[99999]">
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
                <h3 class="text-lg font-medium">Buat Purchase Order</h3>
                <button @click="closeForm" class="text-gray-400 hover:text-gray-600">
                  <i class="fas fa-times"></i>
                </button>
              </div>

              <!-- Form content -->
              <div class="space-y-4">
                <!-- Loading State -->
                <div v-if="loadingPRs" class="flex items-center justify-center py-8">
                  <div class="flex items-center gap-2">
                    <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                    <span>Loading PRs...</span>
                  </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="!loadingPRs && prs.length === 0" class="text-center py-8 text-gray-500">
                  Tidak ada PR yang tersedia untuk dikonversi ke PO.
                </div>

                <!-- PR List -->
                <div v-else class="space-y-4">
                  <div v-for="pr in prs" :key="pr.id" class="border rounded-lg p-4">
                    <div class="flex justify-between items-start">
                      <div>
                        <h4 class="font-semibold">{{ pr.pr_number }}</h4>
                        <p class="text-sm text-gray-500">{{ formatDate(pr.created_at) }}</p>
                      </div>
                      <button 
                        @click="togglePR(pr)"
                        class="text-blue-600 hover:text-blue-800"
                      >
                        <i :class="['fas', expandedPRs.includes(pr.id) ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                      </button>
                    </div>

                    <div v-show="expandedPRs.includes(pr.id)">
                      <div v-if="pr.items?.length" class="space-y-4">
                        <div v-for="item in pr.items" :key="item.id" class="bg-gray-50 p-4 rounded">
                          <div class="grid grid-cols-2 gap-4 mb-3">
                            <div>
                              <div class="font-medium">{{ item.item_name }}</div>
                              <div class="text-sm text-gray-600" v-if="item.specifications">
                                {{ item.specifications }}
                              </div>
                            </div>
                            <div class="text-right">
                              <div>{{ item.quantity }} x {{ formatCurrency(item.price) }}</div>
                              <div class="text-sm text-gray-600">
                                Subtotal: {{ formatCurrency(item.quantity * item.price) }}
                              </div>
                            </div>
                          </div>

                          <div class="grid grid-cols-2 gap-4">
                            <div>
                              <label class="block text-sm font-medium text-gray-700 mb-1">
                                Supplier
                              </label>
                              <select 
                                v-model="item.selected_supplier_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              >
                                <option value="">Pilih Supplier</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                  {{ supplier.name }}
                                </option>
                              </select>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700 mb-1">
                                Harga Supplier
                              </label>
                              <input 
                                type="number"
                                v-model="item.supplier_price"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Masukkan harga dari supplier"
                              >
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex justify-end gap-2 mt-6">
                <button 
                  @click="closeForm"
                  class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                  :disabled="isSaving"
                >
                  Batal
                </button>
                <button 
                  @click="createPO"
                  class="px-4 py-2 text-white bg-blue-500 rounded hover:bg-blue-600 flex items-center gap-2"
                  :disabled="!isValidForSubmission || isSaving"
                >
                  <div v-if="isSaving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  <span>{{ isSaving ? 'Menyimpan...' : 'Buat PO' }}</span>
                </button>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
} from '@headlessui/vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  taskId: [String, Number],
});

const emit = defineEmits(['close', 'po-created']);

const loadingPRs = ref(false);
const isSaving = ref(false);
const prs = ref([]);
const expandedPRs = ref([]);
const suppliers = ref([]);

// Fetch suppliers when form is shown
watch(() => props.show, async (val) => {
  if (val) {
    await Promise.all([
      fetchPRs(),
      fetchSuppliers()
    ]);
  }
});

async function fetchPRs() {
  loadingPRs.value = true;
  try {
    const res = await axios.get(`/api/maintenance-tasks/${props.taskId}/purchase-requisitions`);
    // Only show APPROVED PRs that haven't been converted to PO
    prs.value = res.data.filter(pr => pr.status === 'APPROVED');
  } catch (error) {
    console.error('Error fetching PRs:', error);
    Swal.fire('Error', 'Gagal mengambil data PR', 'error');
  } finally {
    loadingPRs.value = false;
  }
}

async function fetchSuppliers() {
  try {
    const res = await axios.get('/api/suppliers');
    suppliers.value = res.data;
  } catch (error) {
    console.error('Error fetching suppliers:', error);
    Swal.fire('Error', 'Gagal mengambil data supplier', 'error');
  }
}

function togglePR(pr) {
  const index = expandedPRs.value.indexOf(pr.id);
  if (index === -1) {
    expandedPRs.value.push(pr.id);
  } else {
    expandedPRs.value.splice(index, 1);
  }
}

async function createPO() {
  if (!isValidForSubmission.value) return;
  
  isSaving.value = true;
  try {
    // Group items by supplier
    const selectedPRs = prs.value.filter(pr => expandedPRs.value.includes(pr.id));
    if (selectedPRs.length === 0) {
      Swal.fire('Error', 'Silakan pilih minimal satu PR', 'error');
      return;
    }

    // Group items by supplier
    const itemsBySupplier = {};
    
    selectedPRs.forEach(pr => {
      pr.items.forEach(item => {
        if (!item.selected_supplier_id || !item.supplier_price) {
          return;
        }

        if (!itemsBySupplier[item.selected_supplier_id]) {
          itemsBySupplier[item.selected_supplier_id] = [];
        }

        itemsBySupplier[item.selected_supplier_id].push({
          item_name: item.item_name,
          description: item.description,
          specifications: item.specifications,
          quantity: item.quantity,
          unit_id: item.unit_id,
          price: item.price, // PR price
          supplier_price: item.supplier_price,
          subtotal: item.quantity * item.supplier_price,
          pr_id: pr.id, // Add PR ID
          pr_item_id: item.id // Add PR Item ID
        });
      });
    });

    // Create PO for each supplier
    const promises = Object.entries(itemsBySupplier).map(([supplierId, items]) => {
      return axios.post(`/api/maintenance-tasks/${props.taskId}/purchase-orders`, {
        supplier_id: supplierId,
        items: items,
      });
    });

    await Promise.all(promises);
    emit('po-created');
    closeForm();
    Swal.fire('Sukses', 'PO berhasil dibuat', 'success');
  } catch (error) {
    console.error('Error creating PO:', error);
    Swal.fire('Error', error.response?.data?.message || 'Gagal membuat PO', 'error');
  } finally {
    isSaving.value = false;
  }
}

// Add computed property for form validation
const isValidForSubmission = computed(() => {
  const selectedPRs = prs.value.filter(pr => expandedPRs.value.includes(pr.id));
  let hasValidItems = false;

  for (const pr of selectedPRs) {
    for (const item of pr.items) {
      if (item.selected_supplier_id && item.supplier_price) {
        hasValidItems = true;
        break;
      }
    }
    if (hasValidItems) break;
  }

  return hasValidItems;
});

function closeForm() {
  if (isSaving.value) return; // Prevent closing while saving
  emit('close');
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}
</script> 