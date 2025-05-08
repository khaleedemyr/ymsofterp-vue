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
            <DialogPanel class="w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Input Supplier & Upload Permintaan Penawaran</h3>
                <button @click="close" class="text-gray-400 hover:text-gray-600">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div class="mb-4">
                <label class="block font-medium mb-2">Upload File Permintaan Penawaran (PDF/Dokumen)</label>
                <input type="file" @change="handleFileUpload" accept="application/pdf,.doc,.docx,.jpg,.jpeg,.png" />
                <div v-if="fileName" class="text-xs text-gray-500 mt-1">File: {{ fileName }}</div>
              </div>
              <div class="mb-4">
                <label class="block font-medium mb-2">Pilih Supplier</label>
                <select v-model="selectedSupplierId" class="border rounded px-2 py-1 w-full">
                  <option value="">-- Pilih Supplier --</option>
                  <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div v-if="selectedSupplierId && items.length" class="mb-4">
                <label class="block font-medium mb-2">Input Harga Penawaran per Item</label>
                <table class="w-full text-sm mb-2">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Spesifikasi</th>
                      <th>Qty</th>
                      <th>Unit</th>
                      <th>Harga PR</th>
                      <th>Harga Penawaran</th>
                      <th>Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in items" :key="item.id">
                      <td>{{ item.item_name }}</td>
                      <td>{{ item.specifications || '-' }}</td>
                      <td>{{ item.quantity }}</td>
                      <td>{{ item.unit_name || '-' }}</td>
                      <td>{{ formatCurrency(item.price) }}</td>
                      <td>
                        <input type="number" min="0" v-model="itemOffers[item.id]" class="border rounded px-2 py-1 w-32" placeholder="Harga" />
                      </td>
                      <td>{{ formatCurrency(item.quantity * (itemOffers[item.id] || 0)) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="flex justify-end gap-2 mt-6">
                <button @click="close" class="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Batal</button>
                <button @click="proceed" :disabled="!selectedSupplierId || !isAllPriceFilled" class="px-4 py-2 text-white bg-blue-500 rounded hover:bg-blue-600">
                  Lanjut Input Penawaran
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
import { ref, watch, onMounted, computed } from 'vue';
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
  items: Array, // Daftar item yang dipilih dari bidding
});
const emit = defineEmits(['close', 'supplier-input-done']);

const suppliers = ref([]);
const selectedSupplierId = ref('');
const file = ref(null);
const fileName = ref('');
const itemOffers = ref({});

const isAllPriceFilled = computed(() => {
  if (!props.items.length) return false;
  return props.items.every(item => itemOffers.value[item.id] && itemOffers.value[item.id] > 0);
});

watch(() => props.show, (val) => {
  if (val) {
    fetchSuppliers();
    selectedSupplierId.value = '';
    itemOffers.value = {};
  }
});

async function fetchSuppliers() {
  try {
    const res = await axios.get('/api/suppliers?status=active');
    suppliers.value = res.data;
  } catch (e) {
    suppliers.value = [];
  }
}

function handleFileUpload(e) {
  const f = e.target.files[0];
  if (f) {
    file.value = f;
    fileName.value = f.name;
  }
}
function close() {
  emit('close');
}
async function proceed() {
  const formData = new FormData();
  formData.append('supplier_id', selectedSupplierId.value);
  if (file.value) formData.append('file', file.value);
  formData.append('offers', JSON.stringify(itemOffers.value));

  try {
    await axios.post('/api/maintenance-tasks/bidding-offers', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    Swal.fire('Sukses', 'Penawaran berhasil disimpan!', 'success');
    emit('supplier-input-done');
  } catch (e) {
    Swal.fire('Gagal', 'Gagal menyimpan penawaran!', 'error');
  }
}

function formatCurrency(val) {
  if (!val) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script> 