<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-truck text-cyan-600"></i>
            {{ record.number }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">Detail Logbook Driver</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            v-if="canEdit"
            :href="route('logbook-drivers.edit', record.id)"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600"
          >
            <i class="fa-solid fa-pen"></i> Edit
          </Link>
          <button
            v-if="canDelete"
            type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600"
            @click="destroyRecord"
          >
            <i class="fa-solid fa-trash"></i> Hapus
          </button>
          <Link
            :href="route('logbook-drivers.index')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-500">Tanggal:</span> <span class="font-medium">{{ formatDate(record.log_date) }}</span></div>
          <div><span class="text-gray-500">Driver:</span> <span class="font-medium">{{ record.driver_name }}</span></div>
          <div><span class="text-gray-500">Outlet:</span> <span class="font-medium">{{ record.outlet_name }}</span></div>
          <div><span class="text-gray-500">Dibuat oleh:</span> <span class="font-medium">{{ record.creator?.nama_lengkap || '-' }}</span></div>
          <div v-if="record.notes" class="md:col-span-2">
            <span class="text-gray-500">Catatan:</span>
            <span class="font-medium whitespace-pre-wrap">{{ record.notes }}</span>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div
          v-for="(item, idx) in record.items"
          :key="item.id"
          class="bg-white rounded-xl shadow overflow-hidden"
        >
          <div class="px-6 py-4 border-b bg-gradient-to-r from-cyan-50 to-white flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-cyan-100 text-cyan-700 text-xs font-bold">
              {{ idx + 1 }}
            </span>
            <div>
              <h2 class="text-lg font-semibold text-gray-800">
                {{ formatTime(item.log_time) || '—' }}
              </h2>
              <p class="text-xs text-gray-500">Baris log #{{ idx + 1 }}</p>
            </div>
          </div>
          <div class="px-6 py-4 space-y-3">
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ item.description }}</p>
            <div v-if="item.photo_url">
              <a :href="item.photo_url" target="_blank" rel="noopener" class="inline-block">
                <img
                  :src="item.photo_url"
                  alt="Foto log"
                  class="w-40 h-40 object-cover rounded-lg border hover:shadow-md transition"
                />
              </a>
            </div>
            <p v-else class="text-sm text-gray-400">Tidak ada foto.</p>
          </div>
        </div>

        <div v-if="!(record.items || []).length" class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
          Belum ada baris log.
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  record: Object,
  canEdit: Boolean,
  canDelete: Boolean,
})

function formatDate(value) {
  if (!value) return '-'
  const d = String(value).slice(0, 10)
  const [y, m, day] = d.split('-')
  if (!y || !m || !day) return value
  return `${day}/${m}/${y}`
}

function formatTime(value) {
  if (!value) return ''
  return String(value).slice(0, 5)
}

function destroyRecord() {
  if (!confirm(`Hapus logbook ${props.record.number}?`)) return
  router.delete(route('logbook-drivers.destroy', props.record.id))
}
</script>
