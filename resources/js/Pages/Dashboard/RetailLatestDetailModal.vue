<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto relative animate-fadeIn">
      <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-xl z-10">
        <i class="fas fa-times"></i>
      </button>
      <div class="p-6">
        <h2 class="text-xl font-bold mb-4">Detail Retail</h2>
        <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-2">
          <div><span class="font-semibold">Nama Toko:</span> {{ retail.nama_toko }}</div>
          <div><span class="font-semibold">Alamat:</span> {{ retail.alamat_toko || '-' }}</div>
          <div><span class="font-semibold">Task Number:</span> {{ retail.task_number || '-' }}</div>
          <div><span class="font-semibold">Outlet:</span> {{ retail.nama_outlet || '-' }}</div>
          <div><span class="font-semibold">Created By:</span> {{ retail.created_by || '-' }}</div>
          <div><span class="font-semibold">Tanggal:</span> {{ retail.created_at ? retail.created_at.substring(0,10) : '-' }}</div>
          <div><span class="font-semibold">Total Amount:</span> {{ formatRupiah(retail.total_amount) }}</div>
        </div>
        <div class="mb-4">
          <div class="font-semibold mb-1">Items:</div>
          <table class="min-w-full text-xs rounded-xl overflow-hidden">
            <thead class="bg-gray-100">
              <tr>
                <th class="py-1 px-2 text-left">Nama Barang</th>
                <th class="py-1 px-2 text-right">Qty</th>
                <th class="py-1 px-2 text-right">Harga</th>
                <th class="py-1 px-2 text-right">Subtotal</th>
                <th class="py-1 px-2 text-left">Invoice Images</th>
                <th class="py-1 px-2 text-left">Barang Images</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, itemIdx) in retail.items" :key="item.id">
                <td class="py-1 px-2">{{ item.nama_barang }}</td>
                <td class="py-1 px-2 text-right">{{ item.qty }}</td>
                <td class="py-1 px-2 text-right">{{ formatRupiah(item.harga_barang) }}</td>
                <td class="py-1 px-2 text-right">{{ formatRupiah(item.subtotal) }}</td>
                <td class="py-1 px-2">
                  <div class="flex flex-row flex-wrap gap-2">
                    <template v-for="(img, idx) in item.invoice_images" :key="img.id">
                      <img :src="'/storage/' + img.file_path" class="w-12 h-12 object-cover rounded shadow border cursor-pointer" @click="openLightbox(item, 'invoice', idx)" />
                    </template>
                  </div>
                </td>
                <td class="py-1 px-2">
                  <div class="flex flex-row flex-wrap gap-2">
                    <template v-for="(img, idx) in item.barang_images" :key="img.id">
                      <img :src="'/storage/' + img.file_path" class="w-12 h-12 object-cover rounded shadow border cursor-pointer" @click="openLightbox(item, 'barang', idx)" />
                    </template>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <LightboxModal :show="lightboxShow" :mediaList="lightboxMedia" :startIndex="lightboxIndex" @close="lightboxShow = false" />
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue';
import LightboxModal from './LightboxModal.vue';
const props = defineProps({ retail: Object, show: Boolean });
const emit = defineEmits(['close']);
const lightboxShow = ref(false);
const lightboxMedia = ref([]);
const lightboxIndex = ref(0);
function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}
function openLightbox(item, type, idx) {
  let mediaArr = [];
  if (type === 'invoice') {
    mediaArr = item.invoice_images.map(img => ({ type: 'image', url: '/storage/' + img.file_path, caption: img.file_path.split('/').pop() }));
  } else {
    mediaArr = item.barang_images.map(img => ({ type: 'image', url: '/storage/' + img.file_path, caption: img.file_path.split('/').pop() }));
  }
  lightboxMedia.value = mediaArr;
  lightboxIndex.value = idx;
  lightboxShow.value = true;
}
</script> 