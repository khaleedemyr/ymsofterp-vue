<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-laptop-medical text-cyan-600"></i>
            {{ isEdit ? 'Edit IT Work Report' : 'Buat IT Work Report' }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">1 laporan = 1 kunjungan / 1 outlet, multi perangkat</p>
        </div>
        <Link
          :href="isEdit ? route('it-work-reports.show', record.id) : route('it-work-reports.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
        >
          <i class="fa-solid fa-arrow-left"></i>
          Kembali
        </Link>
      </div>

      <form @submit.prevent="submit(false)">
        <div class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Kunjungan</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal kerja *</label>
              <input v-model="form.work_date" type="date" required class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
              <p v-if="form.errors.work_date" class="text-xs text-red-500 mt-1">{{ form.errors.work_date }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Jam mulai</label>
              <div class="grid grid-cols-2 gap-2">
                <select v-model="startHour" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                  <option value="">Jam</option>
                  <option v-for="h in hourOptions" :key="'sh-'+h" :value="h">{{ h }}</option>
                </select>
                <select v-model="startMinute" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                  <option value="">Menit</option>
                  <option v-for="m in minuteOptions" :key="'sm-'+m" :value="m">{{ m }}</option>
                </select>
              </div>
              <p class="text-[10px] text-gray-400 mt-0.5">Format 24 jam</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Jam selesai</label>
              <div class="grid grid-cols-2 gap-2">
                <select v-model="endHour" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                  <option value="">Jam</option>
                  <option v-for="h in hourOptions" :key="'eh-'+h" :value="h">{{ h }}</option>
                </select>
                <select v-model="endMinute" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                  <option value="">Menit</option>
                  <option v-for="m in minuteOptions" :key="'em-'+m" :value="m">{{ m }}</option>
                </select>
              </div>
              <p class="text-[10px] text-gray-400 mt-0.5">Format 24 jam</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet *</label>
              <select v-model="form.outlet_id" required class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                <option value="">Pilih outlet</option>
                <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
              <p v-if="form.errors.outlet_id" class="text-xs text-red-500 mt-1">{{ form.errors.outlet_id }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Pelaksana</label>
              <select v-model="form.executor_id" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                <option v-for="u in executors" :key="u.id" :value="u.id">{{ u.nama_lengkap }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Sumber *</label>
              <select v-model="form.source_type" required class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                <option v-for="(label, key) in sourceOptions" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Judul / ringkasan</label>
              <input v-model="form.title" type="text" placeholder="Opsional" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan umum</label>
              <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </div>
          </div>
        </div>

        <div v-if="form.source_type === 'ticket'" class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Link Ticket</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Cari ticket</label>
              <input
                v-model="ticketQuery"
                type="text"
                placeholder="Nomor atau judul ticket..."
                class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500"
                @input="onTicketSearch"
              />
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-1">Terpilih</p>
              <div class="text-sm font-medium text-gray-800">{{ selectedTicketLabel || 'Belum dipilih' }}</div>
              <p v-if="form.errors.ticket_id" class="text-xs text-red-500 mt-1">{{ form.errors.ticket_id }}</p>
            </div>
          </div>
          <ul v-if="ticketResults.length" class="mt-3 border rounded-lg divide-y max-h-48 overflow-y-auto">
            <li
              v-for="t in ticketResults"
              :key="t.id"
              class="px-3 py-2 text-sm hover:bg-cyan-50 cursor-pointer"
              @click="selectTicket(t)"
            >
              <span class="font-semibold text-cyan-700">{{ t.ticket_number }}</span> — {{ t.title }}
            </li>
          </ul>
        </div>

        <div v-if="form.source_type === 'whatsapp'" class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Sumber WhatsApp</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Nama kontak *</label>
              <input v-model="form.wa_contact_name" type="text" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">No. HP</label>
              <input v-model="form.wa_phone" type="text" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Waktu lapor</label>
              <div class="grid grid-cols-2 gap-2">
                <input v-model="waReportDate" type="date" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
                <input
                  v-model="waReportTime"
                  type="text"
                  inputmode="numeric"
                  placeholder="HH:mm"
                  maxlength="5"
                  class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500"
                  @blur="normalizeWaTime"
                />
              </div>
              <p class="text-[10px] text-gray-400 mt-0.5">Jam format 24 jam</p>
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Ringkasan chat *</label>
              <textarea v-model="form.wa_summary" rows="2" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-gray-600 mb-2">Screenshot WA (upload file, wajib saat submit)</label>
              <div class="flex flex-wrap gap-2 mb-3 items-center">
                <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-cyan-600 text-white text-sm" @click="waUploadInput?.click()">
                  <i class="fa-solid fa-upload"></i> Upload screenshot
                </button>
                <input ref="waUploadInput" type="file" accept="image/*,video/*,.pdf" multiple class="hidden" @change="onWaUpload" />
              </div>
              <p v-if="form.errors.wa_screenshots" class="text-xs text-red-500 mb-2">{{ form.errors.wa_screenshots }}</p>
              <div class="flex flex-wrap gap-2">
                <div
                  v-for="(preview, pIdx) in waPreviews"
                  :key="'wa-new-'+pIdx"
                  class="relative w-24 h-24 rounded-lg overflow-hidden border bg-gray-100 cursor-pointer"
                  @click="openWaLightbox('new', pIdx)"
                >
                  <img v-if="preview.isImage" :src="preview.url" class="w-full h-full object-cover" />
                  <video v-else-if="preview.isVideo" :src="preview.url" class="w-full h-full object-cover" />
                  <button type="button" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs z-10" @click.stop="removeWaNew(pIdx)">×</button>
                </div>
                <div
                  v-for="(ev, eIdx) in existingWa"
                  :key="'wa-old-'+ev.id"
                  class="relative w-24 h-24 rounded-lg overflow-hidden border bg-gray-100 cursor-pointer"
                  @click="openWaLightbox('old', eIdx)"
                >
                  <img v-if="ev.is_image" :src="ev.url" class="w-full h-full object-cover" />
                  <video v-else-if="ev.is_video" :src="ev.url" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-400"><i class="fa-solid fa-file"></i></div>
                  <button type="button" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs z-10" @click.stop="markRemove(ev.id)">×</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
          <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-cyan-50 to-white">
            <div>
              <h2 class="text-lg font-semibold text-gray-800">Perangkat dikerjakan</h2>
              <p class="text-xs text-gray-500">Evidence wajib dari kamera; foto ditandai tanggal, jam, alamat & koordinat</p>
            </div>
            <button type="button" @click="addItem" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 text-sm">
              <i class="fa-solid fa-plus"></i> Tambah perangkat
            </button>
          </div>
          <p v-if="form.errors.items" class="px-6 pt-4 text-sm text-red-500">{{ form.errors.items }}</p>

          <div v-for="(item, index) in form.items" :key="item._key" class="px-6 py-5 border-b last:border-b-0">
            <div class="flex items-center justify-between mb-3">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-cyan-100 text-cyan-700 text-xs font-bold">{{ index + 1 }}</span>
              <button v-if="form.items.length > 1" type="button" class="text-red-500 text-sm" @click="removeItem(index)">
                <i class="fa-solid fa-trash"></i> Hapus
              </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Tipe *</label>
                <select v-model="item.device_type" required class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                  <option v-for="(label, key) in deviceTypes" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Label / lokasi *</label>
                <input v-model="item.device_label" type="text" required placeholder="PC Kasir 1" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
              </div>
              <div v-if="item.device_type !== 'laptop'">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Identifier</label>
                <input v-model="item.identifier" type="text" placeholder="IP / hostname / serial" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Hasil</label>
                <select v-model="item.result" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                  <option value="">-</option>
                  <option v-for="(label, key) in resultOptions" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
              <template v-if="item.device_type === 'laptop'">
                <div class="md:col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Nama pengguna laptop *</label>
                  <input v-model="item.laptop_user_name" type="text" required placeholder="Nama user yang memakai laptop" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
                  <p v-if="form.errors[`items.${index}.laptop_user_name`]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${index}.laptop_user_name`] }}</p>
                </div>
                <div class="md:col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Serial laptop *</label>
                  <input v-model="item.identifier" type="text" required placeholder="Serial number laptop" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
                  <p v-if="form.errors[`items.${index}.identifier`]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${index}.identifier`] }}</p>
                </div>
              </template>
              <div class="md:col-span-4">
                <label class="block text-xs font-semibold text-gray-600 mb-2">Scope pekerjaan *</label>
                <div class="flex flex-wrap gap-3">
                  <label v-for="(label, key) in scopeOptions" :key="key" class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" :value="key" v-model="item.scopes" class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500" />
                    {{ label }}
                  </label>
                </div>
                <p v-if="form.errors[`items.${index}.scopes`]" class="text-xs text-red-500 mt-1">{{ form.errors[`items.${index}.scopes`] }}</p>
              </div>
              <div class="md:col-span-4">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan perangkat</label>
                <textarea v-model="item.notes" rows="3" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" placeholder="Catatan detail pekerjaan..." />
              </div>

              <div class="md:col-span-4 mt-1">
                <label class="block text-xs font-semibold text-gray-600 mb-2">
                  Evidence perangkat <span class="text-gray-400 font-normal">(wajib dari kamera + tag lokasi saat submit)</span>
                </label>
                <div class="flex flex-wrap gap-2 mb-3 items-center">
                  <button
                    type="button"
                    :disabled="locationBusy"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-700 text-white text-sm disabled:opacity-50"
                    @click="openItemCamera(index)"
                  >
                    <i class="fa-solid fa-camera"></i>
                    {{ locationBusy && capturingIndex === index ? 'Mengambil lokasi...' : 'Ambil dari kamera' }}
                  </button>
                  <span class="text-xs text-gray-500">Upload galeri tidak diizinkan. Foto otomatis ditandai tanggal, jam, alamat & koordinat.</span>
                </div>
                <p v-if="form.errors[`item_evidences.${index}`]" class="text-xs text-red-500 mb-2">{{ form.errors[`item_evidences.${index}`] }}</p>

                <div class="flex flex-wrap gap-2">
                  <div
                    v-for="(preview, pIdx) in (itemMedia[index]?.previews || [])"
                    :key="'new-'+index+'-'+pIdx"
                    class="relative w-28 rounded-lg overflow-hidden border bg-gray-100 cursor-pointer"
                    @click="openItemLightbox(index, 'new', pIdx)"
                  >
                    <div class="w-28 h-24">
                      <img v-if="preview.isImage" :src="preview.url" class="w-full h-full object-cover" />
                      <div v-else-if="preview.isVideo" class="relative w-full h-full">
                        <video :src="preview.url" class="w-full h-full object-cover" />
                        <span class="absolute bottom-1 left-1 bg-black/60 text-white text-[10px] px-1 rounded"><i class="fa-solid fa-play"></i></span>
                      </div>
                    </div>
                    <div v-if="itemMedia[index]?.metas?.[pIdx]" class="px-1 py-0.5 text-[9px] leading-tight text-gray-600 bg-white border-t truncate">
                      {{ formatMetaShort(itemMedia[index].metas[pIdx]) }}
                    </div>
                    <button type="button" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs leading-none z-10" @click.stop="removeItemNew(index, pIdx)">×</button>
                  </div>

                  <div
                    v-for="(ev, eIdx) in existingItemEvidences(item.id)"
                    :key="'old-'+ev.id"
                    class="relative w-28 rounded-lg overflow-hidden border bg-gray-100 cursor-pointer"
                    @click="openItemLightbox(index, 'old', eIdx, item.id)"
                  >
                    <div class="w-28 h-24">
                      <img v-if="ev.is_image" :src="ev.url" class="w-full h-full object-cover" />
                      <div v-else-if="ev.is_video" class="relative w-full h-full">
                        <video :src="ev.url" class="w-full h-full object-cover" />
                        <span class="absolute bottom-1 left-1 bg-black/60 text-white text-[10px] px-1 rounded"><i class="fa-solid fa-play"></i></span>
                      </div>
                      <div v-else class="w-full h-full flex items-center justify-center text-gray-400"><i class="fa-solid fa-file"></i></div>
                    </div>
                    <div v-if="ev.captured_at || ev.address || ev.latitude" class="px-1 py-0.5 text-[9px] leading-tight text-gray-600 bg-white border-t truncate">
                      {{ formatExistingMetaShort(ev) }}
                    </div>
                    <button type="button" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs z-10" @click.stop="markRemove(ev.id)">×</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap gap-3 justify-end">
          <button type="button" :disabled="form.processing" @click="submit(false)" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-gray-100 text-gray-800 hover:bg-gray-200 disabled:opacity-50">
            Simpan Draft
          </button>
          <button type="button" :disabled="form.processing" @click="submit(true)" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 disabled:opacity-50">
            <i class="fa-solid fa-paper-plane"></i>
            Submit
          </button>
        </div>
      </form>

      <CameraModal
        v-if="showCameraModal"
        mode="photo"
        @close="closeCameraModal"
        @capture="onLiveCameraCapture"
      />

      <VueEasyLightbox
        :visible="lightboxVisible"
        :imgs="lightboxImgs"
        :index="lightboxIndex"
        @hide="lightboxVisible = false"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CameraModal from '@/Components/CameraModal.vue'
import VueEasyLightbox from 'vue-easy-lightbox'
import axios from 'axios'

const props = defineProps({
  record: Object,
  prefillTicket: Object,
  outlets: Array,
  executors: Array,
  deviceTypes: Object,
  scopeOptions: Object,
  resultOptions: Object,
  sourceOptions: Object,
  currentUserId: [Number, String],
})

const isEdit = computed(() => !!props.record?.id)
let keySeq = 0

function emptyItem() {
  return {
    id: null,
    _key: `new-${++keySeq}`,
    device_type: 'pc',
    device_label: '',
    identifier: '',
    laptop_user_name: '',
    scopes: [],
    notes: '',
    result: '',
  }
}

function mapRecordItems(items) {
  if (!items?.length) return [emptyItem()]
  return items.map((i) => ({
    id: i.id || null,
    _key: `item-${i.id || ++keySeq}`,
    device_type: i.device_type || 'pc',
    device_label: i.device_label || '',
    identifier: i.identifier || '',
    laptop_user_name: i.laptop_user_name || '',
    scopes: Array.isArray(i.scopes) ? [...i.scopes] : [],
    notes: i.notes || '',
    result: i.result || '',
  }))
}

function toTimeInput(value) {
  if (!value) return ''
  return String(value).slice(0, 5)
}

function toDatetimeLocal(value) {
  if (!value) return ''
  return String(value).replace(' ', 'T').slice(0, 16)
}

function splitHm(value) {
  const s = toTimeInput(value)
  if (!/^\d{2}:\d{2}$/.test(s)) return { hour: '', minute: '' }
  const [hour, minute] = s.split(':')
  return { hour, minute }
}

const hourOptions = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'))
const minuteOptions = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'))

function splitDateTimeLocal(value) {
  const s = toDatetimeLocal(value)
  if (!s) return { date: '', time: '' }
  const [date, time] = s.split('T')
  return { date: date || '', time: (time || '').slice(0, 5) }
}

function normalizeWaTime() {
  let v = String(waReportTime.value || '').trim().replace('.', ':')
  if (/^\d{3,4}$/.test(v)) {
    v = v.padStart(4, '0')
    v = `${v.slice(0, 2)}:${v.slice(2)}`
  }
  if (/^\d{1,2}:\d{1,2}$/.test(v)) {
    const [h, m] = v.split(':')
    const hh = Math.min(23, Math.max(0, parseInt(h, 10) || 0))
    const mm = Math.min(59, Math.max(0, parseInt(m, 10) || 0))
    waReportTime.value = `${pad2(hh)}:${pad2(mm)}`
  } else if (v && !/^([01]\d|2[0-3]):[0-5]\d$/.test(v)) {
    waReportTime.value = ''
  }
  syncWaReportedAt()
}

function syncWaReportedAt() {
  if (waReportDate.value && waReportTime.value) {
    form.wa_reported_at = `${waReportDate.value} ${waReportTime.value}:00`
  } else if (waReportDate.value) {
    form.wa_reported_at = `${waReportDate.value} 00:00:00`
  } else {
    form.wa_reported_at = ''
  }
}

function dataUrlToFile(dataUrl, filename = `camera_${Date.now()}.jpg`) {
  const parts = String(dataUrl).split(',')
  const mimeMatch = parts[0].match(/:(.*?);/)
  const mime = mimeMatch ? mimeMatch[1] : 'image/jpeg'
  const binary = atob(parts[1] || '')
  const len = binary.length
  const bytes = new Uint8Array(len)
  for (let i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i)
  return new File([bytes], filename, { type: mime, lastModified: Date.now() })
}

function filePreview(file) {
  return {
    url: URL.createObjectURL(file),
    isImage: (file.type || '').startsWith('image/'),
    isVideo: (file.type || '').startsWith('video/'),
    name: file.name,
  }
}

function pad2(n) {
  return String(n).padStart(2, '0')
}

function formatDateTimeParts(date = new Date()) {
  return {
    date: `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`,
    time: `${pad2(date.getHours())}:${pad2(date.getMinutes())}:${pad2(date.getSeconds())}`,
    captured_at: date.toISOString(),
  }
}

function formatMetaShort(meta) {
  if (!meta) return ''
  const dt = meta.date && meta.time ? `${meta.date} ${meta.time}` : ''
  const coord = meta.latitude != null ? `${Number(meta.latitude).toFixed(5)},${Number(meta.longitude).toFixed(5)}` : ''
  return [dt, coord].filter(Boolean).join(' · ')
}

function formatExistingMetaShort(ev) {
  const dt = ev.captured_at ? String(ev.captured_at).replace('T', ' ').slice(0, 19) : ''
  const coord = ev.latitude != null ? `${Number(ev.latitude).toFixed(5)},${Number(ev.longitude).toFixed(5)}` : ''
  return [dt, coord].filter(Boolean).join(' · ')
}

async function resolveLocationTag() {
  if (!navigator.geolocation) {
    throw new Error('Geolocation tidak tersedia')
  }
  const pos = await new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: true,
      timeout: 20000,
      maximumAge: 0,
    })
  })
  const latitude = pos.coords.latitude
  const longitude = pos.coords.longitude
  const maps_url = `https://maps.google.com/?q=${latitude},${longitude}`
  let address = ''
  try {
    const { data } = await axios.get(route('it-work-reports.reverse-geocode'), {
      params: { lat: latitude, lng: longitude },
    })
    address = data.address || ''
  } catch {
    address = ''
  }
  const parts = formatDateTimeParts(new Date())
  return {
    ...parts,
    latitude,
    longitude,
    address: address || `Lokasi GPS: ${latitude.toFixed(6)}, ${longitude.toFixed(6)}`,
    maps_url,
  }
}

function stampPhotoWithTag(file, meta) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      try {
        const canvas = document.createElement('canvas')
        canvas.width = img.width
        canvas.height = img.height
        const ctx = canvas.getContext('2d')
        ctx.drawImage(img, 0, 0)

        const lines = [
          `${meta.date} ${meta.time}`,
          meta.address || '',
          `${Number(meta.latitude).toFixed(6)}, ${Number(meta.longitude).toFixed(6)}`,
          meta.maps_url || '',
        ].filter(Boolean)

        const fontSize = Math.max(14, Math.round(img.width / 45))
        const padding = Math.round(fontSize * 0.6)
        const lineHeight = Math.round(fontSize * 1.35)
        const boxHeight = padding * 2 + lineHeight * lines.length
        const boxY = img.height - boxHeight

        ctx.fillStyle = 'rgba(0,0,0,0.55)'
        ctx.fillRect(0, boxY, img.width, boxHeight)
        ctx.fillStyle = '#ffffff'
        ctx.font = `bold ${fontSize}px sans-serif`
        ctx.textBaseline = 'top'
        lines.forEach((line, i) => {
          ctx.fillText(line, padding, boxY + padding + i * lineHeight, img.width - padding * 2)
        })

        canvas.toBlob((blob) => {
          URL.revokeObjectURL(url)
          if (!blob) {
            reject(new Error('Gagal menandai foto'))
            return
          }
          const stamped = new File([blob], file.name.replace(/\.\w+$/, '') + '_tagged.jpg', {
            type: 'image/jpeg',
            lastModified: Date.now(),
          })
          resolve(stamped)
        }, 'image/jpeg', 0.92)
      } catch (e) {
        URL.revokeObjectURL(url)
        reject(e)
      }
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Gagal memuat foto'))
    }
    img.src = url
  })
}

const initialTicket = props.record?.ticket || props.prefillTicket || null
const mappedItems = mapRecordItems(props.record?.items)

const form = useForm({
  work_date: props.record?.work_date?.slice?.(0, 10) || String(props.record?.work_date || '').slice(0, 10) || new Date().toISOString().slice(0, 10),
  start_time: toTimeInput(props.record?.start_time),
  end_time: toTimeInput(props.record?.end_time),
  outlet_id: props.record?.outlet_id || props.prefillTicket?.outlet_id || '',
  executor_id: props.record?.executor_id || props.currentUserId,
  source_type: props.record?.source_type || (props.prefillTicket ? 'ticket' : 'proactive'),
  ticket_id: props.record?.ticket_id || props.prefillTicket?.id || null,
  wa_contact_name: props.record?.wa_contact_name || '',
  wa_phone: props.record?.wa_phone || '',
  wa_reported_at: toDatetimeLocal(props.record?.wa_reported_at),
  wa_summary: props.record?.wa_summary || '',
  title: props.record?.title || '',
  notes: props.record?.notes || '',
  items: mappedItems,
  wa_screenshots: [],
  item_evidences: mappedItems.map(() => []),
  item_evidence_meta: mappedItems.map(() => []),
  remove_evidence_ids: [],
  submit: 0,
})

const initialStartHm = splitHm(form.start_time)
const initialEndHm = splitHm(form.end_time)
const startHour = ref(initialStartHm.hour)
const startMinute = ref(initialStartHm.minute)
const endHour = ref(initialEndHm.hour)
const endMinute = ref(initialEndHm.minute)

function syncStartTime() {
  form.start_time = startHour.value !== '' && startMinute.value !== ''
    ? `${startHour.value}:${startMinute.value}`
    : ''
}

function syncEndTime() {
  form.end_time = endHour.value !== '' && endMinute.value !== ''
    ? `${endHour.value}:${endMinute.value}`
    : ''
}

watch([startHour, startMinute], syncStartTime)
watch([endHour, endMinute], syncEndTime)

const initialWaDt = splitDateTimeLocal(props.record?.wa_reported_at)
const waReportDate = ref(initialWaDt.date)
const waReportTime = ref(initialWaDt.time)
watch([waReportDate, waReportTime], syncWaReportedAt)

const itemMedia = reactive(
  mappedItems.map(() => ({ files: [], previews: [], metas: [] }))
)
const waPreviews = ref([])
const locationBusy = ref(false)
const capturingIndex = ref(null)
const pendingLocation = ref(null)
const showCameraModal = ref(false)
const cameraTarget = ref(null) // item index
const waUploadInput = ref(null)
const lightboxVisible = ref(false)
const lightboxImgs = ref([])
const lightboxIndex = ref(0)
const existingEvidences = ref(
  (() => {
    const map = new Map()
    ;[...(props.record?.evidences || [])].forEach((e) => map.set(e.id, e))
    ;(props.record?.items || []).forEach((item) => {
      ;(item.evidences || []).forEach((e) => map.set(e.id, e))
    })
    return Array.from(map.values())
  })()
)
const existingWa = computed(() =>
  existingEvidences.value.filter((e) => e.kind === 'wa_screenshot' && !form.remove_evidence_ids.includes(e.id))
)

function existingItemEvidences(itemId) {
  if (!itemId) return []
  return existingEvidences.value.filter(
    (e) => e.it_work_report_item_id === itemId && e.kind === 'work' && !form.remove_evidence_ids.includes(e.id)
  )
}

function closeCameraModal() {
  showCameraModal.value = false
  cameraTarget.value = null
  pendingLocation.value = null
  locationBusy.value = false
  capturingIndex.value = null
}

function onWaUpload(event) {
  const files = Array.from(event.target.files || [])
  files.forEach((file) => {
    form.wa_screenshots.push(file)
    waPreviews.value.push(filePreview(file))
  })
  event.target.value = ''
}

function showLightbox(images, index = 0) {
  const imgs = images.filter(Boolean)
  if (!imgs.length) return
  lightboxImgs.value = imgs
  lightboxIndex.value = Math.max(0, Math.min(index, imgs.length - 1))
  lightboxVisible.value = true
}

function openWaLightbox(kind, idx) {
  const newImgs = waPreviews.value.filter((p) => p.isImage).map((p) => p.url)
  const oldImgs = existingWa.value.filter((e) => e.is_image).map((e) => e.url)
  const imgs = [...newImgs, ...oldImgs]
  let index = 0
  if (kind === 'new') {
    const preview = waPreviews.value[idx]
    if (!preview?.isImage) {
      if (preview?.url) window.open(preview.url, '_blank')
      return
    }
    index = newImgs.indexOf(preview.url)
  } else {
    const ev = existingWa.value[idx]
    if (!ev?.is_image) {
      if (ev?.url) window.open(ev.url, '_blank')
      return
    }
    index = newImgs.length + oldImgs.indexOf(ev.url)
  }
  showLightbox(imgs, index)
}

function openItemLightbox(itemIndex, kind, idx, itemId = null) {
  const newPreviews = itemMedia[itemIndex]?.previews || []
  const newImgs = newPreviews.filter((p) => p.isImage).map((p) => p.url)
  const oldList = existingItemEvidences(itemId || form.items[itemIndex]?.id)
  const oldImgs = oldList.filter((e) => e.is_image).map((e) => e.url)
  const imgs = [...newImgs, ...oldImgs]

  if (kind === 'new') {
    const preview = newPreviews[idx]
    if (!preview?.isImage) {
      if (preview?.url) window.open(preview.url, '_blank')
      return
    }
    showLightbox(imgs, newImgs.indexOf(preview.url))
    return
  }

  const ev = oldList[idx]
  if (!ev?.is_image) {
    if (ev?.url) window.open(ev.url, '_blank')
    return
  }
  showLightbox(imgs, newImgs.length + oldImgs.indexOf(ev.url))
}

async function openItemCamera(index) {
  locationBusy.value = true
  capturingIndex.value = index
  try {
    pendingLocation.value = await resolveLocationTag()
    cameraTarget.value = index
    showCameraModal.value = true
  } catch (e) {
    alert('Lokasi GPS wajib aktif untuk mengambil evidence. Izinkan akses lokasi lalu coba lagi.')
    pendingLocation.value = null
  } finally {
    locationBusy.value = false
    capturingIndex.value = null
  }
}

async function onLiveCameraCapture(payload) {
  const target = cameraTarget.value
  showCameraModal.value = false

  try {
    if (typeof payload !== 'string') {
      alert('Format capture tidak dikenali. Gunakan mode foto.')
      closeCameraModal()
      return
    }

    const rawFile = dataUrlToFile(payload)
    const index = Number(target)
    let meta = pendingLocation.value
    pendingLocation.value = null
    if (!meta) {
      locationBusy.value = true
      try {
        meta = await resolveLocationTag()
      } catch {
        alert('Lokasi GPS wajib aktif untuk evidence perangkat.')
        locationBusy.value = false
        cameraTarget.value = null
        return
      }
      locationBusy.value = false
    }

    ensureItemMedia(index)
    const stamped = await stampPhotoWithTag(rawFile, meta)
    itemMedia[index].files.push(stamped)
    itemMedia[index].previews.push(filePreview(stamped))
    itemMedia[index].metas.push({ ...meta })
    syncItemEvidenceForm(index)
  } catch (e) {
    console.error(e)
    alert('Gagal memproses foto dari kamera.')
  } finally {
    cameraTarget.value = null
  }
}

function ensureItemMedia(index) {
  while (itemMedia.length <= index) {
    itemMedia.push({ files: [], previews: [], metas: [] })
  }
}

function syncItemEvidenceForm(index) {
  form.item_evidences[index] = [...itemMedia[index].files]
  form.item_evidence_meta[index] = itemMedia[index].metas.map((m) => ({
    latitude: m.latitude,
    longitude: m.longitude,
    address: m.address,
    maps_url: m.maps_url,
    captured_at: m.captured_at,
  }))
}

function removeItemNew(index, pIdx) {
  const preview = itemMedia[index]?.previews?.[pIdx]
  if (preview?.url) URL.revokeObjectURL(preview.url)
  itemMedia[index].files.splice(pIdx, 1)
  itemMedia[index].previews.splice(pIdx, 1)
  itemMedia[index].metas.splice(pIdx, 1)
  syncItemEvidenceForm(index)
}

function removeWaNew(pIdx) {
  const preview = waPreviews.value[pIdx]
  if (preview?.url) URL.revokeObjectURL(preview.url)
  form.wa_screenshots.splice(pIdx, 1)
  waPreviews.value.splice(pIdx, 1)
}

function markRemove(id) {
  if (!form.remove_evidence_ids.includes(id)) {
    form.remove_evidence_ids.push(id)
  }
}

const ticketQuery = ref('')
const ticketResults = ref([])
const selectedTicketLabel = ref(
  initialTicket ? `${initialTicket.ticket_number} — ${initialTicket.title}` : ''
)
let ticketTimer = null

watch(() => form.source_type, (val) => {
  if (val !== 'ticket') {
    form.ticket_id = null
    selectedTicketLabel.value = ''
    ticketResults.value = []
  }
})

function onTicketSearch() {
  clearTimeout(ticketTimer)
  ticketTimer = setTimeout(async () => {
    const q = ticketQuery.value.trim()
    if (q.length < 2) {
      ticketResults.value = []
      return
    }
    try {
      const { data } = await axios.get(route('it-work-reports.search-tickets'), {
        params: { q, outlet_id: form.outlet_id || undefined },
      })
      ticketResults.value = data.data || []
    } catch {
      ticketResults.value = []
    }
  }, 300)
}

function selectTicket(t) {
  form.ticket_id = t.id
  selectedTicketLabel.value = t.label || `${t.ticket_number} — ${t.title}`
  if (t.outlet_id && !form.outlet_id) form.outlet_id = t.outlet_id
  ticketResults.value = []
  ticketQuery.value = ''
}

function addItem() {
  form.items.push(emptyItem())
  itemMedia.push({ files: [], previews: [], metas: [] })
  form.item_evidences.push([])
  form.item_evidence_meta.push([])
}

function removeItem(index) {
  ;(itemMedia[index]?.previews || []).forEach((p) => p.url && URL.revokeObjectURL(p.url))
  form.items.splice(index, 1)
  itemMedia.splice(index, 1)
  form.item_evidences.splice(index, 1)
  form.item_evidence_meta.splice(index, 1)
}

function submit(doSubmit) {
  syncStartTime()
  syncEndTime()
  normalizeWaTime()
  syncWaReportedAt()

  if (doSubmit) {
    for (let i = 0; i < form.items.length; i++) {
      const item = form.items[i]
      if (item.device_type === 'laptop') {
        if (!String(item.laptop_user_name || '').trim()) {
          alert(`Perangkat #${i + 1}: nama pengguna laptop wajib diisi.`)
          return
        }
        if (!String(item.identifier || '').trim()) {
          alert(`Perangkat #${i + 1}: serial laptop wajib diisi.`)
          return
        }
      }
      const newCount = itemMedia[i]?.files?.length || 0
      const oldCount = existingItemEvidences(item.id).length
      if (newCount + oldCount < 1) {
        alert(`Perangkat #${i + 1}: ambil minimal 1 evidence dari kamera.`)
        return
      }
    }
  }

  form.submit = doSubmit ? 1 : 0
  form.item_evidences = itemMedia.map((m) => [...m.files])
  form.item_evidence_meta = itemMedia.map((m) =>
    m.metas.map((meta) => ({
      latitude: meta.latitude,
      longitude: meta.longitude,
      address: meta.address,
      maps_url: meta.maps_url,
      captured_at: meta.captured_at,
    }))
  )
  form.transform((data) => ({
    ...data,
    items: data.items.map(({ _key, ...rest }) => rest),
    _method: isEdit.value ? 'put' : undefined,
  }))
  const opts = { forceFormData: true, preserveScroll: true }
  if (isEdit.value) {
    form.post(route('it-work-reports.update', props.record.id), opts)
  } else {
    form.post(route('it-work-reports.store'), opts)
  }
}

onBeforeUnmount(() => {
  itemMedia.forEach((m) => m.previews.forEach((p) => p.url && URL.revokeObjectURL(p.url)))
  waPreviews.value.forEach((p) => p.url && URL.revokeObjectURL(p.url))
})
</script>
