<template>
  <AppLayout title="Create Employee Resignation">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-user-minus text-red-500"></i> Create Employee Resignation
        </h1>
        <Link
          :href="'/employee-resignations'"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i>
          Back to List
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <!-- Basic Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Outlet -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet *</label>
              <select
                v-model="form.outlet_id"
                required
                @change="loadEmployees"
                :disabled="!props.canSelectOutlet"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
              >
                <option value="">Select Outlet</option>
                <option v-for="outlet in props.outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="!props.canSelectOutlet" class="mt-1 text-xs text-gray-500">
                Outlet otomatis dipilih sesuai outlet Anda
              </p>
            </div>

            <!-- Employee -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nama Karyawan *</label>
              <Multiselect
                v-model="selectedEmployee"
                :options="employeeOptions"
                :searchable="true"
                :clear-on-select="false"
                :close-on-select="true"
                :show-labels="false"
                :disabled="!form.outlet_id || employeeOptions.length === 0"
                track-by="id"
                label="name"
                placeholder="Pilih karyawan..."
                @select="onEmployeeSelect"
                @remove="onEmployeeRemove"
                class="w-full"
              >
                <template slot="option" slot-scope="props">
                  <div class="flex flex-col">
                    <div class="font-medium">{{ props.option.name }}</div>
                    <div class="text-xs text-gray-500">{{ props.option.nik }}</div>
                  </div>
                </template>
              </Multiselect>
              <p v-if="!form.outlet_id" class="mt-1 text-xs text-gray-500">
                Pilih outlet terlebih dahulu
              </p>
              <p v-if="form.outlet_id && employeeOptions.length === 0" class="mt-1 text-xs text-yellow-600">
                Tidak ada karyawan aktif di outlet ini
              </p>
            </div>

            <!-- Resignation Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Resign *</label>
              <input
                v-model="form.resignation_date"
                type="date"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Resignation Type -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Resign *</label>
              <select
                v-model="form.resignation_type"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Type</option>
                <option value="prosedural">Prosedural</option>
                <option value="non_prosedural">Non Prosedural</option>
              </select>
            </div>
          </div>

          <!-- Notes -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea
              v-model="form.notes"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter notes about the resignation..."
            ></textarea>
          </div>

          <!-- Approval Flow Section -->
          <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Flow</h3>
            <p class="text-sm text-gray-600 mb-4">Add approvers in order from lowest to highest level. The first approver will be the lowest level, and the last approver will be the highest level.</p>
            
            <!-- Add Approver Input -->
            <div class="mb-4">
              <div class="relative">
                <input
                  v-model="approverSearch"
                  type="text"
                  placeholder="Search users by name, email, or NIK..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
                  @input="handleApproverSearch"
                />
                
                <!-- Dropdown Results -->
                <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                  <div
                    v-for="user in approverResults"
                    :key="user.id"
                    @click="addApprover(user)"
                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                  >
                    <div class="font-medium">{{ user.nama_lengkap }}</div>
                    <div class="text-sm text-gray-600">{{ user.email }}</div>
                    <div v-if="user.jabatan" class="text-xs text-blue-600 font-medium">
                      {{ typeof user.jabatan === 'object' ? (user.jabatan.nama_jabatan || '') : user.jabatan }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Approvers List -->
            <div v-if="form.approvers.length > 0" class="space-y-2">
              <h4 class="font-medium text-gray-700">Approval Order (Lowest to Highest):</h4>
              
              <div
                v-for="(approver, index) in form.approvers"
                :key="approver.id"
                class="flex items-center justify-between p-3 rounded-md bg-gray-50 border border-gray-200"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="index > 0"
                      @click="reorderApprover(index, index - 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Move Up"
                    >
                      <i class="fa fa-arrow-up"></i>
                    </button>
                    <button
                      v-if="index < form.approvers.length - 1"
                      @click="reorderApprover(index, index + 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Move Down"
                    >
                      <i class="fa fa-arrow-down"></i>
                    </button>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      Level {{ index + 1 }}
                    </span>
                    <div>
                      <div class="font-medium">{{ approver.nama_lengkap }}</div>
                      <div class="text-sm text-gray-600">{{ approver.email }}</div>
                      <div v-if="approver.jabatan" class="text-xs text-blue-600 font-medium">
                        {{ typeof approver.jabatan === 'object' ? (approver.jabatan.nama_jabatan || '') : approver.jabatan }}
                      </div>
                    </div>
                  </div>
                </div>
                <button
                  @click="removeApprover(index)"
                  class="p-1 text-red-500 hover:text-red-700"
                  title="Remove Approver"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end space-x-4">
            <Link
              :href="'/employee-resignations'"
              class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="isSubmitting">
                <i class="fa fa-spinner fa-spin mr-2"></i>
                Creating...
              </span>
              <span v-else>
                <i class="fa fa-save mr-2"></i>
                Create Resignation
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  outlets: Array,
  userOutletId: {
    type: Number,
    default: null
  },
  canSelectOutlet: {
    type: Boolean,
    default: true
  },
})

const form = reactive({
  outlet_id: '',
  employee_id: null,
  resignation_date: '',
  resignation_type: '',
  notes: '',
  approvers: [],
})

const employees = ref([])
const employeeOptions = ref([])
const selectedEmployee = ref(null)
const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)
const isSubmitting = ref(false)

// Auto-set outlet if user is not from outlet 1
onMounted(() => {
  if (!props.canSelectOutlet && props.userOutletId && props.outlets.length > 0) {
    form.outlet_id = props.outlets[0].id_outlet
    loadEmployees()
  }
})

// Load employees by outlet
const loadEmployees = async () => {
  if (!form.outlet_id) {
    employees.value = []
    employeeOptions.value = []
    selectedEmployee.value = null
    form.employee_id = null
    return
  }

  try {
    const response = await axios.get('/employee-resignations/get-employees', {
      params: { outlet_id: form.outlet_id }
    })
    employees.value = response.data.employees || []
    
    // Convert to multiselect format
    employeeOptions.value = employees.value.map(emp => ({
      id: emp.id,
      name: `${emp.nama_lengkap} (${emp.nik})`,
      nik: emp.nik,
      nama_lengkap: emp.nama_lengkap
    }))
    
    // Clear selected employee when outlet changes
    selectedEmployee.value = null
    form.employee_id = null
  } catch (error) {
    console.error('Failed to load employees:', error)
    employees.value = []
    employeeOptions.value = []
    selectedEmployee.value = null
    form.employee_id = null
  }
}

// Handle employee selection
const onEmployeeSelect = (employee) => {
  form.employee_id = employee.id
}

// Handle employee removal
const onEmployeeRemove = () => {
  form.employee_id = null
  selectedEmployee.value = null
}

// Handle approver search
const handleApproverSearch = () => {
  if (approverSearch.value.length >= 2) {
    loadApprovers(approverSearch.value)
  } else {
    showApproverDropdown.value = false
    approverResults.value = []
  }
}

// Load approvers
const loadApprovers = async (search = '') => {
  if (search.length < 2) {
    approverResults.value = []
    showApproverDropdown.value = false
    return
  }

  try {
    const response = await axios.get('/employee-resignations/approvers', {
      params: { search }
    })
    approverResults.value = response.data.approvers || []
    showApproverDropdown.value = approverResults.value.length > 0
  } catch (error) {
    console.error('Failed to load approvers:', error)
    approverResults.value = []
    showApproverDropdown.value = false
  }
}

// Add approver
const addApprover = (user) => {
  if (!form.approvers.find(approver => approver.id === user.id)) {
    form.approvers.push(user)
  }
  approverSearch.value = ''
  showApproverDropdown.value = false
  approverResults.value = []
}

// Remove approver
const removeApprover = (index) => {
  form.approvers.splice(index, 1)
}

// Reorder approver
const reorderApprover = (fromIndex, toIndex) => {
  const approver = form.approvers.splice(fromIndex, 1)[0]
  form.approvers.splice(toIndex, 0, approver)
}

// Submit form
const submitForm = async () => {
  // Validate
  if (!form.outlet_id) {
    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please select an outlet'
    })
    return
  }

  if (!selectedEmployee.value || !form.employee_id) {
    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please select an employee'
    })
    return
  }

  if (!form.resignation_date) {
    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please select resignation date'
    })
    return
  }

  if (!form.resignation_type) {
    Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Please select resignation type'
    })
    return
  }

  isSubmitting.value = true

  try {
    await router.post('/employee-resignations', {
      outlet_id: form.outlet_id,
      employee_id: form.employee_id,
      resignation_date: form.resignation_date,
      resignation_type: form.resignation_type,
      notes: form.notes,
      approvers: form.approvers.map(approver => approver.id)
    })
  } catch (error) {
    console.error('Failed to create resignation:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to create employee resignation'
    })
    isSubmitting.value = false
  }
}
</script>

