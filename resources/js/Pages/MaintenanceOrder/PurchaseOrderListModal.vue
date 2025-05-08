<template>
  <teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-4xl p-6 relative flex flex-col" style="max-height: 90vh;">
        <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        
        <h2 class="text-xl font-bold mb-4 text-center">Purchase Order (PO)</h2>
        
        <div class="flex justify-end mb-4">
          <button @click="showForm = true" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 flex items-center gap-2">
            <i class="fas fa-plus"></i> Buat PO
          </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex-1 flex items-center justify-center">
          <div class="flex items-center gap-2">
            <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            <span>Loading...</span>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!loading && pos.length === 0" class="flex-1 flex items-center justify-center text-gray-500">
          Belum ada PO.
        </div>

        <!-- PO List -->
        <div v-else class="flex-1 overflow-y-auto">
          <div v-for="po in pos" :key="po.id" class="border rounded-lg p-4 mb-4">
            <div class="flex justify-between items-start">
              <div>
                <h3 class="font-semibold text-lg">{{ po.po_number }}</h3>
                <p class="text-sm text-gray-500">{{ formatDate(po.created_at) }}</p>
              </div>
              <div class="flex gap-2">
                <button 
                  @click="showDetail(po.id)"
                  class="px-3 py-1 text-sm bg-blue-100 text-blue-600 rounded hover:bg-blue-200 flex items-center gap-1"
                >
                  <i class="fas fa-eye"></i>
                  Detail
                </button>
                <button
                  @click="openUploadInvoiceModal(po)"
                  class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded hover:bg-green-200 flex items-center gap-1"
                  title="Upload Invoice"
                >
                  <i class="fas fa-file-upload"></i>
                  Upload Invoice
                </button>
                <button 
                  @click="previewPO(po.id)"
                  class="px-3 py-1 text-sm bg-gray-100 text-gray-600 rounded hover:bg-gray-200 flex items-center gap-1"
                >
                  <i class="fas fa-file-alt"></i>
                  Preview
                </button>
                <button
                  @click="editPO(po.id)"
                  class="px-3 py-1 text-sm bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 flex items-center gap-1"
                  title="Edit PO"
                >
                  <i class="fas fa-edit"></i>
                  Edit
                </button>
                <button
                  @click="deletePO(po.id)"
                  class="px-3 py-1 text-sm bg-red-100 text-red-600 rounded hover:bg-red-200 flex items-center gap-1"
                  title="Hapus PO"
                >
                  <i class="fas fa-trash"></i>
                  Hapus
                </button>
                <button
                  v-if="po.status === 'APPROVED'"
                  @click="openGoodReceiveModal(po)"
                  class="px-3 py-1 text-sm bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 flex items-center gap-1"
                  title="Good Receive"
                >
                  <i class="fas fa-box"></i>
                  Good Receive
                </button>
              </div>
            </div>
            
            <div class="mt-2">
              <div class="text-sm">
                <span class="font-medium">Supplier:</span> {{ po.supplier?.name }}
              </div>
              <div class="text-sm">
                <span class="font-medium">Status:</span> 
                <span :class="{
                  'text-yellow-600': po.status === 'DRAFT',
                  'text-green-600': po.status === 'APPROVED',
                  'text-red-600': po.status === 'REJECTED'
                }">{{ po.status }}</span>
              </div>
              <div class="text-sm">
                <span class="font-medium">Total:</span> {{ formatCurrency(po.total_amount) }}
              </div>
            </div>

            <!-- PO Items -->
            <div v-if="po.items?.length" class="mt-3">
              <div class="text-sm font-medium mb-2">Items:</div>
              <div class="space-y-2">
                <div v-for="item in po.items" :key="item.id" class="text-sm bg-gray-50 p-2 rounded">
                  <div class="flex justify-between">
                    <span>{{ item.item_name }}</span>
                    <span>{{ item.quantity }} x {{ formatCurrency(item.supplier_price) }}</span>
                  </div>
                  <div class="text-gray-500 text-xs mt-1" v-if="item.specifications">
                    {{ item.specifications }}
                  </div>
                </div>
              </div>
            </div>

            <div v-if="poInvoices[po.id] && poInvoices[po.id].length" class="mt-2">
              <div class="text-sm font-medium mb-1">Invoice:</div>
              <div v-for="inv in poInvoices[po.id]" :key="inv.id" class="flex items-center gap-2 text-sm bg-gray-50 p-2 rounded mb-1">
                <span>No: {{ inv.invoice_number }}</span>
                <span>Tgl: {{ formatDate(inv.invoice_date) }}</span>
                <span v-if="inv.invoice_file_path">
                  <button @click="openInvoicePreview(inv.invoice_file_path)" class="text-blue-600 underline">Preview</button>
                </span>
                <span v-else>-</span>
              </div>
            </div>

            <div v-if="poReceives[po.id] && poReceives[po.id].length" class="mt-2">
              <div class="text-sm font-medium mb-1">Good Receive:</div>
              <div v-if="poReceives[po.id][0].notes" class="text-xs text-gray-500 mb-1 w-full truncate" :title="poReceives[po.id][0].notes">
                {{ poReceives[po.id][0].notes }}
              </div>
              <div class="flex flex-wrap gap-2">
                <div v-for="rec in poReceives[po.id]" :key="rec.id" class="w-16 h-16 rounded border bg-gray-100 flex flex-col items-center justify-center cursor-pointer overflow-hidden">
                  <div @click="openReceivePreview(rec.file_url, rec.file_type)" class="w-full h-full flex items-center justify-center">
                    <img v-if="rec.file_type && rec.file_type.startsWith('image/')" :src="rec.file_url" class="object-cover w-full h-full" />
                    <video v-else controls :src="rec.file_url" class="object-cover w-full h-full"></video>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Create/Edit PO Form Modal -->
        <TransitionRoot appear :show="showForm" as="template">
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

        <!-- Preview Modal -->
        <TransitionRoot appear :show="showPreview" as="template">
          <Dialog as="div" @close="closePreview" class="relative z-[99999]">
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
                  <DialogPanel class="w-full max-w-6xl transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-xl transition-all">
                    <div class="flex justify-between items-center p-4 border-b">
                      <h3 class="text-lg font-medium">Preview Purchase Order</h3>
                      <div class="flex gap-2">
                        <button 
                          @click="printPreview"
                          class="px-3 py-1 text-sm bg-blue-100 text-blue-600 rounded hover:bg-blue-200 flex items-center gap-1"
                        >
                          <i class="fas fa-print"></i>
                          Print
                        </button>
                        <button 
                          @click="closePreview" 
                          class="text-gray-400 hover:text-gray-600"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                    </div>
                    <div class="p-4" style="height: 80vh;">
                      <iframe 
                        :src="previewUrl" 
                        class="w-full h-full border-0" 
                        ref="previewFrame"
                      ></iframe>
                    </div>
                  </DialogPanel>
                </TransitionChild>
              </div>
            </div>
          </Dialog>
        </TransitionRoot>

        <!-- PO Detail Modal -->
        <PurchaseOrderDetailModal
          v-if="showDetailModal"
          :show="showDetailModal"
          :po-id="selectedPOId"
          :task-id="taskId"
          @close="closeDetailModal"
          @po-updated="loadPurchaseOrders"
        />

        <!-- Modal Upload Invoice -->
        <div v-if="showUploadInvoiceModal" class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/40">
          <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">
            <button @click="closeUploadInvoiceModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
              <i class="fas fa-times text-lg"></i>
            </button>
            <h3 class="text-lg font-medium mb-4">Upload Invoice</h3>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium mb-1">Nomor Invoice</label>
                <input v-model="invoiceNumber" type="text" class="w-full border rounded p-2" placeholder="Nomor Invoice" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Tanggal Invoice</label>
                <input v-model="invoiceDate" type="date" class="w-full border rounded p-2" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">File Invoice</label>
                <input type="file" accept="application/pdf,image/*" @change="onInvoiceFileChange" class="w-full" />
              </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
              <button @click="closeUploadInvoiceModal" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Batal</button>
              <button @click="handleUploadInvoice" :disabled="isUploadingInvoice" class="px-4 py-2 text-white bg-green-500 rounded hover:bg-green-600 flex items-center gap-2">
                <div v-if="isUploadingInvoice" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span>{{ isUploadingInvoice ? 'Mengupload...' : 'Upload' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Preview Invoice Modal -->
        <teleport to="body">
          <div v-if="showInvoicePreviewModal" class="fixed inset-0 z-[10001] flex items-center justify-center bg-black/60">
            <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-4 relative">
              <button @click="closeInvoicePreview" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-lg"></i>
              </button>
              <h3 class="text-lg font-medium mb-4">Preview Invoice</h3>
              <div v-if="previewInvoiceType === 'pdf'">
                <iframe :src="previewInvoiceUrl" class="w-full h-[70vh] border" />
              </div>
              <div v-else-if="previewInvoiceType === 'img'">
                <img :src="previewInvoiceUrl" class="max-w-full max-h-[70vh] mx-auto" />
              </div>
            </div>
          </div>
        </teleport>

        <!-- Good Receive Modal -->
        <GoodReceiveModal
          :show="showGoodReceiveModal"
          :po="selectedPOForReceive"
          @close="closeGoodReceiveModal"
          @uploaded="handleGoodReceiveUploaded"
        />

        <!-- Preview Receive Modal -->
        <teleport to="body">
          <div v-if="showReceivePreviewModal" class="fixed inset-0 z-[10001] flex items-center justify-center bg-black/60">
            <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-4 relative">
              <button @click="closeReceivePreview" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-lg"></i>
              </button>
              <h3 class="text-lg font-medium mb-4">Preview Good Receive</h3>
              <div v-if="previewReceiveType === 'img'">
                <img :src="previewReceiveUrl" class="max-w-full max-h-[70vh] mx-auto" />
              </div>
              <div v-else-if="previewReceiveType === 'video'">
                <video :src="previewReceiveUrl" controls class="max-w-full max-h-[70vh] mx-auto"></video>
              </div>
            </div>
          </div>
        </teleport>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogOverlay,
  DialogPanel,
} from '@headlessui/vue';
import Swal from 'sweetalert2';
import PurchaseOrderForm from './PurchaseOrderForm.vue';
import PurchaseOrderDetailModal from './PurchaseOrderDetailModal.vue';
import GoodReceiveModal from './GoodReceiveModal.vue';

const props = defineProps({
  show: Boolean,
  taskId: [String, Number],
});

const emit = defineEmits(['close']);

const loading = ref(false);
const loadingPRs = ref(false);
const isSaving = ref(false);
const pos = ref([]);
const prs = ref([]);
const showForm = ref(false);
const expandedPRs = ref([]);
const suppliers = ref([]);
const selectedSupplier = ref('');
const showPreview = ref(false);
const previewUrl = ref('');
const previewFrame = ref(null);
const showDetailModal = ref(false);
const selectedPOId = ref(null);
const showUploadInvoiceModal = ref(false);
const selectedPOForInvoice = ref(null);
const invoiceNumber = ref('');
const invoiceDate = ref('');
const invoiceFile = ref(null);
const isUploadingInvoice = ref(false);
const poInvoices = ref({}); // { [poId]: [invoice, ...] }
const showInvoicePreviewModal = ref(false);
const previewInvoiceUrl = ref('');
const previewInvoiceType = ref('');
const showGoodReceiveModal = ref(false);
const selectedPOForReceive = ref(null);
const poReceives = ref({}); // { [poId]: [receive, ...] }
const showReceivePreviewModal = ref(false);
const previewReceiveUrl = ref('');
const previewReceiveType = ref('');

// Fetch POs when modal is shown
watch(() => props.show, async (val) => {
  if (val) {
    await fetchPOs();
  }
});

// Fetch suppliers when form is shown
watch(showForm, async (val) => {
  if (val) {
    await Promise.all([
      fetchPRs(),
      fetchSuppliers()
    ]);
  }
});

async function fetchPOs() {
  loading.value = true;
  try {
    const res = await axios.get(`/api/maintenance-tasks/${props.taskId}/purchase-orders`);
    pos.value = res.data;
    // Fetch invoices & receives for each PO
    for (const po of pos.value) {
      await fetchInvoicesForPO(po.id);
      await fetchReceivesForPO(po.id);
    }
  } catch (error) {
    console.error('Error fetching POs:', error);
    Swal.fire('Error', 'Gagal mengambil data PO', 'error');
  } finally {
    loading.value = false;
  }
}

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
    await fetchPOs();
    await fetchPRs(); // Refresh PR list to remove converted ones
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
  showForm.value = false;
  selectedSupplier.value = '';
  expandedPRs.value = [];
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

async function previewPO(poId) {
  previewUrl.value = `/maintenance-po/${poId}/preview`;
  showPreview.value = true;
}

function closePreview() {
  showPreview.value = false;
  previewUrl.value = '';
}

function printPreview() {
  if (previewFrame.value) {
    previewFrame.value.contentWindow.print();
  }
}

async function editPO(poId) {
  Swal.fire('Edit PO', 'Fitur edit PO akan segera diimplementasikan.', 'info');
}

async function deletePO(poId) {
  const result = await Swal.fire({
    title: 'Hapus PO?',
    text: 'PO yang dihapus tidak dapat dikembalikan',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/api/maintenance-tasks/${props.taskId}/purchase-orders/${poId}`);
      await fetchPOs();
      Swal.fire('Sukses', 'PO berhasil dihapus', 'success');
    } catch (error) {
      console.error('Error deleting PO:', error);
      Swal.fire('Error', 'Gagal menghapus PO', 'error');
    }
  }
}

function showDetail(poId) {
  console.log('Opening detail modal for PO:', poId);
  selectedPOId.value = poId;
  showDetailModal.value = true;
}

function closeDetailModal() {
  console.log('Closing detail modal');
  showDetailModal.value = false;
  selectedPOId.value = null;
}

function handlePOCreated() {
  showForm.value = false;
  loadPurchaseOrders();
}

async function loadPurchaseOrders() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/maintenance-tasks/${props.taskId}/purchase-orders`);
    console.log('Loaded POs:', response.data);
    pos.value = response.data;
  } catch (error) {
    console.error('Error loading purchase orders:', error);
    Swal.fire('Error', 'Failed to load purchase orders', 'error');
  } finally {
    loading.value = false;
  }
}

function openUploadInvoiceModal(po) {
  selectedPOForInvoice.value = po;
  invoiceNumber.value = '';
  invoiceDate.value = '';
  invoiceFile.value = null;
  showUploadInvoiceModal.value = true;
}

function closeUploadInvoiceModal() {
  showUploadInvoiceModal.value = false;
  selectedPOForInvoice.value = null;
}

function onInvoiceFileChange(e) {
  // Defensive: only set if ref exists
  if (invoiceFile) invoiceFile.value = e.target.files && e.target.files[0] ? e.target.files[0] : null;
}

async function handleUploadInvoice() {
  if (!invoiceNumber.value || !invoiceDate.value || !invoiceFile.value) {
    Swal.fire('Error', 'Semua field wajib diisi', 'error');
    return;
  }
  isUploadingInvoice.value = true;
  try {
    const formData = new FormData();
    formData.append('invoice_number', invoiceNumber.value);
    formData.append('invoice_date', invoiceDate.value);
    formData.append('invoice_file', invoiceFile.value);
    await axios.post(`/api/purchase-orders/${selectedPOForInvoice.value.id}/invoices`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    Swal.fire('Sukses', 'Invoice berhasil diupload', 'success');
    closeUploadInvoiceModal();
    await fetchPOs();
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal upload invoice', 'error');
  } finally {
    isUploadingInvoice.value = false;
  }
}

async function fetchInvoicesForPO(poId) {
  try {
    const res = await axios.get(`/api/purchase-orders/${poId}/invoices`);
    poInvoices.value[poId] = res.data;
  } catch (e) {
    poInvoices.value[poId] = [];
  }
}

function openInvoicePreview(url) {
  previewInvoiceUrl.value = url;
  previewInvoiceType.value = url.endsWith('.pdf') ? 'pdf' : 'img';
  showInvoicePreviewModal.value = true;
}

function closeInvoicePreview() {
  showInvoicePreviewModal.value = false;
  previewInvoiceUrl.value = '';
  previewInvoiceType.value = '';
}

function openGoodReceiveModal(po) {
  selectedPOForReceive.value = po;
  showGoodReceiveModal.value = true;
}

function closeGoodReceiveModal() {
  showGoodReceiveModal.value = false;
  selectedPOForReceive.value = null;
}

function handleGoodReceiveUploaded() {
  fetchPOs();
}

async function fetchReceivesForPO(poId) {
  try {
    const res = await axios.get(`/api/purchase-orders/${poId}/receives`);
    poReceives.value[poId] = res.data;
  } catch (e) {
    poReceives.value[poId] = [];
  }
}

function openReceivePreview(url, type) {
  previewReceiveUrl.value = url;
  previewReceiveType.value = type.startsWith('image/') ? 'img' : 'video';
  showReceivePreviewModal.value = true;
}

function closeReceivePreview() {
  showReceivePreviewModal.value = false;
  previewReceiveUrl.value = '';
  previewReceiveType.value = '';
}
</script>

<style scoped>
.bg-blue-50 { background-color: #eff6ff; }
</style>

<style>
.swal2-container {
  z-index: 999999 !important;
}
</style> 