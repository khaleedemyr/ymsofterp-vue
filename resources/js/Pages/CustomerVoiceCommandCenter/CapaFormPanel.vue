<template>
  <div class="capa-form space-y-4">
    <div class="rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50/90 to-white px-4 py-3">
      <p class="text-xs font-bold uppercase tracking-wide text-indigo-800">CAPA</p>
      <p class="mt-0.5 text-sm font-semibold text-slate-900">Corrective &amp; preventive action plan</p>
      <p class="mt-1 text-[11px] leading-snug text-slate-600">
        Diisi untuk dokumentasi penanganan komplain; data disimpan di meta kasus. Kotak centang multi-pilihan; tanggal &amp; teks akan disaring di server.
      </p>
    </div>

    <!-- A -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 class="text-sm font-bold text-slate-900">A. Informasi umum</h4>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Tanggal complaint
          <input v-model="local.a.complaint_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Waktu
          <input v-model="local.a.complaint_time" type="time" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          Nama tamu (opsional)
          <input v-model="local.a.guest_name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" placeholder="Sesuai tamu / penulis" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          Channel complaint
          <select v-model="local.a.channel" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
            <option :value="null">— pilih —</option>
            <option value="dine_in">Dine-in</option>
            <option value="online_review">Online review</option>
            <option value="delivery">Delivery</option>
            <option value="walk_in">Walk-in</option>
            <option value="other">Lainnya</option>
          </select>
        </label>
        <label v-if="local.a.channel === 'other'" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          Channel (lainnya)
          <input v-model="local.a.channel_other" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          PIC penerima complaint
          <input v-model="local.a.pic_receiver_name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" />
        </label>
      </div>
      <p class="mt-3 text-[11px] text-slate-500">Outlet &amp; tanggal kejadian utama sudah tercermin di kartu ringkas di atas (dari data kasus).</p>
    </section>

    <!-- B -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 class="text-sm font-bold text-slate-900">B. Detail complaint</h4>
      <p class="mt-1 text-[11px] text-slate-500">Jenis — centang semua yang relevan</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <label v-for="opt in complaintTypes" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-white">
          <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasType(opt.v)" @change="toggleType(opt.v)" />
          {{ opt.label }}
        </label>
      </div>
      <label v-if="local.b.types?.includes('other')" class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Lainnya (jelaskan)
        <input v-model="local.b.types_other" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
      <label class="mt-4 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Deskripsi / kronologi objektif
        <textarea v-model="local.b.description" rows="5" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm leading-relaxed outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" placeholder="Ringkas fakta, tanpa menyalahkan…" />
      </label>
    </section>

    <!-- C -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 class="text-sm font-bold text-slate-900">C. Tindakan langsung</h4>
      <div class="mt-3 flex flex-wrap gap-2">
        <label v-for="opt in immediateActions" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-white">
          <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasAction(opt.v)" @change="toggleAction(opt.v)" />
          {{ opt.label }}
        </label>
      </div>
      <label v-if="local.c.actions?.includes('other')" class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Lainnya
        <input v-model="local.c.actions_other" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Waktu respon (catatan)
          <input v-model="local.c.response_time_note" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="mis. di bawah 5 menit" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          PIC
          <input v-model="local.c.pic" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
      </div>
    </section>

    <!-- D -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 class="text-sm font-bold text-slate-900">D. Root cause analysis</h4>
      <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
        <input v-model="local.d.use_fishbone" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
        Gunakan kerangka fishbone (Man · Method · Machine · Material · Measurement · Environment)
      </label>
      <div class="mt-4 grid gap-3">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Man (SDM)
          <textarea v-model="local.d.man" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Skill, attitude, staffing…" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Method (SOP)
          <textarea v-model="local.d.method" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Machine (peralatan)
          <textarea v-model="local.d.machine" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Material (bahan)
          <textarea v-model="local.d.material" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Measurement
          <textarea v-model="local.d.measurement" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Environment
          <textarea v-model="local.d.environment" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Akar masalah utama (ringkas)
          <textarea v-model="local.d.root_cause_summary" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
      </div>
    </section>

    <!-- E -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 class="text-sm font-bold text-slate-900">E. Corrective action (jangka pendek)</h4>
      <div class="mt-3 grid gap-3">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Action
          <textarea v-model="local.e.action" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            PIC
            <input v-model="local.e.pic" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </label>
          <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Deadline
            <input v-model="local.e.deadline" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </label>
        </div>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Status
          <select v-model="local.e.status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            <option value="open">Open</option>
            <option value="on_progress">On progress</option>
            <option value="closed">Closed</option>
          </select>
        </label>
      </div>
    </section>

    <!-- F -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 class="text-sm font-bold text-slate-900">F. Preventive action (jangka panjang)</h4>
      <label class="mt-1 block text-[11px] text-slate-500">Area improvement</label>
      <div class="mt-2 flex flex-wrap gap-2">
        <label v-for="opt in improvementAreas" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-white">
          <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasImprovement(opt.v)" @change="toggleImprovement(opt.v)" />
          {{ opt.label }}
        </label>
      </div>
      <div class="mt-4 grid gap-3">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Action
          <textarea v-model="local.f.action" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            PIC
            <input v-model="local.f.pic" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </label>
          <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Timeline
            <input v-model="local.f.timeline" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="mis. Q2 2026" />
          </label>
        </div>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          KPI terkait
          <textarea v-model="local.f.kpi" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Contoh: complaint rate ≤ 2%, service time ≤ 10 menit" />
        </label>
      </div>
    </section>

    <!-- G -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 class="text-sm font-bold text-slate-900">G. Follow up &amp; verifikasi</h4>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Tanggal follow up
          <input v-model="local.g.follow_up_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Verifikasi oleh
          <input v-model="local.g.verified_by" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Manager / QA / Ops" />
        </label>
      </div>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Hasil
        <select v-model="local.g.result" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
          <option :value="null">—</option>
          <option value="effective">Effective</option>
          <option value="not_effective">Not effective</option>
        </select>
      </label>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Catatan tambahan
        <textarea v-model="local.g.notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
    </section>

    <!-- H -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h4 class="text-sm font-bold text-slate-900">H. Customer recovery</h4>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Tamu dihubungi kembali?
          <select v-model="local.h.contacted" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            <option :value="null">—</option>
            <option value="yes">Ya</option>
            <option value="no">Tidak</option>
          </select>
        </label>
        <div class="sm:col-span-2">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Metode kontak</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <label v-for="opt in contactMethods" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-white">
              <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasContact(opt.v)" @change="toggleContact(opt.v)" />
              {{ opt.label }}
            </label>
          </div>
        </div>
      </div>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Feedback tamu setelah recovery
        <textarea v-model="local.h.recovery_feedback" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Status kepuasan
        <select v-model="local.h.satisfaction" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
          <option :value="null">—</option>
          <option value="satisfied">Satisfied</option>
          <option value="neutral">Neutral</option>
          <option value="unsatisfied">Unsatisfied</option>
        </select>
      </label>

      <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-3">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-600">Dokumentasi severity &amp; dampak (CAPA)</p>
        <p class="mt-1 text-[11px] text-slate-500">Boleh selaras dengan klasifikasi AI di atas; disimpan terpisah untuk cetak / audit.</p>
        <div class="mt-3 flex flex-wrap gap-3">
          <label class="text-xs font-medium text-slate-700">
            Severity
            <select v-model="local.h.documented_severity" class="mt-1 block rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm">
              <option :value="null">—</option>
              <option value="minor">Minor</option>
              <option value="major">Major</option>
              <option value="critical">Critical</option>
            </select>
          </label>
        </div>
        <div class="mt-3">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Impact</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <label v-for="opt in impactOpts" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700">
              <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasImpact(opt.v)" @change="toggleImpact(opt.v)" />
              {{ opt.label }}
            </label>
          </div>
        </div>
      </div>
    </section>

    <div class="sticky bottom-0 -mx-1 flex flex-col gap-2 border-t border-slate-200 bg-white/95 py-3 backdrop-blur supports-[backdrop-filter]:bg-white/80 sm:flex-row sm:justify-end">
      <button
        type="button"
        class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        :disabled="saving"
        @click="$emit('reset')"
      >
        Batalkan perubahan lokal
      </button>
      <button
        type="button"
        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="saving"
        @click="submit"
      >
        {{ saving ? 'Menyimpan…' : 'Simpan form CAPA' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  initialCapa: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'reset'])

const local = ref(ensureShape({}))

watch(
  () => props.initialCapa,
  (c) => {
    local.value = ensureShape(c && typeof c === 'object' ? JSON.parse(JSON.stringify(c)) : {})
  },
  { immediate: true, deep: true },
)

function ensureShape(src) {
  const base = {
    a: {
      complaint_date: null,
      complaint_time: null,
      guest_name: null,
      channel: null,
      channel_other: null,
      pic_receiver_name: null,
    },
    b: { types: [], types_other: null, description: null },
    c: { actions: [], actions_other: null, response_time_note: null, pic: null },
    d: {
      use_fishbone: false,
      man: null,
      method: null,
      machine: null,
      material: null,
      measurement: null,
      environment: null,
      root_cause_summary: null,
    },
    e: { action: null, pic: null, deadline: null, status: 'open' },
    f: { action: null, improvement_areas: [], pic: null, timeline: null, kpi: null },
    g: { follow_up_date: null, verified_by: null, result: null, notes: null },
    h: {
      contacted: null,
      contact_methods: [],
      recovery_feedback: null,
      satisfaction: null,
      documented_severity: null,
      documented_impact: [],
    },
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

const complaintTypes = [
  { v: 'food_quality', label: 'Food quality' },
  { v: 'service', label: 'Service' },
  { v: 'cleanliness', label: 'Cleanliness' },
  { v: 'waiting_time', label: 'Waiting time' },
  { v: 'billing', label: 'Billing' },
  { v: 'other', label: 'Others' },
]

const immediateActions = [
  { v: 'apology', label: 'Apology' },
  { v: 'replace_product', label: 'Replace product' },
  { v: 'refund_discount', label: 'Refund / diskon' },
  { v: 'escalate', label: 'Eskalasi supervisor/manager' },
  { v: 'other', label: 'Lainnya' },
]

const improvementAreas = [
  { v: 'sop', label: 'SOP' },
  { v: 'training', label: 'Training' },
  { v: 'equipment', label: 'Equipment' },
  { v: 'manpower', label: 'Manpower' },
  { v: 'system', label: 'System' },
]

const contactMethods = [
  { v: 'call', label: 'Call' },
  { v: 'whatsapp', label: 'WhatsApp' },
  { v: 'email', label: 'Email' },
]

const impactOpts = [
  { v: 'reputasi', label: 'Reputasi' },
  { v: 'finansial', label: 'Finansial' },
  { v: 'operasional', label: 'Operasional' },
]

function hasType(v) {
  return (local.value.b.types || []).includes(v)
}
function toggleType(v) {
  const arr = [...(local.value.b.types || [])]
  const i = arr.indexOf(v)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(v)
  local.value.b.types = arr
}

function hasAction(v) {
  return (local.value.c.actions || []).includes(v)
}
function toggleAction(v) {
  const arr = [...(local.value.c.actions || [])]
  const i = arr.indexOf(v)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(v)
  local.value.c.actions = arr
}

function hasImprovement(v) {
  return (local.value.f.improvement_areas || []).includes(v)
}
function toggleImprovement(v) {
  const arr = [...(local.value.f.improvement_areas || [])]
  const i = arr.indexOf(v)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(v)
  local.value.f.improvement_areas = arr
}

function hasContact(v) {
  return (local.value.h.contact_methods || []).includes(v)
}
function toggleContact(v) {
  const arr = [...(local.value.h.contact_methods || [])]
  const i = arr.indexOf(v)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(v)
  local.value.h.contact_methods = arr
}

function hasImpact(v) {
  return (local.value.h.documented_impact || []).includes(v)
}
function toggleImpact(v) {
  const arr = [...(local.value.h.documented_impact || [])]
  const i = arr.indexOf(v)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(v)
  local.value.h.documented_impact = arr
}

function submit() {
  emit('save', JSON.parse(JSON.stringify(local.value)))
}
</script>
