<template>
  <AppLayout title="Detail Coaching">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Detail Coaching
        </h2>
        <div class="flex gap-2">
          <Link :href="route('coaching.edit', coaching.id)" 
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fa-solid fa-edit mr-2"></i>Edit
          </Link>
          <Link :href="route('coaching.index')" 
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
            <i class="fa-solid fa-arrow-left mr-2"></i>Kembali
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <!-- Header Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-blue-800 mb-4">Informasi Karyawan</h3>
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Nama:</span>
                    <span class="font-medium">{{ coaching.employee?.nama_lengkap }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Jabatan:</span>
                    <span class="font-medium">{{ coaching.employee?.jabatan?.nama_jabatan || 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Divisi:</span>
                    <span class="font-medium">{{ coaching.employee?.divisi?.nama_divisi || 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Outlet:</span>
                    <span class="font-medium">{{ coaching.employee?.outlet?.nama_outlet || 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Tanggal Masuk:</span>
                    <span class="font-medium">{{ formatDate(coaching.employee?.tanggal_masuk) }}</span>
                  </div>
                </div>
              </div>

              <div class="bg-green-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-green-800 mb-4">Informasi Supervisor</h3>
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Nama:</span>
                    <span class="font-medium">{{ coaching.supervisor?.nama_lengkap }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Jabatan:</span>
                    <span class="font-medium">{{ coaching.supervisor?.jabatan?.nama_jabatan || 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Divisi:</span>
                    <span class="font-medium">{{ coaching.supervisor?.divisi?.nama_divisi || 'N/A' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Coaching Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <div class="bg-yellow-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-yellow-800 mb-4">Detail Coaching</h3>
                <div class="space-y-2">
                  <div class="flex justify-between">
                    <span class="text-gray-600">Tanggal Coaching:</span>
                    <span class="font-medium">{{ formatDate(coaching.coaching_date) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Tanggal Pelanggaran:</span>
                    <span class="font-medium">{{ formatDate(coaching.violation_date) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Lokasi:</span>
                    <span class="font-medium">{{ coaching.location || 'N/A' }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="px-2 py-1 rounded-full text-xs font-medium" 
                          :class="getStatusClass(coaching.status)">
                      {{ getStatusText(coaching.status) }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="bg-red-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-red-800 mb-4">Detail Pelanggaran</h3>
                <div class="space-y-2">
                  <div>
                    <span class="text-gray-600 block mb-1">Keterangan:</span>
                    <p class="text-sm bg-white p-3 rounded border">{{ coaching.violation_details }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Disciplinary Actions -->
            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Tindakan Disipliner</h3>
              
              <div v-if="parsedDisciplinaryActions && parsedDisciplinaryActions.length > 0" class="space-y-4">
                <div v-for="(action, index) in parsedDisciplinaryActions" :key="index" 
                     class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="font-semibold text-orange-800">{{ action.name }}</h4>
                    <span class="text-xs px-2 py-1 bg-orange-200 text-orange-800 rounded-full">
                      {{ index + 1 }}
                    </span>
                  </div>
                  <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                      <span class="text-gray-600">Tanggal Berlaku:</span>
                      <span class="font-medium">{{ formatDate(action.effective_date) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-600">Tanggal Berakhir:</span>
                      <span class="font-medium">{{ formatDate(action.end_date) }}</span>
                    </div>
                    <div v-if="action.remarks">
                      <span class="text-gray-600 block mb-1">Keterangan:</span>
                      <p class="text-xs bg-white p-2 rounded border">{{ action.remarks }}</p>
                    </div>
                  </div>
                </div>
              </div>
              
              <div v-else class="text-gray-500 text-center py-8">
                <i class="fa-solid fa-info-circle text-2xl mb-2"></i>
                <p>Tidak ada tindakan disipliner yang tercatat.</p>
              </div>
            </div>

            <!-- Comments -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <div v-if="coaching.supervisor_comments" class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-blue-800 mb-4">Komentar Supervisor</h3>
                <p class="text-sm bg-white p-3 rounded border">{{ coaching.supervisor_comments }}</p>
              </div>

              <div v-if="coaching.employee_response" class="bg-green-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-green-800 mb-4">Tanggapan Karyawan</h3>
                <p class="text-sm bg-white p-3 rounded border">{{ coaching.employee_response }}</p>
              </div>
            </div>

            <!-- Signatures -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <div v-if="coaching.supervisor_signature" class="bg-purple-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-purple-800 mb-4">Tanda Tangan Supervisor</h3>
                <div class="bg-white p-4 rounded border">
                  <img :src="coaching.supervisor_signature" 
                       alt="Tanda Tangan Supervisor" 
                       class="max-w-full h-auto max-h-32 object-contain">
                </div>
              </div>

              <div v-if="coaching.employee_signature" class="bg-indigo-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-indigo-800 mb-4">Tanda Tangan Karyawan</h3>
                <div class="bg-white p-4 rounded border">
                  <img :src="coaching.employee_signature" 
                       alt="Tanda Tangan Karyawan" 
                       class="max-w-full h-auto max-h-32 object-contain">
                </div>
              </div>
            </div>

            <!-- Approval Flow -->
            <div v-if="coaching.approvers && coaching.approvers.length > 0" class="mb-8">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Alur Persetujuan</h3>
              <div class="space-y-4">
                <div v-for="(approver, index) in coaching.approvers" :key="approver.id" 
                     class="flex items-center p-4 rounded-lg border"
                     :class="getApprovalClass(approver.status)">
                  <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-white font-bold"
                       :class="getApprovalIconClass(approver.status)">
                    {{ index + 1 }}
                  </div>
                  <div class="ml-4 flex-1">
                    <div class="flex items-center justify-between">
                      <div>
                        <h4 class="font-semibold">{{ approver.approver?.nama_lengkap }}</h4>
                        <p class="text-sm text-gray-600">{{ approver.approver?.jabatan?.nama_jabatan || 'N/A' }}</p>
                      </div>
                      <div class="text-right">
                        <span class="px-2 py-1 rounded-full text-xs font-medium"
                              :class="getApprovalStatusClass(approver.status)">
                          {{ getApprovalStatusText(approver.status) }}
                        </span>
                        <div v-if="approver.approved_at" class="text-xs text-gray-500 mt-1">
                          {{ formatDateTime(approver.approved_at) }}
                        </div>
                        <div v-if="approver.rejected_at" class="text-xs text-gray-500 mt-1">
                          {{ formatDateTime(approver.rejected_at) }}
                        </div>
                      </div>
                    </div>
                    <div v-if="approver.comments" class="mt-2">
                      <p class="text-sm bg-white p-2 rounded border">{{ approver.comments }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Timestamps -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Sistem</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-600">Dibuat oleh:</span>
                  <span class="font-medium">{{ coaching.creator?.nama_lengkap || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Dibuat pada:</span>
                  <span class="font-medium">{{ formatDateTime(coaching.created_at) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Diupdate oleh:</span>
                  <span class="font-medium">{{ coaching.updater?.nama_lengkap || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Diupdate pada:</span>
                  <span class="font-medium">{{ formatDateTime(coaching.updated_at) }}</span>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4 justify-end">
              <Link :href="route('coaching.index')" 
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali ke Daftar Coaching
              </Link>
              <Link :href="route('coaching.edit', coaching.id)" 
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
                <i class="fa-solid fa-edit"></i>
                Edit Coaching
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  coaching: {
    type: Object,
    required: true
  }
})

// Parse disciplinary actions from JSON string
const parsedDisciplinaryActions = computed(() => {
  if (!props.coaching.disciplinary_actions) {
    return [];
  }
  
  // If it's already an array, return it
  if (Array.isArray(props.coaching.disciplinary_actions)) {
    return props.coaching.disciplinary_actions;
  }
  
  // If it's a string, try to parse it
  if (typeof props.coaching.disciplinary_actions === 'string') {
    try {
      return JSON.parse(props.coaching.disciplinary_actions);
    } catch (error) {
      console.error('Error parsing disciplinary actions:', error);
      return [];
    }
  }
  
  return [];
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

function formatDateTime(dateString) {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
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

function getApprovalClass(status) {
  const classes = {
    'pending': 'bg-yellow-50 border-yellow-200',
    'approved': 'bg-green-50 border-green-200',
    'rejected': 'bg-red-50 border-red-200'
  }
  return classes[status] || 'bg-gray-50 border-gray-200'
}

function getApprovalIconClass(status) {
  const classes = {
    'pending': 'bg-yellow-500',
    'approved': 'bg-green-500',
    'rejected': 'bg-red-500'
  }
  return classes[status] || 'bg-gray-500'
}

function getApprovalStatusClass(status) {
  const classes = {
    'pending': 'bg-yellow-100 text-yellow-800',
    'approved': 'bg-green-100 text-green-800',
    'rejected': 'bg-red-100 text-red-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

function getApprovalStatusText(status) {
  const texts = {
    'pending': 'Menunggu',
    'approved': 'Disetujui',
    'rejected': 'Ditolak'
  }
  return texts[status] || status
}
</script>