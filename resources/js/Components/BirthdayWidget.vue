<template>
    <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 hover:shadow-3xl w-full"
        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                    :class="isNight ? 'bg-pink-500/20' : 'bg-pink-100'">
                    <i class="fas fa-birthday-cake text-pink-500 text-sm"></i>
                </div>
                <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                    Ulang Tahun
                </h3>
            </div>
            <div v-if="hasBirthdayToday" class="relative">
                <div class="w-3 h-3 bg-pink-500 rounded-full animate-pulse"></div>
                <div class="absolute -top-1 -right-1 w-5 h-5 bg-pink-500 rounded-full animate-ping opacity-75"></div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-pink-500"></div>
        </div>

        <!-- Birthday List -->
        <div v-else-if="birthdays.length > 0" class="space-y-3">
            <div v-for="(person, index) in birthdays" :key="person.id" 
                class="flex items-center gap-3 p-3 rounded-xl transition-all duration-300 hover:scale-105"
                :class="[
                    isToday(person.birthday) 
                        ? 'bg-gradient-to-r from-pink-500/20 to-purple-500/20 border border-pink-300/50 shadow-lg' 
                        : isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-slate-50 hover:bg-slate-100'
                ]">
                
                <!-- Avatar -->
                <div class="relative">
                    <div v-if="person.avatar" 
                        class="w-12 h-12 rounded-full overflow-hidden border-2 cursor-pointer hover:scale-105 transition-transform duration-200"
                        :class="isToday(person.birthday) ? 'border-pink-400' : 'border-slate-300'"
                        @click="openImageModal(`/storage/${person.avatar}`)">
                        <img :src="`/storage/${person.avatar}`" :alt="person.nama_lengkap" 
                            class="w-full h-full object-cover hover:opacity-90 transition-opacity" />
                    </div>
                    <div v-else class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-bold border-2 cursor-pointer hover:scale-105 transition-transform duration-200"
                        :class="[
                            isToday(person.birthday) 
                                ? 'bg-gradient-to-br from-pink-500 to-purple-600 border-pink-400' 
                                : 'bg-gradient-to-br from-indigo-500 to-purple-600 border-slate-300'
                        ]"
                        @click="openImageModal(null, person.nama_lengkap)">
                        {{ getInitials(person.nama_lengkap) }}
                    </div>
                    
                    <!-- Birthday Badge -->
                    <div v-if="isToday(person.birthday)" 
                        class="absolute -top-1 -right-1 w-6 h-6 bg-pink-500 rounded-full flex items-center justify-center border-2 border-white">
                        <i class="fas fa-birthday-cake text-white text-xs"></i>
                    </div>
                </div>

                <!-- Person Info -->
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm truncate" 
                        :class="isToday(person.birthday) ? 'text-pink-800' : isNight ? 'text-white' : 'text-slate-800'">
                        {{ person.nama_lengkap }}
                    </div>
                    <div class="text-xs" 
                        :class="isToday(person.birthday) ? 'text-pink-600' : isNight ? 'text-slate-300' : 'text-slate-600'">
                        {{ person.jabatan?.nama_jabatan || 'N/A' }}
                    </div>
                    <div class="text-xs mt-1" 
                        :class="isToday(person.birthday) ? 'text-pink-500' : isNight ? 'text-slate-400' : 'text-slate-500'">
                        <i class="fas fa-building mr-1"></i>
                        {{ person.outlet?.nama_outlet || 'N/A' }}
                    </div>
                    <div class="text-xs font-medium mt-1" 
                        :class="isToday(person.birthday) ? 'text-pink-700' : isNight ? 'text-slate-400' : 'text-slate-500'">
                        <span v-if="isToday(person.birthday)" class="flex items-center gap-1">
                            <i class="fas fa-birthday-cake text-pink-500"></i>
                            <span class="text-pink-600 font-bold">Hari ini!</span>
                        </span>
                        <span v-else>
                            {{ formatBirthdayDate(person.birthday) }}
                        </span>
                    </div>
                </div>

                <!-- Age -->
                <div v-if="isToday(person.birthday)" class="text-right">
                    <div class="text-lg font-bold text-pink-600">
                        {{ calculateAge(person.birthday) }}
                    </div>
                    <div class="text-xs text-pink-500">tahun</div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-8">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center"
                :class="isNight ? 'bg-slate-700/50' : 'bg-slate-100'">
                <i class="fas fa-birthday-cake text-2xl" :class="isNight ? 'text-slate-400' : 'text-slate-500'"></i>
            </div>
            <div class="text-sm" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                Tidak ada ulang tahun dalam 30 hari ke depan
            </div>
        </div>

        <!-- Celebration Animation -->
        <div v-if="hasBirthdayToday" class="absolute inset-0 pointer-events-none overflow-hidden rounded-2xl">
            <div v-for="n in 20" :key="n" 
                class="absolute animate-bounce"
                :style="{
                    left: Math.random() * 100 + '%',
                    top: Math.random() * 100 + '%',
                    animationDelay: Math.random() * 2 + 's',
                    animationDuration: (Math.random() * 2 + 1) + 's'
                }">
                <i class="fas fa-birthday-cake text-pink-400 text-lg opacity-70"></i>
            </div>
        </div>
    </div>

    <!-- Lightbox Component -->
    <vue-easy-lightbox
        :visible="visible"
        :imgs="imgs"
        :index="index"
        @hide="visible = false"
    />
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
    isNight: {
        type: Boolean,
        default: false
    }
});

const birthdays = ref([]);
const loading = ref(true);

// Lightbox state
const visible = ref(false);
const index = ref(0);
const imgs = ref([]);

const hasBirthdayToday = computed(() => {
    return birthdays.value.some(person => isToday(person.birthday));
});

const getInitials = (name) => {
    return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().slice(0, 2);
};

const isToday = (birthday) => {
    const today = new Date();
    const birthDate = new Date(birthday);
    
    // Compare month and day, ignoring year
    return birthDate.getMonth() === today.getMonth() && 
           birthDate.getDate() === today.getDate();
};

const formatBirthdayDate = (birthday) => {
    const birthDate = new Date(birthday);
    const today = new Date();
    const thisYear = today.getFullYear();
    
    // Set birthday to this year
    const birthdayThisYear = new Date(thisYear, birthDate.getMonth(), birthDate.getDate());
    
    // If birthday has passed this year, set to next year
    if (birthdayThisYear < today) {
        birthdayThisYear.setFullYear(thisYear + 1);
    }
    
    const diffTime = birthdayThisYear - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) {
        return 'Besok';
    } else if (diffDays <= 7) {
        return `${diffDays} hari lagi`;
    } else {
        return birthDate.toLocaleDateString('id-ID', { 
            day: 'numeric', 
            month: 'long' 
        });
    }
};

const calculateAge = (birthday) => {
    const today = new Date();
    const birthDate = new Date(birthday);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    return age;
};

const openImageModal = (imageSrc, personName = '') => {
    if (imageSrc) {
        imgs.value = [imageSrc];
        index.value = 0;
        visible.value = true;
    } else {
        // For users without avatar, show a placeholder or do nothing
        console.log(`No avatar for ${personName}`);
    }
};

const fetchBirthdays = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/birthdays');
        birthdays.value = response.data;
    } catch (error) {
        console.error('Error fetching birthdays:', error);
        birthdays.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchBirthdays();
});
</script>

<style scoped>
@keyframes bounce {
    0%, 20%, 53%, 80%, 100% {
        transform: translate3d(0,0,0);
    }
    40%, 43% {
        transform: translate3d(0, -30px, 0);
    }
    70% {
        transform: translate3d(0, -15px, 0);
    }
    90% {
        transform: translate3d(0, -4px, 0);
    }
}

.animate-bounce {
    animation: bounce 2s infinite;
}
</style>
