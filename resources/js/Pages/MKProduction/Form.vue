<template>
  <div>
    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
      <i class="fa-solid fa-industry text-blue-500"></i> Buat Produksi Baru
    </h2>
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
      <select v-model="form.warehouse_id" class="input input-bordered w-full" required>
        <option value="" disabled>Pilih Warehouse</option>
        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
      </select>
    </div>
    <form @submit.prevent="submit" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Produksi</label>
          <input type="date" v-model="form.production_date" class="input input-bordered w-full" required :disabled="!form.warehouse_id" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number</label>
          <input type="text" v-model="form.batch_number" class="input input-bordered w-full" placeholder="Batch/No Lot" :disabled="!form.warehouse_id" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Item Hasil Produksi</label>
          <select v-model="form.item_id" @change="onItemChange" class="input input-bordered w-full" required :disabled="!form.warehouse_id">
            <option value="" disabled>Pilih Item</option>
            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Qty Produksi</label>
          <input type="number" min="1" v-model.number="form.qty" class="input input-bordered w-full" required @input="onQtyChange" :disabled="!form.warehouse_id || !form.item_id" />
        </div>
        <div class="md:col-span-2 flex gap-2 items-end">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Qty Jadi</label>
            <input type="number" min="0" v-model.number="form.qty_jadi" class="input input-bordered w-full" :disabled="!form.item_id" />
          </div>
          <div style="min-width:120px">
            <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
            <select v-model="form.unit_jadi" class="input input-bordered w-full" :disabled="!form.item_id">
              <option value="" disabled>Pilih Unit</option>
              <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
          <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Catatan tambahan" :disabled="!form.warehouse_id"></textarea>
        </div>
      </div>
      <div v-if="bom.length" class="mt-6">
        <h3 class="font-semibold mb-2 text-blue-700">Bahan Baku (BOM)</h3>
        <table class="min-w-full bg-white rounded shadow text-sm">
          <thead class="bg-blue-100">
            <tr>
              <th class="px-2 py-1 text-left">Bahan</th>
              <th class="px-2 py-1 text-right">Qty/1</th>
              <th class="px-2 py-1 text-right">Total</th>
              <th class="px-2 py-1 text-right">Unit</th>
              <th class="px-2 py-1 text-right">Stok</th>
              <th class="px-2 py-1 text-right">Sisa</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in bom" :key="b.material_item_id" :class="b.sisa < 0 ? 'bg-red-50' : ''">
              <td class="px-2 py-1">{{ b.material_name }}</td>
              <td class="px-2 py-1 text-right">{{ formatNumber(b.qty_per_1) }}</td>
              <td class="px-2 py-1 text-right">{{ formatNumber(b.qty_total) }}</td>
              <td class="px-2 py-1 text-right">{{ b.unit_name }}</td>
              <td class="px-2 py-1 text-right">{{ formatNumber(b.stok) }}</td>
              <td class="px-2 py-1 text-right font-bold" :class="b.sisa < 0 ? 'text-red-600' : 'text-green-700'">{{ formatNumber(b.sisa) }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="bom.some(b => b.sisa < 0)" class="text-red-600 mt-2">Stok bahan tidak cukup untuk produksi!</div>
      </div>
      <div class="flex justify-end gap-2 mt-6">
        <button type="button" class="btn btn-ghost" @click="$emit('cancel')">Batal</button>
        <button type="submit" class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white" :disabled="loading || bom.some(b => b.sisa < 0) || !form.item_id || !form.qty">
          <span v-if="loading" class="animate-spin mr-2"><i class="fa fa-spinner"></i></span>
          Simpan Produksi
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  items: Array,
  warehouses: Array,
  loading: Boolean,
})
const emit = defineEmits(['submitted', 'cancel'])

const form = ref({
  warehouse_id: '',
  production_date: new Date().toISOString().slice(0, 10),
  batch_number: '',
  item_id: '',
  qty: 1,
  notes: '',
  unit_id: '',
  qty_jadi: '',
  unit_jadi: '',
})
const bom = ref([])
const unitName = ref('')
const loading = ref(false)
const unitOptions = computed(() => {
  const item = props.items.find(i => i.id == form.value.item_id)
  if (!item) return []
  const opts = []
  if (item.small_unit_id && item.small_unit_name) opts.push({ id: item.small_unit_id, name: item.small_unit_name })
  if (item.medium_unit_id && item.medium_unit_name) opts.push({ id: item.medium_unit_id, name: item.medium_unit_name })
  if (item.large_unit_id && item.large_unit_name) opts.push({ id: item.large_unit_id, name: item.large_unit_name })
  return opts
})

function onItemChange() {
  const item = props.items.find(i => i.id == form.value.item_id)
  form.value.unit_id = item?.small_unit_id || ''
  unitName.value = item?.small_unit_id ? (item.small_unit_name || 'Small') : ''
  fetchBom()
}
function onQtyChange() {
  fetchBom()
}
function fetchBom() {
  if (!form.value.item_id || !form.value.qty || !form.value.warehouse_id) {
    bom.value = []
    return
  }
  loading.value = true
  axios.post('/mk-production/bom', { item_id: form.value.item_id, qty: form.value.qty, warehouse_id: form.value.warehouse_id })
    .then(res => {
      bom.value = res.data
    })
    .finally(() => loading.value = false)
}
function submit() {
  if (bom.value.some(b => b.sisa < 0)) return
  loading.value = true
  const payload = {
    ...form.value,
    qty: form.value.qty,
    unit_id: form.value.unit_jadi || form.value.unit_id,
  }
  axios.post('/mk-production', payload)
    .then(() => {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: 'Produksi berhasil disimpan!',
        timer: 1500,
        showConfirmButton: false
      }).then(() => {
        emit('submitted')
      })
    })
    .catch(e => {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: e.response?.data?.message || 'Gagal menyimpan produksi',
      })
    })
    .finally(() => loading.value = false)
}
function formatNumber(val) {
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
watch(() => form.value.item_id, () => {
  form.value.unit_jadi = ''
})
</script>

<style scoped>
.input {
  @apply border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300;
}
.btn {
  @apply px-4 py-2 rounded font-semibold shadow transition;
}
.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700;
}
.btn-ghost {
  @apply bg-gray-100 hover:bg-gray-200;
}
</style> 