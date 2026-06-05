<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import jsPDF from 'jspdf'
import html2canvas from 'html2canvas'
import VueEasyLightbox from 'vue-easy-lightbox'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  employee: Object,
  summary: Object,
  outletStats: { type: Array, default: () => [] },
  outlets: Array,
  divisions: Array,
  filters: Object,
})

const outletId = ref('')
const divisionId = ref('')
const selectedEmployee = ref(null)
const employees = ref([])
const loadingEmployees = ref(false)
const isLoading = ref(false)
const exportingPdf = ref(false)
const pdfExportMode = ref(false)

const pdfAvatarDataUrl = ref(null)
const pdfEmployeeRef = ref(null)
const pdfSummaryRef = ref(null)
const pdfLeaveRef = ref(null)
const pdfChartsRef = ref(null)
const pdfTableRef = ref(null)

const showOutletModal = ref(false)
const outletModalLoading = ref(false)
const outletModalError = ref(null)
const outletModalData = ref(null)
const expandedSessions = ref(new Set())

const avatarLightboxVisible = ref(false)
const avatarLightboxImages = ref([])

const bulan = ref(props.filters?.bulan || new Date().getMonth() + 1)
const tahun = ref(props.filters?.tahun || new Date().getFullYear())

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const tahunOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i)

const periodLabel = computed(() => {
  if (!props.filters?.start_date || !props.filters?.end_date) return ''
  const fmt = (d) => new Date(d + 'T12:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  return `${fmt(props.filters.start_date)} – ${fmt(props.filters.end_date)}`
})

const hasData = computed(() => props.employee && props.summary)

const employeeAvatarUrl = computed(() => {
  if (!props.employee) return '/images/avatar-default.png'
  if (props.employee.avatar) return `/storage/${props.employee.avatar}`
  if (props.employee.upload_latest_color_photo) return `/storage/${props.employee.upload_latest_color_photo}`
  return '/images/avatar-default.png'
})

function openAvatarLightbox() {
  avatarLightboxImages.value = [employeeAvatarUrl.value]
  avatarLightboxVisible.value = true
}

function getLeaveStyle(name) {
  const n = (name || '').toLowerCase()
  if (n.includes('sick') || n.includes('sakit')) return { icon: 'fa-briefcase-medical', tone: 'rose' }
  if (n.includes('matrimony') || n.includes('nikah') || n.includes('pernikahan')) return { icon: 'fa-heart', tone: 'pink' }
  if (n.includes('public holiday') || n.includes('hari libur')) return { icon: 'fa-star', tone: 'sky' }
  if (n.includes('extra off')) return { icon: 'fa-calendar-plus', tone: 'violet' }
  if (n.includes('annual') || n.includes('cuti tahunan') || n.includes('cuti')) return { icon: 'fa-umbrella-beach', tone: 'emerald' }
  if (n.includes('unpaid')) return { icon: 'fa-ban', tone: 'amber' }
  return { icon: 'fa-file-medical', tone: 'slate' }
}

const summaryCards = computed(() => {
  if (!props.summary) return []
  const s = props.summary
  return [
    { key: 'hadir', label: 'Hadir', value: s.present_days, unit: 'hari', icon: 'fa-check-circle', tone: 'emerald' },
    { key: 'telat', label: 'Terlambat', value: s.total_telat, unit: 'menit', icon: 'fa-clock', tone: 'amber' },
    { key: 'alpha', label: 'Alpha', value: s.alpa_days, unit: 'hari', icon: 'fa-times-circle', tone: 'rose' },
    { key: 'off', label: 'OFF', value: s.off_days, unit: 'hari', icon: 'fa-calendar-minus', tone: 'slate' },
    { key: 'lembur', label: 'Lembur', value: s.total_lembur, unit: 'jam', icon: 'fa-hourglass-half', tone: 'orange' },
    { key: 'ph_komp', label: 'PH Kompensasi', value: s.ph_days, unit: 'hari', icon: 'fa-award', tone: 'sky' },
    { key: 'kerja', label: 'Hari Kerja', value: s.hari_kerja, unit: 'hari', icon: 'fa-briefcase', tone: 'indigo' },
    { key: 'pct', label: 'Kehadiran', value: s.percentage, unit: '%', icon: 'fa-percentage', tone: 'teal' },
  ]
})

const leaveBreakdown = computed(() => {
  if (!props.summary?.leave_breakdown) return []
  return props.summary.leave_breakdown.map((item) => ({
    ...item,
    ...getLeaveStyle(item.name),
  }))
})

const leaveBreakdownWithDays = computed(() => leaveBreakdown.value.filter((item) => item.days > 0))
const leaveBreakdownTotal = computed(() => leaveBreakdown.value.reduce((sum, item) => sum + item.days, 0))

const chartBase = {
  theme: { mode: 'light' },
  chart: {
    toolbar: { show: false },
    fontFamily: 'inherit',
    foreColor: '#475569',
  },
}

const pieSeries = computed(() => props.outletStats.map((o) => o.scan_in))
const pieLabels = computed(() => props.outletStats.map((o) => o.nama_outlet))

const pieOptions = computed(() => ({
  ...chartBase,
  chart: {
    ...chartBase.chart,
    type: 'pie',
    selection: { enabled: true },
  },
  labels: pieLabels.value,
  legend: { position: 'bottom', fontSize: '13px', labels: { colors: '#334155' } },
  dataLabels: {
    enabled: true,
    style: { fontSize: '12px', fontWeight: 600, colors: ['#fff'] },
    formatter: (val) => `${Math.round(val)}%`,
  },
  tooltip: {
    theme: 'light',
    y: {
      formatter: (val, opts) => {
        const item = props.outletStats[opts.seriesIndex]
        return `${val} scan IN (${item?.scan_in_percentage ?? 0}%)`
      },
    },
  },
  colors: ['#4f46e5', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d'],
}))

const hoursBarSeries = computed(() => [{
  name: 'Total Jam',
  data: props.outletStats.map((o) => o.total_hours),
}])

const hoursBarOptions = computed(() => ({
  ...chartBase,
  chart: {
    ...chartBase.chart,
    type: 'bar',
    selection: { enabled: true },
  },
  plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '65%' } },
  xaxis: {
    categories: props.outletStats.map((o) => o.nama_outlet),
    labels: { style: { fontSize: '12px', colors: '#475569' } },
  },
  yaxis: { labels: { style: { colors: '#64748b' } } },
  dataLabels: {
    enabled: true,
    style: { fontSize: '11px', fontWeight: 600, colors: ['#fff'] },
    formatter: (v) => `${v} jam`,
  },
  colors: ['#4f46e5'],
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  tooltip: { theme: 'light' },
}))

const toneClasses = {
  emerald: 'bg-emerald-50 border-emerald-200 text-emerald-700',
  amber: 'bg-amber-50 border-amber-200 text-amber-700',
  rose: 'bg-rose-50 border-rose-200 text-rose-700',
  slate: 'bg-slate-50 border-slate-200 text-slate-700',
  orange: 'bg-orange-50 border-orange-200 text-orange-700',
  sky: 'bg-sky-50 border-sky-200 text-sky-700',
  violet: 'bg-violet-50 border-violet-200 text-violet-700',
  indigo: 'bg-indigo-50 border-indigo-200 text-indigo-700',
  teal: 'bg-teal-50 border-teal-200 text-teal-700',
  pink: 'bg-pink-50 border-pink-200 text-pink-700',
}

async function openOutletDetail(outlet) {
  if (!props.filters?.user_id || !outlet?.id_outlet) return

  showOutletModal.value = true
  outletModalLoading.value = true
  outletModalError.value = null
  outletModalData.value = null
  expandedSessions.value = new Set()

  try {
    const res = await axios.get(route('api.attendance-tracking.outlet-detail'), {
      params: {
        user_id: props.filters.user_id,
        outlet_id: outlet.id_outlet,
        start_date: props.filters.start_date,
        end_date: props.filters.end_date,
      },
    })
    if (res.data.success) {
      outletModalData.value = res.data
    } else {
      outletModalError.value = res.data.message || 'Gagal memuat detail'
    }
  } catch (e) {
    outletModalError.value = e.response?.data?.message || 'Terjadi kesalahan saat memuat detail'
  } finally {
    outletModalLoading.value = false
  }
}

function closeOutletModal() {
  showOutletModal.value = false
  outletModalData.value = null
  outletModalError.value = null
}

function onPieChartClick(_event, _chartContext, config) {
  const idx = config.dataPointIndex ?? config.seriesIndex
  if (idx !== undefined && props.outletStats[idx]) {
    openOutletDetail(props.outletStats[idx])
  }
}

function onBarChartClick(_event, _chartContext, config) {
  if (config.dataPointIndex !== undefined && props.outletStats[config.dataPointIndex]) {
    openOutletDetail(props.outletStats[config.dataPointIndex])
  }
}

function toggleSessionScans(tanggal) {
  const next = new Set(expandedSessions.value)
  if (next.has(tanggal)) next.delete(tanggal)
  else next.add(tanggal)
  expandedSessions.value = next
}

async function fetchEmployees() {
  loadingEmployees.value = true
  try {
    const params = {}
    if (outletId.value) params.outlet_id = outletId.value
    if (divisionId.value) params.division_id = divisionId.value
    const res = await axios.get('/api/attendance-report/employees', { params })
    employees.value = res.data
    if (props.filters?.user_id) {
      const found = employees.value.find((e) => e.id == props.filters.user_id)
      if (found) selectedEmployee.value = found
    }
  } catch (e) {
    console.error(e)
    employees.value = []
  } finally {
    loadingEmployees.value = false
  }
}

function applyFilter() {
  if (!selectedEmployee.value) return
  isLoading.value = true
  router.get(route('attendance-tracking.index'), {
    user_id: selectedEmployee.value.id,
    bulan: bulan.value,
    tahun: tahun.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false },
  })
}

function drawImageCoverSquare(img, size = 512) {
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const ctx = canvas.getContext('2d')
  const srcW = img.naturalWidth || size
  const srcH = img.naturalHeight || size
  const min = Math.min(srcW, srcH)
  const sx = (srcW - min) / 2
  const sy = (srcH - min) / 2
  ctx.drawImage(img, sx, sy, min, min, 0, 0, size, size)
  return canvas
}

function drawImageCoverCircle(img, size = 512) {
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const ctx = canvas.getContext('2d')
  ctx.beginPath()
  ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2)
  ctx.closePath()
  ctx.clip()
  const srcW = img.naturalWidth || size
  const srcH = img.naturalHeight || size
  const min = Math.min(srcW, srcH)
  const sx = (srcW - min) / 2
  const sy = (srcH - min) / 2
  ctx.drawImage(img, sx, sy, min, min, 0, 0, size, size)
  return canvas
}

async function loadImageElement(url) {
  try {
    const response = await fetch(url, { credentials: 'same-origin' })
    if (!response.ok) throw new Error('fetch failed')
    const blob = await response.blob()
    const blobUrl = URL.createObjectURL(blob)
    try {
      return await new Promise((resolve, reject) => {
        const img = new Image()
        img.onload = () => {
          URL.revokeObjectURL(blobUrl)
          resolve(img)
        }
        img.onerror = () => {
          URL.revokeObjectURL(blobUrl)
          reject(new Error('img load failed'))
        }
        img.src = blobUrl
      })
    } catch (e) {
      URL.revokeObjectURL(blobUrl)
      throw e
    }
  } catch {
    return new Promise((resolve, reject) => {
      const img = new Image()
      img.crossOrigin = 'anonymous'
      img.onload = () => resolve(img)
      img.onerror = () => reject(new Error('img load failed'))
      img.src = url.includes('?') ? url : `${url}?pdf=${Date.now()}`
    })
  }
}

async function loadAvatarForPdf(url) {
  try {
    const img = await loadImageElement(url)
    return drawImageCoverCircle(img, 512).toDataURL('image/png')
  } catch {
    return null
  }
}

async function captureSection(element, scale = 2) {
  return html2canvas(element, {
    scale,
    useCORS: true,
    allowTaint: true,
    backgroundColor: '#ffffff',
    logging: false,
    imageTimeout: 15000,
    onclone: (clonedDoc) => {
      clonedDoc.querySelectorAll('.at-pdf-hide').forEach((el) => { el.style.display = 'none' })
      clonedDoc.querySelectorAll('.at-pdf-outlet-name').forEach((el) => {
        el.style.color = '#0f172a'
        el.style.textDecoration = 'none'
      })
      const pdfAvatar = clonedDoc.querySelector('.at-pdf-avatar-img')
      if (pdfAvatar && pdfAvatarDataUrl.value) {
        pdfAvatar.src = pdfAvatarDataUrl.value
      }
    },
  })
}

function drawPdfHeader(doc, margin, pageW) {
  doc.setFillColor(79, 70, 229)
  doc.rect(0, 0, pageW, 32, 'F')
  doc.setFillColor(99, 102, 241)
  doc.rect(0, 28, pageW, 4, 'F')

  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(20)
  doc.text('Tracking Absensi Karyawan', margin, 14)

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(9)
  doc.text(periodLabel.value || '-', margin, 22)
  doc.text(
    `Dicetak: ${new Date().toLocaleString('id-ID')}`,
    pageW - margin,
    22,
    { align: 'right' },
  )

  return 38
}

function appendCanvasToPdf(doc, canvas, startY, margin, pageW, pageH, imageFormat = 'JPEG', jpegQuality = 0.92) {
  const contentW = pageW - margin * 2
  const scale = contentW / canvas.width
  const totalImgH = canvas.height * scale
  let offsetY = 0
  let y = startY
  const gap = 3

  while (offsetY < totalImgH) {
    const availableH = pageH - y - margin - 8
    if (availableH <= 5) {
      doc.addPage()
      y = margin
      continue
    }

    const sliceH = Math.min(totalImgH - offsetY, availableH)
    const sliceSrcH = sliceH / scale

    const sliceCanvas = document.createElement('canvas')
    sliceCanvas.width = canvas.width
    sliceCanvas.height = Math.ceil(sliceSrcH)
    const ctx = sliceCanvas.getContext('2d')
    ctx.drawImage(
      canvas,
      0,
      offsetY / scale,
      canvas.width,
      sliceSrcH,
      0,
      0,
      canvas.width,
      sliceSrcH,
    )

    const sliceData = imageFormat === 'PNG'
      ? sliceCanvas.toDataURL('image/png')
      : sliceCanvas.toDataURL('image/jpeg', jpegQuality)
    doc.addImage(sliceData, imageFormat, margin, y, contentW, sliceH)

    offsetY += sliceH
    y += sliceH + gap

    if (offsetY < totalImgH) {
      doc.addPage()
      y = margin
    }
  }

  return y
}

function addPageFooters(doc, pageW, pageH) {
  const totalPages = doc.internal.getNumberOfPages()
  for (let i = 1; i <= totalPages; i += 1) {
    doc.setPage(i)
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(8)
    doc.setTextColor(148, 163, 184)
    doc.text(`Halaman ${i} dari ${totalPages}`, pageW / 2, pageH - 6, { align: 'center' })
    doc.text('YM Soft ERP · Attendance Tracking', pageW - 10, pageH - 6, { align: 'right' })
  }
}

async function exportPdf() {
  if (!hasData.value) return
  exportingPdf.value = true
  pdfExportMode.value = true

  try {
    pdfAvatarDataUrl.value = await loadAvatarForPdf(employeeAvatarUrl.value)
    await nextTick()
    await new Promise((resolve) => { setTimeout(resolve, 500) })

    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const margin = 10
    const pageW = doc.internal.pageSize.getWidth()
    const pageH = doc.internal.pageSize.getHeight()
    let y = drawPdfHeader(doc, margin, pageW)

    const sections = [
      { el: pdfEmployeeRef.value, scale: 3, format: 'PNG' },
      { el: pdfSummaryRef.value, scale: 2, format: 'JPEG' },
      { el: pdfLeaveRef.value, scale: 2, format: 'JPEG' },
      { el: pdfChartsRef.value, scale: 2, format: 'PNG' },
      { el: pdfTableRef.value, scale: 2, format: 'JPEG' },
    ].filter((s) => s.el)

    for (const section of sections) {
      const canvas = await captureSection(section.el, section.scale)
      if (y > pageH - 40) {
        doc.addPage()
        y = margin
      }
      y = appendCanvasToPdf(doc, canvas, y, margin, pageW, pageH, section.format)
      y += 2
    }

    addPageFooters(doc, pageW, pageH)

    const safeName = (props.employee.nama_lengkap || 'karyawan').replace(/[^\w\s-]/g, '').replace(/\s+/g, '_')
    doc.save(`tracking-absensi_${safeName}_${bulan.value}-${tahun.value}.pdf`)
  } catch (e) {
    console.error(e)
    alert('Gagal membuat PDF. Silakan coba lagi.')
  } finally {
    pdfExportMode.value = false
    pdfAvatarDataUrl.value = null
    exportingPdf.value = false
  }
}

watch([outletId, divisionId], async () => {
  await fetchEmployees()
  selectedEmployee.value = null
})

onMounted(async () => {
  if (props.filters?.outlet_id) outletId.value = props.filters.outlet_id
  if (props.filters?.division_id) divisionId.value = props.filters.division_id
  await fetchEmployees()
})
</script>

<template>
  <AppLayout title="Attendance Tracking">
    <div class="py-5 bg-slate-50 min-h-screen">
      <div class="w-full px-4 sm:px-6 lg:px-8 space-y-5">
        <!-- Filter -->
        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
          <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-filter text-indigo-500"></i>
            <h3 class="text-sm font-semibold text-slate-800">Filter Data</h3>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 items-end">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Outlet</label>
              <select v-model="outletId" class="at-input">
                <option value="">Semua Outlet</option>
                <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Divisi</label>
              <select v-model="divisionId" class="at-input">
                <option value="">Semua Divisi</option>
                <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
              </select>
            </div>
            <div class="xl:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Karyawan</label>
              <Multiselect
                v-model="selectedEmployee"
                :options="employees"
                :loading="loadingEmployees"
                label="name"
                track-by="id"
                placeholder="Cari & pilih karyawan..."
                :disabled="loadingEmployees"
                class="at-multiselect"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Bulan</label>
              <select v-model="bulan" class="at-input">
                <option v-for="(name, idx) in monthNames" :key="idx" :value="idx + 1">{{ name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1.5">Tahun</label>
              <select v-model="tahun" class="at-input">
                <option v-for="y in tahunOptions" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>
          <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
              <button
                type="button"
                :disabled="!selectedEmployee || isLoading"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shadow-sm transition-all disabled:opacity-50"
                @click="applyFilter"
              >
                <i :class="isLoading ? 'fas fa-spinner fa-spin' : 'fas fa-search'"></i>
                Tampilkan
              </button>
              <span v-if="periodLabel" class="inline-flex items-center gap-1.5 text-xs text-slate-600 bg-slate-100 px-3 py-1.5 rounded-full">
                <i class="fas fa-calendar-alt text-indigo-500"></i>
                Periode payroll: <strong class="text-slate-800">{{ periodLabel }}</strong>
              </span>
            </div>
            <button
              v-if="hasData"
              type="button"
              :disabled="exportingPdf"
              class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-rose-600 rounded-lg shadow-sm hover:bg-rose-700 transition-colors disabled:opacity-60"
              @click="exportPdf"
            >
              <i :class="exportingPdf ? 'fas fa-spinner fa-spin' : 'fas fa-file-pdf'"></i>
              Export PDF
            </button>
          </div>
        </section>

        <!-- Empty -->
        <section v-if="!hasData" class="bg-white border border-slate-200 rounded-2xl shadow-sm py-16 text-center">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-indigo-50 flex items-center justify-center">
            <i class="fas fa-user-clock text-2xl text-indigo-400"></i>
          </div>
          <p class="text-slate-600 font-medium">Belum ada data ditampilkan</p>
          <p class="text-sm text-slate-500 mt-1">Pilih karyawan dan periode, lalu klik <strong>Tampilkan</strong></p>
        </section>

        <template v-else>
          <!-- Employee -->
          <section ref="pdfEmployeeRef" class="bg-white border border-slate-200 rounded-2xl shadow-sm px-5 py-8">
            <div class="flex flex-col items-center text-center">
              <button
                v-if="!pdfExportMode"
                type="button"
                class="relative group shrink-0 rounded-full focus:outline-none focus:ring-4 focus:ring-indigo-200"
                title="Klik untuk perbesar foto"
                @click="openAvatarLightbox"
              >
                <img
                  :src="employeeAvatarUrl"
                  :alt="employee.nama_lengkap"
                  class="w-28 h-28 sm:w-32 sm:h-32 rounded-full object-cover border-4 border-indigo-200 shadow-lg group-hover:shadow-xl group-hover:scale-[1.02] transition-all duration-200"
                  @error="($event.target).src = '/images/avatar-default.png'"
                />
                <span class="absolute bottom-1 right-1 w-6 h-6 rounded-full bg-emerald-500 border-[3px] border-white"></span>
                <span class="absolute inset-0 rounded-full bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center">
                  <i class="fas fa-search-plus text-white opacity-0 group-hover:opacity-90 text-xl drop-shadow"></i>
                </span>
              </button>
              <div v-else class="relative shrink-0 w-36 h-36">
                <img
                  v-if="pdfAvatarDataUrl"
                  :src="pdfAvatarDataUrl"
                  :alt="employee.nama_lengkap"
                  class="at-pdf-avatar-img w-36 h-36 block shadow-lg"
                />
                <div v-else class="w-36 h-36 rounded-full bg-slate-100 border-4 border-indigo-200"></div>
                <span class="absolute bottom-1 right-1 w-6 h-6 rounded-full bg-emerald-500 border-[3px] border-white"></span>
              </div>

              <h3 class="mt-4 text-2xl font-bold text-slate-900">{{ employee.nama_lengkap }}</h3>
              <p class="text-sm font-medium text-indigo-600 mt-1">{{ employee.nik || '-' }}</p>
              <p class="text-sm text-slate-600 mt-2 max-w-2xl">
                {{ employee.nama_jabatan || '-' }}
              </p>
              <p class="text-sm text-slate-500 mt-1 max-w-2xl">
                {{ employee.nama_outlet || '-' }}
                <span v-if="employee.nama_divisi"> · {{ employee.nama_divisi }}</span>
              </p>
            </div>
          </section>

          <!-- Summary -->
          <section ref="pdfSummaryRef" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
            <div
              v-for="card in summaryCards"
              :key="card.key"
              class="rounded-xl border p-4 transition-shadow hover:shadow-md"
              :class="toneClasses[card.tone]"
            >
              <div class="flex items-center gap-2 mb-2">
                <i :class="['fas', card.icon, 'text-sm opacity-80']"></i>
                <p class="text-xs font-semibold uppercase tracking-wide opacity-90">{{ card.label }}</p>
              </div>
              <p class="text-2xl font-bold leading-none">
                {{ card.value }}<span class="text-sm font-medium ml-1 opacity-70">{{ card.unit }}</span>
              </p>
            </div>
          </section>

          <!-- Izin & Cuti -->
          <section ref="pdfLeaveRef" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
              <div class="flex items-center gap-2">
                <i class="fas fa-calendar-check text-indigo-500"></i>
                <h3 class="text-base font-semibold text-slate-900">Izin & Cuti (Disetujui)</h3>
              </div>
              <span class="text-xs font-medium text-slate-600 bg-slate-100 px-3 py-1.5 rounded-full">
                Total: <strong class="text-slate-900">{{ leaveBreakdownTotal }}</strong> hari
                <span v-if="leaveBreakdownWithDays.length" class="text-slate-400 mx-1">·</span>
                <span v-if="leaveBreakdownWithDays.length">{{ leaveBreakdownWithDays.length }} jenis digunakan</span>
              </span>
            </div>

            <div v-if="leaveBreakdown.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
              <div
                v-for="item in leaveBreakdown"
                :key="item.leave_type_id"
                class="rounded-xl border p-4 transition-all"
                :class="[
                  toneClasses[item.tone],
                  item.days > 0 ? 'shadow-sm' : 'opacity-50',
                ]"
              >
                <div class="flex items-center gap-2 mb-2">
                  <i :class="['fas', item.icon, 'text-sm']"></i>
                  <p class="text-xs font-semibold leading-tight">{{ item.name }}</p>
                </div>
                <p class="text-2xl font-bold leading-none">
                  {{ item.days }}<span class="text-sm font-medium ml-1 opacity-70">hari</span>
                </p>
              </div>
            </div>
            <p v-else class="text-sm text-slate-500 text-center py-6">Tidak ada data jenis izin/cuti aktif.</p>
          </section>

          <!-- Charts -->
          <section v-if="outletStats.length" ref="pdfChartsRef" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-slate-900">Distribusi Scan IN per Outlet</h3>
              <p class="text-xs text-slate-500 mt-1">
                Persentase kehadiran (scan masuk) di masing-masing outlet
                <span v-if="!pdfExportMode" class="text-indigo-600 at-pdf-hide">· Klik slice untuk detail</span>
              </p>
              <div class="mt-4 bg-slate-50 rounded-xl p-2 at-chart-clickable">
                <apexchart type="pie" height="340" :options="pieOptions" :series="pieSeries" @dataPointSelection="onPieChartClick" />
              </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
              <h3 class="text-base font-semibold text-slate-900">Total Jam per Outlet</h3>
              <p class="text-xs text-slate-500 mt-1">
                Durasi kerja (IN→OUT, termasuk cross-day) per outlet
                <span v-if="!pdfExportMode" class="text-indigo-600 at-pdf-hide">· Klik bar untuk detail</span>
              </p>
              <div class="mt-4 bg-slate-50 rounded-xl p-2 at-chart-clickable">
                <apexchart type="bar" height="340" :options="hoursBarOptions" :series="hoursBarSeries" @dataPointSelection="onBarChartClick" />
              </div>
            </div>
          </section>

          <section v-else class="bg-white border border-slate-200 rounded-2xl shadow-sm p-10 text-center text-slate-500 text-sm">
            Tidak ada data scan absensi per outlet pada periode ini.
          </section>

          <!-- Table -->
          <section v-if="outletStats.length" ref="pdfTableRef" class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
              <h3 class="text-base font-semibold text-slate-900">Detail per Outlet</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                    <th class="px-5 py-3 text-left font-semibold">Outlet</th>
                    <th class="px-5 py-3 text-right font-semibold">Scan IN</th>
                    <th class="px-5 py-3 text-right font-semibold">Scan OUT</th>
                    <th class="px-5 py-3 text-right font-semibold">% IN</th>
                    <th class="px-5 py-3 text-right font-semibold">Total Jam</th>
                    <th class="px-5 py-3 text-right font-semibold">Sesi</th>
                    <th class="px-5 py-3 text-right font-semibold">Tanpa Checkout</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr
                    v-for="(row, idx) in outletStats"
                    :key="row.id_outlet"
                    class="hover:bg-indigo-50/40 transition-colors"
                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'"
                  >
                    <td class="px-5 py-3.5">
                      <button
                        v-if="!pdfExportMode"
                        type="button"
                        class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline text-left transition-colors"
                        @click="openOutletDetail(row)"
                      >
                        {{ row.nama_outlet }}
                      </button>
                      <span v-else class="font-medium text-slate-900 at-pdf-outlet-name">{{ row.nama_outlet }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.scan_in }}</td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.scan_out }}</td>
                    <td class="px-5 py-3.5 text-right font-semibold text-indigo-600">{{ row.scan_in_percentage }}%</td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.total_hours }} jam</td>
                    <td class="px-5 py-3.5 text-right text-slate-700">{{ row.sessions }}</td>
                    <td class="px-5 py-3.5 text-right" :class="row.no_checkout_sessions > 0 ? 'text-rose-600 font-semibold' : 'text-slate-700'">
                      {{ row.no_checkout_sessions }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
      </div>
    </div>

    <!-- Modal Detail Outlet -->
    <Teleport to="body">
      <div v-if="showOutletModal" class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
          <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-[1px]" @click="closeOutletModal"></div>

          <div class="relative z-10 w-full max-w-4xl bg-white rounded-2xl shadow-xl border border-slate-200 max-h-[90vh] flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-4">
              <div>
                <h3 class="text-lg font-semibold text-slate-900">
                  Detail Absensi — {{ outletModalData?.outlet?.nama_outlet || '...' }}
                </h3>
                <p v-if="periodLabel" class="text-sm text-slate-500 mt-1">{{ periodLabel }}</p>
                <p v-if="employee" class="text-xs text-slate-400 mt-0.5">{{ employee.nama_lengkap }} · {{ employee.nik }}</p>
              </div>
              <button
                type="button"
                class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                @click="closeOutletModal"
              >
                <i class="fas fa-times"></i>
              </button>
            </div>

            <div class="px-6 py-4 flex-1 overflow-y-auto">
              <div v-if="outletModalLoading" class="text-center py-12 text-slate-500">
                <i class="fas fa-spinner fa-spin text-2xl text-indigo-500 mb-3"></i>
                <p class="text-sm">Memuat detail absensi...</p>
              </div>

              <div v-else-if="outletModalError" class="text-center py-12">
                <i class="fas fa-exclamation-circle text-2xl text-rose-500 mb-3"></i>
                <p class="text-sm text-rose-600">{{ outletModalError }}</p>
              </div>

              <template v-else-if="outletModalData">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                  <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3">
                    <p class="text-xs text-indigo-600 font-medium">Scan IN</p>
                    <p class="text-xl font-bold text-indigo-800">{{ outletModalData.summary.total_scan_in }}</p>
                  </div>
                  <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs text-emerald-600 font-medium">Scan OUT</p>
                    <p class="text-xl font-bold text-emerald-800">{{ outletModalData.summary.total_scan_out }}</p>
                  </div>
                  <div class="rounded-xl border border-violet-200 bg-violet-50 p-3">
                    <p class="text-xs text-violet-600 font-medium">Total Jam</p>
                    <p class="text-xl font-bold text-violet-800">{{ outletModalData.summary.total_hours }} jam</p>
                  </div>
                  <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                    <p class="text-xs text-rose-600 font-medium">Tanpa Checkout</p>
                    <p class="text-xl font-bold text-rose-800">{{ outletModalData.summary.no_checkout_sessions }}</p>
                  </div>
                </div>

                <div v-if="outletModalData.sessions.length" class="overflow-x-auto rounded-xl border border-slate-200">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                        <th class="px-4 py-2.5 text-left font-semibold w-8"></th>
                        <th class="px-4 py-2.5 text-left font-semibold">Hari</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Tanggal</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Jam Masuk</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Jam Keluar</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Durasi</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <template v-for="session in outletModalData.sessions" :key="session.tanggal">
                        <tr class="hover:bg-indigo-50/30 transition-colors">
                          <td class="px-4 py-3">
                            <button
                              v-if="session.scans?.length"
                              type="button"
                              class="text-slate-400 hover:text-indigo-600"
                              @click="toggleSessionScans(session.tanggal)"
                            >
                              <i :class="['fas', expandedSessions.has(session.tanggal) ? 'fa-chevron-down' : 'fa-chevron-right', 'text-xs']"></i>
                            </button>
                          </td>
                          <td class="px-4 py-3 font-medium text-slate-800">{{ session.hari }}</td>
                          <td class="px-4 py-3 text-slate-700">{{ session.tanggal_label }}</td>
                          <td class="px-4 py-3 text-emerald-700 font-medium">
                            {{ session.jam_masuk_display || '—' }}
                            <span v-if="session.is_cross_day && session.jam_masuk_display" class="text-xs text-amber-600 ml-1">*</span>
                          </td>
                          <td class="px-4 py-3 text-rose-700 font-medium">
                            <span v-if="session.has_no_checkout" class="text-amber-600 text-xs font-semibold">Belum checkout</span>
                            <template v-else>{{ session.jam_keluar_display || '—' }}</template>
                            <span v-if="session.is_cross_day && session.jam_keluar_display" class="text-xs text-amber-600 ml-1">+1 hari</span>
                          </td>
                          <td class="px-4 py-3 text-right text-slate-700">{{ session.durasi_label || '—' }}</td>
                        </tr>
                        <tr v-if="expandedSessions.has(session.tanggal) && session.scans?.length" class="bg-slate-50">
                          <td colspan="6" class="px-4 py-3">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Riwayat Scan</p>
                            <div class="space-y-1.5">
                              <div
                                v-for="(scan, scanIdx) in session.scans"
                                :key="scanIdx"
                                class="flex flex-wrap items-center gap-2 text-xs"
                              >
                                <span
                                  class="inline-flex items-center px-2 py-0.5 rounded font-semibold"
                                  :class="scan.type === 'IN' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                >
                                  {{ scan.type }}
                                </span>
                                <span class="text-slate-600">{{ scan.hari }}, {{ scan.tanggal }}</span>
                                <span class="font-mono font-medium text-slate-800">{{ scan.jam }}</span>
                              </div>
                            </div>
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
                <p v-else class="text-center py-10 text-sm text-slate-500">Tidak ada sesi absensi di outlet ini pada periode terpilih.</p>

                <p v-if="outletModalData.sessions.some(s => s.is_cross_day)" class="text-xs text-amber-600 mt-3">
                  <i class="fas fa-info-circle mr-1"></i>
                  * Sesi cross-day: checkout tercatat di hari berikutnya.
                </p>
              </template>
            </div>

            <div class="px-6 py-4 border-t border-slate-200 flex justify-end">
              <button
                type="button"
                class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200 transition-colors"
                @click="closeOutletModal"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <VueEasyLightbox
      :visible="avatarLightboxVisible"
      :imgs="avatarLightboxImages"
      :index="0"
      @hide="avatarLightboxVisible = false"
    />
  </AppLayout>
</template>

<style scoped>
.at-input {
  @apply w-full rounded-lg border-slate-300 bg-white text-slate-800 text-sm shadow-sm
    focus:border-indigo-500 focus:ring-indigo-500 transition-colors;
}

:deep(.at-multiselect .multiselect__tags) {
  @apply rounded-lg border-slate-300 bg-white min-h-[42px] pt-2;
}

:deep(.at-multiselect .multiselect__input),
:deep(.at-multiselect .multiselect__single) {
  @apply text-sm text-slate-800 bg-white;
}

:deep(.at-multiselect .multiselect__content-wrapper) {
  @apply border-slate-200 shadow-lg rounded-lg;
}

:deep(.at-multiselect .multiselect__option) {
  @apply text-sm text-slate-700;
}

:deep(.at-multiselect .multiselect__option--highlight) {
  background: #4f46e5 !important;
  color: #ffffff !important;
}

:deep(.at-multiselect .multiselect__option--selected.multiselect__option--highlight) {
  background: #4f46e5 !important;
  color: #ffffff !important;
}

:deep(.at-multiselect .multiselect__option--highlight::after) {
  color: #ffffff !important;
}

:deep(.at-chart-clickable .apexcharts-pie-area),
:deep(.at-chart-clickable .apexcharts-bar-area) {
  cursor: pointer;
}
</style>
