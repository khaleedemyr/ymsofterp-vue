<template>
    <div class="relative">
        <!-- Search Input -->
        <div class="relative">
            <input
                v-model="searchQuery"
                type="text"
                :placeholder="placeholder"
                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300 bg-white/50 backdrop-blur-sm"
                @focus="showDropdown = true"
                @input="handleSearch"
                @keydown.escape="closeDropdown"
                @blur="handleBlur"
            >
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div v-if="loading" class="absolute right-3 top-3">
            <div class="spinner-modern"></div>
        </div>

        <!-- Dropdown Results -->
        <div 
            v-if="showDropdown && (searchResults.length > 0 || searchQuery.length > 0)"
            class="absolute z-50 w-full mt-2 bg-white/90 backdrop-blur-xl rounded-xl shadow-2xl border border-white/20 max-h-80 overflow-y-auto"
        >
            <!-- Search Results -->
            <div v-if="searchResults.length > 0" class="py-2">
                <div
                    v-for="user in searchResults"
                    :key="user.id"
                    @click="selectUser(user)"
                    class="px-4 py-3 hover:bg-blue-50 cursor-pointer transition-all duration-200 group"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <!-- User Name -->
                            <div class="font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                                {{ user.nama_lengkap }}
                            </div>
                            
                            <!-- User Details -->
                            <div class="text-sm text-gray-600 space-y-1 mt-1">
                                <div class="flex items-center">
                                    <i class="fas fa-envelope w-4 text-gray-400 mr-2"></i>
                                    <span>{{ user.email }}</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-building w-4 text-gray-400 mr-2"></i>
                                    <span>{{ user.divisi || 'Tidak ada divisi' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-user-tie w-4 text-gray-400 mr-2"></i>
                                    <span>{{ user.jabatan || 'Tidak ada jabatan' }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Selection Indicator -->
                        <div class="ml-3">
                            <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center group-hover:border-blue-500 transition-colors">
                                <div v-if="isSelected(user)" class="w-3 h-3 bg-blue-500 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No Results -->
            <div v-else-if="searchQuery.length > 0 && !loading" class="px-4 py-6 text-center">
                <div class="text-gray-500">
                    <i class="fas fa-search text-2xl mb-2"></i>
                    <p>Tidak ada user ditemukan</p>
                    <p class="text-sm">Coba kata kunci lain</p>
                </div>
            </div>

            <!-- Search Tips -->
            <div v-if="searchQuery.length === 0" class="px-4 py-6 text-center">
                <div class="text-gray-500">
                    <i class="fas fa-users text-2xl mb-2"></i>
                    <p>Mulai mengetik untuk mencari user</p>
                    <p class="text-sm">Cari berdasarkan nama, email, divisi, atau jabatan</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import axios from 'axios'

const props = defineProps({
    placeholder: {
        type: String,
        default: 'Cari user...'
    },
    selectedUsers: {
        type: Array,
        default: () => []
    },
    multiple: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['user-selected', 'user-removed'])

const searchQuery = ref('')
const searchResults = ref([])
const showDropdown = ref(false)
const loading = ref(false)
const searchTimeout = ref(null)

// Debounced search function
const handleSearch = () => {
    clearTimeout(searchTimeout.value)
    
    if (searchQuery.value.length < 2) {
        searchResults.value = []
        return
    }
    
    loading.value = true
    searchTimeout.value = setTimeout(async () => {
        try {
            const response = await axios.get(route('shared-documents.users.search'), {
                params: { search: searchQuery.value }
            })
            
            if (response.data.success) {
                searchResults.value = response.data.data
            }
        } catch (error) {
            console.error('Error searching users:', error)
            searchResults.value = []
        } finally {
            loading.value = false
        }
    }, 300)
}

const selectUser = (user) => {
    if (props.multiple) {
        // For multiple selection, toggle user selection
        const isAlreadySelected = props.selectedUsers.some(u => u.id === user.id)
        if (isAlreadySelected) {
            emit('user-removed', user)
        } else {
            emit('user-selected', user)
        }
    } else {
        // For single selection, select user and close dropdown
        emit('user-selected', user)
        searchQuery.value = user.nama_lengkap
        closeDropdown()
    }
}

const isSelected = (user) => {
    return props.selectedUsers.some(u => u.id === user.id)
}

const closeDropdown = () => {
    showDropdown.value = false
}

const handleBlur = () => {
    // Delay closing to allow for click events
    setTimeout(() => {
        closeDropdown()
    }, 200)
}

// Watch for external changes to selected users
watch(() => props.selectedUsers, () => {
    // Update search query if single selection
    if (!props.multiple && props.selectedUsers.length > 0) {
        searchQuery.value = props.selectedUsers[0].nama_lengkap
    }
}, { deep: true })

// Clear search when dropdown closes
watch(showDropdown, (newValue) => {
    if (!newValue && props.multiple) {
        searchQuery.value = ''
        searchResults.value = []
    }
})
</script>

<style scoped>
/* Custom scrollbar for dropdown */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.5);
}

/* Spinner animation */
.spinner-modern {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(59, 130, 246, 0.1);
    border-left-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style> 