<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

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
  adjustment_effective_date: '',
  comments: '',
  kpi_attachment: null,
  psikotest_attachment: null,
  training_attachment: null,
  other_attachments: [],
  status: 'draft',
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

// Initialize on mount
onMounted(() => {
  console.log('Component mounted, fetching dropdown data...');
  fetchDropdownData();
  
  // Debug: Check data after a delay
  setTimeout(() => {
    console.log('Positions after delay:', positions.value);
    console.log('Levels after delay:', levels.value);
    console.log('Divisions after delay:', divisions.value);
    console.log('Outlets after delay:', outlets.value);
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
  // Calculate total salary from gaji_pokok and tunjangan
  const gajiPokok = unformatCurrency(form.gaji_pokok_to);
  const tunjangan = unformatCurrency(form.tunjangan_to);
  form.salary_to = (parseInt(gajiPokok) || 0) + (parseInt(tunjangan) || 0);
  
  form.post(route('employee-movements.store'), {
    onSuccess: () => {
      // Success handled by Inertia
    },
    forceFormData: true,
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
                  <label class="block text-sm font-medium text-gray-700">Effective Date</label>
                  <input
                    v-model="form.employment_effective_date"
                    type="date"
                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md"
                  />
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
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Salary</label>
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
                            v-model="form.gaji_pokok_to"
                            @input="form.gaji_pokok_to = formatCurrency($event.target.value)"
                            type="text"
                            placeholder="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                          />
                        </div>
                        <div>
                          <label class="block text-xs text-gray-500 mb-1">Tunjangan</label>
                          <input
                            v-model="form.tunjangan_to"
                            @input="form.tunjangan_to = formatCurrency($event.target.value)"
                            type="text"
                            placeholder="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
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
                
                <div class="mt-6">
                  <label class="block text-sm font-medium text-gray-700">Effective Date</label>
                  <input
                    v-model="form.adjustment_effective_date"
                    type="date"
                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-md"
                  />
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

            <!-- Status Section -->
            <div class="mb-8">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Status</h3>
              <div class="bg-gray-50 p-4 rounded-md">
                <select
                  v-model="form.status"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="draft">Draft</option>
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                </select>
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
