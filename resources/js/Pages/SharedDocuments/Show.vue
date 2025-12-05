<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-700 p-8 text-white shadow-2xl">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12 blur-xl"></div>
                
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-4xl font-bold mb-2 animate-fade-in-up">{{ document.title }}</h1>
                            <p class="text-blue-100 text-lg">{{ document.description || 'Dokumen kolaboratif' }}</p>
                        </div>
                        <div class="hidden md:flex items-center gap-4">
                            <div class="w-20 h-20 bg-white/20 rounded-2xl backdrop-blur-sm flex items-center justify-center">
                                <i class="fas fa-file-alt text-3xl text-white"></i>
                            </div>
                            <div class="flex gap-2">
                                <Link
                                    :href="route('shared-documents.index')"
                                    class="inline-flex items-center px-4 py-2 bg-white/20 backdrop-blur-sm rounded-xl text-white hover:bg-white/30 transition-all duration-300"
                                >
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Kembali
                                </Link>
                                <a
                                    :href="downloadUrl"
                                    download
                                    class="inline-flex items-center px-4 py-2 bg-white/20 backdrop-blur-sm rounded-xl text-white hover:bg-white/30 transition-all duration-300"
                                >
                                    <i class="fas fa-download mr-2"></i>
                                    Download
                                </a>
                                <button
                                    v-if="canEdit"
                                    @click="showShareModal = true"
                                    class="inline-flex items-center px-4 py-2 bg-white/20 backdrop-blur-sm rounded-xl text-white hover:bg-white/30 transition-all duration-300"
                                >
                                    <i class="fas fa-share mr-2"></i>
                                    Bagikan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Info -->
            <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-6 animate-fade-in-up animation-delay-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                            Informasi Dokumen
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-3 backdrop-blur-sm">
                                <i class="fas fa-user mr-3 text-blue-500"></i>
                                <span>Dibuat oleh: {{ document.creator.name }}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-3 backdrop-blur-sm">
                                <i class="fas fa-calendar mr-3 text-green-500"></i>
                                <span>{{ formatDate(document.created_at) }}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-3 backdrop-blur-sm">
                                <i class="fas fa-file mr-3 text-purple-500"></i>
                                <span>{{ document.filename }}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-3 backdrop-blur-sm">
                                <i class="fas fa-weight-hanging mr-3 text-orange-500"></i>
                                <span>{{ formatFileSize(document.file_size) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-shield-alt mr-2 text-green-500"></i>
                            Akses
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-3 backdrop-blur-sm">
                                <i class="fas fa-globe mr-3 text-blue-500"></i>
                                <span>{{ document.is_public ? 'Publik' : 'Private' }}</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-3 backdrop-blur-sm">
                                <i class="fas fa-users mr-3 text-purple-500"></i>
                                <span>{{ document.permissions.length }} user{{ document.permissions.length !== 1 ? 's' : '' }} dibagikan</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-code-branch mr-2 text-indigo-500"></i>
                            Versi
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-3 backdrop-blur-sm">
                                <i class="fas fa-layer-group mr-3 text-indigo-500"></i>
                                <span>{{ document.versions.length }} versi{{ document.versions.length !== 1 ? 's' : '' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="document.description" class="mt-6 p-4 bg-blue-50 rounded-xl border border-blue-200">
                    <h4 class="text-sm font-semibold text-gray-900 mb-2 flex items-center">
                        <i class="fas fa-align-left mr-2 text-blue-500"></i>
                        Deskripsi
                    </h4>
                    <p class="text-sm text-gray-700">{{ document.description }}</p>
                </div>
            </div>

            <!-- OnlyOffice Editor -->
            <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-6 animate-fade-in-up animation-delay-400">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-edit mr-2 text-blue-500"></i>
                        Editor Dokumen
                    </h3>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-600 bg-white/50 rounded-lg px-3 py-1 backdrop-blur-sm">
                            Mode: {{ canEdit ? 'Edit' : 'View' }}
                        </span>
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse shadow-lg"></div>
                        <a
                            :href="downloadUrl"
                            download
                            class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <i class="fas fa-download mr-1"></i>
                            Download
                        </a>
                    </div>
                </div>
                
                <!-- Document Viewer Container -->
                <div id="document-viewer" class="w-full rounded-xl overflow-hidden shadow-lg" style="height: 600px;">
                    <!-- OnlyOffice Editor (if available) -->
                    <div id="onlyoffice-editor" class="w-full h-full"></div>
                    
                    <!-- Fallback Viewer -->
                    <div id="fallback-viewer" class="w-full h-full hidden">
                        <div class="fallback-content w-full h-full bg-gray-100 rounded-xl flex items-center justify-center">
                            <div class="text-center">
                                <div class="w-16 h-16 mx-auto mb-4 bg-gray-200 rounded-full flex items-center justify-center">
                                    <i class="fas fa-file-alt text-2xl text-gray-400"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Preview Tidak Tersedia</h3>
                                <p class="text-sm text-gray-500 mb-4">Gunakan tombol download untuk membuka dokumen</p>
                                <a 
                                    :href="downloadUrl" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                    download
                                >
                                    <i class="fas fa-download mr-2"></i>
                                    Download Dokumen
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading State -->
                    <div id="loading-viewer" class="w-full h-full flex items-center justify-center bg-gray-50">
                        <div class="text-center">
                            <div class="w-8 h-8 mx-auto mb-4 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                            <p class="text-sm text-gray-600">Memuat editor...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Share Modal -->
        <div v-if="showShareModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm overflow-y-auto h-full w-full z-50 animate-fade-in">
            <div class="relative top-20 mx-auto p-8 border w-96 shadow-2xl rounded-2xl bg-white/90 backdrop-blur-xl border-white/20">
                <div class="mt-3">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-share mr-2 text-blue-500"></i>
                        Bagikan Dokumen
                    </h3>
                    
                    <!-- Share with Users -->
                    <div class="space-y-4">
                        <!-- Selected Users Display -->
                        <div v-if="selectedUsers.length > 0" class="space-y-2">
                            <div
                                v-for="(user, index) in selectedUsers"
                                :key="user.id"
                                class="flex items-center justify-between p-3 bg-blue-50 rounded-xl border border-blue-200"
                            >
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900">{{ user.nama_lengkap }}</div>
                                    <div class="text-sm text-gray-600">{{ user.email }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ user.divisi || 'Tidak ada divisi' }} • {{ user.jabatan || 'Tidak ada jabatan' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 ml-4">
                                    <select
                                        :value="getUserPermission(user.id)"
                                        @change="updateUserPermission(user.id, $event.target.value)"
                                        class="px-3 py-1 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-300 bg-white/50 backdrop-blur-sm"
                                    >
                                        <option value="view">View</option>
                                        <option value="edit">Edit</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="removeSelectedUser(user)"
                                        class="p-1 text-red-500 hover:text-red-700 transition-colors"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- User Search -->
                        <div class="relative">
                            <UserSearchDropdown
                                placeholder="Cari user berdasarkan nama, email, divisi, atau jabatan..."
                                :selected-users="selectedUsers"
                                :multiple="true"
                                @user-selected="addSelectedUser"
                                @user-removed="removeSelectedUser"
                            />
                        </div>

                        <!-- Help Text -->
                        <div class="text-xs text-gray-500 bg-gray-50 rounded-lg p-3">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                                <div>
                                    <p class="font-medium mb-1">Tips pencarian:</p>
                                    <ul class="space-y-1">
                                        <li>• Ketik nama lengkap user</li>
                                        <li>• Ketik email user</li>
                                        <li>• Ketik nama divisi</li>
                                        <li>• Ketik nama jabatan</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex justify-end space-x-3 mt-8">
                        <button
                            @click="showShareModal = false"
                            class="px-6 py-3 border border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-300"
                        >
                            Batal
                        </button>
                        <button
                            @click="shareDocument"
                            :disabled="shareForm.processing"
                            class="px-6 py-3 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 disabled:opacity-50 transition-all duration-300 transform hover:scale-105"
                        >
                            <i v-if="shareForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fas fa-share mr-2"></i>
                            Bagikan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import UserSearchDropdown from '@/Components/UserSearchDropdown.vue'

const props = defineProps({
    document: {
        type: Object,
        required: true
    },
    canEdit: {
        type: Boolean,
        required: true
    },
    canAdmin: {
        type: Boolean,
        required: true
    },
    onlyOfficeUrl: {
        type: String,
        required: true
    }
})

const showShareModal = ref(false)
const selectedUsers = ref([])
const editorInstance = ref(null)
const downloadUrl = ref('')

const shareForm = useForm({
    shared_users: []
})

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const addSelectedUser = (user) => {
    // Check if user already exists
    if (!selectedUsers.value.some(u => u.id === user.id)) {
        selectedUsers.value.push(user)
        // Add to form shared_users
        shareForm.shared_users.push({
            user_id: user.id,
            permission: 'view'
        })
    }
}

const removeSelectedUser = (user) => {
    // Remove from selected users
    selectedUsers.value = selectedUsers.value.filter(u => u.id !== user.id)
    // Remove from form shared_users
    shareForm.shared_users = shareForm.shared_users.filter(su => su.user_id !== user.id)
}

const getUserPermission = (userId) => {
    const sharedUser = shareForm.shared_users.find(su => su.user_id === userId)
    return sharedUser ? sharedUser.permission : 'view'
}

const updateUserPermission = (userId, permission) => {
    const sharedUser = shareForm.shared_users.find(su => su.user_id === userId)
    if (sharedUser) {
        sharedUser.permission = permission
    }
}

const shareDocument = () => {
    shareForm.post(route('shared-documents.share', props.document.id), {
        onSuccess: () => {
            showShareModal.value = false
        }
    })
}

onMounted(async () => {
    // Set download URL
    downloadUrl.value = `${window.location.origin}/shared-documents/${props.document.id}/download`
    
    // Show loading state
    showLoadingState()
    
    // Wait a bit to show loading state
    await new Promise(resolve => setTimeout(resolve, 1500))
    
    // Check if OnlyOffice is available
    const onlyOfficeAvailable = await checkOnlyOfficeAvailability()
    
    if (onlyOfficeAvailable && window.DocsAPI) {
        console.log('Initializing OnlyOffice editor with document:', props.document);
        
        const config = {
            document: {
                fileType: props.document.file_type,
                key: props.document.id.toString(),
                title: props.document.title,
                url: downloadUrl.value
            },
            documentType: getDocumentType(props.document.file_type),
            editorConfig: {
                mode: props.canEdit ? 'edit' : 'view',
                callbackUrl: `${window.location.origin}/shared-documents/${props.document.id}/callback`,
                user: {
                    id: window.userId || '1',
                    name: window.userName || 'User'
                },
                customization: {
                    chat: false,
                    comments: true,
                    compactToolbar: false,
                    feedback: false,
                    forcesave: true,
                    submitForm: false
                }
            },
            height: '600px',
            width: '100%'
        }

        console.log('OnlyOffice config:', config);
        
        try {
            editorInstance.value = new window.DocsAPI.DocEditor('onlyoffice-editor', config)
            hideLoadingState()
            showOnlyOfficeEditor()
        } catch (error) {
            console.error('OnlyOffice initialization failed:', error)
            hideLoadingState()
            showGoogleDocsViewer()
        }
    } else {
        console.log('OnlyOffice not available, using Google Docs Viewer');
        hideLoadingState()
        showGoogleDocsViewer()
    }
})

const showLoadingState = () => {
    const onlyofficeEditor = document.getElementById('onlyoffice-editor')
    const fallbackViewer = document.getElementById('fallback-viewer')
    const loadingViewer = document.getElementById('loading-viewer')
    
    if (onlyofficeEditor) onlyofficeEditor.classList.add('hidden')
    if (fallbackViewer) fallbackViewer.classList.add('hidden')
    if (loadingViewer) loadingViewer.classList.remove('hidden')
}

const hideLoadingState = () => {
    const loadingViewer = document.getElementById('loading-viewer')
    if (loadingViewer) loadingViewer.classList.add('hidden')
}

const showFallbackViewer = () => {
    const onlyofficeEditor = document.getElementById('onlyoffice-editor')
    const fallbackViewer = document.getElementById('fallback-viewer')
    const loadingViewer = document.getElementById('loading-viewer')
    
    if (onlyofficeEditor) onlyofficeEditor.classList.add('hidden')
    if (fallbackViewer) fallbackViewer.classList.remove('hidden')
    if (loadingViewer) loadingViewer.classList.add('hidden')
}

const showOnlyOfficeEditor = () => {
    const onlyofficeEditor = document.getElementById('onlyoffice-editor')
    const fallbackViewer = document.getElementById('fallback-viewer')
    const loadingViewer = document.getElementById('loading-viewer')
    
    if (onlyofficeEditor) onlyofficeEditor.classList.remove('hidden')
    if (fallbackViewer) fallbackViewer.classList.add('hidden')
    if (loadingViewer) loadingViewer.classList.add('hidden')
}

const checkOnlyOfficeAvailability = async () => {
    try {
        const response = await fetch(`${window.location.origin}/web-apps/apps/api/documents/api.js`, {
            method: 'HEAD',
            mode: 'no-cors'
        })
        return true
    } catch (error) {
        console.log('OnlyOffice server not available:', error)
        return false
    }
}

const showGoogleDocsViewer = () => {
    const onlyofficeEditor = document.getElementById('onlyoffice-editor')
    const fallbackViewer = document.getElementById('fallback-viewer')
    const loadingViewer = document.getElementById('loading-viewer')
    
    if (onlyofficeEditor) onlyofficeEditor.classList.add('hidden')
    if (fallbackViewer) fallbackViewer.classList.remove('hidden')
    if (loadingViewer) loadingViewer.classList.add('hidden')
    
    // Update fallback viewer with Google Docs Viewer
    const fallbackContent = fallbackViewer?.querySelector('.fallback-content')
    if (fallbackContent) {
        fallbackContent.innerHTML = `
            <div class="w-full h-full bg-gray-100 rounded-xl flex items-center justify-center">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-4 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-file-alt text-2xl text-blue-500"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Preview Dokumen</h3>
                    <p class="text-sm text-gray-500 mb-4">Menggunakan Google Docs Viewer</p>
                    <div class="space-y-2">
                        <a 
                            href="https://docs.google.com/viewer?url=${encodeURIComponent(downloadUrl.value)}&embedded=true"
                            target="_blank"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors mr-2"
                        >
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Buka di Tab Baru
                        </a>
                        <a 
                            href="${downloadUrl.value}" 
                            download
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                        >
                            <i class="fas fa-download mr-2"></i>
                            Download
                        </a>
                    </div>
                </div>
            </div>
        `
    }
}

onUnmounted(() => {
    if (editorInstance.value) {
        editorInstance.value.destroyEditor()
    }
})

const getDocumentType = (fileType) => {
    const excelTypes = ['xlsx', 'xls']
    const wordTypes = ['docx', 'doc']
    const powerpointTypes = ['pptx', 'ppt']

    if (excelTypes.includes(fileType)) return 'spreadsheet'
    if (wordTypes.includes(fileType)) return 'text'
    if (powerpointTypes.includes(fileType)) return 'presentation'
    return 'text'
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

@keyframes fade-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out forwards;
}

.animate-fade-in {
    animation: fade-in 0.3s ease-out forwards;
}

.animation-delay-200 {
    animation-delay: 200ms;
}

.animation-delay-400 {
    animation-delay: 400ms;
}

/* Glass effect enhancement */
.backdrop-blur-xl {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

.backdrop-blur-sm {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

/* Smooth transitions */
* {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style> 