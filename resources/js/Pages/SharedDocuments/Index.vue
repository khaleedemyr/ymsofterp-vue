<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 p-8 text-white shadow-2xl">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12 blur-xl"></div>

                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-4xl font-bold mb-2 animate-fade-in-up">Dokumen Bersama</h1>
                            <p class="text-blue-100 text-lg">File explorer dokumen dengan folder dan ACL multi-scope</p>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-20 h-20 bg-white/20 rounded-2xl backdrop-blur-sm flex items-center justify-center">
                                <i class="fas fa-file-alt text-3xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-6 animate-fade-in-up animation-delay-200">
                <div class="flex flex-col lg:flex-row gap-4">
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
                        <option value="pdf">PDF</option>
                    </select>
                    <button
                        @click="openCreateFolderModal"
                        class="inline-flex items-center px-4 py-3 bg-white text-blue-700 font-semibold rounded-xl border border-blue-200 shadow hover:bg-blue-50 transition-all duration-300"
                    >
                        <i class="fas fa-folder-plus mr-2"></i>
                        Folder
                    </button>
                    <button
                        v-if="selectedFolder"
                        @click="openRenameFolderModal(selectedFolder)"
                        class="inline-flex items-center px-4 py-3 bg-white text-amber-700 font-semibold rounded-xl border border-amber-200 shadow hover:bg-amber-50 transition-all duration-300"
                    >
                        <i class="fas fa-pen mr-2"></i>
                        Rename Folder
                    </button>
                    <button
                        v-if="selectedFolder && selectedFolderCanManage"
                        @click="openPermissionModalForFolder(selectedFolder)"
                        class="inline-flex items-center px-4 py-3 bg-white text-green-700 font-semibold rounded-xl border border-green-200 shadow hover:bg-green-50 transition-all duration-300"
                    >
                        <i class="fas fa-shield-alt mr-2"></i>
                        ACL Folder
                    </button>
                    <button
                        v-if="selectedFolder && selectedFolderCanManage"
                        @click="openDeleteFolderModal(selectedFolder)"
                        class="inline-flex items-center px-4 py-3 bg-white text-red-700 font-semibold rounded-xl border border-red-200 shadow hover:bg-red-50 transition-all duration-300"
                    >
                        <i class="fas fa-folder-minus mr-2"></i>
                        Hapus Folder
                    </button>
                    <Link
                        :href="route('shared-documents.create')"
                        class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Upload Dokumen
                    </Link>
                </div>
            </div>

            <div v-if="!documentReadOnly && selectedDocumentCount > 0" class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-indigo-900 font-semibold">
                    {{ selectedDocumentCount }} dokumen dipilih
                </div>
                <div class="flex items-center gap-2">
                    <button @click="selectAllVisibleDocuments" class="px-3 py-2 text-xs rounded-lg border border-indigo-300 text-indigo-700 hover:bg-indigo-100">
                        Pilih Semua Terlihat
                    </button>
                    <button @click="clearSelectedDocuments" class="px-3 py-2 text-xs rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                        Clear
                    </button>
                    <button @click="openBulkMoveModal" class="px-3 py-2 text-xs rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                        Pindah Terpilih
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-4 lg:col-span-1">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Folder Explorer</h3>
                    <button
                        @click="openFolder(null)"
                        class="w-full text-left px-3 py-2 rounded-lg hover:bg-blue-50 transition"
                        :class="[
                            activeFolderId == null ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-700',
                            dragOverFolderId === 'root' ? 'ring-2 ring-indigo-400 bg-indigo-50' : '',
                            isTargetDropDisabled(null) ? 'opacity-60 cursor-not-allowed' : ''
                        ]"
                        @dragover.prevent="setDragOverFolder('root', null)"
                        @dragleave="clearDragOverFolder"
                        @drop.prevent="handleFolderDrop(null)"
                        @contextmenu.prevent="openContextMenu($event, 'folder', { id: null, name: 'Root' })"
                    >
                        <i class="fas fa-home mr-2"></i> Root
                        <span v-if="dragOverFolderId === 'root'" class="ml-2 text-[11px] text-indigo-700 font-semibold">
                            {{ targetDropLabel(null) }}
                        </span>
                    </button>
                    <div class="mt-2 space-y-1 max-h-[520px] overflow-auto pr-1">
                        <div
                            v-for="folder in visibleFolderTreeItems"
                            :key="folder.id"
                            class="flex items-center gap-1 rounded-lg transition text-sm"
                            :class="[
                                dragOverFolderId === String(folder.id) ? 'ring-2 ring-indigo-400 bg-indigo-50' : '',
                                isTargetDropDisabled(folder.id) ? 'opacity-60' : ''
                            ]"
                            :style="{ paddingLeft: `${(folder.depth * 18) + 8}px` }"
                            @dragover.prevent="setDragOverFolder(folder.id, folder.id)"
                            @dragleave="clearDragOverFolder"
                            @drop.prevent="handleFolderDrop(folder.id)"
                            @dragstart="handleFolderDragStart(folder)"
                            @dragend="handleFolderDragEnd"
                            draggable="true"
                        >
                            <button
                                v-if="hasChildren(folder.id)"
                                @click.stop="toggleFolderExpand(folder.id)"
                                class="w-6 h-6 text-gray-500 hover:text-gray-700 rounded hover:bg-gray-100 flex items-center justify-center"
                            >
                                <i
                                    class="fas"
                                    :class="isExpanded(folder.id) ? 'fa-chevron-down' : 'fa-chevron-right'"
                                ></i>
                            </button>
                            <span v-else class="w-6"></span>
                            <button
                                @click="openFolder(folder.id)"
                                class="flex-1 text-left px-2 py-2 rounded-lg hover:bg-blue-50 transition"
                                :class="String(activeFolderId) === String(folder.id) ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-700'"
                                @contextmenu.prevent="openContextMenu($event, 'folder', folder)"
                            >
                                <i class="fas fa-folder mr-2 text-amber-500"></i>
                                {{ folder.name }}
                                <span v-if="dragOverFolderId === String(folder.id)" class="ml-2 text-[11px] text-indigo-700 font-semibold">
                                    {{ targetDropLabel(folder.id) }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-3 space-y-4">
                    <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <button
                                v-for="crumb in breadcrumbs"
                                :key="crumb.id ?? 'root'"
                                @click="openFolder(crumb.id)"
                                class="px-2 py-1 rounded hover:bg-gray-100 text-gray-700"
                            >
                                {{ crumb.name }}
                            </button>
                        </div>
                    </div>

                    <div v-if="filteredDocuments.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in-up animation-delay-400">
                        <div
                            v-for="(document, index) in filteredDocuments"
                            :key="document.id"
                            class="group bg-white/80 backdrop-blur-xl rounded-2xl shadow-lg hover:shadow-2xl border border-white/20 overflow-hidden transform hover:-translate-y-2 transition-all duration-500 animate-fade-in-up"
                            :style="{ animationDelay: `${index * 100}ms` }"
                            :draggable="!documentReadOnly"
                            @dragstart="!documentReadOnly && handleDocumentDragStart(document)"
                            @dragend="!documentReadOnly && handleDocumentDragEnd"
                            :class="draggedDocumentId === document.id ? 'ring-2 ring-indigo-400 bg-indigo-50' : ''"
                            @contextmenu.prevent="openContextMenu($event, 'document', document)"
                        >
                            <div class="p-6">
                                <div v-if="!documentReadOnly" class="flex justify-end mb-2">
                                    <input
                                        type="checkbox"
                                        :checked="isDocumentSelected(document.id)"
                                        @change="handleDocumentSelectionChange($event, document.id, index)"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                </div>
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

                                <div class="space-y-2 mb-6">
                                    <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-2 backdrop-blur-sm">
                                        <i class="fas fa-user mr-2 text-blue-500"></i>
                                        {{ document.creator?.nama_lengkap || document.creator?.name || '-' }}
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-2 backdrop-blur-sm">
                                        <i class="fas fa-calendar mr-2 text-green-500"></i>
                                        {{ formatDate(document.created_at) }}
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600 bg-white/30 rounded-lg p-2 backdrop-blur-sm">
                                        <i class="fas fa-folder mr-2 text-yellow-500"></i>
                                        {{ document.folder?.name || 'Root' }}
                                    </div>
                                </div>

                                <div class="flex gap-2 flex-wrap">
                                    <Link
                                        :href="route('shared-documents.show', document.id)"
                                        class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-medium rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 text-sm"
                                    >
                                        <i class="fas fa-eye mr-1"></i>
                                        Buka
                                    </Link>
                                    <a
                                        :href="route('shared-documents.download', document.id)"
                                        class="px-3 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 text-sm"
                                        title="Download Dokumen"
                                    >
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-16 animate-fade-in-up animation-delay-600 bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20">
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
        </div>

        <DocumentPermissionModal
            :show="showPermissionModal"
            :resource-type="permissionTarget?.type || 'document'"
            :resource-id="permissionTarget?.id || 0"
            :resource-title="permissionTarget?.title || ''"
            @close="closePermissionModal"
            @saved="onPermissionsSaved"
        />

        <div class="fixed top-20 right-4 z-50 flex flex-col gap-2">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="min-w-[260px] max-w-sm rounded-lg px-4 py-3 text-sm shadow-lg border-l-4 bg-white"
                :class="toast.type === 'success' ? 'border-green-500 text-green-800' : 'border-red-500 text-red-700'"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="font-semibold">{{ toast.title }}</div>
                    <button @click="removeToast(toast.id)" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="mt-1 text-gray-700">{{ toast.message }}</p>
            </div>
        </div>

        <div v-if="showCreateFolderModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/40" @click="closeCreateFolderModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Buat Folder Baru</h3>
                <input
                    v-model="createFolderName"
                    type="text"
                    placeholder="Nama folder"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl"
                >
                <label class="flex items-center text-sm text-gray-700">
                    <input v-model="createFolderIsPublic" type="checkbox" class="mr-2">
                    Folder publik
                </label>
                <div class="flex justify-end gap-2">
                    <button @click="closeCreateFolderModal" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Batal</button>
                    <button @click="submitCreateFolder" class="px-4 py-2 rounded-lg bg-blue-600 text-white">Simpan</button>
                </div>
            </div>
        </div>

        <div v-if="showRenameFolderModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/40" @click="closeRenameFolderModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Rename Folder</h3>
                <input
                    v-model="renameFolderName"
                    type="text"
                    placeholder="Nama folder baru"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl"
                >
                <div class="flex justify-end gap-2">
                    <button @click="closeRenameFolderModal" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Batal</button>
                    <button @click="submitRenameFolder" class="px-4 py-2 rounded-lg bg-amber-600 text-white">Update</button>
                </div>
            </div>
        </div>

        <div v-if="showMoveDocumentModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/40" @click="closeMoveDocumentModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Pindah Dokumen</h3>
                <p class="text-sm text-gray-600">{{ moveDocumentTarget?.title }}</p>
                <select v-model="moveTargetFolderId" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    <option :value="null">Root</option>
                    <option v-for="folder in props.folders" :key="folder.id" :value="folder.id">
                        {{ folder.name }}
                    </option>
                </select>
                <div class="flex justify-end gap-2">
                    <button @click="closeMoveDocumentModal" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Batal</button>
                    <button @click="submitMoveDocument" class="px-4 py-2 rounded-lg bg-amber-600 text-white">Pindah</button>
                </div>
            </div>
        </div>

        <div v-if="showBulkMoveModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/40" @click="closeBulkMoveModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Pindah Dokumen Terpilih</h3>
                <p class="text-sm text-gray-600">{{ selectedDocumentCount }} dokumen akan dipindahkan</p>
                <select v-model="bulkMoveTargetFolderId" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    <option :value="null">Root</option>
                    <option v-for="folder in props.folders" :key="folder.id" :value="folder.id">
                        {{ folder.name }}
                    </option>
                </select>
                <div class="flex justify-end gap-2">
                    <button @click="closeBulkMoveModal" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Batal</button>
                    <button @click="submitBulkMoveDocuments" class="px-4 py-2 rounded-lg bg-indigo-600 text-white">Pindah</button>
                </div>
            </div>
        </div>

        <div v-if="showDeleteFolderModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/40" @click="closeDeleteFolderModal"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Hapus Folder</h3>
                <p class="text-sm text-gray-600">
                    Folder <span class="font-semibold">{{ deleteFolderTarget?.name }}</span> akan dihapus.
                </p>

                <div class="space-y-2">
                    <label class="flex items-center text-sm text-gray-700">
                        <input v-model="deleteFolderMode" value="move_to_root" type="radio" class="mr-2">
                        Pindahkan isi folder ke Root
                    </label>
                    <label class="flex items-center text-sm text-gray-700">
                        <input v-model="deleteFolderMode" value="move_to_folder" type="radio" class="mr-2">
                        Pindahkan isi folder ke folder lain
                    </label>
                </div>

                <div v-if="deleteFolderMode === 'move_to_folder'">
                    <select v-model="deleteFolderTargetFolderId" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                        <option :value="null">Pilih folder tujuan</option>
                        <option v-for="folder in deleteFolderDestinationOptions" :key="folder.id" :value="folder.id">
                            {{ folder.name }}
                        </option>
                    </select>
                </div>

                <div class="flex justify-end gap-2">
                    <button @click="closeDeleteFolderModal" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Batal</button>
                    <button @click="submitDeleteFolder" class="px-4 py-2 rounded-lg bg-red-600 text-white">Hapus Folder</button>
                </div>
            </div>
        </div>

        <div
            v-if="contextMenu.visible"
            class="fixed z-[60] min-w-[200px] rounded-lg border border-gray-200 bg-white shadow-xl p-1"
            :style="{ top: `${contextMenu.y}px`, left: `${contextMenu.x}px` }"
        >
            <button
                v-for="item in contextMenuItems"
                :key="item.key"
                @click="item.action"
                class="w-full text-left px-3 py-2 text-sm rounded hover:bg-gray-100 text-gray-700"
            >
                <i :class="item.icon" class="mr-2"></i>
                {{ item.label }}
            </button>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DocumentPermissionModal from '@/Components/DocumentPermissionModal.vue'

const props = defineProps({
    documents: {
        type: Array,
        required: true
    },
    folders: {
        type: Array,
        default: () => []
    },
    selectedFolderId: {
        type: [Number, String],
        default: null
    },
    folderTreeItems: {
        type: Array,
        default: () => []
    },
    breadcrumbs: {
        type: Array,
        default: () => [{ id: null, name: 'Root' }]
    },
    selectedFolder: {
        type: Object,
        default: null
    },
    selectedFolderCanManage: {
        type: Boolean,
        default: false
    }
})

const search = ref('')
const filterType = ref('')
const showPermissionModal = ref(false)
const permissionTarget = ref(null)
const activeFolderId = ref(props.selectedFolderId ?? null)
const expandedFolderIds = ref(new Set())
const dragOverFolderId = ref(null)
const draggedDocumentId = ref(null)
const showCreateFolderModal = ref(false)
const showRenameFolderModal = ref(false)
const showMoveDocumentModal = ref(false)
const createFolderName = ref('')
const createFolderIsPublic = ref(false)
const renameFolderName = ref('')
const renameFolderTarget = ref(null)
const moveDocumentTarget = ref(null)
const moveTargetFolderId = ref(null)
const showBulkMoveModal = ref(false)
const bulkMoveTargetFolderId = ref(null)
const showDeleteFolderModal = ref(false)
const deleteFolderTarget = ref(null)
const deleteFolderMode = ref('move_to_root')
const deleteFolderTargetFolderId = ref(null)
const toasts = ref([])
const selectedDocumentIds = ref(new Set())
const lastSelectedDocumentIndex = ref(null)
const draggedFolderId = ref(null)
const contextMenu = ref({
    visible: false,
    x: 0,
    y: 0,
    type: null,
    payload: null
})
const page = usePage()
const documentReadOnly = true

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

const openFolder = (folderId) => {
    activeFolderId.value = folderId
}

const selectedDocumentCount = computed(() => selectedDocumentIds.value.size)

const isDocumentSelected = (documentId) => selectedDocumentIds.value.has(String(documentId))

const setDocumentSelection = (documentId, checked) => {
    if (documentReadOnly) return
    const next = new Set(selectedDocumentIds.value)
    const normalizedId = String(documentId)
    if (checked) {
        next.add(normalizedId)
    } else {
        next.delete(normalizedId)
    }
    selectedDocumentIds.value = next
}

const handleDocumentSelectionChange = (event, documentId, index) => {
    if (documentReadOnly) return
    const checked = event.target.checked

    if (event.shiftKey && lastSelectedDocumentIndex.value !== null) {
        const start = Math.min(lastSelectedDocumentIndex.value, index)
        const end = Math.max(lastSelectedDocumentIndex.value, index)
        for (let i = start; i <= end; i += 1) {
            const targetDocument = filteredDocuments.value[i]
            if (targetDocument) {
                setDocumentSelection(targetDocument.id, checked)
            }
        }
    } else {
        setDocumentSelection(documentId, checked)
    }

    lastSelectedDocumentIndex.value = index
}

const clearSelectedDocuments = () => {
    if (documentReadOnly) return
    selectedDocumentIds.value = new Set()
    lastSelectedDocumentIndex.value = null
}

const selectAllVisibleDocuments = () => {
    if (documentReadOnly) return
    const next = new Set(selectedDocumentIds.value)
    filteredDocuments.value.forEach((document) => {
        next.add(String(document.id))
    })
    selectedDocumentIds.value = next
}

const addToast = (message, type = 'success', title = null) => {
    const id = `${Date.now()}-${Math.random()}`
    toasts.value.push({
        id,
        type,
        title: title || (type === 'success' ? 'Berhasil' : 'Gagal'),
        message
    })

    setTimeout(() => removeToast(id), 3800)
}

const removeToast = (id) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
}

watch(activeFolderId, (newFolderId) => {
    router.get(route('shared-documents.index'), { folder_id: newFolderId }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
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
    if (document.can_manage_permissions) return true

    const user = page.props?.auth?.user
    if (!user) return false
    
    // Creator can always manage permissions
    if (document.created_by === user.id) return true
    
    // Check if user has admin permission
    const userPermission = document.permissions?.find(p => p.user_id === user.id)
    return userPermission?.permission === 'admin'
}

const canMoveDocument = (document) => {
    if (document.can_move) return true
    return document.created_by === page.props?.auth?.user?.id
}

const openCreateFolderModal = () => {
    createFolderName.value = ''
    createFolderIsPublic.value = false
    showCreateFolderModal.value = true
}

const closeCreateFolderModal = () => {
    showCreateFolderModal.value = false
}

const submitCreateFolder = () => {
    if (!createFolderName.value.trim()) {
        alert('Nama folder wajib diisi')
        return
    }

    router.post(route('shared-documents.folders.store'), {
        name: createFolderName.value.trim(),
        parent_id: activeFolderId.value,
        is_public: createFolderIsPublic.value,
        scope_permissions: []
    }, {
        onError: () => addToast('Folder gagal dibuat.', 'error')
    })
    closeCreateFolderModal()
}

const openRenameFolderModal = (folder = null) => {
    const targetFolder = folder || props.selectedFolder
    if (!targetFolder) return
    renameFolderName.value = targetFolder.name
    renameFolderTarget.value = targetFolder
    showRenameFolderModal.value = true
}

const closeRenameFolderModal = () => {
    showRenameFolderModal.value = false
    renameFolderTarget.value = null
}

const submitRenameFolder = () => {
    if (!renameFolderTarget.value?.id) return
    const newName = renameFolderName.value.trim()
    if (!newName || newName === renameFolderTarget.value.name) {
        closeRenameFolderModal()
        return
    }

    router.patch(route('shared-documents.folders.rename', renameFolderTarget.value.id), {
        name: newName
    }, {
        onError: () => addToast('Rename folder gagal.', 'error')
    })
    closeRenameFolderModal()
}

const openDeleteFolderModal = (folder) => {
    if (!folder?.id) return
    deleteFolderTarget.value = folder
    deleteFolderMode.value = 'move_to_root'
    deleteFolderTargetFolderId.value = null
    showDeleteFolderModal.value = true
}

const closeDeleteFolderModal = () => {
    showDeleteFolderModal.value = false
    deleteFolderTarget.value = null
}

const submitDeleteFolder = () => {
    if (!deleteFolderTarget.value?.id) return

    if (deleteFolderMode.value === 'move_to_folder' && !deleteFolderTargetFolderId.value) {
        addToast('Pilih folder tujuan sebelum hapus folder.', 'error')
        return
    }

    router.delete(route('shared-documents.folders.delete', deleteFolderTarget.value.id), {
        data: {
            mode: deleteFolderMode.value,
            target_folder_id: deleteFolderMode.value === 'move_to_folder' ? deleteFolderTargetFolderId.value : null,
        },
        onError: () => addToast('Hapus folder gagal.', 'error'),
        onSuccess: () => addToast('Folder berhasil dihapus.')
    })
    closeDeleteFolderModal()
}

const openPermissionModalForDocument = (document) => {
    permissionTarget.value = {
        type: 'document',
        id: document.id,
        title: document.title
    }
    showPermissionModal.value = true
}

const openPermissionModalForFolder = (folder) => {
    permissionTarget.value = {
        type: 'folder',
        id: folder.id,
        title: folder.name
    }
    showPermissionModal.value = true
}

const openMoveDocumentModal = (document) => {
    if (documentReadOnly) return
    moveDocumentTarget.value = document
    moveTargetFolderId.value = document.folder_id ?? null
    showMoveDocumentModal.value = true
}

const closeMoveDocumentModal = () => {
    showMoveDocumentModal.value = false
    moveDocumentTarget.value = null
}

const submitMoveDocument = () => {
    if (!moveDocumentTarget.value) return
    router.patch(route('shared-documents.move', moveDocumentTarget.value.id), {
        target_folder_id: moveTargetFolderId.value
    }, {
        onError: () => addToast('Pindah dokumen gagal.', 'error')
    })
    closeMoveDocumentModal()
}

const openBulkMoveModal = () => {
    if (documentReadOnly) return
    if (selectedDocumentIds.value.size === 0) return
    bulkMoveTargetFolderId.value = null
    showBulkMoveModal.value = true
}

const closeBulkMoveModal = () => {
    showBulkMoveModal.value = false
}

const submitBulkMoveDocuments = () => {
    if (documentReadOnly) return
    if (selectedDocumentIds.value.size === 0) {
        closeBulkMoveModal()
        return
    }

    router.patch(route('shared-documents.bulk-move'), {
        document_ids: Array.from(selectedDocumentIds.value).map(Number),
        target_folder_id: bulkMoveTargetFolderId.value
    }, {
        onError: () => addToast('Bulk move dokumen gagal.', 'error'),
        onSuccess: () => {
            addToast('Dokumen terpilih berhasil dipindahkan.')
            clearSelectedDocuments()
        }
    })
    closeBulkMoveModal()
}

const hasChildren = (folderId) => {
    return props.folderTreeItems.some((folder) => String(folder.parent_id) === String(folderId))
}

const isExpanded = (folderId) => expandedFolderIds.value.has(String(folderId))

const toggleFolderExpand = (folderId) => {
    const normalizedId = String(folderId)
    const updated = new Set(expandedFolderIds.value)
    if (updated.has(normalizedId)) {
        updated.delete(normalizedId)
    } else {
        updated.add(normalizedId)
    }
    expandedFolderIds.value = updated
}

const setDragOverFolder = (folderId, targetFolderId) => {
    if (documentReadOnly) return
    if (isTargetDropDisabled(targetFolderId)) {
        dragOverFolderId.value = null
        return
    }
    dragOverFolderId.value = folderId === null ? 'root' : String(folderId)
}

const clearDragOverFolder = () => {
    if (documentReadOnly) return
    dragOverFolderId.value = null
}

const handleDocumentDragStart = (document) => {
    if (documentReadOnly) return
    draggedDocumentId.value = document.id
}

const handleDocumentDragEnd = () => {
    if (documentReadOnly) return
    draggedDocumentId.value = null
    dragOverFolderId.value = null
}

const handleFolderDragStart = (folder) => {
    draggedFolderId.value = folder.id
}

const handleFolderDragEnd = () => {
    draggedFolderId.value = null
}

const dropDocumentToFolder = (folderId) => {
    if (documentReadOnly) return
    if (!draggedDocument.value) return
    const draggedDoc = draggedDocument.value
    if (!draggedDoc) return

    if (String(draggedDoc.folder_id ?? '') === String(folderId ?? '')) {
        handleDocumentDragEnd()
        return
    }

    router.patch(route('shared-documents.move', draggedDoc.id), {
        target_folder_id: folderId
    }, {
        onError: () => addToast('Drop dokumen gagal.', 'error')
    })
    handleDocumentDragEnd()
}

const handleFolderDrop = (targetFolderId) => {
    if (documentReadOnly) return
    if (draggedFolderId.value) {
        dropFolderToFolder(targetFolderId)
        return
    }

    if (draggedDocumentId.value) {
        dropDocumentToFolder(targetFolderId)
    }
}

const dropFolderToFolder = (targetFolderId) => {
    if (!draggedFolderId.value) return

    if (isFolderDropDisabled(targetFolderId)) {
        handleFolderDragEnd()
        return
    }

    router.patch(route('shared-documents.folders.move', draggedFolderId.value), {
        target_parent_id: targetFolderId
    }, {
        onError: () => addToast('Pindah folder gagal.', 'error')
    })
    handleFolderDragEnd()
}

const closePermissionModal = () => {
    showPermissionModal.value = false
    permissionTarget.value = null
}

const onPermissionsSaved = () => {
    // Refresh the page to get updated permissions
    router.reload()
}

const parentMap = computed(() => {
    const map = new Map()
    props.folderTreeItems.forEach((folder) => {
        map.set(String(folder.id), folder.parent_id ? String(folder.parent_id) : null)
    })
    return map
})

const visibleFolderTreeItems = computed(() => {
    return props.folderTreeItems.filter((folder) => {
        let parentId = folder.parent_id ? String(folder.parent_id) : null
        while (parentId) {
            if (!expandedFolderIds.value.has(parentId)) return false
            parentId = parentMap.value.get(parentId) ?? null
        }
        return true
    })
})

const isDescendantOfFolder = (candidateId, ancestorId) => {
    let cursor = candidateId ? String(candidateId) : null
    const normalizedAncestorId = ancestorId ? String(ancestorId) : null
    while (cursor) {
        if (cursor === normalizedAncestorId) return true
        cursor = parentMap.value.get(cursor) ?? null
    }
    return false
}

const deleteFolderDestinationOptions = computed(() => {
    if (!deleteFolderTarget.value?.id) return props.folders

    const targetFolderId = String(deleteFolderTarget.value.id)
    return props.folders.filter((folder) => {
        const candidateId = String(folder.id)
        if (candidateId === targetFolderId) return false
        return !isDescendantOfFolder(candidateId, targetFolderId)
    })
})

const draggedDocument = computed(() => {
    return props.documents.find((document) => document.id === draggedDocumentId.value) || null
})

const isDropDisabled = (targetFolderId) => {
    if (!draggedDocument.value) return false
    return String(draggedDocument.value.folder_id ?? '') === String(targetFolderId ?? '')
}

const isFolderDropDisabled = (targetFolderId) => {
    if (!draggedFolderId.value) return false

    const sourceId = String(draggedFolderId.value)
    const targetId = targetFolderId === null ? null : String(targetFolderId)
    if (targetId === sourceId) return true

    let cursor = targetId
    while (cursor) {
        if (cursor === sourceId) return true
        cursor = parentMap.value.get(cursor) ?? null
    }

    return false
}

const isTargetDropDisabled = (targetFolderId) => {
    if (documentReadOnly) return true
    if (draggedFolderId.value) return isFolderDropDisabled(targetFolderId)
    if (draggedDocument.value) return isDropDisabled(targetFolderId)
    return false
}

const targetDropLabel = (targetFolderId) => {
    if (documentReadOnly) return 'Mode read-only'
    if (draggedFolderId.value && isFolderDropDisabled(targetFolderId)) return 'Posisi tidak valid'
    if (draggedDocument.value && isDropDisabled(targetFolderId)) return 'Folder sama'
    return 'Drop di sini'
}

const openContextMenu = (event, type, payload) => {
    closeContextMenu()
    contextMenu.value = {
        visible: true,
        x: event.clientX,
        y: event.clientY,
        type,
        payload
    }
}

const closeContextMenu = () => {
    contextMenu.value.visible = false
}

const contextMenuItems = computed(() => {
    if (!contextMenu.value.visible || !contextMenu.value.type) return []

    if (contextMenu.value.type === 'document') {
        const document = contextMenu.value.payload
        const items = [
            { key: 'open', label: 'Buka Dokumen', icon: 'fas fa-eye', action: () => { closeContextMenu(); window.location.href = route('shared-documents.show', document.id) } },
            { key: 'download', label: 'Download Dokumen', icon: 'fas fa-download', action: () => { closeContextMenu(); window.location.href = route('shared-documents.download', document.id) } },
        ]

        return items
    }

    if (contextMenu.value.type === 'folder') {
        const folder = contextMenu.value.payload
        const items = [
            { key: 'open', label: 'Buka Folder', icon: 'fas fa-folder-open', action: () => { closeContextMenu(); openFolder(folder.id) } },
        ]

        if (folder.id !== null) {
            items.push({ key: 'rename', label: 'Rename Folder', icon: 'fas fa-pen', action: () => { closeContextMenu(); openFolder(folder.id); openRenameFolderModal(folder) } })
            items.push({ key: 'acl', label: 'Kelola ACL Folder', icon: 'fas fa-shield-alt', action: () => { closeContextMenu(); openPermissionModalForFolder(folder) } })
            items.push({ key: 'delete', label: 'Hapus Folder', icon: 'fas fa-folder-minus', action: () => { closeContextMenu(); openDeleteFolderModal(folder) } })
        }

        return items
    }

    return []
})

const isTypingContext = (target) => {
    if (!target) return false
    const tagName = target.tagName?.toLowerCase()
    return tagName === 'input' || tagName === 'textarea' || tagName === 'select' || target.isContentEditable
}

const handleKeyboardShortcuts = (event) => {
    if (isTypingContext(event.target)) return

    if (documentReadOnly) return

    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
        event.preventDefault()
        selectAllVisibleDocuments()
        return
    }

    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'm') {
        if (selectedDocumentIds.value.size > 0) {
            event.preventDefault()
            openBulkMoveModal()
        }
        return
    }

    if (event.key === 'Delete') {
        if (props.selectedFolder && props.selectedFolderCanManage) {
            event.preventDefault()
            openDeleteFolderModal(props.selectedFolder)
        }
    }
}

watch(() => props.folderTreeItems, () => {
    const expanded = new Set()
    props.folderTreeItems.forEach((folder) => {
        if (folder.depth === 0) expanded.add(String(folder.id))
    })

    if (props.selectedFolderId) {
        let cursor = String(props.selectedFolderId)
        while (cursor) {
            expanded.add(cursor)
            cursor = parentMap.value.get(cursor) ?? null
        }
    }
    expandedFolderIds.value = expanded
}, { immediate: true })

watch(() => page.props.flash, (flash) => {
    if (flash?.success) addToast(flash.success, 'success')
    if (flash?.error) addToast(flash.error, 'error')
}, { immediate: true, deep: true })

onMounted(() => {
    window.addEventListener('click', closeContextMenu)
    window.addEventListener('scroll', closeContextMenu, true)
    window.addEventListener('keydown', handleKeyboardShortcuts)
})

onBeforeUnmount(() => {
    window.removeEventListener('click', closeContextMenu)
    window.removeEventListener('scroll', closeContextMenu, true)
    window.removeEventListener('keydown', handleKeyboardShortcuts)
})
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