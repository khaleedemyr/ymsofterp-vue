<template>
  <AppLayout>
    <Head title="Create Butcher Process" />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <form @submit.prevent="validateAndSubmit" @keydown.enter.prevent>
              <!-- Basic Information -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                  <select
                    v-model="form.warehouse_id"
                    class="mt-1 block w-full rounded-md border-gray-300"
                    required
                    @keydown.enter.prevent
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
                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Harga PO</th>
                        <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Harga per {{ getUnitName(item?.small_unit_id) || 'Small Unit' }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in wholeItems" :key="item.id">
                        <td class="px-2 py-2">
                          <input type="checkbox" :checked="!!selectedWhole[item.id]?.checked" @change="e => setWholeChecked(item.id, e.target.checked)" />
                        </td>
                        <td class="px-4 py-2">{{ item.sku }}</td>
                        <td class="px-4 py-2">{{ item.name }}</td>
                        <td class="px-4 py-2">
                          {{ item.qty_received ?? 0 }}
                        </td>
                        <td class="px-4 py-2">
                          {{ item.sisa_qty ?? 0 }}
                        </td>
                        <td class="px-4 py-2">
                          <input 
                            type="number" 
                            v-model.number="selectedWhole[item.id].qty" 
                            min="0.01" 
                            :max="item.sisa_qty" 
                            step="0.01"
                            :disabled="!selectedWhole[item.id]?.checked" 
                            class="w-24 rounded border-gray-300" 
                          />
                          <div v-if="selectedWhole[item.id]?.qty && Number(selectedWhole[item.id].qty) > item.sisa_qty" class="text-xs text-red-600 mt-1">
                            Qty to Butcher tidak boleh melebihi sisa qty ({{ item.sisa_qty }})
                          </div>
                        </td>
                        <td class="px-4 py-2">{{ item.unit }}</td>
                        <td class="px-4 py-2">
                          {{ formatRupiah(item.po_price) }} / {{ getUnitName(item?.po_unit_id) }}
                        </td>
                        <td class="px-4 py-2">
                          {{ formatRupiah(pricePerSmallUnit(item)) }} / {{ getUnitName(item?.small_unit_id) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <button type="button" class="mt-2 px-3 py-1 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700"
                  @click="addSelectedToButcher"
                  :disabled="!Object.values(selectedWhole).some(v => v.checked && Number(v.qty) > 0) || wholeItems.some(item => selectedWhole[item.id]?.qty && Number(selectedWhole[item.id]?.qty) > item.sisa_qty)">
                  Add to Butcher
                </button>
              </div>

              <!-- Items (Butcher Process Input) -->
              <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-medium text-gray-900">Butcher Output (PCS Items)</h3>
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
                    <div class="text-xs text-gray-600 mb-2">
                      <i class="fas fa-info-circle mr-1"></i>
                      Pilih item PCS, kemudian pilih unit yang tersedia untuk item tersebut (Small, Medium, atau Large)
                    </div>
                    <!-- Header untuk kolom-kolom -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 mb-2 items-center text-xs font-medium text-gray-600 bg-gray-50 p-2 rounded">
                      <div class="lg:col-span-4">Item PCS</div>
                      <div class="lg:col-span-2">Unit</div>
                      <div class="lg:col-span-1">Qty PCS</div>
                      <div class="lg:col-span-1">Qty ({{ item.whole_unit }})</div>
                      <div class="lg:col-span-1">Unit</div>
                      <div class="lg:col-span-1">Costs 0</div>
                      <div class="lg:col-span-1">Action</div>
                      <div class="lg:col-span-2">MAC Preview</div>
                    </div>
                    <div v-for="(pcs, pcsIdx) in item.pcs" :key="pcsIdx" class="grid grid-cols-1 lg:grid-cols-12 gap-3 mb-3 items-center relative">
                      <div class="lg:col-span-4">
                        <Multiselect
                          v-model="pcs.pcs_item_id"
                          :options="filteredPcsItems"
                          :searchable="true"
                          :close-on-select="true"
                          :clear-on-select="false"
                          :preserve-search="true"
                          placeholder="Pilih atau cari item PCS..."
                          track-by="id"
                          label="name"
                          :preselect-first="false"
                          @change="onPcsSelect(idx, pcsIdx)"
                          class="w-full"
                        />
                      </div>
                      <div class="lg:col-span-2">
                        <select v-model="pcs.unit_id" class="rounded border-gray-300 w-full" :class="{ 'border-red-300': !pcs.unit_id && pcs.pcs_item_id }">
                          <option value="">Pilih Unit</option>
                          <option v-for="u in pcs.unit_options || []" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <div v-if="!pcs.unit_id && pcs.pcs_item_id && pcs.unit_options && pcs.unit_options.length > 0" class="text-xs text-red-600 mt-1">
                          Unit harus dipilih
                        </div>
                        <div v-if="pcs.pcs_item_id && (!pcs.unit_options || pcs.unit_options.length === 0)" class="text-xs text-orange-600 mt-1">
                          <i class="fas fa-exclamation-triangle mr-1"></i>
                          Item ini tidak memiliki unit yang valid. Silakan periksa konfigurasi unit di master data item.
                        </div>
                        <div v-if="pcs.pcs_item_id && pcs.unit_options && pcs.unit_options.length === 1" class="text-xs text-blue-600 mt-1">
                          <i class="fas fa-info-circle mr-1"></i>
                          Item ini hanya memiliki 1 unit yang tersedia.
                        </div>
                      </div>
                      <div class="lg:col-span-1">
                        <input v-model="pcs.pcs_qty" type="number" min="0.01" step="0.01" placeholder="Qty PCS" class="rounded border-gray-300 w-full" />
                      </div>
                      <div class="lg:col-span-1">
                        <input v-model="pcs.qty" type="number" min="0.01" step="0.01" :placeholder="'Qty ('+item.whole_unit+')'" class="rounded border-gray-300 w-full" />
                      </div>
                      <div class="lg:col-span-1 text-center">
                        <span class="text-sm text-gray-600">{{ item.whole_unit }}</span>
                      </div>
                      <div class="lg:col-span-1">
                        <label class="flex items-center gap-1 text-sm"><input type="checkbox" v-model="pcs.costs_0" /> Costs 0</label>
                      </div>
                      <div class="lg:col-span-1">
                        <button type="button" class="text-red-600 hover:text-red-800 text-sm" @click="removePcsFromWhole(idx, pcsIdx)">Delete</button>
                      </div>
                      <!-- Kolom baru: MAC PCS Preview -->
                      <div class="lg:col-span-2 text-xs text-blue-700 font-semibold leading-tight">
                        <div>MAC /Gram: {{ formatRupiah(pcs.costs_0 ? 0 : (macPcsPreviewArray[idx]?.[pcsIdx] || 0)) }}</div>
                        <div v-if="pcs.pcs_item_id && (typeof pcs.pcs_item_id === 'object' ? pcs.pcs_item_id.id : pcs.pcs_item_id)">MAC /Pcs: {{ formatRupiah(pcs.costs_0 ? 0 : macPcsPerPcs(idx, pcsIdx)) }}</div>
                      </div>
                    </div>
                    <button type="button" class="mt-2 px-3 py-1 rounded bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200" @click="addPcsToWhole(idx)">+ Add PCS Item</button>
                    <div class="mt-4 font-semibold">Susut Air</div>
                    <div class="flex gap-2 items-center mt-1">
                      <input v-model="item.susut_air.qty" type="number" min="0" step="0.01" :placeholder="'Qty ('+item.whole_unit+')'" class="rounded border-gray-300" />
                      <button type="button" class="px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200" @click="calculateSusutAir(idx)">
                        Hitung
                      </button>
                      <span>{{ item.whole_unit }}</span>
                    </div>
                  </div>
                </div>

                <!-- Total Cost & MAC PCS Preview -->
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                  <div class="font-semibold mb-1">Preview Perhitungan:</div>
                  <div>Total Cost: <span class="font-mono">{{ formatRupiah(totalCostPreview) }}</span></div>
                  <div>MAC Gram: <span class="font-mono">{{ formatRupiah(macPcsPreview) }}</span></div>
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
    <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
      <div class="bg-white px-6 py-4 rounded shadow flex items-center gap-2">
        <span class="loader border-2 border-blue-500 border-t-transparent rounded-full w-6 h-6 animate-spin"></span>
        <span>Menyimpan...</span>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, watch, nextTick, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  warehouses: Array,
  units: Array,
  pcsItems: Array
})

// Debug: Log PCS items data
console.log('PCS Items from props:', props.pcsItems?.length || 0);
if (props.pcsItems) {
  const picanhaItem = props.pcsItems.find(item => item.name?.includes('Beef Picanha 200gr'));
  if (picanhaItem) {
    console.log('Found Picanha item in props:', picanhaItem);
  }
}

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

// Ambil kode kategori yang diizinkan
const allowedCategoryCodes = ['MT', 'BK', 'BP', 'SP', 'SF'];

// Filter pcsItems agar hanya yang kategori MT, BK, BP, SP, SF yang muncul
const filteredPcsItems = computed(() => {
  return (props.pcsItems || []).filter(item =>
    allowedCategoryCodes.includes(item.category_code)
  );
});

// Add new refs for validation
const isSubmitting = ref(false)
const validationErrors = ref({})
const isLoading = ref(false)

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
  const items = wholeItems.value.filter(item => {
    return selectedWhole.value[item.id]?.checked && 
           Number(selectedWhole.value[item.id]?.qty) > 0 &&
           Number(selectedWhole.value[item.id]?.qty) <= item.sisa_qty;
  });

  items.forEach(item => {
    const qtyToButcher = Number(selectedWhole.value[item.id].qty);
    form.items.push({
      whole_item_id: Number(item.item_id),
      whole_item_name: item.name,
      whole_qty: qtyToButcher,
      whole_unit: item.unit,
      qty_purchase: item.qty_received ?? qtyToButcher,
      pcs: [],
      susut_air: { qty: '', unit: item.unit },
      small_conversion_qty: item.small_conversion_qty,
      po_price: item.po_price,
      po_unit_id: item.po_unit_id,
      small_unit_id: item.small_unit_id
    });
    // Kurangi sisa_qty
    item.sisa_qty = (item.sisa_qty ?? 0) - qtyToButcher;
    // Optional: reset checkbox & qty
    selectedWhole.value[item.id].checked = false;
    selectedWhole.value[item.id].qty = '';
    console.log('item yang di-add:', item);
  });
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
    costs_0: false,
    unit_options: [],
    unit_id: ''
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

// Add validation function
const validateForm = () => {
  validationErrors.value = {}
  let isValid = true

  // Validate warehouse
  if (!form.warehouse_id) {
    validationErrors.value.warehouse_id = 'Warehouse is required'
    isValid = false
  }

  // Validate good receive
  if (!form.good_receive_id) {
    validationErrors.value.good_receive_id = 'Good Receive is required'
    isValid = false
  }

  // Validate items
  if (!form.items || form.items.length === 0) {
    validationErrors.value.items = 'At least one item must be added'
    isValid = false
  }

  // Validate each item
  form.items.forEach((item, index) => {
    if (!item.whole_item_id) {
      validationErrors.value[`items.${index}.whole_item_id`] = 'Whole item is required'
      isValid = false
    }
    if (!item.whole_qty || item.whole_qty <= 0) {
      validationErrors.value[`items.${index}.whole_qty`] = 'Whole quantity must be greater than 0'
      isValid = false
    }
    if (!item.pcs || item.pcs.length === 0) {
      validationErrors.value[`items.${index}.pcs`] = 'At least one PCS item must be added'
      isValid = false
    }

    // Validate each PCS item
    item.pcs.forEach((pcs, pcsIndex) => {
      if (!pcs.pcs_item_id || (typeof pcs.pcs_item_id === 'object' && !pcs.pcs_item_id.id)) {
        validationErrors.value[`items.${index}.pcs.${pcsIndex}.pcs_item_id`] = 'PCS item is required'
        isValid = false
      }
      if (!pcs.unit_id) {
        validationErrors.value[`items.${index}.pcs.${pcsIndex}.unit_id`] = 'Unit is required'
        isValid = false
      }
      if (!pcs.pcs_qty || pcs.pcs_qty <= 0) {
        validationErrors.value[`items.${index}.pcs.${pcsIndex}.pcs_qty`] = 'PCS quantity must be greater than 0'
        isValid = false
      }
    })
  })

  // Validate certificates
  if (!form.certificates || form.certificates.length === 0) {
    validationErrors.value.certificates = 'At least one halal certificate is required'
    isValid = false
  } else {
    // Validate each certificate
    form.certificates.forEach((cert, index) => {
      if (!cert.producer_name) {
        validationErrors.value[`certificates.${index}.producer_name`] = 'Producer name is required'
        isValid = false
      }
      if (!cert.certificate_number) {
        validationErrors.value[`certificates.${index}.certificate_number`] = 'Certificate number is required'
        isValid = false
      }
      if (!cert.file) {
        validationErrors.value[`certificates.${index}.file`] = 'Certificate file is required'
        isValid = false
      }
    })
  }

  return isValid
}

// Modify submit function to include validation
const validateAndSubmit = async () => {
  if (isSubmitting.value || isLoading.value) return

  if (!validateForm()) {
    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      html: Object.values(validationErrors.value).join('<br>'),
      confirmButtonText: 'OK'
    })
    return
  }

  // SweetAlert konfirmasi
  const result = await Swal.fire({
    title: 'Konfirmasi Simpan',
    text: 'Apakah Anda yakin ingin menyimpan butcher process ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, simpan!',
    cancelButtonText: 'Batal'
  })

  if (!result.isConfirmed) return

  isSubmitting.value = true
  isLoading.value = true
  try {
    await submit()
  } catch (error) {
    console.error('Error submitting form:', error)
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || error.message || 'Terjadi error saat simpan data.'
    })
  } finally {
    isSubmitting.value = false
    isLoading.value = false
  }
}

// Modify existing submit function
const submit = async () => {
  // Filter hanya item dengan whole_item_id yang valid
  form.items = form.items.filter(item => item.whole_item_id);
  console.log('form.items (before flatten):', JSON.stringify(form.items, null, 2));
  // FLATTEN items: setiap PCS pada setiap Whole menjadi 1 item di array items
  const flatItems = [];
  form.items.forEach((whole, idx) => {
    (whole.pcs || []).forEach((pcs, pcsIdx) => {
      let macPcs = 0;
      if (!pcs.costs_0) {
        // Ambil dari macPcsPreviewArray per row
        macPcs = macPcsPreviewArray.value[idx]?.[pcsIdx] || 0;
      }
      flatItems.push({
        whole_item_id: whole.whole_item_id,
        whole_item_name: whole.whole_item_name,
        whole_qty: whole.whole_qty,
        whole_unit: whole.whole_unit,
        slaughter_date: whole.slaughter_date,
        packing_date: whole.packing_date,
        batch_est: whole.batch_est,
        qty_purchase: whole.qty_purchase,
        susut_air: whole.susut_air,
        pcs_item_id: pcs.pcs_item_id?.id || pcs.pcs_item_id, // Handle both object and ID
        pcs_item_name: pcs.pcs_item_name,
        pcs_qty: pcs.pcs_qty,
        unit_id: pcs.unit_id,
        costs_0: pcs.costs_0,
        qty: pcs.qty,
        qty_kg: pcs.qty,
        mac_pcs: macPcs
      });
      console.log('KIRIM mac_pcs:', macPcs, 'costs_0:', pcs.costs_0, 'pcs:', pcs.pcs_item_name);
    });
  });
  console.log('flatItems (to be sent):', JSON.stringify(flatItems, null, 2));

  // Create FormData for file uploads
  const formData = new FormData();
  formData.append('warehouse_id', form.warehouse_id);
  formData.append('good_receive_id', form.good_receive_id);
  formData.append('items', JSON.stringify(flatItems));
  // Kirim file untuk setiap item
  form.items.forEach((whole, wIdx) => {
    if (whole.attachment_pdf) {
      formData.append(`items_files[${wIdx}][attachment_pdf]`, whole.attachment_pdf);
    }
    if (whole.upload_image) {
      formData.append(`items_files[${wIdx}][upload_image]`, whole.upload_image);
    }
  });
  // Append certificates
  if (form.certificates && form.certificates.length > 0) {
    formData.append('certificates', JSON.stringify(form.certificates));
    form.certificates.forEach((cert, index) => {
      if (cert.file) {
        formData.append(`certificate_files[${index}]`, cert.file);
      }
    });
  }

  try {
    const response = await axios.post(route('butcher-processes.store'), formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    
    if (response.data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: 'Butcher process berhasil disimpan!'
      });
      window.location.href = route('butcher-processes.index');
    } else {
      throw new Error(response.data.message || 'Failed to create butcher process');
        }
  } catch (error) {
    throw error;
    }
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
  // For multiselect, pcs.pcs_item_id will be the selected item object
  const selected = pcs.pcs_item_id;
  if (selected && selected.id) {
    pcs.pcs_item_name = selected.name;
    pcs.small_conversion_qty = selected.small_conversion_qty || 1;
    
    // Debug logging
    console.log('Selected item:', selected.name);
    console.log('Small unit:', selected.small_unit_id, selected.small_unit_name);
    console.log('Medium unit:', selected.medium_unit_id, selected.medium_unit_name);
    console.log('Large unit:', selected.large_unit_id, selected.large_unit_name);
    
    // Build unit options array with deduplication
    const unitOptions = [];
    const addedUnits = new Set();
    
    // Add units in order: small, medium, large
    const unitsToAdd = [
      { id: selected.small_unit_id, name: selected.small_unit_name },
      { id: selected.medium_unit_id, name: selected.medium_unit_name },
      { id: selected.large_unit_id, name: selected.large_unit_name }
    ];
    
    console.log('Units to add:', unitsToAdd);
    
    unitsToAdd.forEach(unit => {
      console.log('Processing unit:', unit);
      if (unit.id && unit.name) {
        const unitKey = `${unit.id}-${unit.name}`;
        console.log('Unit key:', unitKey);
        if (!addedUnits.has(unitKey)) {
          unitOptions.push({ id: unit.id, name: unit.name });
          addedUnits.add(unitKey);
          console.log('Added unit:', unit);
        } else {
          console.log('Unit already exists, skipping:', unit);
        }
      } else {
        console.log('Unit has null/empty id or name, skipping:', unit);
      }
    });
    
    console.log('After deduplication, unit options:', unitOptions);
    
    // If all units are the same, still show at least one
    if (unitOptions.length === 0 && (selected.small_unit_id || selected.medium_unit_id || selected.large_unit_id)) {
      console.log('No unit options after deduplication, trying fallback...');
      // Get the first available unit
      const firstUnit = unitsToAdd.find(unit => unit.id && unit.name);
      if (firstUnit) {
        unitOptions.push({ id: firstUnit.id, name: firstUnit.name });
        console.log('Added fallback unit:', firstUnit);
      } else {
        console.log('No fallback unit found');
      }
    }
    
    console.log('Final unit options:', unitOptions);
    
    pcs.unit_options = unitOptions;
    
    // Auto-select if only one unit is available
    if (unitOptions.length === 1) {
      pcs.unit_id = unitOptions[0].id;
      console.log('Auto-selected unit:', unitOptions[0].id, unitOptions[0].name);
    } else {
      pcs.unit_id = '';
      console.log('No auto-select, unit_id set to empty. Unit options count:', unitOptions.length);
    }
  } else {
    pcs.pcs_item_name = '';
    pcs.small_conversion_qty = 1;
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

// Add keydown handler for inputs
const handleKeyDown = (event) => {
  if (event.key === 'Enter') {
    event.preventDefault()
  }
}

// Tambahkan helper untuk preview total cost dan mac pcs
function formatRupiah(val) {
  if (val == null || isNaN(val)) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Total Cost Preview
const totalCostPreview = computed(() => {
  if (!form.items.length) return 0;
  const pricePerSmall = pricePerSmallUnit(form.items[0]);
  const qtyAddToButcherSmall = Number(form.items[0].whole_qty || 0) * (Number(form.items[0].small_conversion_qty) || 1);
  return pricePerSmall * qtyAddToButcherSmall;
});

// 1. Sum qty_kg (PCS) yang cost 0 = false, dikonversi ke gram (small unit)
const sumQtyKgCostFalseGram = computed(() => {
  let sum = 0;
  form.items.forEach(item => {
    if (Array.isArray(item.pcs)) {
      item.pcs.forEach(pcs => {
        if (!pcs.costs_0) {
          // qty (kg) ke gram
          const qtyKg = Number(pcs.qty || 0);
          sum += qtyKg * 1000; // 1 kg = 1000 gram
        }
      });
    }
  });
  return sum;
});

// 2. Cost per gram
const costPerGram = computed(() => {
  const totalCost = Number(totalCostPreview.value);
  const sumGram = Number(sumQtyKgCostFalseGram.value);
  return sumGram > 0 ? totalCost / sumGram : 0;
});

// 3. MAC PCS Preview per PCS item (array of array)
const macPcsPreviewArray = computed(() => {
  return form.items.map(item => {
    if (!Array.isArray(item.pcs)) return [];
    return item.pcs.map(pcs => {
      // Return cost per gram saja, tidak perlu multiply di sini
      return costPerGram.value;
    });
  });
});

// 4. MAC PCS Preview (total, misal untuk display satu nilai)
const macPcsPreview = computed(() => {
  // Ambil total MAC PCS dari item pertama dan pcs pertama (untuk display preview utama)
  if (!macPcsPreviewArray.value.length || !macPcsPreviewArray.value[0].length) return 0;
  return macPcsPreviewArray.value[0][0];
});

function pricePerSmallUnit(item) {
  const price = Number(item.po_price);
  const conv = Number(item.small_conversion_qty);
  if (!price || !conv) return 0;
  return price / conv;
}

function getUnitName(unitId) {
  const unit = props.units?.find(u => u.id == unitId);
  return unit ? unit.name : '-';
}

const calculateSusutAir = (idx) => {
  const item = form.items[idx];
  const qtyAddToButcher = Number(item.whole_qty || 0);
  const sumQtyKg = item.pcs.reduce((sum, pcs) => {
    return sum + Number(pcs.qty || 0);
  }, 0);
  let susutAir = qtyAddToButcher - sumQtyKg;
  susutAir = susutAir > 0 ? susutAir : 0;
  item.susut_air.qty = Number(susutAir.toFixed(2));
}

function macPcsPerPcs(idx, pcsIdx) {
  const pcs = form.items[idx]?.pcs?.[pcsIdx];
  if (!pcs || !pcs.pcs_item_id || pcs.costs_0) return 0;
  const macPerGram = macPcsPreviewArray.value[idx]?.[pcsIdx] || 0;
  // Use the small_conversion_qty stored in the pcs object
  const smallConv = Number(pcs.small_conversion_qty) || 1;
  return macPerGram * smallConv; // Sekarang benar: cost per gram × small conversion qty
}
</script> 

<style scoped>
.loader {
  border-width: 2px;
  border-style: solid;
  border-radius: 9999px;
  width: 1.5rem;
  height: 1.5rem;
  border-top-color: transparent;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Custom styling for vue-multiselect */
:deep(.multiselect) {
  min-height: 42px;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  min-width: 250px;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 10px 12px;
}

:deep(.multiselect__single) {
  padding: 10px 12px;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__input) {
  padding: 10px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  min-width: 300px;
  max-width: 400px;
}

:deep(.multiselect__option) {
  padding: 10px 12px;
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
  :deep(.multiselect) {
    min-width: 200px;
  }
  
  :deep(.multiselect__content-wrapper) {
    min-width: 250px;
    max-width: 350px;
  }
}
</style> 