<template>
  <Head title="Tracking Nomor Seri" />
  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
          <div class="p-3 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg">
            <i class="fa-solid fa-barcode text-white text-xl"></i>
          </div>
          <span>Tracking Nomor Seri</span>
        </h1>
        <p class="text-gray-600 ml-16">Lacak serial berdasarkan dokumen sumber atau nomor seri langsung</p>
      </div>

      <div class="flex gap-2 mb-6 border-b border-gray-200">
        <button
          type="button"
          class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors"
          :class="activeTab === 'document' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'document'"
        >
          <i class="fa-solid fa-file-lines mr-1"></i> Per Dokumen
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors"
          :class="activeTab === 'serial' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'serial'"
        >
          <i class="fa-solid fa-magnifying-glass mr-1"></i> Per Nomor Seri
        </button>
      </div>

      <!-- Tab: Per Dokumen -->
      <div v-show="activeTab === 'document'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Sumber Dokumen</label>
              <select v-model="docFilters.source_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">-- Pilih --</option>
                <option v-for="st in sourceTypes" :key="st.value" :value="st.value">{{ st.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Cari No. Dokumen</label>
              <input v-model="docFilters.search" type="text" placeholder="GR-..., batch..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @keyup.enter="searchDocuments(1)" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Dari Tanggal</label>
              <input v-model="docFilters.date_from" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Sampai Tanggal</label>
              <input v-model="docFilters.date_to" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="mt-4 flex gap-2">
            <button type="button" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50" :disabled="!docFilters.source_type || docLoading" @click="searchDocuments(1)">
              <i class="fa-solid fa-search mr-1"></i> Cari Dokumen
            </button>
            <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200" @click="resetDocSearch">Reset</button>
          </div>
        </div>

        <div v-if="docResults.length" class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
          <table class="w-full min-w-full text-sm">
            <thead class="bg-indigo-600 text-white">
              <tr>
                <th class="px-4 py-3 text-left">No. Dokumen</th>
                <th class="px-4 py-3 text-left">Tanggal</th>
                <th class="px-4 py-3 text-right">Jumlah Serial</th>
                <th class="px-4 py-3 text-left">Generate Terakhir</th>
                <th class="px-4 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in docResults" :key="`${row.source_type}-${row.source_id}`" class="hover:bg-indigo-50">
                <td class="px-4 py-3">
                  <a v-if="row.document_url" :href="row.document_url" class="font-mono font-semibold text-indigo-700 hover:underline" target="_blank">{{ row.document_number }}</a>
                  <span v-else class="font-mono">{{ row.document_number }}</span>
                </td>
                <td class="px-4 py-3">{{ formatDate(row.document_date) }}</td>
                <td class="px-4 py-3 text-right font-semibold">{{ row.serial_count }}</td>
                <td class="px-4 py-3">{{ formatDateTime(row.last_generated_at) }}</td>
                <td class="px-4 py-3 text-center">
                  <button type="button" class="px-3 py-1 text-xs rounded-lg bg-indigo-100 text-indigo-700 hover:bg-indigo-200 font-semibold" @click="openDocumentSerials(row)">
                    Lihat Serial
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="docPagination.last_page > 1" class="flex justify-center gap-2 p-4 border-t">
            <button type="button" class="px-3 py-1 text-sm rounded border disabled:opacity-40" :disabled="docPagination.current_page <= 1" @click="searchDocuments(docPagination.current_page - 1)">Prev</button>
            <span class="px-3 py-1 text-sm text-gray-600">{{ docPagination.current_page }} / {{ docPagination.last_page }}</span>
            <button type="button" class="px-3 py-1 text-sm rounded border disabled:opacity-40" :disabled="docPagination.current_page >= docPagination.last_page" @click="searchDocuments(docPagination.current_page + 1)">Next</button>
          </div>
        </div>
        <p v-else-if="docSearched && !docLoading" class="text-center text-gray-500 py-8">Tidak ada dokumen ditemukan.</p>
      </div>

      <!-- Tab: Per Nomor Seri -->
      <div v-show="activeTab === 'serial'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Nomor Seri</label>
          <div class="flex flex-col sm:flex-row gap-3">
            <input
              v-model="serialQuery"
              type="text"
              placeholder="Scan atau ketik nomor seri..."
              class="flex-1 border border-gray-300 rounded-lg px-4 py-3 font-mono text-lg"
              @keyup.enter="lookupSerial"
            />
            <button type="button" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 disabled:opacity-50" :disabled="serialQuery.length < 2 || serialLoading" @click="lookupSerial">
              <i class="fa-solid fa-route mr-1"></i> Lacak
            </button>
          </div>
        </div>

        <div v-if="serialSuggestions.length" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <p class="text-sm font-semibold text-amber-800 mb-2">Serial tidak exact match. Pilih dari daftar:</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="s in serialSuggestions"
              :key="s.id"
              type="button"
              class="px-3 py-1 bg-white border border-amber-300 rounded-lg font-mono text-sm hover:bg-amber-100"
              @click="serialQuery = s.serial_number; lookupSerial()"
            >
              {{ s.serial_number }} <span class="text-gray-500 text-xs">({{ s.item_name }})</span>
            </button>
          </div>
        </div>

        <div v-if="serialDetail" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
          <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
              <p class="text-sm text-gray-500 mb-1">Nomor Seri</p>
              <p class="text-2xl font-mono font-bold text-indigo-700">{{ serialDetail.serial_number }}</p>
            </div>
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold" :class="statusBadgeClass(serialDetail.status?.color)">
              {{ serialDetail.status?.label }}
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 text-sm">
            <div><span class="text-gray-500">Item:</span> <strong>{{ serialDetail.item_name }}</strong> <span v-if="serialDetail.item_sku" class="text-gray-400">({{ serialDetail.item_sku }})</span></div>
            <div><span class="text-gray-500">Unit:</span> <strong>{{ serialDetail.unit_name || '-' }}</strong></div>
            <div><span class="text-gray-500">Gudang:</span> <strong>{{ serialDetail.warehouse_name || '-' }}</strong></div>
            <div>
              <span class="text-gray-500">Sumber:</span>
              <strong>{{ serialDetail.source_type_label }}</strong>
              <a v-if="serialDetail.source_document_url" :href="serialDetail.source_document_url" class="ml-1 text-indigo-600 hover:underline font-mono" target="_blank">{{ serialDetail.source_document_number }}</a>
              <span v-else-if="serialDetail.source_document_number" class="ml-1 font-mono">{{ serialDetail.source_document_number }}</span>
            </div>
            <div><span class="text-gray-500">Generate:</span> <strong>{{ formatDateTime(serialDetail.generated_at) }}</strong></div>
            <div v-if="serialDetail.ref_gr_number"><span class="text-gray-500">Ref GR:</span> <strong class="font-mono">{{ serialDetail.ref_gr_number }}</strong></div>
          </div>

          <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-timeline text-indigo-500"></i> Riwayat Pergerakan
          </h3>
          <div v-if="serialTimeline.length" class="relative border-l-2 border-indigo-200 ml-3 space-y-6 pl-8">
            <div v-for="(ev, idx) in serialTimeline" :key="idx" class="relative">
              <span class="absolute -left-[2.15rem] top-1 w-4 h-4 rounded-full bg-indigo-500 ring-4 ring-white"></span>
              <p class="text-xs text-gray-500">{{ formatDateTime(ev.at) }}</p>
              <p class="font-semibold text-gray-900">{{ ev.label }}</p>
              <p v-if="ev.document_label" class="text-sm text-gray-700 mt-0.5">
                {{ ev.document_label }}
                <a v-if="ev.document_url" :href="ev.document_url" class="text-indigo-600 hover:underline font-mono ml-1" target="_blank">{{ ev.document_number }}</a>
                <span v-else-if="ev.document_number" class="font-mono ml-1">{{ ev.document_number }}</span>
              </p>
              <p v-if="ev.notes" class="text-sm text-gray-500 mt-0.5">{{ ev.notes }}</p>
              <p v-if="ev.moved_by_name" class="text-xs text-gray-400 mt-0.5">Oleh: {{ ev.moved_by_name }}</p>
            </div>
          </div>
          <p v-else class="text-gray-500 text-sm">Belum ada riwayat pergerakan tercatat.</p>
        </div>
      </div>

      <!-- Modal serial per dokumen -->
      <div v-if="selectedDoc" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="closeDocumentSerials">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col">
          <div class="p-5 border-b flex justify-between items-center">
            <div>
              <h3 class="text-lg font-bold text-gray-900">Serial — {{ selectedDoc.document_number }}</h3>
              <p class="text-sm text-gray-500">{{ selectedDoc.source_label }} · {{ selectedDoc.serial_count }} serial</p>
            </div>
            <button type="button" class="p-2 hover:bg-gray-100 rounded-lg" @click="closeDocumentSerials"><i class="fa fa-times"></i></button>
          </div>
          <div class="p-4 border-b">
            <input v-model="docSerialSearch" type="text" placeholder="Filter nomor seri..." class="w-full border rounded-lg px-3 py-2 text-sm font-mono" @input="debouncedLoadDocSerials" />
          </div>
          <div class="overflow-auto flex-1 p-4">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 sticky top-0">
                <tr>
                  <th class="px-3 py-2 text-left">Serial</th>
                  <th class="px-3 py-2 text-left">Item</th>
                  <th class="px-3 py-2 text-left">Unit</th>
                  <th class="px-3 py-2 text-left">Status</th>
                  <th class="px-3 py-2 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="s in docSerialList" :key="s.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-mono font-semibold text-indigo-700">{{ s.serial_number }}</td>
                  <td class="px-3 py-2">{{ s.item_name }}</td>
                  <td class="px-3 py-2">{{ s.unit_name }}</td>
                  <td class="px-3 py-2">
                    <span v-if="s.is_out" class="text-amber-700 text-xs font-semibold">Keluar</span>
                    <span v-else-if="s.is_received" class="text-green-700 text-xs font-semibold">Di outlet</span>
                    <span v-else class="text-blue-700 text-xs font-semibold">Gudang</span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <button type="button" class="text-xs text-indigo-600 hover:underline font-semibold" @click="trackFromList(s.serial_number)">Lacak</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="!docSerialLoading && !docSerialList.length" class="text-center text-gray-500 py-6">Tidak ada serial.</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  sourceTypes: { type: Array, default: () => [] },
})

const activeTab = ref('document')

const docFilters = reactive({
  source_type: '',
  search: '',
  date_from: '',
  date_to: '',
})
const docResults = ref([])
const docPagination = reactive({ current_page: 1, last_page: 1, per_page: 20 })
const docLoading = ref(false)
const docSearched = ref(false)

const selectedDoc = ref(null)
const docSerialList = ref([])
const docSerialSearch = ref('')
const docSerialLoading = ref(false)
let docSerialDebounce = null

const serialQuery = ref('')
const serialLoading = ref(false)
const serialDetail = ref(null)
const serialTimeline = ref([])
const serialSuggestions = ref([])

const searchDocuments = async (page = 1) => {
  if (!docFilters.source_type) return
  docLoading.value = true
  docSearched.value = true
  try {
    const { data } = await axios.get('/api/serial-tracking/documents', {
      params: { ...docFilters, page, per_page: 20 },
    })
    docResults.value = data.data || []
    docPagination.current_page = data.current_page || 1
    docPagination.last_page = data.last_page || 1
  } catch {
    docResults.value = []
  } finally {
    docLoading.value = false
  }
}

const resetDocSearch = () => {
  docFilters.search = ''
  docFilters.date_from = ''
  docFilters.date_to = ''
  docResults.value = []
  docSearched.value = false
}

const openDocumentSerials = (row) => {
  selectedDoc.value = row
  docSerialSearch.value = ''
  loadDocSerials()
}

const closeDocumentSerials = () => {
  selectedDoc.value = null
  docSerialList.value = []
}

const loadDocSerials = async () => {
  if (!selectedDoc.value) return
  docSerialLoading.value = true
  try {
    const { data } = await axios.get('/api/serial-tracking/document-serials', {
      params: {
        source_type: selectedDoc.value.source_type,
        source_id: selectedDoc.value.source_id,
        search: docSerialSearch.value || undefined,
        per_page: 100,
      },
    })
    docSerialList.value = data.data || []
  } catch {
    docSerialList.value = []
  } finally {
    docSerialLoading.value = false
  }
}

const debouncedLoadDocSerials = () => {
  clearTimeout(docSerialDebounce)
  docSerialDebounce = setTimeout(loadDocSerials, 350)
}

const lookupSerial = async () => {
  const q = serialQuery.value.trim()
  if (q.length < 2) return
  serialLoading.value = true
  serialDetail.value = null
  serialTimeline.value = []
  serialSuggestions.value = []
  try {
    const { data } = await axios.get('/api/serial-tracking/lookup', { params: { serial_number: q } })
    serialDetail.value = data.serial
    serialTimeline.value = data.timeline || []
  } catch (err) {
    if (err?.response?.status === 404 && err?.response?.data?.suggestions?.length) {
      serialSuggestions.value = err.response.data.suggestions
    }
  } finally {
    serialLoading.value = false
  }
}

const trackFromList = (sn) => {
  closeDocumentSerials()
  activeTab.value = 'serial'
  serialQuery.value = sn
  lookupSerial()
}

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('id-ID') : '-')
const formatDateTime = (d) => (d ? new Date(d).toLocaleString('id-ID') : '-')

const statusBadgeClass = (color) => {
  const map = {
    blue: 'bg-blue-100 text-blue-800',
    green: 'bg-green-100 text-green-800',
    amber: 'bg-amber-100 text-amber-800',
    orange: 'bg-orange-100 text-orange-800',
    red: 'bg-red-100 text-red-800',
    purple: 'bg-purple-100 text-purple-800',
    indigo: 'bg-indigo-100 text-indigo-800',
  }
  return map[color] || 'bg-gray-100 text-gray-800'
}
</script>
