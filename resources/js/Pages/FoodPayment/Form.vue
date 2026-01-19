<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-money-bill-transfer text-blue-500"></i> {{ isEditMode ? 'Edit' : 'Buat' }} Food Payment
        </h1>
      </div>
      <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.date" @keydown.enter.prevent="showPreview" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Payment Type</label>
            <select v-model="form.payment_type" @keydown.enter.prevent="showPreview" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Payment Type</option>
              <option value="Transfer">Transfer</option>
              <option value="Giro">Giro</option>
              <option value="Cash">Cash</option>
            </select>
          </div>
        </div>
        <!-- Bank Selection (DISABLED - sekarang bank dipilih per outlet) -->
        <!-- <div v-if="form.payment_type === 'Transfer' || form.payment_type === 'Giro'">
          <label class="block text-sm font-medium text-gray-700">
            Pilih Bank <span class="text-red-500">*</span>
          </label>
          <multiselect
            v-model="selectedBank"
            :options="banks"
            :searchable="true"
            :close-on-select="true"
            :show-labels="false"
            placeholder="Cari dan pilih bank..."
            label="display_name"
            track-by="id"
            @select="onBankSelect"
            @remove="onBankRemove"
            class="mt-1"
            required
          >
            <template #noOptions>
              <span>Tidak ada bank ditemukan</span>
            </template>
            <template #noResult>
              <span>Tidak ada bank ditemukan</span>
            </template>
          </multiselect>
          <p class="mt-1 text-xs text-gray-500">Cari dan pilih bank dari master data bank untuk {{ form.payment_type }}</p>
        </div> -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Supplier</label>
          <multiselect
            v-model="selectedSupplier"
            :options="suppliers"
            :searchable="true"
            :close-on-select="true"
            :show-labels="false"
            placeholder="Pilih Supplier"
            label="name"
            track-by="id"
            @select="onSupplierChange"
            @remove="onSupplierRemove"
            class="mt-1"
            required
          >
            <template #noOptions>
              <span>Tidak ada supplier ditemukan</span>
            </template>
            <template #noResult>
              <span>Tidak ada supplier ditemukan</span>
            </template>
          </multiselect>
        </div>
        <!-- Card Info Contra Bon -->
        <div class="bg-blue-50 rounded-lg p-4 shadow mb-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold">Pilih Contra Bon yang akan dibayar</h3>
            <div class="text-sm text-gray-600">
              <span v-if="selectedSupplier">{{ filteredContraBons.length }} dari {{ contraBons.length }} contra bon</span>
            </div>
          </div>
          
          <!-- Search Input -->
          <div class="mb-3">
            <div class="relative">
              <input
                type="text"
                v-model="contraBonSearch"
                placeholder="Cari contra bon (nomor, invoice, supplier, total, tanggal, notes, PO, outlet...)"
                class="w-full px-4 py-2 pl-10 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
              <i class="fa fa-search absolute left-3 top-3 text-gray-400"></i>
              <button
                v-if="contraBonSearch"
                @click="contraBonSearch = ''"
                type="button"
                class="absolute right-3 top-2 text-gray-400 hover:text-gray-600"
              >
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>
          
          <!-- Select All / Deselect All -->
          <div v-if="filteredContraBons.length > 0" class="mb-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="selectAllContraBons"
                class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
              >
                <i class="fa fa-check-square mr-1"></i>Pilih Semua
              </button>
              <button
                type="button"
                @click="deselectAllContraBons"
                class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
              >
                <i class="fa fa-square mr-1"></i>Batal Semua
              </button>
            </div>
            <div class="text-xs text-gray-600">
              {{ form.selected_contra_bon_ids.length }} dipilih
            </div>
          </div>
          
          <div class="border rounded p-2 max-h-96 overflow-y-auto bg-white">
            <div v-if="filteredContraBons.length === 0" class="text-gray-400 text-sm p-4 text-center">
              <i class="fa fa-search text-2xl mb-2"></i>
              <div v-if="contraBonSearch">Tidak ada contra bon ditemukan untuk "{{ contraBonSearch }}"</div>
              <div v-else>Tidak ada contra bon yang belum dibayar untuk supplier ini.</div>
            </div>
            <div v-for="cb in filteredContraBons" :key="cb.id" class="flex items-center mb-2 p-3 hover:bg-blue-50 rounded border border-gray-200 transition-colors">
              <input 
                type="checkbox" 
                :value="cb.id" 
                v-model="form.selected_contra_bon_ids" 
                class="mr-3 w-4 h-4 text-blue-600 rounded focus:ring-blue-500" 
              />
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span class="font-medium text-gray-800">{{ cb.number }}</span>
                  <span v-if="cb.source_type_display === 'PR Foods'" class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs font-semibold">
                    🔵 PR Foods
                  </span>
                  <span v-else-if="cb.source_type_display === 'RO Supplier'" class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-semibold">
                    🟢 RO Supplier
                  </span>
                  <span v-else-if="cb.source_type_display === 'Retail Food'" class="bg-purple-100 text-purple-700 px-2 py-1 rounded-full text-xs font-semibold">
                    🟣 Retail Food
                  </span>
                  <span v-else-if="cb.source_type_display === 'Retail Non Food'" class="bg-orange-100 text-orange-700 px-2 py-1 rounded-full text-xs font-semibold">
                    🟠 Retail Non Food
                  </span>
                  <span v-else-if="cb.source_type_display === 'Warehouse Retail Food'" class="bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full text-xs font-semibold">
                    🔷 Warehouse Retail Food
                  </span>
                  <span v-else class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-xs font-semibold">
                    ⚪ Unknown
                  </span>
                </div>
                <div class="text-sm text-gray-600 space-y-1">
                  <div class="flex items-center gap-4 flex-wrap">
                    <span><strong>Total:</strong> {{ formatCurrency(cb.total_amount) }}</span>
                    <span v-if="cb.date" class="text-xs">
                      <i class="fa fa-calendar mr-1"></i>{{ formatDate(cb.date) }}
                    </span>
                  </div>
                  <div v-if="cb.supplier_invoice_number" class="text-xs text-gray-500">
                    <i class="fa fa-file-invoice mr-1"></i><strong>Invoice:</strong> {{ cb.supplier_invoice_number }}
                  </div>
                  <div v-if="cb.supplier?.name" class="text-xs text-gray-500">
                    <i class="fa fa-truck mr-1"></i><strong>Supplier:</strong> {{ cb.supplier.name }}
                  </div>
                  <div v-if="cb.purchaseOrder?.number" class="text-xs text-blue-600">
                    <i class="fa fa-shopping-cart mr-1"></i><strong>PO:</strong> {{ cb.purchaseOrder.number }}
                  </div>
                  <div v-if="cb.retailFood?.number" class="text-xs text-purple-600">
                    <i class="fa fa-store mr-1"></i><strong>Retail:</strong> {{ cb.retailFood.number }}
                  </div>
                  <div v-if="cb.outlet_names && cb.outlet_names.length > 0" class="text-xs text-orange-600 mt-1">
                    <i class="fa fa-map-marker-alt mr-1"></i>
                    <strong>Outlet:</strong> {{ cb.outlet_names.join(', ') }}
                  </div>
                  <div v-if="cb.notes" class="text-xs text-gray-500 italic mt-1">
                    <i class="fa fa-sticky-note mr-1"></i>{{ cb.notes }}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-gray-600">
              <span v-if="form.selected_contra_bon_ids.length > 0">
                {{ form.selected_contra_bon_ids.length }} contra bon dipilih
              </span>
            </div>
            <div class="text-right font-bold text-lg text-blue-700">
              Total Bayar: {{ formatCurrency(totalBayar) }}
            </div>
          </div>
        </div>

        <!-- Payment Per Outlet -->
        <div v-if="selectedContraBonsByOutlet && Object.keys(selectedContraBonsByOutlet).length > 0" class="bg-white rounded-lg p-4 shadow mb-4">
          <h3 class="font-bold text-gray-800 mb-3">Pembayaran Per Outlet</h3>
          <p class="text-sm text-gray-600 mb-4">
            <i class="fa fa-info-circle mr-1"></i>
            Input jumlah pembayaran untuk setiap outlet. Default diisi dari total contra bon per outlet, namun dapat diubah sesuai kebutuhan.
            <span v-if="form.payment_type === 'Transfer' || form.payment_type === 'Giro'" class="block mt-2 text-blue-600">
              <i class="fa fa-university mr-1"></i>
              <strong>Penting:</strong> Pilih bank untuk setiap outlet dengan metode pembayaran <strong>{{ form.payment_type }}</strong>.
            </span>
          </p>
          
          <div class="space-y-4">
            <div v-for="(outletData, outletKey) in selectedContraBonsByOutlet" :key="outletKey" 
                 class="border rounded-lg p-4"
                 :class="outletKey === 'global' ? 'border-orange-300 bg-orange-50' : 'border-blue-300 bg-blue-50'">
              <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <h4 class="text-lg font-semibold" 
                        :class="outletKey === 'global' ? 'text-orange-900' : 'text-blue-900'">
                      <i :class="outletKey === 'global' ? 'fa fa-globe mr-2' : 'fa fa-store mr-2'"></i>
                      {{ outletData.outlet_name || 'Global / All Outlets' }}
                    </h4>
                    <span v-if="outletKey === 'global'" 
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-200 text-orange-800">
                      <i class="fa fa-info-circle mr-1"></i>
                      Global
                    </span>
                    <span v-else 
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-200 text-blue-800">
                      <i class="fa fa-map-marker-alt mr-1"></i>
                      Outlet Spesifik
                    </span>
                  </div>
                  <div class="text-sm mt-1" :class="outletKey === 'global' ? 'text-orange-700' : 'text-blue-700'">
                    <span class="font-medium">Total Contra Bon: {{ formatCurrency(outletData.total_amount) }}</span>
                    <span class="ml-3 text-xs opacity-75">({{ outletData.contra_bon_count }} contra bon)</span>
                  </div>
                  <div v-if="outletData.contra_bons && outletData.contra_bons.length > 0" class="mt-2 text-xs" :class="outletKey === 'global' ? 'text-orange-600' : 'text-blue-600'">
                    <i class="fa fa-list-ul mr-1"></i>
                    <strong>Contra Bon:</strong> 
                    <span class="ml-1">{{ outletData.contra_bons.map(cb => cb.number).join(', ') }}</span>
                  </div>
                </div>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Jumlah Pembayaran untuk Outlet Ini <span class="text-red-500">*</span>
                  </label>
                  <input 
                    type="number" 
                    v-model="outletPayments[outletKey].amount" 
                    step="0.01"
                    :min="0"
                    required 
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                    placeholder="0.00"
                    @input="updateTotalAmount"
                  />
                  <p class="mt-1 text-xs text-gray-500">
                    Default: {{ formatCurrency(outletData.total_amount) }}
                  </p>
                </div>
                <div class="flex items-end">
                  <button
                    type="button"
                    @click="resetOutletAmount(outletKey, outletData.total_amount)"
                    class="px-4 py-2 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition"
                  >
                    <i class="fa fa-undo mr-1"></i>
                    Reset ke Default
                  </button>
                </div>
              </div>

              <!-- Bank Selection per Outlet (hanya muncul jika Transfer atau Giro) -->
              <div v-if="form.payment_type === 'Transfer' || form.payment_type === 'Giro'" class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fa fa-university mr-1"></i>
                  Pilih Bank untuk Outlet Ini <span class="text-red-500">*</span>
                </label>
                <multiselect
                  v-model="outletPayments[outletKey].selectedBank"
                  :options="banks"
                  :searchable="true"
                  :close-on-select="true"
                  :show-labels="false"
                  placeholder="Cari dan pilih bank untuk outlet ini..."
                  label="display_name"
                  track-by="id"
                  @select="(bank) => onOutletBankSelect(outletKey, bank)"
                  @remove="() => onOutletBankRemove(outletKey)"
                  class="w-full"
                  required
                >
                  <template #noOptions>
                    <span>Tidak ada bank ditemukan</span>
                  </template>
                  <template #noResult>
                    <span>Tidak ada bank ditemukan</span>
                  </template>
                </multiselect>
                <p class="mt-1 text-xs text-gray-500">
                  Pilih bank untuk metode pembayaran <strong>{{ form.payment_type }}</strong> di outlet ini
                </p>
                <p v-if="outletPayments[outletKey].selectedBank" class="mt-1 text-xs text-green-600">
                  <i class="fa fa-check-circle mr-1"></i>
                  Bank terpilih: <strong>{{ outletPayments[outletKey].selectedBank.display_name }}</strong>
                </p>
              </div>
            </div>
          </div>
          
          <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex justify-between items-center">
              <span class="text-sm font-medium text-gray-700">Total Pembayaran Semua Outlet:</span>
              <span class="text-lg font-bold text-blue-600">{{ formatCurrency(totalOutletPayments) }}</span>
            </div>
            <p class="text-xs text-gray-600 mt-2">
              <i class="fa fa-info-circle mr-1"></i>
              Total ini akan otomatis terisi ke field Total Bayar di atas. Anda juga bisa mengubah Total Bayar secara manual.
            </p>
          </div>
        </div>

        <!-- Upload Bukti Transfer -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Upload Bukti Transfer (image/pdf)</label>
          <input type="file" accept="image/*,application/pdf" @change="onFileChange" class="mt-1 block" />
          <div v-if="existingBuktiPath && !filePreview" class="mt-2">
            <div v-if="isImageFile(existingBuktiPath)" class="mt-2">
              <img :src="`/storage/${existingBuktiPath}`" alt="Current Bukti" class="max-w-xs rounded shadow" />
            </div>
            <div v-else class="mt-2">
              <a :href="`/storage/${existingBuktiPath}`" target="_blank" class="text-blue-500 hover:underline">
                <i class="fas fa-file-pdf mr-1"></i> Lihat Bukti Transfer Saat Ini
              </a>
            </div>
          </div>
          <div v-if="filePreview && isImage" class="mt-2">
            <img :src="filePreview" alt="Preview" class="max-w-xs rounded shadow" />
          </div>
          <div v-if="filePreview && isPdf" class="mt-2">
            <a :href="filePreview" target="_blank" class="text-blue-500 hover:underline">
              <i class="fas fa-file-pdf mr-1"></i> Preview PDF
            </a>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Notes</label>
          <textarea v-model="form.notes" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button type="button" @click.prevent="showPreview" :disabled="isSubmitting" class="px-4 py-2 rounded bg-gradient-to-r from-blue-500 to-blue-700 text-white font-semibold hover:from-blue-600 hover:to-blue-800 disabled:opacity-50">
            <i class="fa fa-eye mr-2"></i>
            Preview & Simpan
          </button>
        </div>
      </div>

      <!-- Preview Modal -->
      <div v-if="showPreviewModal" class="fixed inset-0 z-50 overflow-y-auto">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showPreviewModal = false" style="z-index: 1;"></div>
        
        <!-- Modal container -->
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0 relative" style="z-index: 2;">
          <!-- Modal panel -->
          <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full relative" @click.stop>
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between">
              <h3 class="text-lg font-semibold text-white">
                <i class="fa fa-eye mr-2"></i>
                Preview Data Food Payment
              </h3>
              <button
                @click="showPreviewModal = false"
                class="text-white hover:text-gray-200 focus:outline-none"
              >
                <i class="fa fa-times text-xl"></i>
              </button>
            </div>

            <!-- Content -->
            <div class="px-6 py-6 max-h-[70vh] overflow-y-auto">
              <div class="space-y-6">
                <!-- Payment Information -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                  <h4 class="font-semibold text-blue-800 mb-3">
                    <i class="fa fa-credit-card mr-2"></i>
                    Informasi Pembayaran
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div>
                      <span class="font-medium text-gray-700">Tanggal:</span>
                      <span class="ml-2 text-gray-900">{{ formatDate(form.date) }}</span>
                    </div>
                    <div>
                      <span class="font-medium text-gray-700">Payment Type:</span>
                      <span class="ml-2 text-gray-900 capitalize">{{ form.payment_type || '-' }}</span>
                    </div>
                    <div>
                      <span class="font-medium text-gray-700">Supplier:</span>
                      <span class="ml-2 text-gray-900">{{ selectedSupplier ? selectedSupplier.name : '-' }}</span>
                    </div>
                    <div>
                      <span class="font-medium text-gray-700">Total Bayar:</span>
                      <span class="ml-2 text-lg font-bold text-green-600">{{ formatCurrency(totalBayar) }}</span>
                    </div>
                    <div v-if="form.notes" class="md:col-span-2">
                      <span class="font-medium text-gray-700">Notes:</span>
                      <p class="mt-1 text-gray-900 whitespace-pre-wrap">{{ form.notes }}</p>
                    </div>
                  </div>
                </div>

                <!-- Selected Contra Bons -->
                <div v-if="form.selected_contra_bon_ids.length > 0" class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                  <h4 class="font-semibold text-green-800 mb-3">
                    <i class="fa fa-list-ul mr-2"></i>
                    Contra Bon yang Dipilih ({{ form.selected_contra_bon_ids.length }})
                  </h4>
                  <div class="space-y-2 max-h-60 overflow-y-auto">
                    <div v-for="cb in selectedContraBons" :key="cb.id" class="bg-white border border-green-200 rounded-lg p-3">
                      <div class="flex items-center justify-between">
                        <div class="flex-1">
                          <div class="flex items-center gap-2 mb-1">
                            <span class="font-semibold text-gray-900">{{ cb.number }}</span>
                            <span v-if="cb.source_type_display === 'PR Foods'" class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs font-semibold">
                              🔵 PR Foods
                            </span>
                            <span v-else-if="cb.source_type_display === 'RO Supplier'" class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-semibold">
                              🟢 RO Supplier
                            </span>
                            <span v-else-if="cb.source_type_display === 'Retail Food'" class="bg-purple-100 text-purple-700 px-2 py-1 rounded-full text-xs font-semibold">
                              🟣 Retail Food
                            </span>
                            <span v-else-if="cb.source_type_display === 'Retail Non Food'" class="bg-orange-100 text-orange-700 px-2 py-1 rounded-full text-xs font-semibold">
                              🟠 Retail Non Food
                            </span>
                            <span v-else-if="cb.source_type_display === 'Warehouse Retail Food'" class="bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full text-xs font-semibold">
                              🔷 Warehouse Retail Food
                            </span>
                          </div>
                          <div class="text-xs text-gray-600 space-y-1">
                            <div><strong>Total:</strong> {{ formatCurrency(cb.total_amount) }}</div>
                            <div v-if="cb.date"><strong>Tanggal:</strong> {{ formatDate(cb.date) }}</div>
                            <div v-if="cb.supplier_invoice_number"><strong>Invoice:</strong> {{ cb.supplier_invoice_number }}</div>
                            <div v-if="cb.outlet_names && cb.outlet_names.length > 0">
                              <strong>Outlet:</strong> {{ Array.isArray(cb.outlet_names) ? cb.outlet_names.join(', ') : cb.outlet_names }}
                            </div>
                          </div>
                        </div>
                        <div class="text-right">
                          <div class="text-lg font-bold text-green-600">{{ formatCurrency(cb.total_amount) }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Payment Per Outlet -->
                <div v-if="selectedContraBonsByOutlet && Object.keys(selectedContraBonsByOutlet).length > 0" class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded">
                  <h4 class="font-semibold text-purple-800 mb-3">
                    <i class="fa fa-store mr-2"></i>
                    Pembayaran Per Outlet
                  </h4>
                  <div class="space-y-3">
                    <div v-for="(outletData, outletKey) in selectedContraBonsByOutlet" :key="outletKey" class="bg-white border border-purple-200 rounded-lg p-3">
                      <div class="flex justify-between items-start mb-2">
                        <div>
                          <h5 class="font-semibold text-gray-900">{{ outletData.outlet_name || 'Global / All Outlets' }}</h5>
                          <div class="text-xs text-gray-600 mt-1">
                            <span>Total Contra Bon: {{ formatCurrency(outletData.total_amount) }}</span>
                            <span class="ml-2">({{ outletData.contra_bon_count }} contra bon)</span>
                          </div>
                          <div v-if="outletData.contra_bons && outletData.contra_bons.length > 0" class="text-xs text-gray-500 mt-1">
                            <strong>Contra Bon:</strong> {{ outletData.contra_bons.map(cb => cb.number).join(', ') }}
                          </div>
                        </div>
                        <div class="text-right">
                          <div class="text-lg font-bold text-purple-600">
                            {{ formatCurrency(outletPayments[outletKey]?.amount || 0) }}
                          </div>
                          <div class="text-xs text-gray-500">Amount</div>
                        </div>
                      </div>
                      <div v-if="(form.payment_type === 'Transfer' || form.payment_type === 'Giro') && outletPayments[outletKey]?.selectedBank" class="mt-2 pt-2 border-t border-purple-200">
                        <span class="text-xs font-medium text-gray-700">Bank:</span>
                        <span class="ml-2 text-xs text-gray-900">{{ outletPayments[outletKey].selectedBank.display_name }}</span>
                      </div>
                    </div>
                    <div class="bg-white border border-purple-300 rounded-lg p-3 mt-3">
                      <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-700">Total Pembayaran Semua Outlet:</span>
                        <span class="text-xl font-bold text-purple-600">{{ formatCurrency(totalOutletPayments) }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Bukti Transfer -->
                <div v-if="filePreview || existingBuktiPath" class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
                  <h4 class="font-semibold text-amber-800 mb-3">
                    <i class="fa fa-file-upload mr-2"></i>
                    Bukti Transfer
                  </h4>
                  <div v-if="filePreview && isImage" class="mt-2">
                    <img :src="filePreview" alt="Preview" class="max-w-xs rounded shadow" />
                  </div>
                  <div v-else-if="filePreview && isPdf" class="mt-2">
                    <a :href="filePreview" target="_blank" class="text-blue-500 hover:underline">
                      <i class="fas fa-file-pdf mr-1"></i> Preview PDF
                    </a>
                  </div>
                  <div v-else-if="existingBuktiPath" class="mt-2">
                    <div v-if="isImageFile(existingBuktiPath)">
                      <img :src="`/storage/${existingBuktiPath}`" alt="Current Bukti" class="max-w-xs rounded shadow" />
                    </div>
                    <div v-else>
                      <a :href="`/storage/${existingBuktiPath}`" target="_blank" class="text-blue-500 hover:underline">
                        <i class="fas fa-file-pdf mr-1"></i> Lihat Bukti Transfer Saat Ini
                      </a>
                    </div>
                  </div>
                </div>

                <!-- Warning/Info -->
                <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
                  <h4 class="font-semibold text-amber-800 mb-2">
                    <i class="fa fa-exclamation-triangle mr-2"></i>
                    Konfirmasi
                  </h4>
                  <p class="text-sm text-amber-700">
                    Pastikan semua data di atas sudah benar sebelum menyimpan. Setelah disimpan, data tidak dapat diubah.
                  </p>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 flex justify-between">
              <button
                @click="showPreviewModal = false"
                class="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
              >
                <i class="fa fa-times mr-2"></i>
                Batal
              </button>
              <button
                @click="confirmSubmit"
                :disabled="isSubmitting"
                class="px-6 py-2 bg-gradient-to-r from-green-500 to-green-700 text-white rounded-md hover:from-green-600 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i v-if="isSubmitting" class="fa fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa fa-check mr-2"></i>
                {{ isSubmitting ? 'Menyimpan...' : 'Konfirmasi & Simpan' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  payment: {
    type: Object,
    default: null
  },
  banks: {
    type: Array,
    default: () => []
  }
});

const isEditMode = computed(() => !!props.payment);

const suppliers = ref([]);
const selectedSupplier = ref(null);
const contraBons = ref([]);
const contraBonSearch = ref('');
const selectedBank = ref(null);
const form = ref({
  date: '',
  payment_type: '',
  bank_id: null,
  selected_contra_bon_ids: [],
  notes: '',
});

// Transform banks untuk multiselect dengan display name yang include outlet
// Format sama seperti di BankAccount/Index: menggunakan outlet.nama_outlet
const banks = computed(() => {
  if (!props.banks || !Array.isArray(props.banks)) return [];
  return props.banks.map(bank => {
    // Gunakan outlet.nama_outlet jika ada, atau 'Head Office' jika null
    const outletName = bank.outlet?.nama_outlet || bank.outlet_name || 'Head Office';
    return {
      ...bank,
      display_name: `${bank.bank_name} - ${bank.account_number} (${bank.account_name}) - ${outletName}`
    };
  });
});

const file = ref(null);
const filePreview = ref(null);
const isImage = ref(false);
const isPdf = ref(false);
const existingBuktiPath = ref(null);
const showPreviewModal = ref(false);
const isSubmitting = ref(false);

const outletPayments = ref({});

const totalBayar = computed(() => {
  return contraBons.value
    .filter(cb => form.value.selected_contra_bon_ids.includes(cb.id))
    .reduce((sum, cb) => {
      const amount = parseFloat(cb.total_amount) || 0;
      return sum + amount;
    }, 0);
});

// Group selected contra bons by outlet
const selectedContraBonsByOutlet = computed(() => {
  const selected = contraBons.value.filter(cb => form.value.selected_contra_bon_ids.includes(cb.id));
  
  // Return empty jika tidak ada yang dipilih
  if (selected.length === 0) {
    return {};
  }
  
  const grouped = {};
  
  selected.forEach(cb => {
    // Handle outlet_names: bisa array, string, atau null/undefined
    let outletNames = [];
    if (cb.outlet_names) {
      if (Array.isArray(cb.outlet_names)) {
        outletNames = cb.outlet_names.filter(name => name && name.trim() !== '');
      } else if (typeof cb.outlet_names === 'string') {
        outletNames = [cb.outlet_names].filter(name => name && name.trim() !== '');
      }
    }
    
    if (outletNames.length === 0) {
      // No outlet, group as "Global"
      const key = 'global';
      if (!grouped[key]) {
        grouped[key] = {
          outlet_id: null,
          outlet_name: 'Global / All Outlets',
          contra_bons: [],
          total_amount: 0,
          contra_bon_count: 0
        };
      }
      grouped[key].contra_bons.push(cb);
      grouped[key].total_amount += parseFloat(cb.total_amount) || 0;
      grouped[key].contra_bon_count += 1;
    } else {
      // Group by each outlet
      outletNames.forEach(outletName => {
        const key = `outlet_${outletName}`;
        if (!grouped[key]) {
          grouped[key] = {
            outlet_id: null, // Will need to get from outlet name if needed
            outlet_name: outletName,
            contra_bons: [],
            total_amount: 0,
            contra_bon_count: 0
          };
        }
        grouped[key].contra_bons.push(cb);
        // Distribute amount equally if contra bon has multiple outlets
        const amountPerOutlet = (parseFloat(cb.total_amount) || 0) / outletNames.length;
        grouped[key].total_amount += amountPerOutlet;
        grouped[key].contra_bon_count += 1;
      });
    }
  });
  
  return grouped;
});

// Computed total dari semua outlet payments
const totalOutletPayments = computed(() => {
  return Object.values(outletPayments.value).reduce((sum, outlet) => {
    return sum + (parseFloat(outlet.amount) || 0);
  }, 0);
});

// Computed untuk selected contra bons (untuk preview)
const selectedContraBons = computed(() => {
  return contraBons.value.filter(cb => form.value.selected_contra_bon_ids.includes(cb.id));
});

// Watch selectedContraBonsByOutlet untuk initialize outletPayments
watch(selectedContraBonsByOutlet, (newVal) => {
  if (newVal && Object.keys(newVal).length > 0) {
    initializeOutletPayments();
  }
}, { immediate: true });

// Function untuk initialize outlet payments
function initializeOutletPayments() {
  outletPayments.value = {};
  if (selectedContraBonsByOutlet.value && Object.keys(selectedContraBonsByOutlet.value).length > 0) {
    Object.keys(selectedContraBonsByOutlet.value).forEach(outletKey => {
      const outletData = selectedContraBonsByOutlet.value[outletKey];
      outletPayments.value[outletKey] = {
        outlet_id: outletData.outlet_id || null,
        amount: outletData.total_amount || 0,
        bank_id: null,
        selectedBank: null
      };
    });
  }
}

// Function untuk update total amount dari outlet payments
function updateTotalAmount() {
  // Total bayar akan otomatis update dari totalOutletPayments
}

// Function untuk reset outlet amount ke default
function resetOutletAmount(outletKey, defaultAmount) {
  if (outletPayments.value[outletKey]) {
    outletPayments.value[outletKey].amount = defaultAmount;
  }
}

// Function untuk handle bank selection per outlet
function onOutletBankSelect(outletKey, bank) {
  if (bank && bank.id) {
    outletPayments.value[outletKey].bank_id = bank.id;
    outletPayments.value[outletKey].selectedBank = bank;
  }
}

// Function untuk handle bank removal per outlet
function onOutletBankRemove(outletKey) {
  outletPayments.value[outletKey].bank_id = null;
  outletPayments.value[outletKey].selectedBank = null;
}

// Filter contra bons based on search
const filteredContraBons = computed(() => {
  if (!contraBonSearch.value) {
    return contraBons.value;
  }
  
  const search = contraBonSearch.value.toLowerCase();
  return contraBons.value.filter(cb => {
    // Search in multiple fields
    const number = (cb.number || '').toLowerCase();
    const invoiceNumber = (cb.supplier_invoice_number || '').toLowerCase();
    const supplierName = (cb.supplier?.name || '').toLowerCase();
    const totalAmount = (cb.total_amount || '').toString().toLowerCase();
    const date = cb.date ? new Date(cb.date).toLocaleDateString('id-ID').toLowerCase() : '';
    const notes = (cb.notes || '').toLowerCase();
    const poNumber = (cb.purchaseOrder?.number || '').toLowerCase();
    const retailNumber = (cb.retailFood?.number || '').toLowerCase();
    const outletNames = (cb.outlet_names || []).join(' ').toLowerCase();
    const sourceType = (cb.source_type_display || '').toLowerCase();
    
    return number.includes(search) ||
           invoiceNumber.includes(search) ||
           supplierName.includes(search) ||
           totalAmount.includes(search) ||
           date.includes(search) ||
           notes.includes(search) ||
           poNumber.includes(search) ||
           retailNumber.includes(search) ||
           outletNames.includes(search) ||
           sourceType.includes(search);
  });
});

function formatDate(dateString) {
  if (!dateString) return '-';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  } catch (e) {
    return dateString;
  }
}

function selectAllContraBons() {
  form.value.selected_contra_bon_ids = filteredContraBons.value.map(cb => cb.id);
}

function deselectAllContraBons() {
  form.value.selected_contra_bon_ids = [];
}

function goBack() { 
  router.visit('/food-payments'); 
}

function onFileChange(e) {
  const f = e.target.files[0];
  file.value = f;
  if (!f) { 
    filePreview.value = null; 
    isImage.value = false; 
    isPdf.value = false; 
    return; 
  }
  if (f.type.startsWith('image/')) {
    isImage.value = true; 
    isPdf.value = false;
    const reader = new FileReader();
    reader.onload = ev => { 
      filePreview.value = ev.target.result; 
    };
    reader.readAsDataURL(f);
  } else if (f.type === 'application/pdf') {
    isImage.value = false; 
    isPdf.value = true;
    filePreview.value = URL.createObjectURL(f);
  } else {
    filePreview.value = null; 
    isImage.value = false; 
    isPdf.value = false;
  }
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

async function onSupplierChange(supplier) {
  if (!isEditMode.value) {
    form.value.selected_contra_bon_ids = [];
  }
  contraBons.value = [];
  contraBonSearch.value = '';
  if (!supplier || !supplier.id) return;
  try {
    const res = await axios.get('/api/food-payments/contra-bon-unpaid', {
      params: {
        supplier_id: supplier.id
      }
    });
    // Pastikan total_amount adalah number
    let availableContraBons = res.data.map(cb => ({
      ...cb,
      total_amount: parseFloat(cb.total_amount) || 0
    }));
    
    // Jika edit mode, tambahkan contra bon yang sudah dipilih meskipun sudah paid
    if (isEditMode.value && props.payment?.contra_bons) {
      const selectedIds = props.payment.contra_bons.map(cb => cb.id);
      const selectedContraBons = props.payment.contra_bons.map(cb => ({
        ...cb,
        total_amount: parseFloat(cb.total_amount || cb.pivot?.total_amount || 0)
      }));
      // Gabungkan dengan yang available, pastikan tidak duplikat
      const existingIds = availableContraBons.map(cb => cb.id);
      selectedContraBons.forEach(cb => {
        if (!existingIds.includes(cb.id)) {
          availableContraBons.push(cb);
        }
      });
    }
    
    contraBons.value = availableContraBons;
  } catch (e) {
    contraBons.value = [];
    Swal.fire('Error', 'Gagal mengambil data Contra Bon', 'error');
  }
}

function onSupplierRemove() {
  form.value.selected_contra_bon_ids = [];
  contraBons.value = [];
  contraBonSearch.value = '';
  selectedSupplier.value = null;
}

function onBankSelect(bank) {
  if (bank && bank.id) {
    form.value.bank_id = bank.id;
  }
}

function onBankRemove() {
  form.value.bank_id = null;
  selectedBank.value = null;
}

function isImageFile(path) {
  return path && (path.endsWith('.jpg') || path.endsWith('.jpeg') || path.endsWith('.png'));
}

function showPreview(event) {
  // Prevent any default form submission
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  // IMPORTANT: This function should ONLY show preview modal, NOT submit!
  // Do NOT call confirmSubmit() here!

  // Validate date
  if (!form.value.date) {
    Swal.fire('Error', 'Tanggal harus diisi', 'error');
    return false;
  }

  // Validate payment type
  if (!form.value.payment_type) {
    Swal.fire('Error', 'Payment Type harus dipilih', 'error');
    return false;
  }

  // Validate supplier
  if (!selectedSupplier.value || !selectedSupplier.value.id) {
    Swal.fire('Error', 'Pilih supplier terlebih dahulu', 'error');
    return false;
  }

  // Validate contra bon
  if (form.value.selected_contra_bon_ids.length === 0) {
    Swal.fire('Error', 'Pilih minimal satu contra bon', 'error');
    return false;
  }

  // Validate bank per outlet if payment method is Transfer or Giro
  if (form.value.payment_type === 'Transfer' || form.value.payment_type === 'Giro') {
    const outletsWithoutBank = Object.keys(outletPayments.value).filter(outletKey => {
      const outlet = outletPayments.value[outletKey];
      return outlet.amount && parseFloat(outlet.amount) > 0 && !outlet.bank_id;
    });
    
    if (outletsWithoutBank.length > 0) {
      Swal.fire('Error', 'Semua outlet yang memiliki jumlah pembayaran harus memiliki bank yang dipilih untuk metode pembayaran ' + form.value.payment_type + '.', 'error');
      return false;
    }
  }

  // Show preview modal - ONLY show modal, do NOT submit
  console.log('Showing preview modal...');
  showPreviewModal.value = true;
  
  // IMPORTANT: Do NOT call confirmSubmit here!
  // confirmSubmit should ONLY be called from the preview modal button
  return false;
}

async function confirmSubmit() {
  console.log('confirmSubmit called - this should ONLY happen from preview modal button');
  isSubmitting.value = true;
  showPreviewModal.value = false;

  try {
    Swal.fire({
      title: isEditMode.value ? 'Memperbarui Data...' : 'Menyimpan Data...',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading(),
    });

    const formData = new FormData();
    formData.append('date', form.value.date);
    formData.append('payment_type', form.value.payment_type);
    if (form.value.bank_id) {
      formData.append('bank_id', form.value.bank_id);
    }
    formData.append('notes', form.value.notes);
    formData.append('supplier_id', selectedSupplier.value.id);
    form.value.selected_contra_bon_ids.forEach(id => {
      formData.append('contra_bon_ids[]', id);
    });
    if (file.value) {
      formData.append('bukti_transfer', file.value);
    }

    // Convert outletPayments object to array and append to formData
    const outletPaymentsArray = Object.values(outletPayments.value)
      .filter(outlet => outlet.amount && parseFloat(outlet.amount) > 0)
      .map(outlet => ({
        outlet_id: outlet.outlet_id,
        amount: outlet.amount,
        bank_id: outlet.bank_id || null
      }));
    
    if (outletPaymentsArray.length > 0) {
      // Append each outlet payment as array item
      outletPaymentsArray.forEach((outlet, index) => {
        formData.append(`outlet_payments[${index}][outlet_id]`, outlet.outlet_id || '');
        formData.append(`outlet_payments[${index}][amount]`, outlet.amount);
        if (outlet.bank_id) {
          formData.append(`outlet_payments[${index}][bank_id]`, outlet.bank_id);
        }
      });
    }

    const url = isEditMode.value 
      ? `/food-payments/${props.payment.id}`
      : '/food-payments';
    const method = isEditMode.value ? 'put' : 'post';

    const response = await axios[method](url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    Swal.fire('Berhasil', isEditMode.value ? 'Data berhasil diperbarui' : 'Data berhasil disimpan', 'success').then(() => {
      router.visit('/food-payments');
    });
  } catch (error) {
    Swal.close();
    if (error.response?.data?.errors) {
      // Handle validation errors
      const errors = error.response.data.errors;
      Object.keys(errors).forEach(key => {
        Swal.fire('Error', errors[key][0], 'error');
      });
    } else {
      Swal.fire('Error', `Terjadi kesalahan saat ${isEditMode.value ? 'memperbarui' : 'menyimpan'} data`, 'error');
    }
  } finally {
    isSubmitting.value = false;
  }
}

onMounted(async () => {
  try {
    const res = await axios.get('/api/suppliers');
    suppliers.value = res.data || [];
  } catch (e) {
    suppliers.value = [];
    Swal.fire('Error', 'Gagal mengambil data supplier', 'error');
  }

  if (isEditMode.value && props.payment) {
    // Load data payment untuk edit
    form.value.date = props.payment.date || '';
    form.value.payment_type = props.payment.payment_type || '';
    form.value.bank_id = props.payment.bank_id || null;
    form.value.notes = props.payment.notes || '';
    
    // Set selected bank object
    if (props.payment.bank_id && banks.value.length > 0) {
      const bank = banks.value.find(b => b.id == props.payment.bank_id);
      if (bank) {
        selectedBank.value = bank;
      }
    }
    
    // Set selected supplier object - pastikan suppliers sudah ter-load
    if (props.payment.supplier_id && suppliers.value.length > 0) {
      const supplier = suppliers.value.find(s => s.id == props.payment.supplier_id);
      if (supplier) {
        selectedSupplier.value = supplier;
        // Trigger load contra bon untuk supplier ini
        if (props.payment.contra_bons && props.payment.contra_bons.length > 0) {
          form.value.selected_contra_bon_ids = props.payment.contra_bons.map(cb => cb.id);
          await onSupplierChange(supplier);
        }
      }
    }
    
    existingBuktiPath.value = props.payment.bukti_transfer_path || null;
  } else {
    // Set default date ke hari ini untuk create mode
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    form.value.date = `${yyyy}-${mm}-${dd}`;
  }
});
</script>

<script>
export default { 
  components: {
    Multiselect
  },
  filters: { 
    currency(val) { 
      return 'Rp ' + Number(val).toLocaleString('id-ID'); 
    } 
  } 
}
</script>

<style scoped>
/* Custom multiselect styling */
.multiselect {
  min-height: 42px;
}

.multiselect :deep(.multiselect__tags) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  min-height: 42px;
  padding: 8px 12px;
}

.multiselect :deep(.multiselect__placeholder) {
  color: #6b7280;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect :deep(.multiselect__single) {
  color: #111827;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect :deep(.multiselect__input) {
  border: none;
  padding: 0;
  margin: 0;
  min-height: auto;
}

.multiselect :deep(.multiselect__input:focus) {
  outline: none;
}

.multiselect :deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.multiselect :deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

.multiselect :deep(.multiselect__option--selected) {
  background: #eff6ff;
  color: #1e40af;
  font-weight: 600;
}

.multiselect :deep(.multiselect__option--selected.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}
</style> 