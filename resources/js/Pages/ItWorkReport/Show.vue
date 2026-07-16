<template>
  <AppLayout>
    <div class="w-full max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-laptop-medical text-cyan-600"></i>
            {{ record.number }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">{{ record.title || 'IT Work Report' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            v-if="canEdit"
            :href="route('it-work-reports.edit', record.id)"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600"
          >
            <i class="fa-solid fa-pen"></i> Edit
          </Link>
          <button
            v-if="canDelete"
            type="button"
            @click="destroyReport"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600"
          >
            <i class="fa-solid fa-trash"></i> Hapus
          </button>
          <Link
            :href="route('it-work-reports.index')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <div class="flex flex-wrap items-center gap-2 mb-4">
          <span :class="record.status === 'submitted' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold">
            {{ record.status === 'submitted' ? 'Submitted' : 'Draft' }}
          </span>
          <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
            {{ sourceOptions[record.source_type] || record.source_type }}
          </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><span class="text-gray-500">Tanggal:</span> <span class="font-medium">{{ formatDate(record.work_date) }}</span></div>
          <div><span class="text-gray-500">Jam:</span> <span class="font-medium">{{ formatTime(record.start_time) }} – {{ formatTime(record.end_time) }}</span></div>
          <div><span class="text-gray-500">Outlet:</span> <span class="font-medium">{{ record.outlet_name }}</span></div>
          <div><span class="text-gray-500">Pelaksana:</span> <span class="font-medium">{{ record.executor?.nama_lengkap || '-' }}</span></div>
          <div v-if="record.ticket">
            <span class="text-gray-500">Ticket:</span>
            <Link :href="route('tickets.show', record.ticket.id)" class="font-medium text-cyan-700 hover:underline ml-1">
              {{ record.ticket.ticket_number }}
            </Link>
          </div>
          <div v-if="record.source_type === 'whatsapp'" class="md:col-span-2 space-y-1">
            <div><span class="text-gray-500">WA kontak:</span> <span class="font-medium">{{ record.wa_contact_name || '-' }}</span> <span v-if="record.wa_phone" class="text-gray-500">({{ record.wa_phone }})</span></div>
            <div><span class="text-gray-500">Ringkasan:</span> <span class="font-medium whitespace-pre-wrap">{{ record.wa_summary || '-' }}</span></div>
          </div>
          <div v-if="record.notes" class="md:col-span-2"><span class="text-gray-500">Catatan:</span> <span class="font-medium whitespace-pre-wrap">{{ record.notes }}</span></div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
          <h2 class="text-lg font-semibold text-gray-800">Perangkat</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tipe</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Label</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Identifier</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Scopes</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Hasil</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Catatan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="item in record.items" :key="item.id">
                <td class="px-4 py-3 text-sm">{{ deviceTypes[item.device_type] || item.device_type }}</td>
                <td class="px-4 py-3 text-sm font-medium">{{ item.device_label }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ item.identifier || '-' }}</td>
                <td class="px-4 py-3 text-sm">
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="s in (item.scopes || [])"
                      :key="s"
                      class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-cyan-50 text-cyan-700"
                    >{{ scopeOptions[s] || s }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-sm">{{ resultOptions[item.result] || item.result || '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ item.notes || '-' }}</td>
              </tr>
              <tr v-if="!record.items?.length">
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada perangkat.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Evidence</h2>
        <div v-if="!record.evidences?.length" class="text-sm text-gray-500">Belum ada evidence.</div>
        <div v-else class="space-y-4">
          <div v-for="group in evidenceGroups" :key="group.kind">
            <h3 class="text-sm font-semibold text-gray-600 mb-2">{{ group.label }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <a
                v-for="ev in group.items"
                :key="ev.id"
                :href="ev.url"
                target="_blank"
                class="border rounded-lg overflow-hidden hover:shadow-md transition bg-gray-50"
              >
                <img
                  v-if="isImage(ev)"
                  :src="ev.url"
                  :alt="ev.original_name"
                  class="w-full h-28 object-cover"
                />
                <div v-else class="h-28 flex items-center justify-center text-gray-400">
                  <i class="fa-solid fa-file text-2xl"></i>
                </div>
                <div class="px-2 py-1 text-xs truncate text-gray-600">{{ ev.original_name || 'file' }}</div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  record: Object,
  deviceTypes: Object,
  scopeOptions: Object,
  resultOptions: Object,
  sourceOptions: Object,
  canEdit: Boolean,
  canDelete: Boolean,
})

const evidenceGroups = computed(() => {
  const labels = {
    wa_screenshot: 'Screenshot WhatsApp',
    work: 'Evidence pekerjaan',
    other: 'Lainnya',
  }
  const groups = []
  for (const kind of Object.keys(labels)) {
    const items = (props.record.evidences || []).filter((e) => e.kind === kind)
    if (items.length) {
      groups.push({ kind, label: labels[kind], items })
    }
  }
  return groups
})

function formatDate(value) {
  return value ? String(value).slice(0, 10) : '-'
}

function formatTime(value) {
  return value ? String(value).slice(0, 5) : '-'
}

function isImage(ev) {
  return (ev.mime_type || '').startsWith('image/') || /\.(jpe?g|png|gif|webp)$/i.test(ev.original_name || '')
}

function destroyReport() {
  if (!confirm('Hapus draft report ini?')) return
  router.delete(route('it-work-reports.destroy', props.record.id))
}
</script>
