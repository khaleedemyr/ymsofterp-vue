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
            <div><span class="text-gray-500">WA kontak:</span> <span class="font-medium">{{ record.wa_contact_name || '-' }}</span></div>
            <div><span class="text-gray-500">Ringkasan:</span> <span class="font-medium whitespace-pre-wrap">{{ record.wa_summary || '-' }}</span></div>
          </div>
          <div v-if="record.notes" class="md:col-span-2"><span class="text-gray-500">Catatan:</span> <span class="font-medium whitespace-pre-wrap">{{ record.notes }}</span></div>
        </div>

        <div v-if="waEvidences.length" class="mt-4">
          <h3 class="text-sm font-semibold text-gray-600 mb-2">Screenshot WhatsApp</h3>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="(ev, idx) in waEvidences"
              :key="ev.id"
              type="button"
              class="relative w-24 h-24 rounded-lg overflow-hidden border bg-gray-100 cursor-pointer"
              @click="openLightboxGroup(waImageUrls, waImageIndex(ev), ev)"
            >
              <img v-if="ev.is_image" :src="ev.url" class="w-full h-full object-cover" />
              <video v-else-if="ev.is_video" :src="ev.url" class="w-full h-full object-cover" />
            </button>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div v-for="(item, idx) in record.items" :key="item.id" class="bg-white rounded-xl shadow overflow-hidden">
          <div class="px-6 py-4 border-b bg-gradient-to-r from-cyan-50 to-white flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-cyan-100 text-cyan-700 text-xs font-bold">{{ idx + 1 }}</span>
            <div>
              <h2 class="text-lg font-semibold text-gray-800">
                {{ deviceTypes[item.device_type] || item.device_type }} — {{ item.device_label }}
              </h2>
              <p class="text-xs text-gray-500">
                {{ item.identifier || 'Tanpa identifier' }}
                <span v-if="item.device_type === 'laptop' && item.laptop_user_name"> · User: {{ item.laptop_user_name }}</span>
                <span v-if="item.device_type === 'laptop' && item.identifier"> · Serial: {{ item.identifier }}</span>
                <span v-if="item.result"> · {{ resultOptions[item.result] || item.result }}</span>
              </p>
            </div>
          </div>
          <div class="px-6 py-4 space-y-3">
            <div class="flex flex-wrap gap-1">
              <span
                v-for="s in (item.scopes || [])"
                :key="s"
                class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-cyan-50 text-cyan-700"
              >{{ scopeOptions[s] || s }}</span>
            </div>
            <p v-if="item.notes" class="text-sm text-gray-600">{{ item.notes }}</p>

            <div>
              <h3 class="text-sm font-semibold text-gray-600 mb-2">Evidence</h3>
              <div v-if="!(item.evidences || []).length" class="text-sm text-gray-400">Belum ada evidence.</div>
              <div v-else class="flex flex-wrap gap-2">
                <div
                  v-for="ev in item.evidences"
                  :key="ev.id"
                  class="relative w-36 rounded-lg overflow-hidden border bg-gray-100 hover:shadow-md transition cursor-pointer"
                  @click="openItemEvidenceLightbox(item, ev)"
                >
                  <div class="block w-full h-28">
                    <img v-if="ev.is_image" :src="ev.url" :alt="ev.original_name" class="w-full h-full object-cover" />
                    <div v-else-if="ev.is_video" class="relative w-full h-full">
                      <video :src="ev.url" class="w-full h-full object-cover" muted />
                      <span class="absolute inset-0 flex items-center justify-center bg-black/20">
                        <i class="fa-solid fa-play text-white text-lg"></i>
                      </span>
                    </div>
                    <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                      <i class="fa-solid fa-file text-2xl"></i>
                    </div>
                  </div>
                  <div class="px-2 py-1 text-[10px] leading-snug text-gray-600 bg-white border-t space-y-0.5" @click.stop>
                    <div v-if="ev.captured_at">{{ String(ev.captured_at).replace('T', ' ').slice(0, 19) }}</div>
                    <div v-if="ev.address" class="truncate" :title="ev.address">{{ ev.address }}</div>
                    <div v-if="ev.latitude != null">{{ Number(ev.latitude).toFixed(6) }}, {{ Number(ev.longitude).toFixed(6) }}</div>
                    <a
                      v-if="ev.maps_url"
                      :href="ev.maps_url"
                      target="_blank"
                      class="text-cyan-700 hover:underline"
                    >Google Maps</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!record.items?.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
          Tidak ada perangkat.
        </div>
      </div>
    </div>

    <VueEasyLightbox
      :visible="lightboxVisible"
      :imgs="lightboxImgs"
      :index="lightboxIndex"
      @hide="lightboxVisible = false"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import VueEasyLightbox from 'vue-easy-lightbox'

const props = defineProps({
  record: Object,
  deviceTypes: Object,
  scopeOptions: Object,
  resultOptions: Object,
  sourceOptions: Object,
  canEdit: Boolean,
  canDelete: Boolean,
})

const lightboxVisible = ref(false)
const lightboxImgs = ref([])
const lightboxIndex = ref(0)

const waEvidences = computed(() =>
  (props.record.evidences || []).filter((e) => e.kind === 'wa_screenshot')
)

const waImageUrls = computed(() =>
  waEvidences.value.filter((e) => e.is_image).map((e) => e.url)
)

function waImageIndex(ev) {
  return waImageUrls.value.indexOf(ev.url)
}

function openLightboxGroup(images, index, ev) {
  if (ev && !ev.is_image) {
    if (ev.url) window.open(ev.url, '_blank')
    return
  }
  if (!images?.length) return
  lightboxImgs.value = images
  lightboxIndex.value = Math.max(0, index)
  lightboxVisible.value = true
}

function openItemEvidenceLightbox(item, ev) {
  if (!ev.is_image) {
    if (ev.url) window.open(ev.url, '_blank')
    return
  }
  const imgs = (item.evidences || []).filter((e) => e.is_image).map((e) => e.url)
  openLightboxGroup(imgs, imgs.indexOf(ev.url), ev)
}

function formatDate(value) {
  if (!value) return '-'
  const s = String(value)
  if (/^\d{4}-\d{2}-\d{2}$/.test(s.slice(0, 10)) && !s.includes('T')) {
    return s.slice(0, 10)
  }
  if (s.includes('T')) {
    const d = new Date(s)
    if (!Number.isNaN(d.getTime())) {
      const p = (n) => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
    }
  }
  return s.slice(0, 10)
}

function formatTime(value) {
  return value ? String(value).slice(0, 5) : '-'
}

function destroyReport() {
  if (!confirm('Hapus draft report ini?')) return
  router.delete(route('it-work-reports.destroy', props.record.id))
}
</script>
