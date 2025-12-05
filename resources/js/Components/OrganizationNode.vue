<script setup>
import { computed } from 'vue';

const props = defineProps({
    employee: {
        type: Object,
        required: true
    },
    level: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['show-details']);

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

// Computed properties for styling based on level
const nodeClasses = computed(() => {
    const baseClasses = 'organization-employee-card';
    const levelClasses = {
        0: 'bg-gradient-to-br from-purple-500 to-indigo-600 text-white border-purple-300',
        1: 'bg-gradient-to-br from-blue-500 to-cyan-600 text-white border-blue-300',
        2: 'bg-gradient-to-br from-green-500 to-emerald-600 text-white border-green-300',
        3: 'bg-gradient-to-br from-yellow-500 to-orange-600 text-white border-yellow-300',
        4: 'bg-gradient-to-br from-red-500 to-pink-600 text-white border-red-300',
        5: 'bg-gradient-to-br from-gray-500 to-slate-600 text-white border-gray-300'
    };
    
    return `${baseClasses} ${levelClasses[props.level] || levelClasses[5]}`;
});

const connectorClasses = computed(() => {
    const levelColors = {
        0: 'border-purple-300',
        1: 'border-blue-300',
        2: 'border-green-300',
        3: 'border-yellow-300',
        4: 'border-red-300',
        5: 'border-gray-300'
    };
    
    return levelColors[props.level] || levelColors[5];
});

function handleClick() {
    emit('show-details', props.employee);
}
</script>

<template>
    <div class="organization-node-wrapper">
        <!-- Employee Card -->
        <div 
            :class="nodeClasses"
            @click="handleClick"
            class="cursor-pointer transition-all duration-300 hover:scale-105 hover:shadow-xl"
        >
            <!-- Avatar -->
            <div class="employee-avatar">
                <div v-if="employee.employees && employee.employees.length > 0 && employee.employees[0].avatar" class="w-10 h-10 md:w-12 md:h-12 rounded-full overflow-hidden border-2 border-white shadow-lg">
                    <img 
                        :src="getAvatarUrl(employee.employees[0].avatar)" 
                        :alt="employee.employees[0].nama_lengkap" 
                        class="w-full h-full object-cover" 
                    />
                </div>
                <div v-else-if="employee.employees && employee.employees.length > 0" class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/20 flex items-center justify-center text-white text-xs md:text-sm font-bold border-2 border-white shadow-lg">
                    {{ getInitials(employee.employees[0].nama_lengkap) }}
                </div>
                <div v-else class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-white/20 flex items-center justify-center text-white text-xs md:text-sm font-bold border-2 border-white shadow-lg">
                    {{ getInitials(employee.nama_jabatan) }}
                </div>
            </div>

            <!-- Employee Info -->
            <div class="employee-info">
                <h3 class="employee-name">
                    {{ employee.employees && employee.employees.length > 0 ? employee.employees[0].nama_lengkap : 'Jabatan Kosong' }}
                </h3>
                <p class="employee-position">
                    {{ employee.nama_jabatan }}
                </p>
                
                <!-- No employees indicator -->
                <div v-if="!employee.employees || employee.employees.length === 0" class="text-xs opacity-75 mt-1">
                    <i class="fas fa-user-slash mr-1"></i>
                    Tidak ada karyawan di outlet ini
                </div>
                
                <!-- Multiple employees indicator -->
                <div v-else-if="employee.employees && employee.employees.length > 1" class="text-xs opacity-75 mt-1">
                    <i class="fas fa-users mr-1"></i>
                    +{{ employee.employees.length - 1 }} karyawan lainnya
                </div>
                
                <!-- Subordinates count -->
                <div v-if="employee.children && employee.children.length > 0" class="subordinates-count">
                    <i class="fas fa-sitemap text-xs mr-1"></i>
                    {{ employee.children.length }} jabatan bawahan
                </div>
            </div>
        </div>

        <!-- Children -->
        <div v-if="employee.children && employee.children.length > 0" class="children-container">
            <!-- Vertical connector line -->
            <div class="vertical-connector" :class="connectorClasses"></div>
            
            <!-- Horizontal connector line -->
            <div class="horizontal-connector" :class="connectorClasses"></div>
            
            <!-- Children nodes -->
            <div class="children-nodes">
                <OrganizationNode
                    v-for="child in employee.children"
                    :key="child.id_jabatan"
                    :employee="child"
                    :level="level + 1"
                    @show-details="$emit('show-details', $event)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.organization-node-wrapper {
    @apply flex flex-col items-center relative;
}

.organization-employee-card {
    @apply p-3 rounded-full shadow-lg border-2 w-32 h-32 text-center relative z-10 flex flex-col items-center justify-center;
    backdrop-filter: blur(10px);
}

.employee-avatar {
    @apply mb-1 flex justify-center;
}

.employee-info {
    @apply space-y-0.5;
}

.employee-name {
    @apply font-bold text-xs leading-tight;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.employee-position {
    @apply text-xs opacity-90 leading-tight;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.subordinates-count {
    @apply text-xs opacity-75 mt-2 flex items-center justify-center;
}

.children-container {
    @apply relative mt-6 flex flex-col items-center;
}

.vertical-connector {
    @apply w-0.5 h-6 border-l-2 border-dashed absolute top-[-24px] left-1/2 transform -translate-x-1/2;
}

.horizontal-connector {
    @apply h-0.5 border-t-2 border-dashed absolute top-[-6px];
    width: calc(100% - 40px);
}

.children-nodes {
    @apply flex flex-wrap justify-center gap-6 pt-6;
    min-width: max-content;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .organization-employee-card {
        @apply w-28 h-28 p-2;
    }
    
    .employee-avatar {
        @apply mb-1;
    }
    
    .employee-name {
        @apply text-xs;
    }
    
    .employee-position {
        @apply text-xs;
    }
    
    .children-nodes {
        @apply gap-4;
    }
}

@media (max-width: 480px) {
    .organization-employee-card {
        @apply w-24 h-24 p-2;
    }
    
}
</style>
