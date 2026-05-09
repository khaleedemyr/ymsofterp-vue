<template>
  <div>
    <div v-if="visible" class="mb-4 flex-shrink-0">
      <div
        class="animate-fade-in rounded-2xl border p-4 shadow-2xl backdrop-blur-md transition-all duration-500 hover:shadow-3xl"
        :class="isNight ? 'border-violet-600/40 bg-slate-800/90' : 'border-violet-200/80 bg-white/90'"
      >
        <div class="mb-3 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="h-3 w-3 animate-pulse rounded-full bg-violet-500" />
            <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
              <i class="fa fa-clipboard-check mr-2 text-violet-500" />
              Verifikasi CAPA
            </h3>
          </div>
          <div class="rounded-full bg-violet-600 px-2 py-1 text-xs font-bold text-white">{{ count }}</div>
        </div>
        <p class="mb-3 text-xs leading-relaxed" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
          Verifikasi dikerjakan langsung dari modal Home, tanpa masuk ke form CAPA.
        </p>

        <div v-if="loading" class="py-4 text-center">
          <div class="inline-block h-6 w-6 animate-spin rounded-full border-b-2 border-violet-500" />
          <p class="mt-2 text-sm" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat…</p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="item in items.slice(0, 3)"
            :key="'capa-ver-' + item.id"
            class="flex w-full items-start gap-2 rounded-lg p-3 text-left transition-all duration-200 hover:scale-[1.01]"
            :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-violet-50 hover:bg-violet-100'"
          >
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">
                Case #{{ item.id }}
                <span v-if="item.nama_outlet" class="font-normal text-slate-500">— {{ item.nama_outlet }}</span>
              </div>
              <div class="mt-1 line-clamp-2 text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">{{ item.summary_id || '—' }}</div>
              <div class="mt-1 flex flex-wrap gap-2 text-[11px]" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                <span class="rounded bg-white/10 px-1.5 py-0.5">{{ item.status || '—' }}</span>
                <span class="rounded bg-white/10 px-1.5 py-0.5">{{ item.severity || '—' }}</span>
                <span>{{ formatDt(item.event_at) }}</span>
              </div>
              <div class="mt-1 flex flex-wrap gap-1">
                <span
                  v-for="div in (item.pending_divisions || [])"
                  :key="`div-${item.id}-${div}`"
                  class="inline-flex rounded-full border border-violet-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-violet-700"
                >
                  {{ divisionLabel(div) }}
                </span>
              </div>
            </div>
            <button
              type="button"
              class="shrink-0 rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-violet-700"
              @click="openVerifyModal(item)"
            >
              <i class="fa fa-clipboard-check mr-1" aria-hidden="true" />
              Verifikasi
            </button>
          </div>

          <div v-if="items.length > 3" class="pt-2 text-center">
            <button
              type="button"
              class="text-sm font-medium text-violet-600 hover:text-violet-800 dark:text-violet-300 dark:hover:text-violet-100"
              @click="openCommandCenter"
            >
              Lihat {{ items.length - 3 }} lainnya di Customer Voice…
            </button>
          </div>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-[10050] flex items-center justify-center overflow-y-auto bg-black/60 p-3 py-8"
        @click.self="closeVerifyModal"
      >
        <div
          class="my-auto max-h-[min(92vh,900px)] w-full max-w-5xl overflow-y-auto rounded-2xl border bg-white p-4 shadow-2xl"
          :class="isNight ? 'border-slate-600 bg-slate-900' : 'border-slate-200 bg-white'"
          @click.stop
        >
          <div class="mb-3 flex items-start justify-between gap-3 border-b pb-3" :class="isNight ? 'border-slate-700' : 'border-slate-200'">
            <div class="min-w-0">
              <h4 class="truncate text-base font-bold" :class="isNight ? 'text-white' : 'text-slate-900'">
                Verifikasi CAPA — Case #{{ modalItem?.id }}
              </h4>
              <p class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-500'">
                Divisi: <span class="font-semibold">{{ divisionLabel(activeDivision) }}</span>
                · {{ modalItem?.nama_outlet || '—' }} · {{ formatDt(modalCase?.event_at || modalItem?.event_at) }}
              </p>
            </div>
            <button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="isNight ? 'border-slate-600 text-slate-200' : 'border-slate-300 text-slate-700'" @click="closeVerifyModal">
              Tutup
            </button>
          </div>

          <div v-if="modalLoading" class="py-10 text-center">
            <div class="inline-block h-7 w-7 animate-spin rounded-full border-b-2 border-violet-500" />
          </div>
          <div v-else-if="modalError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ modalError }}
          </div>
          <div v-else-if="modalCase" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <div class="rounded-xl border p-3 sm:col-span-2" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">Ringkasan kasus</div>
                <div class="mt-1 text-sm font-semibold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">{{ disp(modalCase.summary_id) }}</div>
              </div>
              <div class="rounded-xl border p-3" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">Source</div>
                <div class="mt-1 text-sm font-semibold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">{{ sourceTypeLabel(modalCase.source_type) }}</div>
              </div>
              <div class="rounded-xl border p-3" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">Severity</div>
                <div class="mt-1 text-sm font-semibold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">{{ disp(modalCase.severity) }}</div>
              </div>
              <div class="rounded-xl border p-3" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">Status</div>
                <div class="mt-1 text-sm font-semibold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">{{ disp(modalCase.status) }}</div>
              </div>
              <div class="rounded-xl border p-3" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">FU target</div>
                <div class="mt-1 text-sm font-semibold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">{{ followUpLabel(modalCase.follow_up_target) }}</div>
              </div>
              <div class="rounded-xl border p-3 sm:col-span-2" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">Jenis komplain</div>
                <div v-if="(modalCase.complaint_type_labels || []).length" class="mt-2 flex flex-wrap gap-1">
                  <span
                    v-for="(lbl, i) in modalCase.complaint_type_labels"
                    :key="`ct-${i}`"
                    class="inline-flex rounded-full border border-violet-300 bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-900"
                  >
                    {{ lbl }}
                  </span>
                </div>
                <div v-else class="mt-1 text-sm" :class="isNight ? 'text-slate-400' : 'text-slate-500'">—</div>
              </div>
              <div class="rounded-xl border p-3 sm:col-span-2" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">Dampak (source)</div>
                <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-700'">{{ impactLine(modalCase.impact) }}</div>
              </div>
              <div class="rounded-xl border p-3 sm:col-span-2" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">PIC penerima complaint (kasus)</div>
                <div class="mt-1 text-sm font-semibold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">
                  {{ modalCase.assigned_to_name || '—' }}
                  <span v-if="modalCase.assigned_to_jabatan" class="font-normal text-slate-500"> · {{ modalCase.assigned_to_jabatan }}</span>
                </div>
              </div>
              <div class="rounded-xl border p-3 sm:col-span-2" :class="isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-slate-50'">
                <div class="text-[11px] uppercase tracking-wide" :class="isNight ? 'text-slate-400' : 'text-slate-500'">Deskripsi source (komentar)</div>
                <div class="mt-1 text-sm whitespace-pre-wrap" :class="isNight ? 'text-slate-200' : 'text-slate-700'">{{ disp(modalCase.raw_text) }}</div>
              </div>
            </div>

            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="div in pendingDivisions"
                :key="`tab-${div}`"
                type="button"
                class="rounded-lg border px-2.5 py-1 text-xs font-semibold"
                :class="activeDivision === div
                  ? 'border-violet-500 bg-violet-600 text-white'
                  : (isNight ? 'border-slate-600 text-slate-200' : 'border-slate-300 text-slate-700')"
                @click="activeDivision = div"
              >
                {{ divisionLabel(div) }}
              </button>
            </div>
            <div v-if="!pendingDivisions.length" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
              Tidak ada divisi pending verifikasi untuk Anda pada case ini.
            </div>

            <template v-if="pendingDivisions.length && capa">
              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Informasi Komplain</h5>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Tanggal complaint</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ disp(capa.a?.complaint_date) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Waktu complaint</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ disp(capa.a?.complaint_time) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Outlet / lokasi</div>
                    <div class="mt-1 text-sm font-semibold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">{{ disp(modalCase.nama_outlet) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Channel complaint</div>
                    <div class="mt-1 text-sm font-semibold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">{{ channelLabel(capa.a?.channel, capa.a?.channel_other) }}</div>
                  </div>
                  <div class="sm:col-span-2">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Nama tamu (optional)</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ disp(capa.a?.guest_name) }}</div>
                  </div>
                </div>
              </div>

              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Detail Complaint</h5>
                <div class="mt-2 flex flex-wrap gap-1">
                  <span
                    v-for="t in (capa.b?.types || [])"
                    :key="t"
                    class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-900"
                  >
                    {{ complaintTypeLabel(t) }}
                  </span>
                  <span v-if="capa.b?.types_other" class="text-xs text-slate-600">Lainnya: {{ capa.b.types_other }}</span>
                </div>
                <div class="mt-2">
                  <div class="text-[11px] uppercase tracking-wide text-slate-500">Deskripsi complaint</div>
                  <div class="mt-1 rounded-lg border px-3 py-2 text-sm whitespace-pre-wrap" :class="boxField">{{ disp(capa.b?.description) }}</div>
                </div>
              </div>

              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Lampiran bukti &amp; dokumen</h5>
                <div v-if="activeEvidence.length" class="mt-2 space-y-2">
                  <div v-if="activeImageEvidence.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                    <button
                      v-for="ev in activeImageEvidence"
                      :key="`img-${ev.id || ev.path || ev.url}`"
                      type="button"
                      class="group relative overflow-hidden rounded-lg border"
                      :class="isNight ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-slate-50'"
                      @click="openImageLightbox(ev)"
                    >
                      <img :src="ev.url" :alt="ev.original_name || 'Lampiran'" class="h-24 w-full object-cover transition group-hover:scale-105" />
                      <div class="absolute inset-x-0 bottom-0 truncate bg-black/55 px-2 py-1 text-left text-[10px] text-white">
                        {{ ev.original_name || 'Gambar' }}
                      </div>
                    </button>
                  </div>
                  <ul class="space-y-1">
                    <li v-for="ev in activeNonImageEvidence" :key="`file-${ev.id || ev.path || ev.url}`" class="text-xs">
                      <a :href="ev.url" target="_blank" rel="noopener noreferrer" class="text-violet-600 underline">
                        {{ ev.original_name || ev.path || 'Lampiran' }}
                      </a>
                    </li>
                  </ul>
                </div>
                <div v-else class="mt-1 text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">Belum ada lampiran.</div>
              </div>

              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Immediate Action</h5>
                <div class="mt-2 flex flex-wrap gap-1">
                  <span v-for="act in (capa.c?.actions || [])" :key="act" class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-800">
                    {{ immediateActionLabel(act) }}
                  </span>
                </div>
                <div v-if="capa.c?.actions_other" class="mt-2 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-700'">
                  <span class="font-semibold">Lainnya:</span> {{ capa.c.actions_other }}
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Waktu respon</div>
                    <div class="mt-1 text-sm whitespace-pre-wrap" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ disp(capa.c?.response_time_note) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">PIC</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ userIdLabel(capa.c?.pic_user_id) }}</div>
                  </div>
                </div>
              </div>

              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Root Cause Analysis</h5>
                <div class="mt-2">
                  <div class="text-[11px] uppercase tracking-wide text-slate-500">Masalah</div>
                  <div class="mt-1 rounded-lg border px-3 py-2 text-sm whitespace-pre-wrap" :class="boxField">{{ disp(capa.d?.problem_statement) }}</div>
                </div>
                <div class="mt-4 space-y-3">
                  <div v-for="row in fishboneRows" :key="row.key" class="rounded-lg border border-slate-100 p-2" :class="isNight ? 'border-slate-600 bg-slate-900/40' : 'bg-slate-50/80'">
                    <div class="text-[11px] font-bold text-slate-600">{{ row.title }}</div>
                    <div class="mt-1 text-sm whitespace-pre-wrap" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ disp(capa.d?.[row.key]) }}</div>
                  </div>
                </div>
                <div class="mt-3">
                  <div class="text-[11px] uppercase tracking-wide text-slate-500">Akar masalah utama</div>
                  <div class="mt-1 rounded-lg border px-3 py-2 text-sm whitespace-pre-wrap" :class="boxField">{{ disp(capa.d?.root_cause_summary) }}</div>
                </div>
              </div>

              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Corrective Action</h5>
                <div class="mt-2">
                  <div class="text-[11px] uppercase tracking-wide text-slate-500">Action</div>
                  <div class="mt-1 rounded-lg border px-3 py-2 text-sm whitespace-pre-wrap" :class="boxField">{{ disp(capa.e?.action) }}</div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">PIC</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ userIdLabel(capa.e?.pic_user_id) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Deadline</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ disp(capa.e?.deadline) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Status</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ disp(capa.e?.status) }}</div>
                  </div>
                </div>
              </div>

              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Preventive Action</h5>
                <div class="mt-2 flex flex-wrap gap-1">
                  <span v-for="ar in (capa.f?.improvement_areas || [])" :key="ar" class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-800">
                    {{ improvementAreaLabel(ar) }}
                  </span>
                </div>
                <div class="mt-2">
                  <div class="text-[11px] uppercase tracking-wide text-slate-500">Action</div>
                  <div class="mt-1 rounded-lg border px-3 py-2 text-sm whitespace-pre-wrap" :class="boxField">{{ disp(capa.f?.action) }}</div>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">PIC</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ userIdLabel(capa.f?.pic_user_id) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Timeline</div>
                    <div class="mt-1 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ disp(capa.f?.timeline) }}</div>
                  </div>
                  <div class="sm:col-span-2">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">KPI terkait</div>
                    <div class="mt-1 rounded-lg border px-3 py-2 text-sm whitespace-pre-wrap" :class="boxField">{{ disp(capa.f?.kpi) }}</div>
                  </div>
                </div>
              </div>

              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Follow Up &amp; Verification (data tersimpan)</h5>
                <div class="mt-2 grid gap-2 sm:grid-cols-2 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-700'">
                  <div><span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Verifikator (ditunjuk)</span><br>{{ userIdLabel(capa.g?.verified_by_user_id) }}</div>
                  <div><span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Hasil saat ini</span><br>{{ verificationResultLabel(capa.g?.result) }}</div>
                </div>
              </div>

              <div class="rounded-xl border p-3" :class="boxMuted">
                <h5 class="text-sm font-bold" :class="isNight ? 'text-slate-100' : 'text-slate-900'">Customer Recovery</h5>
                <div class="mt-2 grid gap-3 sm:grid-cols-2 text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-700'">
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Tamu dihubungi kembali</div>
                    <div class="mt-1 font-medium">{{ contactedLabel(capa.h?.contacted) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Metode</div>
                    <div class="mt-1">{{ contactMethodsLine(capa.h?.contact_methods) }}</div>
                  </div>
                  <div class="sm:col-span-2">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Feedback tamu setelah recovery</div>
                    <div class="mt-1 rounded-lg border px-3 py-2 whitespace-pre-wrap" :class="boxField">{{ disp(capa.h?.recovery_feedback) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Status kepuasan</div>
                    <div class="mt-1">{{ satisfactionLabel(capa.h?.satisfaction) }}</div>
                  </div>
                  <div>
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Severity (dokumentasi)</div>
                    <div class="mt-1">{{ disp(capa.h?.documented_severity) }}</div>
                  </div>
                  <div class="sm:col-span-2">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Impact (dokumentasi)</div>
                    <div class="mt-1">{{ impactLine(capa.h?.documented_impact) }}</div>
                  </div>
                </div>
              </div>
            </template>

            <div v-if="pendingDivisions.length" class="rounded-xl border p-3" :class="isNight ? 'border-violet-800 bg-violet-900/20' : 'border-violet-200 bg-violet-50/50'">
              <div class="text-sm font-semibold" :class="isNight ? 'text-violet-200' : 'text-violet-900'">Verifikasi {{ divisionLabel(activeDivision) }}</div>
              <p class="mt-1 text-[11px] text-slate-600">Isi hasil verifikasi di bawah, lalu simpan.</p>
              <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <label class="text-[11px] font-semibold uppercase tracking-wide" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                  Hasil
                  <select v-model="verifyForm.result" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" :class="isNight ? 'border-slate-600 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-800'">
                    <option value="">— pilih —</option>
                    <option value="effective">Effective — efektif</option>
                    <option value="not_effective">Not effective — tidak efektif</option>
                  </select>
                </label>
                <label class="text-[11px] font-semibold uppercase tracking-wide" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                  Tanggal follow up
                  <input v-model="verifyForm.follow_up_date" type="date" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" :class="isNight ? 'border-slate-600 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-800'" />
                </label>
                <label class="sm:col-span-2 text-[11px] font-semibold uppercase tracking-wide" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                  Catatan tambahan
                  <textarea v-model="verifyForm.notes" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" :class="isNight ? 'border-slate-600 bg-slate-900 text-slate-100' : 'border-slate-300 bg-white text-slate-800'" />
                </label>
              </div>
              <div class="mt-3 flex justify-end">
                <button
                  type="button"
                  class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-60"
                  :disabled="savingVerify"
                  @click="submitVerify"
                >
                  {{ savingVerify ? 'Menyimpan…' : 'Simpan Verifikasi' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="lightboxImage"
        class="fixed inset-0 z-[10060] flex items-center justify-center bg-black/85 p-4"
        @click.self="closeImageLightbox"
      >
        <button
          type="button"
          class="absolute right-4 top-4 z-10 rounded-full bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/20"
          @click="closeImageLightbox"
        >
          Tutup
        </button>
        <img :src="lightboxImage.url" :alt="lightboxImage.original_name || 'Lampiran'" class="max-h-[90vh] max-w-[96vw] rounded-lg object-contain shadow-2xl" />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
  isNight: { type: Boolean, default: false },
})

const page = usePage()
const authUserId = computed(() => Number(page.props?.auth?.user?.id || 0))

const loading = ref(true)
const items = ref([])
const count = ref(0)
const showModal = ref(false)
const modalItem = ref(null)
const modalCase = ref(null)
const modalLoading = ref(false)
const modalError = ref('')
const activeDivision = ref('service')
const savingVerify = ref(false)
const lightboxImage = ref(null)
const verifyForm = ref({ result: '', notes: '', follow_up_date: '' })

const visible = computed(() => loading.value || count.value > 0)

const pendingDivisions = computed(() => {
  if (modalCase.value) return getVerifierPendingDivisionsFromCase(modalCase.value)
  const list = modalItem.value?.pending_divisions || []
  return Array.isArray(list) ? list : []
})

const activeCapa = computed(() => {
  const row = modalCase.value
  if (!row) return {}
  const divs = row.capa_divisions || {}
  if (divs && typeof divs === 'object' && divs[activeDivision.value]) return divs[activeDivision.value]
  if ((row.capa_active_division || 'service') === activeDivision.value && row.capa && typeof row.capa === 'object') return row.capa
  return {}
})

/** Alias for template readability */
const capa = activeCapa

const boxMuted = computed(() =>
  props.isNight ? 'border-slate-700 bg-slate-800/70' : 'border-slate-200 bg-white',
)
const boxField = computed(() =>
  props.isNight ? 'border-slate-700 bg-slate-900 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-700',
)

const fishboneRows = [
  { key: 'man', title: 'Man (SDM)' },
  { key: 'method', title: 'Method (SOP)' },
  { key: 'machine', title: 'Machine (equipment)' },
  { key: 'material', title: 'Material (bahan)' },
  { key: 'measurement', title: 'Measurement' },
  { key: 'environment', title: 'Environment' },
]

const activeEvidence = computed(() => {
  const arr = activeCapa.value?.evidence
  return Array.isArray(arr) ? arr : []
})
const activeImageEvidence = computed(() => activeEvidence.value.filter((ev) => isImageEvidence(ev)))
const activeNonImageEvidence = computed(() => activeEvidence.value.filter((ev) => !isImageEvidence(ev)))

watch(activeDivision, () => {
  const g = activeCapa.value?.g || {}
  verifyForm.value = {
    result: String(g.result || ''),
    notes: String(g.notes || ''),
    follow_up_date: String(g.follow_up_date || ''),
  }
})

function divisionLabel(v) {
  if (v === 'service') return 'Service'
  if (v === 'kitchen') return 'Kitchen'
  if (v === 'bar') return 'Bar'
  return String(v || '—')
}

function disp(v) {
  if (v === null || v === undefined) return '—'
  const s = String(v).trim()
  return s !== '' ? s : '—'
}

function sourceTypeLabel(source) {
  const s = String(source || '')
  if (s === 'google_review') return 'Google'
  if (s === 'instagram_comment') return 'Instagram'
  if (s === 'guest_comment') return 'Guest Comment'
  return s || '—'
}

function followUpLabel(v) {
  if (v === 'customer') return 'Customer'
  if (v === 'internal') return 'Internal'
  return '—'
}

function impactLine(raw) {
  if (Array.isArray(raw)) {
    const m = { reputasi: 'Reputasi', finansial: 'Finansial', operasional: 'Operasional' }
    const parts = raw.map((k) => m[k] || k).filter(Boolean)
    return parts.length ? parts.join(', ') : '—'
  }
  return '—'
}

function channelLabel(ch, other) {
  const s = String(ch || '').toLowerCase()
  const map = {
    dine_in: 'Dine in',
    online_review: 'Online review',
    delivery: 'Delivery',
    walk_in: 'Walk in',
    other: 'Other',
    google_review: 'Google Review',
    instagram_comment: 'Instagram',
    guest_comment: 'Guest Comment',
  }
  if (s === 'other' && other) return `${map.other}: ${other}`
  return map[s] || disp(ch)
}

const complaintTypeMap = {
  food_quality: 'Food Quality',
  service: 'Service',
  cleanliness: 'Cleanliness',
  waiting_time: 'Waiting Time',
  billing: 'Billing',
  other: 'Others',
}

function complaintTypeLabel(v) {
  return complaintTypeMap[v] || String(v || '—')
}

const immediateActionMap = {
  apology: 'Apology diberikan',
  replace_product: 'Replace product',
  refund_discount: 'Refund / Discount',
  escalate: 'Escalate ke Supervisor / Manager',
  other: 'Lainnya',
}

function immediateActionLabel(v) {
  return immediateActionMap[v] || String(v || '—')
}

const improvementMap = {
  sop: 'SOP',
  training: 'Training',
  equipment: 'Equipment',
  manpower: 'Manpower',
  system: 'System',
}

function improvementAreaLabel(v) {
  return improvementMap[v] || String(v || '—')
}

const contactMethodMap = {
  call: 'Call',
  whatsapp: 'WhatsApp',
  email: 'Email',
}

function contactMethodsLine(arr) {
  if (!Array.isArray(arr) || !arr.length) return '—'
  return arr.map((x) => contactMethodMap[x] || x).join(', ')
}

function userIdLabel(id) {
  const n = Number(id)
  if (!Number.isFinite(n) || n <= 0) return '—'
  if (n === authUserId.value) return `Anda (user #${n})`
  return `User #${n}`
}

function verificationResultLabel(r) {
  const s = String(r || '').toLowerCase()
  if (s === 'effective') return 'Effective — efektif'
  if (s === 'not_effective') return 'Not effective — tidak efektif'
  return '— belum —'
}

function contactedLabel(v) {
  const s = String(v || '').toLowerCase()
  if (s === 'yes') return 'Ya'
  if (s === 'no') return 'Tidak'
  return '—'
}

function satisfactionLabel(v) {
  const s = String(v || '').toLowerCase()
  if (s === 'satisfied') return 'Satisfied — puas'
  if (s === 'neutral') return 'Neutral — netral'
  if (s === 'unsatisfied') return 'Unsatisfied — tidak puas'
  return '—'
}

function formatDt(v) {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleString('id-ID')
  } catch {
    return String(v)
  }
}

async function load() {
  loading.value = true
  try {
    const res = await fetch(route('customer-voice-command-center.pending-capa-verifications'), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    if (data.success) {
      items.value = data.items || []
      count.value = data.count ?? items.value.length
    } else {
      items.value = []
      count.value = 0
    }
  } catch {
    items.value = []
    count.value = 0
  } finally {
    loading.value = false
  }
}

async function openVerifyModal(item) {
  modalItem.value = item
  showModal.value = true
  modalLoading.value = true
  modalError.value = ''
  modalCase.value = null
  try {
    const res = await fetch(route('customer-voice-command-center.cases.brief', item.id), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    if (!data.success || !data.case) throw new Error(data.message || 'Gagal memuat detail case.')
    modalCase.value = data.case
    const pendingForMe = getVerifierPendingDivisionsFromCase(modalCase.value)
    const firstDiv = pendingForMe[0] || (item.pending_divisions || [])[0] || 'service'
    activeDivision.value = firstDiv
    const g = modalCase.value?.capa_divisions?.[firstDiv]?.g || modalCase.value?.capa?.g || {}
    verifyForm.value = {
      result: String(g.result || ''),
      notes: String(g.notes || ''),
      follow_up_date: String(g.follow_up_date || ''),
    }
  } catch (e) {
    modalError.value = e?.message || 'Gagal memuat detail CAPA.'
  } finally {
    modalLoading.value = false
  }
}

function closeVerifyModal() {
  showModal.value = false
  modalItem.value = null
  modalCase.value = null
  modalError.value = ''
  lightboxImage.value = null
}

function isImageEvidence(ev) {
  const mime = String(ev?.mime || '').toLowerCase()
  if (mime.startsWith('image/')) return true
  const ref = String(ev?.url || ev?.path || ev?.original_name || '').toLowerCase()
  return /\.(png|jpe?g|gif|webp|bmp|svg)(\?|$)/.test(ref)
}

function openImageLightbox(ev) {
  if (!ev?.url) return
  lightboxImage.value = ev
}

function closeImageLightbox() {
  lightboxImage.value = null
}

function getVerifierPendingDivisionsFromCase(caseRow) {
  const uid = Number(authUserId.value || 0)
  if (!uid || !caseRow || typeof caseRow !== 'object') return []

  const divisions = ['service', 'kitchen', 'bar']
  const result = []
  for (const div of divisions) {
    let capa = null
    const divs = caseRow.capa_divisions
    if (divs && typeof divs === 'object' && divs[div] && typeof divs[div] === 'object') {
      capa = divs[div]
    } else if ((caseRow.capa_active_division || 'service') === div && caseRow.capa && typeof caseRow.capa === 'object') {
      capa = caseRow.capa
    }
    if (!capa || typeof capa !== 'object') continue
    const g = capa.g && typeof capa.g === 'object' ? capa.g : {}
    const verifierId = Number(g.verified_by_user_id || 0)
    const resultValue = String(g.result || '').toLowerCase()
    const done = resultValue === 'effective' || resultValue === 'not_effective'
    if (verifierId === uid && !done) {
      result.push(div)
    }
  }
  return result
}

async function submitVerify() {
  if (!modalItem.value?.id || !modalCase.value) return
  if (!verifyForm.value.result) {
    Swal.fire({ icon: 'warning', title: 'Pilih hasil verifikasi', text: 'Isi Effective / Not Effective dulu.' })
    return
  }
  const division = activeDivision.value
  const base = modalCase.value?.capa_divisions?.[division] && typeof modalCase.value.capa_divisions[division] === 'object'
    ? JSON.parse(JSON.stringify(modalCase.value.capa_divisions[division]))
    : JSON.parse(JSON.stringify(modalCase.value?.capa || {}))
  if (!base.g || typeof base.g !== 'object') base.g = {}
  base.g.verified_by_user_id = authUserId.value || base.g.verified_by_user_id || null
  base.g.result = verifyForm.value.result
  base.g.notes = verifyForm.value.notes || null
  base.g.follow_up_date = verifyForm.value.follow_up_date || null

  savingVerify.value = true
  try {
    const { data } = await axios.post(route('api.approval-app.customer-voice-command-center.cases.capa', modalItem.value.id), {
      capa: base,
      capa_division: division,
    })
    if (!data?.success) throw new Error(data?.message || 'Gagal simpan verifikasi.')
    await Swal.fire({ icon: 'success', title: 'Verifikasi tersimpan', timer: 1300, showConfirmButton: false })
    await load()
    closeVerifyModal()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: e?.response?.data?.message || e?.message || 'Gagal simpan verifikasi.' })
  } finally {
    savingVerify.value = false
  }
}

function openCommandCenter() {
  const base = route('customer-voice-command-center.index')
  router.visit(`${base}?show_all=1`)
}

onMounted(() => {
  load()
})
</script>
