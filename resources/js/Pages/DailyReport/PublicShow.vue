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

const reportAreas = computed(() => props.report.report_areas || props.report.reportAreas || [])
const visitTables = computed(() => props.report.visit_tables || props.report.visitTables || [])
const summaries = computed(() => props.report.summaries || [])
const comments = computed(() => props.report.comments || [])
const briefing = computed(() => props.report.briefing || null)
const productivity = computed(() => props.report.productivity || null)

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
  const completedAreas = reportAreas.value.length
  return totalAreas > 0 ? Math.round((completedAreas / totalAreas) * 100) : 0
})

const briefingFields = computed(() => {
  if (!briefing.value) return []
  const b = briefing.value
  return [
    { label: 'Briefing Type', value: b.briefing_type === 'morning' ? 'Morning Briefing' : 'Afternoon Briefing' },
    { label: 'Time of Conduct', value: b.time_of_conduct },
    { label: 'Participant', value: b.participant },
    { label: 'Outlet', value: b.outlet },
    { label: 'Service In Charge', value: b.service_in_charge },
    { label: 'Bar In Charge', value: b.bar_in_charge },
    { label: 'Kitchen In Charge', value: b.kitchen_in_charge },
    { label: 'SO Product', value: b.so_product },
    { label: 'Product Up Selling', value: b.product_up_selling },
    { label: 'Commodity Issue', value: b.commodity_issue },
    { label: 'OE Issue', value: b.oe_issue },
    { label: 'Guest Reservation Pax', value: b.guest_reservation_pax },
    { label: 'Daily Revenue Target', value: b.daily_revenue_target ? formatCurrency(b.daily_revenue_target) : null },
    { label: 'Promotion Program / Campaign', value: b.promotion_program_campaign },
    { label: 'Guest Comment Target', value: b.guest_comment_target },
    { label: 'TripAdvisor Target', value: b.trip_advisor_target },
    { label: 'Other Preparation', value: b.other_preparation },
  ].filter((item) => item.value !== null && item.value !== undefined && String(item.value).trim() !== '')
})

const productivityFields = computed(() => {
  if (!productivity.value) return []
  const p = productivity.value
  return [
    { label: 'Product Knowledge Test', value: p.product_knowledge_test },
    { label: 'SOS Hospitality Role Play', value: p.sos_hospitality_role_play },
    { label: 'Employee Daily Coaching', value: p.employee_daily_coaching },
    { label: 'Others Activity', value: p.others_activity },
  ].filter((item) => item.value !== null && item.value !== undefined && String(item.value).trim() !== '')
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

function formatTimeAgo(value) {
  if (!value) return ''
  const date = new Date(value)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'Baru saja'
  if (diffMins < 60) return `${diffMins} menit lalu`
  if (diffHours < 24) return `${diffHours} jam lalu`
  if (diffDays < 7) return `${diffDays} hari lalu`
  return formatDateTime(value)
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

function getInitials(name) {
  if (!name) return 'U'
  return name.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase()
}

function getAvatarUrl(avatar) {
  if (!avatar) return null
  return avatar.startsWith('/') ? avatar : `/storage/${avatar}`
}

function showLightbox(images, index = 0) {
  imgsRef.value = images
  indexRef.value = index
  visibleRef.value = true
}

function summaryLabel(type) {
  return type === 'summary_1' ? 'Summary 1 (Lunch)' : 'Summary 2 (Dinner)'
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
      <!-- Informasi Report -->
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

      <!-- Progress Inspeksi -->
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
            <p class="text-2xl font-bold text-blue-600">{{ reportAreas.length }}</p>
            <p class="text-sm text-blue-800">Area Selesai</p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-2xl font-bold text-gray-600">{{ report.progress?.length || 0 }}</p>
            <p class="text-sm text-gray-800">Total Area</p>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <p class="text-2xl font-bold text-green-600">{{ (report.progress?.length || 0) - reportAreas.length }}</p>
            <p class="text-sm text-green-800">Sisa</p>
          </div>
        </div>
      </div>

      <!-- Inspection Rating -->
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

      <!-- Hasil Inspeksi Area -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Hasil Inspeksi Area</h2>
        <div v-if="reportAreas.length" class="space-y-4">
          <div
            v-for="area in reportAreas"
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

      <!-- Post-Inspection: Briefing -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-users text-blue-500"></i>
          Post-Inspection — Briefing
        </h2>
        <div v-if="briefingFields.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="field in briefingFields" :key="field.label">
            <p class="text-sm text-gray-500 mb-1">{{ field.label }}</p>
            <p class="text-gray-800 font-medium whitespace-pre-wrap">{{ field.value }}</p>
          </div>
        </div>
        <div v-else class="text-center py-6 text-gray-500 text-sm">
          <i class="fa-solid fa-users text-2xl mb-2 block text-gray-300"></i>
          Belum ada data briefing.
        </div>
      </div>

      <!-- Post-Inspection: Productivity -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-emerald-500"></i>
          Post-Inspection — Employee Productivity
        </h2>
        <div v-if="productivityFields.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="field in productivityFields" :key="field.label">
            <p class="text-sm text-gray-500 mb-1">{{ field.label }}</p>
            <p class="text-gray-800 font-medium whitespace-pre-wrap">{{ field.value }}</p>
          </div>
        </div>
        <div v-else class="text-center py-6 text-gray-500 text-sm">
          <i class="fa-solid fa-chart-line text-2xl mb-2 block text-gray-300"></i>
          Belum ada data productivity.
        </div>
      </div>

      <!-- Post-Inspection: Visit Tables -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-table text-violet-500"></i>
          Post-Inspection — Visit Tables
          <span v-if="visitTables.length" class="text-sm font-normal text-gray-500">({{ visitTables.length }})</span>
        </h2>
        <div v-if="visitTables.length" class="space-y-3">
          <div
            v-for="(table, index) in visitTables"
            :key="table.id || index"
            class="border border-gray-200 rounded-lg p-4"
          >
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Table #{{ index + 1 }}</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
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
              <p class="text-gray-500 text-sm">Guest Experience</p>
              <p class="text-gray-800 whitespace-pre-wrap">{{ table.guest_experience }}</p>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-6 text-gray-500 text-sm">
          <i class="fa-solid fa-table text-2xl mb-2 block text-gray-300"></i>
          Belum ada data visit table.
        </div>
      </div>

      <!-- Post-Inspection: Summary -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-list text-amber-500"></i>
          Post-Inspection — Report Summary
        </h2>
        <div v-if="summaries.length" class="space-y-3">
          <div
            v-for="summary in summaries"
            :key="summary.id"
            class="border border-gray-200 rounded-lg p-4"
          >
            <h4 class="font-semibold text-gray-700 mb-2">{{ summaryLabel(summary.summary_type) }}</h4>
            <p class="text-gray-800 whitespace-pre-wrap">{{ summary.notes || 'Tidak ada catatan.' }}</p>
          </div>
        </div>
        <div v-else class="text-center py-6 text-gray-500 text-sm">
          <i class="fa-solid fa-clipboard-list text-2xl mb-2 block text-gray-300"></i>
          Belum ada summary report.
        </div>
      </div>

      <!-- Komentar -->
      <div class="bg-white rounded-2xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-comments text-blue-500"></i>
          Komentar
          <span v-if="comments.length" class="text-sm font-normal text-gray-500">({{ comments.length }})</span>
        </h2>

        <div v-if="comments.length" class="space-y-4">
          <div v-for="comment in comments" :key="comment.id" class="space-y-3">
            <div class="flex gap-3">
              <div v-if="getAvatarUrl(comment.user?.avatar)" class="w-9 h-9 rounded-full overflow-hidden border border-gray-200 flex-shrink-0">
                <img :src="getAvatarUrl(comment.user.avatar)" :alt="comment.user?.nama_lengkap" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-9 h-9 rounded-full bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                {{ getInitials(comment.user?.nama_lengkap) }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="bg-gray-50 rounded-lg p-3">
                  <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="font-medium text-sm text-gray-800">{{ comment.user?.nama_lengkap || 'Unknown' }}</span>
                    <span v-if="comment.user?.jabatan?.nama_jabatan" class="text-xs text-gray-400">{{ comment.user.jabatan.nama_jabatan }}</span>
                    <span class="text-xs text-gray-500">{{ formatTimeAgo(comment.created_at) }}</span>
                  </div>
                  <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ comment.comment }}</p>
                </div>

                <!-- Replies -->
                <div v-if="comment.replies?.length" class="mt-3 ml-4 space-y-3 border-l-2 border-gray-200 pl-4">
                  <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-3">
                    <div v-if="getAvatarUrl(reply.user?.avatar)" class="w-7 h-7 rounded-full overflow-hidden border border-gray-200 flex-shrink-0">
                      <img :src="getAvatarUrl(reply.user.avatar)" :alt="reply.user?.nama_lengkap" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-7 h-7 rounded-full bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0">
                      {{ getInitials(reply.user?.nama_lengkap) }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="bg-white border border-gray-200 rounded-lg p-3">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                          <span class="font-medium text-sm text-gray-800">{{ reply.user?.nama_lengkap || 'Unknown' }}</span>
                          <span class="text-xs text-gray-500">{{ formatTimeAgo(reply.created_at) }}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ reply.comment }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500 text-sm">
          <i class="fa-solid fa-comment-slash text-3xl mb-3 block text-gray-300"></i>
          Belum ada komentar.
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
