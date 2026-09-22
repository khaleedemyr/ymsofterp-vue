<template>
  <AppLayout>
    <Head title="Outlet Dashboard" />

    <div class="w-full min-h-screen bg-gradient-to-b from-slate-50 via-white to-sky-50/40 px-4 sm:px-6 lg:px-8 py-6">
      <!-- Header -->
      <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4 mb-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-600 mb-1">Outlet Operations</p>
          <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Outlet Dashboard</h1>
          <p class="text-slate-500 mt-1 text-sm">
            Ringkasan revenue, GSR/RO, Retail Food & Non Food
            <span v-if="dashboardData.outlet_name"> · {{ dashboardData.outlet_name }}</span>
          </p>
        </div>

        <div class="w-full xl:w-auto xl:min-w-[640px]">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Bulan</label>
            <select v-model.number="filters.bulan" class="w-full rounded-xl border-slate-200 text-sm focus:ring-sky-500 focus:border-sky-500">
              <option v-for="(m, idx) in monthNames" :key="idx + 1" :value="idx + 1">{{ m }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Tahun</label>
            <select v-model.number="filters.tahun" class="w-full rounded-xl border-slate-200 text-sm focus:ring-sky-500 focus:border-sky-500">
              <option v-for="t in tahunOptions" :key="t" :value="t">{{ t }}</option>
            </select>
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
        <p class="text-xs text-slate-500 mt-2 space-y-0.5">
          <span class="block">Revenue &amp; Spend: {{ periodLabel }}</span>
          <span class="block">Absensi (26–25): {{ attendancePeriodLabel }}</span>
        </p>
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

        <RollingForecastPanel
          :outlet-id="filters.outlet_id"
          :month="rollingForecastMonth"
        />

        <!-- ========== Group: RO Forecast ========== -->
        <section class="mb-8">
          <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-teal-100 text-teal-700">
              <i class="fa-solid fa-bullseye text-sm"></i>
            </span>
            <div>
              <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-teal-700">RO Forecast</h2>
              <p class="text-xs text-slate-500">Budget vs purchase Kitchen, Bar, dan Service</p>
            </div>
          </div>
        <div class="rounded-3xl bg-white border border-teal-100 shadow-sm p-5 sm:p-6">
          <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-teal-600">RO Forecast</p>
              <h2 class="text-xl font-bold text-slate-900 mt-0.5">Budget vs Purchase</h2>
              <p class="text-xs text-slate-500 mt-1">
                Full month {{ roForecast?.period_from || '—' }} s/d {{ roForecast?.period_to || '—' }}
                · Forecast = Rolling Realistis
                · Pool {{ roForecast?.budget_pool_ratio_pct || 43 }}% × Forecast
                · Kitchen 70% · Bar 20% · Service 10%
                · Purchased = GSR/GR + RF · RO outstanding terpisah (bukan MTD)
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
            Belum ada monthly target / Rolling Forecast realistis untuk periode ini.
          </div>

          <div v-else class="space-y-4">
            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4 sm:flex sm:items-end sm:justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 inline-flex items-center gap-1">
                  Forecast
                  <CardHelpTip :text="cardHelps.forecast" />
                </p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ formatCurrency(roForecast.forecast) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ roForecast.forecast_source === 'rolling_realistic' ? 'Rolling Auto Forecast · skenario Realistis' : 'Revenue Target (fallback)' }}
                  <span v-if="roForecast.pace_factor != null"> · pace {{ roForecast.pace_factor }}</span>
                </p>
              </div>
              <div class="mt-3 sm:mt-0 sm:text-right">
                <p class="text-xs text-slate-500 inline-flex items-center gap-1 justify-end">
                  Pool budget {{ roForecast.budget_pool_ratio_pct || 43 }}%
                  <CardHelpTip :text="cardHelps.budget_pool" />
                </p>
                <p class="text-lg font-bold text-slate-800">{{ formatCurrency(roForecast.budget_pool) }}</p>
                <p class="text-[11px] text-slate-400">Lalu dibagi Kitchen / Bar / Service</p>
              </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
              <button
                v-for="card in purchaseBudgetCards"
                :key="card.key"
                type="button"
                class="rounded-2xl border p-4 text-left transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-400"
                :class="card.cardClass"
                @click="openCard(card.modalType)"
              >
                <div class="flex items-center justify-between gap-2">
                  <p class="text-xs font-semibold uppercase tracking-wide inline-flex items-center gap-1" :class="card.titleClass">
                    {{ card.label }}
                    <span @click.stop>
                      <CardHelpTip :text="card.help" />
                    </span>
                  </p>
                  <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full" :class="card.badgeClass">
                    {{ card.sharePct }}% dari {{ roForecast.budget_pool_ratio_pct || 43 }}%
                  </span>
                </div>
                <div class="mt-3">
                  <p class="text-slate-500 text-xs inline-flex items-center gap-1">
                    Purchased
                    <span @click.stop>
                      <CardHelpTip :text="cardHelps.purchased_field" />
                    </span>
                  </p>
                  <p class="mt-0.5 text-2xl sm:text-3xl font-bold tracking-tight" :class="card.valueClass">
                    {{ formatCurrency(card.data?.purchased) }}
                  </p>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <p class="text-slate-500 text-xs inline-flex items-center gap-1">
                      Budget
                      <span @click.stop>
                        <CardHelpTip :text="cardHelps.budget_field" />
                      </span>
                    </p>
                    <p class="font-semibold text-slate-900">{{ formatCurrency(card.data?.budget) }}</p>
                  </div>
                  <div>
                    <p class="text-slate-500 text-xs inline-flex items-center gap-1">
                      RO Outstanding
                      <span @click.stop>
                        <CardHelpTip :text="cardHelps.ro_outstanding_field" />
                      </span>
                    </p>
                    <p class="font-semibold text-amber-800">{{ formatCurrency(card.data?.ro_outstanding) }}</p>
                  </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-white/70 border border-slate-100/80 px-2.5 py-2">
                  <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">GSR</p>
                    <p class="text-xs font-semibold text-slate-800 truncate" :title="formatCurrency(card.data?.gsr)">
                      {{ formatCurrency(card.data?.gsr) }}
                    </p>
                  </div>
                  <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">RF</p>
                    <p class="text-xs font-semibold text-slate-800 truncate" :title="formatCurrency(card.data?.rf)">
                      {{ formatCurrency(card.data?.rf) }}
                    </p>
                  </div>
                </div>
                <div class="mt-3 pt-3 border-t flex items-end justify-between gap-2" :class="card.dividerClass">
                  <div>
                    <p class="text-xs text-slate-500 inline-flex items-center gap-1">
                      Sisa budget
                      <span @click.stop>
                        <CardHelpTip :text="cardHelps.remaining_budget_field" />
                      </span>
                    </p>
                    <p class="text-xl font-bold" :class="remainingClass(card.data?.remaining)">
                      {{ formatRemaining(card.data?.remaining) }}
                    </p>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                      Setelah commit:
                      <span :class="remainingClass(card.data?.remaining_after_commit)">{{ formatRemaining(card.data?.remaining_after_commit) }}</span>
                    </p>
                  </div>
                  <p class="text-sm font-semibold text-slate-600">
                    {{ card.data?.pct != null ? card.data.pct + '% terpakai' : '—' }}
                  </p>
                </div>
                <div class="mt-3 h-2 rounded-full bg-white overflow-hidden border" :class="card.barBorderClass">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="(card.data?.pct || 0) > 100 ? 'bg-rose-500' : card.barFillClass"
                    :style="{ width: Math.min(100, card.data?.pct || 0) + '%' }"
                  ></div>
                </div>
                <p class="mt-3 text-[11px] font-medium" :class="card.titleClass">
                  Klik untuk lihat transaksi · detail item
                </p>
              </button>
            </div>
          </div>
        </div>
        </section>

        <!-- ========== Group: Pembelian & Spend Source ========== -->
        <section class="mb-8">
          <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
              <i class="fa-solid fa-truck-ramp-box text-sm"></i>
            </span>
            <div>
              <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-amber-700">Pembelian &amp; Spend Source</h2>
              <p class="text-xs text-slate-500">GSR · Retail · Petty Cash · MCS · kategori pembelian</p>
            </div>
          </div>

        <!-- Source cards — di bawah Purchased (GSR/RF/RNF/Petty) -->
        <div v-if="!sectionLoading.overview" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          <button
            v-for="card in sourceCards"
            :key="card.key"
            type="button"
            class="rounded-3xl bg-white border shadow-sm p-5 text-left hover:shadow-md transition group"
            :class="card.border"
            @click="openCard(card.key)"
          >
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold uppercase tracking-wide inline-flex items-center gap-1" :class="card.tone">
                {{ card.label }}
                <CardHelpTip :text="card.help" />
              </span>
              <span class="w-10 h-10 rounded-xl flex items-center justify-center" :class="card.iconBg">
                <i :class="[card.icon, card.tone]"></i>
              </span>
            </div>
            <p class="text-2xl font-bold text-slate-900">{{ formatCurrency(card.amount) }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ card.hint }}</p>
            <p v-if="card.paymentHint" class="text-xs text-slate-500 mt-1">{{ card.paymentHint }}</p>
            <p v-if="card.extraHint" class="text-xs font-medium text-emerald-700 mt-1">{{ card.extraHint }}</p>
            <p class="mt-1 text-xs font-medium" :class="vsClass(card.vs, true)">{{ vsLabel(card.vs) }}</p>
            <div class="mt-3 space-y-2">
              <div>
                <div class="flex items-center justify-between gap-2 mb-0.5">
                  <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">vs Total Spend</span>
                  <span class="text-[10px] font-semibold text-slate-600">{{ spendShare(card.amount) }}%</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-full rounded-full" :class="card.bar" :style="{ width: spendShare(card.amount) + '%' }"></div>
                </div>
              </div>
              <div>
                <div class="flex items-center justify-between gap-2 mb-0.5">
                  <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">vs Revenue</span>
                  <span class="text-[10px] font-semibold text-slate-600">{{ revenueShare(card.amount) }}%</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-full rounded-full opacity-80" :class="card.bar" :style="{ width: Math.min(100, revenueShare(card.amount)) + '%' }"></div>
                </div>
              </div>
            </div>
          </button>
        </div>

        <!-- Pembelian MCS — langsung di bawah RO Forecast -->
        <div v-if="!sectionLoading.overview" class="mb-4">
          <button
            type="button"
            class="w-full rounded-3xl bg-white border border-amber-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('mcs_purchase')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 inline-flex items-center gap-1">
                  Pembelian MCS
                  <CardHelpTip :text="cardHelps.mcs_purchase" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.mcs_purchase) }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ ov.mcs_purchase_count || 0 }} transaksi · GSR · Retail Food</p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.mcs_purchase, true)">{{ vsLabel(vs.mcs_purchase) }}</p>
                <div class="mt-3 max-w-md space-y-2">
                  <div>
                    <div class="flex items-center justify-between gap-2 mb-0.5">
                      <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">vs Total Spend</span>
                      <span class="text-[10px] font-semibold text-slate-600">{{ spendShare(ov.mcs_purchase) }}%</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                      <div class="h-full rounded-full bg-amber-400" :style="{ width: spendShare(ov.mcs_purchase) + '%' }"></div>
                    </div>
                  </div>
                  <div>
                    <div class="flex items-center justify-between gap-2 mb-0.5">
                      <span class="text-[10px] font-medium uppercase tracking-wide text-slate-400">vs Revenue</span>
                      <span class="text-[10px] font-semibold text-slate-600">{{ revenueShare(ov.mcs_purchase) }}%</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                      <div class="h-full rounded-full bg-amber-400 opacity-80" :style="{ width: Math.min(100, revenueShare(ov.mcs_purchase)) + '%' }"></div>
                    </div>
                  </div>
                </div>
                <div class="mt-3 grid grid-cols-3 sm:grid-cols-6 gap-x-3 gap-y-1.5">
                  <div
                    v-for="row in mcsPurchaseByCategory"
                    :key="row.key"
                    class="min-w-0"
                  >
                    <p class="text-[10px] uppercase tracking-wide text-slate-400 truncate">{{ row.label }}</p>
                    <p class="text-xs font-semibold text-slate-700 truncate">{{ formatCurrency(row.amount) }}</p>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-box-open text-xl"></i>
              </div>
            </div>
          </button>
        </div>
        <div v-else-if="sectionLoading.overview" class="rounded-3xl bg-white border border-amber-100 shadow-sm py-10 mb-4 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat Pembelian MCS…
        </div>

        <!-- Chart Pembelian per Category — di bawah purchased -->
        <div v-if="sectionLoading.charts" class="rounded-3xl bg-white border border-amber-100 shadow-sm py-12 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat chart pembelian…
        </div>
        <div v-else>
          <div class="rounded-3xl bg-white border border-amber-100 shadow-sm p-5">
            <h2 class="text-lg font-bold text-slate-900 mb-1 inline-flex items-center gap-1.5">
              Pembelian per Category
              <CardHelpTip :text="cardHelps.purchase_category" />
            </h2>
            <p class="text-xs text-slate-500 mb-4">Semua category · GSR · Retail Food — klik slice untuk detail</p>
            <div class="max-w-xl mx-auto">
              <apexchart
                v-if="purchaseCategoryMixSeries.some((v) => v > 0)"
                type="pie"
                height="360"
                :options="purchaseCategoryMixOptions"
                :series="purchaseCategoryMixSeries"
              />
              <p v-else class="text-sm text-slate-500 py-16 text-center">Belum ada pembelian.</p>
            </div>
          </div>
        </div>
        </section>

        <!-- ========== Group: Revenue & Sales ========== -->
        <section class="mb-8">
          <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
              <i class="fa-solid fa-chart-line text-sm"></i>
            </span>
            <div>
              <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-sky-700">Revenue &amp; Sales</h2>
              <p class="text-xs text-slate-500">KPI revenue, spend, cover, diskon, dan payment khusus</p>
            </div>
          </div>

        <!-- Hero metrics -->
        <div v-if="sectionLoading.overview" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-16 mb-4 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat KPI…
        </div>
        <template v-else>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
          <button
            type="button"
            class="lg:col-span-4 rounded-3xl bg-white border border-sky-100 shadow-sm p-6 text-left hover:shadow-md transition"
            @click="openCard('revenue')"
          >
            <div class="flex items-start justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-600 inline-flex items-center gap-1">
                  Revenue
                  <CardHelpTip :text="cardHelps.revenue" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.revenue) }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ ov.revenue_count || 0 }} orders</p>
                <template v-if="ov.revenue_monthly_budget != null">
                  <p class="mt-3 text-sm font-semibold text-slate-700">
                    Budget <span class="text-base text-slate-900">{{ formatCurrency(ov.revenue_monthly_budget) }}</span>
                  </p>
                  <div class="mt-2 h-2.5 rounded-full bg-slate-100 overflow-hidden max-w-[260px]">
                    <div
                      class="h-full rounded-full transition-all"
                      :class="(ov.revenue_budget_perf_percent || 0) >= 100 ? 'bg-emerald-500' : 'bg-sky-500'"
                      :style="{ width: Math.min(100, ov.revenue_budget_perf_percent || 0) + '%' }"
                    ></div>
                  </div>
                  <p
                    class="mt-1.5 text-sm font-bold"
                    :class="(ov.revenue_budget_perf_percent || 0) >= 100 ? 'text-emerald-600' : 'text-sky-700'"
                  >
                    Performa {{ ov.revenue_budget_perf_percent }}%
                    <span v-if="ov.revenue_budget_variance != null" class="font-semibold text-slate-500">
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
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-600 inline-flex items-center gap-1">
                  Total Spend
                  <span @click.stop>
                    <CardHelpTip :text="cardHelps.total_spend" />
                  </span>
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.total_spend) }}</p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ ov.spend_ratio_percent != null ? ov.spend_ratio_percent + '% dari revenue' : '—' }}
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.total_spend, true)">{{ vsLabel(vs.total_spend) }}</p>

                <div class="mt-4 pt-3 border-t border-rose-100/80 space-y-1.5">
                  <div
                    v-for="row in totalSpendBreakdown"
                    :key="row.key"
                    class="flex items-center justify-between gap-3 text-xs"
                  >
                    <span class="text-slate-500">{{ row.label }}</span>
                    <span class="font-semibold tabular-nums text-slate-800">{{ formatCurrency(row.amount) }}</span>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-cart-shopping text-xl"></i>
              </div>
            </div>
          </button>

          <div class="lg:col-span-4 rounded-3xl bg-white border border-emerald-100 shadow-sm p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600 inline-flex items-center gap-1">
              Net (Rev − Spend)
              <CardHelpTip :text="cardHelps.net" />
            </p>
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
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <div class="rounded-3xl bg-white border border-indigo-100 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 inline-flex items-center gap-1">
              Cover / Pax
              <CardHelpTip :text="cardHelps.cover" />
            </p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(ov.cover) }}</p>
            <p class="mt-1 text-xs text-slate-500">Total tamu periode filter</p>
            <p class="mt-1 text-xs font-medium" :class="vsClass(vs.cover)">{{ vsLabel(vs.cover, 'number') }}</p>
          </div>
          <div class="rounded-3xl bg-white border border-violet-100 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-600 inline-flex items-center gap-1">
              Average Pax
              <CardHelpTip :text="cardHelps.avg_pax" />
            </p>
            <p class="mt-2 text-3xl font-bold text-slate-900">
              {{ ov.avg_pax != null ? formatDecimal(ov.avg_pax) : '—' }}
            </p>
            <p class="mt-1 text-xs text-slate-500">Rata-rata pax per bill</p>
            <p class="mt-1 text-xs font-medium" :class="vsClass(vs.avg_pax)">{{ vsLabel(vs.avg_pax, 'decimal') }}</p>
          </div>
          <div class="rounded-3xl bg-white border border-fuchsia-100 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600 inline-flex items-center gap-1">
              Avg Check
              <CardHelpTip :text="cardHelps.avg_check" />
            </p>
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
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600 inline-flex items-center gap-1">
                  Diskon
                  <CardHelpTip :text="cardHelps.discount" />
                </p>
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
                <p class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600 inline-flex items-center gap-1">
                  Compliment
                  <CardHelpTip :text="cardHelps.discount_compliment" />
                </p>
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
                <p class="text-xs font-semibold uppercase tracking-wide text-orange-600 inline-flex items-center gap-1">
                  Guest Satisfaction
                  <CardHelpTip :text="cardHelps.discount_guest_satisfaction" />
                </p>
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
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 inline-flex items-center gap-1">
                  Officer Check
                  <CardHelpTip :text="cardHelps.officer_check" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.officer_check) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ ov.officer_check_count || 0 }} pembayaran OFFICER_CHECK
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.officer_check, true)">{{ vsLabel(vs.officer_check) }}</p>
              </div>
              <span class="text-indigo-400 text-xs mt-1">Detail →</span>
            </div>
          </button>
          <button
            type="button"
            class="rounded-3xl bg-white border border-lime-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('outlet_city_ledger')"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-lime-700 inline-flex items-center gap-1">
                  Outlet City Ledger
                  <CardHelpTip :text="cardHelps.outlet_city_ledger" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.outlet_city_ledger) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ ov.outlet_city_ledger_count || 0 }} pembayaran OUTLET_CITY_LEDGER
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.outlet_city_ledger, true)">{{ vsLabel(vs.outlet_city_ledger) }}</p>
              </div>
              <span class="text-lime-500 text-xs mt-1">Detail →</span>
            </div>
          </button>
        </div>
        </template>
        </section>

        <!-- ========== Group: Member ========== -->
        <section class="mb-8">
          <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-teal-100 text-teal-700">
              <i class="fa-solid fa-id-card text-sm"></i>
            </span>
            <div>
              <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-teal-700">Member</h2>
              <p class="text-xs text-slate-500">Bill member, point earn, dan redeem</p>
            </div>
          </div>

        <!-- Member Top Up / Redeem -->
        <div v-if="sectionLoading.member" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-10 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat data member…
        </div>
        <div v-else-if="!sectionLoading.overview" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="rounded-3xl bg-white border border-sky-100 shadow-sm p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-600 inline-flex items-center gap-1">
              Member Bills
              <CardHelpTip :text="cardHelps.member_bills" />
            </p>
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
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-600 inline-flex items-center gap-1">
                  Point Earn
                  <CardHelpTip :text="cardHelps.member_top_up" />
                </p>
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
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-600 inline-flex items-center gap-1">
                  Point Redeem
                  <CardHelpTip :text="cardHelps.member_redeem" />
                </p>
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
        </section>

        <!-- ========== Group: Inventory ========== -->
        <section class="mb-8">
          <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-700">
              <i class="fa-solid fa-warehouse text-sm"></i>
            </span>
            <div>
              <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-fuchsia-700">Inventory</h2>
              <p class="text-xs text-slate-500">Begin, cut, category, ending, % COGS, transfer, adjustment, WIP</p>
            </div>
          </div>

        <!-- Inventory cards: satu grid agar flow rapat (tanpa baris kosong) -->
        <div v-if="!sectionLoading.overview" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
          <button
            type="button"
            class="rounded-3xl bg-white border border-indigo-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('begin_inventory')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 inline-flex items-center gap-1">
                  Begin Inventory
                  <CardHelpTip :text="cardHelps.begin_inventory" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.begin_inventory) }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ ov.begin_inventory_count || 0 }} item · Total MAC</p>
                <p v-if="ov.begin_inventory_revenue_pct != null" class="text-xs font-semibold text-slate-600 mt-1">
                  {{ ov.begin_inventory_revenue_pct }}% dari revenue
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.begin_inventory, true)">{{ vsLabel(vs.begin_inventory) }}</p>
                <p class="mt-1 text-[10px] uppercase tracking-wide text-slate-400">
                  Sumber: {{ beginInventorySourceLabel(ov.begin_inventory_source) }}
                </p>
                <div v-if="(ov.begin_inventory_by_warehouse || []).length" class="mt-3 space-y-1 border-t border-indigo-50 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400">Per warehouse</p>
                  <div
                    v-for="row in ov.begin_inventory_by_warehouse"
                    :key="'begin-wh-' + row.warehouse_id"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-500 truncate">{{ row.warehouse_name }}</span>
                    <span class="font-semibold text-slate-700 shrink-0">{{ formatCurrency(row.amount) }}</span>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-boxes-stacked text-xl"></i>
              </div>
            </div>
          </button>

          <button
            type="button"
            class="rounded-3xl bg-white border border-fuchsia-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('stock_cut')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600 inline-flex items-center gap-1">
                  Stock Cut
                  <CardHelpTip :text="cardHelps.stock_cut" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.stock_cut) }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ ov.stock_cut_count || 0 }} potong stok · HPP full</p>
                <p v-if="(ov.stock_cut_shortfall || 0) > 0" class="text-xs text-amber-700 mt-1">
                  Minus/shortfall {{ formatCurrency(ov.stock_cut_shortfall) }} · fisik {{ formatCurrency(ov.stock_cut_physical) }}
                </p>
                <p v-if="ov.stock_cut_revenue_pct != null" class="text-xs font-semibold text-slate-600 mt-1">
                  {{ ov.stock_cut_revenue_pct }}% dari revenue
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.stock_cut, true)">{{ vsLabel(vs.stock_cut) }}</p>
                <div v-if="(ov.stock_cut_by_warehouse || []).length" class="mt-3 space-y-1 border-t border-fuchsia-50 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400">Per warehouse</p>
                  <div
                    v-for="row in ov.stock_cut_by_warehouse"
                    :key="'sc-wh-' + row.warehouse_id"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-500 truncate">{{ row.warehouse_name }}</span>
                    <span class="font-semibold text-slate-700 shrink-0">{{ formatCurrency(row.amount) }}</span>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-fuchsia-50 text-fuchsia-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-scissors text-xl"></i>
              </div>
            </div>
          </button>

          <button
            type="button"
            class="rounded-3xl bg-white border border-teal-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('category_cost')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-600 inline-flex items-center gap-1">
                  Category Cost
                  <CardHelpTip :text="cardHelps.category_cost" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.category_cost) }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ ov.category_cost_count || 0 }} dokumen</p>
                <p v-if="ov.category_cost_revenue_pct != null" class="text-xs font-semibold text-slate-600 mt-1">
                  {{ ov.category_cost_revenue_pct }}% dari revenue
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.category_cost, true)">{{ vsLabel(vs.category_cost) }}</p>
                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5">
                  <div
                    v-for="row in categoryCostByType"
                    :key="row.type"
                    class="min-w-0"
                  >
                    <p class="text-[10px] uppercase tracking-wide text-slate-400 truncate">{{ row.label }}</p>
                    <p class="text-xs font-semibold text-slate-700 truncate">{{ formatCurrency(row.amount) }}</p>
                  </div>
                </div>
                <div v-if="(ov.category_cost_by_warehouse || []).length" class="mt-3 space-y-1 border-t border-teal-50 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400">Per warehouse</p>
                  <div
                    v-for="row in ov.category_cost_by_warehouse"
                    :key="'cc-wh-' + row.warehouse_id"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-500 truncate">{{ row.warehouse_name }}</span>
                    <span class="font-semibold text-slate-700 shrink-0">{{ formatCurrency(row.amount) }}</span>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-trash text-xl"></i>
              </div>
            </div>
          </button>

          <button
            type="button"
            class="rounded-3xl bg-white border border-amber-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('ending_inventory')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 inline-flex items-center gap-1">
                  Ending Inventory
                  <CardHelpTip :text="cardHelps.ending_inventory" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.ending_inventory) }}</p>
                <p class="mt-1 text-sm text-slate-500">Begin + Koreksi tgl 1* + Purchased ± Xfer ± Adj − Cut − Category</p>
                <p v-if="ov.ending_inventory_revenue_pct != null" class="text-xs font-semibold text-slate-600 mt-1">
                  {{ ov.ending_inventory_revenue_pct }}% dari revenue
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.ending_inventory, true)">{{ vsLabel(vs.ending_inventory) }}</p>
                <div class="mt-3 rounded-2xl bg-amber-50/70 border border-amber-100 px-3 py-2 space-y-1">
                  <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="text-slate-500">Cost di stok</span>
                    <span class="font-semibold text-slate-800">{{ formatCurrency(ov.ending_inventory_stock) }}</span>
                  </div>
                  <div
                    v-if="(ov.ending_inventory_formula?.day1_opname_cutoff || 0) !== 0"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-500">Koreksi fisik tgl 1 (tanpa IB)</span>
                    <span class="font-semibold text-slate-800">{{ formatCurrency(ov.ending_inventory_formula?.day1_opname_cutoff) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="text-slate-500">Selisih (formula − stok)</span>
                    <span
                      class="font-semibold"
                      :class="(ov.ending_inventory_formula?.variance || 0) === 0 ? 'text-emerald-700' : 'text-rose-700'"
                    >
                      {{ formatCurrency(ov.ending_inventory_formula?.variance) }}
                    </span>
                  </div>
                </div>
                <div v-if="(ov.ending_inventory_stock_by_warehouse || ov.ending_inventory_by_warehouse || []).length" class="mt-3 space-y-1 border-t border-amber-50 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400">Per warehouse (stok)</p>
                  <div
                    v-for="row in (ov.ending_inventory_stock_by_warehouse || ov.ending_inventory_by_warehouse)"
                    :key="'end-wh-' + row.warehouse_id"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-500 truncate">{{ row.warehouse_name }}</span>
                    <span class="font-semibold text-slate-700 shrink-0">{{ formatCurrency(row.amount) }}</span>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-clipboard-check text-xl"></i>
              </div>
            </div>
          </button>

          <div class="rounded-3xl bg-white border border-rose-100 shadow-sm p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700 inline-flex items-center gap-1">
                  % COGS
                  <CardHelpTip :text="cardHelps.cogs_pct" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">
                  {{ ov.cogs_pct != null ? ov.cogs_pct + '%' : '—' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">Actual after discount</p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.cogs_pct, true)">{{ vsLabel(vs.cogs_pct, 'decimal') }}</p>
                <div class="mt-3 rounded-2xl bg-rose-50/70 border border-rose-100 px-3 py-2 space-y-1">
                  <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="text-slate-500">% Actual before disc</span>
                    <span class="font-semibold text-slate-800">{{ ov.cogs?.pct_cogs_actual_before_disc != null ? ov.cogs.pct_cogs_actual_before_disc + '%' : '—' }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="text-slate-500">% COGS Foods</span>
                    <span class="font-semibold text-slate-800">{{ ov.cogs?.pct_cogs_foods != null ? ov.cogs.pct_cogs_foods + '%' : '—' }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="text-slate-500">% COGS Pembanding</span>
                    <span class="font-semibold text-slate-800">{{ ov.cogs?.pct_cogs_pembanding != null ? ov.cogs.pct_cogs_pembanding + '%' : '—' }}</span>
                  </div>
                </div>
                <div class="mt-3 space-y-1 border-t border-rose-50 pt-2 text-xs">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-500">COGS Aktual</span>
                    <span class="font-semibold text-slate-800">{{ formatCurrency(ov.cogs?.cogs_aktual) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-500">COGS Foods (Stock Cut)</span>
                    <span class="font-semibold text-slate-800">{{ formatCurrency(ov.cogs?.cogs_foods) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-500">Cat Cost + Meal Emp</span>
                    <span class="font-semibold text-slate-800">{{ formatCurrency((ov.cogs?.category_cost || 0) + (ov.cogs?.meal_employees || 0)) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-500">COGS Pembanding</span>
                    <span class="font-semibold text-slate-800">{{ formatCurrency(ov.cogs?.cogs_pembanding) }}</span>
                  </div>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-500">Deviasi</span>
                    <span
                      class="font-semibold text-right"
                      :class="ov.cogs?.within_toleransi ? 'text-emerald-700' : 'text-rose-700'"
                    >
                      {{ formatCurrency(ov.cogs?.deviasi) }}
                      <span v-if="ov.cogs?.pct_deviasi != null">
                        ({{ ov.cogs.pct_deviasi > 0 ? '+' : '' }}{{ ov.cogs.pct_deviasi }}%)
                      </span>
                    </span>
                  </div>
                  <p class="text-[10px] text-slate-400 pt-0.5">
                    Max toleransi 2% revenue
                    <span v-if="ov.cogs?.toleransi_max_amount != null">· {{ formatCurrency(ov.cogs.toleransi_max_amount) }}</span>
                  </p>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-percent text-xl"></i>
              </div>
            </div>
          </div>

          <button
            type="button"
            class="rounded-3xl bg-white border border-sky-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('outlet_transfer')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 inline-flex items-center gap-1">
                  Transfer Outlet
                  <CardHelpTip :text="cardHelps.outlet_transfer" />
                </p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                  <div>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Transfer In</p>
                    <p class="text-xl font-bold text-emerald-700">{{ formatCurrency(ov.outlet_transfer_in) }}</p>
                  </div>
                  <div>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Transfer Out</p>
                    <p class="text-xl font-bold text-rose-700">{{ formatCurrency(ov.outlet_transfer_out) }}</p>
                  </div>
                </div>
                <p class="mt-2 text-sm text-slate-500">{{ ov.outlet_transfer_count || 0 }} transaksi</p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.outlet_transfer_in, true)">{{ vsLabel(vs.outlet_transfer_in) }}</p>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-right-left text-xl"></i>
              </div>
            </div>
          </button>

          <button
            type="button"
            class="rounded-3xl bg-white border border-violet-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('outlet_adjustment')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700 inline-flex items-center gap-1">
                  Adjustment
                  <CardHelpTip :text="cardHelps.outlet_adjustment" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.outlet_adjustment) }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ ov.outlet_adjustment_count || 0 }} transaksi · net value</p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.outlet_adjustment, true)">{{ vsLabel(vs.outlet_adjustment) }}</p>
                <div v-if="(ov.outlet_adjustment_by_warehouse || []).length" class="mt-3 space-y-1 border-t border-violet-50 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400">Per warehouse</p>
                  <div
                    v-for="row in ov.outlet_adjustment_by_warehouse"
                    :key="'adj-wh-' + row.warehouse_id"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-500 truncate">{{ row.warehouse_name }}</span>
                    <span class="font-semibold text-slate-700 shrink-0">{{ formatCurrency(row.amount) }}</span>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-violet-50 text-violet-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-sliders text-xl"></i>
              </div>
            </div>
          </button>

          <button
            type="button"
            class="rounded-3xl bg-white border border-orange-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('stock_opname')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-orange-700 inline-flex items-center gap-1">
                  Stock Opname
                  <CardHelpTip :text="cardHelps.stock_opname" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.stock_opname_cutoff) }}</p>
                <p class="mt-2 text-sm text-slate-500">
                  Koreksi tgl 1 tanpa IB · {{ ov.stock_opname_cutoff_count || 0 }} item
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ ov.stock_opname_count || 0 }} dokumen periode · net
                  {{ formatCurrency(ov.stock_opname_period_net) }}
                  <span class="text-slate-400">(balancing, tidak masuk formula)</span>
                </p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.stock_opname_cutoff, true)">{{ vsLabel(vs.stock_opname_cutoff) }}</p>
                <div v-if="(ov.stock_opname_cutoff_by_warehouse || []).length" class="mt-3 space-y-1 border-t border-orange-50 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400">Cutoff per warehouse</p>
                  <div
                    v-for="row in ov.stock_opname_cutoff_by_warehouse"
                    :key="'op-wh-' + row.warehouse_id"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-500 truncate">{{ row.warehouse_name }}</span>
                    <span class="font-semibold text-slate-700 shrink-0">{{ formatCurrency(row.amount) }}</span>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-clipboard-check text-xl"></i>
              </div>
            </div>
          </button>

          <button
            type="button"
            class="rounded-3xl bg-white border border-cyan-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('internal_warehouse_transfer')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700 inline-flex items-center gap-1">
                  Internal WH Transfer
                  <CardHelpTip :text="cardHelps.internal_warehouse_transfer" />
                </p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(ov.internal_warehouse_transfer_total) }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ ov.internal_warehouse_transfer_count || 0 }} transaksi</p>
                <div v-if="(ov.internal_warehouse_transfer_flows || []).length" class="mt-3 space-y-1 border-t border-cyan-50 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400">Per alur gudang</p>
                  <div
                    v-for="(row, idx) in ov.internal_warehouse_transfer_flows"
                    :key="'iwt-flow-' + idx"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-500 truncate">{{ row.from_warehouse_name }} → {{ row.to_warehouse_name }}</span>
                    <span class="font-semibold text-slate-700 shrink-0">{{ formatCurrency(row.amount) }}</span>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-cyan-50 text-cyan-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-arrows-turn-right text-xl"></i>
              </div>
            </div>
          </button>

          <button
            type="button"
            class="rounded-3xl bg-white border border-lime-100 shadow-sm p-5 text-left hover:shadow-md transition"
            @click="openCard('outlet_wip')"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-lime-700 inline-flex items-center gap-1">
                  WIP
                  <CardHelpTip :text="cardHelps.outlet_wip" />
                </p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                  <div>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Cost Bahan</p>
                    <p class="text-lg font-bold text-slate-900">{{ formatCurrency(ov.wip_material_cost) }}</p>
                  </div>
                  <div>
                    <p class="text-[10px] uppercase tracking-wide text-slate-400">Barang Jadi</p>
                    <p class="text-lg font-bold text-slate-900">{{ formatCurrency(ov.wip_finished_cost) }}</p>
                  </div>
                </div>
                <p class="mt-2 text-sm text-slate-500">{{ ov.wip_count || 0 }} produksi</p>
                <p class="mt-1 text-xs font-medium" :class="vsClass(vs.wip_finished_cost, true)">{{ vsLabel(vs.wip_finished_cost) }}</p>
                <div v-if="(ov.wip_by_warehouse || []).length" class="mt-3 space-y-1 border-t border-lime-50 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400">Per warehouse</p>
                  <div
                    v-for="row in ov.wip_by_warehouse"
                    :key="'wip-wh-' + row.warehouse_id"
                    class="text-xs space-y-0.5"
                  >
                    <div class="flex items-center justify-between gap-2">
                      <span class="text-slate-500 truncate font-medium">{{ row.warehouse_name }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 pl-1 text-slate-500">
                      <span>Bahan</span>
                      <span class="font-semibold text-slate-700">{{ formatCurrency(row.material_cost) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 pl-1 text-slate-500">
                      <span>Jadi</span>
                      <span class="font-semibold text-slate-700">{{ formatCurrency(row.finished_cost) }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-lime-50 text-lime-700 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-flask text-xl"></i>
              </div>
            </div>
          </button>
        </div>
        </section>

        <!-- ========== Group: Trend & Analytics ========== -->
        <section class="mb-8">
          <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
              <i class="fa-solid fa-chart-area text-sm"></i>
            </span>
            <div>
              <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-indigo-700">Trend &amp; Analytics</h2>
              <p class="text-xs text-slate-500">Revenue vs spend, mix, dan snapshot harian</p>
            </div>
          </div>

        <!-- Charts -->
        <div v-if="sectionLoading.charts" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-16 mb-4 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat chart…
        </div>
        <template v-else>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
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

        <div class="grid grid-cols-1 gap-4 mb-6">
          <div class="rounded-3xl bg-white border border-slate-100 shadow-sm p-5">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Spend by Source (Daily)</h2>
            <p class="text-xs text-slate-500 mb-4">GSR/RO · Retail Food · Retail Non Food</p>
            <apexchart type="bar" height="320" :options="stackOptions" :series="stackSeries" />
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
          <div class="rounded-3xl bg-white border border-slate-100 shadow-sm p-5 xl:col-span-2">
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
        </section>

        <!-- ========== Group: Metode Pembayaran ========== -->
        <section class="mb-8">
          <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-slate-200 text-slate-700">
              <i class="fa-solid fa-credit-card text-sm"></i>
            </span>
            <div>
              <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-slate-700">Metode Pembayaran</h2>
              <p class="text-xs text-slate-500">Share dan rincian payment code</p>
            </div>
          </div>

        <!-- Payment methods -->
        <div v-if="sectionLoading.payments" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-12 text-center text-slate-400 text-sm">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat metode pembayaran…
        </div>
        <div v-else class="grid grid-cols-1 xl:grid-cols-3 gap-4">
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
        </section>

        <!-- ========== Group: Absensi (paling bawah) ========== -->
        <section class="mb-8">
          <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
              <i class="fa-solid fa-user-clock text-sm"></i>
            </span>
            <div>
              <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-violet-700">Absensi</h2>
              <p class="text-xs text-slate-500">Periode payroll 26–25 · overtime, telat, leave</p>
            </div>
          </div>

          <div v-if="sectionLoading.attendance" class="rounded-3xl bg-white border border-slate-100 shadow-sm py-10 text-center text-slate-400 text-sm">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat data absensi…
          </div>
          <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <button
              type="button"
              class="rounded-3xl bg-white border border-violet-100 shadow-sm p-5 text-left hover:shadow-md transition"
              @click="openAttendanceModal('overtime')"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-semibold uppercase tracking-wide text-violet-600 inline-flex items-center gap-1">
                    Employee Overtime
                    <CardHelpTip :text="cardHelps.employee_overtime" />
                  </p>
                  <div class="mt-3 grid grid-cols-2 gap-3">
                    <div>
                      <p class="text-[10px] uppercase tracking-wide text-slate-400">OT Submission</p>
                      <p class="text-lg font-bold text-teal-700">{{ formatDecimal(att.overtime?.submission_hours) }} jam</p>
                      <p class="text-xs font-semibold text-teal-800">{{ formatCurrency(att.overtime?.submission_amount) }}</p>
                      <p class="mt-1.5 text-[10px] uppercase tracking-wide text-slate-400">Rata-rata / karyawan</p>
                      <p class="text-sm font-semibold text-teal-700">{{ formatDecimal(att.overtime?.avg_submission_hours) }} jam</p>
                      <p class="text-[11px] font-medium text-teal-800">{{ formatCurrency(att.overtime?.avg_submission_amount) }}</p>
                    </div>
                    <div>
                      <p class="text-[10px] uppercase tracking-wide text-slate-400">OT Real</p>
                      <p class="text-lg font-bold text-violet-700">{{ formatNumber(att.overtime?.real_hours) }} jam</p>
                      <p class="text-xs font-semibold text-violet-800">{{ formatCurrency(att.overtime?.real_amount) }}</p>
                      <p class="mt-1.5 text-[10px] uppercase tracking-wide text-slate-400">Rata-rata / karyawan</p>
                      <p class="text-sm font-semibold text-violet-700">{{ formatDecimal(att.overtime?.avg_real_hours) }} jam</p>
                      <p class="text-[11px] font-medium text-violet-800">{{ formatCurrency(att.overtime?.avg_real_amount) }}</p>
                    </div>
                  </div>
                  <p class="mt-2 text-[11px] text-slate-400">
                    {{ att.overtime?.employee_count || 0 }} karyawan
                  </p>
                </div>
                <span class="text-violet-400 text-xs mt-1 shrink-0">Detail →</span>
              </div>
            </button>

            <button
              type="button"
              class="rounded-3xl bg-white border border-orange-100 shadow-sm p-5 text-left hover:shadow-md transition"
              @click="openAttendanceModal('late')"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wide text-orange-600 inline-flex items-center gap-1">
                    Telat Absen
                    <CardHelpTip :text="cardHelps.late_absen" />
                  </p>
                  <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(att.late?.total_minutes) }}</p>
                  <p class="mt-1 text-sm text-slate-500">menit · {{ att.late?.employee_count || 0 }} karyawan</p>
                </div>
                <span class="text-orange-400 text-xs mt-1 shrink-0">Detail →</span>
              </div>
            </button>

            <button
              type="button"
              class="rounded-3xl bg-white border border-cyan-100 shadow-sm p-5 text-left hover:shadow-md transition"
              @click="openAttendanceModal('leave')"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-semibold uppercase tracking-wide text-cyan-600 inline-flex items-center gap-1">
                    Leave
                    <CardHelpTip :text="cardHelps.leave" />
                  </p>
                  <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatNumber(att.leave?.total_days) }}</p>
                  <p class="mt-1 text-sm text-slate-500">hari cuti / izin</p>
                  <div v-if="(att.leave?.types || []).length" class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5">
                    <div v-for="row in att.leave.types" :key="row.leave_type_id" class="min-w-0">
                      <p class="text-[10px] uppercase tracking-wide text-slate-400 truncate">{{ row.name }}</p>
                      <p class="text-xs font-semibold text-slate-700">{{ formatNumber(row.days) }} hari</p>
                    </div>
                  </div>
                </div>
                <span class="text-cyan-400 text-xs mt-1 shrink-0">Detail →</span>
              </div>
            </button>
          </div>
        </section>

        <!-- Quick links -->
        <div class="flex flex-wrap gap-3">
          <a href="/report-daily-outlet-revenue" class="px-4 py-2 rounded-xl bg-white border border-sky-200 text-sky-700 text-sm font-medium hover:bg-sky-50">Daily Revenue</a>
          <a href="/report-receiving-sheet" class="px-4 py-2 rounded-xl bg-white border border-amber-200 text-amber-700 text-sm font-medium hover:bg-amber-50">Receiving Sheet</a>
          <a :href="pettyCashHref" class="px-4 py-2 rounded-xl bg-white border border-emerald-200 text-emerald-700 text-sm font-medium hover:bg-emerald-50">Petty Cash Report</a>
          <a href="/stock-cut" class="px-4 py-2 rounded-xl bg-white border border-fuchsia-200 text-fuchsia-700 text-sm font-medium hover:bg-fuchsia-50">Stock Cut</a>
          <a href="/outlet-internal-use-waste/report-universal" class="px-4 py-2 rounded-xl bg-white border border-teal-200 text-teal-700 text-sm font-medium hover:bg-teal-50">Category Cost</a>
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
      <div
        class="bg-white rounded-3xl shadow-2xl w-full max-h-[88vh] overflow-hidden flex flex-col"
        :class="['revenue', 'total_spend', 'category_cost', 'mcs_purchase', 'purchase_category', 'begin_inventory', 'ending_inventory', 'outlet_transfer', 'outlet_adjustment', 'stock_opname', 'internal_warehouse_transfer', 'outlet_wip'].includes(modalType) ? 'max-w-7xl' : 'max-w-5xl'"
      >
        <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-xl font-bold text-slate-900">{{ modalTitle }}</h3>
            <p class="text-sm text-slate-500">{{ periodLabel || `${filters.date_from} s/d ${filters.date_to}` }}</p>
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
            <apexchart v-if="!['begin_inventory', 'ending_inventory', 'outlet_transfer', 'outlet_adjustment', 'stock_opname', 'internal_warehouse_transfer', 'outlet_wip'].includes(modalType)" type="area" height="220" :options="modalTrendOptions" :series="modalTrendSeries" />

            <!-- Begin Inventory: group by category, expand/collapse + search -->
            <template v-if="modalType === 'begin_inventory'">
              <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="relative flex-1">
                  <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                  <input
                    v-model="modalSearch"
                    type="search"
                    placeholder="Cari item, SKU, kategori, gudang..."
                    class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200"
                    @input="queueBeginInventorySearch"
                  />
                </div>
                <div class="flex items-center gap-2 text-xs">
                  <button type="button" class="px-3 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" @click="expandAllBeginCategories">
                    Expand all
                  </button>
                  <button type="button" class="px-3 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" @click="collapseAllBeginCategories">
                    Collapse all
                  </button>
                </div>
              </div>
              <p class="text-xs text-slate-500">
                Total MAC: <span class="font-semibold text-slate-700">{{ formatCurrency(modalSheetMeta?.total_value) }}</span>
                · {{ beginInventoryGroupCount }} kategori · {{ beginInventoryItemCount }} baris
                · Sumber: {{ beginInventorySourceLabel(modalSheetMeta?.source) }}
              </p>

              <div v-if="!(modalSheetMeta?.groups || []).length" class="py-12 text-center text-slate-400 text-sm">
                Tidak ada item begin inventory.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="group in (modalSheetMeta?.groups || [])"
                  :key="group.category"
                  class="rounded-2xl border border-slate-200 overflow-hidden"
                >
                  <button
                    type="button"
                    class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-slate-50 hover:bg-slate-100 text-left"
                    @click="toggleBeginCategory(group.category)"
                  >
                    <div class="min-w-0 flex items-center gap-2">
                      <i
                        class="fa-solid text-slate-400 text-xs"
                        :class="expandedBeginCategories[group.category] ? 'fa-chevron-down' : 'fa-chevron-right'"
                      ></i>
                      <span class="font-semibold text-slate-800 truncate">{{ group.category }}</span>
                      <span class="text-xs text-slate-500 shrink-0">{{ group.item_count }} item</span>
                    </div>
                    <span class="font-semibold text-indigo-700 shrink-0">{{ formatCurrency(group.total_value) }}</span>
                  </button>
                  <div v-if="expandedBeginCategories[group.category]" class="overflow-x-auto border-t border-slate-100">
                    <table class="min-w-full text-sm">
                      <thead class="bg-white">
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                          <th class="px-4 py-2.5 font-semibold">Item</th>
                          <th class="px-4 py-2.5 font-semibold">SKU</th>
                          <th class="px-4 py-2.5 font-semibold">Gudang</th>
                          <th class="px-4 py-2.5 font-semibold text-right">Qty</th>
                          <th class="px-4 py-2.5 font-semibold text-right">MAC</th>
                          <th class="px-4 py-2.5 font-semibold text-right">Value</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(item, idx) in group.items"
                          :key="group.category + '-' + idx"
                          class="border-t border-slate-50"
                        >
                          <td class="px-4 py-2 font-medium text-slate-800">{{ item.item_name }}</td>
                          <td class="px-4 py-2 text-slate-500">{{ item.item_sku || '—' }}</td>
                          <td class="px-4 py-2 text-slate-600">{{ item.warehouse_name || '—' }}</td>
                          <td class="px-4 py-2 text-right text-slate-700">{{ formatDecimal(item.qty) }}</td>
                          <td class="px-4 py-2 text-right text-slate-700">{{ formatCurrency(item.mac) }}</td>
                          <td class="px-4 py-2 text-right font-semibold text-slate-900">{{ formatCurrency(item.value) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </template>

            <!-- Ending Inventory: per warehouse → category expand + filter/search -->
            <template v-else-if="modalType === 'ending_inventory'">
              <div class="rounded-2xl border border-amber-100 bg-amber-50/40 px-4 py-3 text-xs text-slate-600 space-y-1.5">
                <p class="font-semibold text-amber-800">Formula vs stok</p>
                <p>
                  Begin {{ formatCurrency(modalSheetMeta?.formula?.begin) }}
                  + Purchased {{ formatCurrency(modalSheetMeta?.formula?.purchased) }}
                  <template v-if="(modalSheetMeta?.formula?.outlet_transfer_net || 0) !== 0">
                    {{ (modalSheetMeta?.formula?.outlet_transfer_net || 0) >= 0 ? '+' : '−' }}
                    Xfer {{ formatCurrency(Math.abs(modalSheetMeta?.formula?.outlet_transfer_net || 0)) }}
                  </template>
                  <template v-if="(modalSheetMeta?.formula?.outlet_adjustment || 0) !== 0">
                    {{ (modalSheetMeta?.formula?.outlet_adjustment || 0) >= 0 ? '+' : '−' }}
                    Adj {{ formatCurrency(Math.abs(modalSheetMeta?.formula?.outlet_adjustment || 0)) }}
                  </template>
                  − Stock Cut {{ formatCurrency(modalSheetMeta?.formula?.stock_cut) }}
                  − Category Cost {{ formatCurrency(modalSheetMeta?.formula?.category_cost) }}
                  =
                  <span class="font-bold text-slate-900">{{ formatCurrency(modalSheetMeta?.formula?.ending ?? modalSheetMeta?.formula?.formula_ending) }}</span>
                </p>
                <p v-if="(modalSheetMeta?.formula?.opname || 0) !== 0" class="text-amber-700">
                  Opname periode {{ formatCurrency(modalSheetMeta?.formula?.opname) }}
                  (balancing fisik — tidak masuk formula buku; selisih formula vs stok yang di-rapikan opname EOM / tgl 1)
                </p>
                <p>
                  Detail list = stok kartu as-of {{ modalSheetMeta?.as_of || filters.date_to }}
                  (qty≈0 + value yatim diabaikan{{ modalSheetMeta?.orphan_skipped ? ` · ${modalSheetMeta.orphan_skipped} item` : '' }})
                  =
                  <span class="font-bold text-slate-900">{{ formatCurrency(modalSheetMeta?.total_value) }}</span>
                  <span v-if="endingStockVariance != null" class="ml-1">
                    · selisih
                    <span :class="endingStockVariance === 0 ? 'text-emerald-700' : 'text-rose-700 font-semibold'">
                      {{ formatCurrency(endingStockVariance) }}
                    </span>
                  </span>
                </p>
              </div>

              <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="relative flex-1">
                  <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                  <input
                    v-model="modalSearch"
                    type="search"
                    placeholder="Cari item, SKU, kategori, gudang..."
                    class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-200"
                    @input="queueEndingInventorySearch"
                  />
                </div>
                <select
                  v-model="endingWarehouseFilter"
                  class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-200 min-w-[180px]"
                  @change="onEndingWarehouseFilterChange"
                >
                  <option value="">Semua warehouse</option>
                  <option
                    v-for="opt in (modalSheetMeta?.warehouse_options || [])"
                    :key="'end-opt-' + opt.id"
                    :value="String(opt.id)"
                  >
                    {{ opt.name }}
                  </option>
                </select>
                <div class="flex items-center gap-2 text-xs">
                  <button type="button" class="px-3 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" @click="expandAllEndingCategories">
                    Expand all
                  </button>
                  <button type="button" class="px-3 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" @click="collapseAllEndingCategories">
                    Collapse all
                  </button>
                </div>
              </div>

              <div v-if="!(modalSheetMeta?.warehouses || []).length" class="py-12 text-center text-slate-400 text-sm">
                Tidak ada data ending inventory.
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="wh in (modalSheetMeta?.warehouses || [])"
                  :key="'end-wh-block-' + wh.warehouse_id"
                  class="rounded-2xl border border-slate-200 overflow-hidden"
                >
                  <div class="flex items-center justify-between gap-3 px-4 py-3 bg-amber-50/80 border-b border-amber-100">
                    <div class="min-w-0">
                      <p class="font-bold text-slate-900 truncate">{{ wh.warehouse_name }}</p>
                      <p class="text-xs text-slate-500">
                        {{ wh.item_count }} item · {{ (wh.categories || []).length }} kategori
                        · formula {{ formatCurrency(wh.formula_ending) }}
                        <span v-if="wh.variance != null && wh.variance !== 0" class="text-rose-600">
                          · selisih {{ formatCurrency(wh.variance) }}
                        </span>
                      </p>
                    </div>
                    <div class="text-right shrink-0">
                      <p class="text-[10px] uppercase tracking-wide text-amber-700/80">Stok kartu</p>
                      <span class="font-bold text-amber-800">{{ formatCurrency(wh.total_value) }}</span>
                    </div>
                  </div>
                  <div class="divide-y divide-slate-100">
                    <div v-for="group in (wh.categories || [])" :key="wh.warehouse_id + '-' + group.category">
                      <button
                        type="button"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-white hover:bg-slate-50 text-left"
                        @click="toggleEndingCategory(wh.warehouse_id, group.category)"
                      >
                        <div class="min-w-0 flex items-center gap-2">
                          <i
                            class="fa-solid text-slate-400 text-xs"
                            :class="isEndingCategoryExpanded(wh.warehouse_id, group.category) ? 'fa-chevron-down' : 'fa-chevron-right'"
                          ></i>
                          <span class="font-semibold text-slate-800 truncate">{{ group.category }}</span>
                          <span class="text-xs text-slate-500 shrink-0">{{ group.item_count }} item</span>
                        </div>
                        <span class="font-semibold text-slate-700 shrink-0">{{ formatCurrency(group.total_value) }}</span>
                      </button>
                      <div v-if="isEndingCategoryExpanded(wh.warehouse_id, group.category)" class="overflow-x-auto border-t border-slate-50 bg-slate-50/40">
                        <table class="min-w-full text-sm">
                          <thead class="bg-white">
                            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide">
                              <th class="px-4 py-2.5 font-semibold">Item</th>
                              <th class="px-4 py-2.5 font-semibold">SKU</th>
                              <th class="px-4 py-2.5 font-semibold text-right">Qty</th>
                              <th class="px-4 py-2.5 font-semibold text-right">MAC</th>
                              <th class="px-4 py-2.5 font-semibold text-right">Value</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="(item, idx) in group.items"
                              :key="wh.warehouse_id + '-' + group.category + '-' + idx"
                              class="border-t border-slate-50"
                            >
                              <td class="px-4 py-2 font-medium text-slate-800">{{ item.item_name }}</td>
                              <td class="px-4 py-2 text-slate-500">{{ item.item_sku || '—' }}</td>
                              <td class="px-4 py-2 text-right text-slate-700">{{ formatDecimal(item.qty) }}</td>
                              <td class="px-4 py-2 text-right text-slate-700">{{ formatCurrency(item.mac) }}</td>
                              <td class="px-4 py-2 text-right font-semibold text-slate-900">{{ formatCurrency(item.value) }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <div v-if="!(wh.categories || []).length" class="px-4 py-6 text-center text-slate-400 text-sm">
                      Tidak ada item stok untuk warehouse ini.
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- Inventory movement cards: transfer / adjustment / IWT / WIP -->
            <template v-else-if="['outlet_transfer', 'outlet_adjustment', 'stock_opname', 'internal_warehouse_transfer', 'outlet_wip'].includes(modalType)">
              <div v-if="invTxnDetail" class="space-y-4">
                <button
                  type="button"
                  class="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900"
                  @click="closeInvTxnDetail"
                >
                  <i class="fa-solid fa-arrow-left"></i> Kembali ke daftar transaksi
                </button>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm space-y-1">
                  <p class="font-bold text-slate-900">{{ invTxnDetail.transaction?.number || ('#' + invTxnDetail.transaction?.id) }}</p>
                  <p class="text-slate-600">
                    {{ invTxnDetail.transaction?.date }}
                    <span v-if="invTxnDetail.transaction?.created_by"> · {{ invTxnDetail.transaction.created_by }}</span>
                    <span v-if="invTxnDetail.transaction?.status"> · {{ invTxnDetail.transaction.status }}</span>
                  </p>
                  <p v-if="modalType === 'outlet_transfer'" class="text-slate-600">
                    {{ invTxnDetail.transaction?.from_outlet }} / {{ invTxnDetail.transaction?.from_warehouse }}
                    → {{ invTxnDetail.transaction?.to_outlet }} / {{ invTxnDetail.transaction?.to_warehouse }}
                  </p>
                  <p v-else-if="modalType === 'internal_warehouse_transfer'" class="text-slate-600">
                    {{ invTxnDetail.transaction?.from_warehouse }} → {{ invTxnDetail.transaction?.to_warehouse }}
                  </p>
                  <p v-else-if="modalType === 'outlet_wip'" class="text-slate-600">
                    {{ invTxnDetail.transaction?.warehouse_name }}
                    · Bahan {{ formatCurrency(invTxnDetail.transaction?.material_cost) }}
                    · Jadi {{ formatCurrency(invTxnDetail.transaction?.finished_cost) }}
                  </p>
                  <p v-else-if="modalType === 'stock_opname'" class="text-slate-600">
                    {{ invTxnDetail.transaction?.warehouse_name }}
                    · Net {{ formatCurrency(invTxnDetail.transaction?.amount) }}
                    · Saldo kartu {{ formatCurrency(invTxnDetail.transaction?.saldo_sum) }}
                    <span v-if="invTxnDetail.transaction?.is_day1" class="ml-1 inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-orange-800">Tgl 1</span>
                    <span v-if="invTxnDetail.transaction?.notes" class="block text-slate-500 mt-1">{{ invTxnDetail.transaction.notes }}</span>
                  </p>
                  <p v-else class="text-slate-600">
                    {{ invTxnDetail.transaction?.warehouse_name }}
                    · {{ formatCurrency(invTxnDetail.transaction?.amount) }}
                  </p>
                </div>
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                  <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                      <tr>
                        <th class="px-4 py-2.5 text-left">Item</th>
                        <th class="px-4 py-2.5 text-left">SKU</th>
                        <th v-if="modalType === 'outlet_wip'" class="px-4 py-2.5 text-left">Role</th>
                        <th class="px-4 py-2.5 text-right">Qty In</th>
                        <th class="px-4 py-2.5 text-right">Qty Out</th>
                        <th class="px-4 py-2.5 text-right">Cost</th>
                        <th class="px-4 py-2.5 text-right">Value</th>
                        <th v-if="modalType === 'stock_opname'" class="px-4 py-2.5 text-right">Saldo</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in (invTxnDetail.items || [])" :key="'inv-item-' + item.id" class="border-t border-slate-100">
                        <td class="px-4 py-2 font-medium text-slate-800">{{ item.item_name }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ item.sku }}</td>
                        <td v-if="modalType === 'outlet_wip'" class="px-4 py-2 text-slate-600 capitalize">{{ item.role }}</td>
                        <td class="px-4 py-2 text-right">{{ formatDecimal(item.qty_in ?? item.qty_small ?? 0) }}</td>
                        <td class="px-4 py-2 text-right">{{ formatDecimal(item.qty_out || 0) }}</td>
                        <td class="px-4 py-2 text-right">{{ formatCurrency(item.cost_per_small) }}</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ formatCurrency(item.amount) }}</td>
                        <td v-if="modalType === 'stock_opname'" class="px-4 py-2 text-right">{{ formatCurrency(item.saldo_value) }}</td>
                      </tr>
                      <tr v-if="!(invTxnDetail.items || []).length">
                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">Tidak ada item</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-else class="space-y-4">
                <div class="relative">
                  <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                  <input
                    v-model="modalSearch"
                    type="search"
                    placeholder="Cari nomor, outlet, warehouse, user..."
                    class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fuchsia-200"
                    @input="queueInvTxnSearch"
                  />
                </div>
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                  <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                      <tr>
                        <th class="px-4 py-2.5 text-left">Tanggal</th>
                        <th class="px-4 py-2.5 text-left">Nomor</th>
                        <th class="px-4 py-2.5 text-left">{{ invTxnPartyLabel }}</th>
                        <th class="px-4 py-2.5 text-left">User</th>
                        <th v-if="modalType === 'outlet_wip'" class="px-4 py-2.5 text-right">Bahan</th>
                        <th v-if="modalType === 'outlet_wip'" class="px-4 py-2.5 text-right">Jadi</th>
                        <th v-if="modalType === 'outlet_transfer'" class="px-4 py-2.5 text-right">In</th>
                        <th v-if="modalType === 'outlet_transfer'" class="px-4 py-2.5 text-right">Out</th>
                        <th v-if="modalType !== 'outlet_wip' && modalType !== 'outlet_transfer'" class="px-4 py-2.5 text-right">Nilai</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="txn in modalTxns"
                        :key="'inv-txn-' + txn.id"
                        class="border-t border-slate-100 hover:bg-fuchsia-50/40 cursor-pointer"
                        @click="openInvTxnDetail(txn.id)"
                      >
                        <td class="px-4 py-2.5 text-slate-700">
                          {{ txn.date }}
                          <span
                            v-if="modalType === 'stock_opname' && txn.is_day1"
                            class="ml-1 inline-flex rounded-full bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-orange-800"
                          >Tgl 1</span>
                        </td>
                        <td class="px-4 py-2.5 font-semibold text-slate-900">{{ txn.number }}</td>
                        <td class="px-4 py-2.5 text-slate-600">
                          <template v-if="modalType === 'outlet_transfer'">
                            {{ txn.from_outlet }} → {{ txn.to_outlet }}
                          </template>
                          <template v-else-if="modalType === 'internal_warehouse_transfer'">
                            {{ txn.from_warehouse }} → {{ txn.to_warehouse }}
                          </template>
                          <template v-else>
                            {{ txn.warehouse_name || '-' }}
                            <span v-if="modalType === 'stock_opname' && txn.notes" class="block text-xs text-slate-400 truncate max-w-[14rem]">{{ txn.notes }}</span>
                          </template>
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ txn.created_by || '-' }}</td>
                        <td v-if="modalType === 'outlet_wip'" class="px-4 py-2.5 text-right font-semibold">{{ formatCurrency(txn.material_cost) }}</td>
                        <td v-if="modalType === 'outlet_wip'" class="px-4 py-2.5 text-right font-semibold">{{ formatCurrency(txn.finished_cost) }}</td>
                        <td v-if="modalType === 'outlet_transfer'" class="px-4 py-2.5 text-right text-emerald-700 font-semibold">{{ formatCurrency(txn.value_in) }}</td>
                        <td v-if="modalType === 'outlet_transfer'" class="px-4 py-2.5 text-right text-rose-700 font-semibold">{{ formatCurrency(txn.value_out) }}</td>
                        <td v-if="modalType !== 'outlet_wip' && modalType !== 'outlet_transfer'" class="px-4 py-2.5 text-right font-semibold">{{ formatCurrency(txn.amount) }}</td>
                      </tr>
                      <tr v-if="!modalTxns.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">Tidak ada transaksi</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>

            <!-- Revenue: daily list seperti Daily Outlet Revenue -->
            <template v-else-if="modalType === 'revenue'">
              <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="min-w-full text-xs">
                  <thead>
                    <tr class="bg-slate-900 text-white">
                      <th class="px-3 py-2 text-center border-r border-slate-700" rowspan="2">Tanggal</th>
                      <th class="px-3 py-2 text-center border-r border-slate-700" rowspan="2">Hari</th>
                      <th class="px-3 py-2 text-center border-r border-emerald-700 bg-emerald-800" colspan="4">Lunch</th>
                      <th class="px-3 py-2 text-center border-r border-amber-700 bg-amber-800" colspan="4">Dinner</th>
                      <th class="px-3 py-2 text-center bg-indigo-800" colspan="4">Total</th>
                    </tr>
                    <tr class="bg-slate-800 text-slate-200">
                      <th v-for="(h, idx) in revenueSubHeaders" :key="'rev-sub-' + idx" class="px-2 py-1.5 text-center border-r border-slate-700 font-medium">{{ h }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="row in modalTxns"
                      :key="row.id"
                      class="border-t border-slate-100"
                      :class="row.is_weekend ? 'bg-rose-50/40' : 'bg-white'"
                    >
                      <td class="px-3 py-2.5 text-center font-semibold text-slate-800 border-r border-slate-100">{{ formatShortDate(row.date) }}</td>
                      <td class="px-3 py-2.5 text-center text-slate-700 border-r border-slate-100">{{ row.day_name }}</td>
                      <td class="px-2 py-2.5 text-center border-r border-slate-100">{{ formatNumber(row.lunch_cover) }}</td>
                      <td class="px-2 py-2.5 text-right border-r border-slate-100">{{ formatCurrency(row.lunch_revenue) }}</td>
                      <td class="px-2 py-2.5 text-right border-r border-slate-100">{{ formatCurrency(row.lunch_avg_check) }}</td>
                      <td class="px-2 py-2.5 text-right border-r border-slate-100">{{ formatCurrency(row.lunch_disc) }}</td>
                      <td class="px-2 py-2.5 text-center border-r border-slate-100">{{ formatNumber(row.dinner_cover) }}</td>
                      <td class="px-2 py-2.5 text-right border-r border-slate-100">{{ formatCurrency(row.dinner_revenue) }}</td>
                      <td class="px-2 py-2.5 text-right border-r border-slate-100">{{ formatCurrency(row.dinner_avg_check) }}</td>
                      <td class="px-2 py-2.5 text-right border-r border-slate-100">{{ formatCurrency(row.dinner_disc) }}</td>
                      <td class="px-2 py-2.5 text-center font-semibold border-r border-slate-100">{{ formatNumber(row.total_cover) }}</td>
                      <td class="px-2 py-2.5 text-right font-semibold border-r border-slate-100">{{ formatCurrency(row.total_revenue) }}</td>
                      <td class="px-2 py-2.5 text-right font-semibold border-r border-slate-100">{{ formatCurrency(row.total_avg_check) }}</td>
                      <td class="px-2 py-2.5 text-right font-semibold">{{ formatCurrency(row.total_disc) }}</td>
                    </tr>
                    <tr v-if="modalTxns.length" class="bg-slate-900 text-white font-semibold border-t border-slate-700">
                      <td class="px-3 py-2.5 text-center" colspan="2">TOTAL</td>
                      <td class="px-2 py-2.5 text-center bg-emerald-900/50">{{ formatNumber(revenueModalTotals.lunch_cover) }}</td>
                      <td class="px-2 py-2.5 text-right bg-emerald-900/50">{{ formatCurrency(revenueModalTotals.lunch_revenue) }}</td>
                      <td class="px-2 py-2.5 text-right bg-emerald-900/50">{{ formatCurrency(revenueModalTotals.lunch_avg_check) }}</td>
                      <td class="px-2 py-2.5 text-right bg-emerald-900/50">{{ formatCurrency(revenueModalTotals.lunch_disc) }}</td>
                      <td class="px-2 py-2.5 text-center bg-amber-900/50">{{ formatNumber(revenueModalTotals.dinner_cover) }}</td>
                      <td class="px-2 py-2.5 text-right bg-amber-900/50">{{ formatCurrency(revenueModalTotals.dinner_revenue) }}</td>
                      <td class="px-2 py-2.5 text-right bg-amber-900/50">{{ formatCurrency(revenueModalTotals.dinner_avg_check) }}</td>
                      <td class="px-2 py-2.5 text-right bg-amber-900/50">{{ formatCurrency(revenueModalTotals.dinner_disc) }}</td>
                      <td class="px-2 py-2.5 text-center bg-indigo-900/50">{{ formatNumber(revenueModalTotals.total_cover) }}</td>
                      <td class="px-2 py-2.5 text-right bg-indigo-900/50">{{ formatCurrency(revenueModalTotals.total_revenue) }}</td>
                      <td class="px-2 py-2.5 text-right bg-indigo-900/50">{{ formatCurrency(revenueModalTotals.total_avg_check) }}</td>
                      <td class="px-2 py-2.5 text-right bg-indigo-900/50">{{ formatCurrency(revenueModalTotals.total_disc) }}</td>
                    </tr>
                    <tr v-if="!modalTxns.length">
                      <td colspan="14" class="px-4 py-10 text-center text-slate-400">Tidak ada data revenue</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="text-xs text-slate-500">{{ modalPagination.total }} hari · Lunch = s/d jam 17, Dinner = setelah jam 17</p>
            </template>

            <!-- Total Spend: daily ala Receiving Sheet (tanpa omzet) -->
            <template v-else-if="modalType === 'total_spend'">
              <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-sm">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="font-bold">
                      <th class="px-4 py-3 text-left bg-slate-600 text-white">No</th>
                      <th class="px-4 py-3 text-left bg-sky-600 text-white">Tanggal</th>
                      <th
                        v-for="wh in spendWarehouseColumns"
                        :key="'wh-h-' + wh.key"
                        class="px-4 py-3 text-right bg-indigo-600 text-white"
                      >
                        {{ wh.name }}
                      </th>
                      <th
                        v-for="sp in spendSuppliers"
                        :key="'sp-h-' + sp.id"
                        class="px-4 py-3 text-right bg-amber-600 text-white"
                      >
                        {{ sp.name }}
                      </th>
                      <th class="px-4 py-3 text-right bg-rose-600 text-white">Cost</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(row, index) in modalTxns"
                      :key="row.id"
                      class="border-b last:border-b-0"
                      :class="row.is_weekend ? 'bg-rose-50/50' : 'hover:bg-blue-50/60'"
                    >
                      <td class="px-4 py-3 bg-slate-50 text-slate-700">{{ index + 1 }}</td>
                      <td class="px-4 py-3 bg-sky-50 text-sky-900 font-medium">
                        {{ formatShortDate(row.date) }}
                        <span class="block text-[10px] text-slate-500 font-normal">{{ row.day_name }}</span>
                      </td>
                      <td
                        v-for="wh in spendWarehouseColumns"
                        :key="'wh-' + row.id + '-' + wh.key"
                        class="px-4 py-3 bg-indigo-50 text-indigo-900 text-right font-medium"
                      >
                        <button
                          v-if="Number(row[wh.key]) > 0"
                          type="button"
                          class="underline decoration-dotted underline-offset-2 hover:text-indigo-700"
                          @click="openSpendCellDetail('warehouse', wh.key, wh.name, row.date, row[wh.key])"
                        >
                          {{ formatCurrency(row[wh.key]) }}
                        </button>
                        <span v-else>{{ formatCurrency(0) }}</span>
                      </td>
                      <td
                        v-for="sp in spendSuppliers"
                        :key="'sp-' + row.id + '-' + sp.id"
                        class="px-4 py-3 bg-amber-50 text-amber-900 text-right font-medium"
                      >
                        <button
                          v-if="Number(row['supplier_' + sp.id]) > 0"
                          type="button"
                          class="underline decoration-dotted underline-offset-2 hover:text-amber-700"
                          @click="openSpendCellDetail('supplier', String(sp.id), sp.name, row.date, row['supplier_' + sp.id])"
                        >
                          {{ formatCurrency(row['supplier_' + sp.id]) }}
                        </button>
                        <span v-else>{{ formatCurrency(0) }}</span>
                      </td>
                      <td class="px-4 py-3 bg-rose-50 text-rose-900 text-right font-semibold">
                        {{ formatCurrency(row.total_spend) }}
                      </td>
                    </tr>
                    <tr v-if="modalTxns.length" class="border-t-2 border-slate-400 font-bold">
                      <td class="px-4 py-3 bg-slate-700 text-white" colspan="2">GRAND TOTAL</td>
                      <td
                        v-for="wh in spendWarehouseColumns"
                        :key="'gt-wh-' + wh.key"
                        class="px-4 py-3 bg-indigo-700 text-white text-right"
                      >
                        {{ formatCurrency(spendModalTotals.warehouses[wh.key] || 0) }}
                      </td>
                      <td
                        v-for="sp in spendSuppliers"
                        :key="'gt-sp-' + sp.id"
                        class="px-4 py-3 bg-amber-700 text-white text-right"
                      >
                        {{ formatCurrency(spendModalTotals.suppliers[sp.id] || 0) }}
                      </td>
                      <td class="px-4 py-3 bg-rose-700 text-white text-right">
                        {{ formatCurrency(spendModalTotals.total_spend) }}
                      </td>
                    </tr>
                    <tr v-if="!modalTxns.length">
                      <td :colspan="3 + spendWarehouseColumns.length + spendSuppliers.length" class="px-4 py-10 text-center text-slate-400">
                        Tidak ada data spend
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="text-xs text-slate-500">
                {{ modalPagination.total }} hari · klik nilai untuk melihat transaksi &amp; detail item
              </p>

              <div class="pt-2">
                <h3 class="text-sm font-semibold text-slate-800 mb-2">Retail Non Food</h3>
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                  <table class="min-w-full text-sm">
                    <thead class="bg-orange-50 text-orange-800">
                      <tr>
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">Sumber</th>
                        <th class="px-4 py-3 text-left">Nomor</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="txn in spendRetailNonFoodTxns"
                        :key="'rnf-' + txn.id"
                        class="border-t border-slate-100 cursor-pointer hover:bg-orange-50/70"
                        @click="openSourceTxnDetail(txn)"
                      >
                        <td class="px-4 py-2.5">{{ formatShortDate(txn.date) }}</td>
                        <td class="px-4 py-2.5">
                          <span class="px-2 py-0.5 rounded-lg bg-orange-50 text-orange-700 text-xs font-medium">{{ txn.source || 'Retail Non Food' }}</span>
                        </td>
                        <td class="px-4 py-2.5 font-medium text-slate-800 underline decoration-dotted underline-offset-2">{{ txn.number || '-' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ txn.creator_name || '-' }}</td>
                        <td class="px-4 py-2.5 text-slate-600">{{ txn.category_name || '-' }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold text-slate-900">{{ formatCurrency(txn.amount) }}</td>
                      </tr>
                      <tr v-if="spendRetailNonFoodTxns.length" class="bg-orange-900 text-white font-semibold border-t border-orange-700">
                        <td class="px-4 py-2.5" colspan="5">TOTAL</td>
                        <td class="px-4 py-2.5 text-right">{{ formatCurrency(spendRetailNonFoodTotal) }}</td>
                      </tr>
                      <tr v-if="!spendRetailNonFoodTxns.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Tidak ada transaksi Retail Non Food</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="text-xs text-slate-500 mt-2">{{ spendRetailNonFoodTxns.length }} transaksi · klik baris untuk melihat item yang dibeli</p>
              </div>
            </template>

            <!-- Stock Cut: nilai harian -->
            <template v-else-if="modalType === 'stock_cut'">
              <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="bg-fuchsia-800 text-white">
                      <th class="px-4 py-3 text-left">No</th>
                      <th class="px-4 py-3 text-left">Tanggal</th>
                      <th class="px-4 py-3 text-left">Hari</th>
                      <th class="px-4 py-3 text-right">Food</th>
                      <th class="px-4 py-3 text-right">Beverage</th>
                      <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(row, index) in modalTxns"
                      :key="row.id"
                      class="border-t border-slate-100"
                      :class="row.is_weekend ? 'bg-rose-50/40' : 'hover:bg-fuchsia-50/40'"
                    >
                      <td class="px-4 py-2.5 text-slate-600">{{ index + 1 }}</td>
                      <td class="px-4 py-2.5 font-medium text-slate-800">{{ formatShortDate(row.date) }}</td>
                      <td class="px-4 py-2.5 text-slate-600">{{ row.day_name }}</td>
                      <td class="px-4 py-2.5 text-right font-medium text-fuchsia-700">
                        <button
                          v-if="Number(row.food) > 0"
                          type="button"
                          class="underline decoration-dotted underline-offset-2 hover:text-fuchsia-900"
                          @click="openStockCutCellDetail('food', 'Food', row.date, row.food)"
                        >
                          {{ formatCurrency(row.food) }}
                        </button>
                        <span v-else>{{ formatCurrency(0) }}</span>
                      </td>
                      <td class="px-4 py-2.5 text-right font-medium text-fuchsia-700">
                        <button
                          v-if="Number(row.beverage) > 0"
                          type="button"
                          class="underline decoration-dotted underline-offset-2 hover:text-fuchsia-900"
                          @click="openStockCutCellDetail('beverage', 'Beverage', row.date, row.beverage)"
                        >
                          {{ formatCurrency(row.beverage) }}
                        </button>
                        <span v-else>{{ formatCurrency(0) }}</span>
                      </td>
                      <td class="px-4 py-2.5 text-right font-semibold text-fuchsia-800">
                        <button
                          v-if="Number(row.amount) > 0"
                          type="button"
                          class="underline decoration-dotted underline-offset-2 hover:text-fuchsia-950"
                          @click="openStockCutCellDetail('all', 'Semua', row.date, row.amount)"
                        >
                          {{ formatCurrency(row.amount) }}
                        </button>
                        <span v-else>{{ formatCurrency(0) }}</span>
                      </td>
                    </tr>
                    <tr v-if="modalTxns.length" class="bg-fuchsia-900 text-white font-semibold border-t border-fuchsia-700">
                      <td class="px-4 py-2.5" colspan="3">TOTAL</td>
                      <td class="px-4 py-2.5 text-right">{{ formatCurrency(stockCutModalTotals.food) }}</td>
                      <td class="px-4 py-2.5 text-right">{{ formatCurrency(stockCutModalTotals.beverage) }}</td>
                      <td class="px-4 py-2.5 text-right">{{ formatCurrency(stockCutModalTotals.amount) }}</td>
                    </tr>
                    <tr v-if="!modalTxns.length">
                      <td colspan="6" class="px-4 py-10 text-center text-slate-400">Tidak ada data stock cut</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="text-xs text-slate-500">
                {{ modalPagination.total }} hari · Food / Beverage dari type_filter &amp; warehouse · klik nilai untuk detail item
              </p>
            </template>

            <!-- Category Cost: harian per type + total -->
            <template v-else-if="modalType === 'category_cost'">
              <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="min-w-full text-xs">
                  <thead>
                    <tr class="bg-teal-800 text-white">
                      <th class="px-3 py-2.5 text-left sticky left-0 bg-teal-800 z-10">Tanggal</th>
                      <th class="px-3 py-2.5 text-left">Hari</th>
                      <th
                        v-for="col in categoryCostTypeColumns"
                        :key="'cc-h-' + col.key"
                        class="px-3 py-2.5 text-right whitespace-nowrap"
                      >
                        {{ col.label }}
                      </th>
                      <th class="px-3 py-2.5 text-right bg-teal-900">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="row in modalTxns"
                      :key="row.id"
                      class="border-t border-slate-100"
                      :class="row.is_weekend ? 'bg-rose-50/40' : 'hover:bg-teal-50/40'"
                    >
                      <td class="px-3 py-2 font-semibold text-slate-800 sticky left-0 bg-inherit z-10">{{ formatShortDate(row.date) }}</td>
                      <td class="px-3 py-2 text-slate-600">{{ row.day_name }}</td>
                      <td
                        v-for="col in categoryCostTypeColumns"
                        :key="'cc-' + row.id + '-' + col.key"
                        class="px-3 py-2 text-right text-slate-700"
                      >
                        <button
                          v-if="Number(row[col.key]) > 0"
                          type="button"
                          class="underline decoration-dotted underline-offset-2 hover:text-teal-700 font-medium"
                          @click="openCategoryCostCellDetail(col.key, col.label, row.date, row[col.key])"
                        >
                          {{ formatCurrency(row[col.key]) }}
                        </button>
                        <span v-else>{{ formatCurrency(0) }}</span>
                      </td>
                      <td class="px-3 py-2 text-right font-semibold text-teal-800">
                        <button
                          v-if="Number(row.total) > 0"
                          type="button"
                          class="underline decoration-dotted underline-offset-2 hover:text-teal-900"
                          @click="openCategoryCostCellDetail('all', 'Semua Type', row.date, row.total)"
                        >
                          {{ formatCurrency(row.total) }}
                        </button>
                        <span v-else>{{ formatCurrency(0) }}</span>
                      </td>
                    </tr>
                    <tr v-if="modalTxns.length" class="bg-teal-900 text-white font-semibold border-t border-teal-700">
                      <td class="px-3 py-2.5 sticky left-0 bg-teal-900 z-10" colspan="2">TOTAL</td>
                      <td
                        v-for="col in categoryCostTypeColumns"
                        :key="'cc-gt-' + col.key"
                        class="px-3 py-2.5 text-right"
                      >
                        {{ formatCurrency(categoryCostModalTotals.types[col.key] || 0) }}
                      </td>
                      <td class="px-3 py-2.5 text-right bg-teal-950">{{ formatCurrency(categoryCostModalTotals.total) }}</td>
                    </tr>
                    <tr v-if="!modalTxns.length">
                      <td :colspan="3 + categoryCostTypeColumns.length" class="px-4 py-10 text-center text-slate-400">
                        Tidak ada data category cost
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="text-xs text-slate-500">
                {{ modalPagination.total }} hari · dari Category Cost Outlet (subtotal MAC) · klik nilai untuk detail item
              </p>
            </template>

            <!-- Purchase / MCS: transaksi + items -->
            <template v-else-if="modalType === 'mcs_purchase' || modalType === 'purchase_category'">
              <div class="flex flex-wrap gap-2 items-end">
                <input
                  v-model="modalSearch"
                  type="text"
                  placeholder="Cari nomor / item / category / creator..."
                  class="flex-1 min-w-[200px] rounded-xl border-slate-200 text-sm"
                  @keyup.enter="fetchModal"
                />
                <button
                  v-if="mcsCategoryFilter"
                  type="button"
                  class="px-4 py-2 rounded-xl border border-amber-200 text-amber-700 text-sm"
                  @click="mcsCategoryFilter = ''; fetchModal()"
                >
                  Semua category
                </button>
                <button type="button" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm" @click="fetchModal">Cari</button>
              </div>

              <div class="space-y-3">
                <div
                  v-for="txn in modalTxns"
                  :key="txn.id"
                  class="rounded-2xl border border-amber-100 overflow-hidden bg-white"
                >
                  <button
                    type="button"
                    class="w-full px-4 py-3 flex items-center justify-between gap-3 text-left hover:bg-amber-50/60"
                    @click="toggleMcsTxn(txn.id)"
                  >
                    <div class="min-w-0">
                      <p class="font-semibold text-slate-900 truncate">{{ txn.number || '-' }}</p>
                      <p class="text-xs text-slate-500 mt-0.5">
                        {{ formatShortDate(txn.date) }}
                        · {{ txn.source || 'GR' }}
                        · {{ (txn.items || []).length }} item
                        <span v-if="txn.creator_name"> · {{ txn.creator_name }}</span>
                      </p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                      <span class="font-bold text-amber-700">{{ formatCurrency(txn.amount) }}</span>
                      <i
                        class="fa-solid text-slate-400"
                        :class="expandedMcsTxnIds[txn.id] ? 'fa-chevron-up' : 'fa-chevron-down'"
                      ></i>
                    </div>
                  </button>
                  <div v-if="expandedMcsTxnIds[txn.id]" class="border-t border-amber-100 bg-amber-50/30">
                    <table class="min-w-full text-xs">
                      <thead>
                        <tr class="text-slate-500">
                          <th class="px-4 py-2 text-left">Item</th>
                          <th class="px-4 py-2 text-left">Category</th>
                          <th class="px-4 py-2 text-right">Qty</th>
                          <th class="px-4 py-2 text-left">Unit</th>
                          <th class="px-4 py-2 text-right">Price</th>
                          <th class="px-4 py-2 text-right">Amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="(item, idx) in (txn.items || [])"
                          :key="txn.id + '-item-' + idx"
                          class="border-t border-amber-100/80"
                        >
                          <td class="px-4 py-2 font-medium text-slate-800">{{ item.item_name }}</td>
                          <td class="px-4 py-2 text-slate-600">{{ item.category }}</td>
                          <td class="px-4 py-2 text-right text-slate-700">{{ formatDecimal(item.qty) }}</td>
                          <td class="px-4 py-2 text-slate-600">{{ item.unit }}</td>
                          <td class="px-4 py-2 text-right text-slate-700">{{ formatCurrency(item.price) }}</td>
                          <td class="px-4 py-2 text-right font-semibold text-slate-900">{{ formatCurrency(item.amount) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div v-if="!modalTxns.length" class="py-10 text-center text-slate-400 text-sm">
                  Tidak ada transaksi pembelian
                </div>
              </div>
              <p class="text-xs text-slate-500">
                {{ modalPagination.total }} transaksi · GSR · Retail Food
                <span v-if="mcsCategoryFilter"> · filter {{ mcsCategoryFilter }}</span>
              </p>
            </template>

            <!-- Modal transaksi biasa -->
            <template v-else>
            <div class="flex flex-wrap gap-2 items-end">
              <input
                v-model="modalSearch"
                type="text"
                placeholder="Cari nomor / user / supplier..."
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
                    <template v-if="modalShowsUserSupplier">
                      <th class="px-4 py-3 text-left">User</th>
                      <th class="px-4 py-3 text-left">Supplier</th>
                    </template>
                    <template v-else-if="modalShowsUserCategory">
                      <th class="px-4 py-3 text-left">User</th>
                      <th class="px-4 py-3 text-left">Category</th>
                    </template>
                    <template v-else-if="modalShowsPettyParty">
                      <th class="px-4 py-3 text-left">User</th>
                      <th class="px-4 py-3 text-left">Supplier / Category</th>
                    </template>
                    <th v-else class="px-4 py-3 text-left">{{ modalPartyColumn }}</th>
                    <th class="px-4 py-3 text-right">{{ modalAmountLabel }}</th>
                    <th v-if="modalShowsBill" class="px-4 py-3 text-right">Bill</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="txn in modalTxns"
                    :key="txn.id + '-' + (txn.source || '')"
                    class="border-t border-slate-100"
                    :class="modalTxnClickable ? 'cursor-pointer hover:bg-sky-50/70' : ''"
                    @click="modalTxnClickable ? openSourceTxnDetail(txn) : null"
                  >
                    <td class="px-4 py-2.5">{{ formatShortDate(txn.date) }}</td>
                    <td class="px-4 py-2.5">
                      <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium">{{ txn.source || txn.type }}</span>
                    </td>
                    <td
                      class="px-4 py-2.5 font-medium text-slate-800"
                      :class="modalTxnClickable ? 'underline decoration-dotted underline-offset-2' : ''"
                    >
                      {{ txn.number || '-' }}
                    </td>
                    <template v-if="modalShowsUserSupplier">
                      <td class="px-4 py-2.5 text-slate-600">{{ txn.creator_name || '-' }}</td>
                      <td class="px-4 py-2.5 text-slate-600">{{ txn.supplier_name || '-' }}</td>
                    </template>
                    <template v-else-if="modalShowsUserCategory">
                      <td class="px-4 py-2.5 text-slate-600">{{ txn.creator_name || '-' }}</td>
                      <td class="px-4 py-2.5 text-slate-600">{{ txn.category_name || '-' }}</td>
                    </template>
                    <template v-else-if="modalShowsPettyParty">
                      <td class="px-4 py-2.5 text-slate-600">{{ txn.creator_name || '-' }}</td>
                      <td class="px-4 py-2.5 text-slate-600">
                        <span class="block text-[10px] uppercase tracking-wide text-slate-400">
                          {{ String(txn.source || '').startsWith('RF') ? 'Supplier' : 'Category' }}
                        </span>
                        {{ txn.party_label || txn.supplier_name || txn.category_name || '-' }}
                      </td>
                    </template>
                    <td v-else class="px-4 py-2.5 text-slate-600">
                      <template v-if="modalShowsPurchaseBucket">
                        <span class="block font-medium text-slate-800">{{ txn.warehouse || '-' }}</span>
                        <span class="text-xs text-slate-500">{{ txn.creator_name || '-' }}</span>
                      </template>
                      <template v-else>
                        {{ txn.beneficiary_name || txn.creator_name || txn.supplier_name || '-' }}
                      </template>
                    </td>
                    <td class="px-4 py-2.5 text-right font-semibold text-slate-900">{{ formatCurrency(txn.amount) }}</td>
                    <td v-if="modalShowsBill" class="px-4 py-2.5 text-right font-medium text-slate-700">{{ formatCurrency(txn.bill_amount) }}</td>
                  </tr>
                  <tr v-if="!modalTxns.length">
                    <td :colspan="modalTxnColspan" class="px-4 py-10 text-center text-slate-400">Tidak ada transaksi</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <p v-if="modalTxnClickable" class="text-xs text-slate-500">
              Klik baris transaksi untuk melihat detail item
              <span v-if="modalShowsPurchaseBucket && modalSheetMeta?.period_from">
                · periode {{ modalSheetMeta.period_from }} s/d {{ modalSheetMeta.period_to }}
                · total {{ formatCurrency(modalSheetMeta.purchased_total) }}
              </span>
            </p>

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
          </template>
        </div>
      </div>
    </div>

    <!-- Total Spend cell detail (nested) -->
    <div
      v-if="spendDetailOpen"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4"
      @click.self="closeSpendCellDetail"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">{{ spendDetailMeta.title }}</h2>
            <p class="text-sm text-slate-500 mt-1">
              {{ formatShortDate(spendDetailMeta.date) }}
              <span v-if="spendDetailMeta.amount != null"> · {{ formatCurrency(spendDetailMeta.amount) }}</span>
            </p>
          </div>
          <button type="button" class="text-slate-400 hover:text-slate-700 text-xl" @click="closeSpendCellDetail">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="px-6 py-4 overflow-y-auto flex-1">
          <div v-if="spendDetailLoading" class="py-12 text-center text-slate-500">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat detail...
          </div>
          <div v-else-if="spendDetailError" class="py-8 text-center text-rose-600">
            {{ spendDetailError }}
          </div>
          <div v-else-if="!spendDetailData?.transactions?.length" class="py-8 text-center text-slate-500">
            Tidak ada transaksi.
          </div>
          <div v-else class="space-y-5">
            <div
              v-for="(txn, idx) in spendDetailData.transactions"
              :key="idx"
              class="border border-slate-200 rounded-xl overflow-hidden"
            >
              <div class="bg-slate-50 px-4 py-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <div><span class="text-slate-500">Tipe:</span> <strong>{{ txn.source }}</strong></div>
                <div><span class="text-slate-500">No. Transaksi:</span> <strong>{{ txn.number || '-' }}</strong></div>
                <div v-if="txn.category_name"><span class="text-slate-500">Category:</span> <strong>{{ txn.category_name }}</strong></div>
                <div v-if="txn.warehouse"><span class="text-slate-500">Warehouse:</span> <strong>{{ txn.warehouse }}</strong></div>
                <div v-if="txn.ro_number"><span class="text-slate-500">No. RO/FO:</span> <strong>{{ txn.ro_number }}</strong></div>
                <template v-if="txn.source === 'GSR' || txn.source === 'GR'">
                  <div><span class="text-slate-500">Pembuat RO:</span> <strong>{{ txn.ro_creator || txn.ordered_by || '-' }}</strong></div>
                  <div><span class="text-slate-500">Penerima GR:</span> <strong>{{ txn.received_by || '-' }}</strong></div>
                </template>
                <div v-else-if="txn.supplier_name"><span class="text-slate-500">Supplier:</span> <strong>{{ txn.supplier_name }}</strong></div>
                <div v-else><span class="text-slate-500">User:</span> <strong>{{ txn.ordered_by || '-' }}</strong></div>
                <div class="ml-auto"><span class="text-slate-500">Total:</span> <strong>{{ formatCurrency(txn.total) }}</strong></div>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="bg-white border-b text-slate-600">
                      <th class="px-4 py-2 text-left">Item</th>
                      <th class="px-4 py-2 text-right">Qty</th>
                      <th class="px-4 py-2 text-left">Unit</th>
                      <th class="px-4 py-2 text-right">{{ isStockCutSpendDetail ? 'MAC' : 'Harga' }}</th>
                      <th class="px-4 py-2 text-right">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-if="hasItemCategories(txn.items)">
                      <template v-for="group in groupItemsByCategory(txn.items)" :key="group.category">
                        <tr class="bg-slate-100/90 border-b border-slate-200">
                          <td colspan="5" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-700">
                            {{ group.category }}
                            <span class="ml-2 font-normal normal-case text-slate-500">{{ group.items.length }} item · {{ formatCurrency(group.subtotal) }}</span>
                          </td>
                        </tr>
                        <tr
                          v-for="(item, i) in group.items"
                          :key="group.category + '-' + i"
                          class="border-b last:border-b-0"
                        >
                          <td class="px-4 py-2 pl-6">{{ item.name }}</td>
                          <td class="px-4 py-2 text-right">{{ item.qty }}</td>
                          <td class="px-4 py-2">{{ item.unit }}</td>
                          <td class="px-4 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                          <td class="px-4 py-2 text-right font-medium">{{ formatCurrency(item.subtotal) }}</td>
                        </tr>
                      </template>
                    </template>
                    <template v-else>
                      <tr v-for="(item, i) in txn.items" :key="i" class="border-b last:border-b-0">
                        <td class="px-4 py-2">{{ item.name }}</td>
                        <td class="px-4 py-2 text-right">{{ item.qty }}</td>
                        <td class="px-4 py-2">{{ item.unit }}</td>
                        <td class="px-4 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                        <td class="px-4 py-2 text-right font-medium">{{ formatCurrency(item.subtotal) }}</td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="px-6 py-3 border-t bg-slate-50 flex justify-between items-center">
          <div class="text-sm text-slate-600">
            Grand total: <strong>{{ formatCurrency(spendDetailData?.grand_total || 0) }}</strong>
          </div>
          <button
            type="button"
            class="px-4 py-2 rounded-lg bg-slate-800 text-white hover:bg-black"
            @click="closeSpendCellDetail"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Attendance list modal -->
    <div
      v-if="attModalOpen"
      class="fixed inset-0 z-[55] flex items-center justify-center bg-black/40 p-4"
      @click.self="closeAttendanceModal"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">{{ attModalTitle }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ attModalSubtitle }}</p>
          </div>
          <button type="button" class="text-slate-400 hover:text-slate-700 text-xl" @click="closeAttendanceModal">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="px-6 py-4 overflow-y-auto flex-1">
          <div v-if="!(attModalEmployees || []).length" class="py-10 text-center text-slate-400">
            Tidak ada data.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-slate-50 text-slate-600 border-b">
                  <th class="px-3 py-2 text-left">Karyawan</th>
                  <template v-if="attModalKind === 'overtime'">
                    <th class="px-3 py-2 text-right">OT Submission</th>
                    <th class="px-3 py-2 text-right">OT Real</th>
                  </template>
                  <template v-else-if="attModalKind === 'late'">
                    <th class="px-3 py-2 text-right">Telat (menit)</th>
                  </template>
                  <template v-else>
                    <th class="px-3 py-2 text-left">Leave</th>
                    <th class="px-3 py-2 text-right">Total hari</th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="emp in attModalEmployees"
                  :key="emp.user_id"
                  class="border-b last:border-b-0 hover:bg-slate-50/80"
                >
                  <td class="px-3 py-2.5">
                    <button
                      type="button"
                      class="text-left font-semibold text-sky-700 hover:underline"
                      @click="openAttendanceEmployeeDetail(emp)"
                    >
                      {{ emp.nama_lengkap }}
                    </button>
                  </td>
                  <template v-if="attModalKind === 'overtime'">
                    <td class="px-3 py-2.5 text-right">
                      <div class="font-mono text-teal-700 font-semibold">{{ formatDecimal(emp.submission_hours) }} jam</div>
                      <div class="font-mono text-xs text-teal-800">{{ formatCurrency(emp.submission_amount) }}</div>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                      <div class="font-mono text-violet-700 font-semibold">{{ formatNumber(emp.real_hours) }} jam</div>
                      <div class="font-mono text-xs text-violet-800">{{ formatCurrency(emp.real_amount) }}</div>
                    </td>
                  </template>
                  <template v-else-if="attModalKind === 'late'">
                    <td class="px-3 py-2.5 text-right font-mono font-semibold text-orange-700">
                      {{ formatNumber(emp.total_minutes) }}
                    </td>
                  </template>
                  <template v-else>
                    <td class="px-3 py-2.5 text-slate-600">
                      <span v-for="(t, i) in emp.by_type || []" :key="t.name">
                        {{ t.name }} ({{ t.days }})<span v-if="i < (emp.by_type || []).length - 1">, </span>
                      </span>
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono font-semibold">{{ formatNumber(emp.total_days) }}</td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="px-6 py-3 border-t bg-slate-50 flex justify-end">
          <button type="button" class="px-4 py-2 rounded-lg bg-slate-800 text-white hover:bg-black" @click="closeAttendanceModal">
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Attendance employee day detail (nested) -->
    <div
      v-if="attDayOpen"
      class="fixed inset-0 z-[65] flex items-center justify-center bg-black/40 p-4"
      @click.self="closeAttendanceEmployeeDetail"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">{{ attDayEmployee?.nama_lengkap || 'Detail' }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ attDaySubtitle }}</p>
          </div>
          <button type="button" class="text-slate-400 hover:text-slate-700 text-xl" @click="closeAttendanceEmployeeDetail">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="px-6 py-4 overflow-y-auto flex-1">
          <div v-if="!(attDayRows || []).length" class="py-10 text-center text-slate-400">
            Tidak ada detail tanggal.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-slate-50 text-slate-600 border-b">
                  <template v-if="attModalKind === 'overtime'">
                    <th class="px-3 py-2 text-left">Tanggal</th>
                    <th class="px-3 py-2 text-right">OT Submission</th>
                    <th class="px-3 py-2 text-right">OT Real</th>
                  </template>
                  <template v-else-if="attModalKind === 'late'">
                    <th class="px-3 py-2 text-left">Tanggal</th>
                    <th class="px-3 py-2 text-right">Telat (menit)</th>
                  </template>
                  <template v-else>
                    <th class="px-3 py-2 text-left">Tanggal</th>
                    <th class="px-3 py-2 text-left">Leave</th>
                    <th class="px-3 py-2 text-right">Hari</th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in attDayRows" :key="idx" class="border-b last:border-b-0">
                  <template v-if="attModalKind === 'overtime'">
                    <td class="px-3 py-2.5">{{ formatShortDate(row.tanggal) }}</td>
                    <td class="px-3 py-2.5 text-right">
                      <div class="font-mono text-teal-700">{{ formatDecimal(row.submission_hours) }} jam</div>
                      <div class="font-mono text-xs text-teal-800">{{ formatCurrency(row.submission_amount) }}</div>
                    </td>
                    <td class="px-3 py-2.5 text-right font-mono text-violet-700 font-semibold">
                      {{ formatNumber(row.real_hours) }} jam
                    </td>
                  </template>
                  <template v-else-if="attModalKind === 'late'">
                    <td class="px-3 py-2.5">{{ formatShortDate(row.tanggal) }}</td>
                    <td class="px-3 py-2.5 text-right font-mono font-semibold text-orange-700">
                      {{ formatNumber(row.minutes) }}
                    </td>
                  </template>
                  <template v-else>
                    <td class="px-3 py-2.5">
                      <span v-if="row.date_from === row.date_to">{{ formatShortDate(row.date_from) }}</span>
                      <span v-else>{{ formatShortDate(row.date_from) }} – {{ formatShortDate(row.date_to) }}</span>
                    </td>
                    <td class="px-3 py-2.5">{{ row.leave_type }}</td>
                    <td class="px-3 py-2.5 text-right font-mono font-semibold">{{ formatNumber(row.days) }}</td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="px-6 py-3 border-t bg-slate-50 flex justify-end">
          <button
            type="button"
            class="px-4 py-2 rounded-lg bg-slate-800 text-white hover:bg-black"
            @click="closeAttendanceEmployeeDetail"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardHelpTip from '@/Components/CardHelpTip.vue'
import RollingForecastPanel from './Components/RollingForecastPanel.vue'
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'

const cardHelps = {
  forecast:
    'Nilai Forecast di sini diambil dari Rolling Auto Forecast skenario Realistis (projected EOM).\n\nRealistis = Actual MTD + sisa hari dengan blend 50% pace MTD + 50% baseline dari monthly target.\n\nJika rolling belum tersedia, fallback ke total daily forecast di menu Revenue Target.\n\nPool budget = 43% × Forecast ini, lalu dibagi:\n• Kitchen 70%\n• Bar 20%\n• Service 10%\n\nPeriode selalu full calendar month.',
  budget_pool:
    'Pool budget pembelian = 43% × Forecast (Rolling Realistis).\n\nPool ini kemudian dibagi:\n• Kitchen 70% dari pool\n• Bar 20% dari pool\n• Service 10% dari pool\n\nJadi Budget Kitchen ≈ 30,1% dari Forecast, Bar ≈ 8,6%, Service ≈ 4,3%.',
  purchased_field:
    'Purchased = nilai yang sudah diterima di periode bulan penuh:\n• GSR (serial receive + GR outlet)\n• Retail Food (RF)\n\nRWS tidak dijumlah — inlet outlet sudah lewat RF ke Justus Group.\nDihitung per warehouse Kitchen / Bar / Service.\nCard menampilkan breakdown GSR / RF.\nBukan MTD filter tanggal — selalu full calendar month.',
  budget_field:
    'Budget bucket = share × (43% × Forecast Realistis).\n\n• Kitchen = 70% × pool\n• Bar = 20% × pool\n• Service = 10% × pool\n\nIkut bergerak jika Rolling Forecast realistis naik/turun.',
  ro_outstanding_field:
    'RO Outstanding = qty RO yang belum diterima penuh (belum GSR/GR) × harga RO.\n\nBelum masuk Purchased, tapi sudah “committed”.\nDipakai untuk hitung sisa budget setelah commit.',
  remaining_budget_field:
    'Sisa budget = Budget − Purchased.\n\nSetelah commit = Budget − Purchased − RO Outstanding.\n\nNegatif = over budget (tampil “Over …”).',
  kitchen_purchase:
    'Purchased Kitchen = nilai diterima untuk warehouse Kitchen:\n• GSR (serial receive)\n• GR outlet (jika ada)\n• Retail Food\n\nRWS tidak dijumlah (sudah di RF Justus Group).\nBudget = 70% × (43% × Forecast Rolling Realistis).\nRO Outstanding = qty RO belum diterima penuh (belum GSR/GR) × harga RO.\nSisa budget = Budget − Purchased.\nSetelah commit = Budget − Purchased − RO Outstanding.',
  bar_purchase:
    'Purchased Bar = nilai diterima untuk warehouse Bar:\n• GSR\n• GR outlet (jika ada)\n• Retail Food (warehouse Bar)\n\nBudget = 20% × (43% × Forecast Rolling Realistis).\nRO Outstanding / sisa budget sama logikanya dengan Kitchen.',
  service_purchase:
    'Purchased Service = nilai diterima untuk warehouse Service:\n• GSR\n• GR outlet (jika ada)\n• Retail Food (warehouse Service)\n\nBudget = 10% × (43% × Forecast Rolling Realistis).\nRO Outstanding / sisa budget sama logikanya dengan Kitchen.',
  gsr_ro:
    'Nilai penerimaan outlet pada periode filter:\n• GR = Outlet Food Good Receive × harga RO\n• GSR = Serial Goods Receive × cost (cost_small, dikonversi unit)\n\nDua bar:\n• vs Total Spend = nilai ÷ Total Spend\n• vs Revenue = nilai ÷ Revenue',
  retail_food:
    'Transaksi Retail Food status approved (Cash + Contra Bon) pada periode filter.\n\nTermasuk pembelian ke Justus Group (mirror RWS gudang → outlet).\nIkut ke Purchased Kitchen/Bar/Service menurut warehouse_outlet.\nDua bar: vs Total Spend & vs Revenue.',
  retail_non_food:
    'Transaksi Retail Non Food status approved (Cash + Contra Bon).\n\nMasuk Total Spend, tetapi tidak masuk Purchased Kitchen/Bar/Service.\nDua bar: vs Total Spend & vs Revenue.',
  petty_cash:
    'Subset cash dari Retail Food + Retail Non Food (payment_method = cash).\nBukan tambahan di luar RF/RNF — hanya ringkasan cash spend.\n\nDua bar: vs Total Spend & vs Revenue.',
  mcs_purchase:
    'Pembelian item kategori MCS (Marketing, Chemical, Stationary, dll) dari GSR + Retail Food.\n\nBreakdown per category di bawah nilai total.\nDua bar: vs Total Spend & vs Revenue.',
  purchase_category:
    'Pie chart komposisi pembelian semua category item dari GSR + Retail Food.\nKlik slice untuk buka detail transaksi.',
  revenue:
    'Total penjualan outlet (orders) pada periode filter.\nBudget & performa dibanding Revenue Target bulanan (jika ada).',
  total_spend:
    'Total belanja outlet = GSR + GR + Retail Food + Retail Non Food.\n\nRWS tidak dijumlah (sudah di RF Justus Group).\n\nBreakdown di card:\n• GSR / GR\n• RF Cash & RF Contra Bon\n• RNF Cash & RNF Contra Bon\n\nModal detail: tabel harian Receiving Sheet + list Retail Non Food.\n% di bawah = Total Spend ÷ Revenue.',
  net:
    'Net = Revenue − Total Spend.\nBar menunjukkan rasio spend terhadap revenue.',
  cover:
    'Total cover / pax (jumlah tamu) dari transaksi penjualan periode filter.',
  avg_pax:
    'Average Pax = Cover ÷ jumlah bill.',
  avg_check:
    'Avg Check = Revenue ÷ Cover.',
  discount:
    'Total diskon pada bill penjualan periode filter.',
  discount_compliment:
    'Diskon tipe Compliment (nilai diskon & nilai bill terkait).',
  discount_guest_satisfaction:
    'Diskon tipe Guest Satisfaction.',
  officer_check:
    'Pembayaran / transaksi Officer Check pada periode filter.',
  outlet_city_ledger:
    'Outlet City Ledger (piutang/ledger antar outlet) pada periode filter.',
  member_bills:
    'Jumlah bill member pada periode filter.',
  member_top_up:
    'Point earn / top-up member.',
  member_redeem:
    'Point redeem member.',
  stock_cut:
    'Nilai Stock Cut = HPP teoritis full BOM (stock_cut_details).\n\nJika stok kurang saat potong:\n• Fisik hanya dipotong sampai 0 (kartu order_items)\n• Shortfall dicatat di Laporan Minus (stock_cut_variances)\n• HPP di detail tetap full — ini desain fitur, bukan kelebihan potong\n\nEnding inventory formula memakai potongan fisik (kartu), supaya selaras cost di stok.\nModal detail harian: Food / Beverage / Total dari HPP full.',
  category_cost:
    'Category Cost outlet (Internal Use, Spoil, Waste, dll) berdasarkan subtotal MAC dokumen terkait.\nCard menampilkan breakdown per type dan per warehouse outlet.',
  begin_inventory:
    'Begin Inventory (Total MAC) sama seperti kolom Cost Report.\n\nJika ada upload saldo awal tgl 1 bulan laporan → pakai initial_balance saja (tanpa stock_opname).\nJika tidak → qty × MAC dari stok sistem.\nKlik card → detail item, qty, MAC per kategori (expand/collapse + search).\nCard menampilkan breakdown per warehouse outlet.',
  ending_inventory:
    'Nilai utama = Begin (IB / Cost Report) + Koreksi fisik tgl 1 untuk item tanpa IB + Purchased ± Transfer Outlet (net) ± Adjustment − Stock Cut (fisik) − Category Cost.\n\nBegin Inventory card tetap sama Cost Report (IB saja).\nKoreksi fisik tgl 1 hanya masuk formula ending (bukan begin card), supaya stok item yang dikoreksi tanpa IB tidak hilang dari rollforward.\nOpname EOM / mid-month lain = balancing qty ke fisik — tidak dijumlah ke formula buku.\n\nStok ending = kartu terbaru dalam periode filter saja (dari tgl 1), tidak menarik saldo bulan sebelumnya.\nStock Cut di formula = qty fisik yang keluar kartu (bukan HPP full).\nSelisih HPP full vs fisik = shortfall Laporan Minus.\nIWT tidak dijumlah di level outlet (net antar gudang ≈ 0).\nDi bawahnya: cost stok aktual + selisih (formula − stok).\nPer warehouse = nilai stok (bukan formula).',
  cogs_pct:
    '% COGS (periode filter dashboard).\n\nNilai utama = % COGS Actual After Disc = COGS Aktual ÷ (Sales before disc − Discount).\nSales before disc = Σ(qty × price) order items.\n\nCOGS Foods = Stock Cut HPP full\nCategory Cost (pembanding) = spoil + waste + guest supplies + non commodity\nMeal Employees = internal use\nCOGS Pembanding = Foods + Cat Cost + Meal Emp\nCOGS Aktual = (Begin + Koreksi tgl 1 + Purchased ± Xfer ± Adj) − Ending Stok\n\nDeviasi = Pembanding − Aktual, ditampilkan juga sebagai % dari revenue after disc.\nToleransi max = 2% revenue (hijau jika dalam batas, merah jika melebihi).',
  outlet_transfer:
    'Transfer antar outlet pada periode filter.\nTransfer In = value_in kartu inventory.\nTransfer Out = value_out kartu inventory.\nKlik card → daftar transaksi (outlet + user).\nKlik transaksi → detail item + cost.',
  outlet_adjustment:
    'Stock adjustment outlet (net value_in − value_out) per periode.\nBreakdown per warehouse di card.\nKlik → daftar transaksi → detail item + cost.',
  stock_opname:
    'Stock Opname / koreksi fisik.\n\nNilai utama card = cutoff tgl 1 untuk item tanpa IB (saldo kartu) — ini yang masuk formula Ending Inventory.\nBegin Inventory tetap sama Cost Report (IB saja).\n\nNet periode = value_in − value_out semua opname di filter (balancing fisik) — tidak dijumlah ke formula buku.\nKlik → daftar dokumen opname → detail item (qty in/out, value, saldo).',
  internal_warehouse_transfer:
    'Transfer antar gudang dalam outlet yang sama.\nCard menampilkan nilai per alur (mis. Service → Kitchen).\nKlik → daftar transaksi → detail item + cost.',
  outlet_wip:
    'WIP production outlet.\nCost bahan = value_out kartu (MAC bahan).\nBarang jadi = value_in kartu (= cost bahan batch).\nHarusnya bahan ≈ jadi; selisih kecil = rounding.\nBreakdown per warehouse.\nKlik → daftar produksi → detail item + cost.',
  employee_overtime:
    'OT Submission = jam & nilai dari Overtime Submission approved.\nOT Real = jam lembur aktual (absensi + Extra Off OT, dikurangi 1+1) seperti Attendance Report per outlet.\nRata-rata / karyawan = total ÷ jumlah karyawan yang punya absensi di periode 26–25.\nKlik card → per karyawan. Klik nama → per tanggal.',
  late_absen:
    'Total menit keterlambatan (hari non-off) seperti Attendance Report per outlet.\nKlik card → per karyawan. Klik nama → tanggal & menit telat.',
  leave:
    'Total hari leave/izin approved per tipe (absent_requests), sama sumber Attendance Report.\nKlik card → per karyawan. Klik nama → rentang tanggal, tipe, dan jumlah hari.',
}

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
  mcs_mix: [],
  purchase_category_mix: [],
  payment_methods: [],
  ro_forecast: null,
  outlet_name: null,
  attendance: null,
})

const dashboardData = ref({ ...emptyDashboard(), ...(props.dashboardData || {}) })
const ov = computed(() => dashboardData.value.overview || {})
const vs = computed(() => ov.value.vs_last_month || {})
const vsMember = computed(() => ov.value.vs_last_month_member || {})
const trendRows = computed(() => dashboardData.value.trend || [])
const roForecast = computed(() => dashboardData.value.ro_forecast || null)

const purchaseBudgetCards = computed(() => {
  const rf = roForecast.value || {}
  return [
    {
      key: 'kitchen',
      modalType: 'kitchen_purchase',
      label: 'Budget Kitchen',
      help: cardHelps.kitchen_purchase,
      sharePct: rf.kitchen?.share_of_pool_pct || 70,
      data: rf.kitchen,
      cardClass: 'border-teal-100 bg-teal-50/40',
      titleClass: 'text-teal-700',
      badgeClass: 'bg-teal-100 text-teal-800',
      valueClass: 'text-teal-800',
      dividerClass: 'border-teal-100/80',
      barBorderClass: 'border-teal-100',
      barFillClass: 'bg-teal-500',
    },
    {
      key: 'bar',
      modalType: 'bar_purchase',
      label: 'Budget Bar',
      help: cardHelps.bar_purchase,
      sharePct: rf.bar?.share_of_pool_pct || 20,
      data: rf.bar,
      cardClass: 'border-indigo-100 bg-indigo-50/40',
      titleClass: 'text-indigo-700',
      badgeClass: 'bg-indigo-100 text-indigo-800',
      valueClass: 'text-indigo-800',
      dividerClass: 'border-indigo-100/80',
      barBorderClass: 'border-indigo-100',
      barFillClass: 'bg-indigo-500',
    },
    {
      key: 'service',
      modalType: 'service_purchase',
      label: 'Budget Service',
      help: cardHelps.service_purchase,
      sharePct: rf.service?.share_of_pool_pct || 10,
      data: rf.service,
      cardClass: 'border-cyan-100 bg-cyan-50/40',
      titleClass: 'text-cyan-700',
      badgeClass: 'bg-cyan-100 text-cyan-800',
      valueClass: 'text-cyan-800',
      dividerClass: 'border-cyan-100/80',
      barBorderClass: 'border-cyan-100',
      barFillClass: 'bg-cyan-500',
    },
  ]
})
const att = computed(() => dashboardData.value.attendance || {})

const sectionLoading = ref({
  meta: false,
  overview: false,
  member: false,
  ro_forecast: false,
  payments: false,
  charts: false,
  attendance: false,
})
const sectionError = ref({
  meta: false,
  overview: false,
  member: false,
  ro_forecast: false,
  payments: false,
  charts: false,
  attendance: false,
})

const bootstrapping = computed(() =>
  Object.values(sectionLoading.value).some(Boolean)
)

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const tahunOptions = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i)

const pad2 = (n) => String(n).padStart(2, '0')
const fmtDate = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`

/** Revenue/Spend: kalender tgl 1 s/d akhir bulan (bulan berjalan = MTD). */
const calendarPeriodFor = (bulan, tahun) => {
  const b = Number(bulan) || (new Date().getMonth() + 1)
  const t = Number(tahun) || new Date().getFullYear()
  const start = new Date(t, b - 1, 1)
  let end = new Date(t, b, 0)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  if (start.getFullYear() === today.getFullYear() && start.getMonth() === today.getMonth() && end > today) {
    end = today
  }
  return { bulan: b, tahun: t, date_from: fmtDate(start), date_to: fmtDate(end) }
}

/** Absensi: payroll 26 bulan sebelumnya – 25 bulan label. */
const payrollPeriodFor = (bulan, tahun) => {
  const b = Number(bulan) || (new Date().getMonth() + 1)
  const t = Number(tahun) || new Date().getFullYear()
  const end = new Date(t, b - 1, 25)
  const start = new Date(t, b - 2, 26)
  return { bulan: b, tahun: t, date_from: fmtDate(start), date_to: fmtDate(end) }
}

const defaultCalendar = calendarPeriodFor(
  props.filters?.bulan || new Date().getMonth() + 1,
  props.filters?.tahun || new Date().getFullYear()
)
const defaultPayroll = payrollPeriodFor(defaultCalendar.bulan, defaultCalendar.tahun)

const filters = ref({
  bulan: props.filters?.bulan || defaultCalendar.bulan,
  tahun: props.filters?.tahun || defaultCalendar.tahun,
  date_from: props.filters?.date_from || defaultCalendar.date_from,
  date_to: props.filters?.date_to || defaultCalendar.date_to,
  period_label: props.filters?.period_label || '',
  attendance_date_from: props.filters?.attendance_date_from || defaultPayroll.date_from,
  attendance_date_to: props.filters?.attendance_date_to || defaultPayroll.date_to,
  attendance_period_label: props.filters?.attendance_period_label || '',
  outlet_id: props.filters?.outlet_id || null,
})

const rollingForecastMonth = computed(() => {
  const tahun = Number(filters.value.tahun) || new Date().getFullYear()
  const bulan = Number(filters.value.bulan) || (new Date().getMonth() + 1)
  return `${tahun}-${String(bulan).padStart(2, '0')}`
})

const periodLabel = computed(() => {
  if (filters.value.period_label) return filters.value.period_label
  const p = calendarPeriodFor(filters.value.bulan, filters.value.tahun)
  return `${p.date_from} s/d ${p.date_to}`
})

const attendancePeriodLabel = computed(() => {
  if (filters.value.attendance_period_label) return filters.value.attendance_period_label
  const p = payrollPeriodFor(filters.value.bulan, filters.value.tahun)
  return `${p.date_from} s/d ${p.date_to}`
})

const syncPeriodDates = () => {
  const cal = calendarPeriodFor(filters.value.bulan, filters.value.tahun)
  const pay = payrollPeriodFor(filters.value.bulan, filters.value.tahun)
  filters.value.date_from = cal.date_from
  filters.value.date_to = cal.date_to
  filters.value.attendance_date_from = pay.date_from
  filters.value.attendance_date_to = pay.date_to
}

const filterParams = () => {
  syncPeriodDates()
  return {
    outlet_id: filters.value.outlet_id,
    bulan: filters.value.bulan,
    tahun: filters.value.tahun,
    date_from: filters.value.date_from,
    date_to: filters.value.date_to,
  }
}

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
  if (data.mcs_mix !== undefined) {
    dashboardData.value.mcs_mix = data.mcs_mix
  }
  if (data.purchase_category_mix !== undefined) {
    dashboardData.value.purchase_category_mix = data.purchase_category_mix
  }
  if (data.attendance !== undefined) {
    dashboardData.value.attendance = data.attendance
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
    fetchSection('attendance'),
  ])
}

const applyFilters = () => {
  if (!filters.value.outlet_id) {
    alert('Pilih outlet terlebih dahulu')
    return
  }
  syncPeriodDates()
  // Update URL tanpa menunggu query berat di server
  router.get('/opex-outlet-dashboard', {
    outlet_id: filters.value.outlet_id,
    bulan: filters.value.bulan,
    tahun: filters.value.tahun,
  }, {
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
  () => [
    props.filters?.bulan,
    props.filters?.tahun,
    props.filters?.date_from,
    props.filters?.date_to,
    props.filters?.period_label,
    props.filters?.attendance_date_from,
    props.filters?.attendance_date_to,
    props.filters?.attendance_period_label,
    props.filters?.outlet_id,
  ],
  ([bulan, tahun, df, dt, label, adf, adt, alabel, oid]) => {
    if (bulan != null) filters.value.bulan = Number(bulan)
    if (tahun != null) filters.value.tahun = Number(tahun)
    if (df) filters.value.date_from = df
    if (dt) filters.value.date_to = dt
    if (label !== undefined) filters.value.period_label = label || ''
    if (adf) filters.value.attendance_date_from = adf
    if (adt) filters.value.attendance_date_to = adt
    if (alabel !== undefined) filters.value.attendance_period_label = alabel || ''
    if (oid !== undefined) filters.value.outlet_id = oid
  }
)

const roForecastHref = computed(() => {
  const outlet = filters.value.outlet_id
  const month = `${filters.value.tahun}-${String(filters.value.bulan).padStart(2, '0')}`
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
    paymentHint: null,
    extraHint: null,
    vs: vs.value.gsr_ro,
    help: cardHelps.gsr_ro,
    icon: 'fa-solid fa-truck',
    tone: 'text-amber-600',
    iconBg: 'bg-amber-50',
    border: 'border-amber-100',
    bar: 'bg-amber-400',
  },
  {
    key: 'retail_food',
    label: 'Retail Food',
    amount: ov.value.retail_food || 0,
    hint: `${ov.value.retail_food_count || 0} transaksi`,
    paymentHint: `Cash ${ov.value.retail_food_cash_count || 0} · Contra Bon ${ov.value.retail_food_contra_bon_count || 0}`,
    extraHint: (Number(ov.value.retail_food_justus_group_total) || 0) > 0
      ? `Justus Group ${formatCurrency(ov.value.retail_food_justus_group_total)} · ${ov.value.retail_food_justus_group_count || 0} trx`
      : null,
    vs: vs.value.retail_food,
    help: cardHelps.retail_food,
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
    paymentHint: `Cash ${ov.value.retail_non_food_cash_count || 0} · Contra Bon ${ov.value.retail_non_food_contra_bon_count || 0}`,
    extraHint: null,
    vs: vs.value.retail_non_food,
    help: cardHelps.retail_non_food,
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
    paymentHint: `${ov.value.petty_cash_count || 0} trx cash (RF+RNF)`,
    extraHint: null,
    vs: vs.value.petty_cash,
    help: cardHelps.petty_cash,
    icon: 'fa-solid fa-wallet',
    tone: 'text-cyan-600',
    iconBg: 'bg-cyan-50',
    border: 'border-cyan-100',
    bar: 'bg-cyan-400',
  },
])

const categoryCostByType = computed(() => ov.value.category_cost_by_type || [])
const mcsPurchaseByCategory = computed(() => ov.value.mcs_purchase_by_category || [])

/** Breakdown Total Spend di card (GSR/GR + RF/RNF cash & contra bon). */
const totalSpendBreakdown = computed(() => {
  const o = ov.value || {}
  const rows = [
    { key: 'gsr', label: 'GSR', amount: Number(o.gsr_ro_gsr) || 0 },
    { key: 'gr', label: 'GR', amount: Number(o.gsr_ro_gr) || 0 },
    { key: 'rf_cash', label: 'RF Cash', amount: Number(o.retail_food_cash_total) || 0 },
    { key: 'rf_cb', label: 'RF Contra Bon', amount: Number(o.retail_food_contra_bon_total) || 0 },
    { key: 'rnf_cash', label: 'RNF Cash', amount: Number(o.retail_non_food_cash_total) || 0 },
    { key: 'rnf_cb', label: 'RNF Contra Bon', amount: Number(o.retail_non_food_contra_bon_total) || 0 },
  ]
  // Sembunyikan baris 0 supaya card tidak terlalu ramai, kecuali semua 0.
  const nonzero = rows.filter((r) => r.amount > 0)
  return nonzero.length ? nonzero : rows
})

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
  return Math.min(100, Math.round(((Number(amount) || 0) / total) * 1000) / 10)
}

const revenueShare = (amount) => {
  const total = Number(ov.value.revenue) || 0
  if (total <= 0) return 0
  return Math.round(((Number(amount) || 0) / total) * 1000) / 10
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

const purchaseCategoryMixRows = computed(() => dashboardData.value.purchase_category_mix || [])
const purchaseCategoryMixSeries = computed(() => purchaseCategoryMixRows.value.map((i) => Number(i.amount) || 0))
const purchaseCategoryMixOptions = computed(() => ({
  labels: purchaseCategoryMixRows.value.map((i) => i.label),
  colors: [
    '#f59e0b', '#0ea5e9', '#10b981', '#8b5cf6', '#f43f5e', '#14b8a6',
    '#eab308', '#6366f1', '#fb7185', '#22c55e', '#a855f7', '#64748b',
  ],
  legend: { position: 'bottom', fontSize: '11px' },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(0)}%` },
  tooltip: {
    y: { formatter: (val) => formatCurrency(val) },
  },
  chart: {
    events: {
      dataPointSelection: (_event, _ctx, config) => {
        const idx = config?.dataPointIndex
        if (idx == null || idx < 0) return
        const row = purchaseCategoryMixRows.value[idx]
        if (!row) return
        openPurchaseCategory(row.label || row.key)
      },
    },
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
  { name: 'Retail Food', data: trendRows.value.map((r) => Number(r.retail_food) || 0) },
  { name: 'Retail Non Food', data: trendRows.value.map((r) => Number(r.retail_non_food) || 0) },
])

const stackOptions = computed(() => ({
  chart: { stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
  colors: ['#f59e0b', '#10b981', '#f97316'],
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
const modalSheetMeta = ref(null)
const expandedMcsTxnIds = ref({})
const expandedBeginCategories = ref({})
const expandedEndingCategories = ref({})
const endingWarehouseFilter = ref('')
const mcsCategoryFilter = ref('')
let beginInventorySearchTimer = null
let endingInventorySearchTimer = null
let invTxnSearchTimer = null
const invTxnDetail = ref(null)
const invTxnDetailLoading = ref(false)

const invTxnPartyLabel = computed(() => {
  if (modalType.value === 'outlet_transfer') return 'Outlet'
  if (modalType.value === 'internal_warehouse_transfer') return 'Gudang'
  return 'Warehouse'
})

const spendDetailOpen = ref(false)
const spendDetailLoading = ref(false)
const spendDetailError = ref('')
const spendDetailData = ref(null)
const spendDetailMeta = ref({ title: '', date: '', amount: null })

const beginInventoryGroupCount = computed(() => (modalSheetMeta.value?.groups || []).length)
const beginInventoryItemCount = computed(() =>
  (modalSheetMeta.value?.groups || []).reduce((acc, g) => acc + (Number(g.item_count) || 0), 0)
)

const endingStockVariance = computed(() => {
  if (!modalSheetMeta.value || modalType.value !== 'ending_inventory') return null
  const formula = Number(modalSheetMeta.value.formula?.ending)
  const stock = Number(modalSheetMeta.value.total_value ?? modalSheetMeta.value.stock_total)
  if (Number.isNaN(formula) || Number.isNaN(stock)) return null
  return Math.round((formula - stock) * 100) / 100
})

const endingCategoryKey = (warehouseId, category) => `${warehouseId}::${category}`

const isEndingCategoryExpanded = (warehouseId, category) =>
  !!expandedEndingCategories.value[endingCategoryKey(warehouseId, category)]

const toggleBeginCategory = (category) => {
  expandedBeginCategories.value = {
    ...expandedBeginCategories.value,
    [category]: !expandedBeginCategories.value[category],
  }
}

const expandAllBeginCategories = () => {
  const next = {}
  for (const g of modalSheetMeta.value?.groups || []) {
    next[g.category] = true
  }
  expandedBeginCategories.value = next
}

const collapseAllBeginCategories = () => {
  expandedBeginCategories.value = {}
}

const queueBeginInventorySearch = () => {
  if (beginInventorySearchTimer) window.clearTimeout(beginInventorySearchTimer)
  beginInventorySearchTimer = window.setTimeout(() => {
    modalPage.value = 1
    fetchModal()
  }, 300)
}

const toggleEndingCategory = (warehouseId, category) => {
  const key = endingCategoryKey(warehouseId, category)
  expandedEndingCategories.value = {
    ...expandedEndingCategories.value,
    [key]: !expandedEndingCategories.value[key],
  }
}

const expandAllEndingCategories = () => {
  const next = {}
  for (const wh of modalSheetMeta.value?.warehouses || []) {
    for (const g of wh.categories || []) {
      next[endingCategoryKey(wh.warehouse_id, g.category)] = true
    }
  }
  expandedEndingCategories.value = next
}

const collapseAllEndingCategories = () => {
  expandedEndingCategories.value = {}
}

const queueEndingInventorySearch = () => {
  if (endingInventorySearchTimer) window.clearTimeout(endingInventorySearchTimer)
  endingInventorySearchTimer = window.setTimeout(() => {
    modalPage.value = 1
    fetchModal()
  }, 300)
}

const onEndingWarehouseFilterChange = () => {
  modalPage.value = 1
  expandedEndingCategories.value = {}
  fetchModal()
}

const toggleMcsTxn = (id) => {
  expandedMcsTxnIds.value = {
    ...expandedMcsTxnIds.value,
    [id]: !expandedMcsTxnIds.value[id],
  }
}

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
    stock_cut: 'Stock Cut',
    category_cost: 'Category Cost',
    begin_inventory: 'Begin Inventory',
    ending_inventory: 'Ending Inventory',
    outlet_transfer: 'Transfer Outlet',
    outlet_adjustment: 'Adjustment',
    stock_opname: 'Stock Opname',
    internal_warehouse_transfer: 'Internal Warehouse Transfer',
    outlet_wip: 'WIP Production',
    mcs_purchase: mcsCategoryFilter.value
      ? `Pembelian MCS · ${mcsCategoryFilter.value}`
      : 'Pembelian MCS',
    purchase_category: mcsCategoryFilter.value
      ? `Pembelian · ${mcsCategoryFilter.value}`
      : 'Pembelian per Category',
    kitchen_purchase: 'Budget Kitchen · Purchased',
    bar_purchase: 'Budget Bar · Purchased',
    service_purchase: 'Budget Service · Purchased',
    outlet_city_ledger: 'Outlet City Ledger',
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
    outlet_city_ledger: 'Member / Note',
    member_top_up: 'Member',
    member_redeem: 'Member / Reward',
    revenue: 'Member',
    gsr_ro: 'User / Supplier',
    rws: 'User / Supplier',
    retail_food: 'User',
    retail_non_food: 'User',
    petty_cash: 'User',
    kitchen_purchase: 'Warehouse / User',
    bar_purchase: 'Warehouse / User',
    service_purchase: 'Warehouse / User',
    total_spend: 'User / Supplier',
  }
  return map[modalType.value] || 'Keterangan'
})

const modalShowsBill = computed(() =>
  ['discount_compliment', 'discount_guest_satisfaction', 'officer_check', 'outlet_city_ledger'].includes(modalType.value)
)

const modalShowsUserSupplier = computed(() => modalType.value === 'retail_food')
const modalShowsUserCategory = computed(() => modalType.value === 'retail_non_food')
const modalShowsPettyParty = computed(() => modalType.value === 'petty_cash')
const modalShowsPurchaseBucket = computed(() =>
  ['kitchen_purchase', 'bar_purchase', 'service_purchase'].includes(modalType.value)
)
const modalTxnClickable = computed(() =>
  ['gsr_ro', 'retail_food', 'retail_non_food', 'petty_cash', 'kitchen_purchase', 'bar_purchase', 'service_purchase'].includes(modalType.value)
)

const modalTxnColspan = computed(() => {
  let cols = 5
  if (modalShowsUserSupplier.value || modalShowsUserCategory.value || modalShowsPettyParty.value) cols += 1
  if (modalShowsBill.value) cols += 1
  return cols
})

const modalAmountLabel = computed(() => {
  if (modalType.value === 'officer_check' || modalType.value === 'outlet_city_ledger') return 'Pembayaran'
  if (modalShowsBill.value) return 'Discount'
  return 'Amount'
})

const revenueSubHeaders = [
  'COVER', 'REVENUE', 'A/C', 'DISC',
  'COVER', 'REVENUE', 'A/C', 'DISC',
  'COVER', 'REVENUE', 'A/C', 'DISC',
]

const revenueModalTotals = computed(() => {
  const rows = modalTxns.value || []
  const sum = (key) => rows.reduce((acc, r) => acc + (Number(r[key]) || 0), 0)
  const lunchCover = sum('lunch_cover')
  const lunchRevenue = sum('lunch_revenue')
  const dinnerCover = sum('dinner_cover')
  const dinnerRevenue = sum('dinner_revenue')
  const totalCover = sum('total_cover')
  const totalRevenue = sum('total_revenue')

  return {
    lunch_cover: lunchCover,
    lunch_revenue: lunchRevenue,
    lunch_avg_check: lunchCover > 0 ? Math.round(lunchRevenue / lunchCover) : 0,
    lunch_disc: sum('lunch_disc'),
    dinner_cover: dinnerCover,
    dinner_revenue: dinnerRevenue,
    dinner_avg_check: dinnerCover > 0 ? Math.round(dinnerRevenue / dinnerCover) : 0,
    dinner_disc: sum('dinner_disc'),
    total_cover: totalCover,
    total_revenue: totalRevenue,
    total_avg_check: totalCover > 0 ? Math.round(totalRevenue / totalCover) : 0,
    total_disc: sum('total_disc'),
  }
})

const spendWarehouseColumns = computed(() =>
  modalSheetMeta.value?.warehouse_columns?.length
    ? modalSheetMeta.value.warehouse_columns
    : [
        { key: 'main_store', name: 'Main Store' },
        { key: 'mk1', name: 'MK1 Hot Kitchen' },
        { key: 'mk2', name: 'MK2 Cold Kitchen' },
      ]
)

const spendSuppliers = computed(() => modalSheetMeta.value?.suppliers || [])

const spendRetailNonFoodTxns = computed(() => modalSheetMeta.value?.retail_non_food_transactions || [])

const spendRetailNonFoodTotal = computed(() =>
  (spendRetailNonFoodTxns.value || []).reduce((acc, r) => acc + (Number(r.amount) || 0), 0)
)

const spendModalTotals = computed(() => {
  const rows = modalTxns.value || []
  const sum = (key) => rows.reduce((acc, r) => acc + (Number(r[key]) || 0), 0)
  const warehouses = {}
  for (const wh of spendWarehouseColumns.value) {
    warehouses[wh.key] = sum(wh.key)
  }
  const suppliers = {}
  for (const sp of spendSuppliers.value) {
    suppliers[sp.id] = sum('supplier_' + sp.id)
  }
  return {
    warehouses,
    suppliers,
    total_spend: sum('total_spend'),
  }
})

const stockCutModalTotals = computed(() => {
  const rows = modalTxns.value || []
  return {
    food: rows.reduce((acc, r) => acc + (Number(r.food) || 0), 0),
    beverage: rows.reduce((acc, r) => acc + (Number(r.beverage) || 0), 0),
    amount: rows.reduce((acc, r) => acc + (Number(r.amount) || 0), 0),
  }
})

const categoryCostTypeColumns = computed(() =>
  modalSheetMeta.value?.type_columns?.length
    ? modalSheetMeta.value.type_columns
    : (categoryCostByType.value || []).map((r) => ({ key: r.type, label: r.label }))
)

const categoryCostModalTotals = computed(() => {
  const rows = modalTxns.value || []
  const sum = (key) => rows.reduce((acc, r) => acc + (Number(r[key]) || 0), 0)
  const types = {}
  for (const col of categoryCostTypeColumns.value) {
    types[col.key] = sum(col.key)
  }
  return {
    types,
    total: sum('total'),
  }
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
  expandedMcsTxnIds.value = {}
  expandedBeginCategories.value = {}
  expandedEndingCategories.value = {}
  endingWarehouseFilter.value = ''
  invTxnDetail.value = null
  if (type !== 'mcs_purchase' && type !== 'purchase_category') {
    mcsCategoryFilter.value = ''
  }
  await fetchModal()
}

const openPurchaseCategory = async (category) => {
  mcsCategoryFilter.value = category || ''
  await openCard('purchase_category')
}

const closeModal = () => {
  modalOpen.value = false
  modalTxns.value = []
  modalTrend.value = []
  modalSheetMeta.value = null
  expandedMcsTxnIds.value = {}
  expandedBeginCategories.value = {}
  expandedEndingCategories.value = {}
  endingWarehouseFilter.value = ''
  mcsCategoryFilter.value = ''
  invTxnDetail.value = null
  closeSpendCellDetail()
}

const openSourceTxnDetail = (txn) => {
  if (!txn) return
  const items = (txn.items || []).map((item) => ({
    name: item.name || item.item_name || '-',
    qty: item.qty,
    unit: item.unit || item.unit_name || '-',
    price: item.price,
    subtotal: item.subtotal ?? item.amount,
  }))
  const titlePrefix = modalTitle.value || txn.source || 'Transaksi'
  spendDetailOpen.value = true
  spendDetailLoading.value = false
  spendDetailError.value = ''
  spendDetailMeta.value = {
    title: `${titlePrefix} · ${txn.number || '-'}`,
    date: txn.date,
    amount: txn.amount,
  }
  spendDetailData.value = {
    title: spendDetailMeta.value.title,
    transactions: [
      {
        source: txn.source || titlePrefix,
        number: txn.number,
        ro_number: txn.ro_number,
        ro_creator: txn.ro_creator,
        received_by: txn.received_by,
        category_name: txn.category_name,
        supplier_name: txn.supplier_name,
        warehouse: txn.warehouse,
        ordered_by: txn.creator_name,
        total: Number(txn.amount) || 0,
        items,
      },
    ],
    grand_total: Number(txn.amount) || 0,
  }
}

const openSpendCellDetail = async (type, key, label, date, amount) => {
  if (!filters.value.outlet_id) {
    alert('Pilih outlet terlebih dahulu')
    return
  }

  spendDetailOpen.value = true
  spendDetailLoading.value = true
  spendDetailError.value = ''
  spendDetailData.value = null
  spendDetailMeta.value = {
    title: label,
    date,
    amount,
  }

  try {
    const { data } = await axios.get('/api/report/receiving-sheet-detail', {
      params: {
        type,
        key,
        date,
        outlet: filters.value.outlet_id,
      },
    })
    spendDetailData.value = data
    if (data?.title) {
      spendDetailMeta.value.title = data.title
    }
  } catch (e) {
    spendDetailError.value = e?.response?.data?.error || e?.message || 'Gagal memuat detail'
  } finally {
    spendDetailLoading.value = false
  }
}

const openCategoryCostCellDetail = async (typeKey, label, date, amount) => {
  if (!filters.value.outlet_id) {
    alert('Pilih outlet terlebih dahulu')
    return
  }

  spendDetailOpen.value = true
  spendDetailLoading.value = true
  spendDetailError.value = ''
  spendDetailData.value = null
  spendDetailMeta.value = {
    title: `Category Cost · ${label}`,
    date,
    amount,
  }

  try {
    const { data } = await axios.get('/opex-outlet-dashboard/category-cost-detail', {
      params: {
        outlet_id: filters.value.outlet_id,
        date,
        type: typeKey,
      },
    })
    spendDetailData.value = data
    if (data?.title) {
      spendDetailMeta.value.title = data.title
    }
  } catch (e) {
    spendDetailError.value = e?.response?.data?.error || e?.message || 'Gagal memuat detail'
  } finally {
    spendDetailLoading.value = false
  }
}

const openStockCutCellDetail = async (typeKey, label, date, amount) => {
  if (!filters.value.outlet_id) {
    alert('Pilih outlet terlebih dahulu')
    return
  }

  spendDetailOpen.value = true
  spendDetailLoading.value = true
  spendDetailError.value = ''
  spendDetailData.value = null
  spendDetailMeta.value = {
    title: `Stock Cut · ${label}`,
    date,
    amount,
  }

  try {
    const { data } = await axios.get('/opex-outlet-dashboard/stock-cut-detail', {
      params: {
        outlet_id: filters.value.outlet_id,
        date,
        type: typeKey,
      },
    })
    spendDetailData.value = data
    if (data?.title) {
      spendDetailMeta.value.title = data.title
    }
  } catch (e) {
    spendDetailError.value = e?.response?.data?.error || e?.message || 'Gagal memuat detail'
  } finally {
    spendDetailLoading.value = false
  }
}

const closeSpendCellDetail = () => {
  spendDetailOpen.value = false
  spendDetailLoading.value = false
  spendDetailError.value = ''
  spendDetailData.value = null
}

const attModalOpen = ref(false)
const attModalKind = ref('overtime')
const attModalEmployees = ref([])
const attDayOpen = ref(false)
const attDayEmployee = ref(null)

const attModalTitle = computed(() => {
  if (attModalKind.value === 'overtime') return 'Employee Overtime'
  if (attModalKind.value === 'late') return 'Telat Absen'
  return 'Leave'
})

const attModalSubtitle = computed(() => {
  if (attModalKind.value === 'overtime') {
    return `${formatDecimal(att.value.overtime?.submission_hours)} jam submission · ${formatNumber(att.value.overtime?.real_hours)} jam real`
  }
  if (attModalKind.value === 'late') {
    return `${formatNumber(att.value.late?.total_minutes)} menit · ${att.value.late?.employee_count || 0} karyawan`
  }
  return `${formatNumber(att.value.leave?.total_days)} hari total`
})

const attDaySubtitle = computed(() => {
  if (attModalKind.value === 'overtime') return 'OT Submission & OT Real per tanggal'
  if (attModalKind.value === 'late') return 'Tanggal & menit keterlambatan'
  return 'Tanggal, tipe leave, dan jumlah hari'
})

const attDayRows = computed(() => attDayEmployee.value?.days || [])

const openAttendanceModal = (kind) => {
  attModalKind.value = kind
  if (kind === 'overtime') {
    attModalEmployees.value = att.value.overtime?.employees || []
  } else if (kind === 'late') {
    attModalEmployees.value = att.value.late?.employees || []
  } else {
    attModalEmployees.value = att.value.leave?.employees || []
  }
  attModalOpen.value = true
}

const closeAttendanceModal = () => {
  attModalOpen.value = false
  attDayOpen.value = false
  attDayEmployee.value = null
}

const openAttendanceEmployeeDetail = (emp) => {
  attDayEmployee.value = emp
  attDayOpen.value = true
}

const closeAttendanceEmployeeDetail = () => {
  attDayOpen.value = false
  attDayEmployee.value = null
}

const isStockCutSpendDetail = computed(() =>
  modalType.value === 'stock_cut' || String(spendDetailMeta.value?.title || '').startsWith('Stock Cut')
)

const hasItemCategories = (items) =>
  (items || []).some((item) => item?.category || item?.category_name)

const groupItemsByCategory = (items) => {
  const map = new Map()
  for (const item of items || []) {
    const category = String(item.category || item.category_name || 'Tanpa Category')
    if (!map.has(category)) {
      map.set(category, { category, items: [], subtotal: 0 })
    }
    const group = map.get(category)
    group.items.push(item)
    group.subtotal += Number(item.subtotal) || 0
  }
  return Array.from(map.values()).map((group) => ({
    ...group,
    subtotal: Math.round(group.subtotal * 100) / 100,
  }))
}

const fetchModal = async () => {
  modalLoading.value = true
  try {
    const params = {
      type: modalType.value,
      ...filterParams(),
      search: modalSearch.value,
      page: modalPage.value,
      per_page: ['revenue', 'total_spend', 'stock_cut', 'category_cost', 'mcs_purchase', 'purchase_category', 'begin_inventory', 'ending_inventory', 'kitchen_purchase', 'bar_purchase', 'service_purchase', 'outlet_transfer', 'outlet_adjustment', 'stock_opname', 'internal_warehouse_transfer', 'outlet_wip'].includes(modalType.value) ? 62 : 20,
    }
    if (['mcs_purchase', 'purchase_category'].includes(modalType.value) && mcsCategoryFilter.value) {
      params.category = mcsCategoryFilter.value
    }
    if (modalType.value === 'ending_inventory' && endingWarehouseFilter.value) {
      params.warehouse_id = endingWarehouseFilter.value
    }
    const { data } = await axios.get('/opex-outlet-dashboard/card-detail', { params })
    modalTrend.value = data.trend || []
    modalTxns.value = data.transactions || []
    modalSheetMeta.value = data.sheet_meta || null
    modalPagination.value = data.pagination || { total: 0, total_pages: 1 }
    invTxnDetail.value = null

    if (modalType.value === 'begin_inventory') {
      // Saat search aktif: expand semua hasil. Saat pertama buka: collapse.
      if (String(modalSearch.value || '').trim() !== '') {
        expandAllBeginCategories()
      } else if (Object.keys(expandedBeginCategories.value).length === 0) {
        // biarkan collapse by default
      }
    }
    if (modalType.value === 'ending_inventory') {
      if (String(modalSearch.value || '').trim() !== '') {
        expandAllEndingCategories()
      }
    }
  } catch (e) {
    console.error(e)
    alert('Gagal memuat detail')
  } finally {
    modalLoading.value = false
  }
}

const queueInvTxnSearch = () => {
  if (invTxnSearchTimer) window.clearTimeout(invTxnSearchTimer)
  invTxnSearchTimer = window.setTimeout(() => {
    fetchModal()
  }, 350)
}

const openInvTxnDetail = async (transactionId) => {
  invTxnDetailLoading.value = true
  try {
    const { data } = await axios.get('/opex-outlet-dashboard/card-detail', {
      params: {
        type: modalType.value,
        transaction_id: transactionId,
        ...filterParams(),
      },
    })
    invTxnDetail.value = {
      transaction: data.sheet_meta?.transaction || null,
      items: data.sheet_meta?.items || [],
    }
  } catch (e) {
    console.error(e)
    alert('Gagal memuat detail transaksi')
  } finally {
    invTxnDetailLoading.value = false
  }
}

const closeInvTxnDetail = () => {
  invTxnDetail.value = null
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

const beginInventorySourceLabel = (source) => {
  if (source === 'initial_balance') return 'Saldo awal (tgl 1)'
  if (source === 'none') return '—'
  return 'Stok sistem'
}

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
