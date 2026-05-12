<template>
  <Head title="Buat GR Serial" />
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-purple-800 flex items-center gap-2">
          <i class="fa-solid fa-barcode text-purple-500"></i> Buat GR Serial
        </h1>
        <a href="/outlet-serial-receive" class="text-sm text-blue-600 hover:underline">
          <i class="fa fa-arrow-left"></i> Kembali ke daftar
        </a>
      </div>

      <!-- Scan Input -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <label class="font-semibold text-lg text-purple-700 block mb-2">Scan Nomor Seri</label>
        <input
          ref="serialInput"
          v-model="serialInputVal"
          @keyup.enter="onScan"
          :disabled="scanning || saving"
          class="border-2 border-purple-400 rounded-lg px-4 py-3 w-full text-xl text-center focus:ring-2 focus:ring-purple-500 shadow-lg"
          placeholder="Scan atau ketik nomor seri di sini..."
          autofocus
        />
        <div v-if="feedback" :class="feedbackClass" class="mt-3 font-bold text-lg min-h-[28px] text-center">
          {{ feedback }}
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <div class="text-3xl font-bold text-purple-700">{{ scannedSerials.length }}</div>
          <div class="text-sm text-gray-500">Total Serial</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <div class="text-3xl font-bold text-blue-700">{{ uniqueDOs }}</div>
          <div class="text-sm text-gray-500">DO Terlibat</div>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <div class="text-3xl font-bold text-green-700">{{ uniqueItems }}</div>
          <div class="text-sm text-gray-500">Item Unik</div>
        </div>
      </div>

      <!-- Scanned Serials Table -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <h2 class="text-lg font-bold text-purple-700 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-list"></i> Serial yang Di-scan
        </h2>
        <div v-if="!scannedSerials.length" class="text-center text-gray-400 py-8">
          Belum ada serial yang di-scan. Scan nomor seri di atas untuk memulai.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm divide-y divide-gray-200">
            <thead class="bg-purple-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">No</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Serial</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Item</th>
                <th class="px-3 py-2 text-right text-xs font-bold text-purple-700">Qty</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">DO</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Outlet</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Warehouse</th>
                <th class="px-3 py-2 text-right text-xs font-bold text-purple-700">Harga</th>
                <th class="px-3 py-2 text-center text-xs font-bold text-purple-700">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in scannedSerials" :key="row.serial_number" class="hover:bg-purple-50 transition">
                <td class="px-3 py-2">{{ idx + 1 }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ row.serial_number }}</td>
                <td class="px-3 py-2">{{ row.item_name }}</td>
                <td class="px-3 py-2 text-right">{{ fmtQty(row.qty) }} {{ row.unit_name }}</td>
                <td class="px-3 py-2 text-xs">{{ row.do_number }}</td>
                <td class="px-3 py-2 text-xs">{{ row.outlet_name }}</td>
                <td class="px-3 py-2 text-xs">{{ row.warehouse_name }}</td>
                <td class="px-3 py-2 text-right">{{ formatRupiah(row.cost_small) }}</td>
                <td class="px-3 py-2 text-center">
                  <button @click="removeSerial(idx)" :disabled="saving"
                    class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 disabled:opacity-50">
                    <i class="fa fa-times"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Notes & Save -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <div class="mb-4">
          <label class="text-sm font-semibold text-gray-600 block mb-1">Catatan (opsional)</label>
          <textarea v-model="notes" rows="2" class="border rounded-lg px-3 py-2 w-full text-sm" placeholder="Catatan tambahan..."></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <a href="/outlet-serial-receive" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300">
            Batal
          </a>
          <button @click="onSave" :disabled="!scannedSerials.length || saving"
            class="px-5 py-2 bg-green-600 text-white rounded-lg font-semibold shadow hover:bg-green-700 disabled:opacity-50 flex items-center gap-2">
            <i v-if="saving" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa fa-check"></i> Simpan
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const serialInputVal = ref('')
const serialInput = ref(null)
const feedback = ref('')
const feedbackClass = ref('')
const scanning = ref(false)
const saving = ref(false)
const notes = ref('')
const scannedSerials = ref([])

const uniqueDOs = computed(() => new Set(scannedSerials.value.map(r => r.do_number)).size)
const uniqueItems = computed(() => new Set(scannedSerials.value.map(r => r.item_id)).size)

function formatRupiah(val) {
  if (!val) return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function fmtQty(val) {
  if (!val && val !== 0) return '-'
  let s = Number(val).toFixed(4)
  s = s.replace(/\.?0+$/, '')
  return s
}

async function onScan() {
  const input = serialInputVal.value.trim()
  if (!input) return

  if (scannedSerials.value.some(s => s.serial_number === input)) {
    feedback.value = 'Nomor seri ini sudah di-scan.'
    feedbackClass.value = 'text-orange-600'
    serialInputVal.value = ''
    nextTick(() => serialInput.value?.focus())
    return
  }

  scanning.value = true
  feedback.value = ''
  feedbackClass.value = ''

  try {
    const { data } = await axios.post('/api/outlet-serial-receive/validate-serial', {
      serial_number: input,
    })

    if (data.valid) {
      scannedSerials.value.push(data.serial)
      feedback.value = `✓ ${data.serial.item_name} - ${input}`
      feedbackClass.value = 'text-green-600'
    } else {
      feedback.value = data.message
      feedbackClass.value = 'text-red-600'
    }
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal memvalidasi serial.'
    feedback.value = msg
    feedbackClass.value = 'text-red-600'
  }

  serialInputVal.value = ''
  scanning.value = false
  nextTick(() => serialInput.value?.focus())
}

function removeSerial(idx) {
  scannedSerials.value.splice(idx, 1)
}

async function onSave() {
  if (!scannedSerials.value.length) return

  const confirm = await Swal.fire({
    title: 'Simpan GR Serial?',
    html: `Total <b>${scannedSerials.value.length}</b> serial akan diproses dan masuk inventory.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#16a34a',
  })

  if (!confirm.isConfirmed) return

  saving.value = true
  try {
    const payload = {
      serials: scannedSerials.value.map(s => ({
        serial_id: s.id,
        serial_number: s.serial_number,
      })),
      notes: notes.value || null,
    }

    const form = router.post('/outlet-serial-receive', payload, {
      onSuccess: () => {
        saving.value = false
      },
      onError: (errors) => {
        saving.value = false
        const msg = errors.serials || Object.values(errors).flat().join(', ') || 'Gagal menyimpan.'
        Swal.fire('Gagal', msg, 'error')
      },
    })
  } catch (e) {
    saving.value = false
    Swal.fire('Error', 'Terjadi kesalahan.', 'error')
  }
}

onMounted(() => {
  nextTick(() => serialInput.value?.focus())
})
</script>
