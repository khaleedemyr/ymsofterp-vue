<template>
    <div>
        <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-indigo-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            <i class="fa-solid fa-file-circle-check mr-2 text-indigo-500"></i>
                            SOP Development Approval
                        </h3>
                    </div>
                    <div class="bg-indigo-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                        {{ approvalCount }}
                    </div>
                </div>

                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                </div>

                <div v-else class="space-y-2">
                    <div v-for="item in pendingApprovals.slice(0, 3)" :key="'sop-approval-' + item.id"
                        @click="showDetails(item.id)"
                        class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
                        :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-indigo-50 hover:bg-indigo-100'">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ item.title }}
                                </div>
                                <div class="text-xs mt-1 flex items-center gap-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    <div class="flex-shrink-0 h-6 w-6 rounded-full overflow-hidden bg-indigo-100 flex items-center justify-center border border-indigo-200">
                                        <img
                                            v-if="item.user?.avatar || item.user?.avatar_path"
                                            :src="`/storage/${item.user.avatar || item.user.avatar_path}`"
                                            :alt="item.user?.nama_lengkap || 'User'"
                                            class="w-full h-full object-cover"
                                        />
                                        <i v-else class="fa-solid fa-user text-indigo-500 text-xs"></i>
                                    </div>
                                    <span>{{ item.user?.nama_lengkap || 'Unknown User' }}</span>
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa-solid fa-calendar mr-1 text-indigo-600"></i>
                                    Due: {{ formatDateOnly(item.due_date) }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ formatDate(item.submitted_at || item.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
                        <button @click="openAllModal" class="text-sm text-indigo-500 hover:text-indigo-700 font-medium">
                            Lihat {{ pendingApprovals.length - 3 }} SOP lainnya...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showDetailModal && selectedItem" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]" @click="closeDetailModal">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            <i class="fa-solid fa-file-circle-check mr-2 text-indigo-500"></i>
                            Detail SOP Development Approval
                        </h3>
                        <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fa fa-times text-xl"></i>
                        </button>
                    </div>

                    <div v-if="loadingDetail" class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
                        <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat detail...</p>
                    </div>

                    <div v-else class="space-y-6">
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi SOP</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <label class="text-gray-600 dark:text-gray-400">Judul SOP</label>
                                    <p class="text-gray-900 dark:text-white font-semibold">{{ selectedItem.title }}</p>
                                </div>
                                <div>
                                    <label class="text-gray-600 dark:text-gray-400">Due Date</label>
                                    <p class="text-gray-900 dark:text-white">{{ formatDateOnly(selectedItem.due_date) }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-gray-600 dark:text-gray-400">Pembuat</label>
                                    <p class="text-gray-900 dark:text-white font-semibold">{{ selectedItem.user?.nama_lengkap || '-' }}</p>
                                </div>
                                <div class="md:col-span-2" v-if="selectedItem.description">
                                    <label class="text-gray-600 dark:text-gray-400">Deskripsi</label>
                                    <p class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ selectedItem.description }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedItem.file_path" class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg">
                            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fa-solid fa-file mr-2 text-indigo-500"></i>
                                File SOP
                            </h4>
                            <a
                                :href="`/sop-development-completion/${selectedItem.id}/file`"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"
                            >
                                <i class="fa-solid fa-file-arrow-down"></i>
                                {{ selectedItem.file_original_name || 'Lihat / Download File SOP' }}
                            </a>
                        </div>

                        <div v-if="selectedItem.approval_flows?.length" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Approval Flow</h4>
                            <div class="space-y-2">
                                <div
                                    v-for="flow in selectedItem.approval_flows"
                                    :key="flow.id"
                                    class="flex items-center gap-3 text-sm p-2 rounded-lg"
                                    :class="flow.status === 'PENDING' ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'bg-white dark:bg-gray-800'"
                                >
                                    <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">{{ flow.approval_level }}</span>
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ flow.approver?.nama_lengkap || '-' }}</div>
                                        <div class="text-xs text-gray-500">{{ flow.status }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedItem.can_approve" class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button @click="showRejectModal"
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                                <i class="fa fa-times mr-2"></i>Tolak
                            </button>
                            <button @click="approveItem"
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
        default: false,
    },
});

const emit = defineEmits(['approved', 'rejected']);

const pendingApprovals = ref([]);
const loading = ref(false);
const showDetailModal = ref(false);
const selectedItem = ref(null);
const loadingDetail = ref(false);

const approvalCount = computed(() => pendingApprovals.value.length);

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDateOnly(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

async function loadPendingApprovals() {
    loading.value = true;
    try {
        const response = await axios.get('/api/sop-development-completion/pending-approvals');
        if (response.data?.success) {
            pendingApprovals.value = Array.isArray(response.data.data) ? response.data.data : [];
        } else {
            pendingApprovals.value = [];
        }
    } catch (error) {
        pendingApprovals.value = [];
    } finally {
        loading.value = false;
    }
}

async function showDetails(id) {
    try {
        loadingDetail.value = true;
        showDetailModal.value = true;
        const response = await axios.get(`/api/sop-development-completion/${id}`);
        if (response.data?.success && response.data.data) {
            selectedItem.value = {
                ...response.data.data,
                can_approve: response.data.can_approve,
            };
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal memuat detail', 'error');
            closeDetailModal();
        }
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'Gagal memuat detail', 'error');
        closeDetailModal();
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedItem.value = null;
}

async function approveItem() {
    if (!selectedItem.value) return;
    const itemId = selectedItem.value.id;

    const { value: notes } = await Swal.fire({
        title: 'Setujui SOP',
        input: 'textarea',
        inputLabel: 'Catatan (Opsional)',
        inputPlaceholder: 'Masukkan catatan approval...',
        showCancelButton: true,
        confirmButtonText: 'Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981',
    });

    if (notes === undefined) return;

    try {
        const response = await axios.post(`/api/sop-development-completion/${itemId}/approve`, {
            approval_notes: notes || null,
        });
        if (response.data?.success) {
            Swal.fire('Success', response.data.message || 'SOP berhasil disetujui', 'success');
            closeDetailModal();
            loadPendingApprovals();
            emit('approved', itemId);
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal menyetujui', 'error');
        }
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'Gagal menyetujui', 'error');
    }
}

function showRejectModal() {
    if (!selectedItem.value) return;
    const itemId = selectedItem.value.id;

    Swal.fire({
        title: 'Tolak SOP',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan *',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        inputValidator: (value) => {
            if (!value) return 'Alasan penolakan harus diisi!';
        },
    }).then(async (result) => {
        if (result.isConfirmed && itemId) {
            try {
                const response = await axios.post(`/api/sop-development-completion/${itemId}/reject`, {
                    approval_notes: result.value,
                });
                if (response.data?.success) {
                    Swal.fire('Success', response.data.message || 'SOP berhasil ditolak', 'success');
                    closeDetailModal();
                    loadPendingApprovals();
                    emit('rejected', itemId);
                } else {
                    Swal.fire('Error', response.data?.message || 'Gagal menolak', 'error');
                }
            } catch (error) {
                Swal.fire('Error', error.response?.data?.message || 'Gagal menolak', 'error');
            }
        }
    });
}

function openAllModal() {
    if (pendingApprovals.value.length > 0) {
        showDetails(pendingApprovals.value[0].id);
    }
}

onMounted(() => {
    loadPendingApprovals();
});

defineExpose({
    loadPendingApprovals,
    refresh: loadPendingApprovals,
});
</script>
