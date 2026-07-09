<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-calculator text-blue-600"></i>
            {{ isEdit ? 'Edit' : 'Tambah' }} Manual COGS, Deviation & Catcost
          </h1>
          <p class="text-sm text-gray-500 mt-1">Pilih periode, lalu isi data per outlet</p>
        </div>
        <Link :href="route('manual-cogs-deviation-catcost.index')" class="text-gray-600 hover:text-gray-800">
          <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="bg-white rounded-xl shadow p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Periode</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-xl">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Bulan <span class="text-red-500">*</span></label>
              <select
                v-model="form.month"
                required
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="">Pilih Bulan</option>
                <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
              <p v-if="form.errors.month" class="text-sm text-red-600 mt-1">{{ form.errors.month }}</p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun <span class="text-red-500">*</span></label>
              <select
                v-model="form.year"
                required
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="">Pilih Tahun</option>
                <option v-for="y in yearOptions" :key="y.value" :value="y.value">{{ y.label }}</option>
              </select>
              <p v-if="form.errors.year" class="text-sm text-red-600 mt-1">{{ form.errors.year }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
          <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Data per Outlet</h2>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                @click="downloadTemplate"
                :disabled="downloadingTemplate"
                class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-sm disabled:opacity-50"
              >
                <i class="fa-solid fa-download mr-1"></i>
                {{ downloadingTemplate ? 'Mengunduh...' : 'Download Template' }}
              </button>
              <button
                type="button"
                @click="triggerImportFile"
                :disabled="uploadingExcel"
                class="px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm disabled:opacity-50"
              >
                <i class="fa-solid fa-file-excel mr-1"></i>
                {{ uploadingExcel ? 'Mengupload...' : 'Upload Excel' }}
              </button>
              <input ref="importFileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="importFromExcel" />
              <button
                type="button"
                @click="addRow"
                class="px-3 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 text-sm"
              >
                <i class="fa-solid fa-plus mr-1"></i> Tambah Outlet
              </button>
            </div>
          </div>

          <p v-if="importMessage" class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 mb-3">
            {{ importMessage }}
          </p>
          <p v-if="importError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3 whitespace-pre-line">
            {{ importError }}
          </p>

          <p v-if="form.errors.items" class="text-sm text-red-600 mb-3">{{ form.errors.items }}</p>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
              <thead class="bg-gray-800 text-white">
                <tr>
                  <th class="px-3 py-2 text-left w-8">#</th>
                  <th class="px-3 py-2 text-left min-w-[200px]">Outlet</th>
                  <th class="px-3 py-2 text-right min-w-[120px]">Nilai COGS</th>
                  <th class="px-3 py-2 text-right min-w-[100px]">% COGS</th>
                  <th class="px-3 py-2 text-right min-w-[120px]">Nilai Deviation</th>
                  <th class="px-3 py-2 text-right min-w-[100px]">% Deviation</th>
                  <th class="px-3 py-2 text-right min-w-[120px]">Nilai Catcost</th>
                  <th class="px-3 py-2 text-right min-w-[100px]">% Catcost</th>
                  <th class="px-3 py-2 text-center w-16">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="form.items.length === 0">
                  <td colspan="9" class="px-3 py-6 text-center text-gray-500">
                    Belum ada outlet. Klik "Tambah Outlet".
                  </td>
                </tr>
                <tr v-for="(row, idx) in form.items" :key="row._key" class="border-t align-top">
                  <td class="px-3 py-3 text-gray-500">{{ idx + 1 }}</td>
                  <td class="px-3 py-3">
                    <select
                      v-model="row.outlet_id"
                      required
                      class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                      <option value="">Pilih Outlet</option>
                      <option
                        v-for="outlet in availableOutletsForRow(idx)"
                        :key="outlet.id_outlet"
                        :value="outlet.id_outlet"
                      >
                        {{ outlet.nama_outlet }}
                      </option>
                    </select>
                  </td>
                  <td class="px-3 py-3">
                    <input v-model="row.cogs_value" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-right text-sm" placeholder="0" />
                  </td>
                  <td class="px-3 py-3">
                    <input v-model="row.cogs_percent" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-right text-sm" placeholder="0" />
                  </td>
                  <td class="px-3 py-3">
                    <input v-model="row.deviation_value" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-right text-sm" placeholder="0" />
                  </td>
                  <td class="px-3 py-3">
                    <input v-model="row.deviation_percent" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-right text-sm" placeholder="0" />
                  </td>
                  <td class="px-3 py-3">
                    <input v-model="row.catcost_value" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-right text-sm" placeholder="0" />
                  </td>
                  <td class="px-3 py-3">
                    <input v-model="row.catcost_percent" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 text-right text-sm" placeholder="0" />
                  </td>
                  <td class="px-3 py-3 text-center">
                    <button
                      type="button"
                      @click="removeRow(idx)"
                      class="px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200"
                      title="Hapus baris"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link
            :href="route('manual-cogs-deviation-catcost.index')"
            class="px-5 py-2.5 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
          >
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-5 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
          >
            <i v-if="form.processing" class="fas fa-spinner fa-spin mr-1"></i>
            {{ isEdit ? 'Simpan Perubahan' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  record: { type: Object, default: null },
  outlets: { type: Array, default: () => [] },
  monthOptions: { type: Array, default: () => [] },
  yearOptions: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.record?.id)
const importFileInput = ref(null)
const downloadingTemplate = ref(false)
const uploadingExcel = ref(false)
const importMessage = ref('')
const importError = ref('')

let rowKey = 0
function makeRow(item = null) {
  return {
    _key: ++rowKey,
    outlet_id: item?.outlet_id ?? '',
    cogs_value: item?.cogs_value ?? '',
    cogs_percent: item?.cogs_percent ?? '',
    deviation_value: item?.deviation_value ?? '',
    deviation_percent: item?.deviation_percent ?? '',
    catcost_value: item?.catcost_value ?? '',
    catcost_percent: item?.catcost_percent ?? '',
  }
}

const form = useForm({
  month: props.record?.month ?? '',
  year: props.record?.year ?? '',
  items: props.record?.items?.length
    ? props.record.items.map((item) => makeRow(item))
    : [makeRow()],
})

function addRow() {
  form.items.push(makeRow())
}

function removeRow(idx) {
  if (form.items.length <= 1) return
  form.items.splice(idx, 1)
}

function availableOutletsForRow(currentIdx) {
  const selectedIds = form.items
    .map((row, idx) => (idx !== currentIdx && row.outlet_id ? String(row.outlet_id) : null))
    .filter(Boolean)

  return props.outlets.filter((outlet) => !selectedIds.includes(String(outlet.id_outlet)))
}

async function downloadTemplate() {
  downloadingTemplate.value = true
  importError.value = ''
  try {
    const response = await axios.get(route('manual-cogs-deviation-catcost.template.download'), {
      responseType: 'blob',
    })
    const contentDisposition = response.headers['content-disposition'] || ''
    const matched = contentDisposition.match(/filename="?([^"]+)"?/)
    const fileName = matched?.[1] || 'manual_cogs_deviation_catcost_template.xlsx'
    const blob = new Blob([response.data], {
      type: response.headers['content-type'] || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', fileName)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    importError.value = error?.response?.data?.message || 'Gagal download template.'
  } finally {
    downloadingTemplate.value = false
  }
}

function triggerImportFile() {
  if (!importFileInput.value) return
  importFileInput.value.value = ''
  importFileInput.value.click()
}

async function importFromExcel(event) {
  const file = event?.target?.files?.[0]
  if (!file) return

  uploadingExcel.value = true
  importMessage.value = ''
  importError.value = ''

  const formData = new FormData()
  formData.append('file', file)

  try {
    const response = await axios.post(route('manual-cogs-deviation-catcost.template.import'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (response.data?.success && Array.isArray(response.data.items) && response.data.items.length > 0) {
      form.items = response.data.items.map((item) => makeRow(item))
      importMessage.value = response.data.message || `${response.data.items.length} outlet berhasil diimport.`
    }
  } catch (error) {
    const data = error?.response?.data
    if (Array.isArray(data?.errors) && data.errors.length > 0) {
      importError.value = data.errors.join('\n')
    } else {
      importError.value = data?.message || 'Import gagal. Pastikan file sesuai template.'
    }
  } finally {
    uploadingExcel.value = false
    if (event?.target) event.target.value = ''
  }
}

function submit() {
  const payload = {
    month: form.month,
    year: form.year,
    items: form.items
      .filter((row) => row.outlet_id)
      .map((row) => ({
        outlet_id: row.outlet_id,
        cogs_value: row.cogs_value === '' ? 0 : row.cogs_value,
        cogs_percent: row.cogs_percent === '' ? 0 : row.cogs_percent,
        deviation_value: row.deviation_value === '' ? 0 : row.deviation_value,
        deviation_percent: row.deviation_percent === '' ? 0 : row.deviation_percent,
        catcost_value: row.catcost_value === '' ? 0 : row.catcost_value,
        catcost_percent: row.catcost_percent === '' ? 0 : row.catcost_percent,
      })),
  }

  if (isEdit.value) {
    form.transform(() => payload).put(route('manual-cogs-deviation-catcost.update', props.record.id))
  } else {
    form.transform(() => payload).post(route('manual-cogs-deviation-catcost.store'))
  }
}
</script>
