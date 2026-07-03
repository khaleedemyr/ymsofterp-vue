<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import VueEasyLightbox from 'vue-easy-lightbox'

const props = defineProps({
  report: Object,
  inspectionStats: Object,
})

const visibleRef = ref(false)
const indexRef = ref(0)
const imgsRef = ref([])

const inspectionTimeText = computed(() => (
  props.report.inspection_time === 'lunch' ? 'Lunch' : 'Dinner'
))

const statusText = computed(() => {
  switch (props.report.status) {
    case 'draft': return 'Draft'
    case 'completed': return 'Completed'
    default: return 'Unknown'
  }
})

const statusColor = computed(() => {
  switch (props.report.status) {
    case 'draft': return 'bg-yellow-100 text-yellow-800'
    case 'completed': return 'bg-green-100 text-green-800'
    default: return 'bg-gray-100 text-gray-800'
  }
})

const progressPercentage = computed(() => {
  const totalAreas = props.report.progress?.length || 0
  const completedAreas = props.report.report_areas?.length || 0
  return totalAreas > 0 ? Math.round((completedAreas / totalAreas) * 100) : 0
})

function formatDateTime(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatCurrency(value) {
  if (!value) return '-'
  return new Intl.NumberFormat('id-ID').format(value)
}

function areaStatusLabel(status) {
  if (status === 'G') return 'Good'
  if (status === 'NG') return 'Not Good'
  return 'Not Available'
}

function areaStatusClass(status) {
  if (status === 'G') return 'bg-green-100 text-green-800'
  if (status === 'NG') return 'bg-red-100 text-red-800'
  return 'bg-gray-100 text-gray-800'
}

function showLightbox(images, index = 0) {
  imgsRef.value = images
  indexRef.value = index
  visibleRef.value = true
}
</script>

<template>
  <Head :title="`Daily Report - ${report.outlet?.nama_outlet || 'Detail'}`" />

  <div class="min-h-screen bg-gray-100">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white flex-shrink-0">
            <i class="fa-solid fa-clipboard-list"></i>
          </div>
          <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Daily Report</p>
            <h1 class="text-lg sm:text-xl font-bold text-gray-800 truncate">
              {{ report.outlet?.nama_outlet || '-' }} · {{ inspectionTimeText }}
            </h1>
          </div>
        </div>
        <span class="text-xs text-gray-400 flex-shrink-0 hidden sm:inline">YMSoft ERP</span>
      </div>
    </header>

    <main class="max-w-6xl w-full mx-auto py-6 px-4 space-y-6">
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Report</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div>
            <p class="text-sm text-gray-500 mb-1">Outlet</p>
            <p class="text-lg font-semibold text-gray-800">{{ report.outlet?.nama_outlet || '-' }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500 mb-1">Department</p>
            <p class="text-lg font-semibold text-gray-800">{{ report.department?.nama_departemen || '-' }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500 mb-1">Inspection Time</p>
            <p class="text-lg font-semibold text-gray-800">{{ inspectionTimeText }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500 mb-1">Status</p>
            <span :class="['inline-flex px-3 py-1 rounded-full text-sm font-semibold', statusColor]">
              {{ statusText }}
            </span>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 pt-6 border-t border-gray-100">
          <div>
            <p class="text-sm text-gray-500 mb-1">Dibuat Oleh</p>
            <p class="text-lg font-semibold text-gray-800">{{ report.user?.nama_lengkap || '-' }}</p>
            <p v-if="report.user?.jabatan?.nama_jabatan" class="text-sm text-gray-600">{{ report.user.jabatan.nama_jabatan }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500 mb-1">Tanggal Dibuat</p>
            <p class="text-lg font-semibold text-gray-800">{{ formatDateTime(report.created_at) }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Progress Inspeksi</h2>
        <div class="flex items-center gap-4 mb-4">
          <div class="flex-1 bg-gray-200 rounded-full h-3">
            <div
              class="bg-blue-600 h-3 rounded-full transition-all duration-300"
              :style="{ width: progressPercentage + '%' }"
            ></div>
          </div>
          <span class="text-sm font-medium text-gray-600">{{ progressPercentage }}%</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
          <div class="bg-blue-50 p-4 rounded-lg">
            <p class="text-2xl font-bold text-blue-600">{{ report.report_areas?.length || 0 }}</p>
            <p class="text-sm text-blue-800">Area Selesai</p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-2xl font-bold text-gray-600">{{ report.progress?.length || 0 }}</p>
            <p class="text-sm text-gray-800">Total Area</p>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <p class="text-2xl font-bold text-green-600">{{ (report.progress?.length || 0) - (report.report_areas?.length || 0) }}</p>
            <p class="text-sm text-green-800">Sisa</p>
          </div>
        </div>
      </div>

      <div v-if="report.status === 'completed'" class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-star text-yellow-500"></i>
          Inspection Rating
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <div class="text-center">
            <div class="flex justify-center gap-1 mb-2">
              <i
                v-for="star in 5"
                :key="star"
                :class="[
                  'text-xl',
                  star <= (inspectionStats?.star_rating || 0) ? 'fa-solid fa-star text-yellow-400' : 'fa-regular fa-star text-gray-300',
                ]"
              ></i>
            </div>
            <p class="text-sm text-gray-600">Overall</p>
            <p class="text-xs text-gray-500 mt-1">{{ inspectionStats?.rating || 0 }}%</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-green-600">{{ inspectionStats?.good_areas || 0 }}</p>
            <p class="text-sm text-gray-600">Good</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-red-600">{{ inspectionStats?.not_good_areas || 0 }}</p>
            <p class="text-sm text-gray-600">Not Good</p>
          </div>
          <div class="text-center">
            <p class="text-3xl font-bold text-gray-600">{{ inspectionStats?.not_available_areas || 0 }}</p>
            <p class="text-sm text-gray-600">N/A</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Hasil Inspeksi Area</h2>
        <div v-if="report.report_areas?.length" class="space-y-4">
          <div
            v-for="area in report.report_areas"
            :key="area.id"
            class="border border-gray-200 rounded-xl p-4"
          >
            <div class="flex items-center justify-between gap-3 mb-3">
              <h3 class="text-lg font-semibold text-gray-800">{{ area.area?.nama_area || 'Unknown Area' }}</h3>
              <span :class="['px-3 py-1 rounded-full text-sm font-semibold', areaStatusClass(area.status)]">
                {{ areaStatusLabel(area.status) }}
              </span>
            </div>

            <div v-if="area.finding_problem" class="mb-3">
              <p class="text-sm text-gray-500 mb-1">Finding Problem</p>
              <p class="text-gray-800 whitespace-pre-wrap">{{ area.finding_problem }}</p>
            </div>

            <div v-if="area.dept_concern" class="mb-3">
              <p class="text-sm text-gray-500 mb-1">Department Concern</p>
              <p class="text-gray-800">{{ area.dept_concern.nama_divisi }}</p>
            </div>

            <div v-if="area.documentation?.length">
              <p class="text-sm text-gray-500 mb-2">Documentation</p>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <button
                  v-for="(doc, index) in area.documentation"
                  :key="index"
                  type="button"
                  class="relative group"
                  @click="showLightbox(area.documentation, index)"
                >
                  <img
                    :src="doc"
                    :alt="`Documentation ${index + 1}`"
                    class="w-full h-24 object-cover rounded-lg border border-gray-200"
                  />
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          <i class="fa-solid fa-clipboard-list text-4xl mb-3 block"></i>
          Belum ada area yang diinspeksi.
        </div>
      </div>

      <div
        v-if="report.briefing || report.productivity || report.visit_tables?.length || report.summaries?.length"
        class="bg-white rounded-2xl shadow-lg p-6"
      >
        <h2 class="text-xl font-bold text-gray-800 mb-4">Post-Inspection</h2>

        <div v-if="report.briefing" class="mb-6">
          <h3 class="text-lg font-semibold text-gray-700 mb-3">
            {{ report.briefing.briefing_type === 'morning' ? 'Morning Briefing' : 'Afternoon Briefing' }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div v-if="report.briefing.time_of_conduct">
              <p class="text-gray-500">Time of Conduct</p>
              <p class="text-gray-800 font-medium">{{ report.briefing.time_of_conduct }}</p>
            </div>
            <div v-if="report.briefing.participant">
              <p class="text-gray-500">Participant</p>
              <p class="text-gray-800 font-medium">{{ report.briefing.participant }}</p>
            </div>
            <div v-if="report.briefing.service_in_charge">
              <p class="text-gray-500">Service In Charge</p>
              <p class="text-gray-800 font-medium">{{ report.briefing.service_in_charge }}</p>
            </div>
            <div v-if="report.briefing.bar_in_charge">
              <p class="text-gray-500">Bar In Charge</p>
              <p class="text-gray-800 font-medium">{{ report.briefing.bar_in_charge }}</p>
            </div>
            <div v-if="report.briefing.kitchen_in_charge">
              <p class="text-gray-500">Kitchen In Charge</p>
              <p class="text-gray-800 font-medium">{{ report.briefing.kitchen_in_charge }}</p>
            </div>
            <div v-if="report.briefing.daily_revenue_target">
              <p class="text-gray-500">Daily Revenue Target</p>
              <p class="text-gray-800 font-medium">{{ formatCurrency(report.briefing.daily_revenue_target) }}</p>
            </div>
          </div>
        </div>

        <div v-if="report.productivity" class="mb-6">
          <h3 class="text-lg font-semibold text-gray-700 mb-3">Employee Productivity Program</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div v-if="report.productivity.product_knowledge_test">
              <p class="text-gray-500">Product Knowledge Test</p>
              <p class="text-gray-800 font-medium">{{ report.productivity.product_knowledge_test }}</p>
            </div>
            <div v-if="report.productivity.employee_daily_coaching">
              <p class="text-gray-500">Employee Daily Coaching</p>
              <p class="text-gray-800 font-medium">{{ report.productivity.employee_daily_coaching }}</p>
            </div>
          </div>
        </div>

        <div v-if="report.visit_tables?.length" class="mb-6">
          <h3 class="text-lg font-semibold text-gray-700 mb-3">Visit Tables ({{ report.visit_tables.length }})</h3>
          <div class="space-y-3">
            <div
              v-for="(table, index) in report.visit_tables"
              :key="index"
              class="border border-gray-200 rounded-lg p-4 text-sm"
            >
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <p class="text-gray-500">Guest Name</p>
                  <p class="text-gray-800 font-medium">{{ table.guest_name || '-' }}</p>
                </div>
                <div>
                  <p class="text-gray-500">Table No</p>
                  <p class="text-gray-800 font-medium">{{ table.table_no || '-' }}</p>
                </div>
                <div>
                  <p class="text-gray-500">No of Pax</p>
                  <p class="text-gray-800 font-medium">{{ table.no_of_pax || '-' }}</p>
                </div>
              </div>
              <div v-if="table.guest_experience" class="mt-3">
                <p class="text-gray-500">Guest Experience</p>
                <p class="text-gray-800 whitespace-pre-wrap">{{ table.guest_experience }}</p>
              </div>
            </div>
          </div>
        </div>

        <div v-if="report.summaries?.length">
          <h3 class="text-lg font-semibold text-gray-700 mb-3">Report Summary</h3>
          <div class="space-y-3">
            <div
              v-for="summary in report.summaries"
              :key="summary.id"
              class="border border-gray-200 rounded-lg p-4"
            >
              <h4 class="font-semibold text-gray-700 mb-2">
                {{ summary.summary_type === 'summary_1' ? 'Summary 1' : 'Summary 2' }}
              </h4>
              <p class="text-gray-800 whitespace-pre-wrap">{{ summary.notes || 'Tidak ada catatan.' }}</p>
            </div>
          </div>
        </div>
      </div>
    </main>

    <VueEasyLightbox
      teleport="body"
      :visible="visibleRef"
      :imgs="imgsRef"
      :index="indexRef"
      @hide="visibleRef = false"
    />
  </div>
</template>
