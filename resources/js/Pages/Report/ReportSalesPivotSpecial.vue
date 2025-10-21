<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-4 px-0">
      <h1 class="text-2xl font-bold mb-6">Report Rekap FJ</h1>
      <div v-if="dateRangeInfo" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-center gap-2 text-blue-800">
          <i class="fa fa-calendar text-blue-600"></i>
          <span class="font-medium">Rentang Tanggal:</span>
          <span>{{ dateRangeInfo }}</span>
        </div>
      </div>
      <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Dari:</label>
          <input v-model="from" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Sampai:</label>
          <input v-model="to" type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <input v-model="search" type="text" placeholder="Cari outlet..." class="px-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" />
        <button @click="setTodayRange" class="inline-flex items-center px-3 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 mr-2">
          <span class="mr-2"><i class="fas fa-calendar-day"></i></span>
          Hari Ini
        </button>
        <button @click="setThisWeekRange" class="inline-flex items-center px-3 py-2 bg-purple-600 text-white rounded-md font-semibold hover:bg-purple-700 mr-2">
          <span class="mr-2"><i class="fas fa-calendar-week"></i></span>
          Minggu Ini
        </button>
        <button @click="setThisMonthRange" class="inline-flex items-center px-3 py-2 bg-orange-600 text-white rounded-md font-semibold hover:bg-orange-700 mr-2">
          <span class="mr-2"><i class="fas fa-calendar-alt"></i></span>
          Bulan Ini
        </button>
        <button @click="reloadData" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
          <span class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Load Data
        </button>
        <button @click="exportToExcel" :disabled="!from || !to || !dataLoaded || !report.length" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
          <span class="mr-2"><i class="fas fa-file-excel"></i></span>
          Export Excel
        </button>
      </div>
      <div v-if="!from || !to" class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500 font-bold">
        Silakan pilih rentang tanggal terlebih dahulu
      </div>
      <div v-else-if="!dataLoaded" class="bg-white rounded-xl shadow-lg p-8 text-center text-gray-500 font-bold">
        Silakan klik "Load Data" untuk menampilkan laporan
      </div>
      <div v-else class="bg-white rounded-xl shadow-lg overflow-x-auto relative">
        <div v-if="loading" class="absolute inset-0 bg-white/70 z-20 flex items-center justify-center">
          <svg class="animate-spin h-12 w-12 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
        </div>
        <table class="min-w-full border border-gray-300">
          <thead>
            <tr class="bg-yellow-300 text-gray-900">
              <th class="px-4 py-2 border border-gray-300">Customer</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Main Store</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Chemical</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Stationary</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Marketing</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Main Kitchen</th>
              <th class="px-4 py-2 border border-gray-300 text-right">Line Total</th>
            </tr>
          </thead>
          <tbody>
            <!-- Outlet Group Header -->
            <tr v-if="groupedReport.outlets.length > 0" class="bg-blue-100 font-bold">
              <td colspan="7" class="px-4 py-2 border border-gray-300 text-center text-blue-800">
                <i class="fas fa-store mr-2"></i>OUTLET (is_outlet = 1)
              </td>
            </tr>
            <tr v-for="row in groupedReport.outlets" :key="'outlet-' + row.customer">
              <td class="px-4 py-2 border border-gray-200 flex items-center gap-2">
                {{ row.customer }}
                <button @click="showDetail(row.customer)" class="ml-2 bg-gradient-to-br from-yellow-400 to-yellow-600 text-white rounded-full shadow-lg px-2 py-1 hover:scale-110 transition-all font-bold" title="Lihat Detail">
                  <i class="fas fa-search-plus"></i>
                </button>
              </td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_store) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.chemical) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.stationary) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.marketing) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_kitchen) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right font-bold">{{ formatRupiah(row.line_total) }}</td>
            </tr>
            
            <!-- Outlet Group Subtotal -->
            <tr v-if="groupedReport.outlets.length > 0" class="bg-blue-50 font-semibold">
              <td class="px-4 py-2 border border-gray-300 text-right">SUBTOTAL OUTLET</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('main_store', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('chemical', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('stationary', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('marketing', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('main_kitchen', groupedReport.outlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('line_total', groupedReport.outlets)) }}</td>
            </tr>
            
            <!-- Non-Outlet Group Header -->
            <tr v-if="groupedReport.nonOutlets.length > 0" class="bg-green-100 font-bold">
              <td colspan="7" class="px-4 py-2 border border-gray-300 text-center text-green-800">
                <i class="fas fa-building mr-2"></i>NON-OUTLET (is_outlet = 0)
              </td>
            </tr>
            <tr v-for="row in groupedReport.nonOutlets" :key="'nonoutlet-' + row.customer">
              <td class="px-4 py-2 border border-gray-200 flex items-center gap-2">
                {{ row.customer }}
                <button @click="showDetail(row.customer)" class="ml-2 bg-gradient-to-br from-yellow-400 to-yellow-600 text-white rounded-full shadow-lg px-2 py-1 hover:scale-110 transition-all font-bold" title="Lihat Detail">
                  <i class="fas fa-search-plus"></i>
                </button>
              </td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_store) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.chemical) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.stationary) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.marketing) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_kitchen) }}</td>
              <td class="px-4 py-2 border border-gray-200 text-right font-bold">{{ formatRupiah(row.line_total) }}</td>
            </tr>
            
            <!-- Non-Outlet Group Subtotal -->
            <tr v-if="groupedReport.nonOutlets.length > 0" class="bg-green-50 font-semibold">
              <td class="px-4 py-2 border border-gray-300 text-right">SUBTOTAL NON-OUTLET</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('main_store', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('chemical', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('stationary', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('marketing', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('main_kitchen', groupedReport.nonOutlets)) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColGroup('line_total', groupedReport.nonOutlets)) }}</td>
            </tr>
            
            <!-- No Data Message -->
            <tr v-if="!groupedReport.outlets.length && !groupedReport.nonOutlets.length">
              <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data.</td>
            </tr>
          </tbody>
          <tfoot v-if="filteredReport.length">
            <tr class="bg-gray-100 font-bold">
              <td class="px-4 py-2 border border-gray-300 text-right">TOTAL KESELURUHAN</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('main_store')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('chemical')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('stationary')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('marketing')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('main_kitchen')) }}</td>
              <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalCol('line_total')) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div v-if="from && to && dataLoaded" class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-600">
          <span v-if="filteredReport.length">FJ: {{ filteredReport.length }} data ({{ groupedReport.outlets.length }} Outlet, {{ groupedReport.nonOutlets.length }} Non-Outlet)</span>
          <span v-if="filteredReport.length && filteredRetailReport.length"> | </span>
          <span v-if="filteredRetailReport.length">Retail: {{ filteredRetailReport.length }} data</span>
          <span v-if="(filteredReport.length || filteredRetailReport.length) && filteredWarehouseReport.length"> | </span>
          <span v-if="filteredWarehouseReport.length">Warehouse: {{ filteredWarehouseReport.length }} data</span>
        </div>
      </div>

      <!-- Tabel Retail Warehouse Sales -->
      <div v-if="from && to && dataLoaded" class="mt-8">
        <h2 class="text-xl font-bold mb-4 text-purple-800 flex items-center gap-2">
          <i class="fas fa-shopping-cart"></i>
          Retail Warehouse Sales
        </h2>
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto relative">
          <div v-if="loading" class="absolute inset-0 bg-white/70 z-20 flex items-center justify-center">
            <svg class="animate-spin h-12 w-12 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
          </div>
          <table class="min-w-full border border-gray-300">
            <thead>
              <tr class="bg-purple-300 text-gray-900">
                <th class="px-4 py-2 border border-gray-300">Customer</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Main Store</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Chemical</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Stationary</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Marketing</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Main Kitchen</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Line Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredRetailReport.length">
                <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data retail warehouse sales.</td>
              </tr>
              <tr v-for="row in filteredRetailReport" :key="'retail-' + row.customer">
                <td class="px-4 py-2 border border-gray-200 flex items-center gap-2">
                  {{ row.customer }}
                  <button @click="showRetailDetail(row.customer)" class="ml-2 bg-gradient-to-br from-purple-400 to-purple-600 text-white rounded-full shadow-lg px-2 py-1 hover:scale-110 transition-all font-bold" title="Lihat Detail">
                    <i class="fas fa-search-plus"></i>
                  </button>
                </td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_store) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.chemical) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.stationary) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.marketing) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_kitchen) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right font-bold">{{ formatRupiah(row.line_total) }}</td>
              </tr>
            </tbody>
            <tfoot v-if="filteredRetailReport.length">
              <tr class="bg-purple-100 font-bold">
                <td class="px-4 py-2 border border-gray-300 text-right">TOTAL RETAIL</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColRetail('main_store')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColRetail('chemical')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColRetail('stationary')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColRetail('marketing')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColRetail('main_kitchen')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColRetail('line_total')) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

      </div>

      <!-- Tabel Warehouse Sales (Penjualan Antar Gudang) -->
      <div v-if="from && to && dataLoaded" class="mt-8">
        <h2 class="text-xl font-bold mb-4 text-orange-800 flex items-center gap-2">
          <i class="fas fa-warehouse"></i>
          Warehouse Sales (Penjualan Antar Gudang)
        </h2>
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto relative">
          <div v-if="loading" class="absolute inset-0 bg-white/70 z-20 flex items-center justify-center">
            <svg class="animate-spin h-12 w-12 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
          </div>
          <table class="min-w-full border border-gray-300">
            <thead>
              <tr class="bg-orange-300 text-gray-900">
                <th class="px-4 py-2 border border-gray-300">Customer</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Main Store</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Chemical</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Stationary</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Marketing</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Main Kitchen</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Line Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredWarehouseReport.length">
                <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data warehouse sales.</td>
              </tr>
              <tr v-for="row in filteredWarehouseReport" :key="'warehouse-' + row.customer">
                <td class="px-4 py-2 border border-gray-200 flex items-center gap-2">
                  {{ row.customer }}
                  <button @click="showWarehouseDetail(row.customer)" class="ml-2 bg-gradient-to-br from-orange-400 to-orange-600 text-white rounded-full shadow-lg px-2 py-1 hover:scale-110 transition-all font-bold" title="Lihat Detail">
                    <i class="fas fa-search-plus"></i>
                  </button>
                </td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_store) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.chemical) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.stationary) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.marketing) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.main_kitchen) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right font-bold">{{ formatRupiah(row.line_total) }}</td>
              </tr>
            </tbody>
            <tfoot v-if="filteredWarehouseReport.length">
              <tr class="bg-orange-100 font-bold">
                <td class="px-4 py-2 border border-gray-300 text-right">TOTAL WAREHOUSE</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColWarehouse('main_store')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColWarehouse('chemical')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColWarehouse('stationary')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColWarehouse('marketing')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColWarehouse('main_kitchen')) }}</td>
                <td class="px-4 py-2 border border-gray-300 text-right">{{ formatRupiah(totalColWarehouse('line_total')) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

      </div>
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
        <div class="bg-gradient-to-br from-yellow-200 via-white to-yellow-100 rounded-3xl shadow-2xl p-8 min-w-[350px] max-w-2xl w-full relative animate-fade-in-3d">
          <button @click="showModal = false" class="absolute top-3 right-4 text-2xl text-yellow-700 hover:text-red-500 font-bold">&times;</button>
          <h2 class="text-xl font-bold mb-4 text-yellow-800 flex items-center gap-2"><i class="fas fa-list-alt"></i> Detail Penjualan: {{ modalCustomer }}</h2>
          
          <!-- Download Buttons -->
          <div class="mb-4 flex justify-end gap-2">
            <button 
              @click="downloadExcel"
              :disabled="loadingDetail || Object.keys(detailData).length === 0"
              class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <i class="fas fa-file-excel mr-2"></i>
              Download Excel
            </button>
            <button 
              @click="downloadPDF"
              :disabled="loadingDetail || Object.keys(detailData).length === 0"
              class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md font-semibold hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <i class="fas fa-file-pdf mr-2"></i>
              Download PDF
            </button>
          </div>
          <div v-if="loadingDetail" class="text-center py-8 text-yellow-600"><i class="fa fa-spinner fa-spin mr-2"></i>Loading...</div>
          <div v-else-if="Object.keys(detailData).length === 0" class="text-center py-8 text-gray-400">Tidak ada data detail.</div>
          <div v-else class="space-y-6 max-h-[60vh] overflow-y-auto">
            <!-- For FJ Detail (GR + Retail Food) -->
            <div v-if="!modalCustomer.includes('(Retail)') && !modalCustomer.includes('(Warehouse)')">
              <div v-for="(categoryData, cat) in detailData" :key="cat" class="rounded-xl shadow bg-white/80 p-4 border-l-8 border-yellow-400 mb-4">
                <div class="font-bold text-lg mb-2 flex items-center gap-2 text-yellow-700">
                  <i class="fa fa-folder-open"></i> {{ cat.charAt(0).toUpperCase() + cat.slice(1).replace('_', ' ') }}
                </div>
                
                <!-- GR Data -->
                <div v-if="categoryData.gr && categoryData.gr.length > 0" class="mb-4">
                  <div class="bg-blue-100 p-2 rounded-lg mb-2 border-l-4 border-blue-500">
                    <div class="font-semibold text-blue-800 text-sm">GR (Good Receipt)</div>
                  </div>
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="text-blue-700">
                        <th class="text-left py-1">Item</th>
                        <th class="text-left py-1">Category</th>
                        <th class="text-right py-1">Unit</th>
                        <th class="text-right py-1">Qty</th>
                        <th class="text-right py-1">Price</th>
                        <th class="text-right py-1">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in categoryData.gr" :key="'gr-' + item.item_name" class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="py-1">{{ item.item_name }}</td>
                        <td class="py-1">{{ item.category }}</td>
                        <td class="py-1 text-right">{{ item.unit }}</td>
                        <td class="py-1 text-right">{{ item.received_qty }}</td>
                        <td class="py-1 text-right">{{ formatRupiah(item.price) }}</td>
                        <td class="py-1 text-right font-bold">{{ formatRupiah(item.subtotal) }}</td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="font-bold bg-blue-50">
                        <td colspan="5" class="text-right py-1">Total GR:</td>
                        <td class="py-1 text-right">{{ formatRupiah(categoryData.gr.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0)) }}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- GR Supplier Data -->
                <div v-if="categoryData.gr_supplier && categoryData.gr_supplier.length > 0" class="mb-4">
                  <div class="bg-green-100 p-2 rounded-lg mb-2 border-l-4 border-green-500">
                    <div class="font-semibold text-green-800 text-sm">GR Supplier (Good Receive Outlet Supplier)</div>
                  </div>
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="text-green-700">
                        <th class="text-left py-1">Item</th>
                        <th class="text-left py-1">Category</th>
                        <th class="text-right py-1">Unit</th>
                        <th class="text-right py-1">Qty</th>
                        <th class="text-right py-1">Price</th>
                        <th class="text-right py-1">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in categoryData.gr_supplier" :key="'gr-supplier-' + item.item_name" class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="py-1">{{ item.item_name }}</td>
                        <td class="py-1">{{ item.category }}</td>
                        <td class="py-1 text-right">{{ item.unit }}</td>
                        <td class="py-1 text-right">{{ item.received_qty }}</td>
                        <td class="py-1 text-right">{{ formatRupiah(item.price) }}</td>
                        <td class="py-1 text-right font-bold">{{ formatRupiah(item.subtotal) }}</td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="font-bold bg-green-50">
                        <td colspan="5" class="text-right py-1">Total GR Supplier:</td>
                        <td class="py-1 text-right">{{ formatRupiah(categoryData.gr_supplier.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0)) }}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Retail Food Data -->
                <div v-if="categoryData.retail_food && categoryData.retail_food.length > 0" class="mb-4">
                  <div class="bg-purple-100 p-2 rounded-lg mb-2 border-l-4 border-purple-500">
                    <div class="font-semibold text-purple-800 text-sm">Retail Food</div>
                  </div>
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="text-purple-700">
                        <th class="text-left py-1">Item</th>
                        <th class="text-left py-1">Category</th>
                        <th class="text-right py-1">Unit</th>
                        <th class="text-right py-1">Qty</th>
                        <th class="text-right py-1">Price</th>
                        <th class="text-right py-1">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in categoryData.retail_food" :key="'rf-' + item.item_name" class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="py-1">{{ item.item_name }}</td>
                        <td class="py-1">{{ item.category }}</td>
                        <td class="py-1 text-right">{{ item.unit }}</td>
                        <td class="py-1 text-right">{{ item.received_qty }}</td>
                        <td class="py-1 text-right">{{ formatRupiah(item.price) }}</td>
                        <td class="py-1 text-right font-bold">{{ formatRupiah(item.subtotal) }}</td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="font-bold bg-purple-50">
                        <td colspan="5" class="text-right py-1">Total Retail Food:</td>
                        <td class="py-1 text-right">{{ formatRupiah(categoryData.retail_food.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0)) }}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Combined Total -->
                <div class="border-t-2 border-yellow-500 pt-2 mt-4">
                  <table class="w-full text-sm">
                    <tfoot>
                      <tr class="font-bold bg-yellow-50">
                        <td colspan="5" class="text-right py-1">Total {{ cat.charAt(0).toUpperCase() + cat.slice(1).replace('_', ' ') }}:</td>
                        <td class="py-1 text-right">{{ formatRupiah(categoryData.all.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0)) }}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>

            <!-- For Retail and Warehouse Detail (existing code) -->
            <div v-else>
              <div v-for="(items, cat) in detailData" :key="cat" class="rounded-xl shadow bg-white/80 p-4 border-l-8" :class="modalCustomer.includes('(Retail)') ? 'border-purple-400' : modalCustomer.includes('(Warehouse)') ? 'border-orange-400' : 'border-yellow-400'">
                <div class="font-bold text-lg mb-2 flex items-center gap-2" :class="modalCustomer.includes('(Retail)') ? 'text-purple-700' : modalCustomer.includes('(Warehouse)') ? 'text-orange-700' : 'text-yellow-700'">
                  <i class="fa fa-folder-open"></i> {{ cat }}
                </div>
                <table class="w-full text-sm">
                  <thead>
                    <tr :class="modalCustomer.includes('(Retail)') ? 'text-purple-700' : modalCustomer.includes('(Warehouse)') ? 'text-orange-700' : 'text-yellow-700'">
                    <th class="text-left py-1">Item</th>
                    <th class="text-right py-1">Qty</th>
                    <th class="text-right py-1">Unit</th>
                    <th class="text-right py-1">Harga</th>
                    <th class="text-right py-1">Subtotal</th>
                    <th v-if="modalCustomer.includes('(Retail)') || modalCustomer.includes('(Warehouse)')" class="text-right py-1">No. Sale</th>
                    <th v-if="modalCustomer.includes('(Retail)') || modalCustomer.includes('(Warehouse)')" class="text-right py-1">Tanggal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in items" :key="item.item_name">
                    <td class="py-1">{{ item.item_name }}</td>
                    <td class="py-1 text-right">
                      <span v-if="modalCustomer.includes('(Warehouse)')">
                        <div v-if="item.qty_small > 0">S: {{ item.qty_small }}</div>
                        <div v-if="item.qty_medium > 0">M: {{ item.qty_medium }}</div>
                        <div v-if="item.qty_large > 0">L: {{ item.qty_large }}</div>
                      </span>
                      <span v-else>{{ modalCustomer.includes('(Retail)') ? item.qty : item.received_qty }}</span>
                    </td>
                    <td class="py-1 text-right">{{ item.unit }}</td>
                    <td class="py-1 text-right">{{ formatRupiah(item.price) }}</td>
                    <td class="py-1 text-right font-bold">{{ formatRupiah(modalCustomer.includes('(Warehouse)') ? item.total : item.subtotal) }}</td>
                    <td v-if="modalCustomer.includes('(Retail)') || modalCustomer.includes('(Warehouse)')" class="py-1 text-right text-xs">{{ item.sale_number }}</td>
                    <td v-if="modalCustomer.includes('(Retail)') || modalCustomer.includes('(Warehouse)')" class="py-1 text-right text-xs">{{ new Date(item.sale_date).toLocaleDateString('id-ID') }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="font-bold bg-gray-100">
                    <td colspan="4" class="text-right py-1">Total {{ cat }}:</td>
                    <td class="py-1 text-right">{{ formatRupiah(items.reduce((sum, item) => sum + (Number(modalCustomer.includes('(Warehouse)') ? item.total : item.subtotal) || 0), 0)) }}</td>
                    <td v-if="modalCustomer.includes('(Retail)') || modalCustomer.includes('(Warehouse)')" colspan="2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  report: Array,
  retailReport: Array,
  warehouseReport: Array,
  filters: Object
});


const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const search = ref('');
const dataLoaded = ref(false);

// Computed untuk menampilkan info rentang tanggal
const dateRangeInfo = computed(() => {
  if (!from.value || !to.value) return '';
  
  const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  };
  
  return `${formatDate(from.value)} - ${formatDate(to.value)}`;
});

const filteredReport = computed(() => {
  let data = props.report;
  if (search.value) {
    const s = search.value.toLowerCase();
    data = data.filter(row => row.customer && row.customer.toLowerCase().includes(s));
  }
  return data;
});

// Computed untuk mengelompokkan data berdasarkan is_outlet
const groupedReport = computed(() => {
  const groups = {
    outlets: [],
    nonOutlets: []
  };
  
  filteredReport.value.forEach(row => {
    if (row.is_outlet == 1) {
      groups.outlets.push(row);
    } else {
      groups.nonOutlets.push(row);
    }
  });
  
  return groups;
});

// Computed untuk retail data
const filteredRetailReport = computed(() => {
  let data = props.retailReport || [];
  if (search.value) {
    const s = search.value.toLowerCase();
    data = data.filter(row => row.customer && row.customer.toLowerCase().includes(s));
  }
  return data;
});

// Computed untuk warehouse data
const filteredWarehouseReport = computed(() => {
  let data = props.warehouseReport || [];
  if (search.value) {
    const s = search.value.toLowerCase();
    data = data.filter(row => row.customer && row.customer.toLowerCase().includes(s));
  }
  return data;
});

watch([from, to], ([newFrom, newTo], [oldFrom, oldTo]) => { 
  // Reset dataLoaded when date range changes, unless it's the initial load
  if (oldFrom !== undefined || oldTo !== undefined) {
    dataLoaded.value = false; 
  }
});

function setTodayRange() {
  const today = new Date();
  const todayStr = today.toISOString().split('T')[0];
  from.value = todayStr;
  to.value = todayStr;
}

function setThisWeekRange() {
  const today = new Date();
  const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, etc.
  const monday = new Date(today);
  monday.setDate(today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1)); // Monday
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6); // Sunday
  
  from.value = monday.toISOString().split('T')[0];
  to.value = sunday.toISOString().split('T')[0];
}

function setThisMonthRange() {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  
  from.value = firstDay.toISOString().split('T')[0];
  to.value = lastDay.toISOString().split('T')[0];
}

const loading = ref(false);

onMounted(() => {
  // Inertia.js v3 doesn't have router.on/off methods
  // We'll handle loading state differently
});

onUnmounted(() => {
  // Cleanup if needed
});

function reloadData() {
  // Validasi tanggal
  if (!from.value || !to.value) {
    alert('Silakan pilih rentang tanggal terlebih dahulu');
    return;
  }
  
  if (new Date(from.value) > new Date(to.value)) {
    alert('Tanggal "Sampai" tidak boleh lebih kecil dari tanggal "Dari"');
    return;
  }
  
  dataLoaded.value = true;
  router.get('/report-rekap-fj', { from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });
}

function totalCol(key) {
  return filteredReport.value.reduce((sum, row) => sum + (Number(row[key]) || 0), 0);
}

function totalColGroup(key, groupData) {
  return groupData.reduce((sum, row) => sum + (Number(row[key]) || 0), 0);
}

function totalColRetail(key) {
  return filteredRetailReport.value.reduce((sum, row) => sum + (Number(row[key]) || 0), 0);
}

function totalColWarehouse(key) {
  return filteredWarehouseReport.value.reduce((sum, row) => sum + (Number(row[key]) || 0), 0);
}
function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value || 0);
}

const showModal = ref(false);
const modalCustomer = ref('');
const detailData = ref({});
const loadingDetail = ref(false);

async function showDetail(customer) {
  showModal.value = true;
  modalCustomer.value = customer;
  detailData.value = {};
  loadingDetail.value = true;
  try {
    const { data } = await axios.post('/api/report/fj-detail', {
      customer: customer,
      from: from.value,
      to: to.value
    });
    detailData.value = data;
  } catch (e) {
    detailData.value = {};
  } finally {
    loadingDetail.value = false;
  }
}

async function downloadPDF() {
  try {
    let endpoint = '/api/report/fj-detail-pdf';
    let customerName = modalCustomer.value;
    
    // Determine the correct endpoint and customer name based on modal type
    if (modalCustomer.value.includes('(Retail)')) {
      endpoint = '/api/report/retail-detail-pdf';
      customerName = modalCustomer.value.replace(' (Retail)', '');
    } else if (modalCustomer.value.includes('(Warehouse)')) {
      endpoint = '/api/report/warehouse-detail-pdf';
      customerName = modalCustomer.value.replace(' (Warehouse)', '');
    }
    
    const response = await axios.post(endpoint, {
      customer: customerName,
      from: from.value,
      to: to.value
    }, {
      responseType: 'blob'
    });
    
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    // Clean filename from invalid characters and ensure it's safe
    const cleanCustomer = modalCustomer.value
      .replace(/[^a-zA-Z0-9\s\-_]/g, '_')
      .trim()
      .replace(/\s+/g, '_');
    link.download = `Detail_${cleanCustomer}_${from.value}_${to.value}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error downloading PDF:', error)
    
    // Check if it's a server error with message
    if (error.response && error.response.data && error.response.data.error) {
      alert('Error: ' + error.response.data.error)
    } else if (error.response && error.response.status === 500) {
      alert('Terjadi kesalahan server saat generate PDF. Silakan coba lagi.')
    } else {
      alert('Terjadi kesalahan saat download PDF. Silakan coba lagi.')
    }
  }
}

async function downloadExcel() {
  try {
    let endpoint = '/api/report/fj-detail-excel';
    let customerName = modalCustomer.value;
    
    // Determine the correct endpoint and customer name based on modal type
    if (modalCustomer.value.includes('(Retail)')) {
      endpoint = '/api/report/retail-detail-excel';
      customerName = modalCustomer.value.replace(' (Retail)', '');
    } else if (modalCustomer.value.includes('(Warehouse)')) {
      endpoint = '/api/report/warehouse-detail-excel';
      customerName = modalCustomer.value.replace(' (Warehouse)', '');
    }
    
    const response = await axios.post(endpoint, {
      customer: customerName,
      from: from.value,
      to: to.value
    }, {
      responseType: 'blob'
    });
    
    const blob = new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    // Clean filename from invalid characters and ensure it's safe
    const cleanCustomer = modalCustomer.value
      .replace(/[^a-zA-Z0-9\s\-_]/g, '_')
      .trim()
      .replace(/\s+/g, '_');
    link.download = `Detail_${cleanCustomer}_${from.value}_${to.value}.xlsx`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error downloading Excel:', error)
    
    // Check if it's a server error with message
    if (error.response && error.response.data && error.response.data.error) {
      alert('Error: ' + error.response.data.error)
    } else if (error.response && error.response.status === 500) {
      alert('Terjadi kesalahan server saat generate Excel. Silakan coba lagi.')
    } else {
      alert('Terjadi kesalahan saat download Excel. Silakan coba lagi.')
    }
  }
}

async function showRetailDetail(customer) {
  showModal.value = true;
  modalCustomer.value = customer + ' (Retail)';
  detailData.value = {};
  loadingDetail.value = true;
  try {
    const { data } = await axios.post(route('report.retail-sales-detail'), {
      customer: customer,
      from: from.value,
      to: to.value
    });
    detailData.value = data;
  } catch (e) {
    detailData.value = {};
  } finally {
    loadingDetail.value = false;
  }
}

async function showWarehouseDetail(customer) {
  showModal.value = true;
  modalCustomer.value = customer + ' (Warehouse)';
  detailData.value = {};
  loadingDetail.value = true;
  try {
    const { data } = await axios.post(route('report.warehouse-sales-detail'), {
      customer: customer,
      from: from.value,
      to: to.value
    });
    detailData.value = data;
  } catch (e) {
    detailData.value = {};
  } finally {
    loadingDetail.value = false;
  }
}

async function exportToExcel() {
  if (!from.value || !to.value) {
    alert('Silakan pilih rentang tanggal terlebih dahulu');
    return;
  }
  
  if (!dataLoaded.value) {
    alert('Silakan load data terlebih dahulu');
    return;
  }
  
  try {
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="mr-2"><i class="fas fa-spinner fa-spin"></i></span>Exporting...';
    button.disabled = true;
    
    // Use axios to download the file
    const response = await axios.get(route('report.rekap-fj.export'), {
      params: { from: from.value, to: to.value },
      responseType: 'blob'
    });
    
    // Create blob and download
    const blob = new Blob([response.data], { 
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `sales_pivot_special_${from.value}_to_${to.value}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    // Reset button
    button.innerHTML = originalText;
    button.disabled = false;
    
  } catch (error) {
    console.error('Export error:', error);
    alert('Terjadi kesalahan saat export. Silakan coba lagi.');
    
    // Reset button
    const button = event.target;
    button.innerHTML = '<span class="mr-2"><i class="fas fa-file-excel"></i></span>Export Excel';
    button.disabled = false;
  }
}
</script>

<style scoped>
@keyframes fade-in-3d {
  from { opacity: 0; transform: scale(0.85) rotateX(10deg); }
  to { opacity: 1; transform: scale(1) rotateX(0); }
}
.animate-fade-in-3d {
  animation: fade-in-3d 0.5s cubic-bezier(.4,2,.3,1) both;
}
</style> 