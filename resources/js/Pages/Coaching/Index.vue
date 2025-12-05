<template>
  <AppLayout title="Coaching">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Coaching
        </h2>
        <Link :href="route('coaching.create')" 
              class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
          <i class="fa-solid fa-plus mr-2"></i>Tambah Coaching
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="w-full mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <!-- Action Buttons -->
            <div class="mb-6 flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-800">Data Coaching</h3>
              <Link :href="route('coaching.create')" 
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <i class="fa-solid fa-plus mr-2"></i>Tambah Coaching
              </Link>
            </div>
            
            <!-- Filters -->
            <div class="mb-6">
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                  <select v-model="filters.status" @change="filterData" 
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Menunggu Persetujuan</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                    <option value="completed">Selesai</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Karyawan</label>
                  <input v-model="filters.employee" @input="filterData" 
                         type="text" placeholder="Cari karyawan..."
                         class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
                  <input v-model="filters.start_date" @change="filterData" 
                         type="date"
                         class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
                  <input v-model="filters.end_date" @change="filterData" 
                         type="date"
                         class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
              </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
              <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Karyawan
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Tanggal Pelanggaran
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Detail
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Tindakan Disipliner
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Aksi
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="coaching in filteredCoachings" :key="coaching.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                          <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold">
                            {{ coaching.employee?.nama_lengkap?.charAt(0) || '?' }}
                          </div>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium text-gray-900">
                            {{ coaching.employee?.nama_lengkap }}
                          </div>
                          <div class="text-sm text-gray-500">
                            {{ coaching.employee?.jabatan?.nama_jabatan || 'N/A' }}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ formatDate(coaching.violation_date) }}
                    </td>
                    <td class="px-6 py-4">
                      <div class="text-sm text-gray-900 max-w-xs truncate">
                        {{ coaching.violation_details }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                            :class="getStatusClass(coaching.status)">
                        {{ getStatusText(coaching.status) }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                      <div v-if="parseDisciplinaryActions(coaching.disciplinary_actions).length > 0">
                        <div v-for="(action, index) in parseDisciplinaryActions(coaching.disciplinary_actions)" :key="index" 
                             class="text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded-full inline-block mr-1 mb-1">
                          {{ action.name }}
                        </div>
                      </div>
                      <span v-else class="text-gray-400">Tidak ada</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <div class="flex space-x-2">
                        <Link :href="route('coaching.show', coaching.id)" 
                              class="text-blue-600 hover:text-blue-900">
                          <i class="fa-solid fa-eye"></i>
                        </Link>
                        <Link :href="route('coaching.edit', coaching.id)" 
                              class="text-green-600 hover:text-green-900">
                          <i class="fa-solid fa-edit"></i>
                        </Link>
                        <button @click="deleteCoaching(coaching.id)" 
                                class="text-red-600 hover:text-red-900">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Empty State -->
            <div v-if="filteredCoachings.length === 0" class="text-center py-12">
              <i class="fa-solid fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
              <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada data coaching</h3>
              <p class="text-gray-500 mb-4">Belum ada data coaching yang sesuai dengan filter yang dipilih.</p>
              <Link :href="route('coaching.create')" 
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <i class="fa-solid fa-plus mr-2"></i>Tambah Coaching Pertama
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const page = usePage()

const props = defineProps({
  coachings: {
    type: Array,
    default: () => []
  }
})

// Filters
const filters = ref({
  status: '',
  employee: '',
  start_date: '',
  end_date: ''
})

// Helper function to parse disciplinary actions
function parseDisciplinaryActions(disciplinaryActions) {
  if (!disciplinaryActions) {
    return [];
  }
  
  // If it's already an array, return it
  if (Array.isArray(disciplinaryActions)) {
    return disciplinaryActions;
  }
  
  // If it's a string, try to parse it
  if (typeof disciplinaryActions === 'string') {
    try {
      return JSON.parse(disciplinaryActions);
    } catch (error) {
      console.error('Error parsing disciplinary actions:', error);
      return [];
    }
  }
  
  return [];
}

// Computed
const filteredCoachings = computed(() => {
  let result = props.coachings

  if (filters.value.status) {
    result = result.filter(coaching => coaching.status === filters.value.status)
  }

  if (filters.value.employee) {
    result = result.filter(coaching => 
      coaching.employee?.nama_lengkap?.toLowerCase().includes(filters.value.employee.toLowerCase())
    )
  }

  if (filters.value.start_date) {
    result = result.filter(coaching => 
      new Date(coaching.violation_date) >= new Date(filters.value.start_date)
    )
  }

  if (filters.value.end_date) {
    result = result.filter(coaching => 
      new Date(coaching.violation_date) <= new Date(filters.value.end_date)
    )
  }

  return result
})

// Helper functions
function formatDate(dateString) {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

function getStatusClass(status) {
  const classes = {
    'draft': 'bg-gray-100 text-gray-800',
    'pending': 'bg-yellow-100 text-yellow-800',
    'approved': 'bg-green-100 text-green-800',
    'rejected': 'bg-red-100 text-red-800',
    'completed': 'bg-blue-100 text-blue-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

function getStatusText(status) {
  const texts = {
    'draft': 'Draft',
    'pending': 'Menunggu Persetujuan',
    'approved': 'Disetujui',
    'rejected': 'Ditolak',
    'completed': 'Selesai'
  }
  return texts[status] || status
}

function filterData() {
  // This will trigger the computed property to recalculate
}

async function deleteCoaching(coachingId) {
  const result = await Swal.fire({
    title: 'Apakah Anda yakin?',
    text: "Data coaching akan dihapus secara permanen!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  })

  if (result.isConfirmed) {
    router.delete(route('coaching.destroy', coachingId))
  }
}

// Show flash messages
onMounted(() => {
  if (page.props.flash?.success) {
    Swal.fire({
      title: 'Berhasil!',
      text: page.props.flash.success,
      icon: 'success',
      timer: 2000,
      showConfirmButton: false
    })
  }
  
  if (page.props.flash?.error) {
    Swal.fire({
      title: 'Error!',
      text: page.props.flash.error,
      icon: 'error'
    })
  }
})
</script>