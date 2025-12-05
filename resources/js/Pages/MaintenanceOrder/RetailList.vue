<template>
  <div class="retail-list">
    <div v-if="loading" class="flex justify-center py-4">
      <i class="fas fa-spinner fa-spin text-blue-500 text-xl"></i>
    </div>
    
    <div v-else-if="retailData.length === 0" class="text-center py-4 text-gray-500">
      Belum ada data retail
    </div>
    
    <div v-else class="space-y-4">
      <div v-for="retail in retailData" :key="retail.id" class="bg-white rounded-lg shadow">
        <!-- Header -->
        <div class="p-4 border-b">
          <div>
            <h3 class="font-medium">Retail</h3>
            <p class="text-sm text-gray-600">Dibuat oleh: {{ retail.created_by_name }}</p>
            <p class="text-sm text-gray-600">{{ formatDate(retail.created_at) }}</p>
            <div class="mt-1">
              <p class="text-sm font-medium">{{ retail.nama_toko }}</p>
              <p class="text-sm text-gray-600">{{ retail.alamat_toko }}</p>
            </div>
          </div>
        </div>

        <!-- Invoice Images -->
        <div class="p-4">
          <div class="grid grid-cols-4 gap-2">
            <template v-for="item in retail.items" :key="item.id">
              <template v-for="image in item.invoice_images" :key="image.id">
                <div class="aspect-square rounded-lg overflow-hidden border cursor-pointer"
                     @click="openImagePreview(image.url)">
                  <img :src="image.url" class="w-full h-full object-cover" />
                </div>
              </template>
            </template>
          </div>
          <div v-if="hasMoreImages(retail)" class="mt-2">
            <button class="text-sm text-gray-600 hover:text-gray-800">
              +{{ countRemainingImages(retail) }} more
            </button>
          </div>
        </div>

        <!-- Items List -->
        <div class="p-4 border-t">
          <div class="space-y-3">
            <div v-for="item in getActualItems(retail.items)" :key="item.id" 
                 class="flex justify-between items-center">
              <div>
                <p class="font-medium">{{ item.nama_barang }}</p>
                <p class="text-sm text-gray-600">{{ item.qty }} x Rp {{ formatNumber(item.harga_barang) }}</p>
                <!-- Barang Images -->
                <div v-if="item.barang_images && item.barang_images.length > 0" class="mt-2">
                  <div class="flex flex-wrap gap-2">
                    <div v-for="image in item.barang_images" :key="image.id" 
                         class="w-16 h-16 rounded-lg overflow-hidden border cursor-pointer"
                         @click="openImagePreview(image.url)">
                      <img :src="image.url" class="w-full h-full object-cover" />
                    </div>
                  </div>
                </div>
              </div>
              <p class="font-medium">Rp {{ formatNumber(item.subtotal) }}</p>
            </div>
          </div>
          
          <!-- Grand Total -->
          <div class="mt-4 pt-3 border-t flex justify-between items-center">
            <p class="font-medium">Grand Total</p>
            <p class="text-lg font-semibold">Rp {{ formatNumber(calculateGrandTotal(retail)) }}</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Image Preview Modal -->
    <Teleport to="body">
      <div v-if="showImagePreview" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" @click="closeImagePreview">
        <div class="bg-white rounded-lg max-w-3xl max-h-[90vh] overflow-hidden" @click.stop>
          <div class="p-2 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold">Preview Image</h3>
            <button @click="closeImagePreview" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="p-4">
            <img :src="previewImageUrl" class="max-w-full max-h-[70vh] object-contain" />
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';

const props = defineProps({
  taskId: {
    type: [Number, String],
    required: true
  }
});

const retailData = ref([]);
const loading = ref(true);
const showImagePreview = ref(false);
const previewImageUrl = ref('');
const MAX_VISIBLE_IMAGES = 4;

onMounted(() => {
  loadRetailData();
});

async function loadRetailData() {
  loading.value = true;
  try {
    const response = await axios.get(`/api/retail/task/${props.taskId}`);
    if (response.data.success) {
      console.log('Retail data received:', response.data.data);
      retailData.value = response.data.data;
      // Log each item's barang_images
      retailData.value.forEach(retail => {
        retail.items.forEach(item => {
          console.log(`Item ${item.id} barang_images:`, item.barang_images);
        });
      });
    }
  } catch (error) {
    console.error('Error loading retail data:', error);
  } finally {
    loading.value = false;
  }
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return format(date, 'dd MMM yyyy HH:mm', { locale: id });
}

function formatNumber(number) {
  if (number === null || number === undefined || isNaN(number)) {
    return '0';
  }
  return new Intl.NumberFormat('id-ID').format(number);
}

function getActualItems(items) {
  if (!Array.isArray(items)) return [];
  return items.filter(item => item.nama_barang !== 'Invoice');
}

function calculateGrandTotal(retail) {
  if (!retail?.items) return 0;
  return getActualItems(retail.items).reduce((total, item) => {
    const subtotal = parseFloat(item.subtotal) || 0;
    return total + subtotal;
  }, 0);
}

function hasMoreImages(retail) {
  const totalImages = retail.items.reduce((count, item) => 
    count + (item.invoice_images?.length || 0), 0);
  return totalImages > MAX_VISIBLE_IMAGES;
}

function countRemainingImages(retail) {
  const totalImages = retail.items.reduce((count, item) => 
    count + (item.invoice_images?.length || 0), 0);
  return totalImages - MAX_VISIBLE_IMAGES;
}

function openImagePreview(url) {
  previewImageUrl.value = url;
  showImagePreview.value = true;
}

function closeImagePreview() {
  showImagePreview.value = false;
  previewImageUrl.value = '';
}

// Expose methods to parent component
defineExpose({
  loadRetailData
});
</script>

<style scoped>
.retail-list {
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.retail-list::-webkit-scrollbar {
  width: 6px;
}

.retail-list::-webkit-scrollbar-track {
  background: transparent;
}

.retail-list::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5);
  border-radius: 3px;
}
</style> 