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
            <span class="badge" :class="'st-' + live.status">{{ statusLabel(live.status) }}</span>
            <span v-if="polling" class="poll">Memperbarui…</span>
          </div>

          <div v-if="live.status === 'pending'" class="queue-hint">
            <p class="hint-p">
              Status <strong>Menunggu</strong> artinya job belum dijalankan worker. Progres baru muncul setelah worker mengambil job dari antrean.
            </p>
            <div class="diag-box">
              <div class="diag-title">Diagnostik (dari server)</div>
              <ul class="diag-list">
                <li><span class="dk">QUEUE_CONNECTION</span> <code class="code">{{ diagnostics.queue_connection || '—' }}</code></li>
                <li v-if="diagnostics.jobs_pending_count !== null">
                  <span class="dk">Job di tabel <code>jobs</code></span> {{ diagnostics.jobs_pending_count }}
                </li>
                <li v-if="diagnostics.failed_jobs_24h != null && diagnostics.failed_jobs_24h > 0">
                  <span class="dk">Job gagal (24 jam)</span> {{ diagnostics.failed_jobs_24h }}
                  — cek <code class="code">php artisan queue:failed</code>
                </li>
              </ul>
              <p class="diag-verdict">{{ queueDiagnosis }}</p>
            </div>
            <p class="hint-p">
              Perintah umum: <code class="code">php artisan queue:work</code>
              (jalan terus di server). Produksi: pakai Supervisor / systemd agar worker tidak mati.
            </p>
            <p class="hint-p hint-ssh">
              <strong>Tanpa worker Redis:</strong> SSH ke server, <code class="code">cd</code> ke folder Laravel, lalu:
              <code class="code">php artisan google-review:process-ai-report {{ report.id }}</code>
              — memproses laporan ini sekali dari CLI (bisa beberapa menit). Jika status macet di «Memproses», pakai
              <code class="code">--force</code>.
            </p>
          </div>

          <div v-if="live.status === 'processing'" class="queue-hint hint-stuck">
            <p class="hint-p">
              Jika lama tidak bergerak, worker mungkin mati di tengah jalan. Paksa selesai dari CLI:
              <code class="code">php artisan google-review:process-ai-report {{ report.id }} --force</code>
            </p>
          </div>

          <div v-if="live.status === 'pending' || live.status === 'processing'" class="progress-block">
            <div class="progress-top">
              <span class="phase">{{ phaseLabel(live.progress_phase) }}</span>
              <span class="pct">{{ progressPercent }}%</span>
            </div>
            <div class="bar-outer" role="progressbar" :aria-valuenow="progressPercent" aria-valuemin="0" aria-valuemax="100">
              <div class="bar-inner" :style="{ width: progressPercent + '%' }"></div>
            </div>
            <div class="progress-sub">
              {{ live.progress_done }} / {{ live.progress_total || '—' }}
              <span v-if="live.raw_review_count"> · mentah {{ live.raw_review_count }}</span>
              <span v-if="live.dedupe_removed_count"> · duplikat diabaikan {{ live.dedupe_removed_count }}</span>
            </div>
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
            <span>{{ live.review_count }}</span>
          </div>
          <div v-if="(live.progress_log || []).length" class="log-panel">
            <div class="log-title">Log proses</div>
            <ul class="log-list">
              <li v-for="(entry, i) in live.progress_log" :key="i">
                <span class="log-t">{{ entry.t }}</span>
                <span class="log-m">{{ entry.m }}</span>
              </li>
            </ul>
          </div>
          <div v-if="live.error_message || report.error_message" class="err">
            {{ live.error_message || report.error_message }}
          </div>
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
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  report: { type: Object, required: true },
  items: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  queue_diagnostics: { type: Object, default: () => ({}) },
})

const severity = ref(props.filters.severity || '')
const polling = ref(false)
let timer = null

function syncLiveFromReport(r) {
  return {
    status: r.status,
    review_count: r.review_count ?? 0,
    raw_review_count: r.raw_review_count ?? 0,
    dedupe_removed_count: r.dedupe_removed_count ?? 0,
    progress_total: r.progress_total ?? 0,
    progress_done: r.progress_done ?? 0,
    progress_phase: r.progress_phase ?? null,
    progress_log: Array.isArray(r.progress_log) ? r.progress_log : [],
    error_message: r.error_message || null,
  }
}

const live = ref(syncLiveFromReport(props.report))

const diagnostics = ref({ ...props.queue_diagnostics })

const queueDiagnosis = computed(() => {
  const d = diagnostics.value || {}
  const driver = d.queue_connection || ''
  if (driver === 'database') {
    const n = d.jobs_pending_count
    if (n == null) {
      return 'Tidak bisa membaca tabel jobs (cek migrasi antrian: php artisan queue:table).'
    }
    if (n > 0) {
      return `Ada ${n} job mengantre — worker belum jalan atau sibuk. Jalankan: php artisan queue:work`
    }
    return 'Antrean kosong: job mungkin sudah diproses/dihapus, atau gagal sebelum masuk antrean. Cek storage/logs/laravel.log dan queue:failed.'
  }
  if (driver === 'redis') {
    return 'Driver Redis: pastikan Redis aktif dan jalankan php artisan queue:work redis (atau sesuai nama koneksi).'
  }
  if (driver === 'sync') {
    return 'Driver sync menjalankan job di request yang sama. Jika tetap Menunggu setelah refresh, ada error saat dispatch atau migrasi tabel laporan belum lengkap.'
  }
  return `Driver "${driver}": sesuaikan cara menjalankan worker dengan dokumentasi Laravel untuk driver ini.`
})

const progressPercent = computed(() => {
  const t = Number(live.value.progress_total) || 0
  const d = Number(live.value.progress_done) || 0
  if (t <= 0) return 0
  return Math.min(100, Math.round((d / t) * 100))
})

watch(
  () => props.filters.severity,
  (v) => {
    severity.value = v || ''
  }
)

watch(
  () => props.report,
  (r) => {
    if (!r) return
    Object.assign(live.value, syncLiveFromReport(r))
  },
  { deep: true }
)

watch(
  () => props.queue_diagnostics,
  (v) => {
    if (v && typeof v === 'object') {
      diagnostics.value = { ...v }
    }
  },
  { deep: true }
)

function statusLabel(s) {
  const m = { pending: 'Menunggu', processing: 'Memproses', completed: 'Selesai', failed: 'Gagal' }
  return m[s] || s
}

function sourceLabel(s) {
  const m = { apify_dataset: 'Apify (dataset)', places_api: 'Google Places API', scraper_inline: 'File scraper / inline' }
  return m[s] || s
}

function phaseLabel(phase) {
  const m = {
    starting: 'Memulai…',
    fetching: 'Mengunduh dataset',
    deduping: 'Menghapus duplikat',
    classifying: 'Klasifikasi AI',
    saving: 'Menyimpan',
    completed: 'Selesai',
    failed: 'Gagal',
  }
  return m[phase] || (phase ? String(phase) : 'Menunggu / memproses')
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

  const tick = async () => {
    try {
      const res = await fetch(`/google-review/ai/reports/${props.report.id}/status`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
      const j = await res.json()
      if (!j.success) return

      live.value.status = j.status
      live.value.review_count = j.review_count ?? live.value.review_count
      live.value.raw_review_count = j.raw_review_count ?? 0
      live.value.dedupe_removed_count = j.dedupe_removed_count ?? 0
      live.value.progress_total = j.progress_total ?? 0
      live.value.progress_done = j.progress_done ?? 0
      live.value.progress_phase = j.progress_phase ?? null
      live.value.progress_log = Array.isArray(j.progress_log) ? j.progress_log : []
      if (j.error_message) live.value.error_message = j.error_message
      if (j.queue_diagnostics && typeof j.queue_diagnostics === 'object') {
        diagnostics.value = { ...j.queue_diagnostics }
      }

      if (j.status === 'completed' || j.status === 'failed') {
        if (timer) clearInterval(timer)
        timer = null
        polling.value = false
        router.reload({ preserveScroll: true })
      }
    } catch {
      /* ignore */
    }
  }

  tick()
  timer = setInterval(tick, 2000)
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
.queue-hint {
  margin: 10px 0 12px;
  padding: 12px 14px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  font-size: 12px;
  color: #1e40af;
  line-height: 1.55;
}
.hint-p {
  margin: 0 0 10px;
}
.hint-p:last-child {
  margin-bottom: 0;
}
.diag-box {
  margin: 10px 0;
  padding: 10px 12px;
  background: #fff;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  color: #1e3a8a;
}
.diag-title {
  font-weight: 700;
  font-size: 12px;
  margin-bottom: 8px;
}
.diag-list {
  margin: 0 0 10px;
  padding-left: 18px;
}
.diag-list li {
  margin-bottom: 4px;
}
.dk {
  margin-right: 6px;
}
.diag-verdict {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: #7c2d12;
  background: #fff7ed;
  padding: 8px 10px;
  border-radius: 6px;
  border: 1px solid #fed7aa;
}
.hint-ssh {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 10px 12px;
  border-radius: 8px;
  color: #14532d;
}
.hint-ssh .code {
  display: inline;
  word-break: break-all;
}
.hint-stuck {
  background: #fffbeb;
  border-color: #fde68a;
  color: #78350f;
}
.code {
  font-size: 11px;
  background: #fff;
  padding: 1px 5px;
  border-radius: 4px;
  border: 1px solid #dbeafe;
}
.progress-block {
  margin: 12px 0 14px;
}
.progress-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
  font-size: 13px;
}
.phase {
  font-weight: 600;
  color: #111827;
}
.pct {
  font-weight: 700;
  color: #2563eb;
  font-variant-numeric: tabular-nums;
}
.bar-outer {
  height: 10px;
  background: #e5e7eb;
  border-radius: 999px;
  overflow: hidden;
}
.bar-inner {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #2563eb);
  border-radius: 999px;
  transition: width 0.35s ease;
  min-width: 0;
}
.progress-sub {
  margin-top: 6px;
  font-size: 12px;
  color: #6b7280;
  font-variant-numeric: tabular-nums;
}
.log-panel {
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid #f3f4f6;
}
.log-title {
  font-size: 12px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 8px;
}
.log-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 220px;
  overflow-y: auto;
  font-size: 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.log-list li {
  padding: 6px 10px;
  border-bottom: 1px solid #eef0f3;
  display: flex;
  gap: 10px;
  align-items: flex-start;
}
.log-list li:last-child {
  border-bottom: none;
}
.log-t {
  flex: 0 0 auto;
  color: #9ca3af;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.log-m {
  color: #374151;
  word-break: break-word;
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
