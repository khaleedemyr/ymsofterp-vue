<template>
  <Head :title="`GR Serial: ${header.number}`" />
  <AppLayout>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Detail GR Serial</h1>
          <p class="text-sm text-gray-500 mt-1">{{ header.number }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button v-if="canDelete" @click="onDelete"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-100 rounded-xl hover:bg-red-100 transition">
            <i class="fa fa-trash text-xs"></i> Hapus
          </button>
          <a href="/outlet-serial-receive"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition">
            <i class="fa fa-arrow-left text-xs"></i> Kembali
          </a>
        </div>
      </div>

      <!-- Header Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
          <div>
            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider block mb-1">Nomor GR</span>
            <span class="font-mono font-bold text-indigo-700 text-lg">{{ header.number }}</span>
          </div>
          <div>
            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider block mb-1">Tanggal</span>
            <span class="font-medium text-gray-800">{{ formatDate(header.receive_date) }}</span>
          </div>
          <div>
            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider block mb-1">Outlet</span>
            <span class="font-medium text-gray-800">{{ header.outlet_name || header.outlet_id || '-' }}</span>
          </div>
          <div>
            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider block mb-1">Dibuat oleh</span>
            <span class="font-medium text-gray-800">{{ header.created_by_name || '-' }}</span>
          </div>
          <div>
            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider block mb-1">Jumlah Serial</span>
            <span class="inline-flex items-center justify-center min-w-[32px] px-2.5 py-1 text-sm font-bold rounded-full bg-emerald-50 text-emerald-700">
              {{ items.length }}
            </span>
          </div>
        </div>
        <div v-if="header.notes" class="mt-5 pt-4 border-t border-gray-100">
          <span class="text-xs font-medium text-gray-400 uppercase tracking-wider block mb-1">Catatan</span>
          <span class="text-sm text-gray-700">{{ header.notes }}</span>
        </div>
      </div>

      <!-- Items Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
          <h2 class="text-base font-semibold text-gray-800">Daftar Serial</h2>
        </div>
        <div v-if="!items.length" class="text-center py-16">
          <p class="text-gray-500 text-sm">Tidak ada item.</p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">No</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Serial</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Item</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">DO</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Warehouse</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="(row, idx) in items" :key="row.id" class="hover:bg-indigo-50/30 transition-colors duration-150">
                <td class="px-4 py-3 text-gray-500">{{ idx + 1 }}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ row.serial_number }}</td>
                <td class="px-4 py-3 text-gray-800 font-medium">{{ row.item_name }}</td>
                <td class="px-4 py-3 text-right text-gray-700">{{ fmtQty(row.qty) }}</td>
                <td class="px-4 py-3 text-gray-600">{{ row.unit_name || '-' }}</td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ row.delivery_order_number }}</td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ row.outlet_name || row.outlet_id }}</td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ row.warehouse_name || '-' }}</td>
                <td class="px-4 py-3 text-right text-gray-700">{{ formatRupiah(row.cost_small) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t border-gray-200 bg-gray-50/80">
                <td colspan="3" class="px-4 py-3 text-right text-xs font-bold text-gray-600 uppercase">Total</td>
                <td class="px-4 py-3 text-right font-bold text-gray-800">{{ fmtQty(totalQty) }}</td>
                <td colspan="4"></td>
                <td class="px-4 py-3 text-right font-bold text-gray-800">{{ formatRupiah(totalCost) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  header: Object,
  items: Array,
  canDelete: Boolean,
})

const totalQty = computed(() => props.items.reduce((sum, i) => sum + Number(i.qty || 0), 0))
const totalCost = computed(() => props.items.reduce((sum, i) => sum + Number(i.cost_small || 0) * Number(i.qty || 0), 0))

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

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

async function onDelete() {
  const confirm = await Swal.fire({
    title: 'Hapus GR Serial?',
    html: `GR <b>${props.header.number}</b> akan dihapus dan inventory akan di-rollback.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
  })

  if (!confirm.isConfirmed) return

  try {
    await axios.delete(`/outlet-serial-receive/${props.header.id}`)
    Swal.fire({ title: 'Berhasil', text: `GR ${props.header.number} berhasil dihapus.`, icon: 'success', timer: 2000, showConfirmButton: false })
    setTimeout(() => { window.location.href = '/outlet-serial-receive' }, 1500)
  } catch (e) {
    const msg = e?.response?.data?.errors?.message || e?.response?.data?.message || 'Gagal menghapus GR.'
    Swal.fire('Gagal', msg, 'error')
  }
}
</script>
