<template>
  <div>
    <form @submit.prevent="submitPR">
      <div class="mb-2">
        <label class="block text-sm font-semibold">PR Number</label>
        <input v-model="form.pr_number" class="border rounded px-2 py-1 w-full bg-gray-100" readonly />
      </div>
      <div class="mb-2">
        <label class="block text-sm font-semibold">Reason for Purchase <span class="text-red-500">*</span></label>
        <textarea v-model="form.description" class="border rounded px-2 py-1 w-full" required />
      </div>
      <div class="mb-4">
        <label class="block text-sm font-semibold mb-2">Items <span class="text-red-500">*</span></label>
        <table class="w-full text-xs border mb-2">
          <thead class="bg-gray-100">
            <tr>
              <th class="border px-2 py-1">Item Name<span class="text-red-500">*</span></th>
              <th class="border px-2 py-1">Description</th>
              <th class="border px-2 py-1">Specs</th>
              <th class="border px-2 py-1">Qty<span class="text-red-500">*</span></th>
              <th class="border px-2 py-1">Unit<span class="text-red-500">*</span></th>
              <th class="border px-2 py-1">Price</th>
              <th class="border px-2 py-1">Subtotal</th>
              <th class="border px-2 py-1">Notes</th>
              <th class="border px-2 py-1"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in form.items" :key="idx">
              <td class="border px-2 py-1"><input v-model="item.item_name" class="border rounded px-1 py-0.5 w-full" required /></td>
              <td class="border px-2 py-1"><input v-model="item.description" class="border rounded px-1 py-0.5 w-full" /></td>
              <td class="border px-2 py-1"><input v-model="item.specifications" class="border rounded px-1 py-0.5 w-full" /></td>
              <td class="border px-2 py-1"><input v-model.number="item.quantity" type="number" min="1" class="border rounded px-1 py-0.5 w-14" required @input="updateSubtotal(idx)" /></td>
              <td class="border px-2 py-1">
                <select v-model="item.unit_id" class="border rounded px-1 py-0.5 w-full" required>
                  <option value="" disabled>Pilih</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                </select>
              </td>
              <td class="border px-2 py-1"><input v-model.number="item.price" type="number" min="0" class="border rounded px-1 py-0.5 w-20" @input="updateSubtotal(idx)" /></td>
              <td class="border px-2 py-1"><input v-model.number="item.subtotal" type="number" min="0" class="border rounded px-1 py-0.5 w-24 bg-gray-100" readonly /></td>
              <td class="border px-2 py-1"><input v-model="item.notes" class="border rounded px-1 py-0.5 w-full" /></td>
              <td class="border px-2 py-1 text-center"><button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></td>
            </tr>
          </tbody>
        </table>
        <button type="button" @click="addItem" class="mt-2 px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"><i class="fas fa-plus"></i> Add Row</button>
      </div>
      <div class="mb-2 flex justify-end items-center">
        <label class="block text-sm font-semibold mr-2">Total Amount</label>
        <input v-model.number="form.total_amount" type="number" class="border rounded px-2 py-1 w-40 bg-gray-100" readonly />
      </div>
      <div v-if="error" class="text-red-500 text-xs mb-2">{{ error }}</div>
      <div v-if="loading" class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/30">
        <div class="bg-white px-6 py-4 rounded shadow flex items-center gap-2">
          <span class="loader border-2 border-blue-500 border-t-transparent rounded-full w-6 h-6 animate-spin"></span>
          <span>Menyimpan...</span>
        </div>
      </div>
      <button type="submit" class="mt-2 px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Simpan PR</button>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  taskId: [String, Number],
  onSaved: Function,
  editPr: Object,
});

const form = ref({
  pr_number: '',
  description: '',
  specifications: '',
  total_amount: null,
  items: [],
});
const units = ref([]);
const error = ref('');
const loading = ref(false);

async function fetchPrNumber() {
  const res = await axios.get('/api/maintenance-purchase-requisitions/generate-number');
  form.value.pr_number = res.data.pr_number;
}
async function fetchUnits() {
  const res = await axios.get('/api/units');
  units.value = res.data;
}

function addItem() {
  form.value.items.push({
    item_name: '',
    description: '',
    specifications: '',
    quantity: 1,
    unit_id: '',
    price: 0,
    subtotal: 0,
    notes: '',
  });
}
function removeItem(idx) {
  form.value.items.splice(idx, 1);
}
function updateSubtotal(idx) {
  const item = form.value.items[idx];
  item.subtotal = (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0);
  updateTotalAmount();
}
function updateTotalAmount() {
  form.value.total_amount = form.value.items.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0);
}

function validateForm() {
  if (!form.value.description) {
    error.value = 'Reason for Purchase wajib diisi';
    return false;
  }
  if (!form.value.items.length) {
    error.value = 'Minimal 1 item';
    return false;
  }
  for (const item of form.value.items) {
    if (!item.item_name) {
      error.value = 'Nama item wajib diisi';
      return false;
    }
    if (!item.unit_id) {
      error.value = 'Unit wajib dipilih';
      return false;
    }
    if (!item.quantity || item.quantity <= 0) {
      error.value = 'Quantity harus lebih dari 0';
      return false;
    }
    if (item.price < 0) {
      error.value = 'Harga tidak boleh negatif';
      return false;
    }
  }
  error.value = '';
  return true;
}

async function submitPR() {
  if (!validateForm()) return;
  loading.value = true;
  try {
    if (props.editPr && props.editPr.id) {
      // Update PR
      await axios.put(`/api/maintenance-purchase-requisitions/${props.editPr.id}`, form.value);
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'PR berhasil diupdate!' });
    } else {
      // Create PR
      await axios.post(`/api/maintenance-tasks/${props.taskId}/purchase-requisitions`, form.value);
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'PR berhasil disimpan!' });
    }
    if (props.onSaved) props.onSaved();
    await fetchPrNumber();
    form.value.description = '';
    form.value.specifications = '';
    form.value.total_amount = null;
    form.value.items = [];
    addItem();
  } catch (e) {
    error.value = 'Gagal simpan PR';
    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal menyimpan PR!' });
  } finally {
    loading.value = false;
  }
}

function fillFormFromEditPr() {
  if (props.editPr) {
    form.value.pr_number = props.editPr.pr_number;
    form.value.description = props.editPr.description;
    form.value.specifications = props.editPr.specifications;
    form.value.total_amount = props.editPr.total_amount;
    form.value.items = (props.editPr.items || []).map(item => ({ ...item }));
    if (!form.value.items.length) addItem();
  } else {
    fetchPrNumber();
    form.value.description = '';
    form.value.specifications = '';
    form.value.total_amount = null;
    form.value.items = [];
    addItem();
  }
}

onMounted(() => {
  fetchUnits();
  fillFormFromEditPr();
});

watch(() => props.editPr, fillFormFromEditPr);
</script>

<style scoped>
.loader {
  border-width: 2px;
  border-style: solid;
  border-color: #3b82f6 transparent #3b82f6 #3b82f6;
}
</style> 