<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-coins text-amber-500"></i>
          Cost Report
        </h1>
      </div>

      <!-- Filter -->
      <div class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
            <input
              type="month"
              v-model="filters.bulan"
              @change="loadReport"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div class="flex items-end gap-2">
            <button
              @click="loadReport"
              :disabled="loading || clearingCache"
              class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-1"></i>
              <i v-else class="fa-solid fa-search mr-1"></i>
              {{ loading ? 'Loading...' : 'Load Data' }}
            </button>
            <button
              @click="clearCacheAndReload"
              :disabled="loading || clearingCache || !filters.bulan"
              class="inline-flex items-center px-4 py-2 rounded bg-amber-600 text-white hover:bg-amber-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="clearingCache" class="fa-solid fa-spinner fa-spin mr-1"></i>
              <i v-else class="fa-solid fa-rotate mr-1"></i>
              {{ clearingCache ? 'Clearing...' : 'Clear Cache' }}
            </button>
            <a
              :href="exportUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700 transition"
            >
              <i class="fa-solid fa-file-excel mr-1"></i>
              Export to Excel
            </a>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-1" aria-label="Tabs">
          <button
            type="button"
            @click="switchTab('cost_inventory')"
            :class="activeTab === 'cost_inventory' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="px-4 py-3 border-b-2 font-medium text-sm transition"
          >
            Official Cost
          </button>
          <button
            type="button"
            @click="switchTab('cogs')"
            :class="activeTab === 'cogs' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="px-4 py-3 border-b-2 font-medium text-sm transition"
          >
            Actual Cost
          </button>
          <button
            type="button"
            @click="switchTab('category_cost')"
            :class="activeTab === 'category_cost' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="px-4 py-3 border-b-2 font-medium text-sm transition"
          >
            Category Cost
          </button>
        </nav>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-blue-500">
          <i class="fa-solid fa-spinner fa-spin text-4xl mb-4"></i>
          <p class="text-lg font-medium">Memuat data...</p>
        </div>
      </div>

      <!-- Tab: Cost Inventory -->
      <div v-else-if="activeTab === 'cost_inventory'" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Begin Inventory (Total MAC)</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Official Cost</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost RND</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet Transfer</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Barang Tersedia</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ending Inventory</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS Aktual</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Before Discount</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales After Discount</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Discount vs Sales</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS Before</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS After</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(row, index) in (reportRowsData || [])" :key="row.outlet_id">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ index + 1 }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.outlet_name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                <button
                  type="button"
                  class="font-medium text-blue-700 hover:text-blue-900 hover:underline"
                  title="Lihat detail begin inventory"
                  @click="openBeginInventoryDetail(row)"
                >
                  {{ formatNumber(row.total_begin_mac) }}
                </button>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                <button type="button" class="font-medium text-blue-700 hover:text-blue-900 hover:underline" title="Lihat detail official cost" @click="openOfficialCostDetail(row)">{{ formatNumber(row.official_cost) }}</button>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.cost_rnd) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right" :class="(row.outlet_transfer || 0) < 0 ? 'text-red-600' : 'text-gray-900'">{{ formatNumber(row.outlet_transfer) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ formatNumber(row.total_barang_tersedia) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.ending_inventory) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.cogs_aktual) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.sales_before_discount) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.discount) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.sales_after_discount) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_discount != null ? (Number(row.pct_discount).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.cogs_before != null ? (Number(row.cogs_before).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.cogs_after != null ? (Number(row.cogs_after).toFixed(2) + '%') : '-' }}</td>
            </tr>
            <tr v-if="!reportRowsData || reportRowsData.length === 0">
              <td colspan="15" class="px-4 py-8 text-center text-gray-500">Tidak ada data. Pilih bulan lalu klik Load Data.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tab: COGS (outlet sama dengan Cost Inventory, kolom COGS + Category Cost + Meal Employees) -->
      <div v-else-if="activeTab === 'cogs'" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Category Cost</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Meal Employees</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">COGS Pembanding</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Deviasi</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toleransi 2%</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% COGS Pembanding</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% COGS Actual Before Disc</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% COGS Actual After Disc</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% COGS Foods</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Deviasi</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Category Cost</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(row, index) in (cogsRowsData || [])" :key="row.outlet_id">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ index + 1 }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.outlet_name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.cogs) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.category_cost) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.meal_employees) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ formatNumber(row.cogs_pembanding) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right" :class="(row.deviasi || 0) < 0 ? 'text-red-600' : (row.deviasi || 0) > 0 ? 'text-green-600' : 'text-gray-900'">{{ formatNumber(row.deviasi) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.toleransi_2_pct) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_cogs_pembanding != null ? (Number(row.pct_cogs_pembanding).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_cogs_actual_before_disc != null ? (Number(row.pct_cogs_actual_before_disc).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_cogs_actual_after_disc != null ? (Number(row.pct_cogs_actual_after_disc).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_cogs_foods != null ? (Number(row.pct_cogs_foods).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-right" :class="(row.pct_deviasi || 0) < 0 ? 'text-red-600' : (row.pct_deviasi || 0) > 0 ? 'text-green-600' : 'text-gray-900'">{{ row.pct_deviasi != null ? (Number(row.pct_deviasi).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_category_cost != null ? (Number(row.pct_category_cost).toFixed(2) + '%') : '-' }}</td>
            </tr>
            <tr v-if="!cogsRowsData || cogsRowsData.length === 0">
              <td colspan="14" class="px-4 py-8 text-center text-gray-500">Tidak ada data. Pilih bulan lalu klik Load Data.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tab: Category Cost (Outlet, Guest Supplies, Spoilage, Waste, Non Commodity, Category Cost + masing-masing %) -->
      <div v-else-if="activeTab === 'category_cost'" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Guest Supplies</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Guest Supplies</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Spoilage</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Spoilage</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Waste</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Waste</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Non Commodity</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Non Commodity</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Category Cost</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">% Category Cost</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(row, index) in (categoryCostRowsData || [])" :key="row.outlet_id">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ index + 1 }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ row.outlet_name }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.guest_supplies) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_guest_supplies != null ? (Number(row.pct_guest_supplies).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.spoilage) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_spoilage != null ? (Number(row.pct_spoilage).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.waste) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_waste != null ? (Number(row.pct_waste).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatNumber(row.non_commodity) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_non_commodity != null ? (Number(row.pct_non_commodity).toFixed(2) + '%') : '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">{{ formatNumber(row.category_cost) }}</td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">{{ row.pct_category_cost != null ? (Number(row.pct_category_cost).toFixed(2) + '%') : '-' }}</td>
            </tr>
            <tr v-if="!categoryCostRowsData || categoryCostRowsData.length === 0">
              <td colspan="12" class="px-4 py-8 text-center text-gray-500">Tidak ada data. Pilih bulan lalu klik Load Data.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="showBeginDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="closeBeginInventoryDetail">
        <section class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="begin-inventory-detail-title">
          <header class="flex items-start justify-between border-b border-gray-200 px-5 py-4">
            <div>
              <h2 id="begin-inventory-detail-title" class="text-lg font-semibold text-gray-900">{{ beginDetailTitle }}</h2>
              <p class="mt-1 text-sm text-gray-500">{{ beginDetailTitle }} · {{ selectedOutlet?.outlet_name || '-' }} · {{ filters.bulan }}</p>
            </div>
            <button type="button" class="text-gray-500 hover:text-gray-900" title="Tutup detail" @click="closeBeginInventoryDetail">
              <i class="fa-solid fa-xmark text-xl"></i>
            </button>
          </header>

          <div class="grid grid-cols-1 gap-3 border-b border-gray-200 px-5 py-4 md:grid-cols-[minmax(0,1fr)_11rem_8rem_8rem]">
            <input v-model="beginDetailFilters.search" type="search" placeholder="Cari item, SKU, kategori, gudang..." class="w-full border border-gray-300 rounded-md px-3 py-2" @input="queueBeginInventorySearch" />
            <select v-model="beginDetailFilters.sort_by" class="border border-gray-300 rounded-md px-3 py-2" @change="loadBeginInventoryDetail(1)">
              <option :value="beginDetailMode === 'official_cost' ? 'amount' : 'begin_value'">Urutkan: Nilai</option>
              <option value="item_name">Urutkan: Nama item</option>
              <option value="item_sku">Urutkan: SKU</option>
              <option value="category_name">Urutkan: Kategori</option>
              <option :value="beginDetailMode === 'official_cost' ? 'transaction_date' : 'warehouse_name'">Urutkan: {{ beginDetailMode === 'official_cost' ? 'Tanggal' : 'Gudang' }}</option>
              <option v-if="beginDetailMode === 'official_cost'" value="source">Urutkan: Sumber</option>
              <option :value="beginDetailMode === 'official_cost' ? 'qty' : 'begin_qty_small'">Urutkan: Qty</option>
              <option :value="beginDetailMode === 'official_cost' ? 'unit_cost' : 'mac'">Urutkan: {{ beginDetailMode === 'official_cost' ? 'Harga' : 'MAC' }}</option>
            </select>
            <select v-model="beginDetailFilters.sort_direction" class="border border-gray-300 rounded-md px-3 py-2" @change="loadBeginInventoryDetail(1)">
              <option value="desc">Terbesar dahulu</option>
              <option value="asc">Terkecil dahulu</option>
            </select>
            <select v-model.number="beginDetailFilters.per_page" class="border border-gray-300 rounded-md px-3 py-2" @change="loadBeginInventoryDetail(1)">
              <option :value="10">10 / halaman</option>
              <option :value="25">25 / halaman</option>
              <option :value="50">50 / halaman</option>
              <option :value="100">100 / halaman</option>
            </select>
          </div>

          <div class="min-h-48 overflow-y-auto px-5 py-4">
            <div v-if="beginDetailLoading" class="py-12 text-center text-gray-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Memuat detail...</div>
            <div v-else-if="beginDetailError" class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ beginDetailError }}</div>
            <div v-else-if="beginDetailItems.length === 0" class="py-12 text-center text-gray-500">Tidak ada item pada data begin inventory.</div>
            <div v-else class="space-y-3">
              <section v-for="group in beginDetailGroups" :key="group.name" class="overflow-hidden rounded-md border border-gray-200">
                <button type="button" class="flex w-full items-center justify-between bg-gray-50 px-4 py-3 text-left hover:bg-gray-100" @click="toggleBeginDetailCategory(group.name)">
                  <span class="font-semibold text-gray-800"><i :class="isBeginDetailCategoryOpen(group.name) ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'" class="mr-2 text-xs"></i>{{ group.name }}</span>
                  <span class="text-sm text-gray-500">{{ group.items.length }} item · {{ formatNumber(group.total) }}</span>
                </button>
                <div v-show="isBeginDetailCategoryOpen(group.name)" class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-white text-xs uppercase text-gray-500">
                      <tr><th class="px-4 py-2 text-left">Item</th><th class="px-4 py-2 text-left">SKU</th><th class="px-4 py-2 text-left">{{ beginDetailMode === 'official_cost' ? 'Referensi' : 'Gudang' }}</th><th class="px-4 py-2 text-right">Qty</th><th class="px-4 py-2 text-right">{{ beginDetailMode === 'official_cost' ? 'Harga' : 'MAC' }}</th><th class="px-4 py-2 text-right">Nilai</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                      <tr v-for="item in group.items" :key="`${item.item_sku}-${item.reference_number || item.warehouse_name}`"><td class="px-4 py-2 text-gray-900">{{ item.item_name }}</td><td class="px-4 py-2 font-mono text-xs text-gray-600">{{ item.item_sku || '-' }}</td><td class="px-4 py-2 text-gray-600">{{ item.reference_number || item.warehouse_name }}</td><td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(item.qty ?? item.begin_qty_small) }}</td><td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(item.unit_cost ?? item.mac) }}</td><td class="px-4 py-2 text-right font-medium tabular-nums">{{ formatNumber(item.amount ?? item.begin_value) }}</td></tr>
                    </tbody>
                  </table>
                </div>
              </section>
            </div>
          </div>

          <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 py-3 text-sm text-gray-600">
            <span>{{ beginDetailPagination.total }} item · Halaman {{ beginDetailPagination.current_page }} / {{ beginDetailPagination.last_page }}</span>
            <div class="flex gap-2">
              <button type="button" :disabled="beginDetailLoading || beginDetailPagination.current_page <= 1" class="rounded-md border border-gray-300 px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-50" @click="loadBeginInventoryDetail(beginDetailPagination.current_page - 1)">Sebelumnya</button>
              <button type="button" :disabled="beginDetailLoading || beginDetailPagination.current_page >= beginDetailPagination.last_page" class="rounded-md border border-gray-300 px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-50" @click="loadBeginInventoryDetail(beginDetailPagination.current_page + 1)">Berikutnya</button>
            </div>
          </footer>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  reportRows: { type: Array, default: () => [] },
  cogsRows: { type: Array, default: () => [] },
  categoryCostRows: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ bulan: '' }) },
});

const loading = ref(false);
const clearingCache = ref(false);
const filters = ref({ ...props.filters });
const activeTab = ref('cost_inventory'); // 'cost_inventory' | 'cogs' | 'category_cost'
const reportRowsData = ref(props.reportRows || []);
const cogsRowsData = ref(props.cogsRows || []);
const categoryCostRowsData = ref(props.categoryCostRows || []);
const loadedTabs = ref({});
const showBeginDetail = ref(false);
const selectedOutlet = ref(null);
const beginDetailLoading = ref(false);
const beginDetailError = ref('');
const beginDetailItems = ref([]);
const beginDetailOpenCategories = ref({});
const beginDetailPagination = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
const beginDetailFilters = ref({ search: '', sort_by: 'begin_value', sort_direction: 'desc', per_page: 25 });
const beginDetailMode = ref('begin_inventory');
let beginDetailSearchTimer;

watch(() => props.filters, (v) => {
  filters.value = { ...v };
}, { immediate: true });

watch(() => props.reportRows, (v) => {
  reportRowsData.value = v || [];
}, { immediate: true });

watch(() => props.cogsRows, (v) => {
  cogsRowsData.value = v || [];
}, { immediate: true });

watch(() => props.categoryCostRows, (v) => {
  categoryCostRowsData.value = v || [];
}, { immediate: true });

const exportUrl = computed(() => {
  const bulan = filters.value.bulan || '';
  return `/cost-report/export?bulan=${encodeURIComponent(bulan)}`;
});

const beginDetailGroups = computed(() => {
  const groups = new Map();
  for (const item of beginDetailItems.value) {
    const name = item.category_name || 'Tanpa Kategori';
    if (!groups.has(name)) groups.set(name, { name, items: [], total: 0 });
    const group = groups.get(name);
    group.items.push(item);
    group.total += Number(item.amount ?? item.begin_value ?? 0);
  }
  return Array.from(groups.values());
});

const beginDetailTitle = computed(() => beginDetailMode.value === 'official_cost' ? 'Detail Official Cost' : 'Detail Begin Inventory');

function formatNumber(value) {
  if (value == null || value === '') return '0';
  const num = Number(value);
  if (isNaN(num)) return '0';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
}

function loadReport() {
  const bulan = filters.value.bulan || '';
  loadedTabs.value = {};
  reportRowsData.value = [];
  cogsRowsData.value = [];
  categoryCostRowsData.value = [];
  fetchTabData(activeTab.value, bulan, true);
}

function switchTab(tab) {
  activeTab.value = tab;
  const bulan = filters.value.bulan || '';
  if (!bulan) return;
  fetchTabData(tab, bulan, false);
}

async function fetchTabData(tab, bulan, force = false) {
  if (!bulan) return;

  const loadKey = `${bulan}:${tab}`;
  if (!force && loadedTabs.value[loadKey]) {
    return;
  }

  loading.value = true;
  try {
    const response = await axios.get('/cost-report/tab-data', {
      params: { bulan, tab },
    });

    if (response?.data?.success) {
      if (tab === 'cost_inventory') {
        reportRowsData.value = response.data.reportRows || [];
      } else if (tab === 'cogs') {
        cogsRowsData.value = response.data.cogsRows || [];
      } else if (tab === 'category_cost') {
        categoryCostRowsData.value = response.data.categoryCostRows || [];
      }
      loadedTabs.value[loadKey] = true;
    }
  } catch (error) {
    console.error('Failed to load cost report tab data:', error);
  } finally {
    loading.value = false;
  }
}

async function clearCacheAndReload() {
  const bulan = filters.value.bulan || '';
  if (!bulan) return;

  clearingCache.value = true;
  try {
    await axios.post('/cost-report/clear-cache', { bulan });
    loadedTabs.value = {};
    reportRowsData.value = [];
    cogsRowsData.value = [];
    categoryCostRowsData.value = [];
    await fetchTabData(activeTab.value, bulan, true);
  } catch (error) {
    console.error('Failed to clear cost report cache:', error);
  } finally {
    clearingCache.value = false;
  }
}

function openBeginInventoryDetail(row) {
  beginDetailMode.value = 'begin_inventory';
  selectedOutlet.value = row;
  beginDetailFilters.value.search = '';
  beginDetailFilters.value.sort_by = 'begin_value';
  beginDetailFilters.value.sort_direction = 'desc';
  beginDetailFilters.value.per_page = 25;
  beginDetailItems.value = [];
  beginDetailOpenCategories.value = {};
  beginDetailError.value = '';
  showBeginDetail.value = true;
  loadBeginInventoryDetail(1);
}

function openOfficialCostDetail(row) {
  beginDetailMode.value = 'official_cost';
  selectedOutlet.value = row;
  beginDetailFilters.value.search = '';
  beginDetailFilters.value.sort_by = 'amount';
  beginDetailFilters.value.sort_direction = 'desc';
  beginDetailFilters.value.per_page = 25;
  beginDetailItems.value = [];
  beginDetailOpenCategories.value = {};
  beginDetailError.value = '';
  showBeginDetail.value = true;
  loadBeginInventoryDetail(1);
}

function closeBeginInventoryDetail() {
  showBeginDetail.value = false;
  window.clearTimeout(beginDetailSearchTimer);
}

function queueBeginInventorySearch() {
  window.clearTimeout(beginDetailSearchTimer);
  beginDetailSearchTimer = window.setTimeout(() => loadBeginInventoryDetail(1), 300);
}

async function loadBeginInventoryDetail(page) {
  if (!selectedOutlet.value || !filters.value.bulan) return;
  beginDetailLoading.value = true;
  beginDetailError.value = '';
  try {
    const isOfficialCost = beginDetailMode.value === 'official_cost';
    const response = await axios.get(isOfficialCost ? '/cost-report/official-cost-detail' : '/cost-report/begin-inventory-detail', {
      params: {
        bulan: filters.value.bulan,
        outlet_id: selectedOutlet.value.outlet_id,
        search: beginDetailFilters.value.search || undefined,
        sort_by: beginDetailFilters.value.sort_by,
        sort_direction: beginDetailFilters.value.sort_direction,
        per_page: beginDetailFilters.value.per_page,
        page,
      },
    });
    if (!response?.data?.success) throw new Error('Gagal memuat detail begin inventory.');
    beginDetailItems.value = response.data.items || [];
    beginDetailPagination.value = response.data.pagination || beginDetailPagination.value;
    beginDetailOpenCategories.value = Object.fromEntries(beginDetailGroups.value.map((group) => [group.name, true]));
  } catch (error) {
    beginDetailItems.value = [];
    beginDetailError.value = error?.response?.data?.message || error.message || 'Gagal memuat detail begin inventory.';
  } finally {
    beginDetailLoading.value = false;
  }
}

function isBeginDetailCategoryOpen(category) {
  return beginDetailOpenCategories.value[category] !== false;
}

function toggleBeginDetailCategory(category) {
  beginDetailOpenCategories.value[category] = !isBeginDetailCategoryOpen(category);
}
</script>
