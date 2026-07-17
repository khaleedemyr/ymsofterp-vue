<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  rows: Array,
  outlets: Array,
  divisions: Array,
  jabatan: Array,
  filter: Object,
  period: Object,
})

const outletId = ref(props.filter?.outlet_id || '')
const selectedDivisions = ref(
  (props.filter?.division_ids || [])
    .map((id) => props.divisions?.find((d) => d.id === id || d.id === Number(id)))
    .filter(Boolean)
)
const selectedJabatan = ref(
  (props.filter?.jabatan_ids || [])
    .map((id) => props.jabatan?.find((j) => j.id === id || j.id === Number(id)))
    .filter(Boolean)
)
const bulan = ref(props.filter?.bulan || (new Date().getMonth() + 1))
const tahun = ref(props.filter?.tahun || new Date().getFullYear())
const isLoading = ref(false)

const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
const tahunOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const totals = computed(() => {
  const list = props.rows || []
  const totalTelat = list.reduce((s, r) => s + (r.total_telat || 0), 0)
  const totalLembur = list.reduce((s, r) => s + (r.total_lembur || 0), 0)
  const totalEmployees = list.reduce((s, r) => s + (r.employee_count || 0), 0)
  return {
    employee_count: totalEmployees,
    total_telat: totalTelat,
    average_telat_per_person: totalEmployees > 0 ? (totalTelat / totalEmployees).toFixed(2) : '0',
    total_lembur: totalLembur,
    average_lembur_per_person: totalEmployees > 0 ? (totalLembur / totalEmployees).toFixed(2) : '0',
  }
})

function applyFilter() {
  isLoading.value = true
  router.get('/attendance-report/outlet-summary', {
    outlet_id: outletId.value || '',
    division_ids: selectedDivisions.value.map((d) => d.id),
    jabatan_ids: selectedJabatan.value.map((j) => j.id),
    bulan: bulan.value,
    tahun: tahun.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => {
      isLoading.value = false
    },
  })
}

function exportExcel() {
  const params = new URLSearchParams({
    bulan: String(bulan.value),
    tahun: String(tahun.value),
  })
  if (outletId.value) params.set('outlet_id', outletId.value)
  selectedDivisions.value.forEach((d) => params.append('division_ids[]', d.id))
  selectedJabatan.value.forEach((j) => params.append('jabatan_ids[]', j.id))
  window.location.href = `/attendance-report/outlet-summary/export?${params.toString()}`
}

function openEmployeeSummary(outletIdParam) {
  const params = new URLSearchParams({
    outlet_id: outletIdParam,
    bulan: bulan.value,
    tahun: tahun.value,
  })
  selectedDivisions.value.forEach((d) => params.append('division_ids[]', d.id))
  // keep single division_id for older employee-summary filter
  if (selectedDivisions.value.length === 1) {
    params.set('division_id', selectedDivisions.value[0].id)
  }
  selectedJabatan.value.forEach((j) => params.append('jabatan_ids[]', j.id))
  window.open(`/attendance-report/employee-summary?${params.toString()}`, '_blank')
}
</script>

<template>
  <AppLayout title="Attendance per Outlet">
    <div class="max-w-6xl mx-auto px-2 md:px-0 py-8">
      <div class="text-2xl font-bold text-gray-800 mb-2">Attendance Summary per Outlet</div>
      <div v-if="period" class="text-sm text-gray-500 mb-6">Periode: {{ period.start }} s.d. {{ period.end }}</div>
      <div v-else class="text-sm text-gray-500 mb-6">Pilih filter dan klik "Tampilkan" untuk melihat data</div>

      <div class="flex flex-wrap gap-2 mb-4">
        <a href="/attendance-report" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-xl shadow flex items-center gap-2">
          <i class="fa fa-calendar"></i> Detail Report
        </a>
        <a href="/attendance-report/employee-summary" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl shadow flex items-center gap-2">
          <i class="fa fa-users"></i> Employee Summary
        </a>
      </div>

      <div class="bg-white rounded-2xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-4 items-end">
          <div class="xl:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select v-model="outletId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="xl:col-span-3 filter-multiselect">
            <label class="block text-sm font-medium text-gray-700 mb-1">Divisi (multi, bisa dicari)</label>
            <Multiselect
              v-model="selectedDivisions"
              :options="divisions"
              :multiple="true"
              :searchable="true"
              :internal-search="true"
              :close-on-select="false"
              :show-labels="false"
              :allow-empty="true"
              :preserve-search="true"
              :options-limit="300"
              label="name"
              track-by="id"
              placeholder="Ketik untuk cari divisi..."
              select-label=""
              selected-label=""
              deselect-label=""
            />
          </div>
          <div class="xl:col-span-3 filter-multiselect">
            <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan (multi, bisa dicari)</label>
            <Multiselect
              v-model="selectedJabatan"
              :options="jabatan"
              :multiple="true"
              :searchable="true"
              :internal-search="true"
              :close-on-select="false"
              :show-labels="false"
              :allow-empty="true"
              :preserve-search="true"
              :options-limit="500"
              label="name"
              track-by="id"
              placeholder="Ketik untuk cari jabatan..."
              select-label=""
              selected-label=""
              deselect-label=""
            />
          </div>
          <div class="xl:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
            <select v-model="bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option v-for="(m, idx) in monthNames" :key="idx+1" :value="idx+1">{{ m }}</option>
            </select>
          </div>
          <div class="xl:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
            <select v-model="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option v-for="t in tahunOptions" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div class="xl:col-span-2 flex flex-wrap gap-2">
            <button
              @click="applyFilter"
              :disabled="isLoading"
              class="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed text-white px-5 py-2 rounded-lg font-medium transition-all duration-200 flex items-center gap-2"
            >
              <i v-if="isLoading" class="fa fa-spinner fa-spin"></i>
              <i v-else class="fa fa-search"></i>
              {{ isLoading ? 'Loading...' : 'Tampilkan' }}
            </button>
            <button
              @click="exportExcel"
              :disabled="isLoading"
              class="bg-green-600 hover:bg-green-700 disabled:bg-green-400 disabled:cursor-not-allowed text-white px-5 py-2 rounded-lg font-medium transition-all duration-200 flex items-center gap-2"
            >
              <i class="fa fa-file-excel"></i>
              Export
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow overflow-x-auto relative">
        <div v-if="isLoading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
          <div class="text-center">
            <i class="fa fa-spinner fa-spin text-4xl text-blue-600 mb-2"></i>
            <div class="text-gray-600 font-medium">Memuat data...</div>
          </div>
        </div>

        <table class="w-full min-w-[900px]">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Karyawan</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Total Telat (menit)</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Avg Telat / Orang</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Total Lembur (jam)</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Avg Lembur / Orang</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.outlet_id || r.nama_outlet" class="odd:bg-blue-50">
              <td class="px-4 py-2 font-medium">{{ r.nama_outlet }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ r.employee_count || 0 }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ r.total_telat }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ r.average_telat_per_person || 0 }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ r.total_lembur }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ r.average_lembur_per_person || 0 }}</td>
              <td class="px-4 py-2 text-center">
                <button
                  @click="openEmployeeSummary(r.outlet_id)"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition-all duration-200 inline-flex items-center gap-1"
                  :title="`Lihat Employee Summary untuk ${r.nama_outlet}`"
                >
                  <i class="fa fa-users text-xs"></i>
                  Detail
                </button>
              </td>
            </tr>
            <tr v-if="!rows || rows.length === 0">
              <td colspan="7" class="text-center py-8 text-gray-400">
                <div v-if="!period" class="text-lg">
                  <i class="fa fa-filter text-4xl mb-2 text-gray-300"></i>
                  <div>Pilih filter dan klik "Tampilkan" untuk melihat data</div>
                </div>
                <div v-else>Tidak ada data untuk periode yang dipilih</div>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="rows && rows.length > 0">
            <tr class="bg-blue-100 font-bold">
              <td class="px-4 py-2 text-right">TOTAL</td>
              <td class="px-4 py-2 text-right font-mono">{{ totals.employee_count }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ totals.total_telat }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ totals.average_telat_per_person }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ totals.total_lembur }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ totals.average_lembur_per_person }}</td>
              <td class="px-4 py-2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
table { width: 100%; border-collapse: collapse; }
th, td { border-bottom: 1px solid #e5e7eb; }

.filter-multiselect :deep(.multiselect) {
  min-height: 42px;
  min-width: 100%;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
}

.filter-multiselect :deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}

.filter-multiselect :deep(.multiselect__tags) {
  min-height: 42px;
  padding: 6px 40px 0 8px;
  border: none;
  background: transparent;
}

.filter-multiselect :deep(.multiselect__tag) {
  max-width: 100%;
  white-space: normal;
  word-break: break-word;
  line-height: 1.3;
  padding-top: 4px;
  padding-bottom: 4px;
}

.filter-multiselect :deep(.multiselect__option) {
  white-space: normal;
  word-break: break-word;
  line-height: 1.35;
  padding: 10px 14px;
  min-height: auto;
}

.filter-multiselect :deep(.multiselect__content-wrapper) {
  min-width: 100%;
  width: max(100%, 320px);
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
  z-index: 50;
}

.filter-multiselect :deep(.multiselect__input),
.filter-multiselect :deep(.multiselect__placeholder) {
  font-size: 0.875rem;
  margin-bottom: 6px;
}
</style>
