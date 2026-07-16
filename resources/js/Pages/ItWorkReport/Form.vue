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
        <!-- Header -->
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
              <input v-model="form.start_time" type="time" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Jam selesai</label>
              <input v-model="form.end_time" type="time" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
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
              <p v-if="form.errors.source_type" class="text-xs text-red-500 mt-1">{{ form.errors.source_type }}</p>
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

        <!-- Ticket source -->
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
              <div class="text-sm font-medium text-gray-800">
                {{ selectedTicketLabel || 'Belum dipilih' }}
              </div>
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
              <span class="font-semibold text-cyan-700">{{ t.ticket_number }}</span>
              — {{ t.title }}
              <span v-if="t.outlet_name" class="text-xs text-gray-500">({{ t.outlet_name }})</span>
            </li>
          </ul>
        </div>

        <!-- WhatsApp source -->
        <div v-if="form.source_type === 'whatsapp'" class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Sumber WhatsApp</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Nama kontak *</label>
              <input v-model="form.wa_contact_name" type="text" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
              <p v-if="form.errors.wa_contact_name" class="text-xs text-red-500 mt-1">{{ form.errors.wa_contact_name }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">No. HP</label>
              <input v-model="form.wa_phone" type="text" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Waktu lapor</label>
              <input v-model="form.wa_reported_at" type="datetime-local" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Ringkasan chat *</label>
              <textarea v-model="form.wa_summary" rows="2" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
              <p v-if="form.errors.wa_summary" class="text-xs text-red-500 mt-1">{{ form.errors.wa_summary }}</p>
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Screenshot WA (wajib saat submit)</label>
              <input type="file" multiple accept="image/*,.pdf" @change="onFiles($event, 'wa_screenshots')" class="block w-full text-sm" />
              <p v-if="form.errors.wa_screenshots" class="text-xs text-red-500 mt-1">{{ form.errors.wa_screenshots }}</p>
              <div v-if="existingWa.length" class="mt-2 flex flex-wrap gap-2">
                <div v-for="ev in existingWa" :key="ev.id" class="relative border rounded-lg p-2 text-xs">
                  <a :href="ev.url" target="_blank" class="text-cyan-700 hover:underline">{{ ev.original_name || 'file' }}</a>
                  <button type="button" class="ml-2 text-red-500" @click="markRemove(ev.id)">hapus</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Devices -->
        <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
          <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-cyan-50 to-white">
            <div>
              <h2 class="text-lg font-semibold text-gray-800">Perangkat dikerjakan</h2>
              <p class="text-xs text-gray-500">Minimal 1 baris saat submit; scope multi-select per perangkat</p>
            </div>
            <button type="button" @click="addItem" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 text-sm">
              <i class="fa-solid fa-plus"></i> Tambah perangkat
            </button>
          </div>
          <p v-if="form.errors.items" class="px-6 pt-4 text-sm text-red-500">{{ form.errors.items }}</p>

          <div v-for="(item, index) in form.items" :key="index" class="px-6 py-5 border-b last:border-b-0">
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
              <div>
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
                <input v-model="item.notes" type="text" class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500" />
              </div>
            </div>
          </div>
        </div>

        <!-- Work evidence -->
        <div class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-2">Evidence pekerjaan</h2>
          <p class="text-xs text-gray-500 mb-3">Minimal 1 file wajib saat submit (foto sebelum/sesudah, dll.)</p>
          <input type="file" multiple accept="image/*,.pdf" @change="onFiles($event, 'work_evidences')" class="block w-full text-sm" />
          <p v-if="form.errors.work_evidences" class="text-xs text-red-500 mt-1">{{ form.errors.work_evidences }}</p>
          <div v-if="existingWork.length" class="mt-2 flex flex-wrap gap-2">
            <div v-for="ev in existingWork" :key="ev.id" class="relative border rounded-lg p-2 text-xs">
              <a :href="ev.url" target="_blank" class="text-cyan-700 hover:underline">{{ ev.original_name || 'file' }}</a>
              <button type="button" class="ml-2 text-red-500" @click="markRemove(ev.id)">hapus</button>
            </div>
          </div>

          <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Evidence lain (opsional)</label>
            <input type="file" multiple accept="image/*,.pdf" @change="onFiles($event, 'other_evidences')" class="block w-full text-sm" />
            <div v-if="existingOther.length" class="mt-2 flex flex-wrap gap-2">
              <div v-for="ev in existingOther" :key="ev.id" class="relative border rounded-lg p-2 text-xs">
                <a :href="ev.url" target="_blank" class="text-cyan-700 hover:underline">{{ ev.original_name || 'file' }}</a>
                <button type="button" class="ml-2 text-red-500" @click="markRemove(ev.id)">hapus</button>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap gap-3 justify-end">
          <button
            type="button"
            :disabled="form.processing"
            @click="submit(false)"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-gray-100 text-gray-800 hover:bg-gray-200 disabled:opacity-50"
          >
            Simpan Draft
          </button>
          <button
            type="button"
            :disabled="form.processing"
            @click="submit(true)"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 disabled:opacity-50"
          >
            <i class="fa-solid fa-paper-plane"></i>
            Submit
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
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

function emptyItem() {
  return {
    device_type: 'pc',
    device_label: '',
    identifier: '',
    scopes: [],
    notes: '',
    result: '',
  }
}

function mapRecordItems(items) {
  if (!items?.length) return [emptyItem()]
  return items.map((i) => ({
    device_type: i.device_type || 'pc',
    device_label: i.device_label || '',
    identifier: i.identifier || '',
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
  const s = String(value).replace(' ', 'T')
  return s.slice(0, 16)
}

const initialTicket = props.record?.ticket || props.prefillTicket || null

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
  items: mapRecordItems(props.record?.items),
  wa_screenshots: [],
  work_evidences: [],
  other_evidences: [],
  remove_evidence_ids: [],
  submit: 0,
})

const existingEvidences = ref([...(props.record?.evidences || [])])
const existingWa = computed(() => existingEvidences.value.filter((e) => e.kind === 'wa_screenshot' && !form.remove_evidence_ids.includes(e.id)))
const existingWork = computed(() => existingEvidences.value.filter((e) => e.kind === 'work' && !form.remove_evidence_ids.includes(e.id)))
const existingOther = computed(() => existingEvidences.value.filter((e) => e.kind === 'other' && !form.remove_evidence_ids.includes(e.id)))

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
  if (val !== 'whatsapp') {
    form.wa_screenshots = []
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
  if (t.outlet_id && !form.outlet_id) {
    form.outlet_id = t.outlet_id
  }
  ticketResults.value = []
  ticketQuery.value = ''
}

function addItem() {
  form.items.push(emptyItem())
}

function removeItem(index) {
  form.items.splice(index, 1)
}

function onFiles(event, field) {
  form[field] = Array.from(event.target.files || [])
}

function markRemove(id) {
  if (!form.remove_evidence_ids.includes(id)) {
    form.remove_evidence_ids.push(id)
  }
}

function submit(doSubmit) {
  form.submit = doSubmit ? 1 : 0
  const opts = { forceFormData: true, preserveScroll: true }
  if (isEdit.value) {
    form.transform((data) => ({ ...data, _method: 'put' }))
    form.post(route('it-work-reports.update', props.record.id), opts)
  } else {
    form.post(route('it-work-reports.store'), opts)
  }
}
</script>
