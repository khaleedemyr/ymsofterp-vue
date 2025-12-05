<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-600 via-blue-600 to-purple-700 p-8 text-white shadow-2xl">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12 blur-xl"></div>
                
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-4xl font-bold mb-2 animate-fade-in-up">Upload Dokumen Bersama</h1>
                            <p class="text-blue-100 text-lg">Upload dan bagikan dokumen Excel, Word, atau PowerPoint</p>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-20 h-20 bg-white/20 rounded-2xl backdrop-blur-sm flex items-center justify-center">
                                <i class="fas fa-upload text-3xl text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-8 animate-fade-in-up animation-delay-200">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Document Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                            Judul Dokumen
                        </label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            required
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 bg-white/50 backdrop-blur-sm"
                            :class="{ 'border-red-500': form.errors.title }"
                        >
                        <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Deskripsi (Opsional)
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 bg-white/50 backdrop-blur-sm"
                            :class="{ 'border-red-500': form.errors.description }"
                        ></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <!-- File Upload -->
                    <div>
                        <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                            Pilih File
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl hover:border-blue-400 transition-colors bg-white/30 backdrop-blur-sm">
                            <div class="space-y-1 text-center">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                <div class="flex text-sm text-gray-600">
                                    <label for="file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>Upload file</span>
                                        <input
                                            id="file"
                                            ref="fileInput"
                                            type="file"
                                            accept=".xlsx,.xls,.docx,.doc,.pptx,.ppt"
                                            class="sr-only"
                                            @change="handleFileSelect"
                                            required
                                        >
                                    </label>
                                    <p class="pl-1">atau drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">
                                    Excel, Word, atau PowerPoint (maksimal 10MB)
                                </p>
                            </div>
                        </div>
                        <div v-if="selectedFile" class="mt-2 p-3 bg-green-50 rounded-xl border border-green-200">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <span class="text-sm text-gray-700">{{ selectedFile.name }}</span>
                                <span class="ml-auto text-xs text-gray-500">{{ formatFileSize(selectedFile.size) }}</span>
                            </div>
                        </div>
                        <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">
                            {{ form.errors.file }}
                        </p>
                    </div>

                    <!-- Public Access -->
                    <div class="flex items-center p-4 bg-blue-50 rounded-xl">
                        <input
                            id="is_public"
                            v-model="form.is_public"
                            type="checkbox"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        <label for="is_public" class="ml-2 block text-sm text-gray-900">
                            Buat dokumen publik (semua user bisa akses)
                        </label>
                    </div>

                    <!-- Share with Users -->
                    <div v-if="!form.is_public" class="space-y-4">
                        <label class="block text-sm font-medium text-gray-700">
                            Bagikan dengan User
                        </label>
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
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex justify-end space-x-3 pt-6">
                        <Link
                            :href="route('shared-documents.index')"
                            class="px-6 py-3 border border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-3 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 transition-all duration-300 transform hover:scale-105"
                        >
                            <i v-if="form.processing" class="fas fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fas fa-upload mr-2"></i>
                            Upload & Bagikan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import UserSearchDropdown from '@/Components/UserSearchDropdown.vue'

const fileInput = ref(null)
const selectedFile = ref(null)
const selectedUsers = ref([])

const form = useForm({
    title: '',
    description: '',
    file: null,
    is_public: false,
    shared_users: []
})

const handleFileSelect = (event) => {
    const file = event.target.files[0]
    if (file) {
        selectedFile.value = file
        form.file = file
    }
}

const addSelectedUser = (user) => {
    // Check if user already exists
    if (!selectedUsers.value.some(u => u.id === user.id)) {
        selectedUsers.value.push(user)
        // Add to form shared_users
        form.shared_users.push({
            user_id: user.id,
            permission: 'view'
        })
    }
}

const removeSelectedUser = (user) => {
    // Remove from selected users
    selectedUsers.value = selectedUsers.value.filter(u => u.id !== user.id)
    // Remove from form shared_users
    form.shared_users = form.shared_users.filter(su => su.user_id !== user.id)
}

const getUserPermission = (userId) => {
    const sharedUser = form.shared_users.find(su => su.user_id === userId)
    return sharedUser ? sharedUser.permission : 'view'
}

const updateUserPermission = (userId, permission) => {
    const sharedUser = form.shared_users.find(su => su.user_id === userId)
    if (sharedUser) {
        sharedUser.permission = permission
    }
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const submit = () => {
    form.post(route('shared-documents.store'))
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

/* Glass effect enhancement */
.backdrop-blur-xl {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

/* Smooth transitions */
* {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style> 