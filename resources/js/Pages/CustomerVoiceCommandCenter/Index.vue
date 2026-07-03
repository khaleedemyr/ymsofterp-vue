<template>
  <AppLayout>
    <div class="w-full max-w-none px-3 py-5 sm:px-4 lg:px-6">
      <div class="rounded-2xl border border-slate-200 bg-white/90 shadow-sm backdrop-blur">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
          <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Customer Voice Command Center</h1>
            <p class="mt-1 text-sm text-slate-500">Monitoring sentimen customer lintas channel dengan aksi follow-up cepat.</p>
            <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
              <span class="rounded-full bg-rose-100 px-2 py-1 font-semibold text-rose-700">SLA critical: 30m</span>
              <span class="rounded-full bg-orange-100 px-2 py-1 font-semibold text-orange-700">SLA major: 2j</span>
              <span class="rounded-full bg-amber-100 px-2 py-1 font-semibold text-amber-700">SLA minor: 24j</span>
              <span class="rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-600">Neutral/Positive: tanpa SLA</span>
            </div>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="syncing"
            @click="syncNow"
          >
            <span>{{ syncing ? 'Syncing...' : 'Sync Data' }}</span>
          </button>
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        {{ $page.props.flash.error }}
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total Cases</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ summary.total_cases || 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Open Cases</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ summary.open_cases || 0 }}</p>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-rose-400">Critical Open</p>
          <p class="mt-2 text-2xl font-bold text-rose-600">{{ summary.critical_open ?? summary.severe_open ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-amber-500">Overdue Open</p>
          <p class="mt-2 text-2xl font-bold text-amber-600">{{ summary.overdue_open || 0 }}</p>
        </div>
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Median First Response</p>
          <p class="mt-2 text-lg font-bold text-slate-900">{{ formatMinutes(kpis.first_response_median_minutes) }}</p>
          <p class="mt-1 text-[11px] text-slate-400">avg: {{ formatMinutes(kpis.first_response_avg_minutes) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Avg Resolution Time</p>
          <p class="mt-2 text-lg font-bold text-slate-900">{{ formatMinutes(kpis.resolution_avg_minutes) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">SLA Compliance</p>
          <p class="mt-2 text-lg font-bold text-emerald-700">{{ formatPercent(kpis.sla_compliance_pct) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Repeat Issue Rate</p>
          <p class="mt-2 text-lg font-bold text-amber-700">{{ formatPercent(kpis.repeat_issue_rate_pct) }}</p>
          <p class="mt-1 text-[11px] text-slate-400">{{ kpis.repeat_issue_window_days || 30 }} hari terakhir</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Top Negative Outlet (30d)</p>
          <p class="mt-2 text-sm font-bold text-slate-900">{{ kpis.negative_top_outlet_30d?.nama_outlet || '-' }}</p>
          <p class="mt-1 text-xs text-slate-500">{{ kpis.negative_top_outlet_30d?.total || 0 }} kasus negatif</p>
        </div>
      </div>

      <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Trend 14 Hari</p>
          <p class="text-xs text-slate-500">Total vs Negatif</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[680px] text-xs">
            <thead>
              <tr class="border-b border-slate-100 text-slate-500">
                <th class="px-2 py-2 text-left font-semibold">Tanggal</th>
                <th class="px-2 py-2 text-left font-semibold">Total</th>
                <th class="px-2 py-2 text-left font-semibold">Negatif</th>
                <th class="px-2 py-2 text-left font-semibold">Rasio Negatif</th>
                <th class="px-2 py-2 text-left font-semibold">Bar</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in trend" :key="row.date" class="border-b border-slate-50">
                <td class="px-2 py-2 text-slate-600">{{ row.date }}</td>
                <td class="px-2 py-2 font-semibold text-slate-800">{{ row.total_cases }}</td>
                <td class="px-2 py-2 font-semibold text-rose-700">{{ row.negative_cases }}</td>
                <td class="px-2 py-2 text-slate-600">{{ formatPercent(row.total_cases > 0 ? (row.negative_cases / row.total_cases) * 100 : 0) }}</td>
                <td class="px-2 py-2">
                  <div class="h-2 w-full rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-rose-500" :style="{ width: `${trendNegativePct(row)}%` }"></div>
                  </div>
                </td>
              </tr>
              <tr v-if="!trend.length">
                <td colspan="5" class="px-2 py-3 text-center text-slate-400">Belum ada data trend.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4 grid gap-3 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Performa PIC</p>
            <p class="text-xs text-slate-500">{{ props.perfWindowDays }} hari terakhir</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] text-xs">
              <thead>
                <tr class="border-b border-slate-100 text-slate-500">
                  <th class="px-2 py-2 text-left font-semibold">PIC</th>
                  <th class="px-2 py-2 text-left font-semibold">Total</th>
                  <th class="px-2 py-2 text-left font-semibold">Resolved</th>
                  <th class="px-2 py-2 text-left font-semibold">Open</th>
                  <th class="px-2 py-2 text-left font-semibold">Avg Resp</th>
                  <th class="px-2 py-2 text-left font-semibold">SLA</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in picPerformance" :key="row.assignee_id" class="border-b border-slate-50">
                  <td class="px-2 py-2 font-semibold text-slate-800">{{ row.assignee_name || '-' }}</td>
                  <td class="px-2 py-2">{{ row.total_cases }}</td>
                  <td class="px-2 py-2 text-emerald-700">{{ row.resolved_cases }}</td>
                  <td class="px-2 py-2 text-amber-700">{{ row.open_cases }}</td>
                  <td class="px-2 py-2">{{ formatMinutes(row.avg_first_response_minutes) }}</td>
                  <td class="px-2 py-2">{{ formatPercent(row.sla_compliance_pct) }}</td>
                </tr>
                <tr v-if="!picPerformance.length">
                  <td colspan="6" class="px-2 py-3 text-center text-slate-400">Belum ada data performa PIC.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Performa Outlet</p>
            <p class="text-xs text-slate-500">{{ props.perfWindowDays }} hari terakhir</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-xs">
              <thead>
                <tr class="border-b border-slate-100 text-slate-500">
                  <th class="px-2 py-2 text-left font-semibold">Outlet</th>
                  <th class="px-2 py-2 text-left font-semibold">Total</th>
                  <th class="px-2 py-2 text-left font-semibold">Negatif</th>
                  <th class="px-2 py-2 text-left font-semibold">Neg Rate</th>
                  <th class="px-2 py-2 text-left font-semibold">Resolved</th>
                  <th class="px-2 py-2 text-left font-semibold">SLA</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in outletPerformance" :key="`${row.id_outlet || 'x'}-${row.outlet_name}`" class="border-b border-slate-50">
                  <td class="px-2 py-2 font-semibold text-slate-800">{{ row.outlet_name || '-' }}</td>
                  <td class="px-2 py-2">{{ row.total_cases }}</td>
                  <td class="px-2 py-2 text-rose-700">{{ row.negative_cases }}</td>
                  <td class="px-2 py-2">{{ formatPercent(row.negative_rate_pct) }}</td>
                  <td class="px-2 py-2 text-emerald-700">{{ row.resolved_cases }}</td>
                  <td class="px-2 py-2">{{ formatPercent(row.sla_compliance_pct) }}</td>
                </tr>
                <tr v-if="!outletPerformance.length">
                  <td colspan="6" class="px-2 py-3 text-center text-slate-400">Belum ada data performa outlet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-8">
          <input
            v-model="q"
            type="text"
            placeholder="Cari author / ringkasan / komentar / outlet"
            class="md:col-span-2 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
            @keyup.enter="applyFilters"
          />
          <select v-model="status" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100" @change="applyFilters">
            <option value="">Semua Courtesy Status</option>
            <option value="new">New</option>
            <option value="internal_follow_up">Internal Follow Up</option>
            <option value="courtesy_done">Courtesy Done</option>
          </select>
          <select v-model="followUpStatus" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100" @change="applyFilters">
            <option value="">Semua Follow Up Status</option>
            <option value="new">New</option>
            <option value="on_progress">On Progress</option>
            <option value="done">Done</option>
          </select>
          <select v-model="severity" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100" @change="applyFilters">
            <option value="">Semua severity</option>
            <option value="critical">Critical</option>
            <option value="major">Major</option>
            <option value="minor">Minor</option>
            <option value="severe">Critical (arsip)</option>
            <option value="negative">Major (arsip)</option>
            <option value="mild_negative">Minor (arsip)</option>
            <option value="neutral">Neutral</option>
            <option value="positive">Positive</option>
          </select>
          <select v-model="sourceType" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100" @change="applyFilters">
            <option value="">Semua source</option>
            <option value="google_review">Google Review</option>
            <option value="instagram_comment">Instagram Comment</option>
            <option value="guest_comment">Guest Comment</option>
          </select>
          <select v-model="idOutlet" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100" @change="applyFilters">
            <option value="">Semua outlet</option>
            <option v-for="o in outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
          </select>
          <label class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm text-slate-700">
            <input v-model="overdueOnly" type="checkbox" class="rounded border-slate-300" @change="applyFilters" />
            Overdue saja
          </label>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3">
          <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input v-model="showAll" type="checkbox" class="rounded border-slate-300" @change="applyFilters" />
            Tampilkan semua kasus
          </label>
          <button
            type="button"
            class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100"
            @click="openArchiveModal"
          >
            Arsip: Done &amp; positif
          </button>
          <p v-if="!showAll" class="text-xs text-slate-500">
            Antrian kerja: status masih <span class="font-semibold text-slate-600">open</span> (New, Internal Follow Up) — severity selain positif &amp; netral.
          </p>
        </div>
        <div class="mt-3 flex flex-col gap-3 border-t border-slate-100 pt-3 md:flex-row md:flex-wrap md:items-end">
          <div class="flex flex-wrap gap-3">
            <label class="flex flex-col gap-1 text-xs font-semibold text-slate-500">
              Event dari
              <input
                v-model="dateFrom"
                type="date"
                class="h-10 rounded-xl border border-slate-200 px-3 text-sm text-slate-800 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
              />
            </label>
            <label class="flex flex-col gap-1 text-xs font-semibold text-slate-500">
              Event sampai
              <input
                v-model="dateTo"
                type="date"
                class="h-10 rounded-xl border border-slate-200 px-3 text-sm text-slate-800 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
              />
            </label>
            <label class="flex flex-col justify-end text-xs font-semibold text-slate-500">
              <span class="invisible">Load</span>
              <button
                type="button"
                class="h-10 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700"
                @click="applyFilters"
              >
                Load
              </button>
            </label>
          </div>
          <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
            <a
              :href="exportPdfHref"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex h-10 items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800"
              :class="{ 'pointer-events-none opacity-45': !canExportPdf }"
              @click="(e) => { if (!canExportPdf) e.preventDefault() }"
            >
              Export PDF (range + filter)
            </a>
            <p class="text-xs leading-relaxed text-slate-500">
              Isi <span class="font-semibold text-slate-600">Event dari / sampai</span>, gunakan filter di atas bila perlu, lalu Export — hingga <span class="font-semibold text-slate-600">600</span> baris terbaru per file (jika lebih banyak, persempit filter atau export beberapa kali). Timeline di PDF: 8 aktivitas terakhir per case.
            </p>
          </div>
        </div>
      </div>

      <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1860px] text-sm">
            <thead class="bg-slate-50">
              <tr class="text-xs uppercase tracking-wide text-slate-500">
                <th class="px-3 py-3 text-left font-semibold">Waktu</th>
                <th class="px-3 py-3 text-left font-semibold min-w-[180px]">Outlet / Source</th>
                <th
                  class="px-3 py-3 text-left font-semibold min-w-[200px]"
                  title="Pilih satu atau lebih user regional. Notifikasi FU &amp; CAPA otomatis terkirim saat user dipilih (bukan ke akun yang sedang login)."
                >
                  Regional
                </th>
                <th class="px-3 py-3 text-left font-semibold min-w-[140px]">Tamu</th>
                <th class="px-3 py-3 text-left font-semibold min-w-[260px]">FU / Severity / Jenis komplain</th>
                <th class="px-3 py-3 text-left font-semibold min-w-[340px]">Ringkasan</th>
                <th class="px-3 py-3 text-left font-semibold min-w-[100px]" title="Berdasarkan isian tersimpan di meta CAPA">CAPA</th>
                <th
                  class="px-3 py-3 text-left font-semibold min-w-[108px]"
                  title="Status approval CAPA per divisi"
                >
                  Approval CAPA
                </th>
                <th class="px-3 py-3 text-left font-semibold">SLA</th>
                <th class="px-3 py-3 text-left font-semibold">CS PIC</th>
                <th class="px-3 py-3 text-left font-semibold">Courtesy Status</th>
                <th class="px-3 py-3 text-left font-semibold">Follow Up Status</th>
                <th class="px-3 py-3 text-left font-semibold">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!cases.data?.length">
                <td colspan="13" class="px-4 py-14 text-center text-slate-400">Belum ada case.</td>
              </tr>
              <template v-for="row in cases.data" :key="row.id">
                <tr
                  class="align-top transition-colors"
                  :class="statusRowClasses(caseForms[row.id]?.status || row.status).main"
                >
                  <td class="px-3 py-3 whitespace-nowrap text-xs text-slate-600">{{ formatDate(row.event_at) }}</td>
                  <td class="px-3 py-3">
                    <div class="font-semibold text-slate-700">{{ row.nama_outlet || '-' }}</div>
                    <span class="mt-1 inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                      {{ sourceLabel(row.source_type) }}
                    </span>
                  </td>
                  <td class="px-3 py-3 align-top min-w-[200px]">
                    <NotifyUserMultiPicker
                      :model-value="caseForms[row.id].regional_user_ids"
                      :assignees="assignees"
                      :disabled="updatingCaseId === row.id || regionalSavingCaseId === row.id"
                      placeholder="Cari nama…"
                      @update:model-value="(ids) => onRegionalUsersChange(row.id, ids)"
                    />
                  </td>
                  <td class="px-3 py-3 max-w-[200px]">
                    <div class="font-semibold text-slate-800">{{ row.author_name || '—' }}</div>
                    <div class="mt-0.5 text-[11px] text-slate-500">{{ row.customer_contact || '—' }}</div>
                    <div class="mt-0.5 text-[11px] text-slate-500">{{ row.customer_email || '—' }}</div>
                  </td>
                  <td class="px-3 py-3 align-top" style="min-width: 240px;">
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                      <select
                        v-model="caseForms[row.id].follow_up_target"
                        :disabled="updatingCaseId === row.id"
                        style="display: block; width: 100%; height: 36px; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 8px; font-size: 12px; outline: none;"
                      >
                        <option value="">FU: belum ditentukan</option>
                        <option value="customer">FU: Customer</option>
                        <option value="internal">FU: Internal</option>
                      </select>
                      <select
                        v-model="caseForms[row.id].severity"
                        :disabled="updatingCaseId === row.id"
                        :style="{
                          display: 'block', width: '100%', height: '36px', borderRadius: '8px',
                          padding: '0 8px', fontSize: '12px', fontWeight: '600', outline: 'none',
                          border: '1px solid ' + severityBorderColor(caseForms[row.id].severity),
                          backgroundColor: severityBgColor(caseForms[row.id].severity),
                          color: severityTextColor(caseForms[row.id].severity),
                        }"
                      >
                        <option value="critical">Critical</option>
                        <option value="major">Major</option>
                        <option value="minor">Minor</option>
                        <option value="neutral">Neutral</option>
                        <option value="positive">Positive</option>
                      </select>
                      <div style="position: relative;">
                        <div
                          style="display: flex; flex-wrap: wrap; align-items: center; gap: 4px; min-height: 36px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; padding: 4px 8px; cursor: pointer;"
                          @click="toggleTopicPicker(row.id)"
                        >
                          <template v-if="caseForms[row.id].topics.length">
                            <span
                              v-for="t in caseForms[row.id].topics"
                              :key="`btn-${row.id}-${t}`"
                              style="display: inline-flex; border-radius: 9999px; border: 1px solid #ddd6fe; background: #f5f3ff; padding: 2px 8px; font-size: 11px; font-weight: 500; color: #4c1d95;"
                            >{{ topicLabelFor(t) }}</span>
                          </template>
                          <span style="font-size: 12px; color: #94a3b8;">{{ caseForms[row.id].topics.length ? '✎' : '+ Jenis komplain' }}</span>
                        </div>
                        <div v-if="openTopicPickerCaseId === row.id" style="position: fixed; inset: 0; z-index: 20;" @click="openTopicPickerCaseId = null"></div>
                        <div
                          v-if="openTopicPickerCaseId === row.id"
                          style="position: absolute; left: 0; top: 100%; z-index: 30; margin-top: 4px; max-height: 240px; width: 240px; overflow-y: auto; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; padding: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,.1);"
                        >
                          <p style="margin-bottom: 6px; padding: 0 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Jenis Komplain</p>
                          <label
                            v-for="opt in topicEditOptions"
                            :key="`tp-${row.id}-${opt.v}`"
                            style="display: flex; align-items: center; gap: 8px; border-radius: 8px; padding: 6px 8px; font-size: 12px; color: #334155; cursor: pointer;"
                            @mouseenter="$event.target.style.background='#f5f3ff'"
                            @mouseleave="$event.target.style.background='transparent'"
                          >
                            <input
                              type="checkbox"
                              :value="opt.v"
                              v-model="caseForms[row.id].topics"
                              style="width: 16px; height: 16px; border-radius: 4px;"
                            />
                            {{ opt.label }}
                          </label>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-3 py-3 min-w-[340px]">
                    <div class="text-[11px] text-slate-500">{{ row.summary_id || '-' }}</div>
                    <div
                      class="mt-1 max-h-28 overflow-y-auto pr-1 text-[12px] font-semibold leading-relaxed text-slate-800 whitespace-pre-wrap"
                      :title="row.raw_text || 'Komentar asli tidak tersedia'"
                    >
                      {{ row.raw_text || 'Komentar asli tidak tersedia' }}
                    </div>
                  </td>
                  <td class="px-3 py-3 align-top">
                    <div class="space-y-1">
                      <div
                        v-for="div in capaDivisionDefs"
                        :key="`capa-${row.id}-${div.id}`"
                        class="flex items-center gap-1.5 text-[10px] text-slate-600"
                      >
                        <span class="w-[44px] shrink-0 font-semibold text-slate-500">{{ div.short }}</span>
                        <span
                          class="inline-flex h-5 w-5 items-center justify-center rounded-full border text-[10px]"
                          :class="divisionCapaFilled(row, div.id) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600'"
                          :title="divisionCapaFilled(row, div.id) ? `CAPA ${div.label} sudah terisi` : `CAPA ${div.label} belum diisi`"
                        >
                          <i :class="divisionCapaFilled(row, div.id) ? 'fa fa-check' : 'fa fa-minus'" aria-hidden="true" />
                        </span>
                      </div>
                      <span v-if="capaAuditInfo(row)" class="text-[10px] text-slate-500">
                        {{ capaAuditInfo(row) }}
                      </span>
                    </div>
                  </td>
                  <td class="px-3 py-3 align-top">
                    <div class="space-y-1">
                      <div
                        v-for="div in capaDivisionDefs"
                        :key="`verif-${row.id}-${div.id}`"
                        class="flex items-center gap-1.5 text-[10px] text-slate-600"
                      >
                        <span class="w-[44px] shrink-0 font-semibold text-slate-500">{{ div.short }}</span>
                        <span
                          class="inline-flex h-5 w-5 items-center justify-center rounded-full border text-[10px]"
                          :class="divisionVerificationDisplay(row, div.id).badgeClass"
                          :title="divisionVerificationDisplay(row, div.id).title"
                        >
                          <i :class="divisionVerificationDisplay(row, div.id).iconClass" aria-hidden="true" />
                        </span>
                      </div>
                      <span v-if="verificationAuditInfo(row)" class="text-[10px] text-slate-500">
                        {{ verificationAuditInfo(row) }}
                      </span>
                    </div>
                  </td>
                  <td class="px-3 py-3 min-w-[190px]">
                    <div class="text-xs font-semibold" :class="slaClass(row)">{{ slaLabel(row) }}</div>
                    <div v-if="row.due_at" class="mt-1 text-[11px] text-slate-400">due {{ formatDate(row.due_at) }}</div>
                  </td>
                  <td class="px-3 py-3 min-w-[200px] align-top">
                    <CapaUserPicker
                      v-model="caseForms[row.id].assigned_to"
                      :assignees="assignees"
                      placeholder="Cari CS PIC…"
                      clearable
                      compact
                      :disabled="updatingCaseId === row.id"
                    />
                  </td>
                  <td class="px-3 py-3 min-w-[150px]">
                    <select
                      v-model="caseForms[row.id].status"
                      class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                    >
                      <option value="new">New</option>
                      <option value="internal_follow_up">Internal Follow Up</option>
                      <option value="courtesy_done">Courtesy Done</option>
                    </select>
                  </td>
                  <td class="px-3 py-3 min-w-[150px]">
                    <select
                      v-model="caseForms[row.id].follow_up_status"
                      class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                    >
                      <option value="new">New</option>
                      <option value="on_progress">On Progress</option>
                      <option value="done">Done</option>
                    </select>
                  </td>
                  <td class="px-3 py-3 min-w-[320px]">
                    <div class="flex flex-wrap gap-1.5">
                      <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-100"
                        @click="openDetail(row.id)"
                      >
                        Detail
                      </button>
                      <button
                        type="button"
                        class="rounded-lg bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-slate-700 disabled:opacity-60"
                        :disabled="updatingCaseId === row.id"
                        @click="updateCase(row.id)"
                      >
                        {{ updatingCaseId === row.id ? 'Menyimpan…' : 'Simpan' }}
                      </button>
                      <button
                        type="button"
                        class="relative rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 pr-3 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-100"
                        @click="openNoteModal(row.id)"
                      >
                        Catatan
                        <span
                          v-if="noteCountFor(row.id) > 0"
                          class="absolute -right-1 -top-1 flex h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-violet-600 px-1 text-[9px] font-bold leading-none text-white shadow-sm ring-2 ring-white"
                        >
                          {{ noteCountFor(row.id) > 99 ? '99+' : noteCountFor(row.id) }}
                        </span>
                      </button>
                      <button
                        type="button"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] font-semibold text-amber-700 transition hover:bg-amber-100"
                        @click="toggleActivities(row.id)"
                      >
                        Timeline
                      </button>
                      <button
                        type="button"
                        class="rounded-lg border border-[#25D366]/30 bg-[#25D366]/10 px-2.5 py-1.5 text-[11px] font-semibold text-[#128C7E] transition hover:bg-[#25D366]/20 disabled:opacity-60"
                        :disabled="sharingCaseId === row.id"
                        title="Share ke WhatsApp"
                        @click="shareToWhatsApp(row)"
                      >
                        <i :class="sharingCaseId === row.id ? 'fas fa-spinner fa-spin' : 'fab fa-whatsapp'" class="mr-0.5"></i>
                        WA
                      </button>
                      <a
                        :href="capaExportPdfUrl(row.id)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-800 transition hover:bg-emerald-100"
                        @click="(e) => onCapaExportClick(e, row.id)"
                      >
                        CAPA PDF
                      </a>
                      <a
                        :href="capaExportExcelUrl(row.id)"
                        class="inline-flex rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-[11px] font-semibold text-sky-800 transition hover:bg-sky-100"
                        @click="(e) => onCapaExportClick(e, row.id)"
                      >
                        CAPA XLS
                      </a>
                    </div>
                  </td>
                </tr>
                <tr
                  v-if="openedActivityCaseId === row.id"
                  class="border-t"
                  :class="statusRowClasses(caseForms[row.id]?.status || row.status).timeline"
                >
                  <td class="px-3 py-3" colspan="16">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Aktivitas terbaru</div>
                    <div v-if="!activitiesFor(row.id).length" class="text-xs text-slate-400">Belum ada aktivitas.</div>
                    <div v-else class="space-y-2">
                      <div
                        v-for="a in activitiesFor(row.id)"
                        :key="a.id"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                      >
                        <div class="font-semibold text-slate-800">
                          {{ a.activity_type }}
                          <span class="ml-1 font-normal text-slate-400">· {{ formatDate(a.created_at) }}</span>
                        </div>
                        <div v-if="a.actor_name" class="text-slate-500">oleh {{ a.actor_name }}</div>
                        <div v-if="a.from_status || a.to_status" class="text-slate-500">{{ voiceCaseStatusLabel(a.from_status) }} → {{ voiceCaseStatusLabel(a.to_status) }}</div>
                        <div v-if="a.note" class="mt-1 leading-relaxed">{{ a.note }}</div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div v-if="cases.links?.length" class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-100 bg-white px-3 py-3">
          <template v-for="(link, index) in cases.links" :key="index">
            <Link
              v-if="link.url"
              :href="link.url"
              class="min-w-[34px] rounded-lg border px-3 py-1.5 text-xs font-semibold"
              :class="link.active ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100'"
              v-html="link.label"
            />
            <span
              v-else
              class="min-w-[34px] rounded-lg border border-slate-100 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-400"
              v-html="link.label"
            />
          </template>
        </div>
      </div>
    </div>

    <div v-if="selectedCase" class="fixed inset-0 z-50 flex justify-end bg-slate-900/40" @click.self="closeDetail">
      <div class="h-full w-full max-w-full overflow-y-auto scroll-smooth border-l border-slate-200 bg-white shadow-2xl sm:max-w-4xl xl:max-w-5xl">
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-5 sm:py-4">
          <div class="min-w-0 flex-1">
            <h3 class="truncate text-base font-bold text-slate-900">Case Detail #{{ selectedCase.id }}</h3>
            <p class="truncate text-xs text-slate-500">{{ sourceLabel(selectedCase.source_type) }} · {{ formatDate(selectedCase.event_at) }}</p>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <button
              type="button"
              class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-[#25D366]/30 bg-[#25D366]/10 px-4 py-2 text-sm font-semibold text-[#128C7E] hover:bg-[#25D366]/20 disabled:opacity-60"
              :disabled="sharingCaseId === selectedCase.id"
              title="Share ke WhatsApp"
              @click="shareToWhatsApp(selectedCase)"
            >
              <i :class="sharingCaseId === selectedCase.id ? 'fas fa-spinner fa-spin' : 'fab fa-whatsapp'"></i>
              Share WA
            </button>
            <button
              type="button"
              class="min-h-11 shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 active:bg-slate-200"
              @click="closeDetail"
            >
              Tutup
            </button>
          </div>
        </div>

        <div class="space-y-5 px-4 py-4 pb-[max(2rem,env(safe-area-inset-bottom))] sm:px-5">
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Outlet</div>
              <div class="mt-1 text-sm font-semibold text-slate-800">{{ selectedCase.nama_outlet || '-' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Source Ref</div>
              <div class="mt-1 text-sm font-semibold text-slate-800 break-all">{{ selectedCase.source_ref || '-' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Nama tamu / penulis</div>
              <div class="mt-1 text-sm font-semibold text-slate-800">{{ selectedCase.author_name || '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">No. HP</div>
              <div class="mt-1 text-sm font-semibold text-slate-800">{{ selectedCase.customer_contact || '—' }}</div>
              <p class="mt-1 text-[10px] text-slate-400">Guest comment: dari formulir. Google/IG: biasanya kosong.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Email</div>
              <div class="mt-1 text-sm font-semibold text-slate-800">{{ selectedCase.customer_email || '—' }}</div>
              <p class="mt-1 text-[10px] text-slate-400">Diisi jika ada kolom email di Guest Comment + sync; bisa juga ditambahkan manual di meta.</p>
            </div>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3">
              <div class="text-[11px] uppercase tracking-wide text-indigo-900">PIC penerima complaint</div>
              <template v-if="selectedCase.assigned_to != null">
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ selectedCase.assigned_to_name || '—' }}</div>
                <div v-if="selectedCase.assigned_to_jabatan" class="text-xs text-slate-600">{{ selectedCase.assigned_to_jabatan }}</div>
              </template>
              <div v-else class="mt-1 text-sm font-medium text-amber-900">Belum ada PIC.</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Follow-up target</div>
              <div class="mt-1 flex flex-wrap items-center gap-2">
                <span
                  v-if="selectedCase.follow_up_target"
                  class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                  :class="selectedCase.follow_up_target === 'customer' ? 'border-violet-200 bg-violet-50 text-violet-800' : 'border-slate-200 bg-slate-50 text-slate-700'"
                >
                  {{ followUpLabel(selectedCase.follow_up_target) }}
                </span>
                <span v-else class="text-sm text-slate-400">—</span>
                <span class="text-[10px] text-slate-400">Dari AI + ingestion (Google/Guest). Kosong → jalankan klasifikasi laporan &amp; Sync Data.</span>
              </div>
              <div v-if="selectedCase.impact?.length" class="mt-2 text-[11px] text-slate-600">
                <span class="font-semibold text-slate-500">Dampak:</span>
                {{ impactLabel(selectedCase.impact) }}
              </div>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-5">
            <div class="rounded-xl border border-slate-200 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Severity</div>
              <span class="mt-1 inline-flex rounded-full px-2 py-1 text-[11px] font-semibold" :class="severityClass(selectedCase.severity)">
                {{ selectedCase.severity || 'neutral' }}
              </span>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Risk</div>
              <div class="mt-1 text-sm font-semibold text-slate-800">{{ selectedCase.risk_score ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Courtesy Status</div>
              <span class="mt-1 inline-flex rounded-full px-2 py-1 text-[11px] font-semibold" :class="statusClass(selectedCase.status)">
                {{ voiceCaseStatusLabel(selectedCase.status) }}
              </span>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Follow Up Status</div>
              <span class="mt-1 inline-flex rounded-full px-2 py-1 text-[11px] font-semibold" :class="followUpStatusClass(selectedCase.follow_up_status)">
                {{ followUpStatusLabel(selectedCase.follow_up_status) }}
              </span>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">SLA</div>
              <div class="mt-1 text-xs font-semibold" :class="slaClass(selectedCase)">{{ slaLabel(selectedCase) }}</div>
              <div v-if="selectedCase.due_at" class="mt-1 text-[11px] text-slate-400">due {{ formatDate(selectedCase.due_at) }}</div>
            </div>
          </div>

          <div class="rounded-xl border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Summary</div>
            <div class="mt-2 text-sm font-semibold text-slate-800">{{ selectedCase.summary_id || '-' }}</div>
          </div>

          <div class="rounded-xl border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Jenis komplain</div>
            <div v-if="complaintTypeLabelsFor(selectedCase).length" class="mt-2 flex flex-wrap gap-1">
              <span
                v-for="(lbl, idx) in complaintTypeLabelsFor(selectedCase)"
                :key="`detail-ct-${selectedCase.id}-${idx}`"
                class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-900"
              >
                {{ lbl }}
              </span>
            </div>
            <div v-else class="mt-2 text-xs text-slate-400">—</div>
          </div>

          <div class="rounded-xl border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Komentar Asli</div>
            <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ selectedCase.raw_text || '-' }}</div>
          </div>

          <div
            v-if="selectedCase.gcf_capa && (selectedCase.gcf_capa.kronologi || selectedCase.gcf_capa.corrective_action || selectedCase.gcf_capa.preventive_action)"
            class="rounded-xl border-2 border-emerald-200 bg-emerald-50/60 p-4 space-y-3"
          >
            <div class="flex items-center gap-2">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100">
                <i class="fa-solid fa-clipboard-check text-emerald-600 text-sm"></i>
              </div>
              <div>
                <div class="text-sm font-bold text-emerald-900">CAPA dari Outlet Leader</div>
                <div class="text-[10px] text-emerald-700">
                  Diisi saat verifikasi Guest Comment
                  <template v-if="selectedCase.gcf_capa.filled_by_name">
                    oleh <span class="font-semibold">{{ selectedCase.gcf_capa.filled_by_name }}</span>
                  </template>
                  <template v-if="selectedCase.gcf_capa.filled_at">
                    · {{ formatDate(selectedCase.gcf_capa.filled_at) }}
                  </template>
                </div>
              </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-1">
              <div class="rounded-lg border border-emerald-200 bg-white p-3">
                <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Kronologi</div>
                <div class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ selectedCase.gcf_capa.kronologi || '—' }}</div>
              </div>
              <div class="rounded-lg border border-emerald-200 bg-white p-3">
                <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Corrective Action</div>
                <div class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ selectedCase.gcf_capa.corrective_action || '—' }}</div>
              </div>
              <div class="rounded-lg border border-emerald-200 bg-white p-3">
                <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Preventive Action</div>
                <div class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ selectedCase.gcf_capa.preventive_action || '—' }}</div>
              </div>
            </div>
          </div>

          <CapaFormPanel
            :key="`${selectedCase.id}-${capaResetKey}`"
            :case-id="selectedCase.id"
            :initial-capa="selectedCase.capa"
            :initial-capa-divisions="selectedCase.capa_divisions || {}"
            :active-division="selectedCase.capa_active_division || 'service'"
            :approval-summaries="selectedCase.capa_division_approval || {}"
            :source-type="selectedCase.source_type || ''"
            :source-complaint-topics="Array.isArray(selectedCase.topics) ? selectedCase.topics : []"
            :source-complaint-text="selectedCase.raw_text || ''"
            :source-severity="selectedCase.severity || ''"
            :source-impact="Array.isArray(selectedCase.impact) ? selectedCase.impact : []"
            :outlet-name="selectedCase.nama_outlet || ''"
            :assignees="assignees"
            :auth-user="capa_auth_user"
            :assigned-to-id="selectedCase.assigned_to != null ? Number(selectedCase.assigned_to) : null"
            :assigned-to-name="selectedCase.assigned_to_name || ''"
            :assigned-to-jabatan="selectedCase.assigned_to_jabatan || ''"
            :saving="capaSaving"
            :deleting="capaDeleting"
            :focus-section-id="capaDetailFocusSection"
            @save="submitCapa"
            @reset="resetCapaDraft"
            @delete-capa="deleteStoredCapa"
            @focused-section="capaDetailFocusSection = null"
            @approval-changed="reloadSelectedCase"
            @dirty-changed="capaFormDirty = $event"
          />

          <div class="rounded-xl border border-slate-200 p-3">
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Timeline Aktivitas</div>
            <div v-if="!activitiesFor(selectedCase.id).length" class="mt-2 text-xs text-slate-400">Belum ada aktivitas.</div>
            <div v-else class="mt-2 space-y-2">
              <div
                v-for="a in activitiesFor(selectedCase.id)"
                :key="a.id"
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700"
              >
                <div class="font-semibold text-slate-800">
                  {{ a.activity_type }}
                  <span class="ml-1 font-normal text-slate-400">· {{ formatDate(a.created_at) }}</span>
                </div>
                <div v-if="a.actor_name" class="text-slate-500">oleh {{ a.actor_name }}</div>
                <div v-if="a.from_status || a.to_status" class="text-slate-500">{{ voiceCaseStatusLabel(a.from_status) }} → {{ voiceCaseStatusLabel(a.to_status) }}</div>
                <div v-if="a.note" class="mt-1">{{ a.note }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="archiveModalOpen"
      class="fixed inset-0 z-[45] flex items-center justify-center bg-slate-900/50 px-3 py-6"
      @click.self="closeArchiveModal"
    >
      <div class="flex max-h-[min(92vh,960px)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
          <div>
            <h3 class="text-base font-bold text-slate-900">Arsip: Done &amp; ulasan positif</h3>
            <p class="mt-0.5 text-xs text-slate-500">
              Kasus dengan courtesy status <span class="font-semibold">Courtesy Done</span> (termasuk <span class="font-semibold">resolved</span> / <span class="font-semibold">ignored</span> di DB) atau severity <span class="font-semibold">positive</span>.
            </p>
          </div>
          <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
            @click="closeArchiveModal"
          >
            Tutup
          </button>
        </div>
        <div class="shrink-0 border-b border-slate-100 bg-slate-50/80 px-3 py-3 sm:px-5">
          <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
            <input
              v-model="archiveFilter.q"
              type="text"
              placeholder="Cari tamu / ringkasan / komentar"
              class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs outline-none focus:border-slate-400 focus:ring-2 focus:ring-slate-100 xl:col-span-2"
              @keyup.enter="applyArchiveFilters"
            />
            <select v-model="archiveFilter.status" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none focus:border-slate-400">
              <option value="">Semua Courtesy Status</option>
              <option value="new">New</option>
              <option value="internal_follow_up">Internal Follow Up</option>
              <option value="courtesy_done">Courtesy Done</option>
            </select>
            <select v-model="archiveFilter.follow_up_status" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none focus:border-slate-400">
              <option value="">Semua Follow Up Status</option>
              <option value="new">New</option>
              <option value="on_progress">On Progress</option>
              <option value="done">Done</option>
            </select>
            <select v-model="archiveFilter.severity" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none focus:border-slate-400">
              <option value="">Semua severity</option>
              <option value="critical">Critical</option>
              <option value="major">Major</option>
              <option value="minor">Minor</option>
              <option value="severe">Critical (arsip)</option>
              <option value="negative">Major (arsip)</option>
              <option value="mild_negative">Minor (arsip)</option>
              <option value="neutral">Neutral</option>
              <option value="positive">Positive</option>
            </select>
            <select v-model="archiveFilter.source_type" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none focus:border-slate-400">
              <option value="">Semua source</option>
              <option value="google_review">Google Review</option>
              <option value="instagram_comment">Instagram Comment</option>
              <option value="guest_comment">Guest Comment</option>
            </select>
            <select v-model="archiveFilter.id_outlet" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none focus:border-slate-400">
              <option value="">Semua outlet</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
            </select>
            <select v-model="archiveFilter.topic" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none focus:border-slate-400">
              <option v-for="opt in archiveTopicOptions" :key="opt.v || 'all'" :value="opt.v">{{ opt.label }}</option>
            </select>
            <select v-model="archiveFilter.assigned_to" class="h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none focus:border-slate-400">
              <option value="">Semua CS PIC</option>
              <option v-for="u in assignees" :key="u.id" :value="String(u.id)">{{ u.nama_lengkap }}</option>
            </select>
            <label class="flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-700">
              <input v-model="archiveFilter.overdue_only" type="checkbox" class="rounded border-slate-300" />
              Overdue (open)
            </label>
            <label class="flex flex-col gap-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
              Event dari
              <input v-model="archiveFilter.date_from" type="date" class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-800 outline-none focus:border-slate-400" />
            </label>
            <label class="flex flex-col gap-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
              Event sampai
              <input v-model="archiveFilter.date_to" type="date" class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-800 outline-none focus:border-slate-400" />
            </label>
          </div>
          <div class="mt-3 flex flex-wrap gap-2">
            <button
              type="button"
              class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-50"
              :disabled="archiveLoading"
              @click="applyArchiveFilters"
            >
              Terapkan filter
            </button>
            <button
              type="button"
              class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              :disabled="archiveLoading"
              @click="reloadArchiveFromMainFilters"
            >
              Samakan dengan halaman
            </button>
            <button
              type="button"
              class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              :disabled="archiveLoading"
              @click="clearArchiveFilters"
            >
              Reset filter arsip
            </button>
          </div>
        </div>
        <div class="min-h-0 flex-1 overflow-auto px-3 pb-3 sm:px-5">
          <div v-if="archiveLoading" class="py-12 text-center text-sm text-slate-500">Memuat…</div>
          <template v-else>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[880px] text-sm">
                <thead class="sticky top-0 bg-white text-xs uppercase tracking-wide text-slate-500">
                  <tr class="border-b border-slate-100">
                    <th class="px-2 py-2 text-left font-semibold">Waktu</th>
                    <th class="px-2 py-2 text-left font-semibold">Outlet</th>
                    <th class="px-2 py-2 text-left font-semibold">Severity</th>
                    <th class="px-2 py-2 text-left font-semibold">Jenis komplain</th>
                    <th class="px-2 py-2 text-left font-semibold">Courtesy Status</th>
                    <th class="px-2 py-2 text-left font-semibold">Follow Up Status</th>
                    <th class="px-2 py-2 text-left font-semibold">Ringkasan</th>
                    <th class="px-2 py-2 text-left font-semibold">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!archiveCases.length">
                    <td colspan="8" class="px-2 py-10 text-center text-slate-400">Tidak ada data di arsip untuk filter ini.</td>
                  </tr>
                  <tr v-for="row in archiveCases" :key="row.id" class="border-b border-slate-50 align-top">
                    <td class="px-2 py-2 whitespace-nowrap text-xs text-slate-600">{{ formatDate(row.event_at) }}</td>
                    <td class="px-2 py-2 text-slate-700">{{ row.nama_outlet || '-' }}</td>
                    <td class="px-2 py-2">
                      <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="severityClass(row.severity)">
                        {{ row.severity || '-' }}
                      </span>
                    </td>
                    <td class="px-2 py-2 max-w-[180px]">
                      <div v-if="complaintTypeLabelsFor(row).length" class="flex flex-wrap gap-1">
                        <span
                          v-for="(lbl, idx) in complaintTypeLabelsFor(row)"
                          :key="`arch-${row.id}-ct-${idx}`"
                          class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[10px] font-medium text-violet-900"
                        >
                          {{ lbl }}
                        </span>
                      </div>
                      <span v-else class="text-[11px] text-slate-400">—</span>
                    </td>
                    <td class="px-2 py-2">
                      <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(row.status)">
                        {{ voiceCaseStatusLabel(row.status) }}
                      </span>
                    </td>
                    <td class="px-2 py-2">
                      <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="followUpStatusClass(row.follow_up_status)">
                        {{ followUpStatusLabel(row.follow_up_status) }}
                      </span>
                    </td>
                    <td class="px-2 py-2">
                      <div class="line-clamp-2 max-w-xs text-xs text-slate-600">{{ row.summary_id || row.raw_text || '—' }}</div>
                    </td>
                    <td class="px-2 py-2 whitespace-nowrap">
                      <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100"
                        @click="openDetailFromArchive(row)"
                      >
                        Detail
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="archiveMeta && archiveMeta.last_page > 1" class="mt-3 flex flex-wrap items-center justify-center gap-2 border-t border-slate-100 pt-3">
              <button
                type="button"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold disabled:opacity-40"
                :disabled="archiveMeta.current_page <= 1"
                @click="fetchArchivePage(archiveMeta.current_page - 1)"
              >
                Sebelumnya
              </button>
              <span class="text-xs text-slate-600">
                Halaman {{ archiveMeta.current_page }} / {{ archiveMeta.last_page }}
                <span class="text-slate-400">({{ archiveMeta.total }} total)</span>
              </span>
              <button
                type="button"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold disabled:opacity-40"
                :disabled="archiveMeta.current_page >= archiveMeta.last_page"
                @click="fetchArchivePage(archiveMeta.current_page + 1)"
              >
                Berikutnya
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>

    <div v-if="showNoteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
      <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <h3 class="text-base font-bold text-slate-900">Tambah Catatan Aktivitas</h3>
        <p class="mt-1 text-xs text-slate-500">Catatan ini akan masuk ke timeline case.</p>
        <textarea
          v-model="noteText"
          rows="4"
          class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
          placeholder="Tulis catatan..."
        />
        <div v-if="page.props.errors?.note" class="mt-2 text-xs text-rose-600">
          {{ page.props.errors.note }}
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
            @click="closeNoteModal"
          >
            Batal
          </button>
          <button
            type="button"
            class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 disabled:opacity-60"
            :disabled="savingNote || !noteText.trim()"
            @click="submitNote"
          >
            {{ savingNote ? 'Menyimpan...' : 'Simpan Catatan' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import CapaFormPanel from '@/Pages/CustomerVoiceCommandCenter/CapaFormPanel.vue'
import CapaUserPicker from '@/Pages/CustomerVoiceCommandCenter/CapaUserPicker.vue'
import NotifyUserMultiPicker from '@/Pages/CustomerVoiceCommandCenter/NotifyUserMultiPicker.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const page = usePage()

const props = defineProps({
  summary: { type: Object, default: () => ({}) },
  kpis: { type: Object, default: () => ({}) },
  trend: { type: Array, default: () => [] },
  picPerformance: { type: Array, default: () => [] },
  outletPerformance: { type: Array, default: () => [] },
  perfWindowDays: { type: Number, default: 30 },
  cases: { type: Object, default: () => ({ data: [] }) },
  outlets: { type: Array, default: () => [] },
  assignees: { type: Array, default: () => [] },
  capa_auth_user: { type: Object, default: null },
  activities: { type: Object, default: () => ({}) },
  note_counts: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
})

const kpis = computed(() => props.kpis || {})
const trend = computed(() => props.trend || [])
const picPerformance = computed(() => props.picPerformance || [])
const outletPerformance = computed(() => props.outletPerformance || [])
const assigneeNameMap = computed(() => {
  const map = {}
  for (const u of props.assignees || []) {
    const id = Number(u?.id)
    if (Number.isFinite(id) && id > 0) {
      map[id] = String(u?.nama_lengkap || '')
    }
  }
  const me = props.capa_auth_user
  if (me?.id != null) {
    const id = Number(me.id)
    if (Number.isFinite(id) && id > 0 && !map[id]) {
      map[id] = String(me?.nama_lengkap || '')
    }
  }
  return map
})

const syncing = ref(false)
const updatingCaseId = ref(null)
const regionalSavingCaseId = ref(null)
const sharingCaseId = ref(null)
const openedActivityCaseId = ref(null)
const detailCaseId = ref(null)
const capaSaving = ref(false)
const capaDeleting = ref(false)
const capaResetKey = ref(0)
/** Deep link ?capa_verify=1 — scroll form ke bagian G sekali setelah panel terbuka */
const capaDetailFocusSection = ref(null)
const capaFormDirty = ref(false)
const caseForms = ref({})
const regionalPersistedByCase = ref({})
const q = ref(props.filters?.q || '')
const status = ref(props.filters?.status || '')
const followUpStatus = ref(props.filters?.follow_up_status || '')
const severity = ref(props.filters?.severity || '')
const sourceType = ref(props.filters?.source_type || '')
const idOutlet = ref(props.filters?.id_outlet ? String(props.filters.id_outlet) : '')
const overdueOnly = ref(Boolean(props.filters?.overdue_only))
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')
const showAll = ref(Boolean(props.filters?.show_all))

const archiveModalOpen = ref(false)
const archiveLoading = ref(false)
const archiveCases = ref([])
const archiveMeta = ref(null)

/** Filter khusus modal arsip (terpisah dari filter halaman utama). */
function emptyArchiveFilter() {
  return {
    q: '',
    status: '',
    follow_up_status: '',
    severity: '',
    source_type: '',
    id_outlet: '',
    date_from: '',
    date_to: '',
    overdue_only: false,
    assigned_to: '',
    topic: '',
  }
}

const archiveFilter = ref(emptyArchiveFilter())

const archiveTopicOptions = [
  { v: '', label: 'Semua jenis komplain' },
  { v: 'food_quality', label: 'Kualitas makanan' },
  { v: 'service', label: 'Layanan' },
  { v: 'hygiene', label: 'Higiene' },
  { v: 'cleanliness', label: 'Kebersihan' },
  { v: 'wait_time', label: 'Waktu tunggu' },
  { v: 'price', label: 'Harga' },
  { v: 'billing', label: 'Tagihan' },
  { v: 'ambiance', label: 'Suasana' },
  { v: 'portion', label: 'Porsi' },
  { v: 'beverage', label: 'Minuman' },
  { v: 'noise', label: 'Kebisingan' },
  { v: 'reservation', label: 'Reservasi' },
  { v: 'parking', label: 'Parkir' },
  { v: 'staff_attitude', label: 'Sikap staf' },
  { v: 'other', label: 'Lainnya' },
]

const topicEditOptions = [
  { v: 'food_quality', label: 'Kualitas makanan' },
  { v: 'service', label: 'Layanan' },
  { v: 'cleanliness', label: 'Kebersihan' },
  { v: 'ambiance', label: 'Suasana' },
  { v: 'wait_time', label: 'Waktu tunggu' },
  { v: 'price', label: 'Harga' },
  { v: 'billing', label: 'Tagihan' },
  { v: 'portion', label: 'Porsi' },
  { v: 'beverage', label: 'Minuman' },
  { v: 'noise', label: 'Kebisingan' },
  { v: 'reservation', label: 'Reservasi' },
  { v: 'parking', label: 'Parkir' },
  { v: 'staff_attitude', label: 'Sikap staf' },
  { v: 'other', label: 'Lainnya' },
]

const openTopicPickerCaseId = ref(null)

const detailOverrides = ref({})

const showNoteModal = ref(false)
const savingNote = ref(false)
const noteCaseId = ref(null)
const noteText = ref('')

function normalizeUserIdList(row, key) {
  const raw = row?.[key]
  if (!Array.isArray(raw)) {
    return []
  }
  const out = []
  for (const x of raw) {
    const n = typeof x === 'number' ? x : parseInt(String(x), 10)
    if (Number.isFinite(n) && n > 0) {
      out.push(n)
    }
  }
  return out
}

/** Samakan nilai DB lama ke opsi form (tanpa migrasi data). */
function canonicalVoiceCaseStatus(raw) {
  const s = String(raw || 'new').toLowerCase()
  if (s === 'courtesy_by_cs' || s === 'follow_up_by_ops' || s === 'in_progress') return 'internal_follow_up'
  if (s === 'done' || s === 'resolved' || s === 'ignored') return 'courtesy_done'
  return s
}

/** Label tampilan untuk slug DB (lama + baru). */
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

function normalizeEditSeverity(sev) {
  const s = String(sev || 'neutral').toLowerCase()
  const map = { severe: 'critical', negative: 'major', mild_negative: 'minor' }
  return map[s] || s
}

function normalizeTopicSlugs(topics) {
  if (!Array.isArray(topics)) return []
  const aliasMap = { hygiene: 'cleanliness', price_value: 'price', waiting_time: 'wait_time', speed_wait_time: 'wait_time' }
  const seen = new Set()
  const out = []
  for (const t of topics) {
    const k = aliasMap[String(t).toLowerCase()] || String(t).toLowerCase()
    if (k && !seen.has(k)) {
      seen.add(k)
      out.push(k)
    }
  }
  return out
}

function topicLabelFor(slug) {
  const found = topicEditOptions.find((o) => o.v === slug)
  return found ? found.label : slug
}

function toggleTopicPicker(caseId) {
  openTopicPickerCaseId.value = openTopicPickerCaseId.value === caseId ? null : caseId
}

function initCaseForms() {
  const next = {}
  const regionalNext = {}
  for (const row of props.cases?.data || []) {
    const regionalIds = normalizeUserIdList(row, 'regional_user_ids')
    next[row.id] = {
      status: canonicalVoiceCaseStatus(row.status),
      follow_up_status: row.follow_up_status || 'new',
      assigned_to: row.assigned_to != null ? Number(row.assigned_to) : null,
      regional_user_ids: regionalIds,
      follow_up_target: row.follow_up_target || '',
      severity: normalizeEditSeverity(row.severity),
      topics: normalizeTopicSlugs(row.topics),
    }
    regionalNext[row.id] = [...regionalIds]
  }
  caseForms.value = next
  regionalPersistedByCase.value = regionalNext
}

initCaseForms()

watch(
  () => props.cases?.data,
  () => initCaseForms()
)

watch(
  () => [props.filters?.date_from, props.filters?.date_to],
  () => {
    dateFrom.value = props.filters?.date_from || ''
    dateTo.value = props.filters?.date_to || ''
  },
)

watch(
  () => props.filters?.show_all,
  (v) => {
    showAll.value = Boolean(v)
  },
)

function voiceIndexPostExtras() {
  let pageNum
  try {
    pageNum = new URL(window.location.href).searchParams.get('page')
  } catch {
    pageNum = null
  }
  // `list_status` = filter dropdown halaman (bukan status case). Hindari bentrok dengan field `status` di POST update case.
  const payload = {
    q: q.value || undefined,
    list_status: status.value ?? '',
    list_follow_up_status: followUpStatus.value ?? '',
    severity: severity.value || undefined,
    source_type: sourceType.value || undefined,
    id_outlet: idOutlet.value || undefined,
    overdue_only: overdueOnly.value ? 1 : 0,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  }
  if (pageNum) {
    payload.page = pageNum
  }
  if (showAll.value) {
    payload.show_all = 1
  }
  return payload
}

const canExportPdf = computed(() => Boolean(dateFrom.value && dateTo.value))

function capaExportPdfUrl(id) {
  return route('customer-voice-command-center.cases.capa.export-pdf', id)
}

function capaExportExcelUrl(id) {
  return route('customer-voice-command-center.cases.capa.export-excel', id)
}

async function shareToWhatsApp(row) {
  const caseId = Number(row?.id)
  if (!Number.isFinite(caseId) || caseId <= 0) return
  if (sharingCaseId.value === caseId) return

  try {
    sharingCaseId.value = caseId
    const response = await axios.post(route('customer-voice-command-center.cases.share-link', caseId))
    const url = response.data?.url
    if (!url) {
      throw new Error('Link tidak tersedia')
    }

    const outlet = row?.nama_outlet ? ` - ${row.nama_outlet}` : ''
    const summary = row?.summary_id ? `: ${row.summary_id}` : ''
    const fallback = `Customer Voice Case #${caseId}${outlet}${summary}\n${url}`
    const message = response.data?.message || fallback
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank')
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || error.message || 'Gagal membuat link share', 'error')
  } finally {
    sharingCaseId.value = null
  }
}

const exportPdfHref = computed(() => {
  if (!dateFrom.value || !dateTo.value) {
    return '#'
  }
  const params = {
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }
  if (q.value) params.q = q.value
  if (status.value) params.status = status.value
  if (followUpStatus.value) params.follow_up_status = followUpStatus.value
  if (severity.value) params.severity = severity.value
  if (sourceType.value) params.source_type = sourceType.value
  if (idOutlet.value) params.id_outlet = idOutlet.value
  if (overdueOnly.value) params.overdue_only = 1
  if (showAll.value) params.show_all = 1
  return route('customer-voice-command-center.export-pdf', params)
})

function syncArchiveFiltersFromMain() {
  archiveFilter.value = {
    q: q.value || '',
    status: status.value || '',
    follow_up_status: followUpStatus.value || '',
    severity: severity.value || '',
    source_type: sourceType.value || '',
    id_outlet: idOutlet.value || '',
    date_from: dateFrom.value || '',
    date_to: dateTo.value || '',
    overdue_only: overdueOnly.value,
    assigned_to: '',
    topic: '',
  }
}

function clearArchiveFilters() {
  archiveFilter.value = emptyArchiveFilter()
  fetchArchivePage(1)
}

function applyArchiveFilters() {
  fetchArchivePage(1)
}

/** Salin filter dari toolbar halaman utama lalu muat ulang arsip. */
function reloadArchiveFromMainFilters() {
  syncArchiveFiltersFromMain()
  fetchArchivePage(1)
}

async function fetchArchivePage(page) {
  archiveLoading.value = true
  try {
    const af = archiveFilter.value
    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', '20')
    if (af.q) params.set('q', af.q)
    if (af.status) params.set('status', af.status)
    if (af.follow_up_status) params.set('follow_up_status', af.follow_up_status)
    if (af.severity) params.set('severity', af.severity)
    if (af.source_type) params.set('source_type', af.source_type)
    if (af.id_outlet) params.set('id_outlet', af.id_outlet)
    if (af.date_from) params.set('date_from', af.date_from)
    if (af.date_to) params.set('date_to', af.date_to)
    if (af.topic) params.set('topic', af.topic)
    if (af.assigned_to) params.set('assigned_to', af.assigned_to)
    if (af.overdue_only) params.set('overdue_only', '1')
    const url = `${route('customer-voice-command-center.archive-cases')}?${params.toString()}`
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
    const data = await res.json()
    if (data.success) {
      archiveCases.value = data.cases || []
      archiveMeta.value = data.meta || null
    } else {
      archiveCases.value = []
      archiveMeta.value = null
    }
  } catch {
    archiveCases.value = []
    archiveMeta.value = null
  } finally {
    archiveLoading.value = false
  }
}

function openArchiveModal() {
  syncArchiveFiltersFromMain()
  archiveModalOpen.value = true
  fetchArchivePage(1)
}

function closeArchiveModal() {
  archiveModalOpen.value = false
}

function syncNow() {
  syncing.value = true
  router.post('/customer-voice-command-center/sync', voiceIndexPostExtras(), {
    preserveScroll: true,
    onFinish: () => {
      syncing.value = false
    },
  })
}

function currentUserId() {
  const raw = page.props.auth?.user?.id
  const n = typeof raw === 'number' ? raw : parseInt(String(raw), 10)
  return Number.isFinite(n) && n > 0 ? n : null
}

/** HTML untuk SweetAlert: hanya regional — tidak mengirim ke akun yang sedang login. */
function notifyRecipientSkipMessageHtml(form) {
  const actorId = currentUserId()
  if (!actorId) {
    return null
  }
  const regionalIds = Array.isArray(form.regional_user_ids)
    ? [...new Set(form.regional_user_ids.map((x) => Number(x)).filter((n) => n > 0))]
    : []
  if (regionalIds.length > 0 && regionalIds.every((id) => id === actorId)) {
    return '<p class="text-left"><strong>Regional:</strong> tidak ada notifikasi masuk ke akun ini karena hanya berisi Anda sendiri. Pilih user lain untuk menguji notifikasi.</p>'
  }
  return null
}

function firstInertiaErrorMessage(errors) {
  if (!errors || typeof errors !== 'object') {
    return 'Terjadi kesalahan.'
  }
  const vals = Object.values(errors).flat().filter(Boolean)
  return vals.length ? String(vals[0]) : 'Terjadi kesalahan.'
}

async function onRegionalUsersChange(caseId, newIds) {
  const form = caseForms.value[caseId]
  if (!form) return

  const normalized = [...new Set((newIds || []).map((x) => Number(x)).filter((n) => n > 0))]
  form.regional_user_ids = normalized

  const prev = regionalPersistedByCase.value[caseId] || []
  const unchanged =
    normalized.length === prev.length &&
    normalized.every((id) => prev.includes(id))
  if (unchanged) {
    return
  }

  regionalSavingCaseId.value = caseId
  try {
    const { data } = await axios.post(route('customer-voice-command-center.cases.regional', caseId), {
      regional_user_ids: normalized,
    })
    regionalPersistedByCase.value[caseId] = [...normalized]
    const notified = Number(data?.notified_count) || 0
    if (notified > 0) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: `Notifikasi terkirim ke ${notified} regional`,
        showConfirmButton: false,
        timer: 2200,
        timerProgressBar: true,
      })
    }
  } catch (err) {
    form.regional_user_ids = [...prev]
    Swal.fire({
      icon: 'error',
      title: 'Gagal menyimpan regional',
      text: err?.response?.data?.message || err?.message || 'Terjadi kesalahan.',
    })
  } finally {
    regionalSavingCaseId.value = null
  }
}

function updateCase(caseId) {
  const form = caseForms.value[caseId]
  if (!form) return
  updatingCaseId.value = caseId
  Swal.fire({
    title: 'Menyimpan…',
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading()
    },
  })
  // voiceIndexPostExtras() menyertakan `list_status` (filter tabel); harus di-spread dulu
  // agar status/assigned_to case tidak tertimpa jadi "new" / filter lain.
  router.post(`/customer-voice-command-center/cases/${caseId}/update`, {
    ...voiceIndexPostExtras(),
    status: form.status,
    follow_up_status: form.follow_up_status || 'new',
    assigned_to: form.assigned_to ?? null,
    notify_follower_user_ids: [],
    regional_user_ids: Array.isArray(form.regional_user_ids) ? form.regional_user_ids : [],
    case_severity: form.severity || 'neutral',
    follow_up_target: form.follow_up_target || null,
    case_topics: Array.isArray(form.topics) ? form.topics : [],
  }, {
    preserveScroll: true,
    onSuccess: () => {
      Swal.close()
      const extraHtml = notifyRecipientSkipMessageHtml(form)
      if (extraHtml) {
        Swal.fire({
          icon: 'success',
          title: 'Case diperbarui',
          html: `${extraHtml}<p class="mt-3 text-sm text-slate-600">Perubahan tersimpan.</p>`,
        })
      } else {
        Swal.fire({
          icon: 'success',
          title: 'Case diperbarui',
          text: 'Perubahan berhasil disimpan.',
        })
      }
    },
    onError: (errors) => {
      Swal.close()
      Swal.fire({
        icon: 'error',
        title: 'Gagal menyimpan',
        text: firstInertiaErrorMessage(errors),
      })
    },
    onFinish: () => {
      updatingCaseId.value = null
    },
  })
}

function openNoteModal(caseId) {
  noteCaseId.value = caseId
  noteText.value = ''
  showNoteModal.value = true
}

function closeNoteModal() {
  showNoteModal.value = false
  noteCaseId.value = null
  noteText.value = ''
}

function submitNote() {
  if (!noteCaseId.value || !noteText.value.trim()) return
  savingNote.value = true
  router.post(`/customer-voice-command-center/cases/${noteCaseId.value}/note`, {
    note: noteText.value.trim(),
    ...voiceIndexPostExtras(),
  }, {
    preserveScroll: true,
    onSuccess: () => {
      closeNoteModal()
    },
    onFinish: () => {
      savingNote.value = false
    },
  })
}

function toggleActivities(caseId) {
  openedActivityCaseId.value = openedActivityCaseId.value === caseId ? null : caseId
}

function openDetail(caseId) {
  const next = { ...detailOverrides.value }
  delete next[caseId]
  detailOverrides.value = next
  detailCaseId.value = caseId
}

function openDetailFromArchive(row) {
  detailOverrides.value = { ...detailOverrides.value, [row.id]: row }
  detailCaseId.value = row.id
  archiveModalOpen.value = false
}

/** Deep link ?open_case= dari Home / kartu verifikasi */
let openCaseQueryHandled = false

function stripVoiceDeepLinkQueryParams() {
  try {
    const u = new URL(window.location.href)
    let changed = false
    if (u.searchParams.has('open_case')) {
      u.searchParams.delete('open_case')
      changed = true
    }
    if (u.searchParams.has('capa_verify') || u.searchParams.has('capa_approval')) {
      u.searchParams.delete('capa_verify')
      u.searchParams.delete('capa_approval')
      changed = true
    }
    if (changed) {
      window.history.replaceState({}, '', u.pathname + (u.search ? u.search : '') + u.hash)
    }
  } catch {
    /* noop */
  }
}

async function tryOpenCaseFromQuery() {
  if (openCaseQueryHandled) {
    return
  }
  let raw = null
  let wantCapaVerify = false
  try {
    const params = new URLSearchParams(window.location.search)
    raw = params.get('open_case')
    const v = params.get('capa_verify') || params.get('capa_approval')
    wantCapaVerify = v === '1' || v === 'true'
  } catch {
    return
  }
  if (!raw) {
    return
  }
  const id = parseInt(raw, 10)
  if (!Number.isFinite(id) || id <= 0) {
    return
  }

  openCaseQueryHandled = true

  if (caseMap.value[id]) {
    openDetail(id)
    if (wantCapaVerify) {
      capaDetailFocusSection.value = 'capa-approval'
    }
    stripVoiceDeepLinkQueryParams()
    return
  }

  try {
    const res = await fetch(route('customer-voice-command-center.cases.brief', id), {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
    const data = await res.json()
    if (data.success && data.case) {
      detailOverrides.value = { ...detailOverrides.value, [id]: data.case }
      detailCaseId.value = id
      if (wantCapaVerify) {
        capaDetailFocusSection.value = 'capa-approval'
      }
      stripVoiceDeepLinkQueryParams()
    } else {
      openCaseQueryHandled = false
    }
  } catch {
    openCaseQueryHandled = false
  }
}

onMounted(() => {
  tryOpenCaseFromQuery()
})

function closeDetail() {
  if (detailCaseId.value) {
    const next = { ...detailOverrides.value }
    delete next[detailCaseId.value]
    detailOverrides.value = next
  }
  detailCaseId.value = null
  capaDetailFocusSection.value = null
  capaFormDirty.value = false
}

async function reloadSelectedCase() {
  if (!detailCaseId.value) return
  const id = detailCaseId.value
  try {
    const res = await fetch(route('customer-voice-command-center.cases.brief', id), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    if (data.success && data.case) {
      detailOverrides.value = { ...detailOverrides.value, [id]: data.case }
    }
    router.reload({ only: ['cases'], preserveScroll: true })
  } catch {
    /* noop */
  }
}

function submitCapa(payload) {
  if (!detailCaseId.value) return
  const capa = payload && typeof payload === 'object' && payload.capa ? payload.capa : payload
  const capaDivision = payload && typeof payload === 'object' ? payload.division : null
  const approvers = payload && typeof payload === 'object' && Array.isArray(payload.approvers) ? payload.approvers : null
  capaSaving.value = true
  Swal.fire({
    title: 'Menyimpan CAPA…',
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading()
    },
  })
  const postData = {
    ...voiceIndexPostExtras(),
    capa_division: capaDivision || 'service',
    capa,
  }
  if (approvers && approvers.length) {
    postData.approvers = approvers
  }
  router.post(`/customer-voice-command-center/cases/${detailCaseId.value}/capa`, postData, {
    preserveScroll: true,
    onSuccess: () => {
      Swal.close()
      Swal.fire({
        icon: 'success',
        title: 'CAPA tersimpan',
        text: 'Form CAPA berhasil disimpan.',
      })
      router.reload({
        only: ['cases', 'summary', 'kpis', 'activities', 'note_counts'],
        preserveScroll: true,
      })
    },
    onError: (errors) => {
      Swal.close()
      Swal.fire({
        icon: 'error',
        title: 'Gagal menyimpan CAPA',
        text: firstInertiaErrorMessage(errors),
      })
    },
    onFinish: () => {
      capaSaving.value = false
    },
  })
}

function showVerifyInfo() {
  Swal.fire({
    icon: 'info',
    title: 'Mode verifikasi',
    text: 'Isi bagian G (hasil verifikasi), lalu klik Simpan form CAPA.',
  })
}

function onCapaExportClick(e, caseId) {
  if (detailCaseId.value === caseId && capaFormDirty.value) {
    e.preventDefault()
    Swal.fire({
      icon: 'warning',
      title: 'Simpan CAPA dulu',
      text: 'Data di detail belum tersimpan. Klik "Simpan form CAPA" dulu, lalu export PDF/XLS.',
    })
  }
}

function resetCapaDraft() {
  capaResetKey.value += 1
}

async function deleteStoredCapa() {
  if (!detailCaseId.value) return
  const res = await Swal.fire({
    icon: 'warning',
    title: 'Hapus data CAPA?',
    text: 'Lampiran file akan dihapus dari server. Status kasus dan komentar pelanggan tidak diubah.',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    reverseButtons: true,
  })
  if (!res.isConfirmed) return
  capaDeleting.value = true
  router.delete(route('customer-voice-command-center.cases.capa.destroy', detailCaseId.value), {
    data: voiceIndexPostExtras(),
    preserveScroll: true,
    onFinish: () => {
      capaDeleting.value = false
    },
    onSuccess: () => {
      capaResetKey.value += 1
    },
  })
}

function activitiesFor(caseId) {
  return Array.isArray(props.activities?.[caseId]) ? props.activities[caseId] : []
}

function noteCountFor(caseId) {
  const m = props.note_counts || {}
  const raw = m[caseId] ?? m[String(caseId)]
  const n = Number(raw)
  return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0
}

/** Jenis komplain dari backend (`complaint_type_labels`), turunan klasifikasi AI pada kolom topics. */
function complaintTypeLabelsFor(row) {
  const labels = row?.complaint_type_labels
  return Array.isArray(labels) && labels.length > 0 ? labels : []
}

function statusRowClasses(status) {
  const s = String(status || 'new').toLowerCase()
  if (s === 'courtesy_done' || s === 'done' || s === 'resolved' || s === 'ignored') {
    return {
      main: 'border-t border-emerald-200/90 bg-emerald-50/85 hover:bg-emerald-50',
      timeline: 'border-t border-emerald-200/70 bg-emerald-50/45',
    }
  }
  if (s === 'internal_follow_up' || s === 'follow_up_by_ops' || s === 'in_progress') {
    return {
      main: 'border-t border-indigo-200/90 bg-indigo-50/85 hover:bg-indigo-50',
      timeline: 'border-t border-indigo-200/70 bg-indigo-50/45',
    }
  }
  if (s === 'courtesy_by_cs') {
    return {
      main: 'border-t border-sky-200/90 bg-sky-50/85 hover:bg-sky-50',
      timeline: 'border-t border-sky-200/70 bg-sky-50/45',
    }
  }
  return {
    main: 'border-t border-amber-200/90 bg-amber-50/80 hover:bg-amber-50',
    timeline: 'border-t border-amber-200/70 bg-amber-50/40',
  }
}

function applyFilters() {
  router.get('/customer-voice-command-center', {
    q: q.value,
    status: status.value,
    follow_up_status: followUpStatus.value,
    severity: severity.value,
    source_type: sourceType.value,
    id_outlet: idOutlet.value,
    overdue_only: overdueOnly.value ? 1 : 0,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    show_all: showAll.value ? 1 : undefined,
  }, {
    preserveState: true,
    replace: true,
  })
}

const caseMap = computed(() => {
  const map = {}
  for (const row of props.cases?.data || []) {
    map[row.id] = row
  }
  return map
})

const selectedCase = computed(() => {
  if (!detailCaseId.value) return null
  const id = detailCaseId.value
  return detailOverrides.value[id] || caseMap.value[id] || null
})

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID')
}

function sourceLabel(source) {
  const s = String(source || '')
  if (s === 'google_review') return 'Google'
  if (s === 'instagram_comment') return 'Instagram'
  if (s === 'guest_comment') return 'Guest Comment'
  return s || '-'
}

function followUpLabel(v) {
  if (v === 'customer') return 'Customer'
  if (v === 'internal') return 'Internal'
  return ''
}

const capaDivisionDefs = [
  { id: 'service', label: 'Service', short: 'Svc' },
  { id: 'kitchen', label: 'Kitchen', short: 'Kit' },
  { id: 'bar', label: 'Bar', short: 'Bar' },
]

function impactLabel(arr) {
  if (!arr?.length) return ''
  const m = { reputasi: 'Reputasi', finansial: 'Finansial', operasional: 'Operasional' }
  return arr.map((k) => m[k] || k).join(', ')
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

function hasMeaningfulValue(value) {
  if (value === null || value === undefined) return false
  if (typeof value === 'string') return value.trim() !== ''
  if (typeof value === 'number') return Number.isFinite(value) && value !== 0
  if (typeof value === 'boolean') return value
  if (Array.isArray(value)) return value.some((v) => hasMeaningfulValue(v))
  if (typeof value === 'object') {
    return Object.values(value).some((v) => hasMeaningfulValue(v))
  }
  return false
}

function rowDivisionCapa(row, divisionId) {
  const divs = row?.capa_divisions
  if (divs && typeof divs === 'object' && divs[divisionId] && typeof divs[divisionId] === 'object') {
    return divs[divisionId]
  }
  const activeDiv = String(row?.capa_active_division || 'service').toLowerCase()
  if (activeDiv === divisionId && row?.capa && typeof row.capa === 'object') {
    return row.capa
  }
  return null
}

function divisionCapaFilled(row, divisionId) {
  const flags = row?.capa_division_filled
  if (flags && typeof flags === 'object' && Object.prototype.hasOwnProperty.call(flags, divisionId)) {
    return !!flags[divisionId]
  }
  const capa = rowDivisionCapa(row, divisionId)
  if (!capa || typeof capa !== 'object') return false
  if (Array.isArray(capa.evidence) && capa.evidence.length > 0) return true
  return hasMeaningfulValue(capa.b) || hasMeaningfulValue(capa.c) || hasMeaningfulValue(capa.e) || hasMeaningfulValue(capa.f)
}

function divisionVerificationDisplay(row, divisionId) {
  const perDiv = row?.capa_division_verification?.[divisionId]
  if (perDiv && typeof perDiv === 'object') {
    const v = perDiv
    if (!v.state || v.state === 'none') {
      return {
        iconClass: 'fa fa-minus',
        badgeClass: 'border-slate-200 bg-slate-50 text-slate-600',
        title: 'Belum diajukan approval',
      }
    }
    if (v.state === 'pending') {
      return {
        iconClass: 'fa fa-hourglass-half',
        badgeClass: 'border-amber-200 bg-amber-50 text-amber-900',
        title: 'Menunggu approval',
      }
    }
    if (v.result === 'approved') {
      return {
        iconClass: 'fa fa-check',
        badgeClass: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        title: 'Approval selesai — disetujui',
      }
    }
    if (v.result === 'rejected') {
      return {
        iconClass: 'fa fa-times',
        badgeClass: 'border-rose-200 bg-rose-50 text-rose-800',
        title: 'Approval ditolak',
      }
    }
    const sub = v.result === 'effective' ? 'Efektif' : 'Tidak efektif'
    return {
      iconClass: v.result === 'effective' ? 'fa fa-check' : 'fa fa-times',
      badgeClass: v.result === 'effective' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800',
      title: `Verifikasi legacy — ${sub}`,
    }
  }
  const capa = rowDivisionCapa(row, divisionId)
  const g = capa?.g && typeof capa.g === 'object' ? capa.g : {}
  const verifier = g.verified_by_user_id
  const result = String(g.result || '').toLowerCase()
  if (!verifier && !result) {
    return {
      iconClass: 'fa fa-minus',
      badgeClass: 'border-slate-200 bg-slate-50 text-slate-600',
      title: 'Belum ada verifikasi',
    }
  }
  if (result !== 'effective' && result !== 'not_effective') {
    return {
      iconClass: 'fa fa-hourglass-half',
      badgeClass: 'border-amber-200 bg-amber-50 text-amber-900',
      title: 'Verifikator sudah ditunjuk, hasil belum diisi',
    }
  }
  return {
    iconClass: result === 'effective' ? 'fa fa-check' : 'fa fa-times',
    badgeClass: result === 'effective'
      ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
      : 'border-rose-200 bg-rose-50 text-rose-800',
    title: result === 'effective' ? 'Verifikasi selesai: efektif' : 'Verifikasi selesai: tidak efektif',
  }
}

function formatShortDateTime(v) {
  if (!v) return ''
  try {
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
  } catch {
    return String(v)
  }
}

function userNameById(id) {
  const n = Number(id)
  if (!Number.isFinite(n) || n <= 0) return ''
  return assigneeNameMap.value[n] || `#${n}`
}

function capaAuditInfo(row) {
  const a = row?.capa_audit
  if (!a) return ''
  const by = userNameById(a.updated_by_user_id)
  const at = formatShortDateTime(a.updated_at)
  if (!by && !at) return ''
  return `${by || '-'} · ${at || '-'}`
}

function verificationAuditInfo(row) {
  const a = row?.capa_audit
  if (!a) return ''
  const by = userNameById(a.verified_by_user_id)
  const at = formatShortDateTime(a.verified_at)
  if (!by && !at) return ''
  return `${by || '-'} · ${at || '-'}`
}

function severityClass(sev) {
  const s = String(sev || '').toLowerCase()
  if (s === 'critical' || s === 'severe') return 'bg-rose-100 text-rose-700'
  if (s === 'major' || s === 'negative') return 'bg-orange-100 text-orange-700'
  if (s === 'minor' || s === 'mild_negative') return 'bg-amber-100 text-amber-700'
  if (s === 'positive') return 'bg-emerald-100 text-emerald-700'
  return 'bg-slate-100 text-slate-700'
}

function severitySelectClass(sev) {
  const s = String(sev || '').toLowerCase()
  if (s === 'critical') return 'border-rose-200 bg-rose-100 text-rose-700'
  if (s === 'major') return 'border-orange-200 bg-orange-100 text-orange-700'
  if (s === 'minor') return 'border-amber-200 bg-amber-100 text-amber-700'
  if (s === 'positive') return 'border-emerald-200 bg-emerald-100 text-emerald-700'
  return 'border-slate-200 bg-slate-100 text-slate-600'
}

function severityBorderColor(sev) {
  const s = String(sev || '').toLowerCase()
  if (s === 'critical') return '#fecdd3'
  if (s === 'major') return '#fed7aa'
  if (s === 'minor') return '#fde68a'
  if (s === 'positive') return '#a7f3d0'
  return '#e2e8f0'
}

function severityBgColor(sev) {
  const s = String(sev || '').toLowerCase()
  if (s === 'critical') return '#ffe4e6'
  if (s === 'major') return '#ffedd5'
  if (s === 'minor') return '#fef3c7'
  if (s === 'positive') return '#d1fae5'
  return '#f1f5f9'
}

function severityTextColor(sev) {
  const s = String(sev || '').toLowerCase()
  if (s === 'critical') return '#be123c'
  if (s === 'major') return '#c2410c'
  if (s === 'minor') return '#a16207'
  if (s === 'positive') return '#047857'
  return '#475569'
}

function isOpenStatus(statusValue) {
  const s = String(statusValue || '').toLowerCase()
  return s === 'new' || s === 'internal_follow_up' || s === 'courtesy_by_cs' || s === 'follow_up_by_ops' || s === 'in_progress'
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
  if (label === 'Overdue') return 'text-rose-700'
  if (label.includes('tersisa')) return 'text-amber-700'
  if (label === 'Closed') return 'text-emerald-700'
  return 'text-slate-500'
}

function formatMinutes(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '-'
  const total = Math.max(0, Math.round(Number(value)))
  if (total < 60) return `${total} menit`
  const hours = Math.floor(total / 60)
  const mins = total % 60
  if (hours < 24) return `${hours}j ${mins}m`
  const days = Math.floor(hours / 24)
  const remHours = hours % 24
  return `${days}h ${remHours}j`
}

function formatPercent(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '-'
  return `${Number(value).toFixed(2)}%`
}

function trendNegativePct(row) {
  const total = Number(row?.total_cases || 0)
  const negative = Number(row?.negative_cases || 0)
  if (total <= 0) return 0
  return Math.max(0, Math.min(100, Math.round((negative / total) * 100)))
}
</script>
