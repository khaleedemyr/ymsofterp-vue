<template>
  <div class="capa-form touch-manipulation space-y-4 pb-2 selection:bg-indigo-100">
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

    <div
      v-if="pendingVerifierSelf"
      class="rounded-2xl border border-violet-300 bg-gradient-to-r from-violet-50 to-indigo-50 px-4 py-3 shadow-sm"
    >
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs font-medium leading-relaxed text-violet-950">
          <i class="fa fa-clipboard-check mr-1.5 text-violet-600" aria-hidden="true" />
          Anda ditunjuk sebagai <strong>verifikator</strong>. Lengkapi <strong>bagian G — Hasil</strong>, lalu klik
          <strong>Simpan form CAPA</strong>.
        </p>
        <button
          type="button"
          class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-violet-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-violet-700 active:bg-violet-800"
          @click="handleVerifyClick"
        >
          Verifikasi
        </button>
      </div>
    </div>

    <nav
      class="sticky top-0 z-[6] -mx-0.5 mb-1 flex gap-0.5 overflow-x-auto rounded-xl border border-slate-200 bg-white/95 py-1.5 px-1 text-[11px] font-semibold shadow-sm backdrop-blur sm:hidden"
      aria-label="Loncat ke bagian form"
    >
      <a v-for="l in sectionLinks" :key="l.id" :href="'#' + l.id" class="shrink-0 rounded-lg px-2.5 py-2 text-indigo-700 active:bg-indigo-50">{{ l.short }}</a>
    </nav>

    <section id="capa-evidence" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">Lampiran bukti &amp; dokumen</h3>
      <p class="mt-1 text-[11px] leading-relaxed text-slate-600">
        Foto struk, SS chat, PDF SOP, dll. Maks. 20 file, per file ±15 MB. Tersimpan aman di server; tidak hilang saat menyimpan form CAPA.
      </p>
      <p v-if="evidenceError" class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">{{ evidenceError }}</p>
      <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 active:bg-slate-50 disabled:opacity-50"
          :disabled="uploadingEvidence || evidenceFull"
          @click="triggerCameraInput"
        >
          Ambil foto
        </button>
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-900 active:bg-indigo-100 disabled:opacity-50"
          :disabled="uploadingEvidence || evidenceFull"
          @click="triggerFilePicker"
        >
          Pilih file / galeri
        </button>
      </div>
      <input ref="cameraInputRef" type="file" class="hidden" accept="image/*" capture="environment" @change="onEvidenceFiles" />
      <input
        ref="pickerInputRef"
        type="file"
        class="hidden"
        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,application/pdf"
        @change="onEvidenceFiles"
      />
      <p v-if="uploadingEvidence" class="mt-2 text-xs font-medium text-indigo-600">Mengunggah…</p>
      <ul v-if="(local.evidence || []).length" class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-100">
        <li v-for="ev in local.evidence" :key="ev.id" class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0">
          <div class="min-w-0 flex-1">
            <a
              v-if="ev.url"
              :href="ev.url"
              target="_blank"
              rel="noopener noreferrer"
              class="break-all text-sm font-medium text-indigo-700 underline decoration-indigo-200 underline-offset-2"
            >{{ ev.original_name || 'Lampiran' }}</a>
            <span v-else class="break-all text-sm text-slate-700">{{ ev.original_name || 'Lampiran' }}</span>
            <p class="text-[10px] text-slate-400">{{ formatBytes(ev.size) }} · {{ formatUploaded(ev.uploaded_at) }}</p>
          </div>
          <button
            type="button"
            class="min-h-10 shrink-0 rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 active:bg-rose-50 disabled:opacity-50"
            :disabled="uploadingEvidence"
            @click="removeEvidence(ev.id)"
          >
            Hapus
          </button>
        </li>
      </ul>
      <p v-else class="mt-3 text-xs text-slate-400">Belum ada lampiran.</p>
    </section>

    <!-- A -->
    <section id="capa-a" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
        <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 px-3 py-2.5 sm:col-span-2">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-indigo-900">PIC penerima complaint</div>
          <p class="mt-1 text-[10px] leading-snug text-indigo-900/80">
            Mengikuti PIC pada kolom tabel (baris kasus). Ubah di daftar utama lalu klik <strong>Simpan</strong>.
          </p>
          <template v-if="assignedToId != null">
            <div class="mt-2 text-sm font-semibold text-slate-900">{{ assignedToName || '—' }}</div>
            <div v-if="assignedToJabatan" class="text-xs text-slate-600">{{ assignedToJabatan }}</div>
            <div v-else class="text-xs text-slate-500">Jabatan belum ada di data master.</div>
          </template>
          <div v-else class="mt-2 text-sm font-medium text-amber-900">Belum ada PIC — pilih PIC di kolom tabel lalu Simpan.</div>
        </div>
      </div>
    </section>

    <!-- B -->
    <section id="capa-b" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
    <section id="capa-c" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
        <div class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          PIC
          <CapaUserPicker v-model="local.c.pic_user_id" :assignees="assigneesMerged" placeholder="Cari PIC…" class="mt-1 block" />
        </div>
      </div>
    </section>

    <!-- D -->
    <section id="capa-d" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">D. Root cause analysis <span class="font-normal text-slate-500">(Analisa akar masalah)</span></h3>
      <p class="mt-2 text-[11px] leading-relaxed text-slate-600">
        Kerangka fishbone (6M): Man, Method, Machine, Material, Measurement, Environment — isi kolom di bawah sesuai fakta.
      </p>

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
    <section id="capa-e" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">E. Corrective action <span class="font-normal text-slate-500">(Perbaikan jangka pendek)</span></h3>
      <p class="mt-1 text-[11px] text-slate-600">Tindakan untuk memperbaiki masalah yang sudah terjadi.</p>
      <div class="mt-3 grid gap-3">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Action
          <textarea v-model="local.e.action" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Perbaikan konkret untuk insiden ini" />
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            PIC
            <CapaUserPicker v-model="local.e.pic_user_id" :assignees="assigneesMerged" placeholder="Cari PIC…" class="mt-1 block" />
          </div>
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
    <section id="capa-f" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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
          <div class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            PIC
            <CapaUserPicker v-model="local.f.pic_user_id" :assignees="assigneesMerged" placeholder="Cari PIC…" class="mt-1 block" />
          </div>
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
    <section id="capa-g" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900">G. Follow up &amp; verification</h3>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Tanggal follow up
          <input v-model="local.g.follow_up_date" type="date" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <div class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:col-span-2">
          Verifikasi oleh <span class="font-normal normal-case text-slate-400">(Manager / QA / Ops)</span>
          <CapaUserPicker
            v-model="local.g.verified_by_user_id"
            :assignees="assigneesMerged"
            placeholder="Cari nama verifikator…"
            clearable
            class="mt-1 block"
          />
          <p class="mt-1 text-[10px] font-normal normal-case text-slate-400">Tanpa default — pilih dari daftar karyawan aktif.</p>
        </div>
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
    <section id="capa-h" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
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

    <div
      class="sticky bottom-0 -mx-1 flex flex-col gap-2 border-t border-slate-200 bg-white/95 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur supports-[backdrop-filter]:bg-white/80 sm:flex-row sm:items-center sm:justify-between"
    >
      <button
        type="button"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-800 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="saving || deleting"
        @click="askDeleteStoredCapa"
      >
        {{ deleting ? 'Menghapus…' : 'Hapus data CAPA' }}
      </button>
      <div class="flex flex-col gap-2 sm:flex-row sm:justify-end sm:gap-2">
        <button
          v-if="pendingVerifierSelf"
          type="button"
          class="rounded-xl border border-violet-300 bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="saving || deleting"
          @click="handleVerifyClick"
        >
          Verifikasi
        </button>
        <button
          type="button"
          class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          :disabled="saving || deleting"
          @click="$emit('reset')"
        >
          Batalkan perubahan lokal
        </button>
        <button
          type="button"
          class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="saving || deleting"
          @click="submit"
        >
          {{ saving ? 'Menyimpan…' : 'Simpan form CAPA' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import CapaUserPicker from '@/Pages/CustomerVoiceCommandCenter/CapaUserPicker.vue'
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
  caseId: { type: Number, required: true },
  initialCapa: { type: Object, default: () => ({}) },
  outletName: { type: String, default: '' },
  saving: { type: Boolean, default: false },
  assignees: { type: Array, default: () => [] },
  authUser: { type: Object, default: null },
  assignedToId: { type: Number, default: null },
  assignedToName: { type: String, default: '' },
  assignedToJabatan: { type: String, default: '' },
  deleting: { type: Boolean, default: false },
  /** Deep link dari Home / kartu verifikasi — scroll ke section CAPA (mis. capa-g). */
  focusSectionId: { type: String, default: null },
})

const emit = defineEmits(['save', 'reset', 'delete-capa', 'focused-section', 'verify-clicked'])

function askDeleteStoredCapa() {
  if (
    !confirm(
      'Hapus seluruh data CAPA yang sudah tersimpan untuk kasus ini?\n\n' +
        'Lampiran file akan dihapus dari server. Status kasus dan komentar pelanggan tidak diubah.',
    )
  ) {
    return
  }
  emit('delete-capa')
}

const sectionLinks = [
  { id: 'capa-evidence', short: 'File' },
  { id: 'capa-a', short: 'A' },
  { id: 'capa-b', short: 'B' },
  { id: 'capa-c', short: 'C' },
  { id: 'capa-d', short: 'D' },
  { id: 'capa-e', short: 'E' },
  { id: 'capa-f', short: 'F' },
  { id: 'capa-g', short: 'G' },
  { id: 'capa-h', short: 'H' },
]

const cameraInputRef = ref(null)
const pickerInputRef = ref(null)
const uploadingEvidence = ref(false)
const evidenceError = ref('')

const outletDisplay = computed(() => {
  const s = (props.outletName || '').trim()
  return s !== '' ? s : '—'
})

const assigneesMerged = computed(() => {
  const base = [...(props.assignees || [])]
  const au = props.authUser
  if (au?.id != null && !base.some((x) => x.id === au.id)) {
    base.unshift({
      id: au.id,
      nama_lengkap: au.nama_lengkap || '',
      nama_jabatan: au.nama_jabatan ?? null,
    })
  }

  return base
})

const local = ref(ensureShape({}))

const evidenceFull = computed(() => (local.value.evidence || []).length >= 20)

/** User login = verifikator di G dan hasil belum dipilih (efektif / tidak efektif). */
const pendingVerifierSelf = computed(() => {
  const uid = props.authUser?.id != null ? Number(props.authUser.id) : null
  if (!uid || uid <= 0) return false
  const vidRaw = local.value?.g?.verified_by_user_id
  const vid = vidRaw != null && vidRaw !== '' ? Number(vidRaw) : null
  if (!vid || vid !== uid) return false
  const r = local.value?.g?.result
  return r !== 'effective' && r !== 'not_effective'
})

function scrollToSectionById(sectionId, emitAfter) {
  if (!sectionId) return
  nextTick(() => {
    requestAnimationFrame(() => {
      setTimeout(() => {
        document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
        if (emitAfter) {
          emit('focused-section')
        }
      }, 120)
    })
  })
}

function scrollToCapaG() {
  scrollToSectionById('capa-g', false)
}

function handleVerifyClick() {
  scrollToCapaG()
  emit('verify-clicked')
}

watch(
  () => [props.caseId, props.focusSectionId],
  ([, sectionId]) => {
    if (sectionId) {
      scrollToSectionById(sectionId, true)
    }
  },
  { flush: 'post' },
)

watch(
  () => props.initialCapa,
  (c) => {
    const merged = ensureShape(c && typeof c === 'object' ? JSON.parse(JSON.stringify(c)) : {})
    const uid = props.authUser?.id
    if (uid != null) {
      if (merged.c.pic_user_id == null) merged.c.pic_user_id = uid
      if (merged.e.pic_user_id == null) merged.e.pic_user_id = uid
      if (merged.f.pic_user_id == null) merged.f.pic_user_id = uid
    }
    local.value = merged
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
    c: { actions: [], actions_other: null, response_time_note: null, pic_user_id: null },
    d: {
      problem_statement: null,
      man: null,
      method: null,
      machine: null,
      material: null,
      measurement: null,
      environment: null,
      root_cause_summary: null,
    },
    e: { action: null, pic_user_id: null, deadline: null, status: 'open' },
    f: { action: null, improvement_areas: [], pic_user_id: null, timeline: null, kpi: null },
    g: { follow_up_date: null, verified_by_user_id: null, result: null, notes: null },
    h: {
      contacted: null,
      contact_methods: [],
      recovery_feedback: null,
      satisfaction: null,
      documented_severity: null,
      documented_impact: [],
    },
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

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function triggerCameraInput() {
  cameraInputRef.value?.click()
}

function triggerFilePicker() {
  pickerInputRef.value?.click()
}

async function onEvidenceFiles(ev) {
  const input = ev.target
  const file = input.files?.[0]
  input.value = ''
  if (!file || !props.caseId) return
  evidenceError.value = ''
  uploadingEvidence.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    const res = await fetch(`/customer-voice-command-center/cases/${props.caseId}/capa/evidence`, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) {
      evidenceError.value = data.message || `Upload gagal (${res.status}).`

      return
    }
    if (!Array.isArray(local.value.evidence)) {
      local.value.evidence = []
    }
    local.value.evidence.push(data.item)
  } catch {
    evidenceError.value = 'Upload gagal (periksa jaringan).'
  } finally {
    uploadingEvidence.value = false
  }
}

async function removeEvidence(evidenceId) {
  if (!props.caseId || !evidenceId) return
  evidenceError.value = ''
  uploadingEvidence.value = true
  try {
    const res = await fetch(`/customer-voice-command-center/cases/${props.caseId}/capa/evidence/${encodeURIComponent(evidenceId)}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) {
      evidenceError.value = data.message || `Hapus gagal (${res.status}).`

      return
    }
    local.value.evidence = (local.value.evidence || []).filter((x) => x.id !== evidenceId)
  } catch {
    evidenceError.value = 'Hapus gagal (periksa jaringan).'
  } finally {
    uploadingEvidence.value = false
  }
}

function formatBytes(n) {
  if (n == null || Number.isNaN(Number(n))) return '—'
  const v = Number(n)
  if (v < 1024) return `${v} B`
  if (v < 1024 * 1024) return `${(v / 1024).toFixed(1)} KB`

  return `${(v / (1024 * 1024)).toFixed(1)} MB`
}

function formatUploaded(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString('id-ID')
  } catch {
    return String(iso)
  }
}
</script>

<style scoped>
/* HP: target sentuh ~44px + font 16px agar tidak zoom otomatis iOS */
@media (max-width: 639px) {
  .capa-form :deep(input[type='text']),
  .capa-form :deep(input[type='date']),
  .capa-form :deep(input[type='time']),
  .capa-form :deep(select),
  .capa-form :deep(textarea) {
    min-height: 2.75rem;
    font-size: 16px;
  }
  .capa-form :deep(textarea) {
    min-height: 6rem;
  }
  .capa-form :deep(input[type='checkbox']) {
    width: 1.25rem;
    height: 1.25rem;
  }
}
</style>
