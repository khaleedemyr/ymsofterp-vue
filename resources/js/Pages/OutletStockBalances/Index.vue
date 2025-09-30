<template>
  <AppLayout title="Saldo Awal Stok Outlet">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Saldo Awal Stok Outlet
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- Import Section -->
          <div class="mb-6 flex justify-between items-center">
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
            <button @click="reloadData" :disabled="loadingReload" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md font-semibold hover:bg-blue-700">
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
              <label class="block text-sm font-medium text-gray-700">Product</label>
              <select
                v-model="filters.product_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @change="filter"
              >
                <option value="">All Products</option>
                <option v-for="product in products" :key="product.id" :value="product.id">
                  {{ product.name }}
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
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse Outlet</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QTY SMALL</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QTY MEDIUM</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QTY LARGE</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TANGGAL UPDATE</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="balance in stockBalances.data" :key="balance.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ balance.product_name }}</div>
                    <div class="text-sm text-gray-500">{{ balance.product_code }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.nama_outlet }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.warehouse_outlet_id }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ Number(balance.qty_small).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ balance.unit_name_small }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ Number(balance.qty_medium).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ balance.unit_name_medium }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ Number(balance.qty_large).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ balance.unit_name_large }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatDate(balance.updated_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.creator_name || '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                      @click="editBalance(balance)"
                      class="text-indigo-600 hover:text-indigo-900 mr-3"
                    >
                      Edit
                    </button>
                    <button
                      @click="deleteBalance(balance)"
                      class="text-red-600 hover:text-red-900"
                    >
                      Delete
                    </button>
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
        <h2 class="text-lg font-medium text-gray-900 mb-4">Import Stock Balance</h2>
        
        <!-- File Upload -->
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
                        <li v-for="(error, index) in importResults.errors" :key="index" 
                            class="flex items-start text-sm">
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
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
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
            <span v-else>
              Import
            </span>
          </button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import Pagination from '@/Components/Pagination.vue'
import { formatDate } from '@/utils.js'

export default {
  components: {
    AppLayout,
    Modal,
    Pagination,
  },
  props: {
    stockBalances: Object,
    outlets: Array,
    products: Array,
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

    const form = useForm({
      outlet_id: props.filters.outlet_id || '',
      product_id: props.filters.product_id || '',
      search: props.filters.search || '',
    })

    const filter = () => {
      form.get(route('outlet-stock-balances.index'), {
        preserveState: true,
        preserveScroll: true,
      })
    }

    const downloadTemplate = () => {
      window.location.href = route('outlet-stock-balances.download-template')
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
        const response = await axios.post(route('outlet-stock-balances.preview-import'), formData)
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
        console.error('Preview failed:', error)
      }
    }

    const processImport = async () => {
      if (!selectedFile.value) return
      importLoading.value = true
      const formData = new FormData()
      formData.append('file', selectedFile.value)
      try {
        const response = await axios.post(route('outlet-stock-balances.import'), formData)
        importResults.value = response.data
        if (response.data.success) {
          setTimeout(() => {
            window.location.reload()
          }, 2000)
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
      if (fileInput.value) {
        fileInput.value.value = ''
      }
    }

    return {
      showImportModal,
      selectedFile,
      previewData,
      previewHeaders,
      importResults,
      fileInput,
      form,
      filter,
      downloadTemplate,
      handleFileUpload,
      previewImport,
      processImport,
      closeImportModal,
      formatDate,
      importLoading,
    }
  },
}
</script> 