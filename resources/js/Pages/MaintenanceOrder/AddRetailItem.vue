<template>
  <div class="add-retail-item">
    <form @submit.prevent="handleSubmit" class="space-y-4 max-h-[calc(100vh-200px)] overflow-y-auto pr-2">
      <!-- Store Details Section -->
      <div class="bg-gray-50 p-4 rounded-lg">
        <h3 class="font-medium mb-3">Detail Toko</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Nama Toko -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Nama Toko
            </label>
            <input v-model="storeDetails.nama_toko"
                   type="text"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                   required />
          </div>

          <!-- Alamat Toko -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Alamat Toko
            </label>
            <input v-model="storeDetails.alamat_toko"
                   type="text"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                   required />
          </div>
        </div>

        <!-- Invoice Images -->
        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Invoice Images
          </label>
          <div class="flex flex-wrap gap-2 mb-2">
            <button 
              type="button"
              @click="openCamera('invoice')" 
              class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
            >
              <i class="fas fa-camera mr-1"></i> Ambil Foto Invoice
            </button>
          </div>
          <div class="flex flex-wrap gap-2">
            <div v-for="(image, index) in invoiceImages" 
                 :key="index"
                 class="relative w-24 h-24 border rounded-lg overflow-hidden">
              <img :src="image.url" class="w-full h-full object-cover" />
              <button type="button"
                      @click="removeInvoiceImage(index)"
                      class="absolute top-0 right-0 bg-red-500 text-white p-1 rounded-full">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Items Section -->
      <div class="bg-gray-50 p-4 rounded-lg">
        <div class="flex justify-between items-center mb-3">
          <h3 class="font-medium">Daftar Barang</h3>
        </div>

        <!-- Item List -->
        <div class="space-y-4">
          <div v-for="(item, index) in items" :key="index" 
               class="grid grid-cols-1 gap-4 pb-4 border-b border-gray-200 last:border-0">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <!-- Nama Barang -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Nama Barang
                </label>
                <input v-model="item.nama_barang"
                       type="text"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                       required />
              </div>

              <!-- Qty -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Qty
                </label>
                <input v-model="item.qty"
                       type="number"
                       min="1"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                       @input="calculateSubtotal(index)"
                       required />
              </div>

              <!-- Harga Barang -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Harga Barang
                </label>
                <input v-model="item.harga_barang"
                       type="number"
                       min="0"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                       @input="calculateSubtotal(index)"
                       required />
              </div>

              <!-- Subtotal -->
              <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Subtotal
                </label>
                <input :value="formatCurrency(item.subtotal)"
                       type="text"
                       class="w-full rounded-md border-gray-300 bg-gray-50 shadow-sm"
                       readonly />
                <button v-if="items.length > 1" 
                        type="button"
                        @click="removeItem(index)"
                        class="absolute top-0 -right-8 text-red-500 hover:text-red-700 p-2">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>

            <!-- Barang Images -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Barang Images
              </label>
              <div class="flex flex-wrap gap-2 mb-2">
                <button 
                  type="button"
                  @click="openCamera('barang', index)" 
                  class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                >
                  <i class="fas fa-camera mr-1"></i> Ambil Foto Barang
                </button>
              </div>
              <div class="flex flex-wrap gap-2">
                <div v-for="(image, imageIndex) in item.barang_images" 
                     :key="imageIndex"
                     class="relative w-24 h-24 border rounded-lg overflow-hidden">
                  <img :src="image.url" class="w-full h-full object-cover" />
                  <button type="button"
                          @click="removeBarangImage(index, imageIndex)"
                          class="absolute top-0 right-0 bg-red-500 text-white p-1 rounded-full">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Item Button -->
        <div class="flex justify-between items-center mt-4">
          <button type="button"
                  @click="addItem"
                  class="text-blue-500 hover:text-blue-700">
            <i class="fas fa-plus mr-1"></i>
            Tambah Item
          </button>
          <div class="text-right">
            <span class="text-sm font-medium text-gray-700">Grand Total:</span>
            <span class="ml-2 text-lg font-semibold">{{ formatCurrency(grandTotal) }}</span>
          </div>
        </div>
      </div>

      <!-- Submit Button -->
      <div class="flex justify-end mt-4">
        <button type="submit"
                class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600"
                :disabled="loading">
          <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
          Simpan
        </button>
      </div>
    </form>

    <!-- Camera Modal -->
    <TransitionRoot appear :show="showCamera" as="template">
      <Dialog as="div" @close="closeCamera" class="relative z-[9999]">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/40" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-lg bg-white shadow-xl transition-all">
                <div class="p-4 border-b flex justify-between items-center">
                  <h3 class="text-lg font-semibold">
                    {{ cameraType === 'invoice' ? 'Ambil Foto Invoice' : 'Ambil Foto Barang' }}
                  </h3>
                  <div class="flex items-center gap-2">
                    <button @click="switchCamera" class="text-gray-500 hover:text-gray-700 px-2 py-1">
                      <i class="fas fa-sync"></i>
                    </button>
                    <button @click="closeCamera" class="text-gray-500 hover:text-gray-700">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>

                <div class="p-4">
                  <div class="mb-4">
                    <video 
                      ref="videoPreview" 
                      class="w-full rounded" 
                      autoplay 
                      playsinline
                    ></video>
                    <canvas 
                      ref="canvas" 
                      class="hidden"
                    ></canvas>
                  </div>

                  <div class="flex justify-center gap-2">
                    <button 
                      type="button"
                      @click="captureImage" 
                      class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                    >
                      <i class="fas fa-camera mr-1"></i> Ambil Foto
                    </button>
                    <button 
                      type="button"
                      @click="closeCamera" 
                      class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
                    >
                      <i class="fas fa-times mr-1"></i> Batal
                    </button>
                  </div>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount, computed } from 'vue';
import axios from 'axios';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogOverlay,
  DialogPanel,
} from '@headlessui/vue';
import Swal from 'sweetalert2';

const props = defineProps({
  taskId: {
    type: [Number, String],
    required: true
  }
});

const emit = defineEmits(['saved']);

const loading = ref(false);
const storeDetails = ref({
  nama_toko: '',
  alamat_toko: ''
});
const items = ref([createEmptyItem()]);
const invoiceImages = ref([]);

// Camera related refs and state
const showCamera = ref(false);
const cameraType = ref('invoice');
const currentItemIndex = ref(null);
const videoPreview = ref(null);
const canvas = ref(null);
const currentFacingMode = ref('environment'); // 'environment' for back camera, 'user' for front camera
let stream = null;

function createEmptyItem() {
  return {
    nama_barang: '',
    qty: 1,
    harga_barang: '',
    subtotal: 0,
    barang_images: []
  };
}

function calculateSubtotal(index) {
  const item = items.value[index];
  item.subtotal = (parseFloat(item.qty) || 0) * (parseFloat(item.harga_barang) || 0);
}

const grandTotal = computed(() => {
  return items.value.reduce((total, item) => total + (item.subtotal || 0), 0);
});

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

function addItem() {
  items.value.push(createEmptyItem());
}

function removeItem(index) {
  items.value.splice(index, 1);
}

function removeInvoiceImage(index) {
  invoiceImages.value.splice(index, 1);
}

function removeBarangImage(itemIndex, imageIndex) {
  items.value[itemIndex].barang_images.splice(imageIndex, 1);
}

function openCamera(type, itemIndex = null) {
  cameraType.value = type;
  currentItemIndex.value = itemIndex;
  showCamera.value = true;
  startCamera();
}

function closeCamera() {
  showCamera.value = false;
  stopCamera();
}

async function startCamera() {
  try {
    if (stream) {
      stopCamera();
    }

    stream = await navigator.mediaDevices.getUserMedia({ 
      video: { 
        facingMode: currentFacingMode.value
      }
    });
    
    if (videoPreview.value) {
      videoPreview.value.srcObject = stream;
    }
  } catch (error) {
    console.error('Error accessing camera:', error);
    alert('Tidak dapat mengakses kamera. Pastikan Anda memberikan izin akses kamera.');
  }
}

async function switchCamera() {
  // Toggle between front and back camera
  currentFacingMode.value = currentFacingMode.value === 'environment' ? 'user' : 'environment';
  await startCamera();
}

function stopCamera() {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    stream = null;
  }
  if (videoPreview.value) {
    videoPreview.value.srcObject = null;
  }
}

function captureImage() {
  if (!videoPreview.value || !canvas.value) return;
  
  const context = canvas.value.getContext('2d');
  canvas.value.width = videoPreview.value.videoWidth;
  canvas.value.height = videoPreview.value.videoHeight;
  context.drawImage(videoPreview.value, 0, 0);
  
  const imageUrl = canvas.value.toDataURL('image/jpeg', 0.8);
  const timestamp = new Date().getTime();
  
  const imageData = {
    url: imageUrl,
    file: dataURLtoFile(imageUrl, `image_${timestamp}.jpg`)
  };

  if (cameraType.value === 'invoice') {
    invoiceImages.value.push(imageData);
  } else if (currentItemIndex.value !== null) {
    items.value[currentItemIndex.value].barang_images.push(imageData);
  }
  
  closeCamera();
}

function dataURLtoFile(dataurl, filename) {
  const arr = dataurl.split(',');
  const mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]);
  let n = bstr.length;
  const u8arr = new Uint8Array(n);
  while (n--) {
    u8arr[n] = bstr.charCodeAt(n);
  }
  return new File([u8arr], filename, { type: mime });
}

async function handleSubmit() {
  loading.value = true;
  try {
    const formData = new FormData();
    formData.append('task_id', props.taskId);
    formData.append('nama_toko', storeDetails.value.nama_toko);
    formData.append('alamat_toko', storeDetails.value.alamat_toko);
    
    // Log items data before sending
    console.log('Items to send:', items.value);
    
    // Append items data
    formData.append('items', JSON.stringify(items.value.map(item => ({
      nama_barang: item.nama_barang,
      qty: item.qty,
      harga_barang: item.harga_barang,
      subtotal: item.subtotal
    }))));

    // Log form data
    for (let [key, value] of formData.entries()) {
      console.log(`${key}:`, value);
    }

    // Append invoice files
    invoiceImages.value.forEach(image => {
      formData.append('invoice_files[]', image.file);
    });

    // Append barang files
    items.value.forEach((item, itemIndex) => {
      item.barang_images.forEach(image => {
        formData.append(`barang_files[${itemIndex}][]`, image.file);
      });
    });

    const response = await axios.post('/api/retail', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Data retail berhasil disimpan',
        timer: 1500,
        showConfirmButton: false
      });
      emit('saved');
      // Reset form
      storeDetails.value = {
        nama_toko: '',
        alamat_toko: ''
      };
      items.value = [createEmptyItem()];
      invoiceImages.value = [];
    }
  } catch (error) {
    console.error('Error saving retail items:', error);
    // Log detailed error information
    if (error.response) {
      console.error('Error response:', error.response.data);
    }
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Gagal menyimpan data retail. Silakan coba lagi.',
    });
  } finally {
    loading.value = false;
  }
}

// Cleanup camera when component is destroyed
onBeforeUnmount(() => {
  stopCamera();
});
</script>

<style>
.add-retail-item {
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.add-retail-item::-webkit-scrollbar {
  width: 6px;
}

.add-retail-item::-webkit-scrollbar-track {
  background: transparent;
}

.add-retail-item::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5);
  border-radius: 3px;
}
</style> 