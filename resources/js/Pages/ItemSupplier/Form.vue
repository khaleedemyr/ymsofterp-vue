<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-10">
      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <h1 class="text-2xl font-bold mb-8 flex items-center gap-2 text-blue-700">
          <i class="fa-solid fa-link text-blue-500"></i> Input Item Supplier
        </h1>
        <form @submit.prevent="submit" class="space-y-6">
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Supplier</label>
            <select v-model="form.supplier_id" class="input input-bordered w-full" required>
              <option value="">Pilih Supplier</option>
              <option v-for="s in props.suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Outlet (bisa lebih dari satu)</label>
            <div class="flex flex-wrap gap-2 mb-2">
              <span v-for="o in selectedOutlets" :key="o.id_outlet" class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full flex items-center gap-1">
                {{ o.nama_outlet }}
                <button type="button" @click="removeOutlet(o.id_outlet)" class="ml-1 text-red-500 hover:text-red-700"><i class="fa fa-times"></i></button>
              </span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
              <button v-for="o in props.outlets" :key="o.id_outlet" type="button" @click="selectOutlet(o)" :disabled="form.outlet_ids.includes(o.id_outlet)" class="w-full px-3 py-2 rounded border border-blue-200 hover:bg-blue-50 text-left flex items-center gap-2" :class="form.outlet_ids.includes(o.id_outlet) ? 'bg-blue-100 text-blue-700 font-bold' : ''">
                <i class="fa fa-store"></i> {{ o.nama_outlet }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Daftar Item</label>
            <div v-for="(item, idx) in form.items" :key="idx" class="grid grid-cols-12 gap-2 mb-2 items-end">
              <div class="col-span-4 relative">
                <input type="text" v-model="item.itemName" @input="onItemInput(idx, $event)" @focus="onItemInput(idx, $event)" @blur="onItemBlur(idx)" @keydown.down="onItemKeydown(idx, $event)" @keydown.up="onItemKeydown(idx, $event)" @keydown.enter="onItemKeydown(idx, $event)" @keydown.esc="onItemKeydown(idx, $event)" :id="`item-input-${idx}`" class="input input-bordered w-full" placeholder="Cari item..." autocomplete="off" required />
                <Teleport to="body">
                  <div v-if="item.showItemDropdown && item.itemSuggestions.length" :style="getDropdownStyle(idx)" :id="`autocomplete-dropdown-${idx}`" class="fixed z-[99999] bg-white border border-blue-200 rounded shadow max-w-xs w-[260px] max-h-96 overflow-auto mt-1">
                    <div v-for="(s, sidx) in item.itemSuggestions" :key="s.id" :id="`autocomplete-item-${idx}-${sidx}`" @mousedown.prevent="selectItem(idx, s)" :class="['px-3 py-2 flex justify-between items-center cursor-pointer', item.highlightedIndex === sidx ? 'bg-blue-100' : 'hover:bg-blue-50']">
                      <div>
                        <div class="font-medium">{{ s.name }}</div>
                        <div class="text-xs text-gray-500">{{ s.sku }}</div>
                      </div>
                      <div class="text-sm text-gray-600">{{ s.unit_small || s.unit || '' }}</div>
                    </div>
                  </div>
                </Teleport>
                <div v-if="item.loading" class="absolute right-2 top-2">
                  <i class="fa fa-spinner fa-spin text-blue-400"></i>
                </div>
              </div>
              <div class="col-span-3">
                <select v-model="item.unit_id" class="input input-bordered w-full" required>
                  <option value="">Pilih Unit</option>
                  <option v-for="u in item.unitOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
              <div class="col-span-3">
                <input type="number" v-model.number="item.price" min="0" class="input input-bordered w-full" placeholder="Harga" required />
              </div>
              <div class="col-span-2 flex gap-1">
                <button type="button" @click="removeItem(idx)" class="btn bg-red-100 text-red-600 hover:bg-red-200"><i class="fa fa-trash"></i></button>
              </div>
            </div>
            <button type="button" @click="addItem" class="btn bg-green-100 text-green-700 hover:bg-green-200 mt-2"><i class="fa fa-plus"></i> Tambah Item</button>
          </div>

          <div class="flex justify-end gap-2 mt-8">
            <button type="button" @click="goBack" class="btn px-6 py-2 rounded-lg font-bold bg-gradient-to-r from-gray-200 to-gray-400 text-gray-700 shadow-md hover:from-gray-300 hover:to-gray-500 active:scale-95 transition-all">Batal</button>
            <button type="submit" class="btn px-6 py-2 rounded-lg font-bold bg-gradient-to-r from-blue-500 to-blue-700 text-white shadow-lg hover:from-blue-600 hover:to-blue-800 active:scale-95 transition-all flex items-center gap-2">
              <i class="fa fa-save"></i> Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  itemSupplier: Object, // null jika create, object jika edit
  suppliers: Array,
  outlets: Array
})

const form = reactive({
  supplier_id: '',
  outlet_ids: [],
  items: [
    { item_id: '', itemName: '', unit_id: '', price: '', itemSuggestions: [], showItemDropdown: false, unitOptions: [] }
  ]
})

const supplierName = ref('')
const supplierSuggestions = ref([])
const showSupplierDropdown = ref(false)
function onSupplierInput() {
  if (!supplierName.value || supplierName.value.length < 2) {
    supplierSuggestions.value = []
    showSupplierDropdown.value = false
    return
  }
  axios.get('/api/suppliers', { params: { q: supplierName.value } }).then(res => {
    supplierSuggestions.value = res.data
    showSupplierDropdown.value = true
  })
}
function selectSupplier(s) {
  form.supplier_id = s.id
  supplierName.value = s.name
  showSupplierDropdown.value = false
}

const outletQuery = ref('')
const outletSuggestions = ref([])
const showOutletDropdown = ref(false)
const selectedOutlets = ref([])
function onOutletInput() {
  if (!outletQuery.value || outletQuery.value.length < 2) {
    outletSuggestions.value = []
    showOutletDropdown.value = false
    return
  }
  axios.get('/api/outlets', { params: { q: outletQuery.value } }).then(res => {
    outletSuggestions.value = res.data
    showOutletDropdown.value = true
  })
}
function selectOutlet(o) {
  if (!selectedOutlets.value.find(x => x.id_outlet === o.id_outlet)) {
    selectedOutlets.value.push(o)
    form.outlet_ids.push(o.id_outlet)
  }
  outletQuery.value = ''
  showOutletDropdown.value = false
}
function removeOutlet(id) {
  selectedOutlets.value = selectedOutlets.value.filter(x => x.id_outlet !== id)
  form.outlet_ids = form.outlet_ids.filter(x => x !== id)
}

function addItem() {
  form.items.push({ item_id: '', itemName: '', unit_id: '', price: '', itemSuggestions: [], showItemDropdown: false, unitOptions: [] })
}
function removeItem(idx) {
  form.items.splice(idx, 1)
}
function onItemInput(idx, e) {
  const item = form.items[idx]
  const value = e ? e.target.value : item.itemName
  item.item_id = ''
  item.itemName = value
  item.showItemDropdown = true
  item.loading = true
  item.highlightedIndex = 0
  if (!value || value.length < 2) {
    item.itemSuggestions = []
    item.showItemDropdown = false
    item.loading = false
    return
  }
  axios.get('/api/items', { params: { q: value } })
    .then(res => {
      item.itemSuggestions = res.data
      item.showItemDropdown = true
      item.highlightedIndex = 0
    })
    .finally(() => {
      item.loading = false
    })
}
function onItemBlur(idx) {
  setTimeout(() => {
    form.items[idx].showItemDropdown = false
  }, 200)
}
function onItemKeydown(idx, e) {
  const item = form.items[idx]
  if (!item.showItemDropdown || !item.itemSuggestions.length) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    item.highlightedIndex = (item.highlightedIndex + 1) % item.itemSuggestions.length
    scrollToHighlighted(idx)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    item.highlightedIndex = (item.highlightedIndex - 1 + item.itemSuggestions.length) % item.itemSuggestions.length
    scrollToHighlighted(idx)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (item.highlightedIndex >= 0 && item.itemSuggestions[item.highlightedIndex]) {
      selectItem(idx, item.itemSuggestions[item.highlightedIndex])
    }
  } else if (e.key === 'Escape') {
    item.showItemDropdown = false
  }
}
function scrollToHighlighted(idx) {
  setTimeout(() => {
    const item = form.items[idx]
    const el = document.getElementById(`autocomplete-item-${idx}-${item.highlightedIndex}`)
    if (el) el.scrollIntoView({ block: 'nearest' })
  }, 0)
}
function getDropdownStyle(idx) {
  const input = document.getElementById(`item-input-${idx}`)
  if (!input) return {}
  const rect = input.getBoundingClientRect()
  return {
    position: 'fixed',
    left: `${rect.left}px`,
    top: `${rect.bottom}px`,
    width: `${rect.width}px`,
    zIndex: 99999
  }
}
function selectItem(idx, i) {
  const item = form.items[idx]
  item.item_id = i.id
  item.itemName = i.name
  item.showItemDropdown = false
  item.highlightedIndex = -1
  // Fetch unit options
  axios.get(`/retail-food/get-item-units/${i.id}`).then(res => {
    item.unitOptions = res.data.units
  })
}

onMounted(() => {
  if (props.itemSupplier) {
    // Untuk edit, mapping ke multiple item jika datanya sudah support
    form.supplier_id = props.itemSupplier.supplier_id
    form.outlet_ids = props.itemSupplier.item_supplier_outlets.map(o => o.outlet_id)
    supplierName.value = props.itemSupplier.supplier?.name || ''
    selectedOutlets.value = props.itemSupplier.item_supplier_outlets.map(o => ({
      id_outlet: o.outlet_id,
      nama_outlet: o.outlet?.nama_outlet || ''
    }))
    form.items = props.itemSupplier.items.map(it => ({
      item_id: it.item_id,
      itemName: it.item?.name || '',
      unit_id: it.unit_id,
      price: it.price,
      itemSuggestions: [],
      showItemDropdown: false,
      unitOptions: []
    }))
    // Fetch unit options untuk setiap item
    form.items.forEach((item, idx) => {
      if (item.item_id) {
        axios.get(`/retail-food/get-item-units/${item.item_id}`).then(res => {
          item.unitOptions = res.data.units
        })
      }
    })
  }
})

let isLoading = ref(false)
async function submit() {
  const confirm = await Swal.fire({
    title: 'Simpan Data?',
    text: 'Yakin ingin menyimpan data item supplier ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
  })
  if (!confirm.isConfirmed) return
  isLoading.value = true
  Swal.fire({
    title: 'Menyimpan...',
    allowOutsideClick: false,
    didOpen: () => { Swal.showLoading() }
  })
  try {
    if (props.itemSupplier) {
      await axios.put(`/item-supplier/${props.itemSupplier.id}`, form)
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data berhasil diupdate', timer: 1500, showConfirmButton: false })
    } else {
      await axios.post('/item-supplier', form)
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data berhasil disimpan', timer: 1500, showConfirmButton: false })
    }
    router.visit('/item-supplier')
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: e.response?.data?.message || 'Gagal menyimpan data' })
  } finally {
    isLoading.value = false
  }
}
function goBack() {
  router.visit('/item-supplier')
}
</script> 