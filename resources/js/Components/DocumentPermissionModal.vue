<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[200] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close"></div>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Kelola Permission {{ resourceTypeLabel }}</h3>
                                <p class="text-sm text-gray-500">{{ resourceTitle }}</p>
                            </div>
                            <button @click="close" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <div class="space-y-6">
                            <div v-if="isDocumentResource">
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
                                                class="px-3 py-1 text-sm border border-gray-300 rounded-md"
                                            >
                                                <option value="view">View</option>
                                                <option value="edit">Edit</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <button @click="removePermission(permission.id)" class="text-red-500 hover:text-red-700 p-1">
                                                <i class="fas fa-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-3 text-gray-500 text-sm">
                                    Belum ada user spesifik
                                </div>
                            </div>

                            <div v-if="isDocumentResource">
                                <h4 class="text-md font-medium text-gray-900 mb-3">Tambah User Baru</h4>
                                <div class="space-y-4">
                                    <UserSearchDropdown
                                        placeholder="Cari user untuk ditambahkan..."
                                        :selected-users="[]"
                                        :multiple="false"
                                        @user-selected="addNewUser"
                                    />

                                    <div v-if="newUser" class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="font-medium text-gray-900">{{ newUser.nama_lengkap }}</div>
                                                <div class="text-sm text-gray-500">{{ newUser.email }}</div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <select v-model="newUserPermission" class="px-3 py-1 text-sm border border-gray-300 rounded-md">
                                                    <option value="view">View</option>
                                                    <option value="edit">Edit</option>
                                                    <option value="admin">Admin</option>
                                                </select>
                                                <button @click="addPermission" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                                                    Tambah
                                                </button>
                                                <button @click="cancelAddUser" class="text-gray-500 hover:text-gray-700">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="text-md font-medium text-gray-900 mb-3">Akses Scope Organisasi</h4>
                                <div class="grid md:grid-cols-4 gap-2 mb-3">
                                    <select v-model="newScope.scope_type" class="px-3 py-2 text-sm border border-gray-300 rounded-md">
                                        <option value="user">User</option>
                                        <option value="jabatan">Jabatan</option>
                                        <option value="divisi">Divisi</option>
                                        <option value="outlet">Outlet</option>
                                    </select>
                                    <select v-model="newScope.scope_id" class="px-3 py-2 text-sm border border-gray-300 rounded-md md:col-span-2">
                                        <option :value="null">Pilih {{ newScope.scope_type }}</option>
                                        <option
                                            v-for="option in currentScopeOptions"
                                            :key="option.id"
                                            :value="option.id"
                                        >
                                            {{ option.name }}
                                        </option>
                                    </select>
                                    <select v-model="newScope.permission" class="px-3 py-2 text-sm border border-gray-300 rounded-md">
                                        <option value="view">View</option>
                                        <option value="edit">Edit</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <button @click="addScopePermission" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                                    Tambah Scope
                                </button>

                                <div v-if="scopePermissions.length > 0" class="space-y-2 mt-3">
                                    <div
                                        v-for="scope in scopePermissions"
                                        :key="scope.id"
                                        class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg border border-indigo-200"
                                    >
                                        <div class="text-sm">
                                            <span class="font-semibold text-indigo-900">{{ scope.scope_type }}</span>
                                            <span class="text-gray-700"> - {{ getScopeName(scope.scope_type, scope.scope_id) }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <select
                                                :value="scope.permission"
                                                @change="updateScopePermission(scope.id, $event.target.value)"
                                                class="px-3 py-1 text-sm border border-gray-300 rounded-md"
                                            >
                                                <option value="view">View</option>
                                                <option value="edit">Edit</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <button @click="removeScopePermission(scope.id)" class="text-red-500 hover:text-red-700 p-1">
                                                <i class="fas fa-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-gray-500 mt-3">
                                    Belum ada scope organisasi
                                </div>
                            </div>

                            <div class="flex items-center p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                                <input
                                    id="is_public"
                                    v-model="isPublic"
                                    type="checkbox"
                                    class="h-4 w-4 text-yellow-600 border-gray-300 rounded"
                                >
                                <label for="is_public" class="ml-2 block text-sm text-gray-900">
                                    <span class="font-medium">{{ resourceTypeLabel }} Publik</span>
                                    <span class="block text-xs text-gray-600">Semua user dapat melihat {{ resourceTypeLabel.toLowerCase() }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            @click="savePermissions"
                            :disabled="loading"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 sm:ml-3 sm:w-auto disabled:opacity-50"
                        >
                            <i v-if="loading" class="fas fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fas fa-save mr-2"></i>
                            Simpan Permission
                        </button>
                        <button
                            @click="close"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import UserSearchDropdown from '@/Components/UserSearchDropdown.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    resourceType: { type: String, default: 'document' },
    resourceId: { type: [String, Number], required: true },
    resourceTitle: { type: String, default: '' },
})

const emit = defineEmits(['close', 'saved'])

const permissions = ref([])
const scopePermissions = ref([])
const scopeOptions = ref({ users: [], jabatans: [], divisis: [], outlets: [] })
const newUser = ref(null)
const newUserPermission = ref('view')
const isPublic = ref(false)
const loading = ref(false)

const newScope = ref({
    scope_type: 'jabatan',
    scope_id: null,
    permission: 'view',
})

const isDocumentResource = computed(() => props.resourceType === 'document')
const resourceTypeLabel = computed(() => (isDocumentResource.value ? 'Dokumen' : 'Folder'))

const currentScopeOptions = computed(() => {
    if (newScope.value.scope_type === 'user') {
        return (scopeOptions.value.users || []).map((item) => ({ id: item.id, name: item.nama_lengkap }))
    }

    if (newScope.value.scope_type === 'jabatan') {
        return (scopeOptions.value.jabatans || []).map((item) => ({ id: item.id_jabatan, name: item.nama_jabatan }))
    }

    if (newScope.value.scope_type === 'divisi') {
        return (scopeOptions.value.divisis || []).map((item) => ({ id: item.id, name: item.nama_divisi }))
    }

    return (scopeOptions.value.outlets || []).map((item) => ({ id: item.id_outlet, name: item.nama_outlet }))
})

watch(() => props.show, (newVal) => {
    if (newVal) {
        loadPermissions()
    }
})

watch(() => newScope.value.scope_type, () => {
    newScope.value.scope_id = null
})

const permissionEndpoint = computed(() => {
    if (props.resourceType === 'folder') {
        return `/shared-documents/folders/${props.resourceId}/permissions`
    }
    return `/shared-documents/${props.resourceId}/permissions`
})

const loadPermissions = async () => {
    try {
        const response = await fetch(permissionEndpoint.value)
        const data = await response.json()
        if (data.success) {
            permissions.value = data.data || []
            scopePermissions.value = data.scope_permissions || []
            scopeOptions.value = data.scope_options || { users: [], jabatans: [], divisis: [], outlets: [] }
            isPublic.value = data.is_public || false
        }
    } catch (error) {
        console.error('Error loading permissions:', error)
    }
}

const updatePermission = (permissionId, newPermission) => {
    const permission = permissions.value.find((p) => p.id === permissionId)
    if (permission) permission.permission = newPermission
}

const removePermission = (permissionId) => {
    permissions.value = permissions.value.filter((p) => p.id !== permissionId)
}

const addNewUser = (user) => {
    const existingPermission = permissions.value.find((p) => p.user_id === user.id)
    if (existingPermission) {
        alert('User ini sudah memiliki akses')
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
    permissions.value.push({
        id: `temp_${Date.now()}`,
        user_id: newUser.value.id,
        user: newUser.value,
        permission: newUserPermission.value,
    })
    cancelAddUser()
}

const getScopeName = (scopeType, scopeId) => {
    const optionsByType = {
        user: (scopeOptions.value.users || []).map((item) => ({ id: item.id, name: item.nama_lengkap })),
        jabatan: (scopeOptions.value.jabatans || []).map((item) => ({ id: item.id_jabatan, name: item.nama_jabatan })),
        divisi: (scopeOptions.value.divisis || []).map((item) => ({ id: item.id, name: item.nama_divisi })),
        outlet: (scopeOptions.value.outlets || []).map((item) => ({ id: item.id_outlet, name: item.nama_outlet })),
    }

    return optionsByType[scopeType]?.find((item) => String(item.id) === String(scopeId))?.name || scopeId
}

const addScopePermission = () => {
    if (!newScope.value.scope_id) {
        alert('Pilih data scope terlebih dahulu')
        return
    }

    const duplicate = scopePermissions.value.find((scopePermission) =>
        scopePermission.scope_type === newScope.value.scope_type &&
        String(scopePermission.scope_id) === String(newScope.value.scope_id)
    )

    if (duplicate) {
        duplicate.permission = newScope.value.permission
        return
    }

    scopePermissions.value.push({
        id: `scope_${Date.now()}`,
        scope_type: newScope.value.scope_type,
        scope_id: newScope.value.scope_id,
        permission: newScope.value.permission,
    })
}

const updateScopePermission = (scopePermissionId, permission) => {
    const scopePermission = scopePermissions.value.find((scope) => scope.id === scopePermissionId)
    if (scopePermission) scopePermission.permission = permission
}

const removeScopePermission = (scopePermissionId) => {
    scopePermissions.value = scopePermissions.value.filter((scope) => scope.id !== scopePermissionId)
}

const savePermissions = async () => {
    loading.value = true
    try {
        await router.post(permissionEndpoint.value, {
            permissions: permissions.value.map((permission) => ({
                user_id: permission.user_id,
                permission: permission.permission,
            })),
            scope_permissions: scopePermissions.value.map((scopePermission) => ({
                scope_type: scopePermission.scope_type,
                scope_id: scopePermission.scope_id,
                permission: scopePermission.permission,
            })),
            is_public: isPublic.value,
        }, {
            onSuccess: () => {
                emit('saved')
                close()
            },
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
