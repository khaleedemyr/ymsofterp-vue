<template>
  <AppLayout>
    <div class="w-full max-w-none px-3 py-5 sm:px-4 lg:px-6">
      <div class="rounded-2xl border border-slate-200 bg-white/90 shadow-sm backdrop-blur">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
          <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Customer Voice Command Center</h1>
            <p class="mt-1 text-sm text-slate-500">Monitoring sentimen customer lintas channel dengan aksi follow-up cepat.</p>
            <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
              <span class="rounded-full bg-rose-100 px-2 py-1 font-semibold text-rose-700">SLA severe: 30m</span>
              <span class="rounded-full bg-orange-100 px-2 py-1 font-semibold text-orange-700">SLA negative: 2j</span>
              <span class="rounded-full bg-amber-100 px-2 py-1 font-semibold text-amber-700">SLA mild_negative: 24j</span>
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
          <p class="text-xs font-semibold uppercase tracking-wide text-rose-400">Severe Open</p>
          <p class="mt-2 text-2xl font-bold text-rose-600">{{ summary.severe_open || 0 }}</p>
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
        <div class="grid gap-3 md:grid-cols-7">
          <input
            v-model="q"
            type="text"
            placeholder="Cari author / ringkasan / komentar / outlet"
            class="md:col-span-2 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
            @keyup.enter="applyFilters"
          />
          <select v-model="status" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100" @change="applyFilters">
            <option value="">Semua status</option>
            <option value="new">New</option>
            <option value="in_progress">In Progress</option>
            <option value="resolved">Resolved</option>
            <option value="ignored">Ignored</option>
          </select>
          <select v-model="severity" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100" @change="applyFilters">
            <option value="">Semua severity</option>
            <option value="severe">Severe</option>
            <option value="negative">Negative</option>
            <option value="mild_negative">Mild Negative</option>
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
      </div>

      <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1180px] text-sm">
            <thead class="bg-slate-50">
              <tr class="text-xs uppercase tracking-wide text-slate-500">
                <th class="px-3 py-3 text-left font-semibold">Waktu</th>
                <th class="px-3 py-3 text-left font-semibold">Outlet</th>
                <th class="px-3 py-3 text-left font-semibold">Source</th>
                <th class="px-3 py-3 text-left font-semibold">Severity</th>
                <th class="px-3 py-3 text-left font-semibold">Ringkasan</th>
                <th class="px-3 py-3 text-left font-semibold">Risk</th>
                <th class="px-3 py-3 text-left font-semibold">SLA</th>
                <th class="px-3 py-3 text-left font-semibold">PIC</th>
                <th class="px-3 py-3 text-left font-semibold">Status</th>
                <th class="px-3 py-3 text-left font-semibold">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!cases.data?.length">
                <td colspan="10" class="px-4 py-14 text-center text-slate-400">Belum ada case.</td>
              </tr>
              <template v-for="row in cases.data" :key="row.id">
                <tr class="border-t border-slate-100 align-top hover:bg-slate-50/70">
                  <td class="px-3 py-3 whitespace-nowrap text-xs text-slate-600">{{ formatDate(row.event_at) }}</td>
                  <td class="px-3 py-3 text-slate-700">{{ row.nama_outlet || '-' }}</td>
                  <td class="px-3 py-3">
                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600">
                      {{ sourceLabel(row.source_type) }}
                    </span>
                  </td>
                  <td class="px-3 py-3">
                    <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold" :class="severityClass(row.severity)">
                      {{ row.severity || 'neutral' }}
                    </span>
                  </td>
                  <td class="px-3 py-3">
                    <div class="font-semibold text-slate-800">{{ row.summary_id || '-' }}</div>
                    <div class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">{{ row.raw_text || '' }}</div>
                  </td>
                  <td class="px-3 py-3 text-sm font-semibold text-slate-800">{{ row.risk_score ?? 0 }}</td>
                  <td class="px-3 py-3 min-w-[190px]">
                    <div class="text-xs font-semibold" :class="slaClass(row)">{{ slaLabel(row) }}</div>
                    <div v-if="row.due_at" class="mt-1 text-[11px] text-slate-400">due {{ formatDate(row.due_at) }}</div>
                  </td>
                  <td class="px-3 py-3 min-w-[190px]">
                    <select
                      v-model="caseForms[row.id].assigned_to"
                      class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                    >
                      <option value="">Unassigned</option>
                      <option v-for="u in assignees" :key="u.id" :value="String(u.id)">
                        {{ u.nama_lengkap }}
                      </option>
                    </select>
                  </td>
                  <td class="px-3 py-3 min-w-[150px]">
                    <select
                      v-model="caseForms[row.id].status"
                      class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100"
                    >
                      <option value="new">new</option>
                      <option value="in_progress">in_progress</option>
                      <option value="resolved">resolved</option>
                      <option value="ignored">ignored</option>
                    </select>
                  </td>
                  <td class="px-3 py-3 min-w-[260px]">
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
                        Simpan
                      </button>
                      <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-100"
                        @click="openNoteModal(row.id)"
                      >
                        Catatan
                      </button>
                      <button
                        type="button"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] font-semibold text-amber-700 transition hover:bg-amber-100"
                        @click="toggleActivities(row.id)"
                      >
                        Timeline
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="openedActivityCaseId === row.id" class="border-t border-slate-100 bg-slate-50">
                  <td class="px-3 py-3" colspan="10">
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
                        <div v-if="a.from_status || a.to_status" class="text-slate-500">{{ a.from_status || '-' }} -> {{ a.to_status || '-' }}</div>
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
      <div class="h-full w-full max-w-2xl overflow-y-auto border-l border-slate-200 bg-white shadow-2xl">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4">
          <div>
            <h3 class="text-base font-bold text-slate-900">Case Detail #{{ selectedCase.id }}</h3>
            <p class="text-xs text-slate-500">{{ sourceLabel(selectedCase.source_type) }} · {{ formatDate(selectedCase.event_at) }}</p>
          </div>
          <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
            @click="closeDetail"
          >
            Tutup
          </button>
        </div>

        <div class="space-y-5 px-5 py-4">
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
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Author</div>
              <div class="mt-1 text-sm font-semibold text-slate-800">{{ selectedCase.author_name || '-' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Kontak</div>
              <div class="mt-1 text-sm font-semibold text-slate-800">{{ selectedCase.customer_contact || '-' }}</div>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-4">
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
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Status</div>
              <span class="mt-1 inline-flex rounded-full px-2 py-1 text-[11px] font-semibold" :class="statusClass(selectedCase.status)">
                {{ selectedCase.status || 'new' }}
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
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Komentar Asli</div>
            <div class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ selectedCase.raw_text || '-' }}</div>
          </div>

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
                <div v-if="a.from_status || a.to_status" class="text-slate-500">{{ a.from_status || '-' }} -> {{ a.to_status || '-' }}</div>
                <div v-if="a.note" class="mt-1">{{ a.note }}</div>
              </div>
            </div>
          </div>
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
import { Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

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
  activities: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
})

const kpis = computed(() => props.kpis || {})
const trend = computed(() => props.trend || [])
const picPerformance = computed(() => props.picPerformance || [])
const outletPerformance = computed(() => props.outletPerformance || [])

const syncing = ref(false)
const updatingCaseId = ref(null)
const openedActivityCaseId = ref(null)
const detailCaseId = ref(null)
const caseForms = ref({})
const q = ref(props.filters?.q || '')
const status = ref(props.filters?.status || '')
const severity = ref(props.filters?.severity || '')
const sourceType = ref(props.filters?.source_type || '')
const idOutlet = ref(props.filters?.id_outlet ? String(props.filters.id_outlet) : '')
const overdueOnly = ref(Boolean(props.filters?.overdue_only))

const showNoteModal = ref(false)
const savingNote = ref(false)
const noteCaseId = ref(null)
const noteText = ref('')

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
  }, {
    preserveScroll: true,
    onFinish: () => {
      savingNote.value = false
      closeNoteModal()
    },
  })
}

function toggleActivities(caseId) {
  openedActivityCaseId.value = openedActivityCaseId.value === caseId ? null : caseId
}

function openDetail(caseId) {
  detailCaseId.value = caseId
}

function closeDetail() {
  detailCaseId.value = null
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

const caseMap = computed(() => {
  const map = {}
  for (const row of props.cases?.data || []) {
    map[row.id] = row
  }
  return map
})

const selectedCase = computed(() => {
  if (!detailCaseId.value) return null
  return caseMap.value[detailCaseId.value] || null
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

function statusClass(statusValue) {
  const s = String(statusValue || '').toLowerCase()
  if (s === 'resolved') return 'bg-emerald-100 text-emerald-700'
  if (s === 'in_progress') return 'bg-indigo-100 text-indigo-700'
  if (s === 'ignored') return 'bg-slate-200 text-slate-700'
  return 'bg-amber-100 text-amber-700'
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
