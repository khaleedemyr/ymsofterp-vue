<script>
import { ref, reactive, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

export default {
    components: {
        AppLayout
    },
    props: {
        categories: Object,
        filters: Object
    },
    setup(props) {
        const showCreateModal = ref(false)
        const showEditModal = ref(false)
        const loading = ref(false)
        const editingCategory = ref(null)
        const actionLoading = ref({}) // Track loading state for individual actions
        
        const form = reactive({
            name: '',
            description: '',
            status: 'active'
        })
        
        const filters = reactive({
            search: props.filters?.search || '',
            status: props.filters?.status || ''
        })
        
        const loadCategories = (params = {}) => {
            router.get('/lms/categories', { ...filters, ...params }, {
                preserveState: true,
                preserveScroll: true
            })
        }
        
        const applyFilters = () => {
            loadCategories({ page: 1 })
        }
        
        const loadPage = (url) => {
            if (url) {
                router.visit(url, { preserveState: true })
            }
        }
        
        const showSweetAlert = (options) => {
            return Swal.fire({
                confirmButtonColor: '#3B82F6',
                cancelButtonColor: '#EF4444',
                ...options
            })
        }
        
        const showSuccessAlert = (message, title = 'Berhasil!') => {
            return showSweetAlert({
                icon: 'success',
                title: title,
                text: message,
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            })
        }
        
        const showErrorAlert = (message, title = 'Error!') => {
            return showSweetAlert({
                icon: 'error',
                title: title,
                text: message,
                confirmButtonText: 'OK'
            })
        }
        
        const showConfirmDialog = (title, text, callback, options = {}) => {
            return showSweetAlert({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                ...options
            }).then((result) => {
                if (result.isConfirmed) {
                    callback()
                }
            })
        }
        
        const showLoadingAlert = (title = 'Memproses...') => {
            return showSweetAlert({
                title: title,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading()
                }
            })
        }
        
        const createCategory = async () => {
            if (!form.name.trim()) {
                showErrorAlert('Nama kategori harus diisi!')
                return
            }
            
            loading.value = true
            const loadingAlert = showLoadingAlert('Menyimpan kategori...')
            
            try {
                await router.post('/lms/categories', form, {
                    onSuccess: () => {
                        loadingAlert.close()
                        showSuccessAlert('Kategori berhasil dibuat!')
                        closeModal()
                        loadCategories()
                    },
                    onError: (errors) => {
                        loadingAlert.close()
                        const errorMessage = Object.values(errors).flat().join(', ')
                        showErrorAlert(errorMessage || 'Terjadi kesalahan saat membuat kategori')
                    }
                })
            } catch (error) {
                loadingAlert.close()
                console.error('Error creating category:', error)
                showErrorAlert('Terjadi kesalahan saat membuat kategori')
            } finally {
                loading.value = false
            }
        }
        
        const editCategory = (category) => {
            editingCategory.value = category
            form.name = category.name
            form.description = category.description || ''
            form.status = category.status
            showEditModal.value = true
        }
        
        const updateCategory = async () => {
            if (!form.name.trim()) {
                showErrorAlert('Nama kategori harus diisi!')
                return
            }
            
            loading.value = true
            const loadingAlert = showLoadingAlert('Memperbarui kategori...')
            
            try {
                await router.put(`/lms/categories/${editingCategory.value.id}`, form, {
                    onSuccess: () => {
                        loadingAlert.close()
                        showSuccessAlert('Kategori berhasil diperbarui!')
                        closeModal()
                        loadCategories()
                    },
                    onError: (errors) => {
                        loadingAlert.close()
                        const errorMessage = Object.values(errors).flat().join(', ')
                        showErrorAlert(errorMessage || 'Terjadi kesalahan saat memperbarui kategori')
                    }
                })
            } catch (error) {
                loadingAlert.close()
                console.error('Error updating category:', error)
                showErrorAlert('Terjadi kesalahan saat memperbarui kategori')
            } finally {
                loading.value = false
            }
        }
        
        const toggleStatus = async (category) => {
            const action = category.status === 'active' ? 'menonaktifkan' : 'mengaktifkan'
            const statusText = category.status === 'active' ? 'nonaktif' : 'aktif'
            
            showConfirmDialog(
                'Konfirmasi Perubahan Status',
                `Apakah Anda yakin ingin ${action} kategori <strong>"${category.name}"</strong>?`,
                async () => {
                    actionLoading.value[category.id] = true
                    const loadingAlert = showLoadingAlert(`${action.charAt(0).toUpperCase() + action.slice(1)} kategori...`)
                    
                    try {
                        await router.put(`/lms/categories/${category.id}/toggle-status`, {}, {
                            onSuccess: () => {
                                loadingAlert.close()
                                showSuccessAlert(`Kategori berhasil di${action}!`)
                                loadCategories()
                            },
                            onError: (errors) => {
                                loadingAlert.close()
                                const errorMessage = Object.values(errors).flat().join(', ')
                                showErrorAlert(errorMessage || `Terjadi kesalahan saat ${action} kategori`)
                            }
                        })
                    } catch (error) {
                        loadingAlert.close()
                        console.error('Error toggling status:', error)
                        showErrorAlert(`Terjadi kesalahan saat ${action} kategori`)
                    } finally {
                        actionLoading.value[category.id] = false
                    }
                },
                {
                    html: true,
                    confirmButtonColor: category.status === 'active' ? '#EF4444' : '#10B981'
                }
            )
        }
        
        const deleteCategory = async (category) => {
            showConfirmDialog(
                'Konfirmasi Hapus Kategori',
                `Apakah Anda yakin ingin menghapus kategori <strong>"${category.name}"</strong>?<br><br><span class="text-red-500 font-semibold">⚠️ Tindakan ini tidak dapat dibatalkan!</span>`,
                async () => {
                    actionLoading.value[category.id] = true
                    const loadingAlert = showLoadingAlert('Menghapus kategori...')
                    
                    try {
                        await router.delete(`/lms/categories/${category.id}`, {
                            onSuccess: () => {
                                loadingAlert.close()
                                showSuccessAlert('Kategori berhasil dihapus!')
                                loadCategories()
                            },
                            onError: (errors) => {
                                loadingAlert.close()
                                const errorMessage = Object.values(errors).flat().join(', ')
                                showErrorAlert(errorMessage || 'Terjadi kesalahan saat menghapus kategori')
                            }
                        })
                    } catch (error) {
                        loadingAlert.close()
                        console.error('Error deleting category:', error)
                        showErrorAlert('Terjadi kesalahan saat menghapus kategori')
                    } finally {
                        actionLoading.value[category.id] = false
                    }
                },
                {
                    html: true,
                    confirmButtonColor: '#EF4444',
                    icon: 'warning'
                }
            )
        }
        
        const closeModal = () => {
            showCreateModal.value = false
            showEditModal.value = false
            editingCategory.value = null
            form.name = ''
            form.description = ''
            form.status = 'active'
        }
        
        const formatDate = (dateString) => {
            return new Date(dateString).toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            })
        }
        
        return {
            showCreateModal,
            showEditModal,
            loading,
            actionLoading,
            form,
            filters,
            applyFilters,
            loadPage,
            createCategory,
            editCategory,
            updateCategory,
            toggleStatus,
            deleteCategory,
            closeModal,
            formatDate
        }
    }
}
</script>

<template>
    <AppLayout>
        <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
            <!-- Header -->
            <div class="bg-white/10 backdrop-blur-md border-b border-white/20">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-white">Kategori Pelatihan</h1>
                            <p class="text-gray-300 mt-1">Kelola kategori untuk mengorganisir materi pelatihan</p>
                        </div>
                        <button
                            @click="showCreateModal = true"
                            class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                        >
                            <i class="fas fa-plus mr-2"></i>
                            Tambah Kategori
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Search and Filter -->
                <div class="bg-white/10 backdrop-blur-md rounded-xl p-6 mb-8 border border-white/20">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Cari Kategori</label>
                            <input
                                v-model="filters.search"
                                type="text"
                                placeholder="Cari nama kategori..."
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                            <select
                                v-model="filters.status"
                                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Tidak Aktif</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button
                                @click="applyFilters"
                                class="w-full bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-300"
                            >
                                <i class="fas fa-search mr-2"></i>
                                Terapkan Filter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Categories Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="category in categories.data"
                        :key="category.id"
                        class="bg-white/10 backdrop-blur-md rounded-xl p-6 border border-white/20 hover:bg-white/15 transition-all duration-300 transform hover:scale-105"
                    >
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-xl font-semibold text-white mb-2">{{ category.name }}</h3>
                                <p class="text-gray-300 text-sm mb-3">{{ category.description || 'Tidak ada deskripsi' }}</p>
                                <div class="flex items-center space-x-4 text-sm">
                                    <span class="text-gray-400">
                                        <i class="fas fa-book mr-1"></i>
                                        {{ category.courses_count || 0 }} Kursus
                                    </span>
                                    <span
                                        :class="category.status === 'active' ? 'text-green-400' : 'text-red-400'"
                                        class="flex items-center"
                                    >
                                        <i :class="category.status === 'active' ? 'fas fa-check-circle' : 'fas fa-times-circle'" class="mr-1"></i>
                                        {{ category.status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-4 border-t border-white/20">
                            <div class="text-xs text-gray-400">
                                Dibuat: {{ formatDate(category.created_at) }}
                            </div>
                            <div class="flex space-x-2">
                                <button
                                    @click="editCategory(category)"
                                    :disabled="actionLoading[category.id]"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fas fa-edit mr-1"></i>
                                    Edit
                                </button>
                                <button
                                    @click="toggleStatus(category)"
                                    :disabled="actionLoading[category.id]"
                                    :class="category.status === 'active' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                                    class="text-white px-3 py-1 rounded text-sm transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="actionLoading[category.id]" class="flex items-center">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>
                                        Loading...
                                    </span>
                                    <span v-else>
                                        <i :class="category.status === 'active' ? 'fas fa-ban' : 'fas fa-check'" class="mr-1"></i>
                                        {{ category.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </span>
                                </button>
                                <button
                                    @click="deleteCategory(category)"
                                    :disabled="actionLoading[category.id]"
                                    class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fas fa-trash mr-1"></i>
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="categories.data.length === 0" class="text-center py-12">
                    <div class="bg-white/10 backdrop-blur-md rounded-xl p-8 border border-white/20">
                        <i class="fas fa-folder-open text-6xl text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-semibold text-white mb-2">Belum ada kategori</h3>
                        <p class="text-gray-300 mb-6">Mulai dengan membuat kategori pertama untuk mengorganisir materi pelatihan</p>
                        <button
                            @click="showCreateModal = true"
                            class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-300"
                        >
                            <i class="fas fa-plus mr-2"></i>
                            Buat Kategori Pertama
                        </button>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="categories.data.length > 0" class="mt-8">
                    <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-300">
                                Menampilkan {{ categories.from }} - {{ categories.to }} dari {{ categories.total }} kategori
                            </div>
                            <div class="flex space-x-2">
                                <button
                                    v-for="link in categories.links"
                                    :key="link.label"
                                    @click="loadPage(link.url)"
                                    :disabled="!link.url || link.active"
                                    :class="[
                                        'px-3 py-2 rounded text-sm font-medium transition-colors duration-200',
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : link.url
                                            ? 'bg-white/10 text-white hover:bg-white/20'
                                            : 'bg-white/5 text-gray-500 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                ></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create/Edit Modal -->
            <div v-if="showCreateModal || showEditModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
                <div class="bg-white/10 backdrop-blur-md rounded-xl p-6 w-full max-w-md mx-4 border border-white/20">
                    <h3 class="text-xl font-semibold text-white mb-4">
                        {{ showEditModal ? 'Edit Kategori' : 'Tambah Kategori Baru' }}
                    </h3>
                    
                    <form @submit.prevent="showEditModal ? updateCategory() : createCategory()">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Nama Kategori <span class="text-red-400">*</span></label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Masukkan nama kategori"
                                    :disabled="loading"
                                />
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi</label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Masukkan deskripsi kategori (opsional)"
                                    :disabled="loading"
                                ></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Status <span class="text-red-400">*</span></label>
                                <select
                                    v-model="form.status"
                                    required
                                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    :disabled="loading"
                                >
                                    <option value="active">Aktif</option>
                                    <option value="inactive">Tidak Aktif</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex space-x-3 mt-6">
                            <button
                                type="button"
                                @click="closeModal"
                                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors duration-200"
                                :disabled="loading"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="loading || !form.name.trim()"
                                class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white px-4 py-2 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="loading" class="flex items-center justify-center">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>
                                    Menyimpan...
                                </span>
                                <span v-else>{{ showEditModal ? 'Update' : 'Simpan' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Custom loading animation */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

/* SweetAlert2 custom styles */
:deep(.swal2-popup) {
    background: rgba(30, 41, 59, 0.95) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: white !important;
}

:deep(.swal2-title) {
    color: white !important;
}

:deep(.swal2-content) {
    color: #cbd5e1 !important;
}

:deep(.swal2-confirm) {
    background: linear-gradient(135deg, #3B82F6, #8B5CF6) !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3) !important;
}

:deep(.swal2-cancel) {
    background: linear-gradient(135deg, #EF4444, #DC2626) !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3) !important;
}

:deep(.swal2-toast) {
    background: rgba(30, 41, 59, 0.95) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}
</style> 