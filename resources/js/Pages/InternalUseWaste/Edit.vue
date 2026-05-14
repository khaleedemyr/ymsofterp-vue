<template>
  <AppLayout>
    <div class="min-h-screen bg-gray-50 py-8 px-4 md:px-8">
      <div class="w-full max-w-4xl mx-auto bg-white rounded-2xl shadow-2xl p-6 md:p-8">
        <h1 class="text-2xl font-bold mb-2 flex items-center gap-2 text-green-700">
          <i class="fa-solid fa-pen text-green-500"></i> Edit Internal Use & Waste
        </h1>
        <p class="text-sm text-gray-500 mb-6">Dokumen #{{ props.headerId }}</p>
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
            <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Opsional"></textarea>
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
            <button type="submit" class="btn bg-gradient-to-r from-blue-500 to-blue-700 text-white px-8 py-2 rounded-lg font-bold shadow" :disabled="loading">
              <span v-if="loading"><i class="fa fa-spinner fa-spin"></i> Menyimpan...</span>
              <span v-else>Perbarui</span>
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
import { onMounted, ref, watch } from 'vue'

const props = defineProps({
  headerId: { type: Number, required: true },
  header: { type: Object, required: true },
  lines: { type: Array, required: true },
  warehouses: Array,
  items: Array,
  rukos: Array,
})

let _keySeq = 1
function newLineFromApi(ln) {
  const id = ln.item_id
  const full = (props.items || []).find((i) => Number(i.id) === Number(id))
  const sel = full || (id ? { id, name: ln.item_name || 'Item #' + id } : null)
  return {
    _key: `l-${_keySeq++}`,
    selectedItem: sel,
    item_id: id || '',
    qty: ln.qty,
    unit_id: ln.unit_id,
    notes: ln.line_notes || ln.notes || '',
    unitOptions: [],
  }
}

function newEmptyLine() {
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
  type: props.header.type,
  date: String(props.header.date).slice(0, 10),
  warehouse_id: props.header.warehouse_id,
  ruko_id: props.header.ruko_id || '',
  notes: props.header.notes || '',
  items: [],
})

const loading = ref(false)

onMounted(async () => {
  const lines = props.lines.length ? props.lines.map(newLineFromApi) : [newEmptyLine()]
  form.value.items = lines
  for (let i = 0; i < form.value.items.length; i++) {
    await loadUnitsForRow(i, true)
  }
})

watch(
  () => form.value.type,
  (t) => {
    if (t !== 'internal_use') form.value.ruko_id = ''
  }
)

function addRow() {
  form.value.items.push(newEmptyLine())
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

async function loadUnitsForRow(idx, preserveUnit) {
  const line = form.value.items[idx]
  if (!preserveUnit) {
    line.unit_id = ''
  }
  line.unitOptions = []
  if (!line.item_id) return
  try {
    const res = await axios.get(route('internal-use-waste.item-units', line.item_id))
    line.unitOptions = res.data.units || []
  } catch {
    line.unitOptions = []
  }
}

function buildPayload() {
  return {
    type: form.value.type,
    date: form.value.date,
    warehouse_id: form.value.warehouse_id,
    ruko_id: form.value.type === 'internal_use' ? form.value.ruko_id : null,
    notes: form.value.notes || null,
    items: form.value.items.map((l) => ({
      item_id: Number(l.item_id),
      qty: Number(l.qty),
      unit_id: Number(l.unit_id),
      notes: l.notes || null,
    })),
  }
}

async function submit() {
  for (const l of form.value.items) {
    if (!l.item_id || !l.unit_id || l.qty === '' || l.qty == null || Number(l.qty) <= 0) {
      Swal.fire({ icon: 'warning', title: 'Lengkapi semua baris', text: 'Setiap baris wajib punya item, qty, dan unit.' })
      return
    }
  }
  loading.value = true
  try {
    await router.put(route('internal-use-waste.update', props.headerId), buildPayload(), {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Data berhasil diperbarui!',
          timer: 1500,
          showConfirmButton: false,
        })
      },
      onError: () => {
        Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal memperbarui data. Cek input / stok.' })
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
