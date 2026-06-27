<template>
  <div class="capa-form touch-manipulation space-y-4 pb-2 selection:bg-indigo-100">
    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white px-4 py-4 shadow-sm">
      <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Form CAPA</p>
      <h2 class="mt-1 text-base font-bold leading-snug text-slate-900">Customer Complaint — Corrective &amp; Preventive Action</h2>
      <p class="mt-2 text-xs leading-relaxed text-slate-600">Isi per divisi (Service / Kitchen / Bar). Lampiran bukti tetap wajib jika ada dokumen pendukung.</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-2">
      <div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Divisi CAPA aktif</div>
      <div class="mt-1 text-xs font-semibold text-indigo-900">{{ divisionLabel(activeDivision) }}</div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="mb-3 flex items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-slate-900">List CAPA</h3>
        <span class="text-[11px] text-slate-500">Tidak wajib semua divisi terisi.</span>
      </div>
      <div class="space-y-2">
        <div
          v-for="row in capaRows"
          :key="row.id"
          class="flex items-center justify-between rounded-xl border px-3 py-2"
          :class="row.id === activeDivision ? 'border-indigo-300 bg-indigo-50/60' : 'border-slate-200 bg-white'"
        >
          <div class="min-w-0">
            <div class="text-xs font-semibold text-slate-800">{{ row.label }}</div>
            <div class="text-[11px] text-slate-500">{{ row.filled ? 'Sudah ada data CAPA' : 'Belum ada data CAPA' }}</div>
          </div>
          <div class="flex items-center gap-1.5">
            <button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50" @click="switchDivision(row.id)">
              {{ row.id === activeDivision ? 'Editing' : 'Show/Edit' }}
            </button>
            <button type="button" class="rounded-md border border-rose-200 px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50" :disabled="!row.filled" @click="removeDivision(row.id)">
              Hapus
            </button>
          </div>
        </div>
      </div>
    </section>

    <div v-if="pendingApproverSelf" class="rounded-2xl border border-violet-300 bg-gradient-to-r from-violet-50 to-indigo-50 px-4 py-3 shadow-sm">
      <p class="text-xs font-medium text-violet-950">
        <i class="fa fa-user-check mr-1.5 text-violet-600" aria-hidden="true" />
        CAPA divisi <strong>{{ divisionLabel(activeDivision) }}</strong> menunggu approval Anda.
      </p>
    </div>

    <!-- 1. General Information -->
    <section id="capa-general" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">1. General Information</h3>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Date
          <input v-model="local.a.complaint_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Time
          <input v-model="local.a.complaint_time" type="time" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
          <div class="text-[11px] uppercase tracking-wide text-slate-500">Outlet Name</div>
          <div class="mt-1 text-sm font-semibold text-slate-900">{{ outletDisplay }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
          <div class="text-[11px] uppercase tracking-wide text-slate-500">Location / Channel</div>
          <div class="mt-1 text-sm font-semibold text-slate-900">{{ sourceLabel }}</div>
        </div>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Reported By
          <input v-model="local.a.reported_by" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Nama pelapor" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Position
          <input v-model="local.a.reported_by_position" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Jabatan / posisi" />
        </label>
      </div>
    </section>

    <!-- 2. Issue Details -->
    <section id="capa-issue" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">2. Issue Details</h3>
      <p class="mt-1 text-[11px] text-slate-600">Type of Issue</p>
      <div class="mt-2 flex flex-wrap gap-2">
        <label v-for="opt in complaintTypes" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800">
          <input type="checkbox" class="rounded border-slate-300 text-indigo-600" :checked="hasType(opt.v)" @change="toggleType(opt.v)" />
          {{ opt.label }}
        </label>
      </div>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Description
        <textarea v-model="local.b.description" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Area / Section
          <input v-model="local.b.area_section" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Involved Parties
          <input v-model="local.b.involved_parties" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="sm:col-span-2 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Witness(es)
          <textarea v-model="local.b.witnesses" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
      </div>
    </section>

    <!-- Evidence -->
    <section id="capa-evidence" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">Lampiran bukti &amp; dokumen</h3>
      <p class="mt-1 text-[11px] text-slate-600">Maks. 20 file, per file ±15 MB.</p>
      <p v-if="evidenceError" class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ evidenceError }}</p>
      <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 disabled:opacity-50" :disabled="uploadingEvidence || evidenceFull" @click="triggerCameraInput">Ambil foto</button>
        <button type="button" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-900 disabled:opacity-50" :disabled="uploadingEvidence || evidenceFull" @click="triggerFilePicker">Pilih file / galeri</button>
      </div>
      <input ref="cameraInputRef" type="file" class="hidden" accept="image/*" capture="environment" @change="onEvidenceFiles" />
      <input ref="pickerInputRef" type="file" class="hidden" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,application/pdf" @change="onEvidenceFiles" />
      <ul v-if="(local.evidence || []).length" class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
        <li v-for="ev in local.evidence" :key="ev.id" class="flex flex-wrap items-center justify-between gap-2 py-3">
          <a v-if="ev.url" :href="ev.url" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-medium text-indigo-700 underline">{{ ev.original_name || 'Lampiran' }}</a>
          <span v-else class="break-all text-sm text-slate-700">{{ ev.original_name || 'Lampiran' }}</span>
          <button type="button" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700" :disabled="uploadingEvidence" @click="removeEvidence(ev.id)">Hapus</button>
        </li>
      </ul>
      <p v-else class="mt-3 text-xs text-slate-400">Belum ada lampiran.</p>
    </section>

    <!-- 3. Action Taken -->
    <section id="capa-action" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">3. Action Taken</h3>
      <p class="mt-2 text-[11px] font-semibold text-slate-700">Immediate Action</p>
      <div class="mt-2 flex flex-wrap gap-2">
        <label v-for="opt in immediateActions" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800">
          <input type="checkbox" class="rounded border-slate-300 text-indigo-600" :checked="hasAction(opt.v)" @change="toggleAction(opt.v)" />
          {{ opt.label }}
        </label>
      </div>
      <label v-if="local.c.actions?.includes('other')" class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Lainnya
        <input v-model="local.c.actions_other" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
      <label class="mt-4 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Follow-Up Action
        <textarea v-model="local.e.action" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Status
          <select v-model="local.e.status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            <option value="open">Open</option>
            <option value="on_progress">On Progress</option>
            <option value="closed">Closed</option>
          </select>
        </label>
        <div class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Follow Up By
          <CapaUserPicker v-model="local.e.pic_user_id" :assignees="assigneesMerged" placeholder="Cari PIC…" class="mt-1 block" />
        </div>
      </div>
    </section>

    <!-- 4. Preventive Measures -->
    <section id="capa-preventive" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">4. Preventive Measures</h3>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Corrective Action Plan
        <textarea v-model="local.f.action" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <div class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Responsible Person
          <CapaUserPicker v-model="local.f.pic_user_id" :assignees="assigneesMerged" placeholder="Cari PIC…" class="mt-1 block" />
        </div>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Target Completion Date
          <input v-model="local.f.timeline" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
      </div>
    </section>

    <!-- Approval -->
    <section id="capa-approval" class="scroll-mt-28 rounded-2xl border border-violet-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">Approval</h3>
      <p class="mt-1 text-[11px] text-slate-600">Pilih approver berurutan (level 1 → terakhir), pola sama dengan PO Ops.</p>

      <div v-if="approvalFlows.length" class="mt-3 space-y-2">
        <div v-for="flow in approvalFlows" :key="flow.id" class="flex items-center justify-between rounded-lg border px-3 py-2 text-xs" :class="approvalFlowClass(flow.status)">
          <div>
            <span class="font-bold">Level {{ flow.approval_level }}</span>
            — {{ flow.approver?.nama_lengkap || `User #${flow.approver_id}` }}
          </div>
          <span class="font-semibold uppercase">{{ flow.status }}</span>
        </div>
      </div>

      <div v-if="canManageApprovers" class="mt-4">
        <div class="relative">
          <input
            v-model="approverSearch"
            type="text"
            placeholder="Cari approver (nama / email / jabatan)…"
            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
            @input="onApproverSearch"
          />
          <div v-if="showApproverDropdown && approverResults.length" class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
            <button
              v-for="user in approverResults"
              :key="user.id"
              type="button"
              class="block w-full border-b border-slate-100 px-3 py-2 text-left text-sm hover:bg-slate-50 last:border-0"
              @click="addApprover(user)"
            >
              <div class="font-medium">{{ user.name }}</div>
              <div v-if="user.jabatan" class="text-[11px] text-indigo-600">{{ user.jabatan }}</div>
            </button>
          </div>
        </div>
        <div v-if="selectedApprovers.length" class="mt-3 space-y-2">
          <div v-for="(ap, idx) in selectedApprovers" :key="ap.id" class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
            <span><strong>L{{ idx + 1 }}</strong> — {{ ap.name }}</span>
            <div class="flex gap-1">
              <button v-if="idx > 0" type="button" class="rounded border px-2 py-0.5 text-xs" @click="reorderApprover(idx, idx - 1)">↑</button>
              <button v-if="idx < selectedApprovers.length - 1" type="button" class="rounded border px-2 py-0.5 text-xs" @click="reorderApprover(idx, idx + 1)">↓</button>
              <button type="button" class="rounded border border-rose-200 px-2 py-0.5 text-xs text-rose-700" @click="removeApprover(idx)">×</button>
            </div>
          </div>
          <button type="button" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="submittingApproval || !selectedApprovers.length" @click="submitApproval">
            {{ submittingApproval ? 'Mengajukan…' : 'Ajukan Approval' }}
          </button>
        </div>
      </div>

      <div v-if="pendingApproverSelf" class="mt-4 rounded-xl border border-violet-200 bg-violet-50 p-3">
        <label class="block text-[11px] font-semibold uppercase text-slate-600">Komentar (opsional)</label>
        <textarea v-model="approvalComments" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        <div class="mt-3 flex flex-wrap gap-2">
          <button type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="actingApproval" @click="actApproval(true)">Approve</button>
          <button type="button" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="actingApproval" @click="actApproval(false)">Reject</button>
        </div>
      </div>
    </section>

    <div class="sticky bottom-0 flex flex-col gap-2 border-t border-slate-200 bg-white/95 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
      <button type="button" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-800 disabled:opacity-60" :disabled="saving || deleting" @click="askDeleteStoredCapa">Hapus data CAPA</button>
      <div class="flex gap-2">
        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700" :disabled="saving || deleting" @click="$emit('reset')">Batalkan</button>
        <button type="button" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60" :disabled="saving || deleting" @click="submit">Simpan form CAPA</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import CapaUserPicker from '@/Pages/CustomerVoiceCommandCenter/CapaUserPicker.vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  caseId: { type: Number, required: true },
  initialCapa: { type: Object, default: () => ({}) },
  initialCapaDivisions: { type: Object, default: () => ({}) },
  activeDivision: { type: String, default: 'service' },
  approvalSummaries: { type: Object, default: () => ({}) },
  sourceType: { type: String, default: '' },
  sourceComplaintTopics: { type: Array, default: () => [] },
  sourceComplaintText: { type: String, default: '' },
  outletName: { type: String, default: '' },
  saving: { type: Boolean, default: false },
  assignees: { type: Array, default: () => [] },
  authUser: { type: Object, default: null },
  deleting: { type: Boolean, default: false },
  focusSectionId: { type: String, default: null },
})

const emit = defineEmits(['save', 'reset', 'delete-capa', 'focused-section', 'dirty-changed', 'approval-changed'])

const divisions = [
  { id: 'service', label: 'Service' },
  { id: 'kitchen', label: 'Kitchen' },
  { id: 'bar', label: 'Bar' },
]

const activeDivision = ref('service')
const divisionDrafts = ref({ service: ensureShape({}), kitchen: ensureShape({}), bar: ensureShape({}) })
const local = ref(ensureShape({}))
const approvalSummariesLocal = ref({ service: emptyApproval(), kitchen: emptyApproval(), bar: emptyApproval() })

const cameraInputRef = ref(null)
const pickerInputRef = ref(null)
const uploadingEvidence = ref(false)
const evidenceError = ref('')
const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)
const selectedApprovers = ref([])
const submittingApproval = ref(false)
const actingApproval = ref(false)
const approvalComments = ref('')

let searchTimer = null

const outletDisplay = computed(() => (props.outletName || '').trim() || '—')
const sourceLabel = computed(() => {
  const s = String(props.sourceType || '').toLowerCase()
  if (s === 'google_review') return 'Google Review'
  if (s === 'instagram_comment') return 'Instagram'
  if (s === 'guest_comment') return 'Guest Comment'
  return '—'
})

const assigneesMerged = computed(() => {
  const base = [...(props.assignees || [])]
  const au = props.authUser
  if (au?.id != null && !base.some((x) => x.id === au.id)) {
    base.unshift({ id: au.id, nama_lengkap: au.nama_lengkap || '', nama_jabatan: au.nama_jabatan ?? null })
  }
  return base
})

const capaRows = computed(() => divisions.map((d) => ({ id: d.id, label: d.label, filled: isDivisionFilled(d.id) })))
const evidenceFull = computed(() => (local.value.evidence || []).length >= 20)

const activeApproval = computed(() => approvalSummariesLocal.value[activeDivision.value] || emptyApproval())
const approvalFlows = computed(() => activeApproval.value.flows || [])
const canManageApprovers = computed(() => {
  const s = activeApproval.value.state
  return s === 'none' || s === 'rejected' || activeApproval.value.can_resubmit
})
const pendingApproverSelf = computed(() => {
  const uid = Number(props.authUser?.id || 0)
  if (!uid) return false
  return activeApproval.value.state === 'pending' && Number(activeApproval.value.next_approver_id) === uid
})

watch(
  () => [props.initialCapa, props.initialCapaDivisions, props.activeDivision, props.approvalSummaries],
  () => {
    const next = {
      service: ensureShape(props.initialCapa && typeof props.initialCapa === 'object' ? JSON.parse(JSON.stringify(props.initialCapa)) : {}),
      kitchen: ensureShape({}),
      bar: ensureShape({}),
    }
    if (props.initialCapaDivisions && typeof props.initialCapaDivisions === 'object') {
      for (const d of ['service', 'kitchen', 'bar']) {
        if (props.initialCapaDivisions[d]) next[d] = ensureShape(JSON.parse(JSON.stringify(props.initialCapaDivisions[d])))
      }
    }
    divisionDrafts.value = next
    activeDivision.value = ['service', 'kitchen', 'bar'].includes(String(props.activeDivision || '').toLowerCase())
      ? String(props.activeDivision).toLowerCase()
      : 'service'
    local.value = ensureShape(JSON.parse(JSON.stringify(divisionDrafts.value[activeDivision.value] || {})))
    seedFromSource(local.value)
    approvalSummariesLocal.value = {
      service: { ...emptyApproval(), ...(props.approvalSummaries?.service || {}) },
      kitchen: { ...emptyApproval(), ...(props.approvalSummaries?.kitchen || {}) },
      bar: { ...emptyApproval(), ...(props.approvalSummaries?.bar || {}) },
    }
    selectedApprovers.value = []
  },
  { immediate: true, deep: true },
)

watch(activeDivision, (next, prev) => {
  if (prev && divisionDrafts.value[prev]) {
    divisionDrafts.value[prev] = ensureShape(JSON.parse(JSON.stringify(local.value)))
  }
  local.value = ensureShape(JSON.parse(JSON.stringify(divisionDrafts.value[next] || {})))
  selectedApprovers.value = []
})

function emptyApproval() {
  return { state: 'none', flows: [], next_approver_id: null, can_submit: true, can_resubmit: false }
}

function seedFromSource(merged) {
  merged.b.types = sourceComplaintTypeBadges.value.map((x) => x.key)
  merged.b.description = sourceComplaintDescription.value || merged.b.description
  if (!merged.a.reported_by && props.authUser?.nama_lengkap) {
    merged.a.reported_by = props.authUser.nama_lengkap
  }
  if (!merged.a.reported_by_position && props.authUser?.nama_jabatan) {
    merged.a.reported_by_position = props.authUser.nama_jabatan
  }
}

const sourceComplaintTypeBadges = computed(() => {
  const keyMap = {
    food_quality: 'food_quality', service: 'service', hygiene: 'cleanliness', cleanliness: 'cleanliness',
    wait_time: 'waiting_time', waiting_time: 'waiting_time', billing: 'billing', price: 'billing', other: 'other',
  }
  const out = []
  const seen = new Set()
  for (const t of props.sourceComplaintTopics || []) {
    const mapped = keyMap[String(t || '').toLowerCase()] || 'other'
    if (seen.has(mapped)) continue
    seen.add(mapped)
    out.push({ key: mapped })
  }
  return out.length ? out : [{ key: 'other' }]
})
const sourceComplaintDescription = computed(() => String(props.sourceComplaintText || '').trim())

function divisionLabel(div) {
  return divisions.find((d) => d.id === div)?.label || String(div)
}

function ensureShape(src) {
  const base = {
    a: { complaint_date: null, complaint_time: null, guest_name: null, channel: null, channel_other: null, reported_by: null, reported_by_position: null },
    b: { types: [], types_other: null, description: null, area_section: null, involved_parties: null, witnesses: null },
    c: { actions: [], actions_other: null, response_time_note: null, pic_user_id: null },
    e: { action: null, pic_user_id: null, deadline: null, status: 'open' },
    f: { action: null, improvement_areas: [], pic_user_id: null, timeline: null, kpi: null },
    evidence: [],
  }
  return deepMerge(base, src || {})
}

function deepMerge(target, src) {
  const out = { ...target }
  for (const k of Object.keys(src || {})) {
    const v = src[k]
    if (v !== null && typeof v === 'object' && !Array.isArray(v) && typeof out[k] === 'object' && out[k] !== null && !Array.isArray(out[k])) {
      out[k] = deepMerge(out[k], v)
    } else {
      out[k] = v
    }
  }
  return out
}

function isDivisionFilled(div) {
  return JSON.stringify(divisionDrafts.value?.[div] || {}) !== JSON.stringify(ensureShape({}))
}

function switchDivision(div) {
  if (['service', 'kitchen', 'bar'].includes(div)) activeDivision.value = div
}

async function removeDivision(div) {
  if (!isDivisionFilled(div)) return
  const res = await Swal.fire({ icon: 'warning', title: `Hapus CAPA ${divisionLabel(div)}?`, showCancelButton: true })
  if (!res.isConfirmed) return
  divisionDrafts.value[div] = ensureShape({})
  if (activeDivision.value === div) local.value = ensureShape({})
}

function askDeleteStoredCapa() {
  emit('delete-capa')
}

const complaintTypes = [
  { v: 'food_quality', label: 'Food Quality' },
  { v: 'service', label: 'Service' },
  { v: 'cleanliness', label: 'Cleanliness' },
  { v: 'waiting_time', label: 'Waiting Time' },
  { v: 'billing', label: 'Billing' },
  { v: 'other', label: 'Others' },
]
const immediateActions = [
  { v: 'apology', label: 'Apology' },
  { v: 'replace_product', label: 'Replace product' },
  { v: 'refund_discount', label: 'Refund / Discount' },
  { v: 'escalate', label: 'Escalate' },
  { v: 'other', label: 'Lainnya' },
]

function hasType(v) { return (local.value.b.types || []).includes(v) }
function toggleType(v) {
  const arr = [...(local.value.b.types || [])]
  const i = arr.indexOf(v)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(v)
  local.value.b.types = arr
}
function hasAction(v) { return (local.value.c.actions || []).includes(v) }
function toggleAction(v) {
  const arr = [...(local.value.c.actions || [])]
  const i = arr.indexOf(v)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(v)
  local.value.c.actions = arr
}

function submit() {
  divisionDrafts.value[activeDivision.value] = ensureShape(JSON.parse(JSON.stringify(local.value)))
  emit('save', { division: activeDivision.value, capa: JSON.parse(JSON.stringify(local.value)) })
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function triggerCameraInput() { cameraInputRef.value?.click() }
function triggerFilePicker() { pickerInputRef.value?.click() }

async function onEvidenceFiles(ev) {
  const file = ev.target.files?.[0]
  ev.target.value = ''
  if (!file || !props.caseId) return
  uploadingEvidence.value = true
  evidenceError.value = ''
  try {
    const fd = new FormData()
    fd.append('file', file)
    const res = await fetch(`/customer-voice-command-center/cases/${props.caseId}/capa/evidence`, {
      method: 'POST', body: fd, credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) { evidenceError.value = data.message || 'Upload gagal.'; return }
    if (!Array.isArray(local.value.evidence)) local.value.evidence = []
    local.value.evidence.push(data.item)
  } catch { evidenceError.value = 'Upload gagal.' }
  finally { uploadingEvidence.value = false }
}

async function removeEvidence(evidenceId) {
  if (!props.caseId || !evidenceId) return
  uploadingEvidence.value = true
  try {
    const res = await fetch(`/customer-voice-command-center/cases/${props.caseId}/capa/evidence/${encodeURIComponent(evidenceId)}`, {
      method: 'DELETE', credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    })
    const data = await res.json().catch(() => ({}))
    if (res.ok && data.success) local.value.evidence = (local.value.evidence || []).filter((x) => x.id !== evidenceId)
  } finally { uploadingEvidence.value = false }
}

function onApproverSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadApprovers, 300)
}

async function loadApprovers() {
  const q = approverSearch.value.trim()
  if (q.length < 2) { approverResults.value = []; showApproverDropdown.value = false; return }
  try {
    const { data } = await axios.get(route('customer-voice-command-center.capa.approvers'), { params: { search: q } })
    approverResults.value = data.users || []
    showApproverDropdown.value = approverResults.value.length > 0
  } catch { approverResults.value = [] }
}

function addApprover(user) {
  if (!selectedApprovers.value.find((a) => a.id === user.id)) selectedApprovers.value.push(user)
  approverSearch.value = ''
  showApproverDropdown.value = false
}

function removeApprover(index) { selectedApprovers.value.splice(index, 1) }
function reorderApprover(from, to) {
  const item = selectedApprovers.value.splice(from, 1)[0]
  selectedApprovers.value.splice(to, 0, item)
}

function approvalFlowClass(status) {
  if (status === 'APPROVED') return 'border-emerald-200 bg-emerald-50'
  if (status === 'REJECTED') return 'border-rose-200 bg-rose-50'
  return 'border-amber-200 bg-amber-50'
}

async function submitApproval() {
  if (!selectedApprovers.value.length) return
  submittingApproval.value = true
  try {
    const { data } = await axios.post(
      route('customer-voice-command-center.cases.capa.submit-approval', props.caseId),
      { division: activeDivision.value, approvers: selectedApprovers.value.map((a) => a.id) },
      { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } },
    )
    if (!data.success) throw new Error(data.message)
    approvalSummariesLocal.value[activeDivision.value] = data.summary
    selectedApprovers.value = []
    emit('approval-changed')
    await Swal.fire({ icon: 'success', title: 'Diajukan', timer: 1200, showConfirmButton: false })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: e?.response?.data?.message || e.message })
  } finally { submittingApproval.value = false }
}

async function actApproval(approved) {
  actingApproval.value = true
  try {
    const { data } = await axios.post(
      route('customer-voice-command-center.cases.capa.approve', props.caseId),
      { division: activeDivision.value, approved, comments: approvalComments.value || null },
      { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } },
    )
    if (!data.success) throw new Error(data.message)
    approvalSummariesLocal.value[activeDivision.value] = data.summary
    approvalComments.value = ''
    emit('approval-changed')
    await Swal.fire({ icon: 'success', title: approved ? 'Disetujui' : 'Ditolak', timer: 1200, showConfirmButton: false })
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: e?.response?.data?.message || e.message })
  } finally { actingApproval.value = false }
}
</script>
