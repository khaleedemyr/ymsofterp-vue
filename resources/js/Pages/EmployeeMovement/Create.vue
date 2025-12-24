<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import Swal from 'sweetalert2';

const form = useForm({
  employee_id: '',
  employee_name: '',
  employee_position: '',
  employee_division: '',
  employee_unit_property: '',
  employee_join_date: '',
  employment_type: '',
  employment_effective_date: '',
  kpi_required: false,
  kpi_date: '',
  psikotest_required: false,
  psikotest_score: '',
  psikotest_date: '',
  training_attendance_required: false,
  training_attendance_date: '',
  position_change: false,
  position_from: '',
  position_to: '',
  level_change: false,
  level_from: '',
  level_to: '',
  salary_change: false,
  salary_from: '',
  salary_to: '',
  gaji_pokok_to: '',
  tunjangan_to: '',
  department_change: false,
  department_from: '',
  department_to: '',
  division_change: false,
  division_from: '',
  division_to: '',
  unit_property_change: false,
  unit_property_from: '',
  unit_property_to: '',
  comments: '',
  kpi_attachment: null,
  psikotest_attachment: null,
  training_attachment: null,
  other_attachments: [],
  hod_approver_id: '', // Keep for backward compatibility
  gm_approver_id: '', // Keep for backward compatibility
  gm_hr_approver_id: '', // Keep for backward compatibility
  bod_approver_id: '', // Keep for backward compatibility
  approvers: [], // New approval flow system
  status: 'pending',
});

// Computed property untuk validasi salary fields
const isSalaryAllowed = computed(() => {
  return form.employment_type && 
         form.employment_type !== 'extend_contract_without_adjustment' &&
         form.employment_type !== 'termination';
});

// Watch employment_type untuk reset salary fields jika tidak diizinkan
watch(() => form.employment_type, (newType) => {
  if (newType === 'extend_contract_without_adjustment' || newType === 'termination') {
    form.salary_change = false;
    form.gaji_pokok_to = '';
    form.tunjangan_to = '';
    form.salary_to = '';
  }
});

const searchQuery = ref('');
const searchResults = ref([]);
const showSearchResults = ref(false);
const selectedEmployee = ref(null);
const isLoading = ref(false);

// Dropdown data
const positions = ref([]);
const levels = ref([]);
const divisions = ref([]);
const outlets = ref([]);
const approvers = ref([]); // For dropdown selection

// Approval flow
const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);

// Salary formatting function
const formatCurrency = (value) => {
  if (!value) return '';
  // Remove all non-numeric characters
  const numericValue = value.toString().replace(/\D/g, '');
  // Format with thousand separator
  return numericValue.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};

const unformatCurrency = (value) => {
  if (!value) return '';
  return value.toString().replace(/\D/g, '');
};

// Fetch dropdown data
const fetchDropdownData = async () => {
  try {
    console.log('Fetching dropdown data...');
    const url = route('employee-movements.dropdown-data');
    console.log('Request URL:', url);
    const response = await axios.get(url);
    console.log('Dropdown data response:', response.data);
    if (response.data.success) {
      positions.value = response.data.positions;
      levels.value = response.data.levels;
      divisions.value = response.data.divisions;
      outlets.value = response.data.outlets;
      console.log('Positions loaded:', positions.value.length);
      console.log('Levels loaded:', levels.value.length);
      console.log('Divisions loaded:', divisions.value.length);
      console.log('Outlets loaded:', outlets.value.length);
    } else {
      console.error('API returned success: false');
    }
  } catch (error) {
    console.error('Error fetching dropdown data:', error);
    console.error('Error details:', error.response?.data);
  }
};

// Fetch approvers data
const fetchApprovers = async () => {
  try {
    console.log('Fetching approvers data...');
    const url = route('employee-movements.approvers');
    const response = await axios.get(url);
    console.log('Approvers data response:', response.data);
    if (response.data.success) {
      approvers.value = response.data.approvers;
      console.log('Approvers loaded:', approvers.value.length);
    } else {
      console.error('API returned success: false');
    }
  } catch (error) {
    console.error('Error fetching approvers data:', error);
    console.error('Error details:', error.response?.data);
  }
};

// Initialize on mount
onMounted(() => {
  console.log('Component mounted, fetching dropdown data...');
  fetchDropdownData();
  fetchApprovers();
  
  // Debug: Check data after a delay
  setTimeout(() => {
    console.log('Positions after delay:', positions.value);
    console.log('Levels after delay:', levels.value);
    console.log('Divisions after delay:', divisions.value);
    console.log('Outlets after delay:', outlets.value);
    console.log('Approvers after delay:', approvers.value);
  }, 2000);
});

const searchEmployees = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    showSearchResults.value = false;
    return;
  }

  isLoading.value = true;
  try {
    const response = await axios.get(route('employee-movements.search-employee'), {
      params: { search: searchQuery.value }
    });
    searchResults.value = response.data;
    showSearchResults.value = true;
  } catch (error) {
    console.error('Error searching employees:', error);
  } finally {
    isLoading.value = false;
  }
};

const selectEmployee = async (employee) => {
  selectedEmployee.value = employee;
  form.employee_id = employee.id;
  form.employee_name = employee.nama_lengkap;
  form.employee_position = employee.jabatan?.nama_jabatan || '';
  form.employee_division = employee.divisi?.nama_divisi || '';
  form.employee_unit_property = employee.outlet?.nama_outlet || '';
  form.employee_join_date = employee.tanggal_masuk || '';
  
  // Fetch detailed employee data including salary and level
  try {
    const response = await axios.get(route('employee-movements.employee-details', employee.id));
    const employeeDetails = response.data;
    
    // Set current values for "from" fields
    form.position_from = employeeDetails.position || '';
    form.level_from = employeeDetails.current_level || '';
    form.salary_from = formatCurrency(employeeDetails.current_salary || 0);
    form.division_from = employeeDetails.division || '';
    form.unit_property_from = employeeDetails.unit_property || '';
  } catch (error) {
    console.error('Error fetching employee details:', error);
  }
  
  searchQuery.value = employee.nama_lengkap;
  showSearchResults.value = false;
  searchResults.value = [];
};

const submit = () => {
  // Validate required fields
  if (!form.employment_effective_date) {
    Swal.fire({
      title: 'Validation Error',
      text: 'Effective Date wajib diisi',
      icon: 'error',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  // Calculate total salary from gaji_pokok and tunjangan
  const gajiPokok = parseInt(form.gaji_pokok_to) || 0;
  const tunjangan = parseInt(form.tunjangan_to) || 0;
  form.salary_to = gajiPokok + tunjangan;
  
  // Unformat salary_from (remove commas) before submit
  if (form.salary_from) {
    form.salary_from = parseInt(unformatCurrency(form.salary_from)) || 0;
  }
  
  // Extract string values from multiselect objects
  const positionToValue = form.position_to?.id || '';
  const levelToValue = form.level_to?.id || '';
  const divisionToValue = form.division_to?.id || '';
  const unitPropertyToValue = form.unit_property_to?.id || '';
  const hodApproverValue = form.hod_approver_id?.id || '';
  const gmApproverValue = form.gm_approver_id?.id || '';
  const gmHrApproverValue = form.gm_hr_approver_id?.id || '';
  const bodApproverValue = form.bod_approver_id?.id || '';

  // Create summary for confirmation
  const summary = {
    employee: form.employee_name,
    employment_type: form.employment_type,
    effective_date: form.employment_effective_date,
    position_change: form.position_change ? 'Yes' : 'No',
    position_to: form.position_to?.name || '-',
    level_change: form.level_change ? 'Yes' : 'No',
    level_to: form.level_to?.name || '-',
    salary_change: form.salary_change ? 'Yes' : 'No',
    salary_to: form.salary_to ? formatCurrency(form.salary_to) : '-',
    division_change: form.division_change ? 'Yes' : 'No',
    division_to: form.division_to?.name || '-',
    unit_property_change: form.unit_property_change ? 'Yes' : 'No',
    unit_property_to: form.unit_property_to?.name || '-',
    comments: form.comments || '-'
  };

  // Update form values to use string IDs instead of objects
  form.position_to = positionToValue;
  form.level_to = levelToValue;
  form.division_to = divisionToValue;
  form.unit_property_to = unitPropertyToValue;
  form.hod_approver_id = hodApproverValue; // Keep for backward compatibility
  form.gm_approver_id = gmApproverValue; // Keep for backward compatibility
  form.gm_hr_approver_id = gmHrApproverValue; // Keep for backward compatibility
  form.bod_approver_id = bodApproverValue; // Keep for backward compatibility
  
  // Convert approvers array to IDs for submission (filter out invalid entries)
  const approversIds = form.approvers
    .filter(approver => approver && approver.id)
    .map(approver => approver.id);
  
  // Store original approvers for display
  const originalApprovers = [...form.approvers];
  
  // Update form.approvers to IDs immediately (before confirmation dialog)
  form.approvers = approversIds;

  // Show confirmation dialog with summary
  Swal.fire({
    title: 'Confirm Employee Movement',
    html: `
      <div class="text-left">
        <h3 class="font-bold text-lg mb-3">Summary:</h3>
        <div class="space-y-2 text-sm">
          <div><strong>Employee:</strong> ${summary.employee}</div>
          <div><strong>Employment Type:</strong> ${summary.employment_type}</div>
          <div><strong>Effective Date:</strong> ${summary.effective_date || '-'}</div>
          <hr class="my-2">
          <div><strong>Position Change:</strong> ${summary.position_change}</div>
          <div><strong>New Position:</strong> ${summary.position_to}</div>
          <div><strong>Level Change:</strong> ${summary.level_change}</div>
          <div><strong>New Level:</strong> ${summary.level_to}</div>
          <div><strong>Salary Change:</strong> ${summary.salary_change}</div>
          <div><strong>New Salary:</strong> ${summary.salary_to}</div>
          <div><strong>Division Change:</strong> ${summary.division_change}</div>
          <div><strong>New Division:</strong> ${summary.division_to}</div>
          <div><strong>Unit/Property Change:</strong> ${summary.unit_property_change}</div>
          <div><strong>New Unit/Property:</strong> ${summary.unit_property_to}</div>
          <div><strong>Adjustment Effective Date:</strong> ${summary.adjustment_effective_date || '-'}</div>
          <hr class="my-2">
          <div><strong>Comments:</strong> ${summary.comments}</div>
        </div>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Save Employee Movement',
    cancelButtonText: 'Cancel',
    showLoaderOnConfirm: true,
    preConfirm: () => {
      return new Promise((resolve) => {
        // Ensure approvers is an array of IDs (should already be set above)
        // Double-check and filter out any null/undefined values
        form.approvers = form.approvers.filter(id => id != null && id !== '');
        
        // Debug log
        console.log('Submitting approvers:', form.approvers);
        console.log('Form data before submit:', {
          approvers: form.approvers,
          approversType: typeof form.approvers,
          approversIsArray: Array.isArray(form.approvers),
          approversLength: form.approvers.length
        });
        
        form.post(route('employee-movements.store'), {
          onSuccess: () => {
            resolve();
          },
          onError: (errors) => {
            Swal.showValidationMessage(`Request failed: ${Object.values(errors).join(', ')}`);
            resolve(false);
          },
          forceFormData: true,
        });
      });
    },
    allowOutsideClick: () => !Swal.isLoading()
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Success!',
        text: 'Employee Movement has been saved successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
      }).then(() => {
        router.visit('/employee-movements');
      });
    }
  });
};

const handleOtherAttachments = (event) => {
  const files = Array.from(event.target.files);
  form.other_attachments = [...form.other_attachments, ...files];
};

const removeOtherAttachment = (index) => {
  form.other_attachments.splice(index, 1);
};

const goBack = () => {
  router.visit('/employee-movements');
};

// Approval flow functions
const loadApprovers = async (search = '') => {
  try {
    const response = await axios.get('/employee-movements/approvers', {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    });
    
    if (response.data.success) {
      // Map approvers to match expected structure
      approverResults.value = response.data.approvers.map(approver => ({
        id: approver.id,
        name: approver.nama_lengkap,
        email: approver.email || '',
        jabatan: approver.jabatan?.nama_jabatan || ''
      }));
      showApproverDropdown.value = true;
    }
  } catch (error) {
    console.error('Failed to load approvers:', error);
    approverResults.value = [];
  }
};

const addApprover = (user) => {
  // Check if user already exists
  if (!form.approvers.find(approver => approver.id === user.id)) {
    form.approvers.push(user);
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
};

const removeApprover = (index) => {
  form.approvers.splice(index, 1);
};

const reorderApprover = (fromIndex, toIndex) => {
  const approver = form.approvers.splice(fromIndex, 1)[0];
  form.approvers.splice(toIndex, 0, approver);
};
</script>

<template>
  <AppLayout title="Create Employee Movement">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create Employee Movement
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <form @submit.prevent="submit" class="p-6">
            <!-- Employee Search Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Employee Details</h3>
              
              <!-- Employee Search -->
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Employee</label>
                <div class="relative">
                  <input
                    v-model="searchQuery"
                    @input="searchEmployees"
                    type="text"
                    placeholder="Search by name, NIK, or email..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                  
                  <!-- Search Results Dropdown -->
                  <div
                    v-if="showSearchResults && searchResults.length > 0"
                    class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                  >
                    <div
                      v-for="employee in searchResults"
                      :key="employee.id"
                      @click="selectEmployee(employee)"
                      class="px-4 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                    >
                      <div class="font-medium">{{ employee.nama_lengkap }}</div>
                      <div class="text-sm text-gray-600">
                        NIK: {{ employee.nik }} | {{ employee.jabatan?.nama_jabatan || 'No Position' }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Employee Details Display -->
              <div v-if="selectedEmployee" class="bg-gray-50 p-4 rounded-md">
                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Position</label>
                    <input
                      v-model="form.employee_position"
                      type="text"
                      readonly
                      class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Division</label>
                    <input
                      v-model="form.employee_division"
                      type="text"
                      readonly
                      class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Unit/Property</label>
                    <input
                      v-model="form.employee_unit_property"
                      type="text"
                      readonly
                      class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Join Date</label>
                    <input
                      v-model="form.employee_join_date"
                      type="date"
                      readonly
                      class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                    />
                  </div>
                </div>
              </div>
            </div>

                         <!-- Employment & Renewal Section -->
             <div class="mb-8">
               <h3 class="text-lg font-medium text-gray-900 mb-4">Employment & Renewal</h3>
               <div class="bg-gray-50 p-4 rounded-md">
                 <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                   <div class="flex items-center">
                     <input
                       v-model="form.employment_type"
                       type="radio"
                       value="extend_contract_without_adjustment"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                     />
                     <label class="ml-2 text-sm text-gray-700">Extend contract without adjustment</label>
                   </div>
                   <div class="flex items-center">
                     <input
                       v-model="form.employment_type"
                       type="radio"
                       value="extend_contract_with_adjustment"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                     />
                     <label class="ml-2 text-sm text-gray-700">Extend contract with adjustment</label>
                   </div>
                   <div class="flex items-center">
                     <input
                       v-model="form.employment_type"
                       type="radio"
                       value="promotion"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                     />
                     <label class="ml-2 text-sm text-gray-700">Promotion</label>
                   </div>
                   <div class="flex items-center">
                     <input
                       v-model="form.employment_type"
                       type="radio"
                       value="demotion"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                     />
                     <label class="ml-2 text-sm text-gray-700">Demotion</label>
                   </div>
                   <div class="flex items-center">
                     <input
                       v-model="form.employment_type"
                       type="radio"
                       value="mutation"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                     />
                     <label class="ml-2 text-sm text-gray-700">Mutation</label>
                   </div>
                   <div class="flex items-center">
                     <input
                       v-model="form.employment_type"
                       type="radio"
                       value="termination"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                     />
                     <label class="ml-2 text-sm text-gray-700">Termination</label>
                   </div>
                 </div>
                <div class="mt-4">
                  <label class="block text-sm font-medium text-gray-700">
                    Effective Date <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.employment_effective_date"
                    type="date"
                    required
                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md"
                    :class="{ 'border-red-500': !form.employment_effective_date }"
                  />
                  <p v-if="!form.employment_effective_date" class="mt-1 text-sm text-red-600">Effective Date wajib diisi</p>
                </div>
              </div>
            </div>

                         <!-- Supporting Documents Section -->
             <div class="mb-8">
               <h3 class="text-lg font-medium text-gray-900 mb-4">Supporting Documents</h3>
               <div class="bg-gray-50 p-4 rounded-md">
                 <div class="space-y-6">
                   <!-- KPI Section -->
                   <div class="border-b border-gray-200 pb-4">
                     <div class="flex items-center justify-between mb-3">
                       <div class="flex items-center">
                         <input
                           v-model="form.kpi_required"
                           type="checkbox"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                         />
                         <label class="ml-2 text-sm font-medium text-gray-700">Key Performance Indicators (KPI)</label>
                       </div>
                       <input
                         v-model="form.kpi_date"
                         type="date"
                         class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                       />
                     </div>
                     <div class="ml-6">
                       <label class="block text-sm text-gray-600 mb-2">Upload KPI Document (PDF/Image)</label>
                       <input
                         @change="form.kpi_attachment = $event.target.files[0]"
                         type="file"
                         accept=".pdf,.jpg,.jpeg,.png"
                         class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       />
                       <p class="text-xs text-gray-500 mt-1">Max file size: 2MB. Allowed formats: PDF, JPG, JPEG, PNG</p>
                     </div>
                   </div>

                   <!-- Psikotest Section -->
                   <div class="border-b border-gray-200 pb-4">
                     <div class="flex items-center justify-between mb-3">
                       <div class="flex items-center">
                         <input
                           v-model="form.psikotest_required"
                           type="checkbox"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                         />
                         <label class="ml-2 text-sm font-medium text-gray-700">Psikotest Result by Training Manager: score =</label>
                       </div>
                       <div class="flex space-x-2">
                         <input
                           v-model="form.psikotest_score"
                           type="text"
                           placeholder="Score"
                           class="px-3 py-2 border border-gray-300 rounded-md w-20 text-sm"
                         />
                         <input
                           v-model="form.psikotest_date"
                           type="date"
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                         />
                       </div>
                     </div>
                     <div class="ml-6">
                       <label class="block text-sm text-gray-600 mb-2">Upload Psikotest Result (PDF/Image)</label>
                       <input
                         @change="form.psikotest_attachment = $event.target.files[0]"
                         type="file"
                         accept=".pdf,.jpg,.jpeg,.png"
                         class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       />
                       <p class="text-xs text-gray-500 mt-1">Max file size: 2MB. Allowed formats: PDF, JPG, JPEG, PNG</p>
                     </div>
                   </div>

                   <!-- Training Attendance Section -->
                   <div class="border-b border-gray-200 pb-4">
                     <div class="flex items-center justify-between mb-3">
                       <div class="flex items-center">
                         <input
                           v-model="form.training_attendance_required"
                           type="checkbox"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                         />
                         <label class="ml-2 text-sm font-medium text-gray-700">Training Attendance Record by Training Manager</label>
                       </div>
                       <input
                         v-model="form.training_attendance_date"
                         type="date"
                         class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                       />
                     </div>
                     <div class="ml-6">
                       <label class="block text-sm text-gray-600 mb-2">Upload Training Record (PDF/Image)</label>
                       <input
                         @change="form.training_attachment = $event.target.files[0]"
                         type="file"
                         accept=".pdf,.jpg,.jpeg,.png"
                         class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       />
                       <p class="text-xs text-gray-500 mt-1">Max file size: 2MB. Allowed formats: PDF, JPG, JPEG, PNG</p>
                     </div>
                   </div>

                   <!-- Other Attachments Section -->
                   <div>
                     <label class="block text-sm font-medium text-gray-700 mb-3">Other Supporting Documents</label>
                     <div class="ml-6">
                       <input
                         @change="handleOtherAttachments"
                         type="file"
                         multiple
                         accept=".pdf,.jpg,.jpeg,.png"
                         class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       />
                       <p class="text-xs text-gray-500 mt-1">Max file size: 2MB per file. Allowed formats: PDF, JPG, JPEG, PNG</p>
                       
                       <!-- Display selected files -->
                       <div v-if="form.other_attachments.length > 0" class="mt-3">
                         <p class="text-sm font-medium text-gray-700 mb-2">Selected files:</p>
                         <ul class="text-sm text-gray-600 space-y-1">
                           <li v-for="(file, index) in form.other_attachments" :key="index" class="flex items-center justify-between">
                             <span>{{ file.name }}</span>
                             <button
                               @click="removeOtherAttachment(index)"
                               type="button"
                               class="text-red-600 hover:text-red-800 text-xs"
                             >
                               Remove
                             </button>
                           </li>
                         </ul>
                       </div>
                     </div>
                   </div>
                 </div>
               </div>
             </div>

            <!-- Adjustment & Movement Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Adjustment & Movement</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <div class="space-y-6">
                  <!-- Position -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        v-model="form.position_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Position</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.position_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <Multiselect
                        v-model="form.position_to"
                        :options="positions"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="true"
                        :show-labels="false"
                        track-by="id"
                        label="name"
                        placeholder="Select position..."
                        class="w-full"
                        @open="() => console.log('Position multiselect opened, options:', positions)"
                      />
                    </div>
                  </div>
                  
                  <!-- Level -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        v-model="form.level_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Level</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.level_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <Multiselect
                        v-model="form.level_to"
                        :options="levels"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="true"
                        :show-labels="false"
                        track-by="id"
                        label="name"
                        placeholder="Select level..."
                        class="w-full"
                        @open="() => console.log('Level multiselect opened, options:', levels)"
                      />
                    </div>
                  </div>
                  
                  <!-- Salary -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        v-model="form.salary_change"
                        type="checkbox"
                        :disabled="!isSalaryAllowed"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                      />
                      <label class="ml-2 text-sm text-gray-700" :class="{ 'text-gray-400': !isSalaryAllowed }">
                        Salary
                        <span v-if="!isSalaryAllowed" class="text-xs text-gray-500 block">
                          (Not available for {{ form.employment_type?.replace(/_/g, ' ') || 'selected type' }})
                        </span>
                      </label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.salary_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div class="space-y-2">
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="block text-xs text-gray-500 mb-1">Gaji Pokok</label>
                          <input
                            v-model.number="form.gaji_pokok_to"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="0"
                            :disabled="!isSalaryAllowed || !form.salary_change"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100"
                          />
                        </div>
                        <div>
                          <label class="block text-xs text-gray-500 mb-1">Tunjangan</label>
                          <input
                            v-model.number="form.tunjangan_to"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="0"
                            :disabled="!isSalaryAllowed || !form.salary_change"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-100"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Division -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        v-model="form.division_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Division</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.division_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <Multiselect
                        v-model="form.division_to"
                        :options="divisions"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="true"
                        :show-labels="false"
                        track-by="id"
                        label="name"
                        placeholder="Select division..."
                        class="w-full"
                        @open="() => console.log('Division multiselect opened, options:', divisions)"
                      />
                    </div>
                  </div>
                  
                  <!-- Unit/Property -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <div class="flex items-center">
                      <input
                        v-model="form.unit_property_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Unit/Property</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.unit_property_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <Multiselect
                        v-model="form.unit_property_to"
                        :options="outlets"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="true"
                        :show-labels="false"
                        track-by="id"
                        label="name"
                        placeholder="Select outlet..."
                        class="w-full"
                        @open="() => console.log('Outlet multiselect opened, options:', outlets)"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Comments Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Comments</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <label class="block text-sm font-medium text-gray-700 mb-2">(Explain reason for movement.)</label>
                <textarea
                  v-model="form.comments"
                  rows="4"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter comments here..."
                ></textarea>
              </div>
            </div>

            <!-- Approval Flow Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Flow</h3>
              <p class="text-sm text-gray-600 mb-4">Add approvers in order from lowest to highest level. The first approver will be the lowest level, and the last approver will be the highest level.</p>
              
              <!-- Add Approver Input -->
              <div class="mb-4">
                <div class="relative">
                  <input
                    v-model="approverSearch"
                    type="text"
                    placeholder="Search users by name, email, or jabatan..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @input="approverSearch.length >= 2 && loadApprovers(approverSearch)"
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
                        <div class="font-medium">{{ approver.name }}</div>
                        <div class="text-sm text-gray-600">{{ approver.email }}</div>
                        <div v-if="approver.jabatan" class="text-xs text-blue-600 font-medium">{{ approver.jabatan }}</div>
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



            <!-- Form Actions -->
            <div class="flex justify-end space-x-4">
              <button
                type="button"
                @click="goBack"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
              >
                {{ form.processing ? 'Saving...' : 'Save Employee Movement' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Multiselect styling */
:deep(.multiselect) {
  min-height: 42px;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__input) {
  background: transparent;
  border: none;
  outline: none;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 0.5rem 0;
}

:deep(.multiselect__single) {
  background: transparent;
  padding: 0.5rem 0;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__option) {
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>
