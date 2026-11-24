<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-check text-blue-500"></i> Buat Stock Opname Baru
          </h1>
          <div v-if="autosaveStatus !== 'idle'" class="flex items-center gap-2 text-sm">
            <span v-if="autosaveStatus === 'saving'" class="text-yellow-600 flex items-center gap-1">
              <i class="fa fa-spinner fa-spin"></i>
              Menyimpan draft...
            </span>
            <span v-else-if="autosaveStatus === 'saved'" class="text-green-600 flex items-center gap-1">
              <i class="fa fa-check-circle"></i>
              Draft tersimpan
              <span v-if="lastSavedAt" class="text-xs text-gray-500">
                ({{ new Date(lastSavedAt).toLocaleTimeString('id-ID') }})
              </span>
            </span>
            <span v-else-if="autosaveStatus === 'local'" class="text-blue-600 flex items-center gap-1">
              <i class="fa fa-save"></i>
              Draft lokal tersimpan
            </span>
            <span v-else-if="autosaveStatus === 'error'" class="text-red-600 flex items-center gap-1">
              <i class="fa fa-exclamation-circle"></i>
              Gagal simpan server (lokal aman)
            </span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="downloadTemplate"
            class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-download mr-2"></i> Download Template
          </button>
          <button
            type="button"
            @click="showImportModal = true"
            class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-file-import mr-2"></i> Import
          </button>
          <Link
            :href="route('stock-opnames.index')"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-arrow-left mr-2"></i> Kembali
          </Link>
        </div>
      </div>

      <form @submit.prevent="submitForm" class="bg-white rounded-xl shadow-lg p-6">
        <!-- Basic Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet *</label>
            <select
              v-model="form.outlet_id"
              @change="onOutletChange"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              :disabled="!outletSelectable"
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse Outlet *</label>
            <select
              v-model="form.warehouse_outlet_id"
              @change="loadItems"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Pilih Warehouse Outlet</option>
              <option v-for="wo in filteredWarehouseOutlets" :key="wo.id" :value="wo.id">
                {{ wo.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Opname *</label>
            <input
              v-model="form.opname_date"
              type="date"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Catatan tambahan..."
            ></textarea>
          </div>
        </div>

        <!-- Approval Flow Section -->
        <div class="mb-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Flow</h3>
          <p class="text-sm text-gray-600 mb-4">Tambahkan approvers secara berurutan dari terendah ke tertinggi. Approver pertama = level terendah, approver terakhir = level tertinggi.</p>
          
          <!-- Add Approver Input -->
          <div class="mb-4">
            <div class="relative">
              <input
                v-model="approverSearch"
                type="text"
                placeholder="Cari user berdasarkan nama, email, atau jabatan..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                @input="onApproverSearch"
                @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
              />
              
              <!-- Dropdown Results -->
              <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                <div
                  v-for="user in approverResults"
                  :key="user.id"
                  @click="addApprover(user)"
                  class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                >
                  <div class="font-medium">{{ user.name }}</div>
                  <div class="text-sm text-gray-600">{{ user.email }}</div>
                  <div v-if="user.jabatan" class="text-xs text-blue-600 font-medium">{{ user.jabatan }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Approvers List -->
          <div v-if="form.approvers.length > 0" class="space-y-2">
            <h4 class="font-medium text-gray-700">Urutan Approval (Terendah ke Tertinggi):</h4>
            
            <template v-for="(approver, index) in form.approvers" :key="approver?.id || index">
              <div
                v-if="approver && approver.id"
                class="flex items-center justify-between p-3 rounded-md bg-gray-50 border border-gray-200"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="index > 0"
                      @click="reorderApprover(index, index - 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Pindah ke atas"
                    >
                      <i class="fa fa-arrow-up"></i>
                    </button>
                    <button
                      v-if="index < form.approvers.length - 1"
                      @click="reorderApprover(index, index + 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Pindah ke bawah"
                    >
                      <i class="fa fa-arrow-down"></i>
                    </button>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      Level {{ index + 1 }}
                    </span>
                    <div>
                      <div class="font-medium">{{ approver.name }}</div>
                      <div class="text-sm text-gray-600">{{ approver.email }}</div>
                      <div v-if="approver.jabatan" class="text-xs text-blue-600 font-medium">{{ approver.jabatan }}</div>
                    </div>
                  </div>
                </div>
                <button
                  @click="removeApprover(index)"
                  class="p-1 text-red-500 hover:text-red-700"
                  title="Hapus Approver"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </template>
          </div>
        </div>

        <!-- Items Table -->
        <div v-if="form.items.length > 0" class="mb-6">
          <div class="sticky top-16 z-10 -mx-1 mb-4 flex flex-col gap-3 rounded-lg border border-gray-200 bg-white/95 px-3 py-3 shadow-sm backdrop-blur-md sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-semibold text-gray-800">
              Items ({{ filteredItems.length }}<span v-if="itemSearch.trim()" class="text-gray-500 font-normal"> / {{ form.items.length }}</span>)
            </h3>
            <div class="relative w-full sm:max-w-sm">
              <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
              <input
                v-model="itemSearch"
                type="text"
                placeholder="Cari produk atau kategori..."
                class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-9 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
              />
              <button
                v-if="itemSearch"
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                @click="itemSearch = ''"
              >
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>

          <div
            v-if="itemSearch.trim() && filteredItems.length === 0"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
          >
            Tidak ada produk yang cocok dengan "{{ itemSearch }}".
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase w-48">Item</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-44">
                    Last Stock<br/>(System)
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Small
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Medium
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Large
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-32">Last Cost</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">Subtotal<br/>(Qty × Last Cost)</th>
                  <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase w-48">Selisih</th>
                  <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase w-48">Alasan</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <template v-for="(category, categoryName) in groupedItems" :key="categoryName">
                  <!-- Category Header Row -->
                  <tr
                    class="bg-blue-50 hover:bg-blue-100 cursor-pointer transition"
                    @click="toggleCategory(categoryName)"
                  >
                    <td class="px-4 py-3 font-bold text-gray-800" colspan="9">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <i 
                            :class="expandedCategories.has(categoryName) ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"
                            class="text-blue-600 transition-transform"
                          ></i>
                          <span class="text-sm">{{ categoryName || 'Uncategorized' }}</span>
                          <span class="text-xs text-gray-500 font-normal">
                            ({{ category.length }} item{{ category.length > 1 ? 's' : '' }})
                          </span>
                        </div>
                        <div class="text-xs text-gray-600">
                          <i class="fa-solid fa-chevron-down" v-if="expandedCategories.has(categoryName)"></i>
                          <i class="fa-solid fa-chevron-right" v-else></i>
                        </div>
                      </div>
                    </td>
                  </tr>
                  
                  <!-- Items in Category -->
                  <template v-for="item in category" :key="item.inventory_item_id">
                    <tr
                      v-show="expandedCategories.has(categoryName)"
                      :class="{ 'bg-yellow-50': hasDifference(item) }"
                      class="hover:bg-gray-50 transition"
                    >
                      <td class="px-4 py-3 text-sm font-medium text-gray-900">
                        <div class="flex items-start gap-2 pl-6">
                          <div class="font-semibold flex-1">{{ item.item_name }}</div>
                          <button
                            type="button"
                            @click="toggleQtyParts(item)"
                            class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded text-[11px] font-semibold border transition"
                            :class="item.show_qty_parts
                              ? 'bg-indigo-100 text-indigo-800 border-indigo-300'
                              : 'bg-white text-indigo-700 border-indigo-200 hover:bg-indigo-50'"
                            :title="'Input qty dari beberapa lokasi, otomatis dijumlah'"
                          >
                            <i class="fa-solid fa-layer-group"></i>
                            Multi
                          </button>
                        </div>
                        <div
                          v-if="item.show_qty_parts && qtyPartsFilledCount(item) > 0"
                          class="pl-6 mt-1 text-[11px] text-indigo-700"
                        >
                          {{ qtyPartsFilledCount(item) }} lokasi → total {{ formatNumber(qtyPartsSum(item)) }} {{ unitLabel(item, item.qty_parts_unit || 'small') }}
                        </div>
                      </td>
                      <td class="px-3 py-3 text-right text-xs text-gray-700 whitespace-nowrap">
                        <div class="font-medium tabular-nums">{{ formatNumber(item.qty_system_small) }} <span class="text-gray-500">{{ item.small_unit_name || '' }}</span></div>
                        <div class="font-medium tabular-nums mt-0.5">{{ formatNumber(item.qty_system_medium) }} <span class="text-gray-500">{{ item.medium_unit_name || '' }}</span></div>
                        <div class="font-medium tabular-nums mt-0.5">{{ formatNumber(item.qty_system_large) }} <span class="text-gray-500">{{ item.large_unit_name || '' }}</span></div>
                      </td>
                      <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                          <input
                            v-model.number="item.qty_physical_small"
                            type="number"
                            step="any"
                            min="0"
                            :readonly="item.show_qty_parts"
                            @input="onQtyPhysicalChange(item, 'small')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'bg-gray-100 cursor-not-allowed': item.show_qty_parts }"
                          />
                          <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.small_unit_name || '-' }}</span>
                        </div>
                      </td>
                      <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                          <input
                            v-model.number="item.qty_physical_medium"
                            type="number"
                            step="any"
                            min="0"
                            :readonly="item.show_qty_parts"
                            @input="onQtyPhysicalChange(item, 'medium')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'bg-gray-100 cursor-not-allowed': item.show_qty_parts }"
                          />
                          <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.medium_unit_name || '-' }}</span>
                        </div>
                      </td>
                      <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                          <input
                            v-model.number="item.qty_physical_large"
                            type="number"
                            step="any"
                            min="0"
                            :readonly="item.show_qty_parts"
                            @input="onQtyPhysicalChange(item, 'large')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'bg-gray-100 cursor-not-allowed': item.show_qty_parts }"
                          />
                          <span class="text-xs text-gray-600 font-medium whitespace-nowrap">{{ item.large_unit_name || '-' }}</span>
                        </div>
                      </td>
                      <td class="px-3 py-3 text-sm text-right text-gray-700">
                        <div class="font-medium">{{ formatCurrency(item.mac) }}</div>
                      </td>
                      <td class="px-3 py-3 text-sm text-right text-gray-700">
                        <div class="font-semibold">{{ formatNumber(getSubtotal(item)) }}</div>
                      </td>
                      <td class="px-3 py-3 text-right text-sm">
                        <div v-if="hasDifference(item)" class="inline-flex flex-col items-end gap-0.5">
                          <span
                            v-for="(diff, diffIdx) in getDifferenceArray(item)"
                            :key="diffIdx"
                            :class="getDifferenceClass(item)"
                            class="px-2 py-0.5 rounded text-[11px] font-semibold whitespace-nowrap tabular-nums"
                          >
                            {{ diff }}
                          </span>
                          <span
                            :class="getDifferenceClass(item)"
                            class="px-2 py-0.5 rounded text-[11px] font-bold whitespace-nowrap tabular-nums"
                          >
                            {{ formatSelisihNilai(item) }}
                          </span>
                        </div>
                        <span v-else class="text-gray-400 text-xs">-</span>
                      </td>
                      <td class="px-3 py-3">
                        <input
                          v-if="hasDifference(item)"
                          v-model="item.reason"
                          type="text"
                          placeholder="Alasan selisih..."
                          @input="triggerAutosave"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <span v-else class="text-gray-400 text-xs">-</span>
                      </td>
                    </tr>
                    <tr
                      v-show="expandedCategories.has(categoryName) && item.show_qty_parts"
                      class="bg-indigo-50/70"
                    >
                      <td colspan="9" class="px-4 py-3">
                        <div class="ml-6 rounded-lg border border-indigo-200 bg-white p-3">
                          <div class="flex flex-wrap items-center gap-3 mb-3">
                            <span class="text-xs font-bold text-indigo-800 uppercase">Qty per lokasi</span>
                            <label class="text-xs text-gray-600 flex items-center gap-1">
                              Satuan
                              <select
                                v-model="item.qty_parts_unit"
                                @change="applyQtyPartsSum(item)"
                                class="ml-1 px-2 py-1 border border-gray-300 rounded text-sm"
                              >
                                <option value="small">{{ item.small_unit_name || 'Small' }}</option>
                                <option value="medium">{{ item.medium_unit_name || 'Medium' }}</option>
                                <option value="large">{{ item.large_unit_name || 'Large' }}</option>
                              </select>
                            </label>
                            <span class="text-xs text-gray-500">Isi qty tiap tempat, total otomatis masuk Qty Physical</span>
                          </div>
                          <div class="flex flex-wrap gap-2 items-end">
                            <div
                              v-for="(part, partIndex) in item.qty_parts"
                              :key="partIndex"
                              class="flex items-center gap-1"
                            >
                              <span class="text-[11px] text-gray-500 w-5 text-right">{{ partIndex + 1 }}.</span>
                              <input
                                v-model.number="part.qty"
                                type="number"
                                step="any"
                                min="0"
                                placeholder="Qty"
                                @input="applyQtyPartsSum(item)"
                                class="w-24 px-2 py-1.5 border border-indigo-200 rounded-md text-right text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400"
                              />
                              <button
                                type="button"
                                @click="removeQtyPart(item, partIndex)"
                                class="px-1.5 py-1 text-red-500 hover:text-red-700 text-xs"
                                title="Hapus"
                              >
                                <i class="fa-solid fa-xmark"></i>
                              </button>
                            </div>
                            <button
                              type="button"
                              @click="addQtyPart(item)"
                              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700"
                            >
                              <i class="fa-solid fa-plus"></i>
                              Tambah lokasi
                            </button>
                          </div>
                          <div class="mt-2 text-sm font-semibold text-indigo-900">
                            Total: {{ formatNumber(qtyPartsSum(item)) }}
                            {{ unitLabel(item, item.qty_parts_unit || 'small') }}
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </template>
              </tbody>
              <tfoot class="bg-gray-100 border-t-2 border-gray-400">
                <tr>
                  <td class="px-4 py-4 text-right font-bold text-gray-900" colspan="6">
                    GRAND TOTAL
                  </td>
                  <td class="px-3 py-4 text-right font-bold text-gray-900 text-lg">
                    {{ formatNumber(grandTotal) }}
                  </td>
                  <td class="px-3 py-4" colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loadingItems" class="text-center py-8">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
          <p class="mt-2 text-gray-600">Memuat items...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="form.items.length === 0 && form.outlet_id && form.warehouse_outlet_id" class="text-center py-8 text-gray-500">
          <i class="fa-solid fa-inbox text-4xl mb-4"></i>
          <p>Tidak ada item untuk outlet dan warehouse outlet yang dipilih.</p>
        </div>

        <!-- Submit Button -->
        <div v-if="form.items.length > 0" class="flex justify-end gap-4 mt-6">
          <button
            type="button"
            @click="$inertia.visit(route('stock-opnames.index'))"
            class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-semibold"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 font-semibold disabled:opacity-50"
          >
            <i v-if="submitting" class="fa fa-spinner fa-spin mr-2"></i>
            <i v-else class="fa-solid fa-save mr-2"></i>
            Simpan Draft
          </button>
        </div>
      </form>

      <!-- Modal Import: pilih file → Import → data langsung terisi ke list item di form -->
      <Modal :show="showImportModal" @close="closeImportModal" max-width="2xl">
        <div class="p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Import Stock Opname</h2>
          <p class="text-sm text-gray-600 mb-4">Pilih file Excel (dari Download Template). Data akan langsung terisi ke list item di bawah. Di Excel: <b>qty system</b> sudah terisi otomatis; isi <b>qty fisik</b> (dan <b>reason</b> bila ada selisih). Kolom <b>selisih</b> boleh dikosongkan.</p>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Pilih File</label>
            <input
              ref="fileInputRef"
              type="file"
              accept=".xlsx,.xls"
              @change="onImportFileChange"
              class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200"
            />
          </div>

          <div v-if="importError" class="mb-4 p-3 bg-red-50 text-red-700 rounded text-sm">
            {{ importError }}
          </div>

          <div class="flex justify-end gap-2 mt-4">
            <button type="button" @click="closeImportModal" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded font-medium">
              Tutup
            </button>
            <button
              v-if="selectedImportFile"
              type="button"
              @click="loadImportToForm"
              :disabled="importLoading"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium disabled:opacity-50"
            >
              <span v-if="importLoading"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</span>
              <span v-else>Import</span>
            </button>
          </div>
        </div>
      </Modal>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import { todayYmdJakarta } from '@/utils/dateOnly';

const props = defineProps({
  outlets: Array,
  warehouseOutlets: Array,
  items: Array,
  selectedOutletId: [String, Number],
  selectedWarehouseOutletId: [String, Number],
  user_outlet_id: [String, Number],
});

// Auto-set outlet_id if user is not superadmin
const getInitialOutletId = () => {
  if (props.user_outlet_id && String(props.user_outlet_id) !== '1') {
    return String(props.user_outlet_id);
  }
  return props.selectedOutletId || '';
};

const form = useForm({
  outlet_id: getInitialOutletId(),
  warehouse_outlet_id: props.selectedWarehouseOutletId || '',
  opname_date: todayYmdJakarta(),
  notes: '',
  items: props.items || [],
  approvers: [],
});

const loadingItems = ref(false);
const submitting = ref(false);
const expandedCategories = ref(new Set());
const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);
const approverSearchTimeout = ref(null);

// Draft autosave (localStorage + server DRAFT)
const draftId = ref(null);
const autosaveStatus = ref('idle'); // idle | saving | saved | local | error
const lastSavedAt = ref(null);
const autosaveTimeout = ref(null);
const restoringDraft = ref(false);

// Import modal
const showImportModal = ref(false);
const selectedImportFile = ref(null);
const importError = ref('');
const importLoading = ref(false);
const fileInputRef = ref(null);
const importJustApplied = ref(false);
const itemSearch = ref('');

const outletSelectable = computed(() => String(props.user_outlet_id) === '1');

const filteredItems = computed(() => {
  const q = itemSearch.value.trim().toLowerCase();
  if (!q) return form.items;

  return form.items.filter((item) => {
    const name = (item.item_name || '').toLowerCase();
    const category = (item.category_name || '').toLowerCase();
    return name.includes(q) || category.includes(q);
  });
});

// Group items by category
const groupedItems = computed(() => {
  const grouped = {};
  filteredItems.value.forEach(item => {
    const categoryName = item.category_name || 'Uncategorized';
    if (!grouped[categoryName]) {
      grouped[categoryName] = [];
    }
    grouped[categoryName].push(item);
  });
  return grouped;
});

// Calculate subtotal for an item (qty_physical_small * mac)
function getSubtotal(item) {
  if (!item) return 0;
  // Return 0 if qty_physical_small is not filled (null/undefined/empty)
  if (item.qty_physical_small === null || item.qty_physical_small === undefined || item.qty_physical_small === '') {
    return 0;
  }
  const qtyPhysical = parseFloat(item.qty_physical_small) || 0;
  const mac = parseFloat(item.mac) || 0;
  return qtyPhysical * mac;
}

// Calculate grand total
const grandTotal = computed(() => {
  if (!form.items || form.items.length === 0) return 0;
  return form.items.reduce((total, item) => {
    return total + getSubtotal(item);
  }, 0);
});

function toggleCategory(categoryName) {
  if (expandedCategories.value.has(categoryName)) {
    expandedCategories.value.delete(categoryName);
  } else {
    expandedCategories.value.add(categoryName);
  }
}

// Auto expand all categories on load
watch(() => form.items, (newItems) => {
  if (newItems && newItems.length > 0) {
    const categories = new Set();
    newItems.forEach(item => {
      categories.add(item.category_name || 'Uncategorized');
    });
    expandedCategories.value = categories;
  }
}, { immediate: true });

watch(itemSearch, () => {
  const categories = new Set();
  filteredItems.value.forEach((item) => {
    categories.add(item.category_name || 'Uncategorized');
  });
  expandedCategories.value = categories;
});

const filteredWarehouseOutlets = computed(() => {
  if (!props.warehouseOutlets || props.warehouseOutlets.length === 0) {
    return [];
  }
  
  let warehouseOutlets = [...props.warehouseOutlets];
  
  // If user is not superadmin (outlet_id != 1), filter by user's outlet first
  if (!outletSelectable.value && props.user_outlet_id) {
    const userOutletId = String(props.user_outlet_id);
    warehouseOutlets = warehouseOutlets.filter(wo => {
      const woOutletId = wo.outlet_id ? String(wo.outlet_id) : '';
      return woOutletId === userOutletId;
    });
  }
  
  // Filter by selected outlet (this should be the main filter)
  if (form.outlet_id) {
    const selectedOutletId = String(form.outlet_id);
    warehouseOutlets = warehouseOutlets.filter(wo => {
      const woOutletId = wo.outlet_id ? String(wo.outlet_id) : '';
      return woOutletId === selectedOutletId;
    });
  }
  
  return warehouseOutlets;
});

function downloadTemplate() {
  if (!form.outlet_id || !form.warehouse_outlet_id) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({ title: 'Perhatian', text: 'Pilih Outlet dan Warehouse Outlet terlebih dahulu.', icon: 'warning', confirmButtonColor: '#3085d6' });
    } else {
      alert('Pilih Outlet dan Warehouse Outlet terlebih dahulu.');
    }
    return;
  }
  const q = new URLSearchParams({
    outlet_id: form.outlet_id,
    warehouse_outlet_id: form.warehouse_outlet_id,
    opname_date: form.opname_date || todayYmdJakarta(),
    notes: form.notes || '',
  });
  window.location.href = route('stock-opnames.download-template') + '?' + q.toString();
}

function onOutletChange() {
  form.warehouse_outlet_id = '';
  form.items = [];
}

async function loadItems() {
  if (!form.outlet_id || !form.warehouse_outlet_id) {
    form.items = [];
    return;
  }

  loadingItems.value = true;
  try {
    const response = await axios.get(route('stock-opnames.get-items'), {
      params: {
        outlet_id: form.outlet_id,
        warehouse_outlet_id: form.warehouse_outlet_id,
      },
    });

    form.items = response.data.map(item => ({
      inventory_item_id: item.inventory_item_id,
      item_name: item.item_name,
      category_name: item.category_name,
      qty_system_small: parseFloat(item.qty_system_small) || 0,
      qty_system_medium: parseFloat(item.qty_system_medium) || 0,
      qty_system_large: parseFloat(item.qty_system_large) || 0,
      qty_physical_small: null,
      qty_physical_medium: null,
      qty_physical_large: null,
      reason: '',
      small_unit_name: item.small_unit_name,
      medium_unit_name: item.medium_unit_name,
      large_unit_name: item.large_unit_name,
      small_conversion_qty: parseFloat(item.small_conversion_qty) || 1,
      medium_conversion_qty: parseFloat(item.medium_conversion_qty) || 1,
      mac: parseFloat(item.mac) || 0,
      show_qty_parts: false,
      qty_parts_unit: 'small',
      qty_parts: [],
    }));

    restoreLocalDraftOntoItems();
  } catch (error) {
    console.error('Error loading items:', error);
    if (error.response) {
      // Server responded with error status
      alert('Gagal memuat items: ' + (error.response.data?.message || error.message));
    } else if (error.request) {
      // Request was made but no response received
      alert('Gagal memuat items: Server tidak merespon. Pastikan server berjalan.');
    } else {
      // Something else happened
      alert('Gagal memuat items: ' + error.message);
    }
    form.items = [];
  } finally {
    loadingItems.value = false;
  }
}

function ensureQtyParts(item) {
  if (!Array.isArray(item.qty_parts) || item.qty_parts.length === 0) {
    item.qty_parts = [{ qty: null }, { qty: null }];
  }
  if (!item.qty_parts_unit) {
    item.qty_parts_unit = 'small';
  }
}

function toggleQtyParts(item) {
  item.show_qty_parts = !item.show_qty_parts;
  if (item.show_qty_parts) {
    ensureQtyParts(item);
  }
}

function addQtyPart(item) {
  ensureQtyParts(item);
  item.qty_parts.push({ qty: null });
}

function removeQtyPart(item, index) {
  ensureQtyParts(item);
  if (item.qty_parts.length <= 1) {
    item.qty_parts = [{ qty: null }];
  } else {
    item.qty_parts.splice(index, 1);
  }
  applyQtyPartsSum(item);
}

function qtyPartsSum(item) {
  if (!Array.isArray(item.qty_parts)) return 0;
  return item.qty_parts.reduce((sum, part) => {
    const n = parseFloat(part?.qty);
    return sum + (Number.isFinite(n) ? n : 0);
  }, 0);
}

function qtyPartsFilledCount(item) {
  if (!Array.isArray(item.qty_parts)) return 0;
  return item.qty_parts.filter((part) => {
    const n = parseFloat(part?.qty);
    return part?.qty !== null && part?.qty !== '' && Number.isFinite(n);
  }).length;
}

function unitLabel(item, unit) {
  if (unit === 'medium') return item.medium_unit_name || 'Medium';
  if (unit === 'large') return item.large_unit_name || 'Large';
  return item.small_unit_name || 'Small';
}

function applyQtyPartsSum(item) {
  const unit = item.qty_parts_unit || 'small';
  if (qtyPartsFilledCount(item) === 0) {
    return;
  }
  item[`qty_physical_${unit}`] = Math.round(qtyPartsSum(item) * 100) / 100;
  onQtyPhysicalChange(item, unit);
}

function draftStorageKey() {
  const outlet = form.outlet_id || 'x';
  const wh = form.warehouse_outlet_id || 'x';
  return `stock_opname_create_draft_${outlet}_${wh}`;
}

function buildDraftPayload() {
  const itemsToSave = form.items.map((item) => {
    const itemData = {
      inventory_item_id: item.inventory_item_id,
      reason: item.reason || '',
    };
    if (item.qty_physical_small !== null && item.qty_physical_small !== undefined && item.qty_physical_small !== '') {
      itemData.qty_physical_small = parseFloat(item.qty_physical_small);
    }
    if (item.qty_physical_medium !== null && item.qty_physical_medium !== undefined && item.qty_physical_medium !== '') {
      itemData.qty_physical_medium = parseFloat(item.qty_physical_medium);
    }
    if (item.qty_physical_large !== null && item.qty_physical_large !== undefined && item.qty_physical_large !== '') {
      itemData.qty_physical_large = parseFloat(item.qty_physical_large);
    }
    if (Array.isArray(item.qty_parts) && item.qty_parts.length) {
      itemData.qty_parts = item.qty_parts;
      itemData.qty_parts_unit = item.qty_parts_unit || 'small';
      itemData.show_qty_parts = !!item.show_qty_parts;
    }
    return itemData;
  });

  const approversIds = form.approvers
    .filter((approver) => approver && approver.id)
    .map((approver) => approver.id);

  return {
    draftId: draftId.value,
    outlet_id: form.outlet_id,
    warehouse_outlet_id: form.warehouse_outlet_id,
    opname_date: form.opname_date,
    notes: form.notes,
    items: itemsToSave,
    approvers: approversIds,
    updatedAt: new Date().toISOString(),
  };
}

function saveLocalDraft() {
  if (!form.outlet_id || !form.warehouse_outlet_id || restoringDraft.value) {
    return;
  }
  try {
    localStorage.setItem(draftStorageKey(), JSON.stringify(buildDraftPayload()));
    if (autosaveStatus.value === 'idle') {
      autosaveStatus.value = 'local';
      setTimeout(() => {
        if (autosaveStatus.value === 'local') autosaveStatus.value = 'idle';
      }, 1500);
    }
  } catch (e) {
    console.warn('Gagal simpan draft lokal', e);
  }
}

function readLocalDraft() {
  if (!form.outlet_id || !form.warehouse_outlet_id) return null;
  try {
    const raw = localStorage.getItem(draftStorageKey());
    if (!raw) return null;
    return JSON.parse(raw);
  } catch (e) {
    return null;
  }
}

function clearLocalDraft() {
  try {
    if (form.outlet_id && form.warehouse_outlet_id) {
      localStorage.removeItem(draftStorageKey());
    }
  } catch (e) {
    // ignore
  }
  draftId.value = null;
}

function restoreLocalDraftOntoItems() {
  const draft = readLocalDraft();
  if (!draft || !Array.isArray(draft.items) || !form.items.length) {
    if (draft?.draftId) draftId.value = draft.draftId;
    if (draft?.notes) form.notes = draft.notes;
    if (draft?.opname_date) form.opname_date = draft.opname_date;
    return;
  }

  restoringDraft.value = true;
  try {
    if (draft.draftId) draftId.value = draft.draftId;
    if (draft.notes != null) form.notes = draft.notes;
    if (draft.opname_date) form.opname_date = draft.opname_date;

    const byId = new Map(draft.items.map((row) => [Number(row.inventory_item_id), row]));
    form.items.forEach((item) => {
      const saved = byId.get(Number(item.inventory_item_id));
      if (!saved) return;
      if (saved.qty_physical_small !== undefined && saved.qty_physical_small !== null && saved.qty_physical_small !== '') {
        item.qty_physical_small = parseFloat(saved.qty_physical_small);
      }
      if (saved.qty_physical_medium !== undefined && saved.qty_physical_medium !== null && saved.qty_physical_medium !== '') {
        item.qty_physical_medium = parseFloat(saved.qty_physical_medium);
      }
      if (saved.qty_physical_large !== undefined && saved.qty_physical_large !== null && saved.qty_physical_large !== '') {
        item.qty_physical_large = parseFloat(saved.qty_physical_large);
      }
      if (saved.reason) item.reason = saved.reason;
      if (Array.isArray(saved.qty_parts) && saved.qty_parts.length) {
        item.qty_parts = saved.qty_parts;
        item.qty_parts_unit = saved.qty_parts_unit || 'small';
        item.show_qty_parts = !!saved.show_qty_parts;
      }
    });
  } finally {
    restoringDraft.value = false;
  }
}

async function autosave() {
  if (!form.outlet_id || !form.warehouse_outlet_id || submitting.value || restoringDraft.value) {
    return;
  }

  saveLocalDraft();

  const payload = buildDraftPayload();
  const hasFilled = payload.items.some((item) =>
    item.qty_physical_small !== undefined
    || item.qty_physical_medium !== undefined
    || item.qty_physical_large !== undefined
    || (item.reason && String(item.reason).trim() !== '')
  );

  // Belum ada isian: cukup local draft header saja
  if (!hasFilled && !draftId.value) {
    return;
  }

  autosaveStatus.value = 'saving';
  try {
    const formData = {
      outlet_id: payload.outlet_id,
      warehouse_outlet_id: payload.warehouse_outlet_id,
      opname_date: payload.opname_date,
      notes: payload.notes,
      items: payload.items,
      approvers: payload.approvers,
      autosave: true,
    };

    let response;
    if (draftId.value) {
      response = await axios.put(route('stock-opnames.update', draftId.value), formData);
    } else {
      response = await axios.post(route('stock-opnames.store'), formData);
    }

    if (response.data?.success) {
      if (response.data.id) {
        draftId.value = response.data.id;
      }
      saveLocalDraft();
      autosaveStatus.value = 'saved';
      lastSavedAt.value = new Date();
      setTimeout(() => {
        if (autosaveStatus.value === 'saved') autosaveStatus.value = 'idle';
      }, 3000);
    }
  } catch (error) {
    console.error('Autosave error:', error);
    autosaveStatus.value = 'error';
    setTimeout(() => {
      if (autosaveStatus.value === 'error') autosaveStatus.value = 'idle';
    }, 5000);
  }
}

function triggerAutosave() {
  if (restoringDraft.value) return;
  saveLocalDraft();
  if (autosaveTimeout.value) clearTimeout(autosaveTimeout.value);
  autosaveTimeout.value = setTimeout(() => {
    autosave();
  }, 2000);
}

function onQtyPhysicalChange(item, changedUnit) {
  const value = item[`qty_physical_${changedUnit}`];
  
  if (value === null || value === undefined || value === '') {
    // Jika dikosongkan, kosongkan semua
    item.qty_physical_small = null;
    item.qty_physical_medium = null;
    item.qty_physical_large = null;
    calculateDifference(item);
    return;
  }

  const numValue = parseFloat(value);
  if (isNaN(numValue) || numValue < 0) {
    calculateDifference(item);
    return;
  }

  // Konversi unit:
  // small_conversion_qty: konversi dari small ke medium (1 medium = small_conversion_qty small)
  // medium_conversion_qty: konversi dari medium ke large (1 large = medium_conversion_qty medium)
  // Jadi: 1 large = medium_conversion_qty medium = medium_conversion_qty * small_conversion_qty small

  if (changedUnit === 'small') {
    // Small adalah base unit
    // Convert ke medium: small / small_conversion_qty
    // Convert ke large: small / (small_conversion_qty * medium_conversion_qty)
    if (item.small_conversion_qty && item.small_conversion_qty > 0) {
      item.qty_physical_medium = numValue / item.small_conversion_qty;
      
      if (item.medium_conversion_qty && item.medium_conversion_qty > 0) {
        item.qty_physical_large = numValue / (item.small_conversion_qty * item.medium_conversion_qty);
      } else {
        item.qty_physical_large = item.qty_physical_medium;
      }
    } else {
      item.qty_physical_medium = numValue;
      item.qty_physical_large = numValue;
    }
  } else if (changedUnit === 'medium') {
    // Convert dari medium ke small: medium * small_conversion_qty
    // Convert ke large: medium / medium_conversion_qty
    if (item.small_conversion_qty && item.small_conversion_qty > 0) {
      item.qty_physical_small = numValue * item.small_conversion_qty;
    } else {
      item.qty_physical_small = numValue;
    }
    
    if (item.medium_conversion_qty && item.medium_conversion_qty > 0) {
      item.qty_physical_large = numValue / item.medium_conversion_qty;
    } else {
      item.qty_physical_large = numValue;
    }
  } else if (changedUnit === 'large') {
    // Convert dari large ke medium: large * medium_conversion_qty
    // Convert ke small: medium * small_conversion_qty
    if (item.medium_conversion_qty && item.medium_conversion_qty > 0) {
      item.qty_physical_medium = numValue * item.medium_conversion_qty;
      
      if (item.small_conversion_qty && item.small_conversion_qty > 0) {
        item.qty_physical_small = item.qty_physical_medium * item.small_conversion_qty;
      } else {
        item.qty_physical_small = item.qty_physical_medium;
      }
    } else {
      item.qty_physical_medium = numValue;
      item.qty_physical_small = numValue;
    }
  }

  // Round to 2 decimal places
  if (item.qty_physical_small !== null) {
    item.qty_physical_small = Math.round(item.qty_physical_small * 100) / 100;
  }
  if (item.qty_physical_medium !== null) {
    item.qty_physical_medium = Math.round(item.qty_physical_medium * 100) / 100;
  }
  if (item.qty_physical_large !== null) {
    item.qty_physical_large = Math.round(item.qty_physical_large * 100) / 100;
  }

  calculateDifference(item);
  triggerAutosave();
}

function calculateDifference(item) {
  // Difference akan dihitung di backend
  // Di frontend hanya untuk display
}

function getDiffSmall(item) {
  return (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
}

function getDiffMedium(item) {
  return (item.qty_physical_medium ?? item.qty_system_medium) - item.qty_system_medium;
}

function getDiffLarge(item) {
  return (item.qty_physical_large ?? item.qty_system_large) - item.qty_system_large;
}

function hasMeaningfulDiff(value) {
  return Math.abs(Number(value) || 0) >= 0.005;
}

function hasDifference(item) {
  return hasMeaningfulDiff(getDiffSmall(item))
    || hasMeaningfulDiff(getDiffMedium(item))
    || hasMeaningfulDiff(getDiffLarge(item));
}

function getDifferenceArray(item) {
  const diffs = [];
  const diffSmall = getDiffSmall(item);
  const diffMedium = getDiffMedium(item);
  const diffLarge = getDiffLarge(item);

  if (hasMeaningfulDiff(diffSmall)) {
    diffs.push(`${diffSmall > 0 ? '+' : ''}${formatNumber(diffSmall)} ${item.small_unit_name || ''}`.trim());
  }
  if (hasMeaningfulDiff(diffMedium)) {
    diffs.push(`${diffMedium > 0 ? '+' : ''}${formatNumber(diffMedium)} ${item.medium_unit_name || ''}`.trim());
  }
  if (hasMeaningfulDiff(diffLarge)) {
    diffs.push(`${diffLarge > 0 ? '+' : ''}${formatNumber(diffLarge)} ${item.large_unit_name || ''}`.trim());
  }

  return diffs.length > 0 ? diffs : ['0'];
}

/** Nilai Rupiah selisih = selisih qty small × last cost small */
function getSelisihNilai(item) {
  return getDiffSmall(item) * (parseFloat(item.mac) || 0);
}

function formatSelisihNilai(item) {
  const nilai = getSelisihNilai(item);
  const prefix = nilai > 0 ? '+' : '';
  return prefix + formatCurrency(nilai);
}

function getDifferenceClass(item) {
  const diffSmall = getDiffSmall(item);
  if (diffSmall > 0.005) return 'bg-green-100 text-green-800';
  if (diffSmall < -0.005) return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-800';
}

function formatNumber(val) {
  if (val == null) return '0';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCurrency(val) {
  if (val == null || val === '' || isNaN(val)) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadApprovers(search = '') {
  if (search.length < 2) {
    approverResults.value = [];
    showApproverDropdown.value = false;
    return;
  }

  try {
    const response = await axios.get(route('stock-opnames.approvers'), {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    });
    
    console.log('Approvers response:', response.data);
    
    if (response.data && response.data.success) {
      approverResults.value = response.data.users || [];
      showApproverDropdown.value = approverResults.value.length > 0;
    } else {
      approverResults.value = [];
      showApproverDropdown.value = false;
    }
  } catch (error) {
    console.error('Failed to load approvers:', error);
    approverResults.value = [];
    showApproverDropdown.value = false;
  }
}

function onApproverSearch() {
  // Debounce search to avoid too many API calls
  if (approverSearchTimeout.value) {
    clearTimeout(approverSearchTimeout.value);
  }
  
  approverSearchTimeout.value = setTimeout(() => {
    if (approverSearch.value.length >= 2) {
      loadApprovers(approverSearch.value);
    } else {
      approverResults.value = [];
      showApproverDropdown.value = false;
    }
  }, 300);
}

function addApprover(user) {
  if (!user || !user.id) {
    console.error('Invalid user object:', user);
    return;
  }
  // Check if user already exists
  if (!form.approvers.find(approver => approver && approver.id === user.id)) {
    form.approvers.push(user);
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
}

function removeApprover(index) {
  form.approvers.splice(index, 1);
}

function reorderApprover(fromIndex, toIndex) {
  const approver = form.approvers.splice(fromIndex, 1)[0];
  form.approvers.splice(toIndex, 0, approver);
}

function submitForm() {
  if (form.items.length === 0) {
    Swal.fire({
      title: 'Error',
      text: 'Minimal harus ada 1 item.',
      icon: 'error',
      confirmButtonColor: '#3085d6'
    });
    return;
  }

  const itemsToSubmit = form.items.map(item => ({
    inventory_item_id: item.inventory_item_id,
    qty_physical_small: item.qty_physical_small !== null && item.qty_physical_small !== undefined && item.qty_physical_small !== ''
      ? parseFloat(item.qty_physical_small)
      : null,
    qty_physical_medium: item.qty_physical_medium !== null && item.qty_physical_medium !== undefined && item.qty_physical_medium !== ''
      ? parseFloat(item.qty_physical_medium)
      : null,
    qty_physical_large: item.qty_physical_large !== null && item.qty_physical_large !== undefined && item.qty_physical_large !== ''
      ? parseFloat(item.qty_physical_large)
      : null,
    reason: item.reason || '',
  }));

  submitting.value = true;

  const approversIds = form.approvers
    .filter(approver => approver && approver.id)
    .map(approver => approver.id);

  const submitPayload = useForm({
    outlet_id: form.outlet_id,
    warehouse_outlet_id: form.warehouse_outlet_id,
    opname_date: form.opname_date,
    notes: form.notes,
    items: itemsToSubmit,
    approvers: approversIds,
  });

  const onDone = {
    preserveScroll: true,
    onSuccess: () => {
      clearLocalDraft();
      submitting.value = false;
    },
    onError: (errors) => {
      submitting.value = false;
      console.error('Error:', errors);
      Swal.fire({
        title: 'Error',
        text: 'Gagal menyimpan stock opname. Silakan coba lagi.',
        icon: 'error',
        confirmButtonColor: '#3085d6'
      });
    },
  };

  // Pakai draft yang sudah ada supaya tidak dobel nomor opname
  if (draftId.value) {
    submitPayload.put(route('stock-opnames.update', draftId.value), onDone);
  } else {
    submitPayload.post(route('stock-opnames.store'), onDone);
  }
}

function onImportFileChange(e) {
  selectedImportFile.value = e.target.files?.[0] || null;
  importError.value = '';
}

async function loadImportToForm() {
  if (!selectedImportFile.value) return;
  importLoading.value = true;
  importError.value = '';
  const fd = new FormData();
  fd.append('file', selectedImportFile.value);
  try {
    const res = await axios.post(route('stock-opnames.preview-import'), fd, {
      headers: { 'Content-Type': 'multipart/form-data', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (res.data.success) {
      importJustApplied.value = true;
      form.outlet_id = String(res.data.outlet_id ?? '');
      form.warehouse_outlet_id = String(res.data.warehouse_outlet_id ?? '');
      form.opname_date = res.data.opname_date || todayYmdJakarta();
      form.notes = res.data.notes || '';
      form.items = (res.data.items || []).map(it => ({
        ...it,
        qty_physical_small: it.qty_physical_small ?? null,
        qty_physical_medium: it.qty_physical_medium ?? null,
        qty_physical_large: it.qty_physical_large ?? null,
      }));
      closeImportModal();
      await nextTick();
      importJustApplied.value = false;
      triggerAutosave();
      const errs = res.data.errors || [];
      if (errs.length) {
        const msg = `${res.data.items?.length || 0} item dimuat. ${errs.length} baris error: ${errs[0]?.error || ''}`;
        if (typeof Swal !== 'undefined') Swal.fire({ title: 'Import', text: msg, icon: 'info', confirmButtonColor: '#3085d6' });
        else alert(msg);
      } else if (typeof Swal !== 'undefined') {
        Swal.fire({ title: 'Berhasil', text: `${form.items.length} item dimuat ke form.`, icon: 'success', timer: 2000, showConfirmButton: false });
      }
    } else {
      importError.value = res.data.message || 'Gagal memuat file.';
    }
  } catch (err) {
    importError.value = err.response?.data?.message || 'Gagal memuat file.';
  } finally {
    importLoading.value = false;
  }
}

function closeImportModal() {
  showImportModal.value = false;
  selectedImportFile.value = null;
  importError.value = '';
  if (fileInputRef.value) fileInputRef.value.value = '';
}

// Load items when warehouse outlet changes (kecuali baru selesai import, agar form.items tidak tertimpa get-items)
watch(() => form.warehouse_outlet_id, () => {
  draftId.value = null;
  if (form.warehouse_outlet_id) {
    if (importJustApplied.value) {
      importJustApplied.value = false;
      restoreLocalDraftOntoItems();
      return;
    }
    loadItems();
  }
});

watch(() => form.notes, () => triggerAutosave());
watch(() => form.opname_date, () => triggerAutosave());

// Auto-set outlet_id on mount if user is not superadmin
if (props.user_outlet_id && String(props.user_outlet_id) !== '1') {
  form.outlet_id = String(props.user_outlet_id);
}

// Auto load items if already selected
if (form.outlet_id && form.warehouse_outlet_id && form.items.length === 0) {
  loadItems();
} else if (form.items.length > 0) {
  restoreLocalDraftOntoItems();
}
</script>

