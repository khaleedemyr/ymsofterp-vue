<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  contraBon: Object, // for edit, can be null for create
});

const isEdit = computed(() => !!props.contraBon);

const poWithGRList = ref([]);
const selectedPOGR = ref(null);
const loadingPOGR = ref(false);
const selectedPOGRKey = ref('');
const supplierDetail = ref(null);
const fileImage = ref(null);
const fileImagePreview = ref(null);

const form = useForm({
  date: props.contraBon?.date ? props.contraBon.date.substring(0, 10) : '',
  po_id: props.contraBon?.po_id || '',
  gr_id: props.contraBon?.gr_id || '',
  notes: props.contraBon?.notes || '',
  supplier_invoice_number: props.contraBon?.supplier_invoice_number || '', // <-- Tambahkan ini
  items: props.contraBon?.items?.map(i => ({
    gr_item_id: i.gr_item_id,
    item_id: i.item_id,
    quantity: i.quantity,
    unit_id: i.unit_id,
    price: i.price,
    notes: i.notes || '',
    item: i.item,
    unit: i.unit,
    _rowKey: Date.now() + '-' + Math.random(),
  })) || [],
});

onMounted(async () => {
  if (!isEdit.value) {
    // Set default date ke hari ini
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    form.date = `${yyyy}-${mm}-${dd}`;
    loadingPOGR.value = true;
    try {
      const res = await axios.get('/api/contra-bon/po-with-approved-gr');
      poWithGRList.value = res.data;
    } catch (e) {
      Swal.fire('Error', 'Gagal mengambil data PO/GR', 'error');
    } finally {
      loadingPOGR.value = false;
    }
  } else {
    // Mode edit: set preview image jika ada
    if (props.contraBon?.image_path) {
      fileImagePreview.value = `/storage/${props.contraBon.image_path}`;
    }
  }
});

async function onPOGRChange() {
  if (!selectedPOGRKey.value) {
    selectedPOGR.value = null;
    form.po_id = '';
    form.gr_id = '';
    form.items = [];
    supplierDetail.value = null;
    fileImage.value = null;
    fileImagePreview.value = null;
    return;
  }
  const sepIdx = selectedPOGRKey.value.lastIndexOf('-');
  const poId = selectedPOGRKey.value.substring(0, sepIdx);
  const grId = selectedPOGRKey.value.substring(sepIdx + 1);
  form.po_id = poId;
  form.gr_id = grId;
  const pogr = poWithGRList.value.find(p => String(p.po_id) === poId && String(p.gr_id) === grId);
  selectedPOGR.value = pogr;
  if (pogr) {
    form.items = pogr.items.map(item => ({
      gr_item_id: item.id,
      item_id: item.item_id,
      po_item_id: item.po_item_id,
      unit_id: item.unit_id,
      quantity: item.qty_received,
      price: item.po_price,
      notes: '',
      _rowKey: Date.now() + '-' + Math.random(),
    }));
    // Fetch supplier detail
    if (pogr.supplier_id) {
      try {
        const res = await axios.get(`/api/suppliers/${pogr.supplier_id}`);
        supplierDetail.value = res.data;
      } catch (e) {
        supplierDetail.value = null;
      }
    } else {
      supplierDetail.value = null;
    }
  } else {
    form.items = [];
    supplierDetail.value = null;
  }
}

function onFileChange(e) {
  const file = e.target.files[0];
  fileImage.value = file;
  if (file) {
    const reader = new FileReader();
    reader.onload = (ev) => {
      fileImagePreview.value = ev.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    fileImagePreview.value = null;
  }
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

async function onSubmit() {
  Swal.fire({
    title: isEdit.value ? 'Menyimpan Perubahan...' : 'Menyimpan Data...',
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });
  // Kirim pakai FormData jika ada file
  if (fileImage.value) {
    const fd = new FormData();
    fd.append('date', form.date);
    fd.append('po_id', form.po_id);
    fd.append('gr_id', form.gr_id);
    fd.append('notes', form.notes);
    fd.append('supplier_invoice_number', form.supplier_invoice_number); // Tambahkan ini
    fd.append('image', fileImage.value);
    form.items.forEach((item, idx) => {
      Object.keys(item).forEach(key => {
        fd.append(`items[${idx}][${key}]`, item[key]);
      });
    });
    try {
      const url = isEdit.value ? `/contra-bons/${props.contraBon.id}` : '/contra-bons';
      const method = isEdit.value ? 'post' : 'post';
      const config = { headers: { 'Content-Type': 'multipart/form-data' } };
      let res;
      if (isEdit.value) {
        fd.append('_method', 'PUT');
        res = await axios.post(url, fd, config);
      } else {
        res = await axios.post(url, fd, config);
      }
      Swal.fire('Berhasil', 'Data berhasil disimpan', 'success').then(() => router.visit('/contra-bons'));
    } catch (e) {
      Swal.close();
      if (e.response && e.response.data && e.response.data.errors) {
        form.setError(e.response.data.errors);
      }
    }
    return;
  }
  // Tanpa file, pakai inertia
  if (isEdit.value) {
    form.put(`/contra-bons/${props.contraBon.id}`, {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Data berhasil disimpan', 'success').then(() => router.visit('/contra-bons'));
      },
      onError: () => Swal.close(),
    });
  } else {
    form.post('/contra-bons', {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Data berhasil disimpan', 'success').then(() => router.visit('/contra-bons'));
      },
      onError: () => Swal.close(),
    });
  }
}

function goBack() {
  router.visit('/contra-bons');
}
</script>
<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-file-circle-xmark text-blue-500"></i> {{ isEdit ? 'Edit' : 'Tambah' }} Contra Bon
        </h1>
      </div>
      <form @submit.prevent="onSubmit" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Purchase Order</label>
            <select v-model="selectedPOGRKey" @change="onPOGRChange" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih PO - GR - Supplier</option>
              <option v-for="p in poWithGRList" :key="p.po_id + '-' + p.gr_id" :value="p.po_id + '-' + p.gr_id">
                {{ p.po_number }} - {{ p.gr_number }} - {{ p.supplier_name }}
              </option>
            </select>
            <input type="hidden" v-model="form.po_id" />
            <input type="hidden" v-model="form.gr_id" />
            <div v-if="form.errors.po_id" class="text-xs text-red-500 mt-1">{{ form.errors.po_id }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <div v-if="form.errors.date" class="text-xs text-red-500 mt-1">{{ form.errors.date }}</div>
          </div>
        </div>

        <!-- Card Info PO & GR -->
        <div v-if="selectedPOGR" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="bg-blue-50 rounded-lg p-4 shadow">
            <h3 class="font-bold mb-2">Info PO</h3>
            <div>No. PO: {{ selectedPOGR.po_number }}</div>
            <div>Tanggal PO: {{ selectedPOGR.po_date }}</div>
            <div>Supplier: <b>{{ selectedPOGR.supplier_name }}</b></div>
            <div>Dibuat oleh: {{ selectedPOGR.po_creator_name }}</div>
          </div>
          <div class="bg-green-50 rounded-lg p-4 shadow">
            <h3 class="font-bold mb-2">Info Good Receive</h3>
            <div>No. GR: {{ selectedPOGR.gr_number }}</div>
            <div>Tanggal GR: {{ selectedPOGR.gr_date }}</div>
            <div>Diterima oleh: {{ selectedPOGR.gr_receiver_name }}</div>
            <div>Supplier: <b>{{ selectedPOGR.supplier_name }}</b></div>
          </div>
        </div>

        <!-- Card Info Supplier -->
        <div v-if="supplierDetail" class="bg-yellow-50 rounded-lg p-4 shadow mb-4">
          <h3 class="font-bold mb-2">Info Supplier</h3>
          <div><b>Nama:</b> {{ supplierDetail.name }}</div>
          <div><b>PIC:</b> {{ supplierDetail.contact_person }}</div>
          <div><b>No. Telp:</b> {{ supplierDetail.phone }}</div>
          <div><b>Email:</b> {{ supplierDetail.email }}</div>
          <div><b>Alamat:</b> {{ supplierDetail.address }}</div>
          <div><b>Kota:</b> {{ supplierDetail.city }}</div>
          <div><b>Provinsi:</b> {{ supplierDetail.province }}</div>
          <div><b>Kode Pos:</b> {{ supplierDetail.postal_code }}</div>
          <div><b>NPWP:</b> {{ supplierDetail.npwp }}</div>
        </div>

        <!-- Upload File Contra Bon Fisik -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Upload Scan/Foto Contra Bon Fisik</label>
          <input type="file" accept="image/*" @change="onFileChange" class="mt-1 block" />
          <div v-if="fileImagePreview" class="mt-2">
            <img :src="fileImagePreview" alt="Preview" class="max-w-xs rounded shadow" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">No Invoice Supplier</label>
          <input type="text" v-model="form.supplier_invoice_number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="No Invoice dari Supplier" />
          <div v-if="form.errors.supplier_invoice_number" class="text-xs text-red-500 mt-1">{{ form.errors.supplier_invoice_number }}</div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Detail Item</label>
          <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Price</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
                  <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Notes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in form.items" :key="item._rowKey || idx">
                  <td class="px-3 py-2 min-w-[200px]">
                    {{ isEdit ? (item.item?.name || '-') : (selectedPOGR?.items.find(i => i.item_id === item.item_id)?.item_name || '-') }}
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    {{ item.quantity }}
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    {{ selectedPOGR?.items.find(i => i.item_id === item.item_id)?.unit_name || item.unit?.name || '-' }}
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    {{ formatCurrency(item.price) }}
                  </td>
                  <td class="px-3 py-2 min-w-[100px]">
                    {{ formatCurrency(item.quantity * item.price) }}
                  </td>
                  <td class="px-3 py-2 min-w-[120px]">
                    <input type="text" v-model="item.notes" class="w-full rounded border-gray-300" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Notes</label>
          <textarea v-model="form.notes" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
          <div v-if="form.errors.notes" class="text-xs text-red-500 mt-1">{{ form.errors.notes }}</div>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50">{{ isEdit ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template> 