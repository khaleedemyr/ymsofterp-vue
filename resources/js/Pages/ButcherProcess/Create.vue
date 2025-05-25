<template>
  <AppLayout>
    <Head title="Create Butcher Process" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <form @submit.prevent="submit">
              <!-- Basic Information -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                  <select
                    v-model="form.warehouse_id"
                    class="mt-1 block w-full rounded-md border-gray-300"
                    required
                  >
                    <option value="">Select Warehouse</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                      {{ warehouse.name }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Good Receive Autocomplete -->
              <div class="mb-6 relative">
                <label class="block text-sm font-medium text-gray-700">Good Receive</label>
                <input
                  v-model="goodReceiveInput"
                  @input="handleGoodReceiveInput"
                  @focus="handleGoodReceiveInput"
                  @blur="handleGoodReceiveBlur"
                  type="text"
                  class="mt-1 block w-full rounded-md border-gray-300"
                  placeholder="Cari nomor/tanggal/supplier..."
                  autocomplete="off"
                  required
                />
                <div v-if="goodReceiveDropdown && goodReceiveSuggestions.length" class="absolute z-10 bg-white border w-full rounded shadow max-h-60 overflow-auto">
                  <div v-for="gr in goodReceiveSuggestions" :key="gr.id" @mousedown.prevent="selectGoodReceive(gr)" class="px-4 py-2 cursor-pointer hover:bg-blue-50">
                    <div class="font-semibold">{{ gr.gr_number }}</div>
                    <div class="text-xs text-gray-500">{{ gr.receive_date }} - {{ gr.supplier_name }}</div>
                  </div>
                </div>
                <div v-if="goodReceiveLoading" class="absolute right-2 top-2"><i class="fa fa-spinner fa-spin text-blue-400"></i></div>
              </div>

              <!-- Card Data Good Receive -->
              <div v-if="selectedGoodReceive" class="mb-4 p-4 border rounded bg-blue-50">
                <div class="font-semibold text-blue-800">Good Receive Info</div>
                <div class="text-sm mt-1">Nomor: <span class="font-mono">{{ selectedGoodReceive.gr_number }}</span></div>
                <div class="text-sm">Tanggal: {{ selectedGoodReceive.receive_date }}</div>
                <div class="text-sm">Supplier: {{ selectedGoodReceive.supplier_name }}</div>
              </div>

              <!-- Tabel Item Good Receive -->
              <div v-if="wholeItems.length" class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Items in Good Receive</h3>
                <div class="overflow-x-auto">
                  <table class="w-full min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                      <tr>
                        <th></th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">SKU</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item Name</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">SISA QTY</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty to Butcher</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in wholeItems" :key="item.id">
                        <td class="px-2 py-2">
                          <input type="checkbox" :checked="!!selectedWhole[item.id]?.checked" @change="e => setWholeChecked(item.id, e.target.checked)" />
                        </td>
                        <td class="px-4 py-2">{{ item.sku }}</td>
                        <td class="px-4 py-2">{{ item.name }}</td>
                        <td class="px-4 py-2">{{ item.qty }}</td>
                        <td class="px-4 py-2">
                          {{ item.qty - getUsedQty(item.id) - (Number(selectedWhole[item.id]?.qty) || 0) }}
                        </td>
                        <td class="px-4 py-2">
                          <input type="number" v-model.number="selectedWhole[item.id].qty" min="0.01" :max="item.qty" step="0.01"
                            :disabled="!selectedWhole[item.id]?.checked" class="w-24 rounded border-gray-300" />
                          <div v-if="selectedWhole[item.id]?.qty && Number(selectedWhole[item.id].qty) > item.qty" class="text-xs text-red-600 mt-1">
                            Qty to Butcher tidak boleh melebihi Qty asal ({{ item.qty }})
                          </div>
                        </td>
                        <td class="px-4 py-2">{{ item.unit }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <button type="button" class="mt-2 px-3 py-1 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700"
                  @click="addSelectedToButcher"
                  :disabled="!Object.values(selectedWhole).some(v => v.checked && Number(v.qty) > 0) || wholeItems.some(item => selectedWhole[item.id]?.qty && Number(selectedWhole[item.id].qty) > item.qty)">
                  Add to Butcher
                </button>
              </div>

              <!-- Items (Butcher Process Input) -->
              <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-medium text-gray-900">Butcher Output (PCS Items)</h3>
                  <button
                    type="button"
                    @click="addItem"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                  >
                    Add Item
                  </button>
                </div>

                <div v-for="(item, idx) in form.items" :key="idx" class="mb-4 border rounded-lg">
                  <div class="flex items-center justify-between px-4 py-2 cursor-pointer bg-gray-50 rounded-t-lg" @click="expandedWhole[idx] = !expandedWhole[idx]">
                    <div>
                      <span class="font-semibold">{{ item.whole_item_name }}</span>
                      <span class="ml-2 text-sm text-gray-500">Qty: {{ item.whole_qty }} {{ item.whole_unit }}</span>
                    </div>
                    <div class="flex gap-2">
                      <button type="button" class="text-red-600" @click.stop="deleteWholeItem(idx, item)" title="Delete">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                      <button type="button" class="text-blue-600" @click.stop="expandedWhole[idx] = !expandedWhole[idx]" title="Expand/Collapse">
                        <svg v-if="expandedWhole[idx]" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  <div v-if="expandedWhole[idx]" class="p-4 bg-gray-50 border-t">
                    <!-- Tambahan field di atas list PCS -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                      <div>
                        <label class="block text-xs font-medium text-gray-700">Slaughter Date</label>
                        <input v-model="item.slaughter_date" type="date" class="mt-1 block w-full rounded-md border-gray-300" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700">Packing Date</label>
                        <input v-model="item.packing_date" type="date" class="mt-1 block w-full rounded-md border-gray-300" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700">Batch/EST</label>
                        <input v-model="item.batch_est" type="text" class="mt-1 block w-full rounded-md border-gray-300" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700">Qty Purchase</label>
                        <input v-model="item.qty_purchase" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700">Attachment PDF</label>
                        <input type="file" accept="application/pdf" @change="e => item.attachment_pdf = e.target.files[0]" class="mt-1 block w-full" />
                        <div v-if="item.attachment_pdf" class="text-xs text-green-700 mt-1">{{ item.attachment_pdf.name }}</div>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700">Upload Image</label>
                        <input type="file" accept="image/*" @change="e => handleImageUpload(e, item)" class="mt-1 block w-full" />
                        <div v-if="item.upload_image" class="text-xs text-green-700 mt-1">{{ item.upload_image.name }}</div>
                        <div v-if="item.upload_image_preview" class="mt-2">
                          <img :src="item.upload_image_preview" alt="Preview" class="max-h-32 rounded border" />
                        </div>
                      </div>
                    </div>
                    <!-- List PCS Items -->
                    <div class="mb-2 font-semibold">PCS Items</div>
                    <div v-for="(pcs, pcsIdx) in item.pcs" :key="pcsIdx" class="grid grid-cols-1 md:grid-cols-7 gap-2 mb-2 items-center relative">
                      <div class="relative w-full">
                        <select v-model="pcs.pcs_item_id" @change="onPcsSelect(idx, pcsIdx)" class="rounded border-gray-300 w-full" required>
                          <option value="">Pilih Item PCS</option>
                          <option v-for="item in pcsItems" :key="item.id" :value="item.id">
                            {{ item.name }} ({{ item.sku }})
                          </option>
                        </select>
                      </div>
                      <select v-model="pcs.unit_id" class="rounded border-gray-300">
                        <option value="">Pilih Unit</option>
                        <option v-for="u in pcs.unit_options || []" :key="u.id" :value="u.id">{{ u.name }}</option>
                      </select>
                      <input v-model="pcs.pcs_qty" type="number" min="0.01" step="0.01" placeholder="Qty PCS" class="rounded border-gray-300" />
                      <input v-model="pcs.qty" type="number" min="0.01" step="0.01" :placeholder="'Qty ('+item.whole_unit+')'" class="rounded border-gray-300" />
                      <span>{{ item.whole_unit }}</span>
                      <label class="flex items-center gap-1"><input type="checkbox" v-model="pcs.costs_0" /> Costs 0</label>
                      <button type="button" class="text-red-600" @click="removePcsFromWhole(idx, pcsIdx)">Delete</button>
                    </div>
                    <button type="button" class="mt-2 px-3 py-1 rounded bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200" @click="addPcsToWhole(idx)">+ Add PCS Item</button>
                    <div class="mt-4 font-semibold">Susut Air</div>
                    <div class="flex gap-2 items-center mt-1">
                      <input v-model="item.susut_air.qty" type="number" min="0" step="0.01" :placeholder="'Qty ('+item.whole_unit+')'" class="rounded border-gray-300" />
                      <span>{{ item.whole_unit }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Certificates -->
              <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-medium text-gray-900">Halal Certificates</h3>
                  <button
                    type="button"
                    @click="() => form.certificates.push({ producer_name: '', certificate_number: '', file: null })"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                  >
                    Add Certificate
                  </button>
                </div>

                <div v-for="(cert, index) in form.certificates" :key="index" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 p-4 border rounded-md">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Producer Name</label>
                    <input
                      v-model="cert.producer_name"
                      type="text"
                      class="mt-1 block w-full rounded-md border-gray-300"
                      required
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Certificate Number</label>
                    <input
                      v-model="cert.certificate_number"
                      type="text"
                      class="mt-1 block w-full rounded-md border-gray-300"
                      required
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Certificate File</label>
                    <input
                      type="file"
                      @change="e => cert.file = e.target.files[0]"
                      class="mt-1 block w-full"
                      accept=".pdf,.jpg,.jpeg,.png"
                      required
                    />
                  </div>
                  <div class="col-span-full flex justify-end">
                    <button
                      type="button"
                      @click="() => form.certificates.splice(index, 1)"
                      class="text-red-600 hover:text-red-900"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea
                  v-model="form.notes"
                  rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300"
                ></textarea>
              </div>

              <!-- Submit Button -->
              <div class="flex justify-end">
                <button
                  type="submit"
                  class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                  :disabled="form.processing"
                >
                  {{ form.processing ? 'Saving...' : 'Save' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, watch, nextTick } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  warehouses: Array,
  units: Array,
  pcsItems: Array
})

const form = useForm({
  warehouse_id: '',
  good_receive_id: '',
  items: [],
  certificates: [],
  notes: ''
})

const goodReceiveInput = ref('')
const goodReceiveSuggestions = ref([])
const goodReceiveLoading = ref(false)
const goodReceiveDropdown = ref(false)
const selectedGoodReceive = ref(null)
const wholeItems = ref([])
const selectedWhole = ref({})
const expandedWhole = ref({})
const pcsItems = ref(props.pcsItems || [])

const fetchGoodReceiveSuggestions = async (q) => {
  goodReceiveLoading.value = true
  try {
    const res = await axios.get('/api/good-receives/autocomplete', { params: { q, warehouse_id: form.warehouse_id } })
    goodReceiveSuggestions.value = res.data
    goodReceiveDropdown.value = true
  } finally {
    goodReceiveLoading.value = false
  }
}

const selectGoodReceive = async (gr) => {
  selectedGoodReceive.value = gr
  form.good_receive_id = gr.id
  goodReceiveInput.value = `${gr.gr_number} - ${gr.supplier_name} (${gr.receive_date})`
  goodReceiveDropdown.value = false
  // fetch items for this good receive
  const res = await axios.get(`/api/good-receives/${gr.id}/items`)
  console.log('Whole items:', res.data)
  wholeItems.value = res.data
  // Inisialisasi selectedWhole agar tidak undefined
  wholeItems.value.forEach(item => {
    if (!selectedWhole.value[item.id]) {
      selectedWhole.value[item.id] = { checked: false, qty: '' }
    }
  })
  // reset items form
  form.items = []
}

const addItem = () => {
  form.items.push({
    whole_item_id: '',
    pcs_item_id: '',
    whole_qty: '',
    pcs_qty: '',
    unit_id: '',
    pcs: []
  })
}

const removeItem = (index) => {
  form.items.splice(index, 1)
}

const addSelectedToButcher = () => {
  // Ambil item yang dicentang dan qty > 0
  const items = wholeItems.value.filter(item => selectedWhole.value[item.id]?.checked && Number(selectedWhole.value[item.id]?.qty) > 0)
  items.forEach(item => {
    form.items.push({
      whole_item_id: item.id,
      whole_item_name: item.name,
      whole_qty: Number(selectedWhole.value[item.id].qty),
      whole_unit: item.unit,
      pcs: [],
      susut_air: { qty: '', unit: item.unit }
    })
    // Optional: reset checkbox & qty
    selectedWhole.value[item.id].checked = false
    selectedWhole.value[item.id].qty = ''
  })
}

const setWholeChecked = (id, checked) => {
  if (!selectedWhole.value[id]) selectedWhole.value[id] = { checked: false, qty: '' }
  selectedWhole.value[id].checked = checked
}
const setWholeQty = (id, qty) => {
  if (!selectedWhole.value[id]) selectedWhole.value[id] = { checked: false, qty: '' }
  selectedWhole.value[id].qty = qty
}

const addPcsToWhole = (idx) => {
  form.items[idx].pcs.push({
    pcs_item_id: '',
    pcs_item_name: '',
    pcs_unit: '',
    pcs_qty: '',
    qty: '',
    costs_0: false
  })
}
const removePcsFromWhole = (idx, pcsIdx) => {
  form.items[idx].pcs.splice(pcsIdx, 1)
}

const getItemUnits = (itemId, itemName) => {
  // Dummy: return ['Small', 'Medium', 'Large']
  // TODO: fetch real units from items table if needed
  return ['Small', 'Medium', 'Large']
}

const submit = () => {
  // FLATTEN items: setiap PCS pada setiap Whole menjadi 1 item di array items
  const flatItems = [];
  form.items.forEach(whole => {
    (whole.pcs || []).forEach(pcs => {
      flatItems.push({
        whole_item_id: whole.whole_item_id,
        whole_item_name: whole.whole_item_name,
        whole_qty: whole.whole_qty,
        whole_unit: whole.whole_unit,
        slaughter_date: whole.slaughter_date,
        packing_date: whole.packing_date,
        batch_est: whole.batch_est,
        qty_purchase: whole.qty_purchase,
        attachment_pdf: whole.attachment_pdf,
        upload_image: whole.upload_image,
        susut_air: whole.susut_air,
        pcs_item_id: pcs.pcs_item_id,
        pcs_item_name: pcs.pcs_item_name,
        pcs_qty: pcs.pcs_qty,
        unit_id: pcs.unit_id,
        costs_0: pcs.costs_0,
        qty: pcs.qty,
        qty_kg: pcs.qty,
      });
    });
  });
  // Debug log
  console.log('FLATTENED ITEMS:', flatItems);
  // Validasi frontend sebelum submit
  for (let i = 0; i < flatItems.length; i++) {
    const item = flatItems[i];
    if (!item.pcs_item_id || !item.pcs_qty || !item.unit_id) {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        html: `PCS item ke-${i+1} belum lengkap.<br>Pastikan pilih item PCS, isi qty, dan pilih unit.`
      });
      return;
    }
  }
  form.items = flatItems; // <-- assign langsung agar FormData tetap terbentuk
  form.processing = true;
  form.post(route('butcher-processes.store'), {
    onSuccess: () => {
      form.processing = false;
      Swal.fire('Sukses', 'Butcher process berhasil disimpan!', 'success');
      form.reset();
      selectedGoodReceive.value = null;
      wholeItems.value = [];
    },
    onError: (errors) => {
      form.processing = false;
      let msg = 'Terjadi kesalahan saat menyimpan data';
      if (errors && typeof errors === 'object') {
        if (errors.error) {
          msg = errors.error;
        } else if (Object.values(errors).length) {
          msg = Object.values(errors).join('<br>');
        }
      }
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        html: msg
      });
    },
    onFinish: () => {
      form.processing = false;
    }
  });
}

function handleImageUpload(e, item) {
  const file = e.target.files[0];
  item.upload_image = file;
  if (item.upload_image_preview) {
    URL.revokeObjectURL(item.upload_image_preview);
  }
  item.upload_image_preview = file ? URL.createObjectURL(file) : '';
}

function deleteWholeItem(idx, item) {
  // Kembalikan qty ke selectedWhole
  if (item.whole_item_id && selectedWhole.value[item.whole_item_id]) {
    selectedWhole.value[item.whole_item_id].qty = '';
    selectedWhole.value[item.whole_item_id].checked = false;
  }
  // Hapus dari form.items
  form.items.splice(idx, 1);
  // Tutup expandable
  expandedWhole.value[idx] = false;
}

function getUsedQty(itemId) {
  return form.items
    .filter(i => i.whole_item_id === itemId)
    .reduce((sum, i) => sum + Number(i.whole_qty || 0), 0);
}

function onPcsSelect(idx, pcsIdx) {
  const pcs = form.items[idx].pcs[pcsIdx];
  const selected = pcsItems.value.find(i => i.id == pcs.pcs_item_id);
  if (selected) {
    pcs.pcs_item_name = selected.name;
    pcs.unit_options = [
      selected.unit_small ? { id: selected.small_unit_id, name: selected.unit_small } : null,
      selected.unit_medium ? { id: selected.medium_unit_id, name: selected.unit_medium } : null,
      selected.unit_large ? { id: selected.large_unit_id, name: selected.unit_large } : null,
    ].filter(Boolean);
    pcs.unit_id = '';
  } else {
    pcs.pcs_item_name = '';
    pcs.unit_options = [];
    pcs.unit_id = '';
  }
}

const handleGoodReceiveInput = (e) => {
  fetchGoodReceiveSuggestions(e.target.value)
}

const handleGoodReceiveBlur = () => {
  setTimeout(() => { goodReceiveDropdown.value = false }, 200)
}

// Reset good receive input & items jika warehouse diganti
watch(() => form.warehouse_id, () => {
  goodReceiveInput.value = ''
  goodReceiveSuggestions.value = []
  goodReceiveDropdown.value = false
  selectedGoodReceive.value = null
  wholeItems.value = []
  form.good_receive_id = ''
  form.items = []
})
</script> 