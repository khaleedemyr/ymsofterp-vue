<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div v-if="$page.props.flash?.success" class="mb-4 p-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm">
        {{ $page.props.flash.success }}
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
        <div class="grid gap-3 md:grid-cols-6">
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
              <th class="text-left p-3">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!cases.data?.length">
              <td colspan="7" class="p-4 text-center text-slate-400">Belum ada case.</td>
            </tr>
            <tr v-for="row in cases.data" :key="row.id" class="border-t border-slate-100 align-top">
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
              <td class="p-3">{{ row.status }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  cases: { type: Object, default: () => ({ data: [] }) },
  outlets: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const syncing = ref(false)
const q = ref(props.filters?.q || '')
const status = ref(props.filters?.status || '')
const severity = ref(props.filters?.severity || '')
const sourceType = ref(props.filters?.source_type || '')
const idOutlet = ref(props.filters?.id_outlet ? String(props.filters.id_outlet) : '')

function syncNow() {
  syncing.value = true
  router.post('/customer-voice-command-center/sync', {}, {
    preserveScroll: true,
    onFinish: () => {
      syncing.value = false
    },
  })
}

function applyFilters() {
  router.get('/customer-voice-command-center', {
    q: q.value,
    status: status.value,
    severity: severity.value,
    source_type: sourceType.value,
    id_outlet: idOutlet.value,
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
</script>
