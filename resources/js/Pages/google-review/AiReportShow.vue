<template>
  <AppLayout>
    <template #default>
      <div class="wrap">
        <div class="head">
          <div>
            <h1 class="title">Laporan #{{ report.id }}</h1>
            <p class="sub">
              {{ report.place_name || '—' }}
              <span v-if="report.nama_outlet"> · {{ report.nama_outlet }}</span>
            </p>
          </div>
          <div class="actions">
            <Link href="/google-review/ai/reports" class="btn-sec">← Daftar laporan</Link>
            <a
              v-if="report.status === 'completed'"
              :href="`/google-review/ai/reports/${report.id}/export`"
              class="btn-prim"
            >Download Excel</a>
          </div>
        </div>

        <div class="panel meta">
          <div class="row">
            <span class="lbl">Status</span>
            <span class="badge" :class="'st-' + report.status">{{ statusLabel(report.status) }}</span>
            <span v-if="polling" class="poll">Memeriksa proses…</span>
          </div>
          <div class="row">
            <span class="lbl">Sumber</span>
            <span>{{ sourceLabel(report.source) }}</span>
          </div>
          <div v-if="report.place_rating" class="row">
            <span class="lbl">Rating tempat</span>
            <span>{{ report.place_rating }}</span>
          </div>
          <div class="row">
            <span class="lbl">Jumlah review terklasifikasi</span>
            <span>{{ report.review_count }}</span>
          </div>
          <div v-if="report.error_message" class="err">{{ report.error_message }}</div>
        </div>

        <div class="panel" v-if="report.status === 'completed'">
          <div class="toolbar">
            <label class="filab">Filter severity</label>
            <select v-model="severity" class="sel" @change="applyFilter">
              <option value="">Semua</option>
              <option value="positive">Positif</option>
              <option value="neutral">Netral</option>
              <option value="mild_negative">Negatif ringan</option>
              <option value="negative">Negatif</option>
              <option value="severe">Sangat parah</option>
            </select>
          </div>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Penulis</th>
                  <th>Rating</th>
                  <th>Tanggal</th>
                  <th>Severity</th>
                  <th>Topik</th>
                  <th>Ringkasan AI</th>
                  <th>Teks</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="it in items.data" :key="it.id">
                  <td>{{ it.sort_order + 1 }}</td>
                  <td>{{ it.author || '—' }}</td>
                  <td>{{ it.rating || '—' }}</td>
                  <td class="muted small">{{ it.review_date }}</td>
                  <td><span class="sev" :class="'s-' + it.severity">{{ sevLabel(it.severity) }}</span></td>
                  <td class="small">{{ (it.topics || []).join(', ') }}</td>
                  <td class="small sum">{{ it.summary_id }}</td>
                  <td class="txt">{{ it.text || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="items.links?.length > 3" class="pager">
            <Link
              v-for="l in items.links"
              :key="l.label"
              :href="l.url || '#'"
              class="page-link"
              :class="{ active: l.active, disabled: !l.url }"
              preserve-scroll
              v-html="l.label"
            />
          </div>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import { onMounted, onUnmounted, ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  report: { type: Object, required: true },
  items: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const severity = ref(props.filters.severity || '')
const polling = ref(false)
let timer = null

watch(
  () => props.filters.severity,
  (v) => {
    severity.value = v || ''
  }
)

function statusLabel(s) {
  const m = { pending: 'Menunggu', processing: 'Memproses', completed: 'Selesai', failed: 'Gagal' }
  return m[s] || s
}

function sourceLabel(s) {
  const m = { apify_dataset: 'Apify (dataset)', places_api: 'Google Places API', scraper_inline: 'File scraper / inline' }
  return m[s] || s
}

function sevLabel(s) {
  const m = {
    positive: 'Positif',
    neutral: 'Netral',
    mild_negative: 'Negatif ringan',
    negative: 'Negatif',
    severe: 'Sangat parah',
  }
  return m[s] || s || '—'
}

function applyFilter() {
  router.get(
    `/google-review/ai/reports/${props.report.id}`,
    { severity: severity.value || undefined },
    { preserveState: true, replace: true, preserveScroll: true }
  )
}

function startPoll() {
  if (timer) clearInterval(timer)
  if (props.report.status !== 'pending' && props.report.status !== 'processing') return
  polling.value = true
  timer = setInterval(async () => {
    try {
      const res = await fetch(`/google-review/ai/reports/${props.report.id}/status`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
      const j = await res.json()
      if (j.success && (j.status === 'completed' || j.status === 'failed')) {
        clearInterval(timer)
        timer = null
        polling.value = false
        router.reload({ preserveScroll: true })
      }
    } catch {
      /* ignore */
    }
  }, 4000)
}

onMounted(() => {
  startPoll()
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.wrap {
  max-width: 1280px;
  margin: 0 auto;
  padding: 20px 12px 48px;
}
.head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
}
.sub {
  margin-top: 4px;
  font-size: 14px;
  color: #6b7280;
}
.actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.btn-sec {
  padding: 10px 14px;
  border-radius: 10px;
  background: #f3f4f6;
  color: #374151;
  font-weight: 600;
  text-decoration: none;
  font-size: 14px;
}
.btn-prim {
  padding: 10px 14px;
  border-radius: 10px;
  background: #059669;
  color: #fff;
  font-weight: 600;
  text-decoration: none;
  font-size: 14px;
}
.panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 2px 12px rgba(17, 24, 39, 0.04);
}
.meta .row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.lbl {
  min-width: 160px;
  font-size: 13px;
  color: #6b7280;
}
.badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}
.st-pending,
.st-processing {
  background: #fef3c7;
  color: #92400e;
}
.st-completed {
  background: #d1fae5;
  color: #065f46;
}
.st-failed {
  background: #fecaca;
  color: #991b1b;
}
.poll {
  font-size: 12px;
  color: #2563eb;
}
.err {
  margin-top: 10px;
  padding: 10px;
  background: #fef2f2;
  border-radius: 8px;
  color: #991b1b;
  font-size: 13px;
}
.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}
.filab {
  font-size: 13px;
  color: #6b7280;
}
.sel {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 10px;
  min-width: 180px;
}
.table-wrap {
  overflow-x: auto;
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.table th,
.table td {
  text-align: left;
  padding: 8px 6px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: top;
}
.table th {
  color: #6b7280;
  font-weight: 600;
  white-space: nowrap;
}
.muted {
  color: #6b7280;
}
.small {
  max-width: 140px;
  word-break: break-word;
}
.sum {
  max-width: 200px;
  font-style: italic;
  color: #4b5563;
}
.txt {
  max-width: 320px;
  word-break: break-word;
}
.sev {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 6px;
  white-space: nowrap;
}
.s-positive {
  background: #d1fae5;
  color: #065f46;
}
.s-neutral {
  background: #e5e7eb;
  color: #374151;
}
.s-mild_negative {
  background: #fef3c7;
  color: #92400e;
}
.s-negative {
  background: #fed7aa;
  color: #9a3412;
}
.s-severe {
  background: #fecaca;
  color: #7f1d1d;
}
.pager {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 16px;
  justify-content: center;
}
.page-link {
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 12px;
  text-decoration: none;
  color: #374151;
}
.page-link.active {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}
.page-link.disabled {
  opacity: 0.4;
  pointer-events: none;
}
</style>
