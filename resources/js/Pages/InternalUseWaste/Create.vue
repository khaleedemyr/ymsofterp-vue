<template>
  <AppLayout>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 md:px-8">
      <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl p-8">
        <h1 class="text-2xl font-bold mb-8 flex items-center gap-2 text-green-700">
          <i class="fa-solid fa-recycle text-green-500"></i> Input Internal Use & Waste
        </h1>
        <form @submit.prevent="submit" class="space-y-5">
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
            <input type="date" v-model="form.date" class="input input-bordered w-full" required />
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
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Item</label>
            <select v-model="form.item_id" class="input input-bordered w-full" required>
              <option value="">Pilih Item</option>
              <option v-for="i in props.items" :key="i.id" :value="i.id">{{ i.name }}</option>
            </select>
          </div>
          <div class="flex gap-2">
            <div class="flex-1">
              <label class="block text-xs font-bold text-gray-600 mb-1">Qty</label>
              <input type="number" min="0" v-model.number="form.qty" class="input input-bordered w-full" required />
            </div>
            <div style="min-width:120px">
              <label class="block text-xs font-bold text-gray-600 mb-1">Unit</label>
              <select v-model="form.unit_id" class="input input-bordered w-full" required>
                <option value="">Pilih Unit</option>
                <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Catatan</label>
            <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Catatan tambahan"></textarea>
          </div>
          <div class="flex justify-end gap-2 mt-8">
            <button type="button" class="btn btn-ghost px-6 py-2 rounded-lg" @click="goBack">Batal</button>
            <button type="submit" class="btn bg-gradient-to-r from-green-500 to-green-700 text-white px-8 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all" :disabled="loading">
              <span v-if="loading">
                <i class="fa fa-spinner fa-spin"></i> Menyimpan...
              </span>
              <span v-else>
                Simpan
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  warehouses: Array,
  items: Array,
  rukos: Array
})

const form = ref({
  type: '',
  date: new Date().toISOString().slice(0, 10),
  warehouse_id: '',
  ruko_id: '',
  item_id: '',
  qty: '',
  unit_id: '',
  notes: ''
})

const unitOptions = ref([])
const loading = ref(false)

watch(() => form.value.item_id, async (newVal) => {
  if (newVal) {
    const res = await axios.get(`/internal-use-waste/item/${newVal}/units`)
    unitOptions.value = res.data.units
    form.value.unit_id = ''
  } else {
    unitOptions.value = []
    form.value.unit_id = ''
  }
})

watch(() => form.value.type, (newVal) => {
  if (newVal !== 'internal_use') {
    form.value.ruko_id = ''
  }
})

async function submit() {
  loading.value = true
  try {
    await router.post(route('internal-use-waste.store'), form.value, {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Data berhasil disimpan!',
          timer: 1500,
          showConfirmButton: false
        })
        loading.value = false
      },
      onError: (errors) => {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: 'Gagal menyimpan data. Silakan cek input Anda.',
        })
        loading.value = false
      },
      onFinish: () => {
        loading.value = false
      }
    })
  } catch (e) {
    loading.value = false
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: 'Terjadi kesalahan sistem.',
    })
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
</style> 