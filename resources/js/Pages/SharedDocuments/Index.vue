<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 p-8 text-white shadow-2xl">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12 blur-xl"></div>
                
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-4xl font-bold mb-2 animate-fade-in-up">Dokumen Bersama</h1>
                            <p class="text-blue-100 text-lg">Kelola dan bagikan dokumen Excel, Word, dan PowerPoint secara real-time</p>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-20 h-20 bg-white/20 rounded-2xl backdrop-blur-sm flex items-center justify-center">
                                <i class="fas fa-file-alt text-3xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-6 animate-fade-in-up animation-delay-200">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1 relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 group-focus-within:text-blue-500 transition-colors"></i>
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari dokumen..."
                            class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 bg-white/50 backdrop-blur-sm"
                        >
                    </div>
                    <select
                        v-model="filterType"
                        class="px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 bg-white/50 backdrop-blur-sm"
                    >
                        <option value="">Semua Tipe</option>
                        <option value="xlsx">Excel</option>
                        <option value="docx">Word</option>
                        <option value="pptx">PowerPoint</option>
                    </select>
                    <Link
                        :href="route('shared-documents.create')"
                        class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Upload Dokumen
                    </Link>
                </div>
            </div>

            <!-- Documents Grid -->
            <div v-if="filteredDocuments.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-fade-in-up animation-delay-400">
                <div
                    v-for="(document, index) in filteredDocuments"
                    :key="document.id"
                    class="group bg-white/80 backdrop-blur-xl rounded-2xl shadow-lg hover:shadow-2xl border border-white/20 overflow-hidden transform hover:-translate-y-2 transition-all duration-500 animate-fade-in-up"
                    :style="{ animationDelay: `${index * 100}ms` }"
                >
                    <div class="p-6">
                        <!-- File Icon with 3D Effect -->
                        <div class="flex items-center mb-6">
                            <div class="relative">
                                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mr-4 shadow-lg transform group-hover:scale-110 transition-transform duration-300"
                                     :class="getFileTypeColor(document.file_type)">
                                    <div class="absolute inset-0 bg-white/20 rounded-2xl blur-sm"></div>
                                    <svg class="w-8 h-8 text-white relative z-10" fill="currentColor" viewBox="0 0 20 20">
                                        <path v-if="document.file_type === 'xlsx' || document.file_type === 'xls'" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        <path v-else-if="document.file_type === 'docx' || document.file_type === 'doc'" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        <path v-else d="M7 4V2a1 1 0 011-1h4a1 1 0 011 1v2h4a1 1 0 011 1v14a1 1 0 01-1 1H3a1 1 0 01-1-1V5a1 1 0 011-1h4z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-gray-900 truncate group-hover:text-blue-600 transition-colors">{{ document.title }}</h4>
                                <p class="text-sm text-gray-500">{{ document.filename }}</p>
                            </div>
                        </div>

                        <!-- Document Info with Glass Effect -->
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-2 backdrop-blur-sm">
                                <i class="fas fa-user mr-2 text-blue-500"></i>
                                {{ document.creator.name }}
                            </div>
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-2 backdrop-blur-sm">
                                <i class="fas fa-calendar mr-2 text-green-500"></i>
                                {{ formatDate(document.created_at) }}
                            </div>
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-2 backdrop-blur-sm">
                                <i class="fas fa-users mr-2 text-purple-500"></i>
                                {{ document.permissions.length }} user{{ document.permissions.length !== 1 ? 's' : '' }}
                            </div>
                        </div>

                        <!-- Actions with 3D Buttons -->
                        <div class="flex gap-2">
                            <Link
                                :href="route('shared-documents.show', document.id)"
                                class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-medium rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 text-sm"
                            >
                                <i class="fas fa-eye mr-1"></i>
                                Buka
                            </Link>
                            <button
                                v-if="canManagePermissions(document)"
                                @click="openPermissionModal(document)"
                                class="px-3 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 text-sm"
                                title="Kelola Permission"
                            >
                                <i class="fas fa-users"></i>
                            </button>
                            <button
                                v-if="document.created_by === $page.props.auth.user.id"
                                @click="deleteDocument(document.id)"
                                class="px-3 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 text-sm"
                                title="Hapus Dokumen"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State with Animation -->
            <div v-else class="text-center py-16 animate-fade-in-up animation-delay-600">
                <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-12">
                    <div class="w-24 h-24 mx-auto mb-6 bg-gradient-to-br from-blue-100 to-purple-100 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-file-alt text-3xl text-blue-500"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Tidak ada dokumen</h3>
                    <p class="text-gray-600 mb-8">Mulai dengan mengupload dokumen pertama Anda.</p>
                    <Link
                        :href="route('shared-documents.create')"
                        class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Upload Dokumen
                    </Link>
                </div>
            </div>
        </div>

        <!-- Permission Management Modal -->
        <DocumentPermissionModal
            :show="showPermissionModal"
            :document-id="selectedDocument?.id"
            :document-title="selectedDocument?.title"
            @close="closePermissionModal"
            @saved="onPermissionsSaved"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DocumentPermissionModal from '@/Components/DocumentPermissionModal.vue'

const props = defineProps({
    documents: {
        type: Array,
        required: true
    }
})

const search = ref('')
const filterType = ref('')
const showPermissionModal = ref(false)
const selectedDocument = ref(null)

const filteredDocuments = computed(() => {
    let filtered = props.documents

    if (search.value) {
        filtered = filtered.filter(doc => 
            doc.title.toLowerCase().includes(search.value.toLowerCase()) ||
            doc.filename.toLowerCase().includes(search.value.toLowerCase())
        )
    }

    if (filterType.value) {
        filtered = filtered.filter(doc => doc.file_type === filterType.value)
    }

    return filtered
})

const getFileTypeColor = (fileType) => {
    const colors = {
        'xlsx': 'bg-green-500',
        'xls': 'bg-green-500',
        'docx': 'bg-blue-500',
        'doc': 'bg-blue-500',
        'pptx': 'bg-orange-500',
        'ppt': 'bg-orange-500'
    }
    return colors[fileType] || 'bg-gray-500'
}

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const deleteDocument = (documentId) => {
    if (confirm('Apakah Anda yakin ingin menghapus dokumen ini?')) {
        router.delete(route('shared-documents.destroy', documentId))
    }
}

const canManagePermissions = (document) => {
    const user = props.$page?.props?.auth?.user
    if (!user) return false
    
    // Creator can always manage permissions
    if (document.created_by === user.id) return true
    
    // Check if user has admin permission
    const userPermission = document.permissions?.find(p => p.user_id === user.id)
    return userPermission?.permission === 'admin'
}

const openPermissionModal = (document) => {
    selectedDocument.value = document
    showPermissionModal.value = true
}

const closePermissionModal = () => {
    showPermissionModal.value = false
    selectedDocument.value = null
}

const onPermissionsSaved = () => {
    // Refresh the page to get updated permissions
    router.reload()
}
</script>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out forwards;
}

.animation-delay-200 {
    animation-delay: 200ms;
}

.animation-delay-400 {
    animation-delay: 400ms;
}

.animation-delay-600 {
    animation-delay: 600ms;
}

/* Glass effect enhancement */
.backdrop-blur-xl {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

/* 3D hover effects */
.group:hover .group-hover\:scale-110 {
    transform: scale(1.1);
}

.group:hover .group-hover\:-translate-y-2 {
    transform: translateY(-8px);
}

/* Smooth transitions */
* {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style> 