<template>
  <Head title="Tracking Nomor Seri" />
  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
          <div class="p-3 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg">
            <i class="fa-solid fa-barcode text-white text-xl"></i>
          </div>
          <span>Tracking Nomor Seri</span>
        </h1>
        <p class="text-gray-600 ml-16">Lacak serial berdasarkan dokumen sumber atau nomor seri langsung</p>
      </div>

      <div class="flex gap-2 mb-6 border-b border-gray-200">
        <button
          type="button"
          class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors"
          :class="activeTab === 'document' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'document'"
        >
          <i class="fa-solid fa-file-lines mr-1"></i> Per Dokumen
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors"
          :class="activeTab === 'serial' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeTab = 'serial'"
        >
          <i class="fa-solid fa-magnifying-glass mr-1"></i> Per Nomor Seri
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors"
          :class="activeTab === 'pending' ? 'border-amber-600 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="switchToPendingTab"
        >
          <i class="fa-solid fa-clock mr-1"></i> Belum GR Outlet
          <span v-if="pendingSummary.total_serials > 0" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800">{{ pendingSummary.total_serials }}</span>
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors"
          :class="activeTab === 'rejects' ? 'border-red-600 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="switchToRejectsTab"
        >
          <i class="fa-solid fa-ban mr-1"></i> GR Ditolak
        </button>
      </div>

      <!-- Tab: Per Dokumen -->
      <div v-show="activeTab === 'document'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Sumber Dokumen</label>
              <select v-model="docFilters.source_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">-- Pilih --</option>
                <option v-for="st in sourceTypes" :key="st.value" :value="st.value">{{ st.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Cari No. Dokumen</label>
              <input v-model="docFilters.search" type="text" placeholder="GR-..., batch..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @keyup.enter="searchDocuments(1)" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Dari Tanggal</label>
              <input v-model="docFilters.date_from" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Sampai Tanggal</label>
              <input v-model="docFilters.date_to" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="mt-4 flex gap-2">
            <button type="button" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50" :disabled="!docFilters.source_type || docLoading" @click="searchDocuments(1)">
              <i class="fa-solid fa-search mr-1"></i> Cari Dokumen
            </button>
            <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200" @click="resetDocSearch">Reset</button>
          </div>
        </div>

        <div v-if="docResults.length" class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
          <table class="w-full min-w-full text-sm">
            <thead class="bg-indigo-600 text-white">
              <tr>
                <th class="px-4 py-3 text-left">No. Dokumen</th>
                <th class="px-4 py-3 text-left">Tanggal</th>
                <th class="px-4 py-3 text-right">Jumlah Serial</th>
                <th class="px-4 py-3 text-left">Generate Terakhir</th>
                <th class="px-4 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in docResults" :key="`${row.source_type}-${row.source_id}`" class="hover:bg-indigo-50">
                <td class="px-4 py-3">
                  <a v-if="row.document_url" :href="row.document_url" class="font-mono font-semibold text-indigo-700 hover:underline" target="_blank">{{ row.document_number }}</a>
                  <span v-else class="font-mono">{{ row.document_number }}</span>
                </td>
                <td class="px-4 py-3">{{ formatDate(row.document_date) }}</td>
                <td class="px-4 py-3 text-right font-semibold">{{ row.serial_count }}</td>
                <td class="px-4 py-3">{{ formatDateTime(row.last_generated_at) }}</td>
                <td class="px-4 py-3 text-center">
                  <button type="button" class="px-3 py-1 text-xs rounded-lg bg-indigo-100 text-indigo-700 hover:bg-indigo-200 font-semibold" @click="openDocumentSerials(row)">
                    Lihat Serial
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="docPagination.last_page > 1" class="flex justify-center gap-2 p-4 border-t">
            <button type="button" class="px-3 py-1 text-sm rounded border disabled:opacity-40" :disabled="docPagination.current_page <= 1" @click="searchDocuments(docPagination.current_page - 1)">Prev</button>
            <span class="px-3 py-1 text-sm text-gray-600">{{ docPagination.current_page }} / {{ docPagination.last_page }}</span>
            <button type="button" class="px-3 py-1 text-sm rounded border disabled:opacity-40" :disabled="docPagination.current_page >= docPagination.last_page" @click="searchDocuments(docPagination.current_page + 1)">Next</button>
          </div>
        </div>
        <p v-else-if="docSearched && !docLoading" class="text-center text-gray-500 py-8">Tidak ada dokumen ditemukan.</p>
      </div>

      <!-- Tab: Per Nomor Seri -->
      <div v-show="activeTab === 'serial'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Nomor Seri</label>
          <div class="flex flex-col sm:flex-row gap-3">
            <input
              v-model="serialQuery"
              type="text"
              placeholder="Scan atau ketik nomor seri..."
              class="flex-1 border border-gray-300 rounded-lg px-4 py-3 font-mono text-lg"
              @keyup.enter="lookupSerial"
            />
            <button type="button" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 disabled:opacity-50" :disabled="serialQuery.length < 2 || serialLoading" @click="lookupSerial">
              <i class="fa-solid fa-route mr-1"></i> Lacak
            </button>
          </div>
        </div>

        <div v-if="serialSuggestions.length" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <p class="text-sm font-semibold text-amber-800 mb-2">Serial tidak exact match. Pilih dari daftar:</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="s in serialSuggestions"
              :key="s.id"
              type="button"
              class="px-3 py-1 bg-white border border-amber-300 rounded-lg font-mono text-sm hover:bg-amber-100"
              @click="serialQuery = s.serial_number; lookupSerial()"
            >
              {{ s.serial_number }} <span class="text-gray-500 text-xs">({{ s.item_name }})</span>
            </button>
          </div>
        </div>

        <div v-if="serialDetail" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
          <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
              <p class="text-sm text-gray-500 mb-1">Nomor Seri</p>
              <p class="text-2xl font-mono font-bold text-indigo-700">{{ serialDetail.serial_number }}</p>
            </div>
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold" :class="statusBadgeClass(serialDetail.status?.color)">
              {{ serialDetail.status?.label }}
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 text-sm">
            <div><span class="text-gray-500">Item:</span> <strong>{{ serialDetail.item_name }}</strong> <span v-if="serialDetail.item_sku" class="text-gray-400">({{ serialDetail.item_sku }})</span></div>
            <div><span class="text-gray-500">Unit:</span> <strong>{{ serialDetail.unit_name || '-' }}</strong></div>
            <div><span class="text-gray-500">Gudang:</span> <strong>{{ serialDetail.warehouse_name || '-' }}</strong></div>
            <div>
              <span class="text-gray-500">Sumber:</span>
              <strong>{{ serialDetail.source_type_label }}</strong>
              <a v-if="serialDetail.source_document_url" :href="serialDetail.source_document_url" class="ml-1 text-indigo-600 hover:underline font-mono" target="_blank">{{ serialDetail.source_document_number }}</a>
              <span v-else-if="serialDetail.source_document_number" class="ml-1 font-mono">{{ serialDetail.source_document_number }}</span>
            </div>
            <div><span class="text-gray-500">Generate:</span> <strong>{{ formatDateTime(serialDetail.generated_at) }}</strong></div>
            <div v-if="serialDetail.ref_gr_number"><span class="text-gray-500">Ref GR:</span> <strong class="font-mono">{{ serialDetail.ref_gr_number }}</strong></div>
          </div>

          <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-timeline text-indigo-500"></i> Riwayat Pergerakan
          </h3>
          <div v-if="serialTimeline.length" class="relative border-l-2 border-indigo-200 ml-3 space-y-6 pl-8">
            <div v-for="(ev, idx) in serialTimeline" :key="idx" class="relative">
              <span class="absolute -left-[2.15rem] top-1 w-4 h-4 rounded-full bg-indigo-500 ring-4 ring-white"></span>
              <p class="text-xs text-gray-500">{{ formatDateTime(ev.at) }}</p>
              <p class="font-semibold text-gray-900">{{ ev.label }}</p>
              <p v-if="ev.document_label" class="text-sm text-gray-700 mt-0.5">
                {{ ev.document_label }}
                <a v-if="ev.document_url" :href="ev.document_url" class="text-indigo-600 hover:underline font-mono ml-1" target="_blank">{{ ev.document_number }}</a>
                <span v-else-if="ev.document_number" class="font-mono ml-1">{{ ev.document_number }}</span>
              </p>
              <p v-if="ev.notes" class="text-sm text-gray-500 mt-0.5">{{ ev.notes }}</p>
              <template v-if="ev.movement_type === 'outlet_receive'">
                <p v-if="ev.outlet_name" class="text-xs text-gray-600 mt-0.5">
                  <span class="text-gray-500">Outlet:</span> {{ ev.outlet_name }}
                </p>
                <p v-if="ev.warehouse_name" class="text-xs text-gray-600">
                  <span class="text-gray-500">Warehouse:</span> {{ ev.warehouse_name }}
                </p>
              </template>
              <p v-if="ev.moved_by_name" class="text-xs text-gray-400 mt-0.5">
                {{ ev.movement_type === 'outlet_receive' ? 'Penerima' : 'Oleh' }}: {{ ev.moved_by_name }}
              </p>
            </div>
          </div>
          <p v-else class="text-gray-500 text-sm">Belum ada riwayat pergerakan tercatat.</p>
        </div>
      </div>

      <!-- Tab: Belum GR Outlet (sudah DO, belum terima) -->
      <div v-show="activeTab === 'pending'" class="space-y-6">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900">
          <i class="fa-solid fa-circle-info mr-1"></i>
          Daftar <strong>Delivery Order</strong> yang sudah dispatch serial tetapi <strong>belum diterima outlet</strong> (GR Nomor Seri). Klik baris DO untuk melihat nomor seri yang belum di-GR.
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div v-if="isHQ">
              <label class="block text-sm font-semibold text-gray-700 mb-1">Outlet</label>
              <select v-model="pendingFilters.outlet_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @change="onPendingOutletChange">
                <option value="">Semua outlet</option>
                <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div v-if="isHQ && pendingWarehouseOutlets.length">
              <label class="block text-sm font-semibold text-gray-700 mb-1">Warehouse Outlet</label>
              <select v-model="pendingFilters.warehouse_outlet_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Semua WH outlet</option>
                <option v-for="w in pendingWarehouseOutlets" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">No. DO</label>
              <input v-model="pendingFilters.do_number" type="text" placeholder="D02..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" @keyup.enter="loadPending(1)" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Nomor Seri</label>
              <input v-model="pendingFilters.serial_number" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" @keyup.enter="loadPending(1)" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">Cari umum</label>
              <input v-model="pendingFilters.search" type="text" placeholder="serial, DO, item, outlet..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @keyup.enter="loadPending(1)" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">DO / keluar dari</label>
              <input v-model="pendingFilters.date_from" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1">sampai</label>
              <input v-model="pendingFilters.date_to" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="mt-4 flex gap-2">
            <button type="button" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-semibold hover:bg-amber-700 disabled:opacity-50" :disabled="pendingLoading" @click="loadPending(1)">
              <i class="fa-solid fa-search mr-1"></i> Tampilkan
            </button>
            <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200" @click="resetPending">Reset</button>
          </div>
        </div>

        <div v-if="pendingLoaded" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-white rounded-xl border border-amber-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase font-semibold">Total Serial</p>
            <p class="text-2xl font-bold text-amber-700">{{ pendingSummary.total_serials }}</p>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase font-semibold">Jumlah DO</p>
            <p class="text-2xl font-bold text-gray-800">{{ pendingSummary.distinct_do }}</p>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase font-semibold">Jumlah Outlet</p>
            <p class="text-2xl font-bold text-gray-800">{{ pendingSummary.distinct_outlet }}</p>
          </div>
        </div>

        <div v-if="pendingDoList.length" class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-x-auto">
          <table class="w-full min-w-[960px] text-sm">
            <thead class="bg-amber-600 text-white">
              <tr>
                <th class="px-3 py-3 w-10"></th>
                <th class="px-4 py-3 text-left">Delivery Order</th>
                <th class="px-4 py-3 text-left">Tgl DO / Keluar</th>
                <th class="px-4 py-3 text-left">Outlet</th>
                <th class="px-4 py-3 text-left">Warehouse Outlet</th>
                <th class="px-4 py-3 text-center">Belum GR</th>
                <th class="px-4 py-3 text-center">Hari</th>
                <th class="px-4 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="doRow in pendingDoList" :key="doRow.do_id">
                <tr
                  class="border-t border-gray-100 cursor-pointer hover:bg-amber-50 transition-colors"
                  :class="isPendingDoExpanded(doRow.do_id) ? 'bg-amber-50/80' : ''"
                  @click="togglePendingDo(doRow.do_id)"
                >
                  <td class="px-3 py-3 text-center text-amber-700">
                    <i class="fa-solid transition-transform" :class="isPendingDoExpanded(doRow.do_id) ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                  </td>
                  <td class="px-4 py-3">
                    <a :href="doRow.do_url" class="font-mono font-semibold text-amber-700 hover:underline" target="_blank" @click.stop>{{ doRow.do_number }}</a>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(doRow.display_date) }}</td>
                  <td class="px-4 py-3">{{ doRow.outlet_name }}</td>
                  <td class="px-4 py-3">{{ doRow.warehouse_outlet_name }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                      {{ doRow.pending_serial_count }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="doRow.days_pending > 7 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'">
                      {{ doRow.days_pending ?? '-' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center" @click.stop>
                    <a :href="doRow.do_url" class="text-xs text-amber-700 hover:underline font-semibold" target="_blank">Buka DO</a>
                  </td>
                </tr>
                <tr v-if="isPendingDoExpanded(doRow.do_id)" class="bg-gray-50">
                  <td colspan="8" class="px-4 py-3">
                    <div class="rounded-xl border border-amber-200 overflow-hidden">
                      <table class="w-full text-sm">
                        <thead class="bg-amber-100/80 text-amber-900">
                          <tr>
                            <th class="px-4 py-2 text-left">Nomor Seri</th>
                            <th class="px-4 py-2 text-left">Item</th>
                            <th class="px-4 py-2 text-left">SKU</th>
                            <th class="px-4 py-2 text-left">Unit</th>
                            <th class="px-4 py-2 text-center">Aksi</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100 bg-white">
                          <tr v-for="sn in doRow.serials" :key="sn.serial_id" class="hover:bg-amber-50/50">
                            <td class="px-4 py-2 font-mono font-semibold text-indigo-700">{{ sn.serial_number }}</td>
                            <td class="px-4 py-2">{{ sn.item_name || '-' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ sn.item_sku || '-' }}</td>
                            <td class="px-4 py-2">{{ sn.unit_name || '-' }}</td>
                            <td class="px-4 py-2 text-center">
                              <button type="button" class="text-xs text-indigo-600 hover:underline font-semibold" @click="trackPendingSerial(sn.serial_number)">Lacak</button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
          <div v-if="pendingPagination.last_page > 1" class="flex justify-center gap-2 p-4 border-t">
            <button type="button" class="px-3 py-1 text-sm rounded border disabled:opacity-40" :disabled="pendingPagination.current_page <= 1" @click="loadPending(pendingPagination.current_page - 1)">Prev</button>
            <span class="px-3 py-1 text-sm text-gray-600">{{ pendingPagination.current_page }} / {{ pendingPagination.last_page }}</span>
            <button type="button" class="px-3 py-1 text-sm rounded border disabled:opacity-40" :disabled="pendingPagination.current_page >= pendingPagination.last_page" @click="loadPending(pendingPagination.current_page + 1)">Next</button>
          </div>
        </div>
        <p v-else-if="pendingLoaded && !pendingLoading" class="text-center text-gray-500 py-8">Tidak ada DO dengan serial menunggu GR outlet.</p>
        <div v-if="pendingLoading" class="text-center py-8 text-gray-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Memuat...</div>
      </div>

      <!-- Tab: GR Ditolak -->
      <div v-show="activeTab === 'rejects'" class="space-y-6">
        <div
          v-if="!rejectLogsTableReady"
          class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
          <i class="fa-solid fa-triangle-exclamation mr-1"></i>
          Tabel <code class="bg-amber-100 px-1 rounded">outlet_serial_receive_reject_logs</code> belum ada.
          Jalankan <code class="bg-amber-100 px-1 rounded">database/sql/create_outlet_serial_receive_reject_logs.sql</code>.
        </div>
        <template v-else>
          <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-900">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Log percobaan <strong>GR Nomor Seri yang ditolak</strong>. Serial tetap <strong>tidak</strong> masuk GR — hanya dicatat untuk audit.
          </div>
          <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
              <div v-if="isHQ">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Outlet</label>
                <select v-model="rejectFilters.outlet_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                  <option value="">Semua outlet</option>
                  <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Alasan</label>
                <select v-model="rejectFilters.reject_reason" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                  <option value="">Semua</option>
                  <option v-for="r in grRejectReasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nomor Seri</label>
                <input v-model="rejectFilters.serial_number" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" @keyup.enter="loadRejectLogs(1)" />
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Cari</label>
                <input v-model="rejectFilters.search" type="text" placeholder="serial, DO, item, scanner..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @keyup.enter="loadRejectLogs(1)" />
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Dari</label>
                <input v-model="rejectFilters.date_from" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Sampai</label>
                <input v-model="rejectFilters.date_to" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              </div>
            </div>
            <div class="mt-4 flex gap-2">
              <button type="button" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 disabled:opacity-50" :disabled="rejectLoading" @click="loadRejectLogs(1)">
                <i class="fa-solid fa-search mr-1"></i> Tampilkan
              </button>
              <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200" @click="resetRejectLogs">Reset</button>
            </div>
          </div>
          <div v-if="rejectLoaded && rejectSummaryByReason.length" class="flex flex-wrap gap-2">
            <span v-for="chip in rejectSummaryByReason" :key="chip.reason" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-800 border border-red-100">
              {{ chip.label }}: <strong>{{ chip.count }}</strong>
            </span>
          </div>
          <div v-if="rejectLogList.length" class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-x-auto">
            <table class="w-full min-w-[1100px] text-sm">
              <thead class="bg-red-600 text-white">
                <tr>
                  <th class="px-4 py-3 text-left">Waktu</th>
                  <th class="px-4 py-3 text-left">Serial</th>
                  <th class="px-4 py-3 text-left">Alasan</th>
                  <th class="px-4 py-3 text-left">Pesan</th>
                  <th class="px-4 py-3 text-left">Scanner / Outlet</th>
                  <th class="px-4 py-3 text-left">Outlet Tujuan</th>
                  <th class="px-4 py-3 text-left">DO</th>
                  <th class="px-4 py-3 text-left">Warehouse</th>
                  <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="row in rejectLogList" :key="row.id" class="hover:bg-red-50/40">
                  <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">{{ formatDateTime(row.created_at) }}</td>
                  <td class="px-4 py-3 font-mono font-semibold text-indigo-700">{{ row.serial_number }}</td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold" :class="rejectReasonBadge(row.reject_reason)">{{ row.reject_reason_label }}</span>
                  </td>
                  <td class="px-4 py-3 text-gray-700 max-w-xs">{{ row.reject_message }}</td>
                  <td class="px-4 py-3 text-xs">
                    <div class="font-medium text-gray-800">{{ row.scanner_name || '—' }}</div>
                    <div class="text-gray-500">{{ row.scanner_outlet_name || '—' }}</div>
                  </td>
                  <td class="px-4 py-3 text-xs text-gray-700">{{ row.serial_target_outlet_name || '—' }}</td>
                  <td class="px-4 py-3 font-mono text-xs">{{ row.delivery_order_number || '—' }}</td>
                  <td class="px-4 py-3 text-xs text-gray-600">{{ row.warehouse_outlet_name || '—' }}</td>
                  <td class="px-4 py-3 text-center">
                    <button type="button" class="text-xs text-indigo-600 hover:underline font-semibold" @click="trackRejectSerial(row.serial_number)">Lacak</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <div v-if="rejectPagination.last_page > 1" class="flex justify-center gap-2 p-4 border-t">
              <button type="button" class="px-3 py-1 text-sm rounded border disabled:opacity-40" :disabled="rejectPagination.current_page <= 1" @click="loadRejectLogs(rejectPagination.current_page - 1)">Prev</button>
              <span class="px-3 py-1 text-sm text-gray-600">{{ rejectPagination.current_page }} / {{ rejectPagination.last_page }}</span>
              <button type="button" class="px-3 py-1 text-sm rounded border disabled:opacity-40" :disabled="rejectPagination.current_page >= rejectPagination.last_page" @click="loadRejectLogs(rejectPagination.current_page + 1)">Next</button>
            </div>
          </div>
          <p v-else-if="rejectLoaded && !rejectLoading" class="text-center text-gray-500 py-8">Belum ada log penolakan GR.</p>
          <div v-if="rejectLoading" class="text-center py-8 text-gray-500"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Memuat...</div>
        </template>
      </div>

      <!-- Modal serial per dokumen -->
      <div v-if="selectedDoc" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="closeDocumentSerials">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col">
          <div class="p-5 border-b flex justify-between items-center">
            <div>
              <h3 class="text-lg font-bold text-gray-900">Serial — {{ selectedDoc.document_number }}</h3>
              <p class="text-sm text-gray-500">{{ selectedDoc.source_label }} · {{ selectedDoc.serial_count }} serial</p>
            </div>
            <button type="button" class="p-2 hover:bg-gray-100 rounded-lg" @click="closeDocumentSerials"><i class="fa fa-times"></i></button>
          </div>
          <div class="p-4 border-b">
            <input v-model="docSerialSearch" type="text" placeholder="Filter nomor seri..." class="w-full border rounded-lg px-3 py-2 text-sm font-mono" @input="debouncedLoadDocSerials" />
          </div>
          <div class="overflow-auto flex-1 p-4">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 sticky top-0">
                <tr>
                  <th class="px-3 py-2 text-left">Serial</th>
                  <th class="px-3 py-2 text-left">Item</th>
                  <th class="px-3 py-2 text-left">Unit</th>
                  <th class="px-3 py-2 text-left">Status</th>
                  <th class="px-3 py-2 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="s in docSerialList" :key="s.id" class="hover:bg-gray-50">
                  <td class="px-3 py-2 font-mono font-semibold text-indigo-700">{{ s.serial_number }}</td>
                  <td class="px-3 py-2">{{ s.item_name }}</td>
                  <td class="px-3 py-2">{{ s.unit_name }}</td>
                  <td class="px-3 py-2">
                    <span v-if="s.is_out" class="text-amber-700 text-xs font-semibold">Keluar</span>
                    <span v-else-if="s.is_received" class="text-green-700 text-xs font-semibold">Di outlet</span>
                    <span v-else class="text-blue-700 text-xs font-semibold">Gudang</span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <button type="button" class="text-xs text-indigo-600 hover:underline font-semibold" @click="trackFromList(s.serial_number)">Lacak</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="!docSerialLoading && !docSerialList.length" class="text-center text-gray-500 py-6">Tidak ada serial.</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { ref, reactive, computed } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  sourceTypes: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
  isHQ: { type: Boolean, default: false },
  userOutletId: { type: [String, Number], default: null },
  grRejectReasons: { type: Array, default: () => [] },
  rejectLogsTableReady: { type: Boolean, default: false },
})

const activeTab = ref('document')

const docFilters = reactive({
  source_type: '',
  search: '',
  date_from: '',
  date_to: '',
})
const docResults = ref([])
const docPagination = reactive({ current_page: 1, last_page: 1, per_page: 20 })
const docLoading = ref(false)
const docSearched = ref(false)

const selectedDoc = ref(null)
const docSerialList = ref([])
const docSerialSearch = ref('')
const docSerialLoading = ref(false)
let docSerialDebounce = null

const serialQuery = ref('')
const serialLoading = ref(false)
const serialDetail = ref(null)
const serialTimeline = ref([])
const serialSuggestions = ref([])

const pendingFilters = reactive({
  outlet_id: '',
  warehouse_outlet_id: '',
  do_number: '',
  serial_number: '',
  search: '',
  date_from: '',
  date_to: '',
})
const pendingDoList = ref([])
const expandedDoIds = ref({})
const pendingSummary = reactive({ total_serials: 0, distinct_do: 0, distinct_outlet: 0 })
const pendingPagination = reactive({ current_page: 1, last_page: 1 })
const pendingWarehouseOutlets = ref([])
const pendingLoading = ref(false)
const pendingLoaded = ref(false)

const rejectFilters = reactive({
  outlet_id: '',
  reject_reason: '',
  serial_number: '',
  search: '',
  date_from: '',
  date_to: '',
})
const rejectLogList = ref([])
const rejectSummaryRaw = ref({})
const rejectPagination = reactive({ current_page: 1, last_page: 1 })
const rejectLoading = ref(false)
const rejectLoaded = ref(false)

const rejectSummaryByReason = computed(() => {
  const byReason = rejectSummaryRaw.value?.by_reason || {}
  const labels = Object.fromEntries((props.grRejectReasons || []).map((r) => [r.value, r.label]))
  return Object.entries(byReason).map(([reason, count]) => ({
    reason,
    label: labels[reason] || reason,
    count,
  }))
})

const searchDocuments = async (page = 1) => {
  if (!docFilters.source_type) return
  docLoading.value = true
  docSearched.value = true
  try {
    const { data } = await axios.get('/api/serial-tracking/documents', {
      params: { ...docFilters, page, per_page: 20 },
    })
    docResults.value = data.data || []
    docPagination.current_page = data.current_page || 1
    docPagination.last_page = data.last_page || 1
  } catch {
    docResults.value = []
  } finally {
    docLoading.value = false
  }
}

const resetDocSearch = () => {
  docFilters.search = ''
  docFilters.date_from = ''
  docFilters.date_to = ''
  docResults.value = []
  docSearched.value = false
}

const openDocumentSerials = (row) => {
  selectedDoc.value = row
  docSerialSearch.value = ''
  loadDocSerials()
}

const closeDocumentSerials = () => {
  selectedDoc.value = null
  docSerialList.value = []
}

const loadDocSerials = async () => {
  if (!selectedDoc.value) return
  docSerialLoading.value = true
  try {
    const { data } = await axios.get('/api/serial-tracking/document-serials', {
      params: {
        source_type: selectedDoc.value.source_type,
        source_id: selectedDoc.value.source_id,
        search: docSerialSearch.value || undefined,
        per_page: 100,
      },
    })
    docSerialList.value = data.data || []
  } catch {
    docSerialList.value = []
  } finally {
    docSerialLoading.value = false
  }
}

const debouncedLoadDocSerials = () => {
  clearTimeout(docSerialDebounce)
  docSerialDebounce = setTimeout(loadDocSerials, 350)
}

const lookupSerial = async () => {
  const q = serialQuery.value.trim()
  if (q.length < 2) return
  serialLoading.value = true
  serialDetail.value = null
  serialTimeline.value = []
  serialSuggestions.value = []
  try {
    const { data } = await axios.get('/api/serial-tracking/lookup', { params: { serial_number: q } })
    serialDetail.value = data.serial
    serialTimeline.value = data.timeline || []
  } catch (err) {
    if (err?.response?.status === 404 && err?.response?.data?.suggestions?.length) {
      serialSuggestions.value = err.response.data.suggestions
    }
  } finally {
    serialLoading.value = false
  }
}

const trackFromList = (sn) => {
  closeDocumentSerials()
  activeTab.value = 'serial'
  serialQuery.value = sn
  lookupSerial()
}

const switchToPendingTab = () => {
  activeTab.value = 'pending'
  if (!pendingLoaded.value) {
    loadPending(1)
  }
}

const loadPending = async (page = 1) => {
  pendingLoading.value = true
  pendingLoaded.value = true
  try {
    const params = {
      page,
      per_page: 20,
      outlet_id: pendingFilters.outlet_id || undefined,
      warehouse_outlet_id: pendingFilters.warehouse_outlet_id || undefined,
      do_number: pendingFilters.do_number || undefined,
      serial_number: pendingFilters.serial_number || undefined,
      search: pendingFilters.search || undefined,
      date_from: pendingFilters.date_from || undefined,
      date_to: pendingFilters.date_to || undefined,
    }
    const { data } = await axios.get('/api/serial-tracking/pending-outlet-receive', { params })
    pendingDoList.value = data.data || []
    expandedDoIds.value = {}
    pendingSummary.total_serials = data.summary?.total_serials ?? data.total ?? 0
    pendingSummary.distinct_do = data.summary?.distinct_do ?? 0
    pendingSummary.distinct_outlet = data.summary?.distinct_outlet ?? 0
    pendingPagination.current_page = data.current_page || 1
    pendingPagination.last_page = data.last_page || 1
    if (data.warehouse_outlets) {
      pendingWarehouseOutlets.value = data.warehouse_outlets
    }
  } catch {
    pendingDoList.value = []
    expandedDoIds.value = {}
    pendingSummary.total_serials = 0
    pendingSummary.distinct_do = 0
    pendingSummary.distinct_outlet = 0
  } finally {
    pendingLoading.value = false
  }
}

const onPendingOutletChange = () => {
  pendingFilters.warehouse_outlet_id = ''
  pendingWarehouseOutlets.value = []
  if (pendingFilters.outlet_id) {
    loadPending(1)
  }
}

const resetPending = () => {
  pendingFilters.outlet_id = ''
  pendingFilters.warehouse_outlet_id = ''
  pendingFilters.do_number = ''
  pendingFilters.serial_number = ''
  pendingFilters.search = ''
  pendingFilters.date_from = ''
  pendingFilters.date_to = ''
  pendingWarehouseOutlets.value = []
  pendingDoList.value = []
  expandedDoIds.value = {}
  pendingLoaded.value = false
  pendingSummary.total_serials = 0
  pendingSummary.distinct_do = 0
  pendingSummary.distinct_outlet = 0
}

const trackPendingSerial = (sn) => {
  activeTab.value = 'serial'
  serialQuery.value = sn
  lookupSerial()
}

const switchToRejectsTab = () => {
  activeTab.value = 'rejects'
  if (!rejectLoaded.value && props.rejectLogsTableReady) {
    loadRejectLogs(1)
  }
}

const loadRejectLogs = async (page = 1) => {
  if (!props.rejectLogsTableReady) return
  rejectLoading.value = true
  rejectLoaded.value = true
  try {
    const { data } = await axios.get('/api/serial-tracking/gr-reject-logs', {
      params: {
        page,
        per_page: 20,
        outlet_id: rejectFilters.outlet_id || undefined,
        reject_reason: rejectFilters.reject_reason || undefined,
        serial_number: rejectFilters.serial_number || undefined,
        search: rejectFilters.search || undefined,
        date_from: rejectFilters.date_from || undefined,
        date_to: rejectFilters.date_to || undefined,
      },
    })
    rejectLogList.value = data.data || []
    rejectSummaryRaw.value = data.summary || {}
    rejectPagination.current_page = data.current_page || 1
    rejectPagination.last_page = data.last_page || 1
  } catch {
    rejectLogList.value = []
    rejectSummaryRaw.value = {}
  } finally {
    rejectLoading.value = false
  }
}

const resetRejectLogs = () => {
  rejectFilters.outlet_id = ''
  rejectFilters.reject_reason = ''
  rejectFilters.serial_number = ''
  rejectFilters.search = ''
  rejectFilters.date_from = ''
  rejectFilters.date_to = ''
  rejectLogList.value = []
  rejectLoaded.value = false
  rejectSummaryRaw.value = {}
}

const trackRejectSerial = (sn) => {
  activeTab.value = 'serial'
  serialQuery.value = sn
  lookupSerial()
}

const rejectReasonBadge = (reason) => {
  const map = {
    wrong_outlet: 'bg-orange-100 text-orange-800',
    not_dispatched: 'bg-amber-100 text-amber-800',
    already_received: 'bg-gray-100 text-gray-800',
    not_found: 'bg-slate-100 text-slate-800',
    duplicate_scan: 'bg-purple-100 text-purple-800',
    incomplete_do_data: 'bg-yellow-100 text-yellow-800',
  }
  return map[reason] || 'bg-red-100 text-red-800'
}

const isPendingDoExpanded = (doId) => !!expandedDoIds.value[doId]

const togglePendingDo = (doId) => {
  expandedDoIds.value = {
    ...expandedDoIds.value,
    [doId]: !expandedDoIds.value[doId],
  }
}

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('id-ID') : '-')
const formatDateTime = (d) => (d ? new Date(d).toLocaleString('id-ID') : '-')

const statusBadgeClass = (color) => {
  const map = {
    blue: 'bg-blue-100 text-blue-800',
    green: 'bg-green-100 text-green-800',
    amber: 'bg-amber-100 text-amber-800',
    orange: 'bg-orange-100 text-orange-800',
    red: 'bg-red-100 text-red-800',
    purple: 'bg-purple-100 text-purple-800',
    indigo: 'bg-indigo-100 text-indigo-800',
  }
  return map[color] || 'bg-gray-100 text-gray-800'
}
</script>
