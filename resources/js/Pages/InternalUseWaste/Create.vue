<template>
  <AppLayout>
    <div class="min-h-screen bg-gray-50 py-8 px-4 md:px-8">
      <div class="w-full max-w-4xl mx-auto bg-white rounded-2xl shadow-2xl p-6 md:p-8">
        <h1 class="text-2xl font-bold mb-6 flex items-center gap-2 text-green-700">
          <i class="fa-solid fa-recycle text-green-500"></i> Input Internal Use & Waste
        </h1>
        <p class="text-sm text-gray-600 mb-6">Satu dokumen bisa berisi banyak item. Tanggal, tipe, warehouse, dan ruko (jika internal use) sama untuk semua baris.</p>
        <form @submit.prevent="submit" class="space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Tipe</label>
              <select v-model="form.type" class="input input-bordered w-full" required>
                <option value="">Pilih Tipe</option>
                <option value="internal_use">Internal Use</option>
                <option value="spoil">Spoil</option>
                <option value="waste">Waste</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Tanggal</label>
              <input v-model="form.date" type="date" class="input input-bordered w-full" required />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Warehouse</label>
              <select v-model="form.warehouse_id" class="input input-bordered w-full" required>
                <option value="">Pilih Warehouse</option>
                <option v-for="w in props.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
            </div>
            <div v-if="form.type === 'internal_use'">
              <label class="block text-xs font-bold text-gray-600 mb-1">Ruko</label>
              <select v-model="form.ruko_id" class="input input-bordered w-full" required>
                <option value="">Pilih Ruko</option>
                <option v-for="r in props.rukos" :key="r.id_ruko" :value="r.id_ruko">{{ r.nama_ruko }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Catatan dokumen</label>
            <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Opsional, berlaku untuk seluruh dokumen"></textarea>
          </div>


          <div class="mb-4 border rounded-lg p-4" :class="serialMode ? 'border-indigo-300 bg-indigo-50/30' : 'border-gray-200'">
            <label class="flex items-center justify-between cursor-pointer">
              <span class="text-sm font-medium text-gray-700"><i class="fa-solid fa-qrcode mr-1 text-indigo-500"></i> Mode Nomor Seri</span>
              <input type="checkbox" v-model="serialMode" class="sr-only peer" />
              <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-indigo-500 relative after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
            </label>
            <div v-if="serialMode" class="mt-3 space-y-2">
              <label class="block text-xs font-bold text-indigo-700 mb-1">Scan nomor seri</label>
              <input
                ref="serialInputRef"
                v-model="serialInput"
                @keypress="handleSerialKeyPress"
                type="text"
                placeholder="Arahkan scanner ke sini lalu scan, atau ketik lalu Enter..."
                class="input input-bordered w-full border-indigo-300 focus:ring-indigo-400"
                :disabled="serialScanning || !form.warehouse_id"
              />
              <p v-if="!form.warehouse_id" class="text-xs text-amber-600">Pilih warehouse terlebih dahulu agar serial bisa divalidasi.</p>
              <p v-if="serialFeedback" class="text-sm" :class="serialFeedbackSuccess ? 'text-green-600' : 'text-red-600'">{{ serialFeedback }}</p>
              <div v-if="scannedSerials.length" class="space-y-2">
                <p class="text-xs font-semibold text-indigo-700">{{ scannedSerials.length }} serial discan</p>
                <div v-for="(s, sIdx) in scannedSerials" :key="s.serial_number" class="flex justify-between items-center border border-indigo-200 rounded-lg p-2 bg-white text-sm">
                  <div>
                    <span class="font-mono font-semibold">{{ s.serial_number }}</span>
                    <span class="block text-gray-600">{{ s.item_name }} — {{ s.qty }} {{ s.unit_name }}</span>
                  </div>
                  <button type="button" class="text-red-600" @click="removeSerial(sIdx)"><i class="fa fa-times"></i></button>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t pt-4">
            <div class="flex justify-between items-center mb-3">
              <span class="font-bold text-gray-700">Daftar item</span>
              <button type="button" class="btn btn-sm bg-green-600 text-white rounded-lg px-3 py-1" @click="addRow">
                <i class="fa fa-plus mr-1"></i> Baris item
              </button>
            </div>
            <div class="space-y-4">
              <div
                v-for="(line, idx) in form.items"
                :key="line._key"
                class="border rounded-xl p-4 bg-gray-50/80 grid grid-cols-1 md:grid-cols-12 gap-3 items-end"
              >
                <div class="md:col-span-5">
                  <label class="block text-xs font-bold text-gray-600 mb-1">Item</label>
                  <Multiselect
                    :model-value="line.selectedItem"
                    :options="props.items || []"
                    :searchable="true"
                    :close-on-select="true"
                    :show-labels="false"
                    :allow-empty="true"
                    placeholder="Ketik untuk mencari item..."
                    label="name"
                    track-by="id"
                    class="iuw-multiselect"
                    @select="(sel) => onItemSelect(sel, idx)"
                    @remove="() => onItemRemove(idx)"
                  >
                    <template #noResult>
                      <span class="text-sm text-gray-500 px-2 py-1">Tidak ada hasil</span>
                    </template>
                  </Multiselect>
                </div>
                <div class="md:col-span-2">
                  <label class="block text-xs font-bold text-gray-600 mb-1">Qty</label>
                  <input v-model.number="line.qty" type="number" min="0" step="any" class="input input-bordered w-full" required />
                </div>
                <div class="md:col-span-3">
                  <label class="block text-xs font-bold text-gray-600 mb-1">Unit</label>
                  <select v-model="line.unit_id" class="input input-bordered w-full" required>
                    <option value="">Pilih Unit</option>
                    <option v-for="u in line.unitOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
                  </select>
                </div>
                <div class="md:col-span-2 flex gap-1">
                  <button
                    v-if="form.items.length > 1"
                    type="button"
                    class="btn btn-sm bg-red-100 text-red-700 rounded-lg px-2"
                    title="Hapus baris"
                    @click="removeRow(idx)"
                  >
                    <i class="fa fa-times"></i>
                  </button>
                </div>
                <div class="md:col-span-12">
                  <label class="block text-xs font-bold text-gray-600 mb-1">Catatan baris</label>
                  <input v-model="line.notes" type="text" class="input input-bordered w-full" placeholder="Opsional" />
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 mt-8">
            <button type="button" class="btn btn-ghost px-6 py-2 rounded-lg" @click="goBack">Batal</button>
            <button
              type="submit"
              class="btn bg-gradient-to-r from-green-500 to-green-700 text-white px-8 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all"
              :disabled="loading"
            >
              <span v-if="loading"> <i class="fa fa-spinner fa-spin"></i> Menyimpan... </span>
              <span v-else> Simpan </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import { ref, watch, nextTick } from 'vue'

const props = defineProps({
  warehouses: Array,
  items: Array,
  rukos: Array,
})

let _keySeq = 1
function newLine() {
  return {
    _key: `l-${_keySeq++}`,
    selectedItem: null,
    item_id: '',
    qty: '',
    unit_id: '',
    notes: '',
    unitOptions: [],
  }
}

const form = ref({
  type: '',
  date: new Date().toISOString().slice(0, 10),
  warehouse_id: '',
  ruko_id: '',
  notes: '',
  items: [newLine()],
})

const loading = ref(false)

const serialMode = ref(false)
const serialInput = ref('')
const serialInputRef = ref(null)
const serialScanning = ref(false)
const serialFeedback = ref('')
const serialFeedbackSuccess = ref(false)
const scannedSerials = ref([])

watch(serialMode, (on) => {
  if (on) nextTick(() => serialInputRef.value?.focus())
})

watch(
  () => form.value.type,
  (t) => {
    if (t !== 'internal_use') form.value.ruko_id = ''
  }
)

function addRow() {
  form.value.items.push(newLine())
}

function removeRow(idx) {
  if (form.value.items.length <= 1) return
  form.value.items.splice(idx, 1)
}

async function onItemSelect(sel, idx) {
  const line = form.value.items[idx]
  line.selectedItem = sel
  line.item_id = sel && sel.id != null ? sel.id : ''
  line.unit_id = ''
  line.unitOptions = []
  if (!line.item_id) return
  try {
    const res = await axios.get(route('internal-use-waste.item-units', line.item_id))
    line.unitOptions = res.data.units || []
  } catch {
    line.unitOptions = []
  }
}

function onItemRemove(idx) {
  const line = form.value.items[idx]
  line.selectedItem = null
  line.item_id = ''
  line.unit_id = ''
  line.unitOptions = []
}


async function onSerialScan() {
  const input = serialInput.value.trim()
  if (!input) return
  if (!form.value.warehouse_id) {
    serialFeedback.value = 'Pilih warehouse dulu'
    serialFeedbackSuccess.value = false
    return
  }
  if (scannedSerials.value.some((s) => s.serial_number === input)) {
    serialFeedback.value = `Serial "${input}" sudah discan`
    serialFeedbackSuccess.value = false
    serialInput.value = ''
    return
  }
  serialScanning.value = true
  try {
    const res = await axios.post(route('internal-use-waste.validate-serial'), {
      serial_number: input,
      warehouse_id: form.value.warehouse_id,
    })
    if (res.data.valid) {
      const serial = res.data.serial
      scannedSerials.value.push({
        serial_id: serial.id,
        serial_number: serial.serial_number,
        item_id: serial.item_id,
        item_name: serial.item_name,
        unit_id: serial.unit_id,
        unit_name: serial.unit_name,
        qty: serial.qty,
        qty_small: serial.qty_small,
      })
      serialFeedback.value = `Serial "${input}" valid`
      serialFeedbackSuccess.value = true
    } else {
      serialFeedback.value = res.data.message || 'Serial tidak valid'
      serialFeedbackSuccess.value = false
    }
  } catch {
    serialFeedback.value = 'Gagal validasi serial'
    serialFeedbackSuccess.value = false
  } finally {
    serialScanning.value = false
    serialInput.value = ''
    nextTick(() => serialInputRef.value?.focus())
  }
}

function removeSerial(idx) {
  scannedSerials.value.splice(idx, 1)
}

function handleSerialKeyPress(e) {
  if (e.key === 'Enter') {
    e.preventDefault()
    onSerialScan()
  }
}

function buildPayload() {
  const qtyItems = form.value.items
    .filter((l) => l.item_id && l.unit_id && l.qty !== '' && l.qty != null && Number(l.qty) > 0)
    .map((l) => ({
      item_id: Number(l.item_id),
      qty: Number(l.qty),
      unit_id: Number(l.unit_id),
      notes: l.notes || null,
    }))
  const payload = {
    type: form.value.type,
    date: form.value.date,
    warehouse_id: form.value.warehouse_id,
    ruko_id: form.value.type === 'internal_use' ? form.value.ruko_id : null,
    notes: form.value.notes || null,
  }
  if (qtyItems.length) payload.items = qtyItems
  if (scannedSerials.value.length) {
    payload.serial_items = scannedSerials.value.map((s) => ({
      serial_id: s.serial_id,
      serial_number: s.serial_number,
      item_id: s.item_id,
      unit_id: s.unit_id,
      unit_name: s.unit_name,
      qty: s.qty,
      qty_small: s.qty_small,
    }))
  }
  return payload
}

async function submit() {
  const qtyItems = form.value.items.filter(
    (l) => l.item_id && l.unit_id && l.qty !== '' && l.qty != null && Number(l.qty) > 0
  )
  const hasSerials = scannedSerials.value.length > 0

  if (!qtyItems.length && !hasSerials) {
    Swal.fire({
      icon: 'warning',
      title: 'Data kosong',
      text: 'Minimal 1 baris item (qty) atau 1 nomor seri yang discan.',
    })
    return
  }

  for (const l of form.value.items) {
    if (!l.item_id) continue
    if (!l.unit_id || l.qty === '' || l.qty == null || Number(l.qty) <= 0) {
      Swal.fire({ icon: 'warning', title: 'Lengkapi baris item', text: 'Baris yang punya item wajib diisi qty dan unit.' })
      return
    }
  }
  loading.value = true
  try {
    await router.post(route('internal-use-waste.store'), buildPayload(), {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Data berhasil disimpan!',
          timer: 1500,
          showConfirmButton: false,
        })
      },
      onError: () => {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: 'Gagal menyimpan data. Silakan cek input Anda.',
        })
      },
      onFinish: () => {
        loading.value = false
      },
    })
  } catch {
    loading.value = false
    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan sistem.' })
  }
}

function goBack() {
  router.visit(route('internal-use-waste.index'))
}
</script>

<style scoped>
.input {
  @apply border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300 transition;
}
.btn {
  @apply font-semibold shadow transition;
}
.btn-ghost {
  @apply bg-gray-100 hover:bg-gray-200;
}

.iuw-multiselect :deep(.multiselect__tags) {
  min-height: 42px;
  border-radius: 0.375rem;
  border-color: #e5e7eb;
}
.iuw-multiselect :deep(.multiselect__input),
.iuw-multiselect :deep(.multiselect__single) {
  font-size: 0.875rem;
}
</style>
