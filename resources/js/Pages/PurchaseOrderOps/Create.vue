<script setup>
import { ref, onMounted, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const prList = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const generatingPO = ref(false);
const expandedPRs = ref({});
const notes = ref('');

// Form untuk generate PO
const poForm = useForm({
    items_by_supplier: {}, // Akan diisi array per item
    ppn_enabled: false, // PPN switch
});

// Fetch PR list yang belum di-PO
const fetchPRList = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/pr-ops/available');
        const filtered = (response.data || []).filter(pr => {
            const mode = (pr.mode || 'pr_ops').toString().toLowerCase();
            const status = (pr.status || '').toString().toUpperCase();
            return mode === 'pr_ops' && status === 'APPROVED';
        });
        prList.value = filtered.map(pr => ({
            ...pr,
            items: pr.items.map(item => {
                if (!poForm.items_by_supplier[item.id]) {
                    // Default: satu baris, qty penuh
                    poForm.items_by_supplier[item.id] = [{
                        supplier_id: null,
                        qty: item.qty,
                        price: '',
                        last_price: '',
                        min_price: '',
                        max_price: ''
                    }];
                }
                return { ...item };
            })
        }));
    } catch (error) {
        console.error('Error fetching PR list:', error);
        Swal.fire('Error', 'Failed to fetch PR list', 'error');
    } finally {
        loading.value = false;
    }
};

// Fetch suppliers
const fetchSuppliers = async () => {
    try {
        const response = await axios.get('/api/suppliers');
        suppliers.value = response.data;
    } catch (error) {
        console.error('Error fetching suppliers:', error);
    }
};

// Toggle expand/collapse PR
const togglePR = (prId) => {
    expandedPRs.value[prId] = !expandedPRs.value[prId];
};

// Add item row untuk supplier tertentu
const addItemRow = (itemId, supplierId) => {
    if (!poForm.items_by_supplier[itemId]) {
        poForm.items_by_supplier[itemId] = [];
    }
    
    poForm.items_by_supplier[itemId].push({
        supplier_id: supplierId,
        qty: 0,
        price: '',
        last_price: '',
        min_price: '',
        max_price: ''
    });
};

// Remove item row
const removeItemRow = (itemId, index) => {
    if (poForm.items_by_supplier[itemId] && poForm.items_by_supplier[itemId].length > 1) {
        poForm.items_by_supplier[itemId].splice(index, 1);
    }
};

// Removed getLastPrice function as requested

// Calculate total untuk item
const calculateItemTotal = (itemId, index) => {
    const item = poForm.items_by_supplier[itemId][index];
    if (item && item.qty && item.price) {
        return parseFloat(item.qty) * parseFloat(item.price);
    }
    return 0;
};

// Calculate grand total
const grandTotal = computed(() => {
    let total = 0;
    Object.values(poForm.items_by_supplier).forEach(items => {
        items.forEach(item => {
            if (item.qty && item.price) {
                total += parseFloat(item.qty) * parseFloat(item.price);
            }
        });
    });
    
    if (poForm.ppn_enabled) {
        total += total * 0.11; // 11% PPN
    }
    
    return total;
});

// Submit form
const submitForm = async () => {
    try {
        generatingPO.value = true;
        
        // Validate form
        const itemsBySupplier = {};
        let hasItems = false;
        
        Object.keys(poForm.items_by_supplier).forEach(itemId => {
            poForm.items_by_supplier[itemId].forEach(item => {
                if (item.supplier_id && item.qty && item.price) {
                    hasItems = true;
                    // Extract supplier ID (handle both object and integer)
                    const supplierId = typeof item.supplier_id === 'object' ? item.supplier_id.id : item.supplier_id;
                    
                    if (!itemsBySupplier[supplierId]) {
                        itemsBySupplier[supplierId] = [];
                    }
                    
                    // Find original item data
                    const originalItem = prList.value
                        .flatMap(pr => pr.items)
                        .find(i => i.id == itemId);
                    
                    itemsBySupplier[supplierId].push({
                        id: itemId,
                        supplier_id: supplierId,
                        qty: parseFloat(item.qty),
                        price: parseFloat(item.price),
                        pr_id: originalItem?.pr_id,
                        item_name: originalItem?.item_name,
                        unit: originalItem?.unit,
                        arrival_date: originalItem?.arrival_date
                    });
                }
            });
        });
        
        if (!hasItems) {
            Swal.fire('Error', 'Please select at least one item with supplier, quantity, and price', 'error');
            return;
        }
        
        poForm.items_by_supplier = itemsBySupplier;
        poForm.notes = notes.value;
        
        const response = await axios.post('/po-ops/generate', poForm.data());
        
        if (response.data.success) {
            Swal.fire('Success', 'Purchase Orders created successfully!', 'success');
            // Use window.location instead of router.visit
            window.location.href = '/po-ops';
        } else {
            Swal.fire('Error', response.data.message || 'Failed to create PO', 'error');
        }
    } catch (error) {
        console.error('Error creating PO:', error);
        Swal.fire('Error', error.response?.data?.message || 'Failed to create PO', 'error');
    } finally {
        generatingPO.value = false;
    }
};

// File handling functions
const isImageFile = (fileName) => {
    if (!fileName) return false;
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    const extension = fileName.split('.').pop().toLowerCase();
    return imageExtensions.includes(extension);
};

const getFileIcon = (fileName) => {
    if (!fileName) return 'fa-file';
    const extension = fileName.split('.').pop().toLowerCase();
    const iconMap = {
        'pdf': 'fa-file-pdf text-red-500',
        'doc': 'fa-file-word text-blue-500',
        'docx': 'fa-file-word text-blue-500',
        'xls': 'fa-file-excel text-green-500',
        'xlsx': 'fa-file-excel text-green-500',
        'ppt': 'fa-file-powerpoint text-orange-500',
        'pptx': 'fa-file-powerpoint text-orange-500',
        'jpg': 'fa-file-image text-purple-500',
        'jpeg': 'fa-file-image text-purple-500',
        'png': 'fa-file-image text-purple-500',
        'gif': 'fa-file-image text-purple-500',
        'txt': 'fa-file-alt text-gray-500',
        'zip': 'fa-file-archive text-yellow-500',
        'rar': 'fa-file-archive text-yellow-500',
        'webp': 'fa-file-image text-purple-500',
        'bmp': 'fa-file-image text-purple-500',
    };
    return iconMap[extension] || 'fa-file text-gray-500';
};

const formatFileSize = (bytes) => {
    if (!bytes) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const downloadFile = (attachment) => {
    window.open(`/purchase-requisitions/attachments/${attachment.id}/download`, '_blank');
};

// Lightbox state
const showLightbox = ref(false);
const lightboxImage = ref(null);

const openLightbox = (attachment) => {
    if (isImageFile(attachment.file_name)) {
        lightboxImage.value = attachment;
        showLightbox.value = true;
    }
};

const closeLightbox = () => {
    showLightbox.value = false;
    lightboxImage.value = null;
};

onMounted(() => {
    fetchPRList();
    fetchSuppliers();
});

// Mode selector: 'pr_ops' or 'purchase_payment'
const mode = ref('pr_ops');
</script>

<template>
  <AppLayout title="Create Purchase Order Ops">
    <div class="w-full py-8 px-4 relative">
      <!-- Loading Overlay -->
      <div v-if="generatingPO" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 rounded-lg">
        <div class="bg-white p-6 rounded-lg shadow-lg flex items-center space-x-3">
          <i class="fas fa-spinner fa-spin text-blue-500 text-xl"></i>
          <span class="text-gray-700 font-medium">Generating Purchase Orders...</span>
        </div>
      </div>
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-plus text-blue-500"></i> Create Purchase Order Ops
        </h1>
        <Link
          href="/po-ops"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i>
          Back to List
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <!-- Mode Switch -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
          <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
            <select v-model="mode" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="pr_ops">Purchase Requisition Ops</option>
              <option value="purchase_payment">Purchase Payment</option>
            </select>
          </div>
          <div v-if="mode === 'purchase_payment'" class="md:col-span-2 p-3 bg-yellow-50 border border-yellow-200 rounded">
            <div class="text-sm text-yellow-800">
              Anda memilih mode Purchase Payment. Jika Anda ingin melakukan pembayaran atas PO yang sudah dibuat, silakan lanjutkan ke halaman pembayaran.
            </div>
            <div class="mt-2">
              <a href="/purchase-payments" class="inline-flex items-center px-3 py-1 text-sm bg-yellow-600 text-white rounded hover:bg-yellow-700">
                <i class="fa-solid fa-credit-card mr-2"></i> Buka Halaman Purchase Payment
              </a>
            </div>
          </div>
        </div>

        <div v-if="mode === 'pr_ops'">
        <!-- PPN Toggle -->
        <div class="mb-6">
          <label class="flex items-center">
            <input
              type="checkbox"
              v-model="poForm.ppn_enabled"
              class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
            />
            <span class="ml-2 text-sm text-gray-700">Enable PPN (11%)</span>
          </label>
        </div>

        <!-- Notes -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
          <textarea
            v-model="notes"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Enter notes..."
          ></textarea>
        </div>

        <!-- PR List -->
        <div v-if="loading" class="text-center py-8">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
          <p class="mt-2 text-gray-600">Loading PR data...</p>
        </div>

        <div v-else-if="prList.length === 0" class="text-center py-8">
          <p class="text-gray-600">No available PR found</p>
        </div>

        <div v-else class="space-y-6">
          <div v-for="pr in prList" :key="pr.id" class="border border-gray-200 rounded-lg">
            <!-- PR Header -->
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                  <button
                    @click="togglePR(pr.id)"
                    class="text-blue-600 hover:text-blue-800"
                  >
                    <i :class="expandedPRs[pr.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                  </button>
                  <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ pr.number }}</h3>
                    <p class="text-sm text-gray-600">{{ pr.title }} - {{ pr.division_name }}</p>
                    <p class="text-sm text-gray-500">Amount: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(pr.amount) }}</p>
                  </div>
                </div>
                <div class="text-sm text-gray-500">
                  {{ new Date(pr.date).toLocaleDateString('id-ID') }}
                </div>
              </div>
            </div>

            <!-- PR Items -->
            <div v-if="expandedPRs[pr.id]" class="p-4">
              <!-- Attachments Section -->
              <div v-if="pr.attachments && pr.attachments.length > 0" class="mb-6 p-4 bg-blue-50 rounded-lg">
                <h4 class="text-md font-semibold text-gray-900 mb-3 flex items-center">
                  <i class="fa fa-paperclip mr-2 text-blue-500"></i>
                  Purchase Requisition Attachments
                  <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                    {{ pr.attachments.length }}
                  </span>
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div
                    v-for="attachment in pr.attachments"
                    :key="attachment.id"
                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    <div class="flex items-center space-x-3 flex-1">
                      <!-- Image Thumbnail -->
                      <div v-if="isImageFile(attachment.file_name)" class="relative">
                        <img
                          :src="`/purchase-requisitions/attachments/${attachment.id}/view`"
                          :alt="attachment.file_name"
                          class="w-10 h-10 object-cover rounded border border-gray-300 cursor-pointer hover:opacity-80 transition-opacity"
                          @click="openLightbox(attachment)"
                          @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='block'"
                        />
                        <i :class="getFileIcon(attachment.file_name)" class="text-sm absolute inset-0 flex items-center justify-center bg-gray-100 rounded" style="display: none;"></i>
                      </div>
                      <!-- File Icon for non-images -->
                      <i v-else :class="getFileIcon(attachment.file_name)" class="text-lg text-gray-500"></i>
                      
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ attachment.file_name }}</p>
                        <div class="flex items-center space-x-2 text-xs text-gray-500">
                          <span>{{ formatFileSize(attachment.file_size) }}</span>
                          <span>•</span>
                          <span>{{ attachment.uploader?.nama_lengkap || 'Unknown' }}</span>
                        </div>
                      </div>
                    </div>
                    <div class="flex items-center space-x-1">
                      <button
                        v-if="isImageFile(attachment.file_name)"
                        @click="openLightbox(attachment)"
                        class="p-1 text-green-600 hover:text-green-800 hover:bg-green-100 rounded transition-colors"
                        title="View Image"
                      >
                        <i class="fa fa-eye text-xs"></i>
                      </button>
                      <button
                        @click="downloadFile(attachment)"
                        class="p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded transition-colors"
                        title="Download"
                      >
                        <i class="fa fa-download text-xs"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="space-y-4">
                <div v-for="item in pr.items" :key="item.id" class="border border-gray-200 rounded-lg p-4">
                  <div class="flex items-center justify-between mb-3">
                    <div>
                      <h4 class="font-medium text-gray-900">{{ item.item_name }}</h4>
                      <p class="text-sm text-gray-600">Qty: {{ item.qty }} {{ item.unit }} | Unit Price: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(item.unit_price) }}</p>
                    </div>
                    <button
                      @click="addItemRow(item.id, null)"
                      class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600"
                    >
                      <i class="fas fa-plus mr-1"></i> Add Row
                    </button>
                  </div>

                  <!-- Item Rows -->
                  <div class="space-y-3">
                    <div v-for="(row, index) in poForm.items_by_supplier[item.id]" :key="index" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                      <!-- Supplier -->
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Supplier</label>
                        <Multiselect
                          v-model="row.supplier_id"
                          :options="suppliers"
                          :custom-label="supplier => supplier.name"
                          placeholder="Select Supplier"
                          class="text-sm"
                        />
                      </div>

                      <!-- Quantity -->
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Qty</label>
                        <input
                          type="number"
                          v-model.number="row.qty"
                          min="0"
                          step="0.01"
                          class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                      </div>

                      <!-- Price -->
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Price</label>
                        <input
                          type="number"
                          v-model.number="row.price"
                          min="0"
                          step="0.01"
                          class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                      </div>

                      <!-- Last Price Info -->
                      <div v-if="row.last_price" class="text-xs text-gray-600">
                        <div>Last: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(row.last_price) }}</div>
                        <div>Min: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(row.min_price) }}</div>
                        <div>Max: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(row.max_price) }}</div>
                      </div>

                      <!-- Total -->
                      <div class="text-sm font-medium">
                        <div class="text-xs text-gray-600">Total</div>
                        <div>{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(calculateItemTotal(item.id, index)) }}</div>
                      </div>

                      <!-- Actions -->
                      <div class="flex space-x-1">
                        <button
                          v-if="poForm.items_by_supplier[item.id].length > 1"
                          @click="removeItemRow(item.id, index)"
                          class="text-red-600 hover:text-red-800"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Grand Total -->
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
          <div class="flex justify-between items-center">
            <span class="text-lg font-semibold">Grand Total:</span>
            <span class="text-xl font-bold text-blue-600">
              {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(grandTotal) }}
            </span>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="mt-6 flex justify-end">
          <button
            @click="submitForm"
            :disabled="generatingPO"
            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
          >
            <i v-if="generatingPO" class="fas fa-spinner fa-spin mr-2"></i>
            <i v-else class="fas fa-plus mr-2"></i>
            {{ generatingPO ? 'Generating...' : 'Generate Purchase Orders' }}
          </button>
        </div>
        </div>
      </div>
    </div>

    <!-- Lightbox Modal for Images -->
    <div v-if="showLightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeLightbox">
      <div class="relative max-w-4xl max-h-full p-4" @click.stop>
        <button
          @click="closeLightbox"
          class="absolute top-2 right-2 z-10 p-2 text-white bg-black bg-opacity-50 rounded-full hover:bg-opacity-75 transition-colors"
        >
          <i class="fa fa-times text-xl"></i>
        </button>
        <img
          v-if="lightboxImage"
          :src="`/purchase-requisitions/attachments/${lightboxImage.id}/view`"
          :alt="lightboxImage.file_name"
          class="max-w-full max-h-full object-contain rounded-lg"
        />
        <div class="absolute bottom-4 left-4 right-4 text-center">
          <p class="text-white bg-black bg-opacity-50 px-3 py-1 rounded-lg text-sm">
            {{ lightboxImage?.file_name }}
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
