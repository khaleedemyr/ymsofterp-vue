<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  rows: Array,
  outlets: Array,
  divisions: Array,
  filter: Object,
  period: Object,
})

const outletId = ref(props.filter?.outlet_id || '')
const divisionId = ref(props.filter?.division_id || '')
const bulan = ref(props.filter?.bulan || (new Date().getMonth() + 1))
const tahun = ref(props.filter?.tahun || new Date().getFullYear())

const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
const tahunOptions = Array.from({length: 5}, (_,i) => new Date().getFullYear() - i)

function applyFilter() {
  router.get('/attendance-report/outlet-summary', {
    outlet_id: outletId.value || '',
    division_id: divisionId.value || '',
    bulan: bulan.value,
    tahun: tahun.value,
  }, { preserveState: true, replace: true })
}
</script>

<template>
  <AppLayout title="Attendance per Outlet">
    <div class="max-w-5xl mx-auto px-2 md:px-0 py-8">
      <div class="text-2xl font-bold text-gray-800 mb-2">Attendance Summary per Outlet</div>
      <div class="text-sm text-gray-500 mb-6">Periode: {{ period?.start }} s.d. {{ period?.end }}</div>
      
      <!-- Navigation Links -->
      <div class="flex gap-2 mb-4">
        <a href="/attendance-report" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-xl shadow flex items-center gap-2">
          <i class="fa fa-calendar"></i> Detail Report
        </a>
        <a href="/attendance-report/employee-summary" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl shadow flex items-center gap-2">
          <i class="fa fa-users"></i> Employee Summary
        </a>
      </div>

      <div class="flex flex-col md:flex-row md:items-end gap-4 mb-6">
        <div class="flex-1 min-w-[180px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
          <select v-model="outletId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
          <select v-model="divisionId" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Divisi</option>
            <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[120px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
          <select v-model="bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="(m, idx) in monthNames" :key="idx+1" :value="idx+1">{{ m }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[100px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
          <select v-model="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option v-for="t in tahunOptions" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div>
          <button @click="applyFilter" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-200">Tampilkan</button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="w-full">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Total Telat (menit)</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Total Lembur (jam)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.nama_outlet" class="odd:bg-blue-50">
              <td class="px-4 py-2">{{ r.nama_outlet }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ r.total_telat }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ r.total_lembur }}</td>
            </tr>
            <tr v-if="!rows || rows.length === 0">
              <td colspan="3" class="text-center py-8 text-gray-400">Tidak ada data</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-blue-100 font-bold">
              <td class="px-4 py-2 text-right">TOTAL</td>
              <td class="px-4 py-2 text-right font-mono">{{ (rows||[]).reduce((s,r)=>s+(r.total_telat||0),0) }}</td>
              <td class="px-4 py-2 text-right font-mono">{{ (rows||[]).reduce((s,r)=>s+(r.total_lembur||0),0) }}</td>
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
</style>


