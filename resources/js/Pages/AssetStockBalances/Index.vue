<template>
  <AppLayout title="Saldo Awal Stok Asset">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Saldo Awal Stok Asset
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- Import Section -->
          <div class="mb-6 flex justify-between items-center flex-wrap gap-2">
            <div class="flex space-x-4">
              <button
                @click="downloadTemplate"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
              >
                <i class="fas fa-download mr-2"></i>
                Download Template
              </button>
              <button
                @click="showImportModal = true"
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
              >
                <i class="fas fa-file-import mr-2"></i>
                Import Data
              </button>
            </div>
            <button
              @click="reloadData"
              :disabled="loadingReload"
              class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700"
            >
              <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
              <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
              Load Data
            </button>
          </div>

          <!-- Filters -->
          <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Outlet</label>
              <select
                v-model="filters.outlet_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @change="filter"
              >
                <option value="">All Outlets</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Warehouse</label>
              <select
                v-model="filters.warehouse_outlet_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @change="filter"
              >
                <option value="">All Warehouses</option>
                <option v-for="w in filteredWarehouses" :key="w.id" :value="w.id">
                  {{ w.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Search</label>
              <input
                type="text"
                v-model="filters.search"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Search by product name or code..."
                @input="filter"
              />
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Small</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Medium</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Large</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="stockBalances.data && stockBalances.data.length === 0">
                  <td colspan="9" class="text-center py-10 text-gray-400">Tidak ada data.</td>
                </tr>
                <tr v-for="balance in stockBalances.data" :key="balance.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ balance.product_name }}</div>
                    <div class="text-sm text-gray-500">{{ balance.product_code }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.nama_outlet }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.warehouse_outlet_name || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                    {{ fmtQty(balance.qty_small) }} {{ balance.unit_name_small }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                    {{ fmtQty(balance.qty_medium) }} {{ balance.unit_name_medium }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                    {{ fmtQty(balance.qty_large) }} {{ balance.unit_name_large }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                    {{ fmtValue(balance.value) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatDate(balance.updated_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button @click="editBalance(balance)" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                    <button @click="deleteBalance(balance)" class="text-red-600 hover:text-red-900">Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="mt-4">
            <Pagination :links="stockBalances.links" />
          </div>
        </div>
      </div>
    </div>

    <!-- Import Modal -->
    <Modal :show="showImportModal" @close="closeImportModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Import Stock Balance Asset</h2>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Select File</label>
          <input
            type="file"
            ref="fileInput"
            @change="handleFileUpload"
            accept=".xlsx,.xls"
            class="mt-1 block w-full"
          />
        </div>

        <!-- Preview Section -->
        <div v-if="previewData.length > 0" class="mb-4">
          <h3 class="text-md font-medium text-gray-900 mb-2">Preview Data</h3>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th v-for="header in previewHeaders" :key="header" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ header }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(row, index) in previewData" :key="index">
                  <td v-for="header in previewHeaders" :key="header" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ row[header] }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Import Results -->
        <div v-if="importResults" class="mb-4">
          <div class="rounded-md p-4" :class="importResults.success ? 'bg-green-50' : 'bg-red-50'">
            <div class="flex">
              <div class="flex-shrink-0">
                <i :class="importResults.success ? 'fas fa-check-circle text-green-400' : 'fas fa-exclamation-circle text-red-400'"></i>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium" :class="importResults.success ? 'text-green-800' : 'text-red-800'">
                  {{ importResults.message }}
                </h3>
                <div v-if="importResults.errors && importResults.errors.length > 0" class="mt-3">
                  <div class="bg-red-50 border border-red-200 rounded-md p-3">
                    <div class="flex items-center mb-2">
                      <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                      <span class="text-sm font-medium text-red-800">
                        {{ importResults.errors.length }} error ditemukan:
                      </span>
                    </div>
                    <div class="max-h-40 overflow-y-auto">
                      <ul class="space-y-2">
                        <li v-for="(error, index) in importResults.errors" :key="index" class="flex items-start text-sm">
                          <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2 flex-shrink-0">
                            Row {{ error.row }}
                          </span>
                          <span class="text-red-700">{{ error.error }}</span>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex justify-end space-x-3">
          <button
            @click="closeImportModal"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            v-if="selectedFile && !importResults"
            @click="previewImport"
            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
          >
            Preview
          </button>
          <button
            v-if="selectedFile && !importResults"
            @click="processImport"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
            :disabled="importLoading"
          >
            <span v-if="importLoading">
              <i class="fas fa-spinner fa-spin mr-2"></i> Importing...
            </span>
            <span v-else>Import</span>
          </button>
        </div>
      </div>
    </Modal>

    <!-- Edit Modal -->
    <Modal :show="showEditModal" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Edit Saldo Awal</h2>
        <div v-if="editItem" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Item</label>
            <div class="mt-1 text-sm text-gray-900">{{ editItem.product_name }} ({{ editItem.product_code }})</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Outlet</label>
            <div class="mt-1 text-sm text-gray-900">{{ editItem.nama_outlet }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Warehouse</label>
            <div class="mt-1 text-sm text-gray-900">{{ editItem.warehouse_outlet_name || '-' }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Quantity (Small Unit)</label>
            <input
              type="number"
              v-model="editForm.qty_small"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              step="0.01"
              min="0"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Cost per Small Unit</label>
            <input
              type="number"
              v-model="editForm.cost"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              step="0.01"
              min="0"
            />
          </div>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
          <button
            @click="closeEditModal"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="saveEdit"
            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
            :disabled="editLoading"
          >
            <span v-if="editLoading"><i class="fas fa-spinner fa-spin mr-2"></i> Saving...</span>
            <span v-else>Save</span>
          </button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>

<script>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import Pagination from '@/Components/Pagination.vue'
import { formatDate } from '@/utils.js'
import Swal from 'sweetalert2'
import axios from 'axios'

export default {
  components: { AppLayout, Modal, Pagination },
  props: {
    stockBalances: Object,
    outlets: Array,
    warehouseOutlets: Array,
    filters: Object,
  },
  setup(props) {
    const showImportModal = ref(false)
    const selectedFile = ref(null)
    const previewData = ref([])
    const previewHeaders = ref([])
    const importResults = ref(null)
    const fileInput = ref(null)
    const importLoading = ref(false)
    const loadingReload = ref(false)

    const showEditModal = ref(false)
    const editItem = ref(null)
    const editForm = ref({ qty_small: 0, cost: 0 })
    const editLoading = ref(false)

    const filters = ref({
      outlet_id: props.filters.outlet_id || '',
      warehouse_outlet_id: props.filters.warehouse_outlet_id || '',
      search: props.filters.search || '',
    })

    const filteredWarehouses = computed(() => {
      const all = Array.isArray(props.warehouseOutlets) ? props.warehouseOutlets : []
      if (!filters.value.outlet_id) return all
      return all.filter(w => String(w.outlet_id) === String(filters.value.outlet_id))
    })

    let filterTimeout = null
    const filter = () => {
      clearTimeout(filterTimeout)
      filterTimeout = setTimeout(() => {
        router.get('/asset-stock-balances', {
          outlet_id: filters.value.outlet_id || undefined,
          warehouse_outlet_id: filters.value.warehouse_outlet_id || undefined,
          search: filters.value.search || undefined,
        }, {
          preserveState: true,
          preserveScroll: true,
        })
      }, 300)
    }

    const reloadData = () => {
      loadingReload.value = true
      router.get('/asset-stock-balances', {
        outlet_id: filters.value.outlet_id || undefined,
        warehouse_outlet_id: filters.value.warehouse_outlet_id || undefined,
        search: filters.value.search || undefined,
      }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { loadingReload.value = false },
      })
    }

    const downloadTemplate = () => {
      window.location.href = '/asset-stock-balances/download-template'
    }

    const handleFileUpload = (event) => {
      selectedFile.value = event.target.files[0]
      previewData.value = []
      previewHeaders.value = []
      importResults.value = null
    }

    const previewImport = async () => {
      if (!selectedFile.value) return
      const formData = new FormData()
      formData.append('file', selectedFile.value)
      try {
        const response = await axios.post('/asset-stock-balances/preview-import', formData)
        if (response.data.success) {
          previewData.value = response.data.preview
          if (previewData.value.length > 0) {
            previewHeaders.value = Object.keys(previewData.value[0])
          }
        } else {
          importResults.value = {
            success: false,
            message: response.data.message || 'Preview failed',
            errors: response.data.errors || []
          }
        }
      } catch (error) {
        importResults.value = {
          success: false,
          message: error.response?.data?.message || 'Preview failed',
          errors: error.response?.data?.errors || []
        }
      }
    }

    const processImport = async () => {
      if (!selectedFile.value) return
      importLoading.value = true
      const formData = new FormData()
      formData.append('file', selectedFile.value)
      try {
        const response = await axios.post('/asset-stock-balances/import', formData)
        importResults.value = response.data
        if (response.data.success) {
          setTimeout(() => { window.location.reload() }, 2000)
        }
      } catch (error) {
        importResults.value = {
          success: false,
          message: error.response?.data?.message || 'Import failed',
          errors: error.response?.data?.errors || []
        }
      } finally {
        importLoading.value = false
      }
    }

    const closeImportModal = () => {
      showImportModal.value = false
      selectedFile.value = null
      previewData.value = []
      previewHeaders.value = []
      importResults.value = null
      if (fileInput.value) fileInput.value.value = ''
    }

    const editBalance = (balance) => {
      editItem.value = balance
      editForm.value = {
        qty_small: balance.qty_small || 0,
        cost: balance.last_cost_small || 0,
      }
      showEditModal.value = true
    }

    const closeEditModal = () => {
      showEditModal.value = false
      editItem.value = null
      editForm.value = { qty_small: 0, cost: 0 }
    }

    const saveEdit = () => {
      if (!editItem.value) return
      editLoading.value = true
      router.put(`/asset-stock-balances/${editItem.value.id}`, {
        qty_small: editForm.value.qty_small,
        cost: editForm.value.cost,
      }, {
        onSuccess: () => {
          closeEditModal()
          Swal.fire('Berhasil', 'Data berhasil diupdate', 'success')
        },
        onError: (errors) => {
          Swal.fire('Error', Object.values(errors).flat().join(', '), 'error')
        },
        onFinish: () => { editLoading.value = false },
      })
    }

    const deleteBalance = (balance) => {
      Swal.fire({
        title: 'Hapus Data?',
        text: `Hapus saldo awal ${balance.product_name} di ${balance.nama_outlet}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
      }).then((result) => {
        if (result.isConfirmed) {
          router.delete(`/asset-stock-balances/${balance.id}`, {
            onSuccess: () => {
              Swal.fire('Terhapus', 'Data berhasil dihapus', 'success')
            },
          })
        }
      })
    }

    const fmtQty = (val) => {
      if (!val || Number(val) === 0) return '0.00'
      return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    }

    const fmtValue = (val) => {
      if (!val || Number(val) === 0) return '-'
      return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
    }

    return {
      showImportModal, selectedFile, previewData, previewHeaders,
      importResults, fileInput, importLoading, loadingReload,
      showEditModal, editItem, editForm, editLoading,
      filters, filteredWarehouses,
      filter, reloadData, downloadTemplate,
      handleFileUpload, previewImport, processImport,
      closeImportModal, editBalance, closeEditModal,
      saveEdit, deleteBalance, fmtQty, fmtValue, formatDate,
    }
  },
}
</script>
