<template>
  <Head title="Outlet Serial Receive" />
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-purple-800 flex items-center gap-2">
          <i class="fa-solid fa-barcode text-purple-500"></i> Outlet Serial Receive
        </h1>
        <span class="text-sm text-gray-500">{{ todayLabel }}</span>
      </div>

      <!-- Scan Input -->
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <label class="font-semibold text-lg text-purple-700 block mb-2">Scan Nomor Seri</label>
        <input
          ref="serialInput"
          v-model="serialInputVal"
          @keyup.enter="onScan"
          :disabled="scanning"
          class="border-2 border-purple-400 rounded-lg px-4 py-3 w-full text-xl text-center focus:ring-2 focus:ring-purple-500 shadow-lg"
          placeholder="Scan atau ketik nomor seri di sini..."
          autofocus
        />
        <div v-if="feedback" :class="feedbackClass" class="mt-3 font-bold text-lg min-h-[28px] text-center">
          {{ feedback }}
        </div>

        <!-- Last scan info -->
        <div v-if="lastScan" class="mt-4 bg-green-50 border border-green-200 rounded-xl p-4">
          <div class="grid grid-cols-2 gap-2 text-sm">
            <div><span class="text-gray-500">Serial:</span> <span class="font-mono font-bold">{{ lastScan.serial_number }}</span></div>
            <div><span class="text-gray-500">DO:</span> <span class="font-semibold">{{ lastScan.do_number }}</span></div>
            <div><span class="text-gray-500">Item:</span> <span class="font-semibold">{{ lastScan.item_name }}</span></div>
            <div><span class="text-gray-500">Qty:</span> <span class="font-semibold">{{ lastScan.qty }} {{ lastScan.unit_name }}</span></div>
            <div><span class="text-gray-500">Outlet:</span> <span class="font-semibold">{{ lastScan.outlet_name }}</span></div>
            <div><span class="text-gray-500">Warehouse:</span> <span class="font-semibold">{{ lastScan.warehouse_name }}</span></div>
            <div><span class="text-gray-500">Harga:</span> <span class="font-semibold">{{ formatRupiah(lastScan.cost_small) }}</span></div>
            <div><span class="text-gray-500">Sumber:</span> <span class="font-semibold">{{ lastScan.cost_source }}</span></div>
            <div v-if="lastScan.repack_label" class="col-span-2">
              <span class="text-gray-500">Konversi:</span>
              <span class="inline-block bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-xs font-bold ml-1">{{ lastScan.repack_label }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4 text-center">
          <div class="text-3xl font-bold text-purple-700">{{ history.length }}</div>
          <div class="text-sm text-gray-500">Serial Diterima</div>
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

      <!-- History Table -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-lg font-bold text-purple-700 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-list"></i> Riwayat Scan Hari Ini
          <button @click="loadHistory" class="ml-auto text-sm text-blue-600 hover:underline font-normal">
            <i class="fa fa-refresh"></i> Refresh
          </button>
        </h2>
        <div v-if="!history.length" class="text-center text-gray-400 py-8">Belum ada serial yang di-scan hari ini.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm divide-y divide-gray-200">
            <thead class="bg-purple-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">No</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Serial</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Item</th>
                <th class="px-3 py-2 text-right text-xs font-bold text-purple-700">Qty</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">DO</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">User</th>
                <th class="px-3 py-2 text-left text-xs font-bold text-purple-700">Waktu</th>
                <th class="px-3 py-2 text-center text-xs font-bold text-purple-700">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in history" :key="row.id" class="hover:bg-purple-50 transition">
                <td class="px-3 py-2">{{ idx + 1 }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ row.serial_number }}</td>
                <td class="px-3 py-2">{{ row.item_name }}</td>
                <td class="px-3 py-2 text-right">{{ row.qty }} {{ row.unit_name }}</td>
                <td class="px-3 py-2 text-xs">{{ row.delivery_order_number }}</td>
                <td class="px-3 py-2 text-xs">{{ row.received_by_name }}</td>
                <td class="px-3 py-2 text-xs">{{ formatTime(row.received_at) }}</td>
                <td class="px-3 py-2 text-center">
                  <button
                    @click="onRollback(row)"
                    :disabled="rollingBack === row.id"
                    class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 disabled:opacity-50"
                  >
                    <i v-if="rollingBack === row.id" class="fa fa-spinner fa-spin"></i>
                    <i v-else class="fa fa-undo"></i> Rollback
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const serialInputVal = ref('')
const serialInput = ref(null)
const feedback = ref('')
const feedbackClass = ref('')
const scanning = ref(false)
const lastScan = ref(null)
const history = ref([])
const rollingBack = ref(null)

const todayLabel = computed(() => {
  return new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
})

const uniqueDOs = computed(() => new Set(history.value.map(r => r.delivery_order_number)).size)
const uniqueItems = computed(() => new Set(history.value.map(r => r.item_id)).size)

function formatRupiah(val) {
  if (!val) return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function formatTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

async function loadHistory() {
  try {
    const { data } = await axios.get('/api/outlet-serial-receive/history')
    history.value = data || []
  } catch (e) {
    history.value = []
  }
}

async function onScan() {
  const input = serialInputVal.value.trim()
  if (!input) return

  scanning.value = true
  feedback.value = ''
  feedbackClass.value = ''

  try {
    const { data } = await axios.post('/api/outlet-serial-receive/scan', {
      serial_number: input,
    })

    if (data.success) {
      feedback.value = `${data.data.item_name} - ${input} (+${data.data.qty})`
      feedbackClass.value = 'text-green-600'
      lastScan.value = data.data
      await loadHistory()
    } else {
      feedback.value = data.message
      feedbackClass.value = 'text-red-600'
      lastScan.value = null
    }
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal memproses serial (server error).'
    feedback.value = msg
    feedbackClass.value = 'text-red-600'
    lastScan.value = null
  }

  serialInputVal.value = ''
  scanning.value = false
  nextTick(() => serialInput.value?.focus())
}

async function onRollback(row) {
  const confirm = await Swal.fire({
    title: 'Rollback serial?',
    html: `Serial <b>${row.serial_number}</b> (${row.item_name}) akan dikembalikan.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, rollback',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#d33',
  })

  if (!confirm.isConfirmed) return

  rollingBack.value = row.id
  try {
    const { data } = await axios.delete(`/api/outlet-serial-receive/${row.id}`)
    if (data.success) {
      await Swal.fire('Berhasil', data.message, 'success')
      await loadHistory()
      if (lastScan.value?.id === row.id) {
        lastScan.value = null
      }
    } else {
      await Swal.fire('Gagal', data.message, 'error')
    }
  } catch (e) {
    const msg = e?.response?.data?.message || 'Gagal rollback serial.'
    await Swal.fire('Error', msg, 'error')
  }
  rollingBack.value = null
  nextTick(() => serialInput.value?.focus())
}

onMounted(() => {
  loadHistory()
  nextTick(() => serialInput.value?.focus())
})
</script>
