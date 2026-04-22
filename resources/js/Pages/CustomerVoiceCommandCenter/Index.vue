<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-3 sm:px-4 lg:px-6">
      <div v-if="$page.props.flash?.success" class="mb-4 p-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="mb-4 p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-800 text-sm">
        {{ $page.props.flash.error }}
      </div>

      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-slate-900">Customer Voice Command Center</h1>
        <p class="mt-2 text-sm text-slate-600">
          Menu ini berdiri sendiri dan disiapkan untuk operasional follow-up sentimen customer lintas channel.
        </p>
        <div class="mt-4">
          <button
            type="button"
            class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60"
            :disabled="syncing"
            @click="syncNow"
          >
            {{ syncing ? 'Syncing...' : 'Sync Data Sekarang' }}
          </button>
        </div>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-4">
        <div class="bg-white border border-slate-200 rounded-xl p-4">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Cases</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ summary.total_cases || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Open Cases</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ summary.open_cases || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Severe Open</p>
          <p class="mt-2 text-2xl font-bold text-rose-600">{{ summary.severe_open || 0 }}</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Overdue Open</p>
          <p class="mt-2 text-2xl font-bold text-amber-600">{{ summary.overdue_open || 0 }}</p>
        </div>
      </div>

      <div class="mt-4 bg-white border border-slate-200 rounded-xl p-4">
        <div class="grid gap-3 md:grid-cols-7">
          <input
            v-model="q"
            type="text"
            placeholder="Cari author/ringkasan/komentar/outlet"
            class="md:col-span-2 w-full px-3 py-2 border rounded-lg text-sm"
            @keyup.enter="applyFilters"
          />
          <select v-model="status" class="w-full px-3 py-2 border rounded-lg text-sm" @change="applyFilters">
            <option value="">Semua status</option>
            <option value="new">New</option>
            <option value="in_progress">In Progress</option>
            <option value="resolved">Resolved</option>
            <option value="ignored">Ignored</option>
          </select>
          <select v-model="severity" class="w-full px-3 py-2 border rounded-lg text-sm" @change="applyFilters">
            <option value="">Semua severity</option>
            <option value="severe">Severe</option>
            <option value="negative">Negative</option>
            <option value="mild_negative">Mild Negative</option>
            <option value="neutral">Neutral</option>
            <option value="positive">Positive</option>
          </select>
          <select v-model="sourceType" class="w-full px-3 py-2 border rounded-lg text-sm" @change="applyFilters">
            <option value="">Semua source</option>
            <option value="google_review">Google Review</option>
            <option value="instagram_comment">Instagram Comment</option>
            <option value="guest_comment">Guest Comment</option>
          </select>
          <select v-model="idOutlet" class="w-full px-3 py-2 border rounded-lg text-sm" @change="applyFilters">
            <option value="">Semua outlet</option>
            <option v-for="o in outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
          </select>
          <label class="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm">
            <input v-model="overdueOnly" type="checkbox" @change="applyFilters" />
            Overdue saja
          </label>
        </div>
      </div>

      <div class="mt-4 bg-white border border-slate-200 rounded-xl overflow-x-auto">
        <table class="w-full min-w-[1000px] text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="text-left p-3">Waktu</th>
              <th class="text-left p-3">Outlet</th>
              <th class="text-left p-3">Source</th>
              <th class="text-left p-3">Severity</th>
              <th class="text-left p-3">Ringkasan</th>
              <th class="text-left p-3">Risk</th>
              <th class="text-left p-3">SLA</th>
              <th class="text-left p-3">PIC</th>
              <th class="text-left p-3">Status</th>
              <th class="text-left p-3">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!cases.data?.length">
              <td colspan="10" class="p-4 text-center text-slate-400">Belum ada case.</td>
            </tr>
            <template v-for="row in cases.data" :key="row.id">
              <tr class="border-t border-slate-100 align-top">
                <td class="p-3 whitespace-nowrap">{{ formatDate(row.event_at) }}</td>
                <td class="p-3">{{ row.nama_outlet || '-' }}</td>
                <td class="p-3">{{ row.source_type }}</td>
                <td class="p-3">
                  <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="severityClass(row.severity)">
                    {{ row.severity || 'neutral' }}
                  </span>
                </td>
                <td class="p-3">
                  <div class="font-medium text-slate-800">{{ row.summary_id || '-' }}</div>
                  <div class="mt-1 text-xs text-slate-500 line-clamp-2">{{ row.raw_text || '' }}</div>
                </td>
                <td class="p-3 font-semibold">{{ row.risk_score ?? 0 }}</td>
                <td class="p-3 min-w-[180px]">
                  <div class="text-xs" :class="slaClass(row)">
                    {{ slaLabel(row) }}
                  </div>
                  <div v-if="row.due_at" class="text-[11px] text-slate-500 mt-1">
                    due {{ formatDate(row.due_at) }}
                  </div>
                </td>
                <td class="p-3 min-w-[180px]">
                  <select
                    v-model="caseForms[row.id].assigned_to"
                    class="w-full px-2 py-1 border rounded-md text-xs"
                  >
                    <option value="">Unassigned</option>
                    <option v-for="u in assignees" :key="u.id" :value="String(u.id)">
                      {{ u.nama_lengkap }}
                    </option>
                  </select>
                </td>
                <td class="p-3 min-w-[140px]">
                  <select
                    v-model="caseForms[row.id].status"
                    class="w-full px-2 py-1 border rounded-md text-xs"
                  >
                    <option value="new">new</option>
                    <option value="in_progress">in_progress</option>
                    <option value="resolved">resolved</option>
                    <option value="ignored">ignored</option>
                  </select>
                </td>
                <td class="p-3 min-w-[220px]">
                  <div class="flex flex-wrap gap-1">
                    <button
                      type="button"
                      class="px-2 py-1 rounded bg-indigo-600 text-white text-xs font-semibold disabled:opacity-60"
                      :disabled="updatingCaseId === row.id"
                      @click="updateCase(row.id)"
                    >
                      Simpan
                    </button>
                    <button
                      type="button"
                      class="px-2 py-1 rounded bg-slate-100 text-slate-700 text-xs font-semibold"
                      @click="addNote(row.id)"
                    >
                      Catatan
                    </button>
                    <button
                      type="button"
                      class="px-2 py-1 rounded bg-amber-100 text-amber-700 text-xs font-semibold"
                      @click="toggleActivities(row.id)"
                    >
                      Timeline
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="openedActivityCaseId === row.id" class="border-t border-slate-100 bg-slate-50">
                <td class="p-3" colspan="10">
                  <div class="text-xs font-semibold text-slate-600 mb-2">Aktivitas terbaru</div>
                  <div v-if="!activitiesFor(row.id).length" class="text-xs text-slate-400">
                    Belum ada aktivitas.
                  </div>
                  <div v-else class="space-y-2">
                    <div v-for="a in activitiesFor(row.id)" :key="a.id" class="text-xs text-slate-700 border border-slate-200 rounded-md p-2 bg-white">
                      <div class="font-semibold">
                        {{ a.activity_type }}
                        <span class="font-normal text-slate-500">· {{ formatDate(a.created_at) }}</span>
                      </div>
                      <div v-if="a.actor_name" class="text-slate-500">oleh {{ a.actor_name }}</div>
                      <div v-if="a.from_status || a.to_status" class="text-slate-500">
                        {{ a.from_status || '-' }} -> {{ a.to_status || '-' }}
                      </div>
                      <div v-if="a.note" class="mt-1">{{ a.note }}</div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  cases: { type: Object, default: () => ({ data: [] }) },
  outlets: { type: Array, default: () => [] },
  assignees: { type: Array, default: () => [] },
  activities: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
})

const syncing = ref(false)
const updatingCaseId = ref(null)
const openedActivityCaseId = ref(null)
const caseForms = ref({})
const q = ref(props.filters?.q || '')
const status = ref(props.filters?.status || '')
const severity = ref(props.filters?.severity || '')
const sourceType = ref(props.filters?.source_type || '')
const idOutlet = ref(props.filters?.id_outlet ? String(props.filters.id_outlet) : '')
const overdueOnly = ref(Boolean(props.filters?.overdue_only))

function initCaseForms() {
  const next = {}
  for (const row of props.cases?.data || []) {
    next[row.id] = {
      status: String(row.status || 'new'),
      assigned_to: row.assigned_to != null ? String(row.assigned_to) : '',
    }
  }
  caseForms.value = next
}

initCaseForms()

watch(
  () => props.cases?.data,
  () => initCaseForms()
)

function syncNow() {
  syncing.value = true
  router.post('/customer-voice-command-center/sync', {}, {
    preserveScroll: true,
    onFinish: () => {
      syncing.value = false
    },
  })
}

function updateCase(caseId) {
  const form = caseForms.value[caseId]
  if (!form) return
  updatingCaseId.value = caseId
  router.post(`/customer-voice-command-center/cases/${caseId}/update`, {
    status: form.status,
    assigned_to: form.assigned_to || null,
  }, {
    preserveScroll: true,
    onFinish: () => {
      updatingCaseId.value = null
    },
  })
}

function addNote(caseId) {
  const note = window.prompt('Tulis catatan aktivitas:')
  if (!note || !String(note).trim()) return
  router.post(`/customer-voice-command-center/cases/${caseId}/note`, {
    note: String(note).trim(),
  }, {
    preserveScroll: true,
  })
}

function toggleActivities(caseId) {
  openedActivityCaseId.value = openedActivityCaseId.value === caseId ? null : caseId
}

function activitiesFor(caseId) {
  return Array.isArray(props.activities?.[caseId]) ? props.activities[caseId] : []
}

function applyFilters() {
  router.get('/customer-voice-command-center', {
    q: q.value,
    status: status.value,
    severity: severity.value,
    source_type: sourceType.value,
    id_outlet: idOutlet.value,
    overdue_only: overdueOnly.value ? 1 : 0,
  }, {
    preserveState: true,
    replace: true,
  })
}

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID')
}

function severityClass(sev) {
  const s = String(sev || '').toLowerCase()
  if (s === 'severe') return 'bg-rose-100 text-rose-700'
  if (s === 'negative') return 'bg-orange-100 text-orange-700'
  if (s === 'mild_negative') return 'bg-amber-100 text-amber-700'
  if (s === 'positive') return 'bg-emerald-100 text-emerald-700'
  return 'bg-slate-100 text-slate-700'
}

function isOpenStatus(statusValue) {
  const s = String(statusValue || '').toLowerCase()
  return s === 'new' || s === 'in_progress'
}

function slaLabel(row) {
  if (!row?.due_at) return 'Tanpa SLA'
  if (!isOpenStatus(row.status)) return 'Closed'
  const due = new Date(row.due_at).getTime()
  const nowTs = Date.now()
  if (Number.isNaN(due)) return 'SLA invalid'
  if (due < nowTs) return 'Overdue'
  const diffMs = due - nowTs
  const diffMin = Math.floor(diffMs / 60000)
  if (diffMin < 60) return `${diffMin}m tersisa`
  const diffHour = Math.floor(diffMin / 60)
  const remMin = diffMin % 60
  return `${diffHour}j ${remMin}m tersisa`
}

function slaClass(row) {
  const label = slaLabel(row)
  if (label === 'Overdue') return 'text-rose-700 font-semibold'
  if (label.includes('tersisa')) return 'text-amber-700 font-semibold'
  if (label === 'Closed') return 'text-emerald-700 font-semibold'
  return 'text-slate-500'
}
</script>
