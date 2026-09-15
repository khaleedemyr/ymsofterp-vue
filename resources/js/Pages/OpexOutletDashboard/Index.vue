<template>
  <AppLayout>
    <Head title="Outlet Spend Dashboard" />

    <div class="w-full min-h-screen bg-gradient-to-b from-slate-50 via-white to-sky-50/40 px-4 sm:px-6 lg:px-8 py-6">
      <!-- Header -->
      <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4 mb-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600 mb-1">Outlet Operations</p>
          <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Revenue & Spend Dashboard</h1>
          <p class="text-slate-500 mt-1 text-sm">
            Ringkasan revenue, GSR/RO, RWS, Retail Food & Non Food
            <span v-if="dashboardData.outlet_name"> · {{ dashboardData.outlet_name }}</span>
          </p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 w-full xl:w-auto xl:min-w-[640px]">
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Dari</label>
            <input v-model="filters.date_from" type="date" class="w-full rounded-xl border-slate-200 text-sm focus:ring-sky-500 focus:border-sky-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Sampai</label>
            <input v-model="filters.date_to" type="date" class="w-full rounded-xl border-slate-200 text-sm focus:ring-sky-500 focus:border-sky-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Outlet</label>
            <select
              v-model="filters.outlet_id"
              :disabled="!canSelectOutlet"
              class="w-full rounded-xl border-slate-200 text-sm focus:ring-sky-500 focus:border-sky-500 disabled:bg-slate-100"
            >
              <option v-if="canSelectOutlet" :value="null">Pilih outlet</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
          </div>
          <div class="flex items-end">
            <button
              type="button"
              class="w-full rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold py-2.5 shadow-sm"
              @click="applyFilters"
            >
              Tampilkan
            </button>
          </div>
        </div>
      </div>

      <div v-if="!filters.outlet_id" class="rounded-3xl border border-dashed border-slate-300 bg-white/70 py-24 text-center text-slate-400">
        <i class="fa-solid fa-store text-4xl mb-3"></i>
        <p class="font-medium">Pilih outlet untuk melihat dashboard</p>
      </div>

      <template v-else>
        <div v-if="bootstrapping" class="rounded-3xl bg-white border border-slate-100 shadow-sm px-5 py-4 mb-6 flex items-center gap-3 text-sm text-slate-500">
          <i class="fa-solid fa-spinner fa-spin text-sky-500"></i>
          Memuat data dashboard secara bertahap…
        </div>

        <!-- RO Forecast summary -->
        <div class="rounded-3xl bg-white border border-teal-100 shadow-sm p-5 sm:p-6 mb-6">
          <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">RO Forecast</p>
              <h2 class="text-xl font-bold text-slate-900 mt-0.5">Budget vs Purchase</h2>
              <p class="text-xs text-slate-500 mt-1">
                Full month {{ roForecast?.period_from || '—' }} s/d {{ roForecast?.period_to || '—' }}
                · F&amp;B 40% · Service 5% (bukan MTD)
              </p>
            </div>
            <a
              :href="roForecastHref"
              class="text-sm font-medium text-teal-700 hover:text-teal-900 underline underline-offset-2"
            >
              Buka laporan lengkap
            </a>
          </div>

          <div v-if="sectionLoading.ro_forecast" class="py-10 text-center text-slate-400 text-sm">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat RO Forecast…
          </div>
          <div v-else-if="sectionError.ro_forecast" class="rounded-2xl bg-rose-50 border border-rose-100 px-4 py-3 text-sm text-rose-700">
            Gagal memuat RO Forecast.
          </div>
          <div v-else-if="!roForecast?.has_forecast && !(roForecast?.forecast > 0)" class="rounded-2xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-800">
            Belum ada Revenue Target / Forecast untuk periode ini.
          </div>

          <div v-else class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <div class="xl:col-span-3 rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Forecast</p>
              <p class="mt-2 text-2xl font-bold text-slate-900">{{ formatCurrency(roForecast.forecast) }}</p>
              <p class="mt-1 text-xs text-slate-500">Total forecast revenue 1 bulan penuh</p>
            </div>

            <div class="xl:col-span-4 rounded-2xl border border-teal-100 bg-teal-50/40 p-4">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">F &amp; B Purchase</p>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-800">Budget {{ roForecast.fb?.budget_ratio_pct || 40 }}%</span>
              </div>
              <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                  <p class="text-slate-500 text-xs">Budget</p>
                  <p class="font-semibold text-slate-900">{{ formatCurrency(roForecast.fb?.budget) }}</p>
                </div>
                <div>
                  <p class="text-slate-500 text-xs">Purchased</p>
                  <p class="font-semibold text-slate-900">{{ formatCurrency(roForecast.fb?.purchased) }}</p>
                </div>
              </div>
              <div class="mt-3 pt-3 border-t border-teal-100/80 flex items-end justify-between gap-2">
                <div>
                  <p class="text-xs text-slate-500">Sisa budget</p>
                  <p class="text-xl font-bold" :class="remainingClass(roForecast.fb?.remaining)">
                    {{ formatRemaining(roForecast.fb?.remaining) }}
                  </p>
                </div>
                <p class="text-sm font-semibold text-slate-600">
                  {{ roForecast.fb?.pct != null ? roForecast.fb.pct + '% terpakai' : '—' }}
                </p>
              </div>
              <div class="mt-3 h-2 rounded-full bg-white overflow-hidden border border-teal-100">
                <div
                  class="h-full rounded-full transition-all"
                  :class="(roForecast.fb?.pct || 0) > 100 ? 'bg-rose-500' : 'bg-teal-500'"
                  :style="{ width: Math.min(100, roForecast.fb?.pct || 0) + '%' }"
                ></div>
              </div>
            </div>

            <div class="xl:col-span-5 rounded-2xl border border-cyan-100 bg-cyan-50/40 p-4">
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Service Purchase</p>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-cyan-100 text-cyan-800">Budget {{ roForecast.service?.budget_ratio_pct || 5 }}%</span>
              </div>
              <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                  <p class="text-slate-500 text-xs">Budget</p>
                  <p class="font-semibold text-slate-900">{{ formatCurrency(roForecast.service?.budget) }}</p>
                </div>
                <div>
                  <p class="text-slate-500 text-xs">Purchased</p>
                  <p class="font-semibold text-slate-900">{{ formatCurrency(roForecast.service?.purchased) }}</p>
                </div>
              </div>
              <div class="mt-3 pt-3 border-t border-cyan-100/80 flex items-end justify-between gap-2">
                <div>
                  <p class="text-xs text-slate-500">Sisa budget</p>
                  <p class="text-xl font-bold" :class="remainingClass(roForecast.service?.remaining)">
                    {{ formatRemaining(roForecast.service?.remaining) }}
                  </p>
                </div>
                <p class="text-sm font-semibold text-slate-600">
                  {{ roForecast.service?.pct != null ? roForecast.service.pct + '% terpakai' : '—' }}
                </p>
              </div>
              <div class="mt-3 h-2 rounded-full bg-white overflow-hidden border border-cyan-100">
                <div
                  class="h-full rounded-full transition-all"
                  :class="(roForecast.service?.pct || 0) > 100 ? 'bg-rose-500' : 'bg-cyan-500'"
                  :style="{ width: Math.min(100, roForecast.service?.pct || 0) + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Hero metrics -->
        <div v-if="sectionLoading.overview" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-16 mb-6 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat KPI…
        </div>
        <template v-else>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-6">
          <button
            type="button"
            class="lg:col-span-4 rounded-3xl bg-white border border-sky-100 shadow-sm p-6 text-left hover:shadow-md transition"
            @click="openCard('revenue')"
          >
            <div class="flex items-start justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Revenue</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.revenue) }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ ov.revenue_count || 0 }} orders</p>
                <template v-if="ov.revenue_monthly_budget != null">
                  <p class="mt-2 text-xs text-slate-500">
                    Budget {{ formatCurrency(ov.revenue_monthly_budget) }}
                  </p>
                  <div class="mt-1.5 h-1.5 rounded-full bg-slate-100 overflow-hidden max-w-[220px]">
                    <div
                      class="h-full rounded-full transition-all"
                      :class="(ov.revenue_budget_perf_percent || 0) >= 100 ? 'bg-emerald-500' : 'bg-sky-500'"
                      :style="{ width: Math.min(100, ov.revenue_budget_perf_percent || 0) + '%' }"
                    ></div>
                  </div>
                  <p
                    class="mt-1 text-xs font-semibold"
                    :class="(ov.revenue_budget_perf_percent || 0) >= 100 ? 'text-emerald-600' : 'text-sky-700'"
                  >
                    Performa {{ ov.revenue_budget_perf_percent }}%
                    <span v-if="ov.revenue_budget_variance != null" class="font-medium text-slate-500">
                      · {{ ov.revenue_budget_variance >= 0 ? '+' : '' }}{{ formatCurrency(ov.revenue_budget_variance) }}
                    </span>
                  </p>
                </template>
                <p v-else class="mt-2 text-xs text-slate-400">Belum ada revenue target</p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.revenue)">{{ vsLabel(vs.revenue) }}</p>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center">
                <i class="fa-solid fa-chart-line text-xl"></i>
              </div>
            </div>
          </button>

          <button
            type="button"
            class="lg:col-span-4 rounded-3xl bg-white border border-rose-100 shadow-sm p-6 text-left hover:shadow-md transition"
            @click="openCard('total_spend')"
          >
            <div class="flex items-start justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Total Spend</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.total_spend) }}</p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ ov.spend_ratio_percent != null ? ov.spend_ratio_percent + '% dari revenue' : '—' }}
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.total_spend, true)">{{ vsLabel(vs.total_spend) }}</p>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
                <i class="fa-solid fa-cart-shopping text-xl"></i>
              </div>
            </div>
          </button>

          <div class="lg:col-span-4 rounded-3xl bg-white border border-emerald-100 shadow-sm p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Net (Rev − Spend)</p>
            <p class="mt-2 text-3xl font-bold" :class="(ov.net || 0) >= 0 ? 'text-emerald-700' : 'text-rose-600'">
              {{ formatCurrency(ov.net) }}
            </p>
            <div class="mt-4 h-2 rounded-full bg-slate-100 overflow-hidden">
              <div
                class="h-full rounded-full bg-gradient-to-r from-sky-500 to-emerald-400 transition-all"
                :style="{ width: Math.min(100, ov.spend_ratio_percent || 0) + '%' }"
              ></div>
            </div>
            <p class="mt-2 text-xs font-medium" :class="vsClass(vs.net)">{{ vsLabel(vs.net) }}</p>
          </div>
        </div>

        <!-- Cover / Pax / Discount / Compliment / GS / OC -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
          <div class="rounded-3xl bg-white border border-indigo-100 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Cover / Pax</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(ov.cover) }}</p>
            <p class="mt-1 text-xs text-slate-500">Total tamu periode filter</p>
            <p class="mt-1 text-xs font-medium" :class="vsClass(vs.cover)">{{ vsLabel(vs.cover, 'number') }}</p>
          </div>
          <div class="rounded-3xl bg-white border border-violet-100 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">Average Pax</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">
              {{ ov.avg_pax != null ? formatDecimal(ov.avg_pax) : '—' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">Rata-rata pax per bill</p>
            <p class="mt-1 text-xs font-medium" :class="vsClass(vs.avg_pax)">{{ vsLabel(vs.avg_pax, 'decimal') }}</p>
          </div>
          <div class="rounded-3xl bg-white border border-fuchsia-100 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600">Avg Check</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">
              {{ ov.avg_check != null ? formatCurrency(ov.avg_check) : '—' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">Revenue ÷ cover</p>
            <p class="mt-1 text-xs font-medium" :class="vsClass(vs.avg_check)">{{ vsLabel(vs.avg_check) }}</p>
          </div>
          <button
            type="button"
            class="rounded-3xl bg-white border border-amber-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('discount')"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Diskon</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.discount) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ ov.discount_count || 0 }} bill
                  <span v-if="ov.discount_ratio_percent != null"> · {{ ov.discount_ratio_percent }}% sales</span>
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.discount, true)">{{ vsLabel(vs.discount) }}</p>
              </div>
              <span class="text-amber-400 text-xs mt-1">Detail →</span>
            </div>
          </button>
          <button
            type="button"
            class="rounded-3xl bg-white border border-fuchsia-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('discount_compliment')"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600">Compliment</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.discount_compliment) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  Bill {{ formatCurrency(ov.discount_compliment_bill) }}
                  · {{ ov.discount_compliment_count || 0 }} trx
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.discount_compliment, true)">{{ vsLabel(vs.discount_compliment) }}</p>
              </div>
              <span class="text-fuchsia-400 text-xs mt-1">Detail →</span>
            </div>
          </button>
          <button
            type="button"
            class="rounded-3xl bg-white border border-orange-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('discount_guest_satisfaction')"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-orange-600">Guest Satisfaction</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.discount_guest_satisfaction) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  Bill {{ formatCurrency(ov.discount_guest_satisfaction_bill) }}
                  · {{ ov.discount_guest_satisfaction_count || 0 }} trx
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.discount_guest_satisfaction, true)">{{ vsLabel(vs.discount_guest_satisfaction) }}</p>
              </div>
              <span class="text-orange-400 text-xs mt-1">Detail →</span>
            </div>
          </button>
          <button
            type="button"
            class="rounded-3xl bg-white border border-indigo-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('officer_check')"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Officer Check</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.officer_check) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ ov.officer_check_count || 0 }} pembayaran OFFICER_CHECK
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.officer_check, true)">{{ vsLabel(vs.officer_check) }}</p>
              </div>
              <span class="text-indigo-400 text-xs mt-1">Detail →</span>
            </div>
          </button>
        </div>
        </template>

        <!-- Member Top Up / Redeem -->
        <div v-if="sectionLoading.member" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-10 mb-6 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat data member…
        </div>
        <div v-else-if="!sectionLoading.overview" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
          <div class="rounded-3xl bg-white border border-sky-100 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Member Bills</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(ov.member_bills) }}</p>
            <p class="mt-1 text-xs text-slate-500">
              Revenue member {{ formatCurrency(ov.member_revenue) }}
            </p>
            <p class="mt-1 text-xs font-medium" :class="vsClass(vsMember.member_bills)">{{ vsLabel(vsMember.member_bills, 'number') }}</p>
          </div>
          <button
            type="button"
            class="rounded-3xl bg-white border border-teal-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('member_top_up')"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">Point Earn</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">
                  {{ formatNumber(ov.member_top_up_points) }}
                  <span class="text-base font-semibold text-slate-500">pts</span>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ ov.member_top_up_count || 0 }} trx · dari bill {{ formatCurrency(ov.member_top_up) }}
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vsMember.member_top_up_points)">{{ vsLabel(vsMember.member_top_up_points, 'points') }}</p>
              </div>
              <span class="text-teal-400 text-xs mt-1">Detail →</span>
            </div>
          </button>
          <button
            type="button"
            class="rounded-3xl bg-white border border-rose-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('member_redeem')"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Point Redeem</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.member_redeem) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ ov.member_redeem_count || 0 }} trx
                  <span v-if="ov.member_redeem_points"> · {{ formatNumber(ov.member_redeem_points) }} pts</span>
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vsMember.member_redeem)">{{ vsLabel(vsMember.member_redeem) }}</p>
              </div>
              <span class="text-rose-400 text-xs mt-1">Detail →</span>
            </div>
          </button>
        </div>

        <!-- Source cards -->
        <div v-if="!sectionLoading.overview" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
          <button
            v-for="card in sourceCards"
            :key="card.key"
            type="button"
            class="rounded-3xl bg-white border shadow-sm p-5 text-left hover:shadow-md transition group"
            :class="card.border"
            @click="openCard(card.key)"
          >
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold uppercase tracking-wide" :class="card.tone">{{ card.label }}</span>
              <span class="w-10 h-10 rounded-xl flex items-center justify-center" :class="card.iconBg">
                <i :class="[card.icon, card.tone]"></i>
              </span>
            </div>
            <p class="text-2xl font-bold text-slate-900">{{ formatCurrency(card.amount) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ card.hint }}</p>
            <p v-if="card.revenuePct != null" class="text-xs font-semibold text-slate-600 mt-1">
              {{ card.revenuePct }}% dari revenue
            </p>
            <p v-if="card.paymentHint" class="text-xs text-slate-500 mt-1">{{ card.paymentHint }}</p>
            <p class="mt-1 text-xs font-medium" :class="vsClass(card.vs, true)">{{ vsLabel(card.vs) }}</p>
            <div class="mt-3 h-1.5 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full" :class="card.bar" :style="{ width: spendShare(card.amount) + '%' }"></div>
            </div>
          </button>
        </div>

        <!-- Charts -->
        <div v-if="sectionLoading.charts" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-16 mb-6 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat chart…
        </div>
        <template v-else>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
          <div class="xl:col-span-2 rounded-3xl bg-white border border-slate-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h2 class="text-lg font-bold text-slate-900">Revenue vs Spend Trend</h2>
                <p class="text-xs text-slate-500">Harian sepanjang periode filter</p>
              </div>
            </div>
            <apexchart type="area" height="360" :options="trendOptions" :series="trendSeries" />
          </div>

          <div class="rounded-3xl bg-white border border-slate-100 shadow-sm p-5">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Spend Mix</h2>
            <p class="text-xs text-slate-500 mb-4">Komposisi pembelanjaan</p>
            <apexchart type="donut" height="320" :options="mixOptions" :series="mixSeries" />
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
          <div class="rounded-3xl bg-white border border-slate-100 shadow-sm p-5">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Spend by Source (Daily)</h2>
            <p class="text-xs text-slate-500 mb-4">GSR/RO · RWS · Retail Food · Retail Non Food</p>
            <apexchart type="bar" height="340" :options="stackOptions" :series="stackSeries" />
          </div>

          <div class="rounded-3xl bg-white border border-slate-100 shadow-sm p-5">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Daily Snapshot</h2>
            <p class="text-xs text-slate-500 mb-4">Revenue & total spend per hari</p>
            <div class="overflow-auto max-h-[340px] rounded-2xl border border-slate-100">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 sticky top-0">
                  <tr class="text-left text-slate-500">
                    <th class="px-4 py-3 font-semibold">Tanggal</th>
                    <th class="px-4 py-3 font-semibold text-right">Revenue</th>
                    <th class="px-4 py-3 font-semibold text-right">Spend</th>
                    <th class="px-4 py-3 font-semibold text-right">Net</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in trendRows"
                    :key="row.date"
                    class="border-t border-slate-100 hover:bg-sky-50/40"
                  >
                    <td class="px-4 py-2.5 text-slate-700">{{ formatShortDate(row.date) }}</td>
                    <td class="px-4 py-2.5 text-right font-medium text-sky-700">{{ formatCurrency(row.revenue) }}</td>
                    <td class="px-4 py-2.5 text-right font-medium text-rose-600">{{ formatCurrency(row.total_spend) }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold" :class="(row.revenue - row.total_spend) >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                      {{ formatCurrency(row.revenue - row.total_spend) }}
                    </td>
                  </tr>
                  <tr v-if="!trendRows.length">
                    <td colspan="4" class="px-4 py-10 text-center text-slate-400">Tidak ada data</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        </template>

        <!-- Payment methods -->
        <div v-if="sectionLoading.payments" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-12 mb-6 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat metode pembayaran…
        </div>
        <div v-else class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
          <div class="xl:col-span-1 rounded-3xl bg-white border border-slate-100 shadow-sm p-5">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Metode Pembayaran</h2>
            <p class="text-xs text-slate-500 mb-4">Share amount per payment code</p>
            <apexchart
              v-if="paymentMixSeries.length"
              type="donut"
              height="300"
              :options="paymentMixOptions"
              :series="paymentMixSeries"
            />
            <p v-else class="text-sm text-slate-500 py-10 text-center">Belum ada data pembayaran.</p>
          </div>

          <div class="xl:col-span-2 rounded-3xl bg-white border border-slate-100 shadow-sm p-5">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Rincian Metode Pembayaran</h2>
            <p class="text-xs text-slate-500 mb-4">Dari order_payment periode filter</p>
            <div class="overflow-auto max-h-[320px] rounded-2xl border border-slate-100">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 sticky top-0">
                  <tr class="text-left text-slate-500">
                    <th class="px-4 py-3 font-semibold">Payment</th>
                    <th class="px-4 py-3 font-semibold">Type</th>
                    <th class="px-4 py-3 font-semibold text-right">Trx</th>
                    <th class="px-4 py-3 font-semibold text-right">Amount</th>
                    <th class="px-4 py-3 font-semibold text-right">%</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!paymentMethods.length">
                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">Tidak ada data</td>
                  </tr>
                  <tr
                    v-for="(row, idx) in paymentMethods"
                    :key="row.payment_code + '-' + (row.payment_type || '') + '-' + idx"
                    class="border-t border-slate-50"
                  >
                    <td class="px-4 py-2.5 font-medium text-slate-800">{{ row.payment_code }}</td>
                    <td class="px-4 py-2.5 text-slate-600">{{ row.payment_type || '—' }}</td>
                    <td class="px-4 py-2.5 text-right text-slate-700">{{ formatNumber(row.count) }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-slate-900">{{ formatCurrency(row.amount) }}</td>
                    <td class="px-4 py-2.5 text-right text-slate-600">
                      {{ row.pct != null ? row.pct + '%' : '—' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Quick links -->
        <div class="flex flex-wrap gap-3">
          <a href="/report-daily-outlet-revenue" class="px-4 py-2 rounded-xl bg-white border border-sky-200 text-sky-700 text-sm font-medium hover:bg-sky-50">Daily Revenue</a>
          <a href="/report-receiving-sheet" class="px-4 py-2 rounded-xl bg-white border border-amber-200 text-amber-700 text-sm font-medium hover:bg-amber-50">Receiving Sheet</a>
          <a :href="pettyCashHref" class="px-4 py-2 rounded-xl bg-white border border-emerald-200 text-emerald-700 text-sm font-medium hover:bg-emerald-50">Petty Cash Report</a>
          <a :href="roForecastHref" class="px-4 py-2 rounded-xl bg-white border border-teal-200 text-teal-700 text-sm font-medium hover:bg-teal-50">RO Forecast</a>
        </div>
      </template>
    </div>

    <!-- Detail Modal -->
    <div
      v-if="modalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4 backdrop-blur-[2px]"
      @click.self="closeModal"
    >
      <div class="bg-white rounded-3xl shadow-2xl w-full max-w-5xl max-h-[88vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-xl font-bold text-slate-900">{{ modalTitle }}</h3>
            <p class="text-sm text-slate-500">{{ filters.date_from }} s/d {{ filters.date_to }}</p>
          </div>
          <button type="button" class="text-slate-400 hover:text-slate-700" @click="closeModal">
            <i class="fa-solid fa-xmark text-xl"></i>
          </button>
        </div>

        <div class="p-6 overflow-y-auto flex-1 space-y-5">
          <div v-if="modalLoading" class="py-16 text-center text-slate-400">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat...
          </div>
          <template v-else>
            <apexchart type="area" height="220" :options="modalTrendOptions" :series="modalTrendSeries" />

            <div class="flex flex-wrap gap-2 items-end">
              <input
                v-model="modalSearch"
                type="text"
                placeholder="Cari nomor / user..."
                class="flex-1 min-w-[200px] rounded-xl border-slate-200 text-sm"
                @keyup.enter="fetchModal"
              />
              <button type="button" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm" @click="fetchModal">Cari</button>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-100">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-500">
                  <tr>
                    <th class="px-4 py-3 text-left">Tanggal</th>
                    <th class="px-4 py-3 text-left">Sumber</th>
                    <th class="px-4 py-3 text-left">Nomor</th>
                    <th class="px-4 py-3 text-left">{{ modalPartyColumn }}</th>
                    <th class="px-4 py-3 text-right">{{ modalAmountLabel }}</th>
                    <th v-if="modalShowsBill" class="px-4 py-3 text-right">Bill</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="txn in modalTxns" :key="txn.id + '-' + (txn.source || '')" class="border-t border-slate-100">
                    <td class="px-4 py-2.5">{{ formatShortDate(txn.date) }}</td>
                    <td class="px-4 py-2.5">
                      <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium">{{ txn.source || txn.type }}</span>
                    </td>
                    <td class="px-4 py-2.5 font-medium text-slate-800">{{ txn.number || '-' }}</td>
                    <td class="px-4 py-2.5 text-slate-600">{{ txn.beneficiary_name || txn.creator_name || txn.supplier_name || '-' }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-slate-900">{{ formatCurrency(txn.amount) }}</td>
                    <td v-if="modalShowsBill" class="px-4 py-2.5 text-right font-medium text-slate-700">{{ formatCurrency(txn.bill_amount) }}</td>
                  </tr>
                  <tr v-if="!modalTxns.length">
                    <td :colspan="modalShowsBill ? 6 : 5" class="px-4 py-10 text-center text-slate-400">Tidak ada transaksi</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="modalPagination.total_pages > 1" class="flex justify-between items-center text-sm">
              <span class="text-slate-500">{{ modalPagination.total }} transaksi</span>
              <div class="flex gap-2">
                <button
                  type="button"
                  class="px-3 py-1.5 rounded-lg border disabled:opacity-40"
                  :disabled="modalPage <= 1"
                  @click="modalPage--; fetchModal()"
                >Prev</button>
                <span class="px-2 py-1.5">{{ modalPage }} / {{ modalPagination.total_pages }}</span>
                <button
                  type="button"
                  class="px-3 py-1.5 rounded-lg border disabled:opacity-40"
                  :disabled="modalPage >= modalPagination.total_pages"
                  @click="modalPage++; fetchModal()"
                >Next</button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  dashboardData: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
  userOutletId: { type: [Number, String], default: null },
  canSelectOutlet: { type: Boolean, default: false },
  filters: { type: Object, default: () => ({}) },
  lazy: { type: Boolean, default: true },
})

const page = usePage()
const outlets = computed(() => props.outlets || [])
const canSelectOutlet = computed(() => props.canSelectOutlet)

const emptyDashboard = () => ({
  overview: null,
  trend: [],
  spend_mix: [],
  payment_methods: [],
  ro_forecast: null,
  outlet_name: null,
})

const dashboardData = ref({ ...emptyDashboard(), ...(props.dashboardData || {}) })
const ov = computed(() => dashboardData.value.overview || {})
const vs = computed(() => ov.value.vs_last_month || {})
const vsMember = computed(() => ov.value.vs_last_month_member || {})
const trendRows = computed(() => dashboardData.value.trend || [])
const roForecast = computed(() => dashboardData.value.ro_forecast || null)

const sectionLoading = ref({
  meta: false,
  overview: false,
  member: false,
  ro_forecast: false,
  payments: false,
  charts: false,
})
const sectionError = ref({
  meta: false,
  overview: false,
  member: false,
  ro_forecast: false,
  payments: false,
  charts: false,
})

const bootstrapping = computed(() =>
  Object.values(sectionLoading.value).some(Boolean)
)

const filters = ref({
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  outlet_id: props.filters?.outlet_id || null,
})

const filterParams = () => ({
  outlet_id: filters.value.outlet_id,
  date_from: filters.value.date_from,
  date_to: filters.value.date_to,
})

const mergeSectionPayload = (section, data) => {
  if (!data || typeof data !== 'object') return

  if (section === 'meta' && data.outlet_name !== undefined) {
    dashboardData.value.outlet_name = data.outlet_name
  }

  if (data.overview) {
    dashboardData.value.overview = {
      ...(dashboardData.value.overview || {}),
      ...data.overview,
    }
  }

  if (data.ro_forecast !== undefined) {
    dashboardData.value.ro_forecast = data.ro_forecast
  }

  if (data.payment_methods !== undefined) {
    dashboardData.value.payment_methods = data.payment_methods
  }

  if (data.trend !== undefined) {
    dashboardData.value.trend = data.trend
  }

  if (data.spend_mix !== undefined) {
    dashboardData.value.spend_mix = data.spend_mix
  }
}

const fetchSection = async (section) => {
  if (!filters.value.outlet_id) return
  sectionLoading.value[section] = true
  sectionError.value[section] = false
  try {
    const { data } = await axios.get('/opex-outlet-dashboard/section', {
      params: { section, ...filterParams() },
    })
    mergeSectionPayload(section, data)
  } catch (e) {
    console.error(`Failed loading section ${section}`, e)
    sectionError.value[section] = true
  } finally {
    sectionLoading.value[section] = false
  }
}

const loadDashboardLazy = async () => {
  if (!filters.value.outlet_id) {
    dashboardData.value = emptyDashboard()
    return
  }

  dashboardData.value = emptyDashboard()

  // Semua section paralel; halaman shell sudah tampil tanpa menunggu Inertia berat.
  await Promise.all([
    fetchSection('meta'),
    fetchSection('overview'),
    fetchSection('member'),
    fetchSection('ro_forecast'),
    fetchSection('charts'),
    fetchSection('payments'),
  ])
}

const applyFilters = () => {
  if (!filters.value.outlet_id) {
    alert('Pilih outlet terlebih dahulu')
    return
  }
  // Update URL tanpa menunggu query berat di server
  router.get('/opex-outlet-dashboard', filters.value, {
    preserveState: true,
    preserveScroll: true,
    only: ['filters', 'outlets', 'canSelectOutlet', 'userOutletId', 'lazy'],
    onFinish: () => {
      loadDashboardLazy()
    },
  })
}

onMounted(() => {
  if (filters.value.outlet_id) {
    loadDashboardLazy()
  }
})

watch(
  () => [props.filters?.date_from, props.filters?.date_to, props.filters?.outlet_id],
  ([df, dt, oid]) => {
    if (df) filters.value.date_from = df
    if (dt) filters.value.date_to = dt
    if (oid !== undefined) filters.value.outlet_id = oid
  }
)

const roForecastHref = computed(() => {
  const outlet = filters.value.outlet_id
  const month = (filters.value.date_from || '').slice(0, 7)
  const p = new URLSearchParams()
  if (outlet) p.set('outlet_id', String(outlet))
  if (month) p.set('month', month)
  const q = p.toString()
  return q ? `/reports/floor-order-vs-forecast?${q}` : '/reports/floor-order-vs-forecast'
})

const sourceCards = computed(() => [
  {
    key: 'gsr_ro',
    label: 'GSR / RO',
    amount: ov.value.gsr_ro || 0,
    hint: `GR ${formatCurrency(ov.value.gsr_ro_gr || 0)} · GSR ${formatCurrency(ov.value.gsr_ro_gsr || 0)}`,
    revenuePct: null,
    paymentHint: null,
    vs: vs.value.gsr_ro,
    icon: 'fa-solid fa-truck',
    tone: 'text-amber-600',
    iconBg: 'bg-amber-50',
    border: 'border-amber-100',
    bar: 'bg-amber-400',
  },
  {
    key: 'rws',
    label: 'RWS',
    amount: ov.value.rws || 0,
    hint: `${ov.value.rws_count || 0} transaksi warehouse`,
    revenuePct: null,
    paymentHint: null,
    vs: vs.value.rws,
    icon: 'fa-solid fa-warehouse',
    tone: 'text-violet-600',
    iconBg: 'bg-violet-50',
    border: 'border-violet-100',
    bar: 'bg-violet-400',
  },
  {
    key: 'retail_food',
    label: 'Retail Food',
    amount: ov.value.retail_food || 0,
    hint: `${ov.value.retail_food_count || 0} transaksi`,
    revenuePct: ov.value.retail_food_revenue_pct,
    paymentHint: `Cash ${ov.value.retail_food_cash_count || 0} · Contra Bon ${ov.value.retail_food_contra_bon_count || 0}`,
    vs: vs.value.retail_food,
    icon: 'fa-solid fa-utensils',
    tone: 'text-emerald-600',
    iconBg: 'bg-emerald-50',
    border: 'border-emerald-100',
    bar: 'bg-emerald-400',
  },
  {
    key: 'retail_non_food',
    label: 'Retail Non Food',
    amount: ov.value.retail_non_food || 0,
    hint: `${ov.value.retail_non_food_count || 0} transaksi`,
    revenuePct: ov.value.retail_non_food_revenue_pct,
    paymentHint: `Cash ${ov.value.retail_non_food_cash_count || 0} · Contra Bon ${ov.value.retail_non_food_contra_bon_count || 0}`,
    vs: vs.value.retail_non_food,
    icon: 'fa-solid fa-bag-shopping',
    tone: 'text-orange-600',
    iconBg: 'bg-orange-50',
    border: 'border-orange-100',
    bar: 'bg-orange-400',
  },
  {
    key: 'petty_cash',
    label: 'Petty Cash',
    amount: ov.value.petty_cash || 0,
    hint: `RF cash ${formatCurrency(ov.value.petty_cash_rf || 0)} · RNF cash ${formatCurrency(ov.value.petty_cash_rnf || 0)}`,
    revenuePct: ov.value.petty_cash_revenue_pct,
    paymentHint: `${ov.value.petty_cash_count || 0} trx cash (RF+RNF)`,
    vs: vs.value.petty_cash,
    icon: 'fa-solid fa-wallet',
    tone: 'text-cyan-600',
    iconBg: 'bg-cyan-50',
    border: 'border-cyan-100',
    bar: 'bg-cyan-400',
  },
])

const pettyCashHref = computed(() => {
  const p = new URLSearchParams()
  if (filters.value.outlet_id) p.set('outlet', String(filters.value.outlet_id))
  if (filters.value.date_from) p.set('date_from', filters.value.date_from)
  if (filters.value.date_to) p.set('date_to', filters.value.date_to)
  const q = p.toString()
  return q ? `/report-petty-cash?${q}` : '/report-petty-cash'
})

const spendShare = (amount) => {
  const total = Number(ov.value.total_spend) || 0
  if (total <= 0) return 0
  return Math.min(100, Math.round((Number(amount) / total) * 100))
}

const categories = computed(() =>
  trendRows.value.map((r) =>
    new Date(r.date + 'T12:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
  )
)

const moneyTooltip = {
  y: {
    formatter: (val) => formatCurrency(val),
  },
}

const trendSeries = computed(() => [
  { name: 'Revenue', data: trendRows.value.map((r) => Number(r.revenue) || 0) },
  { name: 'Total Spend', data: trendRows.value.map((r) => Number(r.total_spend) || 0) },
])

const trendOptions = computed(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit' },
  colors: ['#0ea5e9', '#f43f5e'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 3 },
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 90, 100] },
  },
  xaxis: { categories: categories.value, labels: { rotate: -35, style: { fontSize: '11px' } } },
  yaxis: { labels: { formatter: (v) => formatCompact(v) } },
  legend: { position: 'top' },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  tooltip: moneyTooltip,
}))

const mixSeries = computed(() => (dashboardData.value.spend_mix || []).map((i) => Number(i.amount) || 0))
const mixOptions = computed(() => ({
  labels: (dashboardData.value.spend_mix || []).map((i) => i.label),
  colors: ['#f59e0b', '#8b5cf6', '#10b981', '#f97316'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
  plotOptions: { pie: { donut: { size: '62%' } } },
  tooltip: {
    y: { formatter: (val) => formatCurrency(val) },
  },
}))

const paymentMethods = computed(() => dashboardData.value.payment_methods || [])

const paymentGrouped = computed(() => {
  const map = new Map()
  for (const row of paymentMethods.value) {
    const code = row.payment_code || 'Other'
    const prev = map.get(code) || { payment_code: code, amount: 0, count: 0 }
    prev.amount += Number(row.amount) || 0
    prev.count += Number(row.count) || 0
    map.set(code, prev)
  }
  return Array.from(map.values()).sort((a, b) => b.amount - a.amount)
})

const paymentMixSeries = computed(() => paymentGrouped.value.map((i) => Number(i.amount) || 0))
const paymentMixOptions = computed(() => ({
  labels: paymentGrouped.value.map((i) => i.payment_code),
  colors: ['#0ea5e9', '#14b8a6', '#8b5cf6', '#f59e0b', '#f43f5e', '#64748b', '#22c55e', '#eab308'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(0)}%` },
  plotOptions: { pie: { donut: { size: '60%' } } },
  tooltip: {
    y: { formatter: (val) => formatCurrency(val) },
  },
}))

const stackSeries = computed(() => [
  { name: 'GSR / RO', data: trendRows.value.map((r) => Number(r.gsr_ro) || 0) },
  { name: 'RWS', data: trendRows.value.map((r) => Number(r.rws) || 0) },
  { name: 'Retail Food', data: trendRows.value.map((r) => Number(r.retail_food) || 0) },
  { name: 'Retail Non Food', data: trendRows.value.map((r) => Number(r.retail_non_food) || 0) },
])

const stackOptions = computed(() => ({
  chart: { stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
  colors: ['#f59e0b', '#8b5cf6', '#10b981', '#f97316'],
  plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
  dataLabels: { enabled: false },
  xaxis: { categories: categories.value, labels: { rotate: -35, style: { fontSize: '11px' } } },
  yaxis: { labels: { formatter: (v) => formatCompact(v) } },
  legend: { position: 'top' },
  grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
  tooltip: moneyTooltip,
}))

const modalOpen = ref(false)
const modalLoading = ref(false)
const modalType = ref('')
const modalSearch = ref('')
const modalPage = ref(1)
const modalTxns = ref([])
const modalTrend = ref([])
const modalPagination = ref({ total: 0, total_pages: 1 })

const modalTitle = computed(() => {
  const map = {
    revenue: 'Revenue',
    discount: 'Diskon',
    discount_compliment: 'Compliment',
    discount_guest_satisfaction: 'Guest Satisfaction',
    officer_check: 'Officer Check',
    member_top_up: 'Point Earn',
    member_redeem: 'Point Redeem',
    gsr_ro: 'GSR / RO',
    rws: 'RWS',
    retail_food: 'Retail Food',
    retail_non_food: 'Retail Non Food',
    petty_cash: 'Petty Cash',
    total_spend: 'Total Spend',
  }
  return map[modalType.value] || 'Detail'
})

const modalPartyColumn = computed(() => {
  const map = {
    discount: 'Member / Promo',
    discount_compliment: 'Reason',
    discount_guest_satisfaction: 'Reason',
    officer_check: 'Officer',
    member_top_up: 'Member',
    member_redeem: 'Member / Reward',
    revenue: 'Member',
    gsr_ro: 'User / Supplier',
    rws: 'User / Supplier',
    retail_food: 'User / Supplier',
    retail_non_food: 'User / Supplier',
    petty_cash: 'User / Supplier',
    total_spend: 'User / Supplier',
  }
  return map[modalType.value] || 'Keterangan'
})

const modalShowsBill = computed(() =>
  ['discount_compliment', 'discount_guest_satisfaction', 'officer_check'].includes(modalType.value)
)

const modalAmountLabel = computed(() => {
  if (modalType.value === 'officer_check') return 'Pembayaran'
  if (modalShowsBill.value) return 'Discount'
  return 'Amount'
})

const modalTrendSeries = computed(() => [{ name: modalTitle.value, data: modalTrend.value.map((r) => Number(r.amount) || 0) }])
const modalTrendOptions = computed(() => ({
  chart: { toolbar: { show: false }, sparkline: { enabled: false } },
  colors: ['#0ea5e9'],
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.05 } },
  dataLabels: { enabled: false },
  xaxis: {
    categories: modalTrend.value.map((r) =>
      new Date(r.date + 'T12:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
    ),
    labels: { rotate: -30, style: { fontSize: '10px' } },
  },
  yaxis: { labels: { formatter: (v) => formatCompact(v) } },
  tooltip: moneyTooltip,
}))

const openCard = async (type) => {
  modalType.value = type
  modalOpen.value = true
  modalSearch.value = ''
  modalPage.value = 1
  await fetchModal()
}

const closeModal = () => {
  modalOpen.value = false
  modalTxns.value = []
  modalTrend.value = []
}

const fetchModal = async () => {
  modalLoading.value = true
  try {
    const { data } = await axios.get('/opex-outlet-dashboard/card-detail', {
      params: {
        type: modalType.value,
        outlet_id: filters.value.outlet_id,
        date_from: filters.value.date_from,
        date_to: filters.value.date_to,
        search: modalSearch.value,
        page: modalPage.value,
        per_page: 20,
      },
    })
    modalTrend.value = data.trend || []
    modalTxns.value = data.transactions || []
    modalPagination.value = data.pagination || { total: 0, total_pages: 1 }
  } catch (e) {
    console.error(e)
    alert('Gagal memuat detail')
  } finally {
    modalLoading.value = false
  }
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(Number(value) || 0)

const formatNumber = (value) =>
  new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
  }).format(Number(value) || 0)

const formatDecimal = (value) =>
  new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(Number(value) || 0)

const vsLabel = (metric, format = 'currency') => {
  if (!metric || metric.previous == null) return 'vs last month —'
  const prev = Number(metric.previous)
  let prevText = ''
  if (format === 'number') prevText = formatNumber(prev)
  else if (format === 'decimal') prevText = formatDecimal(prev)
  else if (format === 'points') prevText = `${formatNumber(prev)} pts`
  else prevText = formatCurrency(prev)

  if (metric.pct == null) return `LM ${prevText}`
  const pct = Number(metric.pct)
  const sign = pct > 0 ? '+' : ''
  return `${sign}${pct}% · LM ${prevText}`
}

/** invert=true: naik = buruk (spend/discount) */
const vsClass = (metric, invert = false) => {
  if (!metric || (metric.pct == null && metric.previous == null)) return 'text-slate-400'
  if (metric.pct == null) return 'text-slate-500'
  const pct = Number(metric.pct)
  if (pct === 0) return 'text-slate-500'
  const up = pct > 0
  const good = invert ? !up : up
  return good ? 'text-emerald-600' : 'text-rose-600'
}

const formatRemaining = (value) => {
  const n = Number(value) || 0
  if (n >= 0) return formatCurrency(n)
  return 'Over ' + formatCurrency(Math.abs(n))
}

const remainingClass = (value) => {
  const n = Number(value) || 0
  if (n > 0) return 'text-teal-700'
  if (n < 0) return 'text-rose-600'
  return 'text-slate-700'
}

const formatCompact = (value) => {
  const n = Number(value) || 0
  if (Math.abs(n) >= 1_000_000_000) return (n / 1_000_000_000).toFixed(1) + 'M'
  if (Math.abs(n) >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'jt'
  if (Math.abs(n) >= 1_000) return (n / 1_000).toFixed(0) + 'rb'
  return String(Math.round(n))
}

const formatShortDate = (date) => {
  if (!date) return '-'
  const d = String(date).slice(0, 10)
  return new Date(d + 'T12:00:00').toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

void page
</script>
