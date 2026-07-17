<template>
  <Head :title="`Customer Voice Case #${caseData.id}`" />

  <div class="min-h-screen bg-slate-100">
    <header class="border-b border-slate-200 bg-white shadow-sm">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
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

    <main class="mx-auto w-full max-w-6xl space-y-5 px-4 py-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ sourceLabel(caseData.source_type) }}</span>
          <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="severityClass(caseData.severity)">{{ caseData.severity || 'neutral' }}</span>
          <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(caseData.status)">{{ voiceCaseStatusLabel(caseData.status) }}</span>
          <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="followUpStatusClass(caseData.follow_up_status)">Follow-up: {{ followUpStatusLabel(caseData.follow_up_status) }}</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-[11px] uppercase tracking-wide text-slate-400">Outlet</div><div class="mt-1 text-sm font-semibold text-slate-800">{{ caseData.nama_outlet || '—' }}</div></div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-[11px] uppercase tracking-wide text-slate-400">Source Ref</div><div class="mt-1 break-all text-sm font-semibold text-slate-800">{{ caseData.source_ref || '—' }}</div></div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-[11px] uppercase tracking-wide text-slate-400">Waktu event</div><div class="mt-1 text-sm font-semibold text-slate-800">{{ formatDate(caseData.event_at) }}</div></div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-[11px] uppercase tracking-wide text-slate-400">Tamu / penulis</div><div class="mt-1 text-sm font-semibold text-slate-800">{{ caseData.author_name || '—' }}</div></div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-[11px] uppercase tracking-wide text-slate-400">Kontak</div><div class="mt-1 text-sm font-semibold text-slate-800">{{ caseData.customer_contact || '—' }}</div></div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-[11px] uppercase tracking-wide text-slate-400">PIC complaint</div><div class="mt-1 text-sm font-semibold text-slate-800">{{ picLabel }}</div></div>
        </div>

        <div v-if="complaintLabels.length" class="mt-4">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Jenis komplain</div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <span v-for="(lbl, idx) in complaintLabels" :key="`ct-${idx}`" class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-900">{{ lbl }}</span>
          </div>
        </div>

        <div class="mt-4">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Ringkasan</div>
          <div class="mt-1 whitespace-pre-wrap text-sm font-semibold text-slate-800">{{ caseData.summary_id || '—' }}</div>
        </div>
        <div class="mt-4">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Komentar asli</div>
          <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ caseData.raw_text || '—' }}</div>
        </div>
      </div>

      <div v-if="gcfCapaVisible" class="space-y-3 rounded-2xl border-2 border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
        <div class="flex items-center gap-2">
          <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100">
            <i class="fa-solid fa-clipboard-check text-sm text-emerald-600"></i>
          </div>
          <div>
            <div class="text-sm font-bold text-emerald-900">CAPA dari Outlet Leader</div>
            <div class="text-[10px] text-emerald-700">
              Diisi saat verifikasi Guest Comment
              <template v-if="caseData.gcf_capa?.filled_by_name"> oleh <span class="font-semibold">{{ caseData.gcf_capa.filled_by_name }}</span></template>
              <template v-if="caseData.gcf_capa?.filled_at"> · {{ formatDate(caseData.gcf_capa.filled_at) }}</template>
            </div>
          </div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-white p-3">
          <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Kronologi</div>
          <div class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ valueOrDash(caseData.gcf_capa?.kronologi) }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-white p-3">
          <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Corrective Action</div>
          <div class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ valueOrDash(caseData.gcf_capa?.corrective_action) }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-white p-3">
          <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Preventive Action</div>
          <div class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ valueOrDash(caseData.gcf_capa?.preventive_action) }}</div>
        </div>
      </div>

      <div v-if="allCapaSections.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-slate-900">CAPA Lengkap</h2>
        <div class="space-y-4">
          <section v-for="(section, sIdx) in allCapaSections" :key="`capa-${sIdx}`" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <h3 class="text-sm font-bold text-slate-800">{{ section.bagian || 'Bagian' }}</h3>
            <dl class="mt-3 space-y-2">
              <div v-for="(item, iIdx) in section.items" :key="`capa-item-${sIdx}-${iIdx}`" class="grid gap-1 sm:grid-cols-[minmax(8rem,13rem)_1fr]">
                <dt class="text-xs font-semibold text-slate-500">{{ item.field || 'Field' }}</dt>
                <dd class="whitespace-pre-wrap text-sm text-slate-800">{{ valueOrDash(item.nilai) }}</dd>
              </div>
            </dl>
          </section>
        </div>
      </div>

      <div v-if="allAttachments.length || capaEvidenceImages.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-slate-900">Lampiran CAPA</h2>

        <div v-if="allAttachments.length" class="mb-4 space-y-2">
          <a
            v-for="(att, idx) in allAttachments"
            :key="`att-${idx}-${att.url}`"
            :href="att.url"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm hover:bg-slate-100"
          >
            <span class="min-w-0 truncate text-slate-700">{{ att.label }}</span>
            <span class="text-xs text-slate-500">Buka</span>
          </a>
        </div>

        <div v-if="capaEvidenceImages.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <button
            v-for="(img, idx) in capaEvidenceImages"
            :key="`img-${idx}`"
            type="button"
            class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 text-left hover:border-violet-300"
            @click="openLightbox(img)"
          >
            <div class="border-b border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700">{{ img.label || `Lampiran #${idx + 1}` }}</div>
            <div class="p-2">
              <img v-if="img.src" :src="img.src" :alt="img.label || 'Lampiran CAPA'" class="max-h-64 w-full rounded-lg object-contain" />
              <p v-else class="px-2 py-4 text-xs text-slate-500">{{ img.note || 'Pratinjau tidak tersedia' }}</p>
            </div>
          </button>
        </div>
      </div>

      <div v-if="activities.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-slate-900">Timeline aktivitas</h2>
        <div class="space-y-2">
          <div v-for="a in activities" :key="a.id" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
            <div class="font-semibold text-slate-800">{{ a.activity_type }} <span class="ml-1 font-normal text-slate-400">· {{ formatDate(a.created_at) }}</span></div>
            <div v-if="a.actor_name" class="text-slate-500">oleh {{ a.actor_name }}</div>
            <div v-if="a.from_status || a.to_status" class="text-slate-500">{{ voiceCaseStatusLabel(a.from_status) }} → {{ voiceCaseStatusLabel(a.to_status) }}</div>
            <div v-if="a.note" class="mt-1 whitespace-pre-wrap">{{ a.note }}</div>
          </div>
        </div>
      </div>

      <p v-if="generatedAt" class="pb-4 text-center text-[11px] text-slate-400">Dibagikan pada {{ generatedAt }}</p>
    </main>

    <div v-if="lightboxImage" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click.self="closeLightbox">
      <div class="relative w-full max-w-6xl">
        <button type="button" class="absolute right-0 top-0 rounded-lg bg-white/90 px-3 py-1 text-sm font-semibold text-slate-700" @click="closeLightbox">Tutup</button>
        <img :src="lightboxImage.src" :alt="lightboxImage.label || 'Lampiran CAPA'" class="mx-auto max-h-[90vh] w-auto max-w-full rounded-lg bg-white object-contain" />
        <p v-if="lightboxImage.label" class="mt-2 text-center text-xs text-slate-200">{{ lightboxImage.label }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  case: { type: Object, required: true },
  capa_grouped_sections: { type: Array, default: () => [] },
  capa_evidence_images: { type: Array, default: () => [] },
  activities: { type: Array, default: () => [] },
  generated_at: { type: String, default: '' },
})

const caseData = computed(() => props.case || {})
const generatedAt = computed(() => props.generated_at || '')
const lightboxImage = ref(null)

const complaintLabels = computed(() => (Array.isArray(caseData.value?.complaint_type_labels) ? caseData.value.complaint_type_labels : []))
const capaEvidenceImages = computed(() => (Array.isArray(props.capa_evidence_images) ? props.capa_evidence_images.filter((x) => x?.src) : []))
const allCapaSections = computed(() => (Array.isArray(props.capa_grouped_sections) ? props.capa_grouped_sections : []))

const picLabel = computed(() => {
  const name = caseData.value?.assigned_to_name
  const jabatan = caseData.value?.assigned_to_jabatan
  if (!name) return '—'
  return jabatan ? `${name} (${jabatan})` : name
})

const gcfCapaVisible = computed(() => Boolean(caseData.value?.gcf_capa))

const allAttachments = computed(() => {
  const out = []
  const pushEvidence = (divisionLabel, capa) => {
    const evidence = Array.isArray(capa?.evidence) ? capa.evidence : []
    for (const file of evidence) {
      const url = String(file?.url || '')
      if (!url) continue
      const name = String(file?.original_name || file?.path || 'Attachment')
      out.push({ label: `${divisionLabel} - ${name}`, url })
    }
  }

  const active = caseData.value?.capa
  if (active) pushEvidence('CAPA Aktif', active)
  const divisions = caseData.value?.capa_divisions || {}
  pushEvidence('Service', divisions?.service)
  pushEvidence('Kitchen', divisions?.kitchen)
  pushEvidence('Bar', divisions?.bar)

  const seen = new Set()
  return out.filter((x) => {
    if (seen.has(x.url)) return false
    seen.add(x.url)
    return true
  })
})

function openLightbox(img) {
  if (!img?.src) return
  lightboxImage.value = { src: img.src, label: img.label || '' }
}

function closeLightbox() {
  lightboxImage.value = null
}

function valueOrDash(value) {
  const v = String(value ?? '').trim()
  return v !== '' ? v : '—'
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
  if (s === 'manual_cs') return 'Input CS Manual'
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
