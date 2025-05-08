<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AnalogClock from '@/Components/AnalogClock.vue';
import WeatherIcon from '@/Components/WeatherIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    nama_lengkap: String,
    avatar: String,
});

const { t, locale } = useI18n();

const greeting = ref('');
const time = ref(new Date());
const weather = ref({ temp: '', desc: '', icon: '', city: '', code: '' });
const isNight = ref(false);
const quote = ref({ text: '', author: '' });
const loadingQuote = ref(true);

function updateGreeting() {
    const hour = time.value.getHours();
    if (hour >= 5 && hour < 12) greeting.value = t('greeting.pagi');
    else if (hour >= 12 && hour < 16) greeting.value = t('greeting.siang');
    else if (hour >= 16 && hour < 19) greeting.value = t('greeting.sore');
    else greeting.value = t('greeting.malam');
    isNight.value = (hour >= 19 || hour < 5);
}

function updateTime() {
    time.value = new Date();
    updateGreeting();
}

async function fetchQuote() {
    loadingQuote.value = true;
    try {
        const today = new Date();
        const startOfYear = new Date(today.getFullYear(), 0, 0);
        const diff = today - startOfYear;
        const dayOfYear = Math.floor(diff / (1000 * 60 * 60 * 24));
        
        const response = await fetch(`/api/quotes/${dayOfYear}`);
        const data = await response.json();
        
        if (data.quote && data.author) {
            quote.value = {
                text: data.quote,
                author: data.author
            };
        } else {
            quote.value = { text: t('home.default_quote'), author: t('home.default_author') };
        }
    } catch (e) {
        quote.value = { text: t('home.default_quote'), author: t('home.default_author') };
    }
    loadingQuote.value = false;
}

function getInitials(name) {
    if (!name) return '';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
}

async function fetchWeather() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(async (pos) => {
            const lat = pos.coords.latitude;
            const lon = pos.coords.longitude;
            const API_KEY = 'b1b15e88fa797225412429c1c50c122a1';
            const url = `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&appid=${API_KEY}&units=metric&lang=${locale.value}`;
            const res = await fetch(url);
            const data = await res.json();
            weather.value = {
                temp: Math.round(data.main.temp) + '°C',
                desc: data.weather[0].description,
                icon: `https://openweathermap.org/img/wn/${data.weather[0].icon}@2x.png`,
                city: data.name,
                code: data.weather[0].id,
            };
        });
    }
}

onMounted(() => {
    updateGreeting();
    setInterval(updateTime, 1000);
    fetchQuote();
    fetchWeather();
});

watch(locale, () => {
    updateGreeting();
    fetchWeather();
});
</script>

<template>
    <AppLayout>
        <Head title="Home" />
        <div :class="[
            'min-h-screen w-full flex items-center justify-center transition-all duration-700 relative',
            isNight ? 'bg-gradient-to-b from-[#0a183d] via-[#1a237e] to-[#232946]' : 'bg-gradient-to-b from-blue-100 via-white to-blue-300'
        ]">
            <!-- Animasi bintang jika malam -->
            <div v-if="isNight" class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <div v-for="n in 50" :key="n" :style="{
                    left: Math.random()*100+'vw',
                    top: Math.random()*100+'vh',
                    width: '2px',
                    height: '2px',
                    background: 'white',
                    position: 'absolute',
                    borderRadius: '50%',
                    opacity: Math.random()
                }"></div>
            </div>
            <div class="relative z-10 w-full max-w-5xl mx-auto p-4 md:p-10 rounded-3xl shadow-2xl transition-all duration-500 group overflow-hidden animate-fade-in"
                :class="isNight ? 'bg-[#1a223a]/80 border-blue-900' : 'bg-white/70 border-blue-200 hover:shadow-blue-300'">
                <!-- Avatar user -->
                <div class="absolute left-6 top-6 flex items-center gap-2 z-20">
                    <div v-if="props.avatar" class="w-14 h-14 rounded-full overflow-hidden border-4 border-white shadow-lg">
                        <img :src="props.avatar" alt="Avatar" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-14 h-14 rounded-full bg-gradient-to-br from-blue-700 to-blue-400 flex items-center justify-center text-white text-2xl font-bold border-4 border-white shadow-lg">
                        {{ getInitials(props.nama_lengkap) }}
                    </div>
                </div>
                <div class="ml-24 md:ml-32">
                    <div class="flex flex-col md:flex-row items-center justify-center gap-10 md:gap-20">
                        <!-- Kiri: Sapaan, nama, quotes -->
                        <div class="flex-1 flex flex-col items-center md:items-start text-center md:text-left gap-6">
                            <div class="text-3xl md:text-5xl font-extrabold" :class="isNight ? 'text-white' : 'text-gray-800'">{{ greeting }},</div>
                            <div class="text-2xl md:text-3xl font-bold" :class="isNight ? 'text-blue-100' : 'text-blue-900'">{{ props.nama_lengkap }}</div>
                            <div class="mt-6 max-w-xl mx-auto p-4 rounded-xl min-h-[100px] flex flex-col justify-center border"
                                :class="[
                                    isNight ? 'bg-white/10 text-blue-100 border-blue-300' : 'bg-blue-50/80 text-blue-900 border-blue-100',
                                    'shadow', 'animate-fade-in'
                                ]">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-blue-700 font-semibold" :class="isNight ? 'text-blue-200' : 'text-blue-700'">{{ t('home.quote_of_the_day') }}</span>
                                </div>
                                <div v-if="loadingQuote" class="italic text-gray-400">{{ t('home.loading_quote') }}</div>
                                <template v-else>
                                    <div class="italic text-lg md:text-xl">"{{ quote.text }}"</div>
                                    <div class="mt-2 text-right font-semibold" :class="isNight ? 'text-blue-200' : 'text-blue-700'">- {{ quote.author }}</div>
                                </template>
                            </div>
                        </div>
                        <!-- Kanan: Jam analog, cuaca -->
                        <div class="flex-1 flex flex-col items-center gap-4 md:gap-8">
                            <AnalogClock :date="time" class="scale-125 md:scale-150 mb-2 animate-slide-in" />
                            <div v-if="weather.city && weather.code" class="flex flex-col items-center gap-2 animate-fade-in mt-2">
                                <div class="flex items-center gap-2">
                                    <WeatherIcon :code="weather.code" />
                                </div>
                                <div class="text-xl md:text-2xl font-semibold" :class="isNight ? 'text-blue-100' : 'text-blue-800'">{{ weather.city }}</div>
                                <div class="text-base md:text-lg" :class="isNight ? 'text-blue-200' : 'text-gray-600'">{{ weather.temp }} - {{ weather.desc }}</div>
                            </div>
                        </div>
                    </div>
                    <!-- Ilustrasi cityscape SVG -->
                    <svg class="absolute bottom-0 right-0 w-64 h-32 opacity-40 pointer-events-none select-none" viewBox="0 0 400 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="0" y="60" width="400" height="40" :fill="isNight ? '#23395d' : '#90caf9'" />
                        <rect x="20" y="40" width="30" height="60" :fill="isNight ? '#2e4a7d' : '#64b5f6'" />
                        <rect x="60" y="50" width="20" height="50" :fill="isNight ? '#3b5998' : '#1976d2'" />
                        <rect x="90" y="30" width="40" height="70" :fill="isNight ? '#4f6fa5' : '#42a5f5'" />
                        <rect x="140" y="55" width="25" height="45" :fill="isNight ? '#3b5998' : '#1976d2'" />
                        <rect x="180" y="45" width="35" height="55" :fill="isNight ? '#2e4a7d' : '#64b5f6'" />
                        <rect x="230" y="60" width="20" height="40" :fill="isNight ? '#3b5998' : '#1976d2'" />
                        <rect x="260" y="35" width="30" height="65" :fill="isNight ? '#4f6fa5' : '#42a5f5'" />
                        <rect x="300" y="50" width="25" height="50" :fill="isNight ? '#3b5998' : '#1976d2'" />
                        <rect x="340" y="40" width="40" height="60" :fill="isNight ? '#2e4a7d' : '#64b5f6'" />
                    </svg>
                    <!-- Footer mini -->
                    <div class="w-full text-center text-xs mt-8 opacity-90" :class="isNight ? 'text-blue-200' : 'text-blue-400'">{{ t('home.powered') }} &copy; {{ new Date().getFullYear() }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 1s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slide-in {
    animation: slideIn 1s cubic-bezier(.4,2,.6,1) 0.2s both;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-40px) scale(0.8); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style> 