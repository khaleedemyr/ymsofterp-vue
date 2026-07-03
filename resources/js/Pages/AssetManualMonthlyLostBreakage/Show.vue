<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-file-pen text-blue-600"></i>
            Detail Asset Manual Monthly Lost & Breakage
          </h1>
          <p class="text-sm text-gray-500 mt-1">Periode: <strong>{{ monthLabel }} {{ record.year }}</strong></p>
        </div>
        <div class="flex gap-2">
          <Link
            :href="route('asset-manual-monthly-lost-breakage.edit', record.id)"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200"
          >
            <i class="fa-solid fa-pen"></i> Edit
          </Link>
          <Link
            :href="route('asset-manual-monthly-lost-breakage.index')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Periode</p>
          <p class="text-lg font-semibold text-gray-800">{{ monthLabel }} {{ record.year }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Dibuat Oleh</p>
          <p class="text-lg font-semibold text-gray-800">{{ record.creator?.nama_lengkap || '-' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wide">Terakhir Diubah</p>
          <p class="text-lg font-semibold text-gray-800">{{ formatDateTime(record.updated_at) }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-800 text-white">
            <tr>
              <th class="px-4 py-3 text-left">Outlet</th>
              <th class="px-4 py-3 text-right">Nilai Lost & Breakage</th>
              <th class="px-4 py-3 text-right">% Lost & Breakage</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!record.items?.length">
              <td colspan="3" class="px-4 py-8 text-center text-gray-500">Tidak ada data outlet.</td>
            </tr>
            <tr
              v-for="item in record.items"
              :key="item.id"
              class="border-b hover:bg-gray-50"
            >
              <td class="px-4 py-3 font-medium text-gray-800">{{ item.outlet?.nama_outlet || '-' }}</td>
              <td class="px-4 py-3 text-right">{{ formatNumber(item.lost_breakage_value) }}</td>
              <td class="px-4 py-3 text-right">{{ formatPercent(item.lost_breakage_percent) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  record: { type: Object, required: true },
  monthLabel: { type: String, default: '' },
})

function formatNumber(value) {
  if (value === null || value === undefined || value === '') return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Number(value))
}

function formatPercent(value) {
  if (value === null || value === undefined || value === '') return '-'
  return `${new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Number(value))}%`
}

function formatDateTime(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}
</script>
