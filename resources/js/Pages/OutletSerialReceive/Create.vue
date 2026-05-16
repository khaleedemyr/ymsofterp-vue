<template>
  <Head title="Buat GR Serial" />
  <AppLayout>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Buat GR Serial</h1>
          <p class="text-sm text-gray-500 mt-1">Outlet: <span class="font-medium text-gray-700">{{ userOutlet.name }}</span></p>
        </div>
        <a href="/outlet-serial-receive"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
          <i class="fa fa-arrow-left text-xs"></i> Kembali
        </a>
      </div>

      <!-- Scan Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="max-w-xl mx-auto">
          <!-- Outlet Badge -->
          <div class="flex items-center justify-center mb-4">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-full">
              <i class="fa-solid fa-store text-indigo-500 text-sm"></i>
              <span class="text-sm font-semibold text-indigo-700">{{ userOutlet.name }}</span>
            </div>
          </div>
          <p class="text-xs text-center text-gray-400 mb-4">Hanya serial untuk outlet ini yang dapat di-scan</p>

          <label class="text-sm font-semibold text-gray-700 block mb-2">Scan Nomor Seri</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <i class="fa-solid fa-barcode text-gray-400"></i>
            </div>
            <input
              ref="serialInput"
              v-model="serialInputVal"
              @keyup.enter="onScan"
              :disabled="scanning || saving"
              class="w-full pl-11 pr-4 py-3.5 text-lg border-2 border-gray-200 rounded-xl focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 transition-all duration-200 placeholder:text-gray-400"
              placeholder="Scan atau ketik nomor seri..."
              autofocus
            />
            <div v-if="scanning" class="absolute inset-y-0 right-0 pr-4 flex items-center">
              <i class="fa fa-spinner fa-spin text-indigo-500"></i>
            </div>
          </div>
          <div v-if="feedback" :class="feedbackClass" class="mt-3 text-sm font-medium text-center min-h-[24px]">
            {{ feedback }}
          </div>
        </div>
      </div>

      <!-- Stats Row -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
          <div class="text-3xl font-bold text-indigo-600">{{ scannedSerials.length }}</div>
          <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wide">Total Serial</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
          <div class="text-3xl font-bold text-blue-600">{{ uniqueDOs }}</div>
          <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wide">DO Terlibat</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
          <div class="text-3xl font-bold text-emerald-600">{{ uniqueItems }}</div>
          <div class="text-xs text-gray-500 mt-1 font-medium uppercase tracking-wide">Item Unik</div>
        </div>
      </div>

      <!-- Scanned Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100">
          <h2 class="text-base font-semibold text-gray-800">Serial yang Di-scan</h2>
        </div>
        <div v-if="!scannedSerials.length" class="text-center py-16">
          <div class="text-gray-300 text-5xl mb-4"><i class="fa-solid fa-barcode"></i></div>
          <p class="text-gray-500 text-sm">Scan nomor seri di atas untuk memulai.</p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Serial</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">DO</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Warehouse</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-16"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="(row, idx) in scannedSerials" :key="row.serial_number" class="hover:bg-red-50/20 transition-colors duration-150 group">
                <td class="px-4 py-3 text-gray-500">{{ idx + 1 }}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ row.serial_number }}</td>
                <td class="px-4 py-3 text-gray-800 font-medium">{{ row.item_name }}</td>
                <td class="px-4 py-3 text-right text-gray-700">{{ fmtQty(row.qty) }} <span class="text-gray-400 text-xs">{{ row.unit_name }}</span></td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ row.do_number }}</td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ row.outlet_name }}</td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ row.warehouse_name }}</td>
                <td class="px-4 py-3 text-right text-gray-700">{{ formatRupiah(row.cost_small) }}</td>
                <td class="px-4 py-3 text-center">
                  <button @click="removeSerial(idx)" :disabled="saving"
                    class="opacity-0 group-hover:opacity-100 inline-flex items-center justify-center w-7 h-7 text-xs text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-all duration-150 disabled:opacity-30">
                    <i class="fa fa-times"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Save Section -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="mb-4">
          <label class="text-sm font-medium text-gray-700 block mb-1.5">Catatan (opsional)</label>
          <textarea v-model="notes" rows="2"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition resize-none"
            placeholder="Catatan tambahan..."></textarea>
        </div>
        <div class="flex items-center justify-between pt-2">
          <a href="/outlet-serial-receive"
            class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-xl hover:bg-gray-200 transition">
            Batal
          </a>
          <button @click="onSave" :disabled="!scannedSerials.length || saving"
            class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-emerald-600 rounded-xl shadow-sm hover:bg-emerald-700 hover:shadow-md disabled:opacity-40 disabled:cursor-not-allowed transition-all duration-200">
            <i v-if="saving" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa fa-check"></i>
            Simpan ({{ scannedSerials.length }} serial)
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

const props = defineProps({
  userOutlet: Object,
})

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

function playBeep(type) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    if (type === 'success') {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.type = 'sine'
      osc.frequency.value = 1200
      gain.gain.value = 0.3
      osc.start()
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15)
      osc.stop(ctx.currentTime + 0.15)
    } else {
      const osc1 = ctx.createOscillator()
      const gain1 = ctx.createGain()
      osc1.connect(gain1)
      gain1.connect(ctx.destination)
      osc1.type = 'square'
      osc1.frequency.value = 400
      gain1.gain.value = 0.25
      osc1.start()
      gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.12)
      osc1.stop(ctx.currentTime + 0.12)

      setTimeout(() => {
        const osc2 = ctx.createOscillator()
        const gain2 = ctx.createGain()
        osc2.connect(gain2)
        gain2.connect(ctx.destination)
        osc2.type = 'square'
        osc2.frequency.value = 300
        gain2.gain.value = 0.25
        osc2.start()
        gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18)
        osc2.stop(ctx.currentTime + 0.18)
      }, 150)
    }
  } catch (e) {}
}

async function onScan() {
  const input = serialInputVal.value.trim()
  if (!input) return

  if (scannedSerials.value.some(s => s.serial_number === input)) {
    feedback.value = 'Nomor seri ini sudah di-scan.'
    feedbackClass.value = 'text-amber-600'
    playBeep('error')
    axios.post('/api/outlet-serial-receive/log-reject', {
      serial_number: input,
      reject_reason: 'duplicate_scan',
    }).catch(() => {})
    Swal.fire({ icon: 'warning', title: 'Duplikat', text: 'Nomor seri ini sudah ada di daftar scan.', timer: 2500, showConfirmButton: false })
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
      feedback.value = `✓ ${data.serial.item_name} — ${input}`
      feedbackClass.value = 'text-emerald-600'
      playBeep('success')
    } else {
      feedback.value = data.message
      feedbackClass.value = 'text-red-600'
      playBeep('error')
      Swal.fire({
        icon: 'error',
        title: 'Ditolak',
        text: data.message,
        timer: 3000,
        showConfirmButton: false,
      })
    }
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal memvalidasi serial.'
    feedback.value = msg
    feedbackClass.value = 'text-red-600'
    playBeep('error')
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: msg,
      timer: 3000,
      showConfirmButton: false,
    })
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
    confirmButtonColor: '#059669',
    cancelButtonColor: '#6b7280',
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

    router.post('/outlet-serial-receive', payload, {
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
