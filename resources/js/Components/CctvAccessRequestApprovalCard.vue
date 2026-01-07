<template>
    <div>
        <!-- CCTV Access Request Approval Section -->
        <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-blue-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            <i class="fa-solid fa-video mr-2 text-blue-500"></i>
                            CCTV Access Request Approval
                        </h3>
                    </div>
                    <div class="bg-blue-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                        {{ approvalCount }}
                    </div>
                </div>
                
                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                </div>
                
                <div v-else class="space-y-2">
                    <!-- CCTV Access Request Approvals -->
                    <div v-for="req in pendingApprovals.slice(0, 3)" :key="'cctv-approval-' + req.id"
                            @click="showDetails(req.id)"
                            class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
                            :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-blue-50 hover:bg-blue-100'">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                        <span :class="req.access_type === 'live_view' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-purple-100 text-purple-700 border border-purple-300'" 
                                              class="px-2 py-0.5 rounded-full text-xs font-bold mr-2">
                                            {{ req.access_type === 'live_view' ? 'Live View' : 'Playback' }}
                                        </span>
                                        Request #{{ req.id }}
                                    </div>
                                    <div class="text-xs mt-1 flex items-center gap-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    <div class="flex-shrink-0 h-6 w-6 rounded-full overflow-hidden bg-blue-100 flex items-center justify-center border border-blue-200">
                                      <img 
                                        v-if="req.user?.avatar || req.user?.avatar_path" 
                                        :src="`/storage/${req.user.avatar || req.user.avatar_path}`" 
                                        :alt="req.user?.nama_lengkap || 'User'"
                                        class="w-full h-full object-cover"
                                      />
                                      <i v-else class="fa-solid fa-user text-blue-500 text-xs"></i>
                                    </div>
                                    <span>{{ req.user?.nama_lengkap || 'Unknown User' }}</span>
                                </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa-solid fa-store mr-1 text-blue-600"></i>
                                        {{ req.outlet_ids?.length || 0 }} Outlet
                                    </div>
                                    <div v-if="req.access_type === 'live_view' && req.email" class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa-solid fa-envelope mr-1 text-blue-500"></i>{{ req.email }}
                                    </div>
                                    <div v-if="req.access_type === 'playback' && req.area" class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa-solid fa-map-marker-alt mr-1 text-purple-500"></i>{{ req.area }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        {{ formatDate(req.created_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Show more button if there are more than 3 Requests -->
                        <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
                            <button @click="openAllModal" class="text-sm text-blue-500 hover:text-blue-700 font-medium">
                                Lihat {{ pendingApprovals.length - 3 }} Request lainnya...
                            </button>
                        </div>
                </div>
            </div>
        </div>

        <!-- CCTV Access Request Approval Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedRequest" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]" @click="closeDetailModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa-solid fa-video mr-2 text-blue-500"></i>
                        Detail CCTV Access Request Approval
                    </h3>
                    <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>

                <div v-if="loadingDetail" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat detail...</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Request ID</label>
                                <p class="text-gray-900 dark:text-white font-semibold">#{{ selectedRequest.id }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Jenis Akses</label>
                                <p class="text-gray-900 dark:text-white">
                                    <span :class="selectedRequest.access_type === 'live_view' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-purple-100 text-purple-700 border border-purple-300'" 
                                          class="px-2 py-1 rounded-full text-xs font-bold">
                                        {{ selectedRequest.access_type === 'live_view' ? 'Live View' : 'Playback' }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">User</label>
                                <div class="flex items-center gap-3">
                                  <div class="flex-shrink-0 h-10 w-10 rounded-full overflow-hidden bg-blue-100 flex items-center justify-center border-2 border-blue-200">
                                    <img 
                                      v-if="selectedRequest.user?.avatar || selectedRequest.user?.avatar_path" 
                                      :src="`/storage/${selectedRequest.user.avatar || selectedRequest.user.avatar_path}`" 
                                      :alt="selectedRequest.user?.nama_lengkap || 'User'"
                                      class="w-full h-full object-cover"
                                    />
                                    <i v-else class="fa-solid fa-user text-blue-600"></i>
                                  </div>
                                  <p class="text-gray-900 dark:text-white font-semibold">{{ selectedRequest.user?.nama_lengkap || 'Unknown' }}</p>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Tanggal Request</label>
                                <p class="text-gray-900 dark:text-white">{{ formatDate(selectedRequest.created_at) }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</label>
                                <p class="text-gray-900 dark:text-white">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800 border border-yellow-300">
                                        Menunggu Approval
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Outlet Information -->
                    <div v-if="selectedRequest.outlet_ids && selectedRequest.outlet_ids.length > 0" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa-solid fa-store mr-2 text-blue-500"></i>
                            Outlet yang Diminta ({{ selectedRequest.outlet_ids.length }})
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <span 
                                v-for="outletId in selectedRequest.outlet_ids" 
                                :key="outletId"
                                class="px-3 py-1.5 bg-white dark:bg-gray-800 text-blue-800 dark:text-blue-200 rounded-lg text-sm font-semibold border border-blue-200 dark:border-blue-700"
                            >
                                {{ getOutletName(outletId) }}
                            </span>
                        </div>
                    </div>

                    <!-- Live View Information -->
                    <div v-if="selectedRequest.access_type === 'live_view'" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa-solid fa-eye mr-2 text-blue-500"></i>
                            Informasi Live View
                        </h4>
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Email</label>
                            <p class="text-gray-900 dark:text-white">
                                <i class="fa-solid fa-envelope mr-2 text-blue-500"></i>
                                {{ selectedRequest.email || '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Playback Information -->
                    <div v-if="selectedRequest.access_type === 'playback'" class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa-solid fa-play mr-2 text-purple-500"></i>
                            Informasi Playback
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Area</label>
                                <p class="text-gray-900 dark:text-white">
                                    <i class="fa-solid fa-map-marker-alt mr-2 text-purple-500"></i>
                                    {{ selectedRequest.area || '-' }}
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Tanggal</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs text-gray-500">Tanggal Mulai</label>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                                            {{ formatDateOnly(selectedRequest.date_from) || '-' }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500">Tanggal Selesai</label>
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                                            {{ formatDateOnly(selectedRequest.date_to) || '-' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Waktu Mulai</label>
                                <p class="text-gray-900 dark:text-white">
                                    {{ formatTime(selectedRequest.time_from) || '-' }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Waktu Selesai</label>
                                <p class="text-gray-900 dark:text-white">
                                    {{ formatTime(selectedRequest.time_to) || '-' }}
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Deskripsi Kejadian</label>
                                <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ selectedRequest.incident_description || '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Alasan Permintaan</h4>
                        <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ selectedRequest.reason || '-' }}</p>
                    </div>

                    <!-- Approval Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button @click="showRejectModal" 
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                            <i class="fa fa-times mr-2"></i>Tolak
                        </button>
                        <button @click="approveRequest" 
                                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                            <i class="fa fa-check mr-2"></i>Setujui
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Teleport } from 'vue';

const props = defineProps({
    isNight: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['approved', 'rejected']);

// State
const pendingApprovals = ref([]);
const loading = ref(false);
const showDetailModal = ref(false);
const selectedRequest = ref(null);
const loadingDetail = ref(false);

// Computed
const approvalCount = computed(() => pendingApprovals.value.length);

// Methods
function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatTime(time) {
    if (!time) return '-';
    return time.substring(0, 5); // Format HH:mm
}

function formatDateOnly(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getOutletName(outletId) {
    // Try to get from selectedRequest first (if detail is loaded)
    if (selectedRequest.value?.outlet_names) {
        const index = selectedRequest.value.outlet_ids?.indexOf(outletId);
        if (index !== -1 && selectedRequest.value.outlet_names[index]) {
            return selectedRequest.value.outlet_names[index];
        }
    }
    // Fallback to outlet ID
    return outletId;
}

async function loadPendingApprovals() {
    loading.value = true;
    try {
        const response = await axios.get('/api/cctv-access-requests/pending-approvals');
        console.log('CCTV Access Request pending approvals response:', response.data);
        if (response.data && response.data.success) {
            // Response is now a direct array (not paginated)
            if (Array.isArray(response.data.data)) {
                pendingApprovals.value = response.data.data || [];
            } else {
                pendingApprovals.value = [];
            }
        } else {
            pendingApprovals.value = [];
        }
    } catch (error) {
        console.error('Error loading pending CCTV Access Request approvals:', error);
        console.error('Error response:', error.response);
        // If unauthorized (403), just set empty array
        if (error.response?.status === 403) {
            pendingApprovals.value = [];
        } else {
            pendingApprovals.value = [];
        }
    } finally {
        loading.value = false;
    }
}

async function showDetails(requestId) {
    try {
        loadingDetail.value = true;
        showDetailModal.value = true;
        
        const response = await axios.get(`/api/cctv-access-requests/${requestId}`);
        if (response.data && response.data.success && response.data.data) {
            selectedRequest.value = response.data.data;
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal memuat detail Request', 'error');
            closeDetailModal();
        }
    } catch (error) {
        console.error('Error loading CCTV Access Request details:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal memuat detail Request';
        Swal.fire('Error', errorMessage, 'error');
        closeDetailModal();
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedRequest.value = null;
}

async function approveRequest() {
    if (!selectedRequest.value) return;
    
    // Store the ID before any async operations
    const requestId = selectedRequest.value.id;
    
    const { value: notes } = await Swal.fire({
        title: 'Setujui Request',
        input: 'textarea',
        inputLabel: 'Catatan (Opsional)',
        inputPlaceholder: 'Masukkan catatan approval...',
        inputAttributes: {
            'aria-label': 'Masukkan catatan approval'
        },
        showCancelButton: true,
        confirmButtonText: 'Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981',
    });

    if (notes === undefined) return; // User cancelled

    try {
        const response = await axios.post(`/api/cctv-access-requests/${requestId}/approve`, {
            approval_notes: notes || null
        });
        
        if (response.data && response.data.success) {
            Swal.fire('Success', response.data.message || 'Request berhasil disetujui', 'success');
            closeDetailModal();
            loadPendingApprovals();
            emit('approved', requestId);
        } else {
            const errorMsg = response.data?.message || 'Gagal menyetujui Request';
            Swal.fire('Error', errorMsg, 'error');
        }
    } catch (error) {
        console.error('Error approving CCTV Access Request:', error);
        const errorMsg = error.response?.data?.message || error.message || 'Gagal menyetujui Request';
        Swal.fire('Error', errorMsg, 'error');
    }
}

function showRejectModal() {
    if (!selectedRequest.value) return;
    
    // Store the ID before any async operations
    const requestId = selectedRequest.value.id;
    
    Swal.fire({
        title: 'Tolak Request',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan *',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputAttributes: {
            'aria-label': 'Masukkan alasan penolakan'
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!';
            }
        }
    }).then(async (result) => {
        if (result.isConfirmed && requestId) {
            
            try {
                const response = await axios.post(`/api/cctv-access-requests/${requestId}/reject`, {
                    approval_notes: result.value
                });
                
                if (response.data && response.data.success) {
                    Swal.fire('Success', response.data.message || 'Request berhasil ditolak', 'success');
                    closeDetailModal();
                    loadPendingApprovals();
                    emit('rejected', requestId);
                } else {
                    const errorMsg = response.data?.message || 'Gagal menolak Request';
                    Swal.fire('Error', errorMsg, 'error');
                }
            } catch (error) {
                console.error('Error rejecting CCTV Access Request:', error);
                const errorMsg = error.response?.data?.message || error.message || 'Gagal menolak Request';
                Swal.fire('Error', errorMsg, 'error');
            }
        }
    });
}

function openAllModal() {
    // For now, just show the detail of the first one
    // You can implement a full modal later if needed
    if (pendingApprovals.value.length > 0) {
        showDetails(pendingApprovals.value[0].id);
    }
}

// Lifecycle
onMounted(() => {
    loadPendingApprovals();
});

// Expose methods for parent component
defineExpose({
    loadPendingApprovals,
    refresh: loadPendingApprovals
});
</script>

