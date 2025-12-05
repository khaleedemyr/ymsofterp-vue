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
const showTutorial = ref(false);

// Form untuk generate PO
const poForm = useForm({
    items_by_supplier: {}, // Akan diisi array per item
    ppn_enabled: false, // PPN switch
    discount_total_percent: 0, // Discount total percent
    discount_total_amount: 0, // Discount total amount
    payment_type: 'lunas', // Payment type: 'lunas' or 'termin'
    payment_terms: '', // Payment terms description (for termin)
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
        prList.value = filtered.map(pr => {
            // Ensure is_held and hold_reason are properly set
            const isHeld = pr.is_held === true || pr.is_held === 1 || pr.is_held === '1' || pr.is_held === 'true';
            const prData = {
                ...pr,
                is_held: isHeld,
                hold_reason: pr.hold_reason || null,
                items: pr.items.map(item => {
                    if (!poForm.items_by_supplier[item.id]) {
                        // Default: satu baris, qty penuh
                        poForm.items_by_supplier[item.id] = [{
                            supplier_id: null,
                            qty: item.qty,
                            price: '',
                            discount_percent: 0,
                            discount_amount: 0,
                            last_price: '',
                            min_price: '',
                            max_price: ''
                        }];
                    }
                    return { ...item };
                })
            };
            // Debug log for held PRs
            if (isHeld) {
                console.log('Held PR found:', prData.number, 'Reason:', prData.hold_reason);
            }
            return prData;
        });
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
    // Prevent expanding PRs that are on hold
    const pr = prList.value.find(p => p.id === prId);
    if (pr && pr.is_held) {
        Swal.fire('Warning', 'PR yang sedang di-hold tidak dapat di-expand. Silakan release PR terlebih dahulu.', 'warning');
        return;
    }
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
        discount_percent: 0,
        discount_amount: 0,
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
        const subtotal = parseFloat(item.qty) * parseFloat(item.price);
        const discount = parseFloat(item.discount_amount || 0);
        return subtotal - discount;
    }
    return 0;
};

// Calculate item discount amount from percent
function calculateItemDiscount(itemId, index) {
    const item = poForm.items_by_supplier[itemId][index];
    if (item && item.discount_percent > 0 && item.price && item.qty) {
        const subtotal = parseFloat(item.price) * parseFloat(item.qty);
        item.discount_amount = subtotal * (item.discount_percent / 100);
    } else if (item && item.discount_percent === 0) {
        item.discount_amount = 0;
    }
}

// Calculate item discount percent from amount
function calculateItemDiscountPercent(itemId, index) {
    const item = poForm.items_by_supplier[itemId][index];
    if (item && item.discount_amount > 0 && item.price && item.qty) {
        const subtotal = parseFloat(item.price) * parseFloat(item.qty);
        item.discount_percent = (item.discount_amount / subtotal) * 100;
    } else if (item && item.discount_amount === 0) {
        item.discount_percent = 0;
    }
}

// Calculate subtotal before discount
const subtotalBeforeDiscount = computed(() => {
    let total = 0;
    Object.values(poForm.items_by_supplier).forEach(items => {
        items.forEach(item => {
            if (item.qty && item.price) {
                total += parseFloat(item.qty) * parseFloat(item.price);
            }
        });
    });
    return total;
});

// Calculate total item discount
const totalItemDiscount = computed(() => {
    let totalDiscount = 0;
    Object.values(poForm.items_by_supplier).forEach(items => {
        items.forEach(item => {
            if (item.qty && item.price) {
                const itemSubtotal = parseFloat(item.qty) * parseFloat(item.price);
                const discountPercent = parseFloat(item.discount_percent || 0);
                const discountAmount = parseFloat(item.discount_amount || 0);
                
                if (discountPercent > 0) {
                    totalDiscount += itemSubtotal * (discountPercent / 100);
                } else {
                    totalDiscount += discountAmount;
                }
            }
        });
    });
    return totalDiscount;
});

// Calculate subtotal after item discount
const subtotalAfterItemDiscount = computed(() => {
    return subtotalBeforeDiscount.value - totalItemDiscount.value;
});

// Calculate total discount amount
const totalDiscountAmount = computed(() => {
    const discountTotalPercent = parseFloat(poForm.discount_total_percent || 0);
    const discountTotalAmount = parseFloat(poForm.discount_total_amount || 0);
    
    if (discountTotalPercent > 0) {
        return subtotalAfterItemDiscount.value * (discountTotalPercent / 100);
    }
    return discountTotalAmount;
});

// Calculate subtotal after discount total
const subtotalAfterDiscountTotal = computed(() => {
    return subtotalAfterItemDiscount.value - totalDiscountAmount.value;
});

// Calculate grand total
const grandTotal = computed(() => {
    let total = subtotalAfterDiscountTotal.value;
    
    if (poForm.ppn_enabled) {
        total += total * 0.11; // 11% PPN
    }
    
    return total;
});

// Calculate total discount amount from percent
function calculateTotalDiscount() {
    if (poForm.discount_total_percent > 0 && subtotalAfterItemDiscount.value > 0) {
        poForm.discount_total_amount = subtotalAfterItemDiscount.value * (poForm.discount_total_percent / 100);
    } else if (poForm.discount_total_percent === 0) {
        poForm.discount_total_amount = 0;
    }
}

// Calculate total discount percent from amount
function calculateTotalDiscountPercent() {
    if (poForm.discount_total_amount > 0 && subtotalAfterItemDiscount.value > 0) {
        poForm.discount_total_percent = (poForm.discount_total_amount / subtotalAfterItemDiscount.value) * 100;
    } else if (poForm.discount_total_amount === 0) {
        poForm.discount_total_percent = 0;
    }
}

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
                    // Find original item data and check if PR is held
                    const originalItem = prList.value
                        .flatMap(pr => pr.items)
                        .find(i => i.id == itemId);
                    
                    // Find PR that contains this item
                    const parentPR = prList.value.find(pr => 
                        pr.items && pr.items.some(i => i.id == itemId)
                    );
                    
                    // Skip items from held PRs
                    if (parentPR && parentPR.is_held) {
                        return;
                    }
                    
                    hasItems = true;
                    // Extract supplier ID (handle both object and integer)
                    const supplierId = typeof item.supplier_id === 'object' ? item.supplier_id.id : item.supplier_id;
                    
                    if (!itemsBySupplier[supplierId]) {
                        itemsBySupplier[supplierId] = [];
                    }
                    
                    itemsBySupplier[supplierId].push({
                        id: itemId,
                        supplier_id: supplierId,
                        qty: parseFloat(item.qty),
                        price: parseFloat(item.price),
                        discount_percent: parseFloat(item.discount_percent || 0),
                        discount_amount: parseFloat(item.discount_amount || 0),
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

// Group attachments by outlet (for new structure with outlet_id)
const getGroupedAttachments = (attachments) => {
    if (!attachments || attachments.length === 0) return {};
    
    const grouped = {};
    attachments.forEach(attachment => {
        // For legacy data without outlet_id, use 'no-outlet'
        const outletId = attachment.outlet_id || 'no-outlet';
        const outletName = attachment.outlet?.nama_outlet || 'General';
        
        if (!grouped[outletId]) {
            grouped[outletId] = {
                outlet_id: outletId,
                outlet_name: outletName,
                attachments: []
            };
        }
        
        grouped[outletId].attachments.push(attachment);
    });
    
    return grouped;
};

onMounted(() => {
    fetchPRList();
    fetchSuppliers();
});
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
        <!-- Discount Total -->
        <div class="mb-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
          <h4 class="text-sm font-semibold text-yellow-800 mb-3">Diskon Total</h4>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Diskon (%)</label>
              <input type="number" v-model.number="poForm.discount_total_percent" min="0" max="100" step="0.01" placeholder="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" @input="calculateTotalDiscount" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Diskon (Rp)</label>
              <input type="number" v-model.number="poForm.discount_total_amount" min="0" step="0.01" placeholder="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500" @input="calculateTotalDiscountPercent" />
            </div>
          </div>
        </div>
        <!-- Payment Type -->
        <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-blue-800">Metode Pembayaran</h4>
            <button
              type="button"
              @click="showTutorial = true"
              class="text-blue-600 hover:text-blue-800 text-xs font-medium flex items-center gap-1"
            >
              <i class="fa fa-question-circle"></i>
              <span class="text-red-600 font-semibold">Klik disini untuk tutorial</span>
            </button>
          </div>
          <div class="space-y-3">
            <div class="flex items-center space-x-4">
              <label class="flex items-center">
                <input
                  type="radio"
                  v-model="poForm.payment_type"
                  value="lunas"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                />
                <span class="ml-2 text-sm text-gray-700">Bayar Lunas</span>
              </label>
              <label class="flex items-center">
                <input
                  type="radio"
                  v-model="poForm.payment_type"
                  value="termin"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                />
                <span class="ml-2 text-sm text-gray-700">Termin Bayar</span>
              </label>
            </div>
            <!-- Payment Terms Input (only show if termin selected) -->
            <div v-if="poForm.payment_type === 'termin'">
              <label class="block text-sm font-medium text-gray-700 mb-1">Detail Termin Pembayaran</label>
              <textarea
                v-model="poForm.payment_terms"
                rows="2"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Contoh: 50% DP saat PO, 50% saat barang diterima"
              ></textarea>
            </div>
          </div>
        </div>

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
          <div v-for="pr in prList" :key="pr.id" class="border border-gray-200 rounded-lg" :class="pr.is_held ? 'opacity-60' : ''">
            <!-- PR Header -->
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200" :class="pr.is_held ? 'bg-red-50' : ''">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4 flex-1">
                  <button
                    @click="pr.is_held ? null : togglePR(pr.id)"
                    :class="pr.is_held ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:text-blue-800'"
                    :disabled="pr.is_held"
                  >
                    <i :class="expandedPRs[pr.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                  </button>
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <h3 class="text-lg font-semibold text-gray-900">{{ pr.number }}</h3>
                      <span v-if="pr.is_held" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        <i class="fas fa-lock mr-1"></i>
                        ON HOLD
                      </span>
                    </div>
                    <p class="text-sm text-gray-600">{{ pr.title }} - {{ pr.division_name }}</p>
                    <p class="text-sm text-gray-500">Amount: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(pr.amount) }}</p>
                    <p v-if="pr.is_held && pr.hold_reason" class="text-sm text-red-600 mt-1">
                      <i class="fas fa-info-circle mr-1"></i>
                      {{ pr.hold_reason }}
                    </p>
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
                
                <!-- Group attachments by outlet if applicable -->
                <template v-if="pr.attachments.some(att => att.outlet)">
                  <div v-for="(outletGroup, outletId) in getGroupedAttachments(pr.attachments)" :key="outletId" class="mb-4 last:mb-0">
                    <h5 v-if="outletId !== 'no-outlet'" class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                      <i class="fa fa-store mr-2 text-blue-500"></i>
                      Outlet: {{ outletGroup.outlet_name }}
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                      <div
                        v-for="attachment in outletGroup.attachments"
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
                </template>
                
                <!-- Simple list for attachments without outlet grouping -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
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

              <div v-if="!pr.is_held" class="space-y-4">
                <div v-for="item in pr.items" :key="item.id" class="border border-gray-200 rounded-lg p-4">
                  <div class="flex items-center justify-between mb-3">
                    <div class="flex-1">
                      <h4 class="font-medium text-gray-900">{{ item.item_name }}</h4>
                      <p class="text-sm text-gray-600">Qty: {{ item.qty }} {{ item.unit }} | Unit Price: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(item.unit_price) }}</p>
                      <!-- Outlet and Category info (for new structure, with fallback to PR outlet/category for legacy data) -->
                      <div v-if="item.outlet || item.category || pr.outlet || pr.category" class="flex flex-wrap gap-2 mt-2">
                        <span v-if="item.outlet || pr.outlet" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                          <i class="fa fa-store mr-1"></i>
                          Outlet: {{ (item.outlet || pr.outlet)?.nama_outlet }}
                        </span>
                        <span v-if="item.category || pr.category" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                          <i class="fa fa-tag mr-1"></i>
                          Category: {{ (item.category || pr.category)?.name }}
                        </span>
                      </div>
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
                    <div v-for="(row, index) in poForm.items_by_supplier[item.id]" :key="index" class="grid grid-cols-1 md:grid-cols-8 gap-3 items-end">
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

                      <!-- Discount Percent -->
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Diskon %</label>
                        <input
                          type="number"
                          v-model.number="row.discount_percent"
                          min="0"
                          max="100"
                          step="0.01"
                          @input="calculateItemDiscount(item.id, index)"
                          class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                          placeholder="%"
                        />
                      </div>

                      <!-- Discount Amount -->
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Diskon (Rp)</label>
                        <input
                          type="number"
                          v-model.number="row.discount_amount"
                          min="0"
                          step="0.01"
                          @input="calculateItemDiscountPercent(item.id, index)"
                          class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                          placeholder="Rp"
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

        <!-- Total Calculation -->
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
          <h3 class="text-lg font-semibold mb-3">Total Calculation</h3>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-gray-600">Subtotal:</span>
              <span class="font-medium">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(subtotalBeforeDiscount) }}</span>
            </div>
            <div v-if="totalItemDiscount > 0" class="flex justify-between">
              <span class="text-gray-600">Diskon per Item:</span>
              <span class="font-medium text-red-600">- {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(totalItemDiscount) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Subtotal setelah Diskon Item:</span>
              <span class="font-medium">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(subtotalAfterItemDiscount) }}</span>
            </div>
            <div v-if="totalDiscountAmount > 0" class="flex justify-between">
              <span class="text-gray-600">Diskon Total:</span>
              <span class="font-medium text-red-600">- {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(totalDiscountAmount) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Subtotal setelah Diskon Total:</span>
              <span class="font-medium">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(subtotalAfterDiscountTotal) }}</span>
            </div>
            <div v-if="poForm.ppn_enabled" class="flex justify-between">
              <span class="text-gray-600">PPN (11%):</span>
              <span class="font-medium text-blue-600">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format((subtotalAfterDiscountTotal * 0.11)) }}</span>
            </div>
            <div class="flex justify-between border-t pt-2">
            <span class="text-lg font-semibold">Grand Total:</span>
              <span class="text-xl font-bold text-green-600">
              {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(grandTotal) }}
            </span>
            </div>
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

    <!-- Tutorial Modal -->
    <div v-if="showTutorial" class="fixed inset-0 z-50 overflow-y-auto" @click.self="showTutorial = false">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showTutorial = false"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
          <!-- Header -->
          <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">
              <i class="fa fa-graduation-cap mr-2"></i>
              Tutorial: Metode Pembayaran (Payment Type)
            </h3>
            <button
              @click="showTutorial = false"
              class="text-white hover:text-gray-200 focus:outline-none"
            >
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>

          <!-- Content -->
          <div class="px-6 py-6 max-h-[70vh] overflow-y-auto">
            <div class="space-y-6">
              <!-- Introduction -->
              <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                <h4 class="font-semibold text-blue-800 mb-2">
                  <i class="fa fa-info-circle mr-2"></i>
                  Apa itu Metode Pembayaran?
                </h4>
                <p class="text-sm text-blue-700">
                  Metode pembayaran menentukan cara pembayaran untuk Purchase Order ini. Sistem mendukung dua jenis:
                  <strong>Bayar Lunas</strong> (pembayaran penuh sekaligus) dan <strong>Termin Bayar</strong> (pembayaran bertahap).
                  Pilihan ini akan mempengaruhi cara pembayaran di menu Non Food Payment nantinya.
                </p>
              </div>

              <!-- Step 1: Bayar Lunas -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
                    1
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">
                      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">
                        Bayar Lunas
                      </span>
                      Pembayaran Penuh Sekaligus
                    </h4>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Pilih opsi <strong>"Bayar Lunas"</strong> untuk pembayaran penuh sekaligus</li>
                      <li>Cocok untuk pembayaran langsung setelah barang diterima</li>
                      <li>Di menu Non Food Payment, hanya bisa membuat <strong>1x pembayaran</strong> dengan amount = Grand Total PO</li>
                      <li>Tidak perlu mengisi detail termin</li>
                    </ul>
                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded mt-2">
                      <p class="text-xs text-green-800">
                        <i class="fa fa-check-circle mr-1"></i>
                        <strong>Contoh:</strong> PO dengan Grand Total Rp 10.000.000 → Payment 1x sebesar Rp 10.000.000
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 2: Termin Bayar -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    2
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">
                      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">
                        Termin Bayar
                      </span>
                      Pembayaran Bertahap
                    </h4>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Pilih opsi <strong>"Termin Bayar"</strong> untuk pembayaran bertahap</li>
                      <li>Cocok untuk pembayaran yang dibagi menjadi beberapa termin</li>
                      <li>Di menu Non Food Payment, bisa membuat <strong>multiple payments</strong> sampai lunas</li>
                      <li><strong>Wajib</strong> mengisi "Detail Termin Pembayaran"</li>
                    </ul>
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded mt-2">
                      <p class="text-xs text-blue-800 mb-2">
                        <i class="fa fa-info-circle mr-1"></i>
                        <strong>Detail Termin Pembayaran:</strong>
                      </p>
                      <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside ml-4">
                        <li>Contoh: "50% di muka, 50% setelah barang diterima"</li>
                        <li>Contoh: "30% di muka, 40% saat pengiriman, 30% setelah diterima"</li>
                        <li>Detail ini akan muncul di menu Non Food Payment sebagai referensi</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 3: Flow di Non Food Payment -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    3
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Dampak di Menu Non Food Payment</h4>
                    <div class="space-y-3">
                      <div class="bg-gray-50 p-3 rounded border border-gray-200">
                        <p class="text-sm font-medium text-gray-900 mb-1">
                          <i class="fa fa-check-circle text-green-500 mr-1"></i>
                          Jika PO dengan <strong>Bayar Lunas</strong>:
                        </p>
                        <ul class="text-xs text-gray-700 space-y-1 list-disc list-inside ml-4">
                          <li>Hanya bisa membuat 1x payment</li>
                          <li>Amount harus = Grand Total PO</li>
                          <li>Jika sudah ada payment, tidak bisa buat payment baru</li>
                        </ul>
                      </div>
                      <div class="bg-gray-50 p-3 rounded border border-gray-200">
                        <p class="text-sm font-medium text-gray-900 mb-1">
                          <i class="fa fa-calendar-alt text-blue-500 mr-1"></i>
                          Jika PO dengan <strong>Termin Bayar</strong>:
                        </p>
                        <ul class="text-xs text-gray-700 space-y-1 list-disc list-inside ml-4">
                          <li>Bisa membuat multiple payments</li>
                          <li>Amount bisa ≤ Sisa Pembayaran</li>
                          <li>Sistem menampilkan: Total PO, Sudah Dibayar, Sisa Pembayaran</li>
                          <li>Progress bar visual untuk tracking pembayaran</li>
                          <li>Riwayat semua pembayaran untuk PO tersebut</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tips -->
              <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
                <h4 class="font-semibold text-amber-800 mb-2">
                  <i class="fa fa-lightbulb mr-2"></i>
                  Tips Penting
                </h4>
                <ul class="text-sm text-amber-700 space-y-1 list-disc list-inside">
                  <li>Metode pembayaran <strong>tidak bisa diubah</strong> setelah PO dibuat</li>
                  <li>Pastikan pilih metode yang sesuai dengan kesepakatan dengan supplier</li>
                  <li>Untuk termin, isi detail termin dengan jelas untuk referensi di kemudian hari</li>
                  <li>Detail termin hanya sebagai catatan, sistem tidak memvalidasi apakah pembayaran sesuai detail termin</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-6 py-4 flex justify-end">
            <button
              @click="showTutorial = false"
              class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
