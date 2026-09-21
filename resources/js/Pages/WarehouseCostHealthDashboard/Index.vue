<template>
  <AppLayout>
    <Head title="Warehouse Dashboard" />

    <div class="w-full min-h-screen bg-gray-50">
      <div class="w-full px-4 md:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Warehouse Dashboard</h1>
            <p class="text-gray-600 mt-1">
              Ringkas transaksi gudang + kesehatan MAC / cost.
              <a href="/warehouse-mac-anomaly-tracking" class="text-blue-600 underline">Scan detail anomali</a>
            </p>
          </div>
          <button
            type="button"
            @click="loadSnapshot"
            :disabled="loading"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 disabled:bg-blue-300 text-white text-sm hover:bg-blue-700"
          >
            <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }"></i>
            {{ loading ? 'Memuat…' : 'Muat ulang' }}
          </button>
        </div>

        <!-- Filters: bulan seperti Sales Outlet Dashboard -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Periode (Bulan)</label>
              <input
                v-model="selectedMonth"
                type="month"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
              <p v-if="periodLabel" class="text-xs text-gray-500 mt-2">Periode: {{ periodLabel }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse</label>
              <select
                v-model="filters.warehouse_id"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Semua warehouse</option>
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
              </select>
            </div>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                @click="resetFilters"
                class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
              >
                Reset
              </button>
              <button
                type="button"
                @click="loadSnapshot"
                :disabled="loading"
                class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
              >
                {{ loading ? 'Loading…' : 'Apply Filters' }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 text-sm text-red-800">
          <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ error }}
        </div>

        <div v-if="loading && !hasData" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
          <p class="text-gray-600 mt-2">Memuat data dashboard…</p>
        </div>

        <template v-else>
          <!-- Overview transaksi -->
          <div class="mb-2 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">Transaksi periode</h2>
            <span class="text-sm text-gray-500">Total: <strong>{{ formatNumber(transactions.total_transactions || 0) }}</strong></span>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3 mb-6">
            <button
              v-for="card in (transactions.summary || [])"
              :key="card.key"
              type="button"
              class="bg-white rounded-lg shadow-sm border p-4 text-left hover:border-blue-300 hover:shadow transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
              @click="openCard(card)"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-medium text-gray-500 leading-tight">{{ card.label }}</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1">{{ formatNumber(card.count) }}</p>
                  <p v-if="card.amount != null" class="text-xs text-emerald-700 mt-1 font-medium">{{ formatCurrency(card.amount) }}</p>
                  <div
                    v-if="card.amount_breakdown?.length"
                    class="mt-1.5 space-y-0.5"
                  >
                    <p
                      v-for="row in card.amount_breakdown"
                      :key="row.type"
                      class="text-[10px] text-slate-500 flex justify-between gap-2 leading-snug"
                    >
                      <span class="truncate">{{ row.label }}<span v-if="row.count" class="text-slate-400"> · {{ row.count }}</span></span>
                      <span class="tabular-nums text-slate-600 shrink-0">{{ formatCurrency(row.amount) }}</span>
                    </p>
                  </div>
                </div>
                <i :class="[card.icon, 'text-blue-500 text-lg shrink-0']"></i>
              </div>
              <p v-if="card.note" class="text-[10px] text-amber-600 mt-2">{{ card.note }}</p>
              <p class="mt-2 text-[11px] font-medium text-blue-600">Klik untuk lihat transaksi</p>
            </button>
          </div>

          <!-- Revenue: DO GSR + RWS + Penjualan Antar Gudang -->
          <div v-if="revenue?.total != null" class="mb-6">
            <div class="mb-2 flex items-center justify-between gap-3 flex-wrap">
              <h2 class="text-lg font-semibold text-gray-800">Revenue warehouse</h2>
              <span class="text-sm text-emerald-700 font-semibold">Total: {{ formatCurrency(revenue.total) }}</span>
            </div>
            <p class="text-xs text-slate-500 mb-3">
              Sumber: DO sudah GSR (harga FO × qty diterima, disamakan unit) · RWS · Penjualan Antar Gudang (gudang sumber).
              Division dari transaksi: Packing List / pilihan RWS — bukan dari master barang. “Tanpa Division” = transaksi tanpa division (mis. DO tanpa PL, WHS).
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
              <div
                v-for="src in (revenue.by_source || [])"
                :key="src.key"
                class="bg-white rounded-lg border p-3"
              >
                <p class="text-xs text-slate-500">{{ src.label }}</p>
                <p class="text-lg font-bold text-slate-900 mt-0.5 tabular-nums">{{ formatCurrency(src.amount) }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              <div
                v-for="wh in (revenue.by_warehouse || [])"
                :key="'wh-' + wh.warehouse_id"
                class="bg-white rounded-lg shadow-sm border p-4"
              >
                <div class="flex items-start justify-between gap-2 mb-2">
                  <div>
                    <p class="text-xs font-medium text-slate-500">Warehouse</p>
                    <p class="text-sm font-semibold text-slate-900">{{ wh.warehouse_name }}</p>
                  </div>
                  <p class="text-sm font-bold text-emerald-700 tabular-nums">{{ formatCurrency(wh.amount) }}</p>
                </div>
                <div class="space-y-1.5 border-t border-slate-100 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Per division</p>
                  <div
                    v-for="div in (wh.divisions || [])"
                    :key="(div.division_id || 'x') + '-' + div.division_name"
                    class="flex justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-600 truncate">{{ div.division_name }}</span>
                    <span class="tabular-nums text-slate-800 shrink-0 font-medium">{{ formatCurrency(div.amount) }}</span>
                  </div>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-50 grid grid-cols-3 gap-1 text-[10px] text-slate-400">
                  <span>DO {{ formatCurrency(wh.sources?.do_gsr || 0) }}</span>
                  <span>RWS {{ formatCurrency(wh.sources?.rws || 0) }}</span>
                  <span>WHS {{ formatCurrency(wh.sources?.warehouse_sales || 0) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Purchase: PO GR + Retail Food + WHS penerima -->
          <div v-if="purchase?.total != null" class="mb-6">
            <div class="mb-2 flex items-center justify-between gap-3 flex-wrap">
              <h2 class="text-lg font-semibold text-gray-800">Purchase warehouse</h2>
              <span class="text-sm text-sky-700 font-semibold">Total: {{ formatCurrency(purchase.total) }}</span>
            </div>
            <p class="text-xs text-slate-500 mb-3">
              Sumber: PO sudah GR (harga PO × qty GR, disamakan unit) · Warehouse Retail Food · Penjualan Antar Gudang (gudang penerima).
              Division mengikuti master per warehouse (Main Store = Perishable/Dry Store saja).
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
              <div
                v-for="src in (purchase.by_source || [])"
                :key="'p-' + src.key"
                class="bg-white rounded-lg border p-3"
              >
                <p class="text-xs text-slate-500">{{ src.label }}</p>
                <p class="text-lg font-bold text-slate-900 mt-0.5 tabular-nums">{{ formatCurrency(src.amount) }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              <div
                v-for="wh in (purchase.by_warehouse || [])"
                :key="'pwh-' + wh.warehouse_id"
                class="bg-white rounded-lg shadow-sm border p-4"
              >
                <div class="flex items-start justify-between gap-2 mb-2">
                  <div>
                    <p class="text-xs font-medium text-slate-500">Warehouse</p>
                    <p class="text-sm font-semibold text-slate-900">{{ wh.warehouse_name }}</p>
                  </div>
                  <p class="text-sm font-bold text-sky-700 tabular-nums">{{ formatCurrency(wh.amount) }}</p>
                </div>
                <div class="space-y-1.5 border-t border-slate-100 pt-2">
                  <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">Per division</p>
                  <div
                    v-for="div in (wh.divisions || [])"
                    :key="'pd-' + (div.division_id || 'x') + '-' + div.division_name"
                    class="flex justify-between gap-2 text-xs"
                  >
                    <span class="text-slate-600 truncate">{{ div.division_name }}</span>
                    <span class="tabular-nums text-slate-800 shrink-0 font-medium">{{ formatCurrency(div.amount) }}</span>
                  </div>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-50 grid grid-cols-3 gap-1 text-[10px] text-slate-400">
                  <span>GR {{ formatCurrency(wh.sources?.po_gr || 0) }}</span>
                  <span>RWF {{ formatCurrency(wh.sources?.retail_food || 0) }}</span>
                  <span>WHS-in {{ formatCurrency(wh.sources?.warehouse_sales_in || 0) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Cost health KPIs -->
          <h2 class="text-lg font-semibold text-gray-800 mb-2">Kesehatan cost / MAC</h2>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
              <p class="text-xs text-rose-600">Value yatim</p>
              <p class="text-2xl font-bold text-rose-900">{{ kpis.orphan_value ?? 0 }}</p>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
              <p class="text-xs text-amber-700">MAC stok terlalu besar</p>
              <p class="text-2xl font-bold text-amber-900">{{ kpis.absolute_high_stock ?? 0 }}</p>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
              <p class="text-xs text-orange-700">MAC stok minus</p>
              <p class="text-2xl font-bold text-orange-900">{{ kpis.negative_mac_stock ?? 0 }}</p>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
              <p class="text-xs text-blue-700">Anomali history 7 hari</p>
              <p class="text-2xl font-bold text-blue-900">{{ kpis.history_last_7_days ?? 0 }}</p>
            </div>
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
              <p class="text-xs text-indigo-700">Anomali history periode</p>
              <p class="text-2xl font-bold text-indigo-900">{{ kpis.history_in_period ?? 0 }}</p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
              <p class="text-xs text-gray-600">Total anomali</p>
              <p class="text-2xl font-bold text-gray-900">{{ kpis.total_anomalies ?? 0 }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Recent transactions -->
            <div class="bg-white rounded-lg shadow-sm border">
              <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Transaksi terbaru</h2>
                <span class="text-xs text-gray-500">{{ (transactions.recent || []).length }} baris</span>
              </div>
              <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 sticky top-0">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jenis</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nomor</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-if="!(transactions.recent || []).length">
                      <td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada transaksi di periode ini</td>
                    </tr>
                    <tr v-for="(row, idx) in transactions.recent" :key="idx" class="hover:bg-gray-50">
                      <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ row.date }}</td>
                      <td class="px-4 py-2 text-gray-700">{{ row.type }}</td>
                      <td class="px-4 py-2">
                        <a v-if="row.url" :href="row.url" class="text-blue-600 hover:underline font-medium">{{ row.number }}</a>
                        <span v-else>{{ row.number }}</span>
                      </td>
                      <td class="px-4 py-2 text-gray-600 text-xs">{{ row.warehouse_name }}</td>
                      <td class="px-4 py-2">
                        <span v-if="row.status" class="inline-flex px-2 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-700">{{ row.status }}</span>
                        <span v-else class="text-gray-300">—</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Module breakdown cost -->
            <div class="bg-white rounded-lg shadow-sm border">
              <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Sumber modul anomali MAC</h2>
              </div>
              <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 sticky top-0">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Modul</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-if="!moduleBreakdown.length">
                      <td colspan="2" class="px-4 py-8 text-center text-gray-400">Tidak ada anomali</td>
                    </tr>
                    <tr v-for="mod in moduleBreakdown" :key="(mod.reference_type || '') + mod.module_name" class="hover:bg-gray-50">
                      <td class="px-4 py-3">
                        <p class="font-medium text-gray-900">{{ mod.module_name }}</p>
                        <p class="text-xs text-gray-500">{{ mod.reference_label }}</p>
                      </td>
                      <td class="px-4 py-3 text-right font-semibold text-red-600">{{ mod.count }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm border">
              <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Top warehouse (anomali)</h2>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Yatim</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">History</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-if="!topWarehouses.length">
                      <td colspan="4" class="px-4 py-6 text-center text-gray-400">Tidak ada data</td>
                    </tr>
                    <tr v-for="row in topWarehouses" :key="row.warehouse_id" class="hover:bg-gray-50">
                      <td class="px-4 py-3 font-medium text-gray-900">{{ row.warehouse_name }}</td>
                      <td class="px-4 py-3 text-right font-semibold text-red-600">{{ row.count }}</td>
                      <td class="px-4 py-3 text-right text-rose-600">{{ row.orphan_value }}</td>
                      <td class="px-4 py-3 text-right text-gray-700">{{ row.history }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border">
              <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Top item bermasalah</h2>
                <p class="text-xs text-gray-500 mt-1">Berdasarkan cost stok saat ini (bukan puncak riwayat lama)</p>
              </div>
              <div class="overflow-x-auto max-h-[360px] overflow-y-auto">
                <table class="w-full min-w-[720px] text-sm">
                  <thead class="bg-gray-50 sticky top-0">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Barang</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Warehouse</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Hit</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cost stok</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">MAC max riwayat</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100">
                    <tr v-if="!topItems.length">
                      <td colspan="5" class="px-4 py-6 text-center text-gray-400">Tidak ada item yang masih bermasalah di stok saat ini</td>
                    </tr>
                    <tr v-for="item in topItems" :key="(item.warehouse_id || 0) + '-' + (item.item_id || item.inventory_item_id)" class="hover:bg-gray-50">
                      <td class="px-4 py-3">
                        <p class="font-medium text-gray-900">{{ item.item_name }}</p>
                        <p class="text-xs text-gray-500">{{ item.item_code || '—' }}</p>
                      </td>
                      <td class="px-4 py-3 text-gray-700">{{ item.warehouse_name }}</td>
                      <td class="px-4 py-3 text-right font-semibold">{{ item.count }}</td>
                      <td class="px-4 py-3 text-right font-mono" :class="Number(item.current_cost) > 100000 ? 'text-red-700' : 'text-slate-800'">
                        {{ item.current_cost != null ? formatNum(item.current_cost) : '—' }}
                      </td>
                      <td class="px-4 py-3 text-right font-mono text-slate-400 text-xs">{{ formatNum(item.max_mac) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Daily activity strip -->
          <div v-if="(transactions.daily || []).length" class="bg-white rounded-lg shadow-sm border p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Aktivitas harian (GR / Transfer / Retail / DO / Adjustment)</h2>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">GR</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Transfer</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Retail</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">DO</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Adj</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="d in transactions.daily" :key="d.date" class="hover:bg-gray-50">
                    <td class="px-3 py-2 text-gray-700">{{ d.date }}</td>
                    <td class="px-3 py-2 text-right">{{ d.gr }}</td>
                    <td class="px-3 py-2 text-right">{{ d.transfer }}</td>
                    <td class="px-3 py-2 text-right">{{ d.retail }}</td>
                    <td class="px-3 py-2 text-right">{{ d.do }}</td>
                    <td class="px-3 py-2 text-right">{{ d.adjustment }}</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ d.gr + d.transfer + d.retail + d.do + d.adjustment }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Shortcut</h2>
            <div class="mb-4">
              <p class="text-xs font-semibold uppercase text-gray-500 mb-2">Cost / MAC</p>
              <div class="flex flex-wrap gap-2">
                <a
                  v-for="s in costShortcuts"
                  :key="s.route"
                  :href="s.route"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-800 hover:bg-blue-50 hover:border-blue-200"
                >
                  <i :class="s.icon" class="text-blue-600"></i>
                  {{ s.label }}
                </a>
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-gray-500 mb-2">Warehouse ops</p>
              <div class="flex flex-wrap gap-2">
                <a
                  v-for="s in opsShortcuts"
                  :key="s.route"
                  :href="s.route"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-800 hover:bg-emerald-50 hover:border-emerald-200"
                >
                  <i :class="s.icon" class="text-emerald-600"></i>
                  {{ s.label }}
                </a>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Detail Modal (pola Opex Outlet Dashboard) -->
    <div
      v-if="modalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 p-4 backdrop-blur-[2px]"
      @click.self="closeModal"
    >
      <div class="bg-white rounded-3xl shadow-2xl w-full max-w-5xl max-h-[88vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between gap-4">
          <div>
            <h3 class="text-xl font-bold text-slate-900">{{ modalTitle }}</h3>
            <p class="text-sm text-slate-500">{{ periodLabel }}</p>
          </div>
          <div class="flex items-center gap-2">
            <a
              v-if="modalListRoute"
              :href="modalListRoute"
              class="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
            >
              Buka halaman
            </a>
            <button type="button" class="text-slate-400 hover:text-slate-700" @click="closeModal">
              <i class="fa-solid fa-xmark text-xl"></i>
            </button>
          </div>
        </div>

        <div class="px-5 py-4 overflow-y-auto flex-1 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
            <div>
              <label class="block text-[11px] font-medium text-slate-500 mb-1">Dari tanggal</label>
              <input v-model="modalDateFrom" type="date" class="w-full rounded-lg border-slate-200 text-sm" />
            </div>
            <div>
              <label class="block text-[11px] font-medium text-slate-500 mb-1">Sampai tanggal</label>
              <input v-model="modalDateTo" type="date" class="w-full rounded-lg border-slate-200 text-sm" />
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-2 items-end">
              <input
                v-model="modalSearch"
                type="text"
                :placeholder="modalSearchPlaceholder"
                class="flex-1 min-w-[180px] rounded-lg border-slate-200 text-sm"
                @keyup.enter="() => { modalPage = 1; fetchModal(); }"
              />
              <button type="button" class="px-3.5 py-2 rounded-lg bg-slate-900 text-white text-sm" @click="() => { modalPage = 1; fetchModal(); }">
                Cari
              </button>
            </div>
          </div>

          <div v-if="modalLoading" class="py-16 text-center text-slate-400">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat...
          </div>
          <div v-else-if="modalError" class="py-10 text-center text-rose-600 text-sm">
            {{ modalError }}
          </div>

          <template v-else>
            <div class="rounded-xl border border-slate-200 overflow-hidden">
              <table class="w-full table-fixed text-[13px] leading-snug">
                <thead>
                  <tr class="bg-slate-50/90 text-[11px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <th class="px-3 py-2 text-left font-semibold w-[10%]">Tanggal</th>
                    <th class="px-3 py-2 text-left font-semibold" :class="modalShowParty ? 'w-[16%]' : 'w-[20%]'">Nomor</th>
                    <th class="px-3 py-2 text-left font-semibold" :class="modalShowParty ? 'w-[18%]' : 'w-[28%]'">Warehouse</th>
                    <th v-if="modalShowParty" class="px-3 py-2 text-left font-semibold w-[26%]">Keterangan</th>
                    <th class="px-3 py-2 text-left font-semibold w-[12%]">Status</th>
                    <th class="px-3 py-2 text-left font-semibold w-[18%]">{{ modalUserLabel }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr
                    v-for="txn in modalTxns"
                    :key="txn.id + '-' + (txn.number || '')"
                    class="cursor-pointer hover:bg-slate-50 transition-colors"
                    @click="openTxn(txn)"
                  >
                    <td class="px-3 py-2 whitespace-nowrap text-slate-500 tabular-nums">
                      {{ formatDateOnly(txn.date) }}
                    </td>
                    <td class="px-3 py-2">
                      <span class="font-medium text-slate-800 truncate block" :title="txn.number || ''">
                        {{ txn.number || '—' }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <span class="text-slate-700 truncate block" :title="txn.warehouse_name || ''">
                        {{ txn.warehouse_name || '—' }}
                      </span>
                    </td>
                    <td v-if="modalShowParty" class="px-3 py-2">
                      <span class="text-slate-600 truncate block" :title="txn.party || ''">
                        {{ txn.party || '—' }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <span
                        v-if="txn.status"
                        class="inline-block max-w-full truncate px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[11px] font-medium"
                        :title="txn.status"
                      >{{ formatStatus(txn.status) }}</span>
                      <span v-else class="text-slate-300">—</span>
                    </td>
                    <td class="px-3 py-2">
                      <span
                        v-if="txn.user || txn.approver"
                        class="text-slate-600 truncate block"
                        :title="txn.user || txn.approver"
                      >{{ txn.user || txn.approver }}</span>
                      <span v-else class="text-slate-300">—</span>
                    </td>
                  </tr>
                  <tr v-if="!modalTxns.length">
                    <td :colspan="modalShowParty ? 6 : 5" class="px-3 py-10 text-center text-slate-400">Tidak ada transaksi</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <p class="text-xs text-slate-400">Klik baris untuk lihat detail item</p>

            <div v-if="modalPagination.total_pages > 1" class="flex justify-between items-center text-sm">
              <span class="text-slate-500">{{ formatNumber(modalPagination.total) }} transaksi</span>
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
            <p v-else class="text-sm text-slate-500">{{ formatNumber(modalPagination.total) }} transaksi</p>
          </template>
        </div>
      </div>
    </div>

    <!-- Nested detail modal (item lines) -->
    <div
      v-if="detailOpen"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4"
      @click.self="closeDetail"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900">
              {{ detailHeader?.type || 'Detail' }} · {{ detailHeader?.number || '—' }}
            </h2>
            <p class="text-sm text-slate-500 mt-1">
              {{ detailHeader?.date || '—' }}
              <span v-if="detailHeader?.warehouse"> · {{ detailHeader.warehouse }}</span>
              <span v-if="detailHeader?.status"> · {{ detailHeader.status }}</span>
              <span v-if="detailGrandTotal != null"> · {{ formatCurrency(detailGrandTotal) }}</span>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <a
              v-if="detailHeader?.url"
              :href="detailHeader.url"
              class="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
            >
              Buka halaman
            </a>
            <button type="button" class="text-slate-400 hover:text-slate-700 text-xl" @click="closeDetail">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
        </div>

        <div class="px-6 py-4 overflow-y-auto flex-1">
          <div v-if="detailLoading" class="py-12 text-center text-slate-500">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat detail...
          </div>
          <div v-else-if="detailError" class="py-8 text-center text-rose-600">{{ detailError }}</div>
          <div v-else>
            <div class="bg-slate-50 rounded-xl px-4 py-3 mb-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
              <div v-if="detailHeader?.party"><span class="text-slate-500">Keterangan:</span> <strong>{{ detailHeader.party }}</strong></div>
              <div v-if="detailHeader?.user"><span class="text-slate-500">{{ detailUserLabel }}:</span> <strong>{{ detailHeader.user }}</strong></div>
              <div v-if="detailHeader?.warehouse"><span class="text-slate-500">Warehouse:</span> <strong>{{ detailHeader.warehouse }}</strong></div>
              <div v-if="detailHeader?.status"><span class="text-slate-500">Status:</span> <strong>{{ detailHeader.status }}</strong></div>
              <div v-if="detailHeader?.note"><span class="text-slate-500">Catatan:</span> <strong>{{ detailHeader.note }}</strong></div>
              <div class="ml-auto text-slate-500">{{ detailItems.length }} item</div>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-xl">
              <table class="w-full text-sm table-fixed">
                <thead>
                  <tr class="bg-white border-b text-slate-600">
                    <th class="px-4 py-2 text-left" :class="detailShowPo ? 'w-[42%]' : 'w-[62%]'">Item</th>
                    <th class="px-4 py-2 text-right" :class="detailShowPo ? 'w-[22%]' : 'w-[38%]'">{{ detailPriceLabel }}</th>
                    <th v-if="detailShowPo" class="px-4 py-2 text-left w-[36%]">PO</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in detailItems" :key="idx" class="border-t border-slate-100 align-top">
                    <td class="px-4 py-3">
                      <p class="font-medium text-slate-800 leading-snug">{{ item.name }}</p>
                      <p v-if="item.code" class="text-xs text-slate-400 mt-0.5">{{ item.code }}</p>
                      <p class="text-xs text-slate-600 mt-1">
                        Qty <strong>{{ formatNum(item.qty) }}</strong><span v-if="item.unit"> {{ item.unit }}</span>
                      </p>
                      <p v-if="item.note" class="text-xs text-slate-400 mt-1">{{ item.note }}</p>
                      <p v-if="item.cost_note" class="text-[11px] text-amber-700 mt-1 leading-snug">{{ item.cost_note }}</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <p class="text-slate-800">{{ item.price != null ? formatCurrency(item.price) : '—' }}</p>
                      <p v-if="item.historical_cost != null" class="text-[10px] text-slate-400 line-through mt-0.5">
                        {{ formatCurrency(item.historical_cost) }}
                      </p>
                      <p class="text-xs text-slate-500 mt-0.5">Subtotal</p>
                      <p class="font-semibold text-slate-900">{{ item.subtotal != null ? formatCurrency(item.subtotal) : '—' }}</p>
                    </td>
                    <td v-if="detailShowPo" class="px-4 py-3">
                      <template v-if="item.po_number">
                        <a
                          :href="item.po_url || '#'"
                          class="text-blue-600 hover:underline font-medium"
                          @click.stop
                        >{{ item.po_number }}</a>
                        <p class="text-xs text-slate-500 mt-0.5">{{ formatShortDate(item.po_date) }}</p>
                        <p v-if="item.po_creator" class="text-xs text-slate-600 mt-0.5">{{ item.po_creator }}</p>
                        <p v-if="item.po_supplier" class="text-xs text-slate-500 mt-0.5">{{ item.po_supplier }}</p>
                      </template>
                      <span v-else class="text-slate-300">Belum ada PO</span>
                    </td>
                  </tr>
                  <tr v-if="!detailItems.length">
                    <td :colspan="detailShowPo ? 3 : 2" class="px-4 py-10 text-center text-slate-400">Tidak ada item</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <p v-if="detailGrandTotal != null" class="mt-4 text-right text-sm text-slate-700">
              Grand total: <strong>{{ formatCurrency(detailGrandTotal) }}</strong>
            </p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  warehouses: { type: Array, default: () => [] },
  historyCutoffDate: { type: String, default: '2024-01-01' },
  defaultFilters: { type: Object, default: () => ({}) },
  shortcuts: { type: Array, default: () => [] },
});

function currentMonthValue() {
  return new Date().toISOString().substring(0, 7);
}

function monthLabel(monthValue) {
  if (!monthValue) return '';
  const [year, month] = monthValue.split('-').map(Number);
  const label = new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
  return label.charAt(0).toUpperCase() + label.slice(1);
}

const selectedMonth = ref(props.defaultFilters.period || currentMonthValue());
const filters = ref({
  warehouse_id: props.defaultFilters.warehouse_id ?? '',
  max_mac: props.defaultFilters.max_mac ?? 10_000_000,
});

const loading = ref(false);
const error = ref('');
const kpis = ref({});
const typeBreakdown = ref({});
const moduleBreakdown = ref([]);
const topWarehouses = ref([]);
const topItems = ref([]);
const summary = ref(null);
const periodMeta = ref(null);
const transactions = ref({ summary: [], recent: [], daily: [], total_transactions: 0, revenue: null });
const revenue = computed(() => transactions.value?.revenue || null);
const purchase = computed(() => transactions.value?.purchase || null);

const modalOpen = ref(false);
const modalLoading = ref(false);
const modalType = ref('');
const modalTitle = ref('');
const modalListRoute = ref('');
const modalSearch = ref('');
const modalDateFrom = ref('');
const modalDateTo = ref('');
const modalPage = ref(1);
const modalTxns = ref([]);
const modalPagination = ref({ current_page: 1, per_page: 20, total: 0, total_pages: 1 });
const modalError = ref('');

const detailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const detailHeader = ref(null);
const detailItems = ref([]);
const detailGrandTotal = ref(null);

const periodLabel = computed(() => periodMeta.value?.label || monthLabel(selectedMonth.value));
const hasData = computed(() => (transactions.value.summary || []).length > 0 || Object.keys(kpis.value).length > 0);
const costShortcuts = computed(() => props.shortcuts.filter((s) => s.group === 'cost'));
const opsShortcuts = computed(() => props.shortcuts.filter((s) => s.group === 'ops'));
const modalUserLabel = computed(() => {
  if (modalType.value === 'food_good_receive') return 'User GR';
  if (modalType.value === 'pr_foods') return 'Approver';
  return 'User';
});
const modalShowParty = computed(() => modalType.value !== 'warehouse_transfer');
const modalSearchPlaceholder = computed(() => {
  if (modalType.value === 'food_good_receive') return 'Cari nomor / warehouse / status / user GR...';
  if (modalType.value === 'pr_foods') return 'Cari nomor / warehouse / status / approver...';
  if (modalType.value === 'warehouse_transfer') return 'Cari nomor / warehouse / status / user...';
  return 'Cari nomor / warehouse / status / user...';
});
const detailUserLabel = computed(() =>
  modalType.value === 'food_good_receive' ? 'User GR' : 'User'
);
const detailPriceLabel = computed(() =>
  detailHeader.value?.price_label
    || (['warehouse_transfer', 'stock_adjustment', 'internal_use_waste', 'warehouse_sales', 'outlet_rejection'].includes(modalType.value) ? 'Cost' : 'Harga')
);
const detailShowPo = computed(() => {
  if (detailHeader.value?.show_po === false) return false;
  if (detailHeader.value?.show_po === true) return true;
  return modalType.value === 'food_good_receive' || modalType.value === 'pr_foods';
});

const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0);
const formatCurrency = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
const formatNum = (value) => {
  const n = Number(value);
  if (Number.isNaN(n)) return value ?? '—';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n);
};
const formatShortDate = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};
const formatDateOnly = (value) => {
  if (!value) return '—';
  const s = String(value);
  return s.length >= 10 ? s.slice(0, 10) : s;
};
const formatStatus = (value) => {
  if (!value) return '—';
  return String(value)
    .replace(/[_-]+/g, ' ')
    .trim()
    .replace(/\b\w/g, (c) => c.toUpperCase());
};

function monthRange(monthValue) {
  if (!monthValue) return { date_from: '', date_to: '' };
  const [year, month] = monthValue.split('-').map(Number);
  const lastDay = new Date(year, month, 0).getDate();
  return {
    date_from: `${monthValue}-01`,
    date_to: `${monthValue}-${String(lastDay).padStart(2, '0')}`,
  };
}

const resetFilters = () => {
  selectedMonth.value = currentMonthValue();
  filters.value.warehouse_id = '';
  loadSnapshot();
};

const openCard = async (card) => {
  if (!card?.key) return;
  modalType.value = card.key;
  modalTitle.value = card.label || card.key;
  modalListRoute.value = card.route || '';
  modalSearch.value = '';
  modalPage.value = 1;
  const range = monthRange(selectedMonth.value);
  modalDateFrom.value = periodMeta.value?.date_from || range.date_from;
  modalDateTo.value = periodMeta.value?.date_to || range.date_to;
  modalOpen.value = true;
  await fetchModal();
};

const closeModal = () => {
  modalOpen.value = false;
  modalTxns.value = [];
  modalError.value = '';
};

const openTxn = async (txn) => {
  if (!txn?.id || !modalType.value) return;
  detailOpen.value = true;
  detailLoading.value = true;
  detailError.value = '';
  detailHeader.value = null;
  detailItems.value = [];
  detailGrandTotal.value = null;
  try {
    const { data } = await axios.get('/api/warehouse-cost-health-dashboard/transaction-detail', {
      params: { type: modalType.value, id: txn.id },
    });
    if (data.status !== 'success') {
      throw new Error(data.message || 'Gagal memuat detail');
    }
    detailHeader.value = data.header || null;
    detailItems.value = data.items || [];
    detailGrandTotal.value = data.grand_total ?? null;
  } catch (e) {
    detailError.value = e.response?.data?.message || e.message || 'Gagal memuat detail';
  } finally {
    detailLoading.value = false;
  }
};

const closeDetail = () => {
  detailOpen.value = false;
  detailHeader.value = null;
  detailItems.value = [];
  detailGrandTotal.value = null;
  detailError.value = '';
};

const fetchModal = async () => {
  modalLoading.value = true;
  modalError.value = '';
  try {
    const params = {
      type: modalType.value,
      period: selectedMonth.value,
      page: modalPage.value,
      per_page: 20,
      search: modalSearch.value || undefined,
      date_from: modalDateFrom.value || undefined,
      date_to: modalDateTo.value || undefined,
    };
    if (filters.value.warehouse_id) {
      params.warehouse_id = filters.value.warehouse_id;
    }
    const { data } = await axios.get('/api/warehouse-cost-health-dashboard/transactions', { params });
    if (data.status !== 'success') {
      throw new Error(data.message || 'Gagal memuat transaksi');
    }
    modalTitle.value = data.title || modalTitle.value;
    modalListRoute.value = data.list_route || modalListRoute.value;
    modalTxns.value = data.transactions || [];
    modalPagination.value = data.pagination || { current_page: 1, per_page: 20, total: 0, total_pages: 1 };
  } catch (e) {
    modalError.value = e.response?.data?.message || e.message || 'Gagal memuat transaksi';
    modalTxns.value = [];
  } finally {
    modalLoading.value = false;
  }
};

const loadSnapshot = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = {
      period: selectedMonth.value,
      max_mac: filters.value.max_mac,
    };
    if (filters.value.warehouse_id) {
      params.warehouse_id = filters.value.warehouse_id;
    }
    const { data } = await axios.get('/api/warehouse-cost-health-dashboard/snapshot', { params });
    if (data.status !== 'success') {
      throw new Error(data.message || 'Gagal memuat snapshot');
    }
    kpis.value = data.kpis || {};
    typeBreakdown.value = data.type_breakdown || {};
    moduleBreakdown.value = data.module_breakdown || [];
    topWarehouses.value = data.top_warehouses || [];
    topItems.value = data.top_items || [];
    summary.value = data.summary || null;
    periodMeta.value = data.period || null;
    transactions.value = data.transactions || { summary: [], recent: [], daily: [], total_transactions: 0, revenue: null };
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'Gagal memuat snapshot';
    kpis.value = {};
    moduleBreakdown.value = [];
    topWarehouses.value = [];
    topItems.value = [];
    transactions.value = { summary: [], recent: [], daily: [], total_transactions: 0, revenue: null };
  } finally {
    loading.value = false;
  }
};

watch(selectedMonth, () => {
  // period dipilih via Apply / klik card
});

onMounted(loadSnapshot);
</script>
