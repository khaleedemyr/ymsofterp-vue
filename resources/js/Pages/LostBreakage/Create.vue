<template>
  <AppLayout>
    <div class="min-h-screen bg-gray-50 py-8 px-4 md:px-8">
      <div class="w-full max-w-5xl mx-auto bg-white rounded-2xl shadow-2xl p-6 md:p-8">
        <h1 class="text-2xl font-bold mb-8 flex items-center gap-2 text-orange-700">
          <i class="fa-solid fa-box-open text-orange-500"></i> {{ isEdit ? 'Edit' : 'Input' }} Lost &amp; Breakage
        </h1>

        <form @submit.prevent class="space-y-5">
          <!-- Header Fields -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Tanggal</label>
              <input type="date" v-model="form.date" class="input input-bordered w-full" required />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Outlet</label>
              <select v-model="form.outlet_id" :disabled="outletDisabled" class="input input-bordered w-full" required>
                <option value="">Pilih Outlet</option>
                <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
          </div>

          <!-- Items Section -->
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <h3 class="font-semibold text-gray-700">Items (Asset)</h3>
              <button type="button" @click="addItem" class="btn btn-sm btn-primary">
                <i class="fa fa-plus mr-1"></i> Tambah Item
              </button>
            </div>

            <div v-if="form.items.length === 0" class="text-center py-8 text-gray-400 border-2 border-dashed rounded-lg">
              <i class="fa fa-box-open text-4xl mb-2"></i>
              <p>Belum ada item. Klik "Tambah Item" untuk menambahkan.</p>
            </div>

            <div v-for="(item, idx) in form.items" :key="item._uid" class="bg-white border rounded-lg p-4 space-y-4 relative">
              <div class="flex justify-between items-start mb-2">
                <h4 class="font-medium text-gray-800">Item #{{ idx + 1 }}</h4>
                <button type="button" @click="removeItem(idx)" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition-colors text-sm font-medium" :disabled="form.items.length === 1" :class="form.items.length === 1 ? 'opacity-50 cursor-not-allowed' : ''">
                  <i class="fa fa-trash mr-1"></i> Hapus
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
                  <multiselect
                    v-model="item.selectedItem"
                    :options="assetItems"
                    :searchable="true"
                    :close-on-select="true"
                    :show-labels="false"
                    placeholder="Cari dan pilih item asset..."
                    label="name"
                    track-by="id"
                    @select="(sel) => onItemSelect(sel, idx)"
                    @remove="() => onItemRemove(idx)"
                    class="multiselect-custom"
                  >
                    <template #option="{ option }">
                      <div>
                        <div class="font-medium text-gray-900">{{ option.name }}</div>
                        <div class="text-xs text-gray-500">{{ option.category_name }} &middot; {{ option.sku || '-' }}</div>
                      </div>
                    </template>
                    <template #noResult>
                      <div class="text-center py-2 text-gray-500"><i class="fa-solid fa-search mr-2"></i>Tidak ditemukan</div>
                    </template>
                  </multiselect>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                  <input type="number" min="0.01" step="0.01" v-model.number="item.qty" class="input input-bordered w-full" required :disabled="!item.item_id" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                  <select v-model="item.unit_id" class="input input-bordered w-full" :disabled="!item.item_id">
                    <option v-for="u in item.availableUnits" :key="u.id" :value="u.id">{{ u.name }} ({{ u.type }})</option>
                  </select>
                </div>

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan / Alasan</label>
                  <textarea v-model="item.note" class="input input-bordered w-full" placeholder="Alasan lost/breakage (opsional)" rows="2"></textarea>
                </div>

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Foto Bukti</label>
                  <div class="flex items-center gap-3">
                    <input type="file" accept="image/*" @change="(e) => handlePhoto(e, idx)" class="file-input file-input-bordered w-full max-w-xs" />
                    <div v-if="item.photoUploading" class="text-sm text-blue-600"><i class="fa fa-spinner fa-spin"></i> Uploading...</div>
                    <img v-if="item.photoPreview" :src="item.photoPreview" class="w-16 h-16 object-cover rounded-lg border" />
                  </div>
                </div>
              </div>
            </div>

            <div v-if="form.items.length > 0" class="flex justify-center pt-2">
              <button type="button" @click="addItem" class="btn btn-sm btn-primary"><i class="fa fa-plus mr-1"></i> Tambah Item</button>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Catatan Umum</label>
            <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Catatan tambahan"></textarea>
          </div>

          <!-- Approval Flow -->
          <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Flow</h3>
            <p class="text-sm text-gray-600 mb-4">Tambahkan approver dalam urutan level terendah ke tertinggi.</p>
            <div class="mb-4">
              <div class="relative">
                <input v-model="approverSearch" type="text" placeholder="Cari user berdasarkan nama, email, atau jabatan..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500" @input="handleApproverSearch" @focus="handleApproverFocus" @blur="handleApproverBlur" />
                <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                  <div v-for="u in approverResults" :key="u.id" @mousedown.prevent="addApprover(u)" class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0">
                    <div class="font-medium">{{ u.name }}</div>
                    <div class="text-sm text-gray-600">{{ u.email }}</div>
                    <div v-if="u.jabatan" class="text-xs text-blue-600 font-medium">{{ u.jabatan }}</div>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="form.approvers.length > 0" class="space-y-2">
              <h4 class="font-medium text-gray-700">Urutan Approval:</h4>
              <div v-for="(a, i) in form.approvers" :key="a.id" class="flex items-center justify-between p-3 bg-gray-50 border rounded-md">
                <div class="flex items-center space-x-3">
                  <div class="flex items-center space-x-2">
                    <button v-if="i > 0" type="button" @click="reorderApprover(i, i - 1)" class="p-1 text-gray-500 hover:text-gray-700"><i class="fa fa-arrow-up"></i></button>
                    <button v-if="i < form.approvers.length - 1" type="button" @click="reorderApprover(i, i + 1)" class="p-1 text-gray-500 hover:text-gray-700"><i class="fa fa-arrow-down"></i></button>
                  </div>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Level {{ i + 1 }}</span>
                  <div>
                    <div class="font-medium">{{ a.name }}</div>
                    <div class="text-sm text-gray-600">{{ a.email }}</div>
                    <div v-if="a.jabatan" class="text-xs text-blue-600 font-medium">{{ a.jabatan }}</div>
                  </div>
                </div>
                <button type="button" @click="removeApprover(i)" class="text-red-600 hover:text-red-800"><i class="fa fa-trash"></i></button>
              </div>
            </div>
            <div v-else class="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-md p-3">
              <i class="fa fa-exclamation-triangle mr-2"></i> Wajib menambahkan minimal 1 approver.
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-between items-center mt-8">
            <div class="flex items-center gap-2 text-sm text-gray-500">
              <span v-if="isAutosaving"><i class="fa fa-spinner fa-spin"></i> Menyimpan otomatis...</span>
              <span v-else-if="lastSaved"><i class="fa fa-check text-green-500"></i> Terakhir disimpan: {{ new Date(lastSaved).toLocaleTimeString('id-ID') }}</span>
            </div>
            <div class="flex justify-end gap-2">
              <button type="button" class="btn btn-ghost px-6 py-2 rounded-lg" @click="goBack">Batal</button>
              <button type="button" @click="saveDraft" class="btn bg-gray-500 text-white px-6 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all" :disabled="loading">
                <span v-if="loading && !submitting"><i class="fa fa-spinner fa-spin"></i> Menyimpan...</span>
                <span v-else>Simpan Draft</span>
              </button>
              <button type="button" @click="submitForm" class="btn bg-gradient-to-r from-orange-500 to-orange-700 text-white px-8 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all" :disabled="loading">
                <span v-if="submitting"><i class="fa fa-spinner fa-spin"></i> Submit...</span>
                <span v-else>Submit</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  outlets: Array,
  items: Array,
  units: Array,
  header: Object,
  details: Array,
  approvalFlows: Array,
  isEdit: Boolean
})

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')
const outletDisabled = computed(() => userOutletId.value != 1)
const isEdit = computed(() => props.isEdit || false)
const assetItems = computed(() => props.items || [])

let uidCounter = 0
function newItem() {
  return {
    _uid: ++uidCounter,
    item_id: '',
    selectedItem: null,
    qty: '',
    unit_id: '',
    note: '',
    photo: '',
    photoPreview: '',
    photoUploading: false,
    availableUnits: []
  }
}

function buildUnitsForItem(itemData) {
  const units = []
  if (itemData.small_unit_id) units.push({ id: itemData.small_unit_id, name: itemData.small_unit_name, type: 'small' })
  if (itemData.medium_unit_id) units.push({ id: itemData.medium_unit_id, name: itemData.medium_unit_name, type: 'medium' })
  if (itemData.large_unit_id) units.push({ id: itemData.large_unit_id, name: itemData.large_unit_name, type: 'large' })
  return units
}

function initItems() {
  if (props.details && props.details.length > 0) {
    return props.details.map(d => {
      const master = assetItems.value.find(i => i.id == d.item_id)
      const units = master ? buildUnitsForItem(master) : (d.small_unit_id ? buildUnitsForItem(d) : [])
      return {
        _uid: ++uidCounter,
        item_id: d.item_id,
        selectedItem: master || { id: d.item_id, name: d.item_name },
        qty: Number(d.qty),
        unit_id: d.unit_id,
        note: d.note || '',
        photo: d.photo || '',
        photoPreview: d.photo ? `/storage/${d.photo}` : '',
        photoUploading: false,
        availableUnits: units
      }
    })
  }
  return [newItem()]
}

const form = ref({
  header_id: props.header?.id || null,
  date: props.header?.date || new Date().toISOString().split('T')[0],
  outlet_id: props.header?.outlet_id || (userOutletId.value == 1 ? '' : userOutletId.value),
  notes: props.header?.notes || '',
  items: initItems(),
  approvers: []
})

if (props.approvalFlows && props.approvalFlows.length > 0) {
  form.value.approvers = props.approvalFlows.map(f => ({
    id: f.approver_id,
    name: f.approver_name,
    email: f.approver_email || '',
    jabatan: ''
  }))
}

const loading = ref(false)
const submitting = ref(false)
const isAutosaving = ref(false)
const lastSaved = ref(null)

function addItem() { form.value.items.push(newItem()) }
function removeItem(idx) { if (form.value.items.length > 1) form.value.items.splice(idx, 1) }

function onItemSelect(selected, idx) {
  const item = form.value.items[idx]
  item.item_id = selected.id
  item.availableUnits = buildUnitsForItem(selected)
  if (item.availableUnits.length > 0) item.unit_id = item.availableUnits[0].id
}

function onItemRemove(idx) {
  const item = form.value.items[idx]
  item.item_id = ''
  item.unit_id = ''
  item.availableUnits = []
}

async function handlePhoto(e, idx) {
  const file = e.target.files[0]
  if (!file) return
  const item = form.value.items[idx]
  item.photoUploading = true
  try {
    const fd = new FormData()
    fd.append('photo', file)
    const res = await axios.post('/lost-breakage/upload-photo', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    if (res.data.success) {
      item.photo = res.data.path
      item.photoPreview = res.data.url
    }
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Upload gagal', text: err.response?.data?.message || err.message })
  } finally {
    item.photoUploading = false
  }
}

// Approver search
const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)
let approverSearchTimeout = null

function handleApproverSearch() {
  clearTimeout(approverSearchTimeout)
  approverSearchTimeout = setTimeout(async () => {
    if (approverSearch.value.length < 2) { approverResults.value = []; return }
    try {
      const res = await axios.get('/lost-breakage/approvers', { params: { q: approverSearch.value } })
      approverResults.value = (res.data.users || []).filter(u => !form.value.approvers.find(a => a.id === u.id))
    } catch { approverResults.value = [] }
  }, 300)
}
function handleApproverFocus() { showApproverDropdown.value = true }
function handleApproverBlur() { setTimeout(() => { showApproverDropdown.value = false }, 200) }
function addApprover(user) {
  if (!form.value.approvers.find(a => a.id === user.id)) {
    form.value.approvers.push({ id: user.id, name: user.name, email: user.email, jabatan: user.jabatan })
  }
  approverSearch.value = ''
  approverResults.value = []
}
function removeApprover(i) { form.value.approvers.splice(i, 1) }
function reorderApprover(from, to) {
  const list = form.value.approvers
  const item = list.splice(from, 1)[0]
  list.splice(to, 0, item)
}

function buildPayload() {
  return {
    header_id: form.value.header_id,
    date: form.value.date,
    outlet_id: form.value.outlet_id,
    notes: form.value.notes,
    items: form.value.items.filter(i => i.item_id).map(i => ({
      item_id: i.item_id,
      qty: i.qty,
      unit_id: i.unit_id,
      note: i.note,
      photo: i.photo
    }))
  }
}

async function saveDraft() {
  if (!form.value.date || !form.value.outlet_id) {
    Swal.fire({ icon: 'warning', title: 'Lengkapi data', text: 'Tanggal dan Outlet wajib diisi.' })
    return
  }
  loading.value = true
  try {
    const res = await axios.post('/lost-breakage', buildPayload())
    if (res.data.success) {
      form.value.header_id = res.data.header_id
      lastSaved.value = new Date()
      Swal.fire({ icon: 'success', title: 'Draft tersimpan', timer: 1500, showConfirmButton: false })
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal menyimpan', text: e.response?.data?.message || e.message })
  } finally { loading.value = false }
}

async function submitForm() {
  if (!form.value.date || !form.value.outlet_id) {
    Swal.fire({ icon: 'warning', title: 'Lengkapi data', text: 'Tanggal dan Outlet wajib diisi.' })
    return
  }
  const validItems = form.value.items.filter(i => i.item_id)
  if (validItems.length === 0) {
    Swal.fire({ icon: 'warning', title: 'Tambahkan item', text: 'Minimal 1 item harus ditambahkan.' })
    return
  }
  if (form.value.approvers.length === 0) {
    Swal.fire({ icon: 'warning', title: 'Approver kosong', text: 'Wajib menambahkan minimal 1 approver.' })
    return
  }

  submitting.value = true
  loading.value = true
  try {
    // Save draft first
    const saveRes = await axios.post('/lost-breakage', buildPayload())
    if (!saveRes.data.success) throw new Error(saveRes.data.message || 'Gagal menyimpan draft')
    const headerId = saveRes.data.header_id
    form.value.header_id = headerId

    // Then submit
    const submitRes = await axios.post(`/lost-breakage/${headerId}/submit`, {
      approvers: form.value.approvers.map(a => a.id)
    })
    if (submitRes.data.success) {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: submitRes.data.message, timer: 2000, showConfirmButton: false })
      setTimeout(() => router.visit('/lost-breakage'), 1500)
    } else {
      throw new Error(submitRes.data.message)
    }
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal submit', text: e.response?.data?.message || e.message })
  } finally { submitting.value = false; loading.value = false }
}

// Autosave
let autosaveTimeout = null
watch(() => [form.value.date, form.value.outlet_id, form.value.notes, form.value.items], () => {
  clearTimeout(autosaveTimeout)
  if (!form.value.date || !form.value.outlet_id) return
  autosaveTimeout = setTimeout(async () => {
    isAutosaving.value = true
    try {
      const res = await axios.post('/lost-breakage', { ...buildPayload(), autosave: true })
      if (res.data.success) { form.value.header_id = res.data.header_id; lastSaved.value = new Date() }
    } catch {}
    finally { isAutosaving.value = false }
  }, 3000)
}, { deep: true })

function goBack() { router.visit('/lost-breakage') }
</script>
