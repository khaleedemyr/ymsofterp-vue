<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Kelola Permission Dokumen</h3>
                            <p class="text-sm text-gray-500">{{ documentTitle }}</p>
                        </div>
                        <button @click="close" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Permission Management -->
                    <div class="space-y-6">
                        <!-- Current Permissions -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-3">User dengan Akses</h4>
                            <div v-if="permissions.length > 0" class="space-y-2">
                                <div
                                    v-for="permission in permissions"
                                    :key="permission.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border"
                                >
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">{{ permission.user.nama_lengkap }}</div>
                                        <div class="text-sm text-gray-500">{{ permission.user.email }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <select
                                            :value="permission.permission"
                                            @change="updatePermission(permission.id, $event.target.value)"
                                            class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        >
                                            <option value="view">View</option>
                                            <option value="edit">Edit</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                        <button
                                            @click="removePermission(permission.id)"
                                            class="text-red-500 hover:text-red-700 p-1"
                                        >
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-4 text-gray-500">
                                Belum ada user yang memiliki akses khusus
                            </div>
                        </div>

                        <!-- Add New User -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-3">Tambah User Baru</h4>
                            <div class="space-y-4">
                                <!-- User Search -->
                                <div class="relative">
                                    <UserSearchDropdown
                                        placeholder="Cari user untuk ditambahkan..."
                                        :selected-users="[]"
                                        :multiple="false"
                                        @user-selected="addNewUser"
                                    />
                                </div>

                                <!-- Selected User for Permission -->
                                <div v-if="newUser" class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ newUser.nama_lengkap }}</div>
                                            <div class="text-sm text-gray-500">{{ newUser.email }}</div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <select
                                                v-model="newUserPermission"
                                                class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            >
                                                <option value="view">View</option>
                                                <option value="edit">Edit</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <button
                                                @click="addPermission"
                                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
                                            >
                                                Tambah
                                            </button>
                                            <button
                                                @click="cancelAddUser"
                                                class="text-gray-500 hover:text-gray-700"
                                            >
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Public Access Toggle -->
                        <div class="flex items-center p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                            <input
                                id="is_public"
                                v-model="isPublic"
                                type="checkbox"
                                class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                            >
                            <label for="is_public" class="ml-2 block text-sm text-gray-900">
                                <span class="font-medium">Dokumen Publik</span>
                                <span class="block text-xs text-gray-600">Semua user dapat mengakses dokumen ini</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button
                        @click="savePermissions"
                        :disabled="loading"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                    >
                        <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
                        <i v-else class="fas fa-save mr-2"></i>
                        Simpan Permission
                    </button>
                    <button
                        @click="close"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import UserSearchDropdown from '@/Components/UserSearchDropdown.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    documentId: {
        type: [String, Number],
        required: true
    },
    documentTitle: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['close', 'saved'])

const permissions = ref([])
const newUser = ref(null)
const newUserPermission = ref('view')
const isPublic = ref(false)
const loading = ref(false)

// Load permissions when modal opens
watch(() => props.show, (newVal) => {
    if (newVal) {
        loadPermissions()
    }
})

const loadPermissions = async () => {
    try {
        const response = await fetch(`/shared-documents/${props.documentId}/permissions`)
        const data = await response.json()
        
        if (data.success) {
            permissions.value = data.data
            isPublic.value = data.is_public || false
        }
    } catch (error) {
        console.error('Error loading permissions:', error)
    }
}

const updatePermission = (permissionId, newPermission) => {
    const permission = permissions.value.find(p => p.id === permissionId)
    if (permission) {
        permission.permission = newPermission
    }
}

const removePermission = (permissionId) => {
    permissions.value = permissions.value.filter(p => p.id !== permissionId)
}

const addNewUser = (user) => {
    // Check if user already has permission
    const existingPermission = permissions.value.find(p => p.user_id === user.id)
    if (existingPermission) {
        alert('User ini sudah memiliki akses ke dokumen')
        return
    }
    
    newUser.value = user
}

const cancelAddUser = () => {
    newUser.value = null
    newUserPermission.value = 'view'
}

const addPermission = () => {
    if (!newUser.value) return
    
    const newPermission = {
        id: 'temp_' + Date.now(),
        user_id: newUser.value.id,
        user: newUser.value,
        permission: newUserPermission.value
    }
    
    permissions.value.push(newPermission)
    cancelAddUser()
}

const savePermissions = async () => {
    loading.value = true
    
    try {
        const permissionData = permissions.value.map(p => ({
            user_id: p.user_id,
            permission: p.permission
        }))
        
        await router.post(`/shared-documents/${props.documentId}/permissions`, {
            permissions: permissionData,
            is_public: isPublic.value
        }, {
            onSuccess: () => {
                emit('saved')
                close()
            }
        })
    } catch (error) {
        console.error('Error saving permissions:', error)
    } finally {
        loading.value = false
    }
}

const close = () => {
    emit('close')
}
</script>
