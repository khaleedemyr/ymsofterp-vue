<template>
  <Head :title="`Customer Voice Case #${caseData.id}`" />

  <div class="min-h-screen bg-slate-100">
    <header class="border-b border-slate-200 bg-white shadow-sm">
      <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
        <div class="flex min-w-0 items-center gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-600 text-white">
            <i class="fa-solid fa-headset"></i>
          </div>
          <div class="min-w-0">
            <p class="text-xs uppercase tracking-wide text-slate-500">Customer Voice Command Center</p>
            <h1 class="truncate text-lg font-bold text-slate-900 sm:text-xl">Case #{{ caseData.id }}</h1>
          </div>
        </div>
        <span class="hidden shrink-0 text-xs text-slate-400 sm:inline">YMSoft ERP</span>
      </div>
    </header>

    <main class="mx-auto w-full max-w-5xl space-y-5 px-4 py-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
            {{ sourceLabel(caseData.source_type) }}
          </span>
          <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="severityClass(caseData.severity)">
            {{ caseData.severity || 'neutral' }}
          </span>
          <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(caseData.status)">
            {{ voiceCaseStatusLabel(caseData.status) }}
          </span>
          <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="followUpStatusClass(caseData.follow_up_status)">
            Follow-up: {{ followUpStatusLabel(caseData.follow_up_status) }}
          </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <InfoCard label="Outlet" :value="caseData.nama_outlet || '—'" />
          <InfoCard label="Waktu event" :value="formatDate(caseData.event_at)" />
          <InfoCard label="Tamu / penulis" :value="caseData.author_name || '—'" />
          <InfoCard label="Kontak" :value="caseData.customer_contact || '—'" />
          <InfoCard label="PIC complaint" :value="picLabel" />
          <InfoCard label="Risk score" :value="String(caseData.risk_score ?? 0)" />
        </div>

        <div v-if="complaintLabels.length" class="mt-4">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Jenis komplain</div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <span
              v-for="(lbl, idx) in complaintLabels"
              :key="`ct-${idx}`"
              class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-900"
            >
              {{ lbl }}
            </span>
          </div>
        </div>

        <div class="mt-4">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Ringkasan</div>
          <div class="mt-1 text-sm font-semibold text-slate-800">{{ caseData.summary_id || '—' }}</div>
        </div>

        <div class="mt-4">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Komentar asli</div>
          <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ caseData.raw_text || '—' }}</div>
        </div>
      </div>

      <div
        v-if="gcfCapaVisible"
        class="space-y-3 rounded-2xl border-2 border-emerald-200 bg-emerald-50/60 p-5 shadow-sm"
      >
        <div class="flex items-center gap-2">
          <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100">
            <i class="fa-solid fa-clipboard-check text-sm text-emerald-600"></i>
          </div>
          <div>
            <div class="text-sm font-bold text-emerald-900">CAPA dari Outlet Leader</div>
            <div class="text-[10px] text-emerald-700">
              Diisi saat verifikasi Guest Comment
              <template v-if="caseData.gcf_capa?.filled_by_name">
                oleh <span class="font-semibold">{{ caseData.gcf_capa.filled_by_name }}</span>
              </template>
              <template v-if="caseData.gcf_capa?.filled_at">
                · {{ formatDate(caseData.gcf_capa.filled_at) }}
              </template>
            </div>
          </div>
        </div>
        <CapaTextBlock label="Kronologi" :value="caseData.gcf_capa?.kronologi" />
        <CapaTextBlock label="Corrective Action" :value="caseData.gcf_capa?.corrective_action" />
        <CapaTextBlock label="Preventive Action" :value="caseData.gcf_capa?.preventive_action" />
      </div>

      <div v-if="visibleCapaSections.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-slate-900">CAPA</h2>
        <div class="space-y-5">
          <section
            v-for="(section, sIdx) in visibleCapaSections"
            :key="`capa-${sIdx}`"
            class="rounded-xl border border-slate-200 bg-slate-50/70 p-4"
          >
            <h3 class="text-sm font-bold text-slate-800">{{ section.bagian }}</h3>
            <dl class="mt-3 space-y-2">
              <div
                v-for="(item, iIdx) in section.items"
                :key="`capa-item-${sIdx}-${iIdx}`"
                class="grid gap-1 sm:grid-cols-[minmax(8rem,11rem)_1fr]"
              >
                <dt class="text-xs font-semibold text-slate-500">{{ item.field }}</dt>
                <dd class="whitespace-pre-wrap text-sm text-slate-800">{{ item.nilai }}</dd>
              </div>
            </dl>
          </section>
        </div>
      </div>

      <div v-if="capaEvidenceImages.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-slate-900">Lampiran CAPA</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="(img, idx) in capaEvidenceImages"
            :key="`evidence-${idx}`"
            class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
          >
            <div class="border-b border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700">{{ img.label || `Lampiran #${idx + 1}` }}</div>
            <div class="p-2">
              <img
                v-if="img.src"
                :src="img.src"
                :alt="img.label || 'Lampiran CAPA'"
                class="max-h-64 w-full rounded-lg object-contain"
              />
              <p v-else class="px-2 py-4 text-xs text-slate-500">{{ img.note || 'Pratinjau tidak tersedia' }}</p>
            </div>
          </div>
        </div>
      </div>

      <div v-if="activities.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-slate-900">Timeline aktivitas</h2>
        <div class="space-y-2">
          <div
            v-for="a in activities"
            :key="a.id"
            class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700"
          >
            <div class="font-semibold text-slate-800">
              {{ a.activity_type }}
              <span class="ml-1 font-normal text-slate-400">· {{ formatDate(a.created_at) }}</span>
            </div>
            <div v-if="a.actor_name" class="text-slate-500">oleh {{ a.actor_name }}</div>
            <div v-if="a.from_status || a.to_status" class="text-slate-500">
              {{ voiceCaseStatusLabel(a.from_status) }} → {{ voiceCaseStatusLabel(a.to_status) }}
            </div>
            <div v-if="a.note" class="mt-1 whitespace-pre-wrap">{{ a.note }}</div>
          </div>
        </div>
      </div>

      <p v-if="generatedAt" class="pb-4 text-center text-[11px] text-slate-400">
        Dibagikan pada {{ generatedAt }}
      </p>
    </main>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  case: { type: Object, required: true },
  capa_grouped_sections: { type: Array, default: () => [] },
  capa_evidence_images: { type: Array, default: () => [] },
  activities: { type: Array, default: () => [] },
  generated_at: { type: String, default: '' },
})

const caseData = computed(() => props.case || {})
const generatedAt = computed(() => props.generated_at || '')

const complaintLabels = computed(() => {
  const labels = caseData.value?.complaint_type_labels
  return Array.isArray(labels) ? labels : []
})

const picLabel = computed(() => {
  const name = caseData.value?.assigned_to_name
  const jabatan = caseData.value?.assigned_to_jabatan
  if (!name) return '—'
  return jabatan ? `${name} (${jabatan})` : name
})

const gcfCapaVisible = computed(() => {
  const capa = caseData.value?.gcf_capa
  if (!capa) return false
  return Boolean(capa.kronologi || capa.corrective_action || capa.preventive_action)
})

const capaEvidenceImages = computed(() => {
  return Array.isArray(props.capa_evidence_images) ? props.capa_evidence_images : []
})

const visibleCapaSections = computed(() => {
  const sections = Array.isArray(props.capa_grouped_sections) ? props.capa_grouped_sections : []
  return sections
    .map((section) => {
      const bagian = String(section?.bagian || '')
      if (/ringkas kasus/i.test(bagian)) {
        return null
      }
      const items = (section?.items || []).filter((item) => hasMeaningfulValue(item?.nilai))
      if (!items.length) {
        return null
      }
      return { bagian, items }
    })
    .filter(Boolean)
})

function hasMeaningfulValue(value) {
  if (value === null || value === undefined) return false
  const s = String(value).trim()
  return s !== '' && s !== '—' && s !== '-'
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString('id-ID')
}

function sourceLabel(source) {
  const s = String(source || '')
  if (s === 'google_review') return 'Google Review'
  if (s === 'instagram_comment') return 'Instagram Comment'
  if (s === 'guest_comment') return 'Guest Comment'
  return s || '—'
}

function voiceCaseStatusLabel(raw) {
  const s = String(raw || '').toLowerCase()
  const map = {
    new: 'New',
    internal_follow_up: 'Internal Follow Up',
    courtesy_done: 'Courtesy Done',
    courtesy_by_cs: 'Internal Follow Up',
    follow_up_by_ops: 'Internal Follow Up',
    done: 'Courtesy Done',
    in_progress: 'Internal Follow Up',
    resolved: 'Courtesy Done',
    ignored: 'Courtesy Done',
  }
  if (!s) return '—'
  return map[s] || raw
}

function followUpStatusLabel(raw) {
  const s = String(raw || '').toLowerCase()
  const map = { new: 'New', on_progress: 'On Progress', done: 'Done' }
  if (!s) return '—'
  return map[s] || raw
}

function severityClass(severity) {
  const s = String(severity || '').toLowerCase()
  if (s === 'critical' || s === 'severe') return 'bg-red-100 text-red-700'
  if (s === 'major' || s === 'negative') return 'bg-orange-100 text-orange-700'
  if (s === 'minor' || s === 'mild_negative') return 'bg-amber-100 text-amber-700'
  if (s === 'positive') return 'bg-emerald-100 text-emerald-700'
  return 'bg-slate-100 text-slate-700'
}

function statusClass(statusValue) {
  const s = String(statusValue || '').toLowerCase()
  if (s === 'courtesy_done' || s === 'done' || s === 'resolved' || s === 'ignored') return 'bg-emerald-100 text-emerald-700'
  if (s === 'internal_follow_up' || s === 'follow_up_by_ops' || s === 'in_progress' || s === 'courtesy_by_cs') return 'bg-indigo-100 text-indigo-700'
  return 'bg-amber-100 text-amber-700'
}

function followUpStatusClass(statusValue) {
  const s = String(statusValue || '').toLowerCase()
  if (s === 'done') return 'bg-emerald-100 text-emerald-700'
  if (s === 'on_progress') return 'bg-blue-100 text-blue-700'
  return 'bg-amber-100 text-amber-700'
}
</script>

<script>
export default {
  components: {
    InfoCard: {
      props: {
        label: { type: String, required: true },
        value: { type: String, default: '—' },
      },
      template: `
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
          <div class="text-[11px] uppercase tracking-wide text-slate-400">{{ label }}</div>
          <div class="mt-1 text-sm font-semibold text-slate-800 break-words">{{ value }}</div>
        </div>
      `,
    },
    CapaTextBlock: {
      props: {
        label: { type: String, required: true },
        value: { type: String, default: '' },
      },
      template: `
        <div class="rounded-lg border border-emerald-200 bg-white p-3">
          <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">{{ label }}</div>
          <div class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ value || '—' }}</div>
        </div>
      `,
    },
  },
}
</script>
