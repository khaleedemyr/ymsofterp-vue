<template>
    <div class="organization-node relative" :data-level="level">
        <!-- Jabatan Card -->
        <div class="bg-white border border-gray-300 rounded-lg shadow-sm p-4 mb-6 relative z-10 max-w-sm mx-auto">
            <div class="text-center">
                <!-- Avatar -->
                <div class="flex justify-center mb-3">
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-2xl">
                            {{ getJabatanInitials(node.nama_jabatan) }}
                        </span>
                    </div>
                </div>
                
                <!-- Name & Title -->
                <h4 class="text-lg font-bold text-gray-900 mb-1">{{ node.nama_jabatan }}</h4>
                <p class="text-sm text-gray-600 mb-3">{{ node.nama_level || 'Level' }}</p>
                
                <!-- Action Buttons -->
                <div class="flex justify-center space-x-2 mb-3">
                    <button
                        v-if="node.employees && node.employees.length > 0"
                        @click="toggleEmployees"
                        class="text-xs text-blue-600 hover:text-blue-800 font-medium px-2 py-1 rounded hover:bg-blue-50"
                    >
                        {{ showEmployees ? 'Hide' : 'Show' }} Employees
                    </button>
                    <button
                        v-if="node.children && node.children.length > 0"
                        @click="toggleChildren"
                        class="text-xs text-green-600 hover:text-green-800 font-medium px-2 py-1 rounded hover:bg-green-50"
                    >
                        {{ showChildren ? 'Hide' : 'Show' }} Subordinates
                    </button>
                </div>
                
                <!-- Employees List -->
                <div v-if="showEmployees && node.employees && node.employees.length > 0" class="mt-4 pt-3 border-t border-gray-100">
                    <h5 class="text-xs font-medium text-gray-700 mb-2">Employees:</h5>
                    <div class="space-y-2">
                        <div 
                            v-for="employee in node.employees" 
                            :key="employee.id"
                            class="flex items-center space-x-2 p-2 bg-gray-50 rounded text-xs"
                        >
                            <div class="flex-shrink-0">
                                <img 
                                    v-if="employee.avatar" 
                                    :src="getAvatarUrl(employee.avatar)" 
                                    :alt="employee.nama_lengkap"
                                    class="w-6 h-6 rounded-full object-cover"
                                >
                                <div 
                                    v-else 
                                    class="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center"
                                >
                                    <span class="text-xs font-medium text-gray-600">
                                        {{ getInitials(employee.nama_lengkap) }}
                                    </span>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-gray-900 truncate">{{ employee.nama_lengkap }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Children (Subordinates) -->
        <div v-if="showChildren && node.children && node.children.length > 0" class="relative">
            <!-- Vertical Connection Line -->
            <div class="absolute left-1/2 top-0 w-px h-8 bg-gray-400 transform -translate-x-1/2"></div>
            
            <!-- Horizontal Connection Line -->
            <div class="absolute left-1/2 top-8 w-full h-px bg-gray-400 transform -translate-x-1/2"></div>
            
            <!-- Children Cards -->
            <div class="flex justify-center space-x-8 pt-8">
                <div 
                    v-for="(child, index) in node.children" 
                    :key="child.id_jabatan"
                    class="relative"
                >
                    <!-- Vertical Line to Child -->
                    <div class="absolute left-1/2 top-0 w-px h-8 bg-gray-400 transform -translate-x-1/2"></div>
                    
                    <OrganizationTreeNode 
                        :node="child"
                        :level="level + 1"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'

// Props
const props = defineProps({
    node: {
        type: Object,
        required: true
    },
    level: {
        type: Number,
        default: 0
    }
})

// Reactive data
const showEmployees = ref(false)
const showChildren = ref(false)

// Methods
const getJabatanInitials = (namaJabatan) => {
    if (!namaJabatan) return 'J'
    return namaJabatan.split(' ').map(word => word[0]).join('').toUpperCase().slice(0, 2)
}

const getInitials = (name) => {
    if (!name) return ''
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

const getAvatarUrl = (avatar) => {
    if (!avatar) return null
    return `/storage/${avatar}`
}

const toggleEmployees = () => {
    showEmployees.value = !showEmployees.value
}

const toggleChildren = () => {
    showChildren.value = !showChildren.value
}
</script>

<style scoped>
.organization-node {
    position: relative;
}

/* Connection lines styling */
.organization-node::before {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 0;
    width: 1.5rem;
    height: 1rem;
    border-left: 2px solid #d1d5db;
    border-bottom: 2px solid #d1d5db;
    border-radius: 0 0 0 0.5rem;
}

.organization-node:first-child::before {
    display: none;
}
</style>
