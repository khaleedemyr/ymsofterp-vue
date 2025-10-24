<template>
    <div class="space-y-1">
        <div 
            v-for="node in nodes" 
            :key="node.id_jabatan"
            class="bg-white border border-gray-200 rounded-md"
        >
            <!-- Jabatan Item -->
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <!-- Level Indicator -->
                        <div class="flex items-center space-x-1">
                            <div 
                                v-for="i in level" 
                                :key="i"
                                class="w-4 h-0.5 bg-gray-300"
                            ></div>
                            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                        </div>
                        
                        <!-- Jabatan Info -->
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-blue-600 font-semibold text-xs">
                                    {{ getJabatanInitials(node.nama_jabatan) }}
                                </span>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">{{ node.nama_jabatan }}</h4>
                                <div class="flex items-center space-x-4 text-xs text-gray-500">
                                    <span v-if="node.level">{{ node.level.nama_level }}</span>
                                    <span v-if="node.divisi">{{ node.divisi.nama_divisi }}</span>
                                    <span v-if="node.employee_count > 0">{{ node.employee_count }} karyawan</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex items-center space-x-2">
                        <button
                            v-if="node.employees && node.employees.length > 0"
                            @click="toggleEmployees(node.id_jabatan)"
                            class="text-xs text-blue-600 hover:text-blue-800 font-medium px-2 py-1 rounded hover:bg-blue-50"
                        >
                            {{ expandedEmployees[node.id_jabatan] ? 'Sembunyikan' : 'Tampilkan' }} Karyawan
                        </button>
                        <button
                            v-if="node.children && node.children.length > 0"
                            @click="toggleChildren(node.id_jabatan)"
                            class="text-xs text-green-600 hover:text-green-800 font-medium px-2 py-1 rounded hover:bg-green-50"
                        >
                            {{ expandedChildren[node.id_jabatan] ? 'Sembunyikan' : 'Tampilkan' }} Bawahan
                        </button>
                    </div>
                </div>

                <!-- Employees List -->
                <div 
                    v-if="expandedEmployees[node.id_jabatan] && node.employees && node.employees.length > 0" 
                    class="mt-4 pt-4 border-t border-gray-100"
                >
                    <h5 class="text-xs font-medium text-gray-700 mb-3">Karyawan:</h5>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div 
                            v-for="employee in node.employees" 
                            :key="employee.id"
                            class="flex items-center space-x-3 p-3 bg-gray-50 rounded-md hover:bg-gray-100 transition-colors"
                        >
                            <div class="flex-shrink-0">
                                <img 
                                    v-if="employee.avatar" 
                                    :src="getAvatarUrl(employee.avatar)" 
                                    :alt="employee.nama_lengkap"
                                    class="w-8 h-8 rounded-full object-cover"
                                >
                                <div 
                                    v-else 
                                    class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center"
                                >
                                    <span class="text-xs font-medium text-gray-600">
                                        {{ getInitials(employee.nama_lengkap) }}
                                    </span>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ employee.nama_lengkap }}</p>
                                <p class="text-xs text-gray-500">{{ employee.email }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Children (Subordinates) -->
                <div 
                    v-if="expandedChildren[node.id_jabatan] && node.children && node.children.length > 0" 
                    class="mt-4 pt-4 border-t border-gray-100"
                >
                    <OrganizationListItems 
                        :nodes="node.children"
                        :level="level + 1"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'

// Props
const props = defineProps({
    nodes: {
        type: Array,
        required: true
    },
    level: {
        type: Number,
        default: 0
    }
})

// Reactive data
const expandedEmployees = reactive({})
const expandedChildren = reactive({})

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

const toggleEmployees = (jabatanId) => {
    expandedEmployees[jabatanId] = !expandedEmployees[jabatanId]
}

const toggleChildren = (jabatanId) => {
    expandedChildren[jabatanId] = !expandedChildren[jabatanId]
}
</script>
