<template>
  <AppLayout title="Edit Payment">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-blue-500"></i> Edit Payment
        </h1>
        <div class="flex space-x-2">
          <Link
            :href="`/purchase-requisitions/${purchaseRequisition.id}`"
            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-eye mr-2"></i>
            View
          </Link>
          <Link
            :href="'/purchase-requisitions'"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-arrow-left mr-2"></i>
            Back to List
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Title -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
              <input
                v-model="form.title"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter payment title"
              />
            </div>

            <!-- Division -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Division *</label>
              <select
                v-model="form.division_id"
                required
                @change="loadBudgetInfo"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Division</option>
                <option v-for="division in divisions" :key="division.id" :value="division.id">
                  {{ division.nama_divisi }}
                </option>
              </select>
            </div>

            <!-- Category -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
              <select
                v-model="form.category_id"
                @change="onCategoryChange"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  [{{ category.division }}] {{ category.name }}
                </option>
              </select>
              
              <!-- Category Details -->
              <div v-if="selectedCategoryDetails" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="space-y-2">
                  <div>
                    <span class="text-sm font-medium text-blue-800">Division:</span>
                    <span class="text-sm text-blue-700 ml-2">{{ selectedCategoryDetails.division }}</span>
                  </div>
                  <div>
                    <span class="text-sm font-medium text-blue-800">Subcategory:</span>
                    <span class="text-sm text-blue-700 ml-2">{{ selectedCategoryDetails.subcategory }}</span>
                  </div>
                  <div>
                    <span class="text-sm font-medium text-blue-800">Budget Limit:</span>
                    <span class="text-sm text-blue-700 ml-2">{{ formatCurrency(selectedCategoryDetails.budget_limit) }}</span>
                  </div>
                  <div v-if="selectedCategoryDetails.description">
                    <span class="text-sm font-medium text-blue-800">Description:</span>
                    <p class="text-sm text-blue-700 mt-1">{{ selectedCategoryDetails.description }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Outlet -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <select
                v-model="form.outlet_id"
                @change="onOutletChange"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET'" class="mt-1 text-xs text-gray-500">
                Outlet selection is required for per-outlet budget categories
              </p>
            </div>

            <!-- Ticket -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Related Ticket</label>
              <select
                v-model="form.ticket_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Ticket</option>
                <option v-for="ticket in tickets" :key="ticket.id" :value="ticket.id">
                  {{ ticket.ticket_number }} - {{ ticket.title }} ({{ ticket.outlet?.nama_outlet }})
                </option>
              </select>
            </div>

            <!-- Amount -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Amount *</label>
              <input
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="0.00"
              />
            </div>

            <!-- Currency -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
              <select
                v-model="form.currency"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="IDR">IDR</option>
                <option value="USD">USD</option>
              </select>
            </div>

            <!-- Priority -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
              <select
                v-model="form.priority"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="LOW">Low</option>
                <option value="MEDIUM">Medium</option>
                <option value="HIGH">High</option>
                <option value="URGENT">Urgent</option>
              </select>
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea
                v-model="form.description"
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter description..."
              ></textarea>
            </div>

            <!-- Attachments Section -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Attachments</label>
              <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
                <div class="text-center">
                  <input
                    ref="fileInput"
                    type="file"
                    multiple
                    @change="handleFileUpload"
                    class="hidden"
                    accept="*/*"
                  />
                  <button
                    type="button"
                    @click="$refs.fileInput.click()"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                  >
                    <i class="fa fa-upload mr-2"></i>
                    Upload Files
                  </button>
                  <p class="mt-2 text-sm text-gray-500">
                    Upload any file type (Max 10MB per file)
                  </p>
                </div>
              </div>

              <!-- Uploaded Files List -->
              <div v-if="attachments.length > 0" class="mt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Uploaded Files:</h4>
                <div class="space-y-2">
                <div
                  v-for="(attachment, index) in attachments"
                  :key="attachment.id || attachment.temp_id"
                  class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-md"
                >
                  <div class="flex items-center space-x-3">
                    <!-- Image Thumbnail -->
                    <div v-if="isImageFile(attachment.file_name) && attachment.id" class="relative">
                      <img
                        :src="`/purchase-requisitions/attachments/${attachment.id}/view`"
                        :alt="attachment.file_name"
                        class="w-12 h-12 object-cover rounded-lg border border-gray-300 cursor-pointer hover:opacity-80 transition-opacity"
                        @click="openLightbox(attachment)"
                        @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='block'"
                      />
                      <i :class="getFileIcon(attachment.file_name)" class="text-lg absolute inset-0 flex items-center justify-center bg-gray-100 rounded-lg" style="display: none;"></i>
                    </div>
                    <!-- File Icon for non-images or temp files -->
                    <i v-else :class="getFileIcon(attachment.file_name)" class="text-lg"></i>
                    
                    <div>
                      <p class="text-sm font-medium text-gray-900">{{ attachment.file_name }}</p>
                      <p class="text-xs text-gray-500">{{ formatFileSize(attachment.file_size) }}</p>
                      <p v-if="attachment.uploader" class="text-xs text-gray-400">Uploaded by {{ attachment.uploader.nama_lengkap || attachment.uploader.name }}</p>
                    </div>
                  </div>
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="isImageFile(attachment.file_name) && attachment.id"
                      type="button"
                      @click="openLightbox(attachment)"
                      class="text-green-600 hover:text-green-800"
                      title="View Image"
                    >
                      <i class="fa fa-eye"></i>
                    </button>
                    <button
                      type="button"
                      @click="downloadFile(attachment)"
                      class="text-blue-600 hover:text-blue-800"
                      title="Download"
                    >
                      <i class="fa fa-download"></i>
                    </button>
                    <button
                      v-if="canDeleteAttachment(attachment)"
                      type="button"
                      @click="deleteAttachment(attachment, index)"
                      class="text-red-600 hover:text-red-800"
                      title="Delete"
                    >
                      <i class="fa fa-trash"></i>
                    </button>
                  </div>
                </div>
                </div>
              </div>
            </div>

            <!-- Budget Info -->
            <div v-if="budgetInfo" class="md:col-span-2">
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="text-lg font-medium text-blue-800 mb-2">Category Budget Information</h3>
                
                <!-- Category Budget -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                  <div>
                    <p class="text-sm text-blue-600">Category Budget</p>
                    <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.category_budget) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-blue-600">Used This Month</p>
                    <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.category_used_amount) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-blue-600">Current Input</p>
                    <p class="text-lg font-semibold text-green-800">{{ formatCurrency(budgetInfo.current_amount) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-blue-600">Total After Input</p>
                    <p class="text-lg font-semibold" :class="budgetInfo.exceeds_budget ? 'text-red-800' : 'text-blue-800'">
                      {{ formatCurrency(budgetInfo.total_with_current) }}
                    </p>
                  </div>
                </div>
                
                <!-- Remaining Budget -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p class="text-sm text-blue-600">Remaining Before Input</p>
                    <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.category_remaining_amount) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-blue-600">Remaining After Input</p>
                    <p class="text-lg font-semibold" :class="budgetInfo.remaining_after_current < 0 ? 'text-red-800' : 'text-blue-800'">
                      {{ formatCurrency(budgetInfo.remaining_after_current) }}
                    </p>
                  </div>
                </div>
                
                <!-- Warning Messages -->
                <div v-if="budgetInfo.exceeds_budget" class="mt-4 p-3 bg-red-100 border border-red-300 rounded text-red-800 text-sm">
                  <i class="fa fa-exclamation-triangle mr-2"></i>
                  <strong>Budget Exceeded!</strong> Total amount ({{ formatCurrency(budgetInfo.total_with_current) }}) exceeds category budget limit ({{ formatCurrency(budgetInfo.category_budget) }}) for this month. You cannot save this payment.
                </div>
                <div v-else-if="budgetInfo.remaining_after_current < (budgetInfo.category_budget * 0.1)" class="mt-4 p-3 bg-yellow-100 border border-yellow-300 rounded text-yellow-800 text-sm">
                  <i class="fa fa-exclamation-circle mr-2"></i>
                  <strong>Budget Warning!</strong> Only {{ formatCurrency(budgetInfo.remaining_after_current) }} remaining after this input.
                </div>
              </div>
            </div>
          </div>

          <!-- Budget Info Message for PER_OUTLET without outlet selection -->
          <div v-if="!budgetInfo && selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET' && !form.outlet_id" class="mb-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <h3 class="text-lg font-medium text-yellow-800 mb-2">
                <i class="fa fa-info-circle mr-2"></i>
                Outlet Selection Required
              </h3>
              <p class="text-yellow-700">
                This category uses per-outlet budget allocation. Please select an outlet above to view the budget information.
              </p>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="mt-6 flex justify-end space-x-3">
            <Link
              :href="`/purchase-requisitions/${purchaseRequisition.id}`"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="loading || (budgetInfo && budgetInfo.exceeds_budget) || (selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET' && !form.outlet_id)"
              class="px-4 py-2 text-white rounded-md disabled:opacity-50"
              :class="(budgetInfo && budgetInfo.exceeds_budget) ? 'bg-red-600 hover:bg-red-700' : (selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET' && !form.outlet_id) ? 'bg-gray-400' : 'bg-blue-600 hover:bg-blue-700'"
            >
              <span v-if="loading">Updating...</span>
              <span v-else-if="budgetInfo && budgetInfo.exceeds_budget">Budget Exceeded - Cannot Save</span>
              <span v-else-if="selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET' && !form.outlet_id">Select Outlet First</span>
              <span v-else>Update Payment</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Lightbox Modal -->
    <div v-if="showLightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeLightbox">
      <div class="relative max-w-4xl max-h-full p-4" @click.stop>
        <button
          @click="closeLightbox"
          class="absolute top-2 right-2 z-10 p-2 text-white bg-black bg-opacity-50 rounded-full hover:bg-opacity-75 transition-colors"
        >
          <i class="fa fa-times text-xl"></i>
        </button>
        <img
          v-if="lightboxImage"
          :src="`/purchase-requisitions/attachments/${lightboxImage.id}/view`"
          :alt="lightboxImage.file_name"
          class="max-w-full max-h-full object-contain rounded-lg"
        />
        <div class="absolute bottom-4 left-4 right-4 text-center">
          <p class="text-white bg-black bg-opacity-50 px-3 py-1 rounded-lg text-sm">
            {{ lightboxImage?.file_name }}
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  purchaseRequisition: Object,
  categories: Array,
  outlets: Array,
  tickets: Array,
  divisions: Array,
})

const loading = ref(false)
const budgetInfo = ref(null)
const selectedCategoryDetails = ref(null)
const attachments = ref([])
const uploading = ref(false)
const showLightbox = ref(false)
const lightboxImage = ref(null)

const form = reactive({
  title: props.purchaseRequisition.title || '',
  description: props.purchaseRequisition.description || '',
  division_id: props.purchaseRequisition.division_id || '',
  category_id: props.purchaseRequisition.category_id || '',
  outlet_id: props.purchaseRequisition.outlet_id || '',
  ticket_id: props.purchaseRequisition.ticket_id || '',
  amount: props.purchaseRequisition.amount || '',
  currency: props.purchaseRequisition.currency || 'IDR',
  priority: props.purchaseRequisition.priority || 'MEDIUM',
})

const totalAmount = computed(() => {
  return form.amount || 0
})

// Watch totalAmount changes to update budget info
watch(totalAmount, (newTotal) => {
  // Reload budget info when total amount changes
  if (form.category_id) {
    loadBudgetInfo()
  }
})

const onCategoryChange = () => {
  // Load category details
  loadCategoryDetails()
  // Load budget info
  loadBudgetInfo()
}

const onOutletChange = () => {
  // Load budget info when outlet changes
  if (form.category_id) {
    loadBudgetInfo()
  }
}

const loadCategoryDetails = () => {
  if (!form.category_id) {
    selectedCategoryDetails.value = null
    return
  }
  
  // Find category from props
  const category = props.categories.find(cat => cat.id == form.category_id)
  if (category) {
    selectedCategoryDetails.value = {
      division: category.division,
      subcategory: category.subcategory,
      budget_limit: category.budget_limit,
      budget_type: category.budget_type,
      description: category.description
    }
  } else {
    selectedCategoryDetails.value = null
  }
}

const loadBudgetInfo = async () => {
  if (!form.category_id) {
    budgetInfo.value = null
    return
  }
  
  // For PER_OUTLET budget type, require outlet selection
  if (selectedCategoryDetails.value && selectedCategoryDetails.value.budget_type === 'PER_OUTLET' && !form.outlet_id) {
    budgetInfo.value = null
    return
  }
  
  try {
    const response = await axios.get('/purchase-requisitions/budget-info', {
      params: {
        category_id: form.category_id,
        outlet_id: form.outlet_id,
        current_amount: totalAmount.value,
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1
      },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    if (response.data.success) {
      budgetInfo.value = response.data
    } else {
      budgetInfo.value = null
    }
  } catch (error) {
    budgetInfo.value = null
  }
}

const submitForm = () => {
  loading.value = true
  
  router.put(`/purchase-requisitions/${props.purchaseRequisition.id}`, form, {
    onFinish: () => {
      loading.value = false
    },
    onError: (errors) => {
      console.error('Form errors:', errors)
    }
  })
}

const formatCurrency = (amount) => {
  if (!amount) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount)
}

// File upload functions
const handleFileUpload = async (event) => {
  const files = Array.from(event.target.files)
  
  for (const file of files) {
    if (file.size > 10 * 1024 * 1024) { // 10MB limit
      alert(`File ${file.name} is too large. Maximum size is 10MB.`)
      continue
    }
    
    // Upload file immediately for edit form
    uploading.value = true
    const formData = new FormData()
    formData.append('file', file)
    
    try {
      const response = await axios.post(`/purchase-requisitions/${props.purchaseRequisition.id}/attachments`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        }
      })
      
      if (response.data.success) {
        attachments.value.push(response.data.attachment)
      }
    } catch (error) {
      console.error('Failed to upload attachment:', error)
      alert(`Failed to upload ${file.name}: ${error.response?.data?.message || error.message}`)
    }
    
    uploading.value = false
  }
  
  // Clear the input
  event.target.value = ''
}

const deleteAttachment = async (attachment, index) => {
  if (!confirm('Are you sure you want to delete this file?')) {
    return
  }
  
  try {
    const response = await axios.delete(`/purchase-requisitions/attachments/${attachment.id}`)
    
    if (response.data.success) {
      attachments.value.splice(index, 1)
    }
  } catch (error) {
    console.error('Failed to delete attachment:', error)
    alert(`Failed to delete file: ${error.response?.data?.message || error.message}`)
  }
}

const canDeleteAttachment = (attachment) => {
  // Only allow deletion if user is the uploader or admin
  return attachment.uploaded_by === window.authUser?.id || window.authUser?.roles?.includes('admin')
}

const getFileIcon = (fileName) => {
  const extension = fileName.split('.').pop().toLowerCase()
  
  const iconMap = {
    'pdf': 'fa-file-pdf text-red-500',
    'doc': 'fa-file-word text-blue-500',
    'docx': 'fa-file-word text-blue-500',
    'xls': 'fa-file-excel text-green-500',
    'xlsx': 'fa-file-excel text-green-500',
    'ppt': 'fa-file-powerpoint text-orange-500',
    'pptx': 'fa-file-powerpoint text-orange-500',
    'jpg': 'fa-file-image text-purple-500',
    'jpeg': 'fa-file-image text-purple-500',
    'png': 'fa-file-image text-purple-500',
    'gif': 'fa-file-image text-purple-500',
    'webp': 'fa-file-image text-purple-500',
    'bmp': 'fa-file-image text-purple-500',
    'txt': 'fa-file-alt text-gray-500',
    'zip': 'fa-file-archive text-yellow-500',
    'rar': 'fa-file-archive text-yellow-500',
  }
  
  return iconMap[extension] || 'fa-file text-gray-500'
}

const isImageFile = (fileName) => {
  const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']
  const extension = fileName.split('.').pop().toLowerCase()
  return imageExtensions.includes(extension)
}

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const downloadFile = (attachment) => {
  // Create download link
  const link = document.createElement('a')
  link.href = `/purchase-requisitions/attachments/${attachment.id}/download`
  link.download = attachment.file_name
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const openLightbox = (attachment) => {
  lightboxImage.value = attachment
  showLightbox.value = true
}

const closeLightbox = () => {
  showLightbox.value = false
  lightboxImage.value = null
}

// Load budget info and category details on mount if category is selected
onMounted(() => {
  if (form.category_id) {
    loadCategoryDetails()
    loadBudgetInfo()
  }
  
  // Load existing attachments
  if (props.purchaseRequisition.attachments) {
    attachments.value = props.purchaseRequisition.attachments
  }
})
</script>