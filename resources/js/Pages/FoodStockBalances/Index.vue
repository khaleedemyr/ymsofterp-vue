<template>
  <AppLayout title="Saldo Awal Stok">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Saldo Awal Stok
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
              <label class="block text-sm font-medium text-gray-700">Warehouse</label>
              <select
                v-model="filters.warehouse_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @change="filter"
              >
                <option value="">All Warehouses</option>
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                  {{ warehouse.name }}
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
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch Number</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="balance in stockBalances.data" :key="balance.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ balance.product.name }}</div>
                    <div class="text-sm text-gray-500">{{ balance.product.code }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.warehouse.name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.quantity }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.unit.name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.batch_number }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.expiry_date ? formatDate(balance.expiry_date) : '-' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ balance.creator.name }}
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
                <div v-if="importResults.errors && importResults.errors.length > 0" class="mt-2 text-sm text-red-700">
                  <ul class="list-disc pl-5 space-y-1">
                    <li v-for="(error, index) in importResults.errors" :key="index">
                      Row {{ error.row }}: {{ error.error }}
                    </li>
                  </ul>
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

    <!-- Edit Modal -->
    <Modal :show="showEditModal" @close="closeEditModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Edit Stock Balance</h2>
        
        <form @submit.prevent="updateBalance">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Product -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Product</label>
              <select
                v-model="editForm.product_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
              >
                <option value="">Select Product</option>
                <option v-for="product in products" :key="product.id" :value="product.id">
                  {{ product.name }} ({{ product.code }})
                </option>
              </select>
              <div v-if="editForm.errors.product_id" class="mt-1 text-sm text-red-600">
                {{ editForm.errors.product_id }}
              </div>
            </div>

            <!-- Warehouse -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Warehouse</label>
              <select
                v-model="editForm.warehouse_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
              >
                <option value="">Select Warehouse</option>
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                  {{ warehouse.name }}
                </option>
              </select>
              <div v-if="editForm.errors.warehouse_id" class="mt-1 text-sm text-red-600">
                {{ editForm.errors.warehouse_id }}
              </div>
            </div>

            <!-- Quantity -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Quantity</label>
              <input
                type="number"
                v-model="editForm.quantity"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                min="0"
                step="0.01"
                required
              />
              <div v-if="editForm.errors.quantity" class="mt-1 text-sm text-red-600">
                {{ editForm.errors.quantity }}
              </div>
            </div>

            <!-- Unit -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Unit</label>
              <select
                v-model="editForm.unit_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
              >
                <option value="">Select Unit</option>
                <option v-for="unit in units" :key="unit.id" :value="unit.id">
                  {{ unit.name }}
                </option>
              </select>
              <div v-if="editForm.errors.unit_id" class="mt-1 text-sm text-red-600">
                {{ editForm.errors.unit_id }}
              </div>
            </div>

            <!-- Batch Number -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Batch Number</label>
              <input
                type="text"
                v-model="editForm.batch_number"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
              <div v-if="editForm.errors.batch_number" class="mt-1 text-sm text-red-600">
                {{ editForm.errors.batch_number }}
              </div>
            </div>

            <!-- Expiry Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700">Expiry Date</label>
              <input
                type="date"
                v-model="editForm.expiry_date"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
              <div v-if="editForm.errors.expiry_date" class="mt-1 text-sm text-red-600">
                {{ editForm.errors.expiry_date }}
              </div>
            </div>

            <!-- Notes -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Notes</label>
              <textarea
                v-model="editForm.notes"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              ></textarea>
              <div v-if="editForm.errors.notes" class="mt-1 text-sm text-red-600">
                {{ editForm.errors.notes }}
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="mt-6 flex justify-end space-x-3">
            <button
              type="button"
              @click="closeEditModal"
              class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
              :disabled="editForm.processing"
            >
              {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </form>
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
    warehouses: Array,
    products: Array,
    units: Array,
    filters: Object,
  },
  setup(props) {
    const showImportModal = ref(false)
    const selectedFile = ref(null)
    const previewData = ref([])
    const previewHeaders = ref([])
    const importResults = ref(null)
    const fileInput = ref(null)
    const showEditModal = ref(false)
    const editForm = useForm({
      id: null,
      product_id: '',
      warehouse_id: '',
      quantity: '',
      unit_id: '',
      batch_number: '',
      expiry_date: '',
      notes: '',
    })
    const importLoading = ref(false)
    const loadingReload = ref(false)

    const form = useForm({
      warehouse_id: props.filters.warehouse_id || '',
      product_id: props.filters.product_id || '',
      search: props.filters.search || '',
    })

    const filter = () => {
      form.get(route('food-stock-balances.index'), {
        preserveState: true,
        preserveScroll: true,
      })
    }

    const downloadTemplate = () => {
      window.location.href = route('food-stock-balances.download-template')
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
        const response = await axios.post(route('food-stock-balances.preview-import'), formData, {
          // Jangan set Content-Type, biarkan browser yang set
        })
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
        // Tampilkan pesan error validasi dari backend jika ada
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
        const response = await axios.post(route('food-stock-balances.import'), formData, {
          // Jangan set Content-Type, biarkan browser yang set
        })
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

    const editBalance = (balance) => {
      editForm.id = balance.id
      editForm.product_id = balance.product_id
      editForm.warehouse_id = balance.warehouse_id
      editForm.quantity = balance.quantity
      editForm.unit_id = balance.unit_id
      editForm.batch_number = balance.batch_number || ''
      editForm.expiry_date = balance.expiry_date ? balance.expiry_date.split('T')[0] : ''
      editForm.notes = balance.notes || ''
      showEditModal.value = true
    }

    const updateBalance = async () => {
      try {
        const response = await axios.put(route('food-stock-balances.update', editForm.id), editForm.data())
        if (response.data.success) {
          closeEditModal()
          window.location.reload()
        }
      } catch (error) {
        if (error.response?.data?.errors) {
          editForm.errors = error.response.data.errors
        }
      }
    }

    const closeEditModal = () => {
      showEditModal.value = false
      editForm.reset()
      editForm.clearErrors()
    }

    const deleteBalance = async (balance) => {
      if (!confirm('Are you sure you want to delete this stock balance?')) return

      try {
        const response = await axios.delete(route('food-stock-balances.destroy', balance.id))
        if (response.data.success) {
          window.location.reload()
        }
      } catch (error) {
        console.error('Delete failed:', error)
      }
    }

    function reloadData() {
      loadingReload.value = true
      filter()
      setTimeout(() => { loadingReload.value = false }, 1000)
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
      showEditModal,
      editForm,
      editBalance,
      updateBalance,
      closeEditModal,
      deleteBalance,
      formatDate,
      importLoading,
      loadingReload,
      reloadData,
    }
  },
}
</script> 