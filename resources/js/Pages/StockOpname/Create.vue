<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Buat Stock Opname Baru
        </h1>
        <div class="flex items-center gap-2">
          <a
            :href="route('stock-opnames.download-template')"
            class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-download mr-2"></i> Download Template
          </a>
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
        <!-- Autosave Status -->
        <div class="mb-4 flex items-center justify-end gap-2 text-sm">
          <span v-if="autosaveStatus === 'saving'" class="text-blue-600 flex items-center gap-1">
            <i class="fas fa-spinner fa-spin"></i>
            Menyimpan...
          </span>
          <span v-else-if="autosaveStatus === 'saved'" class="text-green-600 flex items-center gap-1">
            <i class="fas fa-check-circle"></i>
            Tersimpan
            <span v-if="lastSavedAt" class="text-xs text-gray-500">
              ({{ new Date(lastSavedAt).toLocaleTimeString('id-ID') }})
            </span>
          </span>
          <span v-else-if="autosaveStatus === 'error'" class="text-red-600 flex items-center gap-1">
            <i class="fas fa-exclamation-circle"></i>
            Gagal menyimpan
          </span>
        </div>

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
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Items ({{ form.items.length }})</h3>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase w-48">Item</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Small
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Medium
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">
                    Qty Physical<br/>Large
                  </th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-32">MAC</th>
                  <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase w-40">Subtotal<br/>(Qty × MAC)</th>
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
                    <td class="px-4 py-3 font-bold text-gray-800" colspan="8">
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
                  <tr
                    v-for="item in category"
                    :key="item.inventory_item_id"
                    v-show="expandedCategories.has(categoryName)"
                    :class="{ 'bg-yellow-50': hasDifference(item) }"
                    class="hover:bg-gray-50 transition"
                  >
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                      <div class="font-semibold pl-6">{{ item.item_name }}</div>
                    </td>
                    <td class="px-3 py-3">
                      <div class="flex items-center gap-2">
                        <input
                          v-model.number="item.qty_physical_small"
                          type="number"
                          step="any"
                          min="0"
                          @input="onQtyPhysicalChange(item, 'small')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                          @input="onQtyPhysicalChange(item, 'medium')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                          @input="onQtyPhysicalChange(item, 'large')"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-right text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                    <td class="px-3 py-3 text-center text-sm">
                      <div v-if="hasDifference(item)">
                        <span
                          :class="getDifferenceClass(item)"
                          class="px-2 py-1 rounded text-xs font-semibold whitespace-nowrap"
                        >
                          {{ getDifferenceSign(item) }}
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
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      />
                      <span v-else class="text-gray-400 text-xs">-</span>
                    </td>
                  </tr>
                </template>
              </tbody>
              <tfoot class="bg-gray-100 border-t-2 border-gray-400">
                <tr>
                  <td class="px-4 py-4 text-right font-bold text-gray-900" colspan="5">
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
          <p>Tidak ada item dengan stock untuk outlet dan warehouse outlet yang dipilih.</p>
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

      <!-- Modal Import Stock Opname -->
      <Modal :show="showImportModal" @close="closeImportModal" max-width="2xl">
        <div class="p-6">
          <h2 class="text-lg font-medium text-gray-900 mb-4">Import Stock Opname</h2>
          <p class="text-sm text-gray-600 mb-4">Upload file Excel (template). Kolom: Kategori, Nama Item, Qty Terkecil, Unit Terkecil, Alasan. MAC diisi otomatis dari sistem.</p>

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

          <!-- Info dari preview -->
          <div v-if="previewInfo && (previewInfo.outlet || previewInfo.warehouse_outlet)" class="mb-4 p-3 bg-gray-50 rounded text-sm">
            <div><span class="font-medium">Outlet:</span> {{ previewInfo.outlet }}</div>
            <div><span class="font-medium">Warehouse Outlet:</span> {{ previewInfo.warehouse_outlet }}</div>
            <div><span class="font-medium">Tanggal Opname:</span> {{ previewInfo.tanggal_opname }}</div>
            <div v-if="previewInfo.catatan"><span class="font-medium">Catatan:</span> {{ previewInfo.catatan }}</div>
          </div>

          <!-- Tabel Preview -->
          <div v-if="previewRows.length > 0" class="mb-4 overflow-x-auto max-h-80">
            <table class="min-w-full divide-y divide-gray-200 border text-sm">
              <thead class="bg-gray-100 sticky top-0">
                <tr>
                  <th class="px-2 py-2 text-left">No</th>
                  <th class="px-2 py-2 text-left">Kategori</th>
                  <th class="px-2 py-2 text-left">Nama Item</th>
                  <th class="px-2 py-2 text-right">Qty Terkecil</th>
                  <th class="px-2 py-2 text-left">Unit</th>
                  <th class="px-2 py-2 text-right">Qty System</th>
                  <th class="px-2 py-2 text-right">MAC</th>
                  <th class="px-2 py-2 text-right">Qty Physical</th>
                  <th class="px-2 py-2 text-right">Selisih</th>
                  <th class="px-2 py-2 text-left">Alasan</th>
                  <th class="px-2 py-2 text-left">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr
                  v-for="(r, i) in previewRows"
                  :key="i"
                  :class="{ 'bg-red-50': r.status === 'error' }"
                >
                  <td class="px-2 py-2">{{ r.no }}</td>
                  <td class="px-2 py-2">{{ r.kategori }}</td>
                  <td class="px-2 py-2">{{ r.nama_item }}</td>
                  <td class="px-2 py-2 text-right">{{ formatNumber(r.qty_terkecil) }}</td>
                  <td class="px-2 py-2">{{ r.unit_terkecil }}</td>
                  <td class="px-2 py-2 text-right">{{ formatNumber(r.qty_system_small) }}</td>
                  <td class="px-2 py-2 text-right">{{ formatNumber(r.mac) }}</td>
                  <td class="px-2 py-2 text-right">{{ formatNumber(r.qty_physical_small) }}</td>
                  <td class="px-2 py-2 text-right" :class="(r.selisih_small || 0) > 0 ? 'text-green-600' : (r.selisih_small || 0) < 0 ? 'text-red-600' : ''">
                    {{ formatNumber(r.selisih_small) }}
                  </td>
                  <td class="px-2 py-2">{{ r.alasan }}</td>
                  <td class="px-2 py-2">
                    <span v-if="r.status === 'error'" class="text-red-600 text-xs" :title="r.error">{{ r.error || 'Error' }}</span>
                    <span v-else class="text-green-600 text-xs">OK</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Hasil Import -->
          <div v-if="importResults" class="mb-4 p-4 rounded" :class="importResults.success ? 'bg-green-50' : 'bg-red-50'">
            <div class="flex items-start gap-2">
              <i :class="importResults.success ? 'fas fa-check-circle text-green-500' : 'fas fa-exclamation-circle text-red-500'"></i>
              <div>
                <p :class="importResults.success ? 'text-green-800' : 'text-red-800'">
                  {{ importResults.message }}
                  <Link v-if="importResults.success && importResults.id" :href="route('stock-opnames.show', importResults.id)" class="text-blue-600 underline ml-2">Lihat Stock Opname</Link>
                </p>
                <div v-if="importResults.errors && importResults.errors.length" class="mt-2 text-sm text-red-700">
                  <span class="font-medium">{{ importResults.errors.length }} error:</span>
                  <ul class="list-disc pl-5 mt-1">
                    <li v-for="(e, i) in importResults.errors" :key="i">Baris {{ e.row }}: {{ e.error }}</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 mt-4">
            <button type="button" @click="closeImportModal" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded font-medium">
              Tutup
            </button>
            <button
              v-if="selectedImportFile && !importResults"
              type="button"
              @click="previewImport"
              class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded font-medium"
            >
              Preview
            </button>
            <button
              v-if="selectedImportFile && !importResults"
              type="button"
              @click="processImport"
              :disabled="importLoading"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium disabled:opacity-50"
            >
              <span v-if="importLoading"><i class="fas fa-spinner fa-spin mr-1"></i> Importing...</span>
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
import { ref, computed, watch } from 'vue';
import axios from 'axios';

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
  opname_date: new Date().toISOString().split('T')[0],
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

// Import modal
const showImportModal = ref(false);
const selectedImportFile = ref(null);
const previewInfo = ref(null);
const previewRows = ref([]);
const importResults = ref(null);
const importLoading = ref(false);
const fileInputRef = ref(null);

// Autosave
const draftId = ref(null);
const autosaveStatus = ref('idle'); // idle, saving, saved, error
const autosaveTimeout = ref(null);
const lastSavedAt = ref(null);
const autosaveInProgress = ref(false); // Flag to prevent multiple simultaneous autosaves
const lastAutosaveData = ref(null); // Track last saved data to avoid unnecessary saves

// Autosave function
async function autosave() {
  // Skip autosave if form is not valid enough (at least outlet and warehouse outlet must be selected)
  if (!form.outlet_id || !form.warehouse_outlet_id) {
    return;
  }

  // Skip if already submitting
  if (submitting.value) {
    return;
  }

  // PERFORMANCE: Prevent multiple simultaneous autosaves
  if (autosaveInProgress.value) {
    return;
  }

  // PERFORMANCE: Skip if no significant changes (compare with last saved data)
  const currentData = JSON.stringify({
    outlet_id: form.outlet_id,
    warehouse_outlet_id: form.warehouse_outlet_id,
    opname_date: form.opname_date,
    notes: form.notes,
    items: form.items.map(item => ({
      inventory_item_id: item.inventory_item_id,
      qty_physical_small: item.qty_physical_small,
      qty_physical_medium: item.qty_physical_medium,
      qty_physical_large: item.qty_physical_large,
      reason: item.reason,
    })),
  });
  
  if (lastAutosaveData.value === currentData) {
    return; // No changes, skip autosave
  }

  autosaveInProgress.value = true;
  autosaveStatus.value = 'saving';

  try {
    // Only autosave items that have been explicitly filled (not null/undefined)
    // This prevents overwriting user input with system qty
    const itemsToSave = form.items.map(item => {
      const itemData = {
        inventory_item_id: item.inventory_item_id,
        reason: item.reason || '',
      };
      
      // Only include qty_physical if it's been explicitly set (not null/undefined)
      // This way, backend won't overwrite with system qty
      if (item.qty_physical_small !== null && item.qty_physical_small !== undefined && item.qty_physical_small !== '') {
        itemData.qty_physical_small = parseFloat(item.qty_physical_small);
      }
      if (item.qty_physical_medium !== null && item.qty_physical_medium !== undefined && item.qty_physical_medium !== '') {
        itemData.qty_physical_medium = parseFloat(item.qty_physical_medium);
      }
      if (item.qty_physical_large !== null && item.qty_physical_large !== undefined && item.qty_physical_large !== '') {
        itemData.qty_physical_large = parseFloat(item.qty_physical_large);
      }
      
      return itemData;
    });

    // Prepare approvers array (only IDs)
    const approversIds = form.approvers
      .filter(approver => approver && approver.id)
      .map(approver => approver.id);

    const formData = {
      outlet_id: form.outlet_id,
      warehouse_outlet_id: form.warehouse_outlet_id,
      opname_date: form.opname_date,
      notes: form.notes,
      items: itemsToSave,
      approvers: approversIds,
      autosave: true,
    };

    let response;
    if (draftId.value) {
      // Update existing draft
      response = await axios.put(route('stock-opnames.update', draftId.value), formData);
    } else {
      // Create new draft
      response = await axios.post(route('stock-opnames.store'), formData);
    }

    if (response.data.success) {
      if (response.data.id && !draftId.value) {
        draftId.value = response.data.id;
      }
      autosaveStatus.value = 'saved';
      lastSavedAt.value = new Date();
      lastAutosaveData.value = currentData; // Update last saved data
      
      // Clear saved status after 3 seconds
      setTimeout(() => {
        if (autosaveStatus.value === 'saved') {
          autosaveStatus.value = 'idle';
        }
      }, 3000);
    }
  } catch (error) {
    console.error('Autosave error:', error);
    autosaveStatus.value = 'error';
    
    // Clear error status after 5 seconds
    setTimeout(() => {
      if (autosaveStatus.value === 'error') {
        autosaveStatus.value = 'idle';
      }
    }, 5000);
  } finally {
    autosaveInProgress.value = false;
  }
}

// PERFORMANCE OPTIMIZATION: Debounced autosave with longer delay
// Increased from 2s to 8s to reduce server load when many users are editing
function triggerAutosave() {
  // Don't trigger if autosave is already in progress
  if (autosaveInProgress.value) {
    return;
  }

  // Clear existing timeout
  if (autosaveTimeout.value) {
    clearTimeout(autosaveTimeout.value);
  }

  // PERFORMANCE: Increased debounce from 2s to 8s to reduce frequency
  // This significantly reduces server load when 50+ users are editing simultaneously
  autosaveTimeout.value = setTimeout(() => {
    // Double check before autosave
    if (!autosaveInProgress.value && !submitting.value) {
      autosave();
    }
  }, 8000); // 8 seconds debounce
}

const outletSelectable = computed(() => String(props.user_outlet_id) === '1');

// Group items by category
const groupedItems = computed(() => {
  const grouped = {};
  form.items.forEach(item => {
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
    }));
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
}

function calculateDifference(item) {
  // Difference akan dihitung di backend
  // Di frontend hanya untuk display
}

function hasDifference(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  const diffMedium = (item.qty_physical_medium ?? item.qty_system_medium) - item.qty_system_medium;
  const diffLarge = (item.qty_physical_large ?? item.qty_system_large) - item.qty_system_large;
  return diffSmall !== 0 || diffMedium !== 0 || diffLarge !== 0;
}

function formatDifference(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  const diffMedium = (item.qty_physical_medium ?? item.qty_system_medium) - item.qty_system_medium;
  const diffLarge = (item.qty_physical_large ?? item.qty_system_large) - item.qty_system_large;
  
  const parts = [];
  if (diffSmall !== 0) parts.push(`${diffSmall > 0 ? '+' : ''}${formatNumber(diffSmall)} ${item.small_unit_name}`);
  if (diffMedium !== 0) parts.push(`${diffMedium > 0 ? '+' : ''}${formatNumber(diffMedium)} ${item.medium_unit_name}`);
  if (diffLarge !== 0) parts.push(`${diffLarge > 0 ? '+' : ''}${formatNumber(diffLarge)} ${item.large_unit_name}`);
  
  return parts.join(', ') || '0';
}

function getDifferenceArray(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  const diffMedium = (item.qty_physical_medium ?? item.qty_system_medium) - item.qty_system_medium;
  const diffLarge = (item.qty_physical_large ?? item.qty_system_large) - item.qty_system_large;
  
  const diffs = [];
  if (diffSmall !== 0) {
    diffs.push(`${diffSmall > 0 ? '+' : ''}${formatNumber(diffSmall)} ${item.small_unit_name}`);
  }
  if (diffMedium !== 0) {
    diffs.push(`${diffMedium > 0 ? '+' : ''}${formatNumber(diffMedium)} ${item.medium_unit_name}`);
  }
  if (diffLarge !== 0) {
    diffs.push(`${diffLarge > 0 ? '+' : ''}${formatNumber(diffLarge)} ${item.large_unit_name}`);
  }
  
  return diffs.length > 0 ? diffs : ['0'];
}

function getDifferenceSign(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  if (diffSmall > 0) return '+';
  if (diffSmall < 0) return '-';
  return '0';
}

function getDifferenceClass(item) {
  const diffSmall = (item.qty_physical_small ?? item.qty_system_small) - item.qty_system_small;
  if (diffSmall > 0) return 'bg-green-100 text-green-800';
  if (diffSmall < 0) return 'bg-red-100 text-red-800';
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
    triggerAutosave();
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
}

function removeApprover(index) {
  form.approvers.splice(index, 1);
  triggerAutosave();
}

function reorderApprover(fromIndex, toIndex) {
  const approver = form.approvers.splice(fromIndex, 1)[0];
  form.approvers.splice(toIndex, 0, approver);
  triggerAutosave();
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

  // Ensure all items have correct data structure
  const itemsToSubmit = form.items.map(item => {
    const processedItem = {
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
    };
    
    // Log for debugging
    console.log('Item before processing:', {
      inventory_item_id: item.inventory_item_id,
      qty_physical_small_raw: item.qty_physical_small,
      qty_physical_medium_raw: item.qty_physical_medium,
      qty_physical_large_raw: item.qty_physical_large,
      qty_system_small: item.qty_system_small,
      qty_system_medium: item.qty_system_medium,
      qty_system_large: item.qty_system_large,
    });
    console.log('Item after processing:', processedItem);
    
    return processedItem;
  });

  // Log for debugging
  console.log('All items to submit:', itemsToSubmit);

  submitting.value = true;
  
  // If draft exists, use update route, otherwise use store
  const routeName = draftId.value ? 'stock-opnames.update' : 'stock-opnames.store';
  const routeParams = draftId.value ? { id: draftId.value } : {};
  
  // Prepare approvers array (only IDs)
  const approversIds = form.approvers
    .filter(approver => approver && approver.id)
    .map(approver => approver.id);

  // Create a new form with the processed items
  const submitForm = useForm({
    outlet_id: form.outlet_id,
    warehouse_outlet_id: form.warehouse_outlet_id,
    opname_date: form.opname_date,
    notes: form.notes,
    items: itemsToSubmit,
    approvers: approversIds,
  });
  
  submitForm.post(route(routeName, routeParams), {
    preserveScroll: true,
    onSuccess: () => {
      submitting.value = false;
      // Clear draft ID after successful submit
      draftId.value = null;
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
  });
}

function onImportFileChange(e) {
  const f = e.target.files?.[0];
  selectedImportFile.value = f || null;
  previewInfo.value = null;
  previewRows.value = [];
  importResults.value = null;
}

async function previewImport() {
  if (!selectedImportFile.value) return;
  importResults.value = null;
  const fd = new FormData();
  fd.append('file', selectedImportFile.value);
  try {
    const res = await axios.post(route('stock-opnames.preview-import'), fd, {
      headers: { 'Content-Type': 'multipart/form-data', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (res.data.success) {
      previewInfo.value = res.data.info || null;
      previewRows.value = res.data.rows || [];
    } else {
      importResults.value = { success: false, message: res.data.message || 'Preview gagal' };
    }
  } catch (err) {
    importResults.value = {
      success: false,
      message: err.response?.data?.message || 'Preview gagal',
      errors: err.response?.data?.info_errors || [],
    };
  }
}

async function processImport() {
  if (!selectedImportFile.value) return;
  importLoading.value = true;
  importResults.value = null;
  const fd = new FormData();
  fd.append('file', selectedImportFile.value);
  try {
    const res = await axios.post(route('stock-opnames.import'), fd, {
      headers: { 'Content-Type': 'multipart/form-data', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (res.data.success) {
      importResults.value = {
        success: true,
        message: res.data.message,
        id: res.data.id,
        errors: res.data.errors || [],
      };
    } else {
      importResults.value = { success: false, message: res.data.message, errors: res.data.errors || [] };
    }
  } catch (err) {
    importResults.value = {
      success: false,
      message: err.response?.data?.message || 'Import gagal',
      errors: err.response?.data?.errors || [],
    };
  } finally {
    importLoading.value = false;
  }
}

function closeImportModal() {
  showImportModal.value = false;
  selectedImportFile.value = null;
  previewInfo.value = null;
  previewRows.value = [];
  importResults.value = null;
  if (fileInputRef.value) fileInputRef.value.value = '';
}

// Load items when warehouse outlet changes
watch(() => form.warehouse_outlet_id, () => {
  if (form.warehouse_outlet_id) {
    loadItems();
  }
});

// Watch form changes for autosave (but not items to avoid overwriting user input)
watch([
  () => form.outlet_id,
  () => form.warehouse_outlet_id,
  () => form.opname_date,
  () => form.notes,
], () => {
  triggerAutosave();
});

// Watch items changes separately with debounce to avoid overwriting while user is typing
watch(() => form.items, () => {
  // Only autosave items if user has finished editing (debounced)
  triggerAutosave();
}, { deep: true });

// Auto-set outlet_id on mount if user is not superadmin
if (props.user_outlet_id && String(props.user_outlet_id) !== '1') {
  form.outlet_id = String(props.user_outlet_id);
}

// Auto load items if already selected
if (form.outlet_id && form.warehouse_outlet_id && form.items.length === 0) {
  loadItems();
}
</script>

