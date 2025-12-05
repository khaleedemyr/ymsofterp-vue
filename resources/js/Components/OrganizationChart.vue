<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import OrganizationNode from './OrganizationNode.vue';

const loading = ref(true);
const organizationData = ref([]);
const selectedEmployee = ref(null);
const showEmployeeModal = ref(false);

// Zoom and pan functionality
const zoomLevel = ref(1);
const panX = ref(0);
const panY = ref(0);
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const lastPan = ref({ x: 0, y: 0 });

// Function to get employee initials (same as in Home.vue)
function getInitials(name) {
    if (!name) return '';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
}

// Function to get avatar URL (same as in Home.vue)
function getAvatarUrl(avatar) {
    if (!avatar) return null;
    return `/storage/${avatar}`;
}

// Load organization data
async function loadOrganizationData() {
    loading.value = true;
    try {
        const response = await axios.get('/api/organization-chart');
        console.log('API Response:', response.data);
        
        if (response.data.success) {
            organizationData.value = response.data.data;
            console.log('Organization Data:', organizationData.value);
            console.log('Organization Data Length:', organizationData.value.length);
            console.log('Sample data (first 3):', organizationData.value.slice(0, 3));
            console.log('Sample id_atasan values:', organizationData.value.slice(0, 10).map(emp => ({ 
                nama: emp.nama_lengkap, 
                id_jabatan: emp.id_jabatan, 
                id_atasan: emp.id_atasan 
            })));
            
            // Check if id_jabatan=149 exists in data
            const jabatan149 = organizationData.value.find(emp => emp.id_jabatan === 149);
            console.log('Jabatan 149 found:', jabatan149);
            
            // Show unique jabatan IDs
            const uniqueJabatanIds = [...new Set(organizationData.value.map(emp => emp.id_jabatan))].sort((a, b) => a - b);
            console.log('Unique jabatan IDs:', uniqueJabatanIds);
        } else {
            console.error('API returned success: false', response.data);
        }
    } catch (error) {
        console.error('Error loading organization data:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal memuat data organisasi',
            confirmButtonText: 'OK',
            confirmButtonColor: '#EF4444'
        });
    } finally {
        loading.value = false;
    }
}

// Show employee details
function showEmployeeDetails(employee) {
    selectedEmployee.value = employee;
    showEmployeeModal.value = true;
}

// Close employee modal
function closeEmployeeModal() {
    showEmployeeModal.value = false;
    selectedEmployee.value = null;
}

// Zoom functions
function zoomIn() {
    zoomLevel.value = Math.min(zoomLevel.value * 1.2, 3);
}

function zoomOut() {
    zoomLevel.value = Math.max(zoomLevel.value / 1.2, 0.3);
}

function resetZoom() {
    zoomLevel.value = 1;
    panX.value = 0;
    panY.value = 0;
}

// Pan functions
function handleMouseDown(event) {
    isDragging.value = true;
    dragStart.value = { x: event.clientX, y: event.clientY };
    lastPan.value = { x: panX.value, y: panY.value };
}

function handleMouseMove(event) {
    if (!isDragging.value) return;
    
    const deltaX = event.clientX - dragStart.value.x;
    const deltaY = event.clientY - dragStart.value.y;
    
    panX.value = lastPan.value.x + deltaX;
    panY.value = lastPan.value.y + deltaY;
}

function handleMouseUp() {
    isDragging.value = false;
}

function handleWheel(event) {
    event.preventDefault();
    const delta = event.deltaY > 0 ? 0.9 : 1.1;
    zoomLevel.value = Math.min(Math.max(zoomLevel.value * delta, 0.3), 3);
}

// Computed transform style
const chartTransform = computed(() => {
    return `translate(${panX.value}px, ${panY.value}px) scale(${zoomLevel.value})`;
});

// Build organization tree structure
const organizationTree = computed(() => {
    console.log('Building organization tree with data:', organizationData.value);
    
    if (!organizationData.value.length) {
        console.log('No organization data available');
        return [];
    }
    
    // Group employees by jabatan (since hierarchy is based on jabatan, not individual employees)
    const jabatanGroups = new Map();
    organizationData.value.forEach(emp => {
        if (!jabatanGroups.has(emp.id_jabatan)) {
            jabatanGroups.set(emp.id_jabatan, {
                id_jabatan: emp.id_jabatan,
                nama_jabatan: emp.nama_jabatan,
                id_atasan: emp.id_atasan,
                employees: []
            });
        }
        jabatanGroups.get(emp.id_jabatan).employees.push(emp);
    });
    
    // Also add jabatan that might not have employees in outlet 1 but exist in hierarchy
    // This ensures we don't miss any jabatan in the hierarchy
    const allJabatanIds = new Set(organizationData.value.map(emp => emp.id_jabatan));
    const allAtasanIds = new Set(organizationData.value.map(emp => emp.id_atasan).filter(id => id));
    
    // Add missing jabatan that are referenced as atasan but don't have employees in outlet 1
    allAtasanIds.forEach(atasanId => {
        if (!allJabatanIds.has(atasanId)) {
            // This jabatan exists in hierarchy but no employees in outlet 1
            // We need to fetch this jabatan info from the API
            console.log(`Missing jabatan ${atasanId} in hierarchy - needs to be added`);
        }
    });
    
    console.log('Jabatan groups:', jabatanGroups);
    
    // Debug: Show all jabatan with their atasan
    const allJabatan = Array.from(jabatanGroups.values());
    console.log('All jabatan with atasan:', allJabatan.map(j => ({
        id: j.id_jabatan,
        nama: j.nama_jabatan,
        atasan: j.id_atasan,
        employeeCount: j.employees.length
    })));
    
    // Find root jabatan - prioritize id_jabatan=149 as the top level
    let rootJabatan = Array.from(jabatanGroups.values()).filter(jabatan => 
        jabatan.id_jabatan === 149
    );
    
    console.log('Root jabatan (id_jabatan=149):', rootJabatan);
    
    // If id_jabatan=149 not found, try other approaches
    if (rootJabatan.length === 0) {
        console.log('id_jabatan=149 not found, trying alternative approaches...');
        
        // Try to find jabatan with id_atasan = null or empty
        const nullAtasanJabatan = Array.from(jabatanGroups.values()).filter(jabatan => 
            jabatan.id_atasan === null || jabatan.id_atasan === '' || jabatan.id_atasan === undefined
        );
        
        console.log('Jabatan with null atasan:', nullAtasanJabatan);
        
        if (nullAtasanJabatan.length > 0) {
            rootJabatan = nullAtasanJabatan;
        } else {
            // Try to find top-level jabatan by checking which id_jabatan are not referenced as id_atasan
            const allAtasanIds = new Set(Array.from(jabatanGroups.values()).map(j => j.id_atasan).filter(id => id && id !== ''));
            const topLevelJabatan = Array.from(jabatanGroups.values()).filter(jabatan => 
                !allAtasanIds.has(jabatan.id_jabatan.toString())
            );
            
            console.log('All atasan IDs:', Array.from(allAtasanIds));
            console.log('Top level jabatan (not referenced as atasan):', topLevelJabatan);
            
            if (topLevelJabatan.length > 0 && topLevelJabatan.length < jabatanGroups.size) {
                rootJabatan = topLevelJabatan;
            } else {
                // If still no clear hierarchy, take first few jabatan as roots
                console.log('Taking first 5 jabatan as roots for display...');
                rootJabatan = Array.from(jabatanGroups.values()).slice(0, 5);
            }
        }
    }
    
    console.log('Final root jabatan:', rootJabatan);
    
    // Build tree using iterative approach to avoid infinite recursion
    function buildJabatanTree(jabatanGroups, rootJabatan) {
        const result = [];
        const processed = new Set();
        
        console.log('Building tree with jabatan groups:', Array.from(jabatanGroups.keys()));
        console.log('Root jabatan:', rootJabatan.map(r => ({ id: r.id_jabatan, nama: r.nama_jabatan, atasan: r.id_atasan })));
        
        // Process each root jabatan
        for (const root of rootJabatan) {
            if (processed.has(root.id_jabatan)) continue;
            
            const treeNode = {
                ...root,
                children: []
            };
            
            // Build children iteratively
            const queue = [treeNode];
            let depth = 0;
            const maxDepth = 10; // Increase depth limit
            
            while (queue.length > 0 && depth < maxDepth) {
                const current = queue.shift();
                processed.add(current.id_jabatan);
                
                // Find children of current jabatan
                const children = Array.from(jabatanGroups.values()).filter(jabatan => {
                    const jabatanAtasan = jabatan.id_atasan;
                    const currentIdStr = current.id_jabatan.toString();
                    const jabatanAtasanStr = jabatanAtasan ? jabatanAtasan.toString() : null;
                    
                    const isChild = jabatanAtasanStr === currentIdStr && !processed.has(jabatan.id_jabatan);
                    
                    if (isChild) {
                        console.log(`Found child: ${jabatan.nama_jabatan} (${jabatan.id_jabatan}) under ${current.nama_jabatan} (${current.id_jabatan})`);
                    }
                    
                    return isChild;
                });
                
                console.log(`Found ${children.length} children for jabatan ${current.id_jabatan} (${current.nama_jabatan})`);
                
                // Add children to current node
                current.children = children.map(child => ({
                    ...child,
                    children: []
                }));
                
                // Add children to queue for next iteration
                queue.push(...current.children);
                depth++;
            }
            
            result.push(treeNode);
        }
        
        console.log('Final tree structure:', result);
        return result;
    }
    
    try {
        const tree = buildJabatanTree(jabatanGroups, rootJabatan);
        
        console.log('Final organization tree:', tree);
        
        return tree;
    } catch (error) {
        console.error('Error building organization tree:', error);
        console.log('Falling back to flat display...');
        return [];
    }
});

onMounted(() => {
    loadOrganizationData();
});
</script>

<template>
    <div class="organization-chart-container">
        <!-- Header -->
        <div class="header-section">
            <div class="header-content">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                    Organization Chart
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Struktur organisasi perusahaan
                </p>
            </div>
            
            <!-- Zoom Controls -->
            <div class="zoom-controls">
                <button @click="zoomOut" class="zoom-btn" title="Zoom Out">
                    <i class="fas fa-minus"></i>
                </button>
                <span class="zoom-level">{{ Math.round(zoomLevel * 100) }}%</span>
                <button @click="zoomIn" class="zoom-btn" title="Zoom In">
                    <i class="fas fa-plus"></i>
                </button>
                <button @click="resetZoom" class="zoom-btn" title="Reset Zoom">
                    <i class="fas fa-expand-arrows-alt"></i>
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mb-4"></div>
                <p class="text-gray-600 dark:text-gray-400">Memuat data organisasi...</p>
            </div>
        </div>

        <!-- Organization Chart -->
        <div v-else-if="organizationTree.length > 0" class="chart-container">
            <div 
                class="chart-viewport"
                @mousedown="handleMouseDown"
                @mousemove="handleMouseMove"
                @mouseup="handleMouseUp"
                @mouseleave="handleMouseUp"
                @wheel="handleWheel"
            >
                <div 
                    class="chart-content"
                    :style="{ transform: chartTransform }"
                >
                    <div class="organization-level">
                        <div 
                            v-for="root in organizationTree" 
                            :key="root.id_jabatan"
                            class="organization-node"
                        >
                            <OrganizationNode 
                                :employee="root" 
                                :level="0"
                                @show-details="showEmployeeDetails"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fallback: Show flat list if no hierarchy -->
        <div v-else-if="organizationData.length > 0" class="organization-fallback">
            <div class="text-center mb-6">
                <h3 class="text-xl font-bold mb-2" :class="isNight ? 'text-white' : 'text-slate-800'">
                    Daftar Karyawan
                </h3>
                <p class="text-sm" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                    Menampilkan {{ organizationData.length }} karyawan (hierarki tidak tersedia)
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div 
                    v-for="employee in organizationData.slice(0, 20)" 
                    :key="employee.id"
                    @click="showEmployeeDetails(employee)"
                    class="backdrop-blur-md rounded-xl shadow-lg border p-4 cursor-pointer transition-all duration-300 hover:scale-105 hover:shadow-xl"
                    :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'"
                >
                    <!-- Avatar -->
                    <div class="flex justify-center mb-3">
                        <div v-if="employee.avatar" class="w-16 h-16 rounded-full overflow-hidden border-2 border-white shadow-lg">
                            <img 
                                :src="getAvatarUrl(employee.avatar)" 
                                :alt="employee.nama_lengkap" 
                                class="w-full h-full object-cover" 
                            />
                        </div>
                        <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold border-2 border-white shadow-lg">
                            {{ getInitials(employee.nama_lengkap) }}
                        </div>
                    </div>

                    <!-- Employee Info -->
                    <div class="text-center">
                        <h4 class="font-bold text-sm mb-1" :class="isNight ? 'text-white' : 'text-slate-800'">
                            {{ employee.nama_lengkap }}
                        </h4>
                        <p class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                            {{ employee.nama_jabatan }}
                        </p>
                        <div v-if="employee.subordinates_count > 0" class="mt-2 text-xs" :class="isNight ? 'text-blue-300' : 'text-blue-600'">
                            <i class="fas fa-users mr-1"></i>
                            {{ employee.subordinates_count }} bawahan
                        </div>
                    </div>
                </div>
            </div>
            
            <div v-if="organizationData.length > 20" class="text-center mt-6">
                <p class="text-sm" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                    Dan {{ organizationData.length - 20 }} karyawan lainnya...
                </p>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
            <div class="mb-4 text-gray-400 dark:text-gray-500">
                <i class="fas fa-sitemap text-6xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                Tidak Ada Data Organisasi
            </h3>
            <p class="text-gray-600 dark:text-gray-400">
                Belum ada data organisasi yang tersedia
            </p>
        </div>

        <!-- Employee Detail Modal -->
        <div v-if="showEmployeeModal && selectedEmployee" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeEmployeeModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Detail Karyawan
                            </h3>
                            <button @click="closeEmployeeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>

                        <div class="text-center">
                            <!-- Jabatan Info -->
                            <div class="mb-4">
                                <h4 class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ selectedEmployee.nama_jabatan }}
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ selectedEmployee.employees ? selectedEmployee.employees.length : 0 }} karyawan
                                </p>
                            </div>

                            <!-- All Employees in this Position -->
                            <div v-if="selectedEmployee.employees && selectedEmployee.employees.length > 0" class="space-y-3">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Karyawan di Jabatan Ini:
                                </div>
                                
                                <div class="grid grid-cols-1 gap-3 max-h-60 overflow-y-auto">
                                    <div 
                                        v-for="emp in selectedEmployee.employees" 
                                        :key="emp.id"
                                        class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg"
                                    >
                                        <!-- Employee Avatar -->
                                        <div class="flex-shrink-0">
                                            <div v-if="emp.avatar" class="w-10 h-10 rounded-full overflow-hidden border-2 border-white shadow-lg">
                                                <img 
                                                    :src="getAvatarUrl(emp.avatar)" 
                                                    :alt="emp.nama_lengkap" 
                                                    class="w-full h-full object-cover" 
                                                />
                                            </div>
                                            <div v-else class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold border-2 border-white shadow-lg">
                                                {{ getInitials(emp.nama_lengkap) }}
                                            </div>
                                        </div>
                                        
                                        <!-- Employee Info -->
                                        <div class="flex-1 text-left">
                                            <div class="font-semibold text-sm text-gray-900 dark:text-white">
                                                {{ emp.nama_lengkap }}
                                            </div>
                                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                                ID: {{ emp.id }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- No Employees -->
                            <div v-else class="text-center py-4">
                                <div class="text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-user-slash text-2xl mb-2"></i>
                                    <p class="text-sm">Tidak ada karyawan di jabatan ini</p>
                                </div>
                            </div>

                            <!-- Subordinates Count -->
                            <div v-if="selectedEmployee.children && selectedEmployee.children.length > 0" class="mt-4 bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                                <div class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-1">
                                    Jabatan Bawahan
                                </div>
                                <div class="text-sm text-blue-900 dark:text-blue-100">
                                    {{ selectedEmployee.children.length }} jabatan
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6">
                        <button @click="closeEmployeeModal" 
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.organization-chart-container {
    @apply bg-gray-50 dark:bg-gray-900 h-screen w-screen flex flex-col;
    margin: 0;
    padding: 0;
}

.header-section {
    @apply flex justify-between items-center p-4 bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700;
    margin: 0;
}

.header-content {
    @apply flex-1;
}

.zoom-controls {
    @apply flex items-center gap-2 bg-gray-100 dark:bg-gray-700 rounded-lg p-2;
}

.zoom-btn {
    @apply w-8 h-8 flex items-center justify-center rounded-md bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-500 transition-colors;
}

.zoom-level {
    @apply text-sm font-medium text-gray-700 dark:text-gray-300 px-2 min-w-[50px] text-center;
}

.chart-container {
    @apply flex-1 relative overflow-hidden;
    width: 100vw;
    height: calc(100vh - 80px);
    margin: 0;
    padding: 0;
}

.chart-viewport {
    @apply w-full h-full cursor-grab;
    user-select: none;
}

.chart-viewport:active {
    @apply cursor-grabbing;
}

.chart-content {
    @apply w-full h-full transition-transform duration-100 ease-out;
    transform-origin: center center;
}

.organization-level {
    @apply flex flex-wrap justify-center items-start gap-8 p-4 min-h-full;
    width: 100%;
}

.organization-node {
    @apply flex flex-col items-center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .header-section {
        @apply p-4 flex-col gap-4;
    }
    
    .zoom-controls {
        @apply w-full justify-center;
    }
    
    .organization-level {
        @apply gap-4 p-4;
    }
}
</style>
