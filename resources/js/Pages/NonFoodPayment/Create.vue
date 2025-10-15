<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-plus-circle"></i> Buat Non Food Payment
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-1"></i> Kembali
        </button>
      </div>

      <!-- Step 1: Pilih Purchase Order -->
      <div v-if="!selectedPO" class="space-y-6">
        <!-- Available Purchase Orders -->
        <div v-if="availablePOs.length > 0" class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Pilih Purchase Order untuk Dibayar</h2>
          <div class="space-y-4">
            <div v-for="po in availablePOs" :key="po.id" class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition cursor-pointer" @click="selectPO(po)">
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="font-semibold text-gray-900">{{ po.number }}</div>
                  <div class="text-sm text-gray-600">{{ po.supplier_name }}</div>
                  <div class="text-sm text-gray-500">
                    {{ formatDate(po.date) }} - {{ formatCurrency(po.grand_total) }}
                  </div>
                  <div v-if="po.source_pr_number" class="text-xs text-blue-600 mt-1">
                    <i class="fa fa-link mr-1"></i>Source: {{ po.source_pr_number }}
                  </div>
                </div>
                <div class="text-right">
                  <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
                    <i class="fa fa-arrow-right mr-1"></i> Pilih
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Available Purchase Requisitions -->
        <div v-if="availablePRs.length > 0" class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Available Purchase Requisitions</h2>
          <div class="space-y-4">
            <div v-for="pr in availablePRs" :key="pr.id" class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-4">
                    <input 
                      type="radio" 
                      :id="`pr_${pr.id}`"
                      :value="pr.id" 
                      v-model="form.purchase_requisition_id"
                      class="text-blue-600 focus:ring-blue-500"
                    />
                    <label :for="`pr_${pr.id}`" class="cursor-pointer flex-1">
                      <div class="font-semibold text-gray-900">{{ pr.pr_number }}</div>
                      <div class="text-sm text-gray-600">{{ pr.title }}</div>
                      <div class="text-sm text-gray-500">{{ formatDate(pr.date) }} - {{ formatCurrency(pr.amount) }}</div>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="availablePOs.length === 0 && availablePRs.length === 0" class="bg-white rounded-2xl shadow-2xl p-6 text-center">
          <div class="text-gray-500">
            <i class="fa fa-inbox text-4xl mb-4"></i>
            <p>Tidak ada Purchase Order atau Purchase Requisition yang tersedia untuk dibayar.</p>
          </div>
        </div>
      </div>

      <!-- Step 2: Form Payment dengan Detail PO -->
      <form v-if="selectedPO" @submit.prevent="submitForm" class="space-y-6">
        <!-- PO Information -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Detail Purchase Order</h2>
            <button type="button" @click="selectedPO = null" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">PO Number</label>
              <p class="mt-1 text-lg font-semibold text-gray-900">{{ selectedPO.number }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Supplier</label>
              <p class="mt-1 text-gray-900">{{ selectedPO.supplier_name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">PO Date</label>
              <p class="mt-1 text-gray-900">{{ formatDate(selectedPO.date) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Total Amount</label>
              <p class="mt-1 text-lg font-bold text-green-600">{{ formatCurrency(selectedPO.grand_total) }}</p>
            </div>
            <div v-if="selectedPO.source_pr_number">
              <label class="block text-sm font-medium text-gray-700">Source PR</label>
              <p class="mt-1 text-gray-900">{{ selectedPO.source_pr_number }}</p>
            </div>
            <div v-if="selectedPO.pr_title">
              <label class="block text-sm font-medium text-gray-700">PR Title</label>
              <p class="mt-1 text-gray-900">{{ selectedPO.pr_title }}</p>
            </div>
            <div v-if="selectedPO.pr_description">
              <label class="block text-sm font-medium text-gray-700">PR Description</label>
              <p class="mt-1 text-gray-900">{{ selectedPO.pr_description }}</p>
            </div>
          </div>

          <!-- Attachments Section -->
          <div v-if="poAttachments && poAttachments.length > 0" class="bg-white rounded-2xl shadow-2xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Attachments</h3>
            
            <!-- PO Attachments -->
            <div v-if="poAttachments && poAttachments.length > 0" class="mb-6">
              <h4 class="text-md font-medium text-gray-700 mb-3">Purchase Order Attachments</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="attachment in poAttachments" :key="`po-${attachment.id}`" class="border border-gray-200 rounded-lg p-3">
                  <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                      <i v-if="isImageFile(attachment.file_name)" class="fa fa-image text-blue-500 text-xl"></i>
                      <i v-else class="fa fa-file text-gray-500 text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate">{{ attachment.file_name }}</p>
                      <p class="text-xs text-gray-500">{{ formatFileSize(attachment.file_size) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                      <button 
                        v-if="isImageFile(attachment.file_name)" 
                        @click="openLightbox(attachment.file_path, attachment.file_name)"
                        class="text-blue-600 hover:text-blue-800 text-sm"
                      >
                        <i class="fa fa-eye"></i>
                      </button>
                      <a 
                        v-else 
                        :href="attachment.file_path" 
                        target="_blank" 
                        class="text-blue-600 hover:text-blue-800 text-sm"
                      >
                        <i class="fa fa-download"></i>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- PO Items Grouped by Outlet -->
          <div v-if="itemsByOutlet && Object.keys(itemsByOutlet).length > 0">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Items per Outlet</h3>
            
            <div v-for="(outletData, outletId) in itemsByOutlet" :key="outletId" class="mb-6">
              <!-- Outlet Header -->
              <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 mb-3">
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="text-lg font-semibold text-blue-800">{{ outletData.outlet_name }}</h4>
                    <div class="text-sm text-blue-600 mt-1">
                      <span v-if="outletData.category_name" class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs mr-2">
                        {{ outletData.category_name }}
                      </span>
                      <span v-if="outletData.category_division" class="inline-block bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full text-xs mr-2">
                        {{ outletData.category_division }}
                      </span>
                      <span v-if="outletData.category_subcategory" class="inline-block bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs mr-2">
                        {{ outletData.category_subcategory }}
                      </span>
                      <span v-if="outletData.category_budget_type" class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                        {{ outletData.category_budget_type === 'GLOBAL' ? 'Global Budget' : 'Per Outlet Budget' }}
                      </span>
                    </div>
                    <div class="text-xs text-gray-600 mt-1">
                      <span v-if="outletData.pr_number">PR: {{ outletData.pr_number }}</span>
                      <span v-if="outletData.pr_title" class="ml-2">{{ outletData.pr_title }}</span>
                    </div>
                    <div v-if="outletData.pr_description" class="text-xs text-gray-700 mt-2 bg-gray-100 p-2 rounded">
                      <strong>Description:</strong> {{ outletData.pr_description }}
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-bold text-green-600">{{ formatCurrency(outletData.subtotal) }}</div>
                    <div class="text-xs text-gray-500">Subtotal</div>
                  </div>
                </div>
              </div>

              <!-- Items Table -->
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in outletData.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.quantity }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(item.price) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ formatCurrency(item.total) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- PR Attachments for this outlet -->
              <div v-if="outletData.pr_attachments && outletData.pr_attachments.length > 0" class="mt-4 bg-green-50 rounded-lg p-4">
                <h5 class="text-sm font-semibold text-green-800 mb-3">PR Attachments</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div v-for="attachment in outletData.pr_attachments" :key="`pr-${attachment.id}`" class="border border-green-200 rounded-lg p-3 bg-white">
                    <div class="flex items-center gap-3">
                      <div class="flex-shrink-0">
                        <i v-if="isImageFile(attachment.file_name)" class="fa fa-image text-green-500 text-xl"></i>
                        <i v-else class="fa fa-file text-gray-500 text-xl"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ attachment.file_name }}</p>
                        <p class="text-xs text-gray-500">{{ formatFileSize(attachment.file_size) }}</p>
                        <p v-if="attachment.pr_description" class="text-xs text-green-600 mt-1">{{ attachment.pr_description }}</p>
                      </div>
                      <div class="flex-shrink-0">
                        <button 
                          v-if="isImageFile(attachment.file_name)" 
                          @click="openLightbox(attachment.file_path, attachment.file_name)"
                          class="text-green-600 hover:text-green-800 text-sm"
                        >
                          <i class="fa fa-eye"></i>
                        </button>
                        <a 
                          v-else 
                          :href="attachment.file_path" 
                          target="_blank" 
                          class="text-green-600 hover:text-green-800 text-sm"
                        >
                          <i class="fa fa-download"></i>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Information -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Payment</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Amount (Auto-filled from PO) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Amount *</label>
              <input 
                type="number" 
                v-model="form.amount" 
                step="0.01"
                min="0"
                required 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                placeholder="0.00"
              />
            </div>

            <!-- Payment Method -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
              <select v-model="form.payment_method" required class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                <option value="">Pilih Payment Method</option>
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="check">Check</option>
              </select>
            </div>

            <!-- Payment Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Payment Date *</label>
              <input 
                type="date" 
                v-model="form.payment_date" 
                required 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>

            <!-- Due Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
              <input 
                type="date" 
                v-model="form.due_date" 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>

            <!-- Reference Number -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
              <input 
                type="text" 
                v-model="form.reference_number" 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                placeholder="Nomor referensi"
              />
            </div>
          </div>

          <!-- Description -->
          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea 
              v-model="form.description" 
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              placeholder="Deskripsi payment"
            ></textarea>
          </div>

          <!-- Notes -->
          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea 
              v-model="form.notes" 
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              placeholder="Catatan tambahan"
            ></textarea>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-4">
          <button type="button" @click="resetSelection" class="bg-gray-500 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            Kembali ke Pilih PO
          </button>
          <button type="submit" :disabled="isSubmitting" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold disabled:opacity-50">
            <i v-if="isSubmitting" class="fa fa-spinner fa-spin mr-2"></i>
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Payment' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Lightbox Modal -->
    <div v-if="lightboxVisible" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" @click="closeLightbox">
      <div class="relative max-w-4xl max-h-full p-4" @click.stop>
        <button @click="closeLightbox" class="absolute top-2 right-2 text-white text-2xl hover:text-gray-300 z-10">
          <i class="fa fa-times"></i>
        </button>
        <img 
          :src="lightboxImage?.path" 
          :alt="lightboxImage?.name"
          class="max-w-full max-h-full object-contain rounded-lg"
        />
        <div class="text-center text-white mt-2">
          <p class="text-sm">{{ lightboxImage?.name }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  suppliers: Array,
  availablePOs: Array,
  availablePRs: Array,
  filters: Object
});

const isSubmitting = ref(false);
const selectedPO = ref(null);
const poItems = ref([]);
const itemsByOutlet = ref({});
const loadingPOItems = ref(false);
const poAttachments = ref([]);
const lightboxImage = ref(null);
const lightboxVisible = ref(false);

const form = reactive({
  purchase_order_ops_id: null,
  purchase_requisition_id: null,
  supplier_id: '',
  amount: '',
  payment_method: '',
  payment_date: new Date().toISOString().split('T')[0],
  due_date: '',
  description: '',
  reference_number: '',
  notes: ''
});

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function formatFileSize(bytes) {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function isImageFile(filename) {
  if (!filename) return false;
  const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg'];
  const ext = filename.toLowerCase().substring(filename.lastIndexOf('.'));
  return imageExtensions.includes(ext);
}

function openLightbox(imagePath, imageName) {
  lightboxImage.value = {
    path: imagePath,
    name: imageName
  };
  lightboxVisible.value = true;
}

function closeLightbox() {
  lightboxVisible.value = false;
  lightboxImage.value = null;
}

function resetSelection() {
  selectedPO.value = null;
  poItems.value = [];
  itemsByOutlet.value = {};
  poAttachments.value = [];
  form.purchase_order_ops_id = null;
  form.purchase_requisition_id = null;
  form.supplier_id = '';
  form.amount = '';
}

async function selectPO(po) {
  selectedPO.value = po;
  form.purchase_order_ops_id = po.id;
  form.supplier_id = po.supplier_id;
  form.amount = po.grand_total;
  
  // Load PO items grouped by outlet
  loadingPOItems.value = true;
  try {
    const response = await axios.get(`/non-food-payments/po-items/${po.id}`);
    poItems.value = response.data.items || [];
    itemsByOutlet.value = response.data.items_by_outlet || {};
    poAttachments.value = response.data.po_attachments || [];
    
    // Update amount with total from API if available
    if (response.data.total_amount) {
      form.amount = response.data.total_amount;
    }
  } catch (error) {
    console.error('Error loading PO items:', error);
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Gagal memuat detail Purchase Order', 'error');
    });
  } finally {
    loadingPOItems.value = false;
  }
}

function submitForm() {
  // Validate that at least one transaction is selected
  if (!form.purchase_order_ops_id && !form.purchase_requisition_id) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Pilih minimal satu transaksi (Purchase Order atau Purchase Requisition).', 'error');
    });
    return;
  }

  isSubmitting.value = true;

  router.post('/non-food-payments', form, {
    onSuccess: () => {
      import('sweetalert2').then(({ default: Swal }) => {
        Swal.fire('Berhasil', 'Non Food Payment berhasil dibuat!', 'success');
      });
    },
    onError: (errors) => {
      console.error('Validation errors:', errors);
      import('sweetalert2').then(({ default: Swal }) => {
        Swal.fire('Error', 'Gagal membuat Non Food Payment. Periksa data yang diinput.', 'error');
      });
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function goBack() {
  router.get('/non-food-payments');
}
</script>
