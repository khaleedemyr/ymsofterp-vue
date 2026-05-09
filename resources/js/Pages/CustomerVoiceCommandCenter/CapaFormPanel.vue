<template>
  <div class="capa-form space-y-4">
    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white px-4 py-4 shadow-sm">
      <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Form standar</p>
      <h2 class="mt-1 text-base font-bold leading-snug text-slate-900">
        Customer Complaint Handling — Corrective &amp; Preventive Action Plan
      </h2>
      <p class="mt-2 text-xs leading-relaxed text-slate-600">
        Isi sesuai penanganan komplain di lapangan. Data tersimpan pada kasus (meta). <strong>Corrective</strong> = perbaiki kejadian saat ini;
        <strong>Preventive</strong> = cegah kejadian berulang.
      </p>
    </div>

    <!-- A -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">A. Informasi umum</h3>
      <p class="mt-1 text-[11px] text-slate-500">General information — tanggal, waktu, lokasi, tamu, channel, PIC penerima.</p>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Tanggal complaint
          <input v-model="local.a.complaint_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Waktu
          <input v-model="local.a.complaint_time" type="time" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" />
        </label>
        <div class="sm:col-span-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/90 px-3 py-2.5">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Outlet / lokasi</div>
          <div class="mt-1 text-sm font-semibold text-slate-900">{{ outletDisplay }}</div>
          <p class="mt-1 text-[10px] leading-snug text-slate-400">Nilai dari data outlet kasus (sama seperti kartu ringkas di atas). Tidak perlu diketik ulang.</p>
        </div>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          Nama tamu <span class="font-normal text-slate-400">(optional)</span>
          <input v-model="local.a.guest_name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" placeholder="Nama tamu / penulis" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          Channel complaint
          <span class="mt-0.5 block text-[10px] font-normal normal-case text-slate-400">Dine-in / Online Review / Delivery / Walk-in / dll</span>
          <select v-model="local.a.channel" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
            <option :value="null">— pilih channel —</option>
            <option value="dine_in">Dine-in</option>
            <option value="online_review">Online Review</option>
            <option value="delivery">Delivery</option>
            <option value="walk_in">Walk-in</option>
            <option value="other">Lainnya (dll)</option>
          </select>
        </label>
        <label v-if="local.a.channel === 'other'" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          Jelaskan channel lainnya
          <input v-model="local.a.channel_other" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          PIC penerima complaint
          <input v-model="local.a.pic_receiver_name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" placeholder="Nama PIC yang menerima keluhan" />
        </label>
      </div>
    </section>

    <!-- B -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">B. Detail complaint</h3>
      <p class="mt-1 text-[11px] font-semibold text-slate-600">Jenis complaint</p>
      <p class="text-[10px] text-slate-500">Centang semua yang sesuai.</p>
      <div class="mt-3 flex flex-wrap gap-2">
        <label v-for="opt in complaintTypes" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 hover:bg-white">
          <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasType(opt.v)" @change="toggleType(opt.v)" />
          {{ opt.label }}
        </label>
      </div>
      <label v-if="local.b.types?.includes('other')" class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Others — jelaskan
        <input v-model="local.b.types_other" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Isi jika memilih Lainnya" />
      </label>
      <label class="mt-4 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Deskripsi complaint
        <span class="mt-0.5 block text-[10px] font-normal normal-case text-slate-400">Tuliskan kronologi lengkap secara objektif.</span>
        <textarea v-model="local.b.description" rows="6" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm leading-relaxed outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100" placeholder="Ringkas fakta apa yang terjadi, urutan waktu, tanpa menyalahkan pihak tertentu…" />
      </label>
    </section>

    <!-- C -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">C. Immediate action <span class="font-normal text-slate-500">(Tindakan langsung)</span></h3>
      <p class="mt-2 text-[11px] font-semibold text-slate-700">Tindakan yang dilakukan saat itu</p>
      <div class="mt-2 flex flex-wrap gap-2">
        <label v-for="opt in immediateActions" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 hover:bg-white">
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
          Waktu respon
          <input v-model="local.c.response_time_note" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Contoh: kurang dari 5 menit setelah keluhan" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          PIC
          <input v-model="local.c.pic" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="PIC tindakan langsung" />
        </label>
      </div>
    </section>

    <!-- D -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">D. Root cause analysis <span class="font-normal text-slate-500">(Analisa akar masalah)</span></h3>
      <p class="mt-2 text-[11px] text-slate-600">Gunakan metode:</p>
      <label class="mt-2 inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-indigo-50/40 px-3 py-2 text-sm font-medium text-slate-800">
        <input v-model="local.d.use_fishbone" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
        Fishbone diagram
      </label>

      <label class="mt-4 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Masalah
        <span class="mt-0.5 block text-[10px] font-normal normal-case text-slate-400">Ringkas inti keluhan (satu kalimat atau ringkasan singkat).</span>
        <textarea v-model="local.d.problem_statement" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Contoh: Tamu mengeluh waiting time lama" />
      </label>

      <p class="mt-5 text-[11px] font-semibold text-slate-700">Breakdown fishbone</p>
      <div class="mt-3 grid gap-4">
        <div v-for="row in fishboneRows" :key="row.key" class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
          <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-700">{{ row.title }}</label>
          <p class="mt-0.5 text-[10px] leading-snug text-slate-500">{{ row.hint }}</p>
          <textarea v-model="local.d[row.key]" rows="3" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" :placeholder="row.placeholder" />
        </div>
      </div>
      <label class="mt-4 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Akar masalah utama
        <span class="mt-0.5 block text-[10px] font-normal normal-case text-slate-400">Contoh: SOP tidak dijalankan, human error, equipment issue, dll.</span>
        <textarea v-model="local.d.root_cause_summary" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
    </section>

    <!-- E -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">E. Corrective action <span class="font-normal text-slate-500">(Perbaikan jangka pendek)</span></h3>
      <p class="mt-1 text-[11px] text-slate-600">Tindakan untuk memperbaiki masalah yang sudah terjadi.</p>
      <div class="mt-3 grid gap-3">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Action
          <textarea v-model="local.e.action" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Perbaikan konkret untuk insiden ini" />
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
            <option value="open">Open — terbuka</option>
            <option value="on_progress">On progress — berjalan</option>
            <option value="closed">Closed — selesai</option>
          </select>
        </label>
      </div>
    </section>

    <!-- F -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">F. Preventive action <span class="font-normal text-slate-500">(Pencegahan jangka panjang)</span></h3>
      <p class="mt-1 text-[11px] text-slate-600">Tindakan agar masalah tidak berulang.</p>
      <p class="mt-3 text-[11px] font-semibold text-slate-700">Improvement area</p>
      <div class="mt-2 flex flex-wrap gap-2">
        <label v-for="opt in improvementAreas" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 hover:bg-white">
          <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasImprovement(opt.v)" @change="toggleImprovement(opt.v)" />
          {{ opt.label }}
        </label>
      </div>
      <div class="mt-4 grid gap-3">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Action
          <textarea v-model="local.f.action" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            PIC
            <input v-model="local.f.pic" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </label>
          <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            Timeline
            <input v-model="local.f.timeline" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Periode atau milestone" />
          </label>
        </div>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          KPI terkait
          <span class="mt-0.5 block text-[10px] font-normal normal-case text-slate-400">Contoh: complaint rate ≤ 2%, service time ≤ 10 menit.</span>
          <textarea v-model="local.f.kpi" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
      </div>
    </section>

    <!-- G -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">G. Follow up &amp; verification</h3>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Tanggal follow up
          <input v-model="local.g.follow_up_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Verifikasi oleh <span class="font-normal normal-case text-slate-400">(Manager / QA / Ops)</span>
          <input v-model="local.g.verified_by" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
      </div>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Hasil
        <select v-model="local.g.result" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
          <option :value="null">— pilih —</option>
          <option value="effective">Effective — efektif</option>
          <option value="not_effective">Not effective — tidak efektif</option>
        </select>
      </label>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Catatan tambahan
        <textarea v-model="local.g.notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
    </section>

    <!-- H -->
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">H. Customer recovery <span class="font-normal text-slate-500">(Service recovery)</span></h3>

      <div class="mt-3">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Apakah tamu sudah dihubungi kembali?</p>
        <div class="mt-2 inline-flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
            :class="local.h.contacted === null || local.h.contacted === undefined ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:bg-white/80'"
            @click="local.h.contacted = null"
          >
            —
          </button>
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
            :class="local.h.contacted === 'yes' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white/80'"
            @click="local.h.contacted = 'yes'"
          >
            Ya
          </button>
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
            :class="local.h.contacted === 'no' ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-600 hover:bg-white/80'"
            @click="local.h.contacted = 'no'"
          >
            Tidak
          </button>
        </div>
      </div>

      <div class="mt-5">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Metode</p>
        <div class="mt-2 flex flex-wrap gap-2">
          <label v-for="opt in contactMethods" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-800 hover:bg-white">
            <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasContact(opt.v)" @change="toggleContact(opt.v)" />
            {{ opt.label }}
          </label>
        </div>
      </div>

      <label class="mt-4 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Feedback tamu setelah recovery
        <textarea v-model="local.h.recovery_feedback" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
      </label>
      <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        Status kepuasan
        <select v-model="local.h.satisfaction" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
          <option :value="null">—</option>
          <option value="satisfied">Satisfied — puas</option>
          <option value="neutral">Neutral — netral</option>
          <option value="unsatisfied">Unsatisfied — tidak puas</option>
        </select>
      </label>

      <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 p-4">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-600">Kategori severity</p>
        <div class="mt-2 inline-flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1">
          <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold" :class="local.h.documented_severity === null ? 'bg-slate-100 text-slate-900' : 'text-slate-500'" @click="local.h.documented_severity = null">—</button>
          <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold" :class="local.h.documented_severity === 'minor' ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-300' : 'text-slate-600'" @click="local.h.documented_severity = 'minor'">Minor</button>
          <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold" :class="local.h.documented_severity === 'major' ? 'bg-orange-100 text-orange-900 ring-1 ring-orange-300' : 'text-slate-600'" @click="local.h.documented_severity = 'major'">Major</button>
          <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold" :class="local.h.documented_severity === 'critical' ? 'bg-rose-100 text-rose-900 ring-1 ring-rose-300' : 'text-slate-600'" @click="local.h.documented_severity = 'critical'">Critical</button>
        </div>
        <p class="mt-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Impact</p>
        <div class="mt-2 flex flex-wrap gap-2">
          <label v-for="opt in impactOpts" :key="opt.v" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-white bg-white px-3 py-2 text-xs font-medium text-slate-800 shadow-sm">
            <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="hasImpact(opt.v)" @change="toggleImpact(opt.v)" />
            {{ opt.label }}
          </label>
        </div>
        <p class="mt-3 text-[10px] leading-snug text-slate-500">Angka severity/dampak di atas untuk dokumentasi CAPA; klasifikasi AI pada kartu ringkas tetap ditampilkan terpisah.</p>
      </div>
    </section>

    <div class="rounded-xl border border-amber-100 bg-amber-50/80 px-3 py-2 text-[11px] leading-relaxed text-amber-950">
      <strong>Catatan:</strong> Corrective = perbaiki kejadian saat ini. Preventive = mencegah kejadian berulang.
    </div>

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
import { computed, ref, watch } from 'vue'

const props = defineProps({
  initialCapa: { type: Object, default: () => ({}) },
  outletName: { type: String, default: '' },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'reset'])

const outletDisplay = computed(() => {
  const s = (props.outletName || '').trim()
  return s !== '' ? s : '—'
})

const local = ref(ensureShape({}))

watch(
  () => props.initialCapa,
  (c) => {
    local.value = ensureShape(c && typeof c === 'object' ? JSON.parse(JSON.stringify(c)) : {})
  },
  { immediate: true, deep: true },
)

const fishboneRows = [
  {
    key: 'man',
    title: 'Man (SDM)',
    hint: 'skill, attitude, staffing',
    placeholder: 'Contoh: understaff saat peak hour…',
  },
  {
    key: 'method',
    title: 'Method (SOP)',
    hint: 'prosedur, workflow',
    placeholder: 'Contoh: SOP service flow tidak konsisten…',
  },
  {
    key: 'machine',
    title: 'Machine (equipment)',
    hint: 'alat rusak / tidak ada',
    placeholder: 'Contoh: POS lambat, equipment bottleneck…',
  },
  {
    key: 'material',
    title: 'Material (bahan)',
    hint: 'kualitas bahan',
    placeholder: 'Contoh: bahan belum siap (prep kurang)…',
  },
  {
    key: 'measurement',
    title: 'Measurement',
    hint: 'KPI, kontrol, monitoring',
    placeholder: 'Contoh: tidak ada target service time…',
  },
  {
    key: 'environment',
    title: 'Environment',
    hint: 'kondisi outlet, rush hour',
    placeholder: 'Contoh: over capacity, layout kurang efisien…',
  },
]

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
      problem_statement: null,
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
  { v: 'food_quality', label: 'Food Quality' },
  { v: 'service', label: 'Service' },
  { v: 'cleanliness', label: 'Cleanliness' },
  { v: 'waiting_time', label: 'Waiting Time' },
  { v: 'billing', label: 'Billing' },
  { v: 'other', label: 'Others' },
]

const immediateActions = [
  { v: 'apology', label: 'Apology diberikan' },
  { v: 'replace_product', label: 'Replace product' },
  { v: 'refund_discount', label: 'Refund / Discount' },
  { v: 'escalate', label: 'Escalate ke Supervisor / Manager' },
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
