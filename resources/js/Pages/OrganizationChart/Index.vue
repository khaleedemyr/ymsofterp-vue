<template>
    <AppLayout>
        <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Struktur Organisasi</h1>
                        <p class="text-sm text-gray-600">Hierarki organisasi berdasarkan outlet</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Outlet Selector -->
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-gray-700">Outlet:</label>
                            <select 
                                v-model="selectedOutletId" 
                                @change="loadOrganizationData"
                                class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">Pilih Outlet</option>
                                <option 
                                    v-for="outlet in outlets" 
                                    :key="outlet.id_outlet" 
                                    :value="outlet.id_outlet"
                                >
                                    {{ outlet.nama_outlet }}
                                </option>
                            </select>
                        </div>
                        
                        <!-- View Toggle -->
                        <div class="flex items-center space-x-2">
                            <button
                                @click="viewMode = 'tree'"
                                :class="[
                                    'px-3 py-2 text-sm font-medium rounded-md',
                                    viewMode === 'tree' 
                                        ? 'bg-blue-100 text-blue-700' 
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                ]"
                            >
                                Tree View
                            </button>
                            <button
                                @click="viewMode = 'list'"
                                :class="[
                                    'px-3 py-2 text-sm font-medium rounded-md',
                                    viewMode === 'list' 
                                        ? 'bg-blue-100 text-blue-700' 
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                ]"
                            >
                                List View
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                <p class="mt-4 text-gray-600">Memuat struktur organisasi...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-red-50 border border-red-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>{{ error }}</p>
                        </div>
                        <div class="mt-4">
                            <button 
                                @click="loadOrganizationData"
                                class="bg-red-100 text-red-800 px-3 py-2 rounded-md text-sm font-medium hover:bg-red-200"
                            >
                                Coba Lagi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-else-if="!loading && !error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Outlet Info -->
            <div v-if="currentOutlet" class="mb-6 bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900">{{ currentOutlet.nama_outlet }}</h2>
                <p class="text-sm text-gray-600">{{ currentOutlet.lokasi }}</p>
            </div>

            <!-- Tree View -->
            <div v-if="viewMode === 'tree' && organizationTree.length > 0" class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Struktur Organisasi</h3>
                    <div class="organization-tree">
                        <OrganizationTreeNode 
                            v-for="node in organizationTree" 
                            :key="node.id_jabatan"
                            :node="node"
                            :level="0"
                        />
                    </div>
                </div>
            </div>
            

            <!-- List View -->
            <div v-else-if="viewMode === 'list' && organizationData.length > 0" class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Daftar Karyawan</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Karyawan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divisi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atasan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="employee in organizationData" :key="employee.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <img 
                                                v-if="employee.avatar" 
                                                :src="`/storage/${employee.avatar}`" 
                                                :alt="employee.nama_lengkap"
                                                class="w-10 h-10 rounded-full object-cover mr-3"
                                            >
                                            <div 
                                                v-else 
                                                class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3"
                                            >
                                                <span class="text-blue-600 font-semibold text-sm">
                                                    {{ getInitials(employee.nama_lengkap) }}
                                                </span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ employee.nama_lengkap }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ employee.nama_jabatan }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ employee.nama_level || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ employee.nama_divisi || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ employee.atasan_jabatan || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="organizationData.length === 0" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada data</h3>
                <p class="mt-1 text-sm text-gray-500">Belum ada struktur organisasi untuk outlet ini.</p>
            </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.organization-tree {
    position: relative;
    padding: 40px 20px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

/* Tree structure styling */
.organization-node {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Card styling for clean tree appearance */
.organization-tree .bg-white {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.organization-tree .bg-white:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

/* Connection lines styling */
.organization-node::before {
    content: '';
    position: absolute;
    left: 50%;
    top: -20px;
    width: 2px;
    height: 20px;
    background-color: #9ca3af;
    transform: translateX(-50%);
    z-index: 1;
}

.organization-node:first-child::before {
    display: none;
}

/* Horizontal connection line */
.organization-node:not(:first-child)::after {
    content: '';
    position: absolute;
    left: 50%;
    top: -20px;
    width: 20px;
    height: 2px;
    background-color: #9ca3af;
    transform: translateX(-50%);
    z-index: 1;
}

/* Children container */
.organization-node > div:last-child {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: 40px;
    margin-top: 20px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .organization-tree {
        padding: 20px 10px;
    }
    
    .organization-node > div:last-child {
        flex-direction: column;
        gap: 20px;
    }
}
</style>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import OrganizationTreeNode from '@/Components/OrganizationTreeNode.vue'

// Props
const props = defineProps({
    outlets: {
        type: Array,
        default: () => []
    }
})

// Reactive data
const loading = ref(false)
const error = ref(null)
const selectedOutletId = ref('')
const viewMode = ref('tree')
const organizationData = ref([])
const currentOutlet = ref(null)

// Computed
const organizationTree = computed(() => {
    if (!organizationData.value.length) {
        return []
    }
    
    // Group employees by jabatan
    const jabatanGroups = {}
    organizationData.value.forEach(emp => {
        if (!jabatanGroups[emp.id_jabatan]) {
            jabatanGroups[emp.id_jabatan] = {
                id_jabatan: emp.id_jabatan,
                nama_jabatan: emp.nama_jabatan,
                id_atasan: emp.id_atasan,
                nama_level: emp.nama_level,
                nama_divisi: emp.nama_divisi,
                employees: []
            }
        }
        jabatanGroups[emp.id_jabatan].employees.push(emp)
    })
    
    // Build tree structure
    const tree = []
    const processed = new Set()
    
    const buildTree = (jabatanId, level = 0) => {
        if (processed.has(jabatanId)) return null
        processed.add(jabatanId)
        
        const jabatan = jabatanGroups[jabatanId]
        if (!jabatan) return null
        
        const node = {
            ...jabatan,
            level,
            children: []
        }
        
        // Find children (jabatan yang memiliki atasan = jabatanId ini)
        Object.values(jabatanGroups).forEach(childJabatan => {
            if (childJabatan.id_atasan == jabatanId) {
                const childNode = buildTree(childJabatan.id_jabatan, level + 1)
                if (childNode) {
                    node.children.push(childNode)
                }
            }
        })
        
        return node
    }
    
    // Find root jabatan (yang tidak memiliki atasan)
    Object.values(jabatanGroups).forEach(jabatan => {
        if (!jabatan.id_atasan) {
            const rootNode = buildTree(jabatan.id_jabatan)
            if (rootNode) {
                tree.push(rootNode)
            }
        }
    })
    
    // Jika tidak ada root jabatan, cari jabatan dengan level tertinggi sebagai root
    if (tree.length === 0) {
        // Cari jabatan dengan level tertinggi (nilai_level terkecil)
        let highestLevelJabatan = null
        let highestLevel = 999
        
        Object.values(jabatanGroups).forEach(jabatan => {
            // Cari level dari data employees
            const employee = jabatan.employees[0]
            if (employee && employee.nilai_level) {
                const levelValue = parseInt(employee.nilai_level.replace(/\D/g, ''))
                if (levelValue < highestLevel) {
                    highestLevel = levelValue
                    highestLevelJabatan = jabatan
                }
            }
        })
        
        // Jika tidak ada level, ambil jabatan pertama
        if (!highestLevelJabatan) {
            highestLevelJabatan = Object.values(jabatanGroups)[0]
        }
        
        if (highestLevelJabatan) {
            // Buat tree dengan jabatan tertinggi sebagai root
            const rootNode = {
                ...highestLevelJabatan,
                level: 0,
                children: []
            }
            
            // Hanya tambahkan jabatan yang benar-benar memiliki atasan = root jabatan
            Object.values(jabatanGroups).forEach(jabatan => {
                if (jabatan.id_jabatan !== highestLevelJabatan.id_jabatan && 
                    jabatan.id_atasan == highestLevelJabatan.id_jabatan) {
                    rootNode.children.push({
                        ...jabatan,
                        level: 1,
                        children: []
                    })
                }
            })
            
            tree.push(rootNode)
        }
    }
    
    return tree
})

// Methods
const getInitials = (name) => {
    if (!name) return ''
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

// Methods
const loadOrganizationData = async () => {
    if (!selectedOutletId.value) {
        organizationData.value = []
        currentOutlet.value = null
        return
    }

    loading.value = true
    error.value = null

    try {
        const response = await axios.get(`/api/organization-chart?outlet_id=${selectedOutletId.value}`)
        
        if (response.data.success) {
            organizationData.value = response.data.data
            currentOutlet.value = response.data.outlet
        } else {
            error.value = response.data.message || 'Gagal memuat data'
        }
    } catch (err) {
        console.error('Error loading organization data:', err)
        error.value = err.response?.data?.message || 'Terjadi kesalahan saat memuat data'
    } finally {
        loading.value = false
    }
}

// Lifecycle
onMounted(() => {
    // Auto-select first outlet if available
    if (props.outlets.length > 0) {
        selectedOutletId.value = props.outlets[0].id_outlet
        loadOrganizationData()
    }
})
</script>