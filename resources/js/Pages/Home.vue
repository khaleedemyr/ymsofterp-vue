<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AnalogClock from '@/Components/AnalogClock.vue';
import WeatherIcon from '@/Components/WeatherIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AnnouncementList from '@/Components/AnnouncementList.vue';
import { useI18n } from 'vue-i18n';

const page = usePage();
const user = page.props.auth?.user || {};

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
            'min-h-screen w-full transition-all duration-700 relative',
            isNight ? 'bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900' : 'bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50'
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
            
            <!-- Main Content Grid -->
            <div class="relative z-10 w-full max-w-7xl mx-auto p-4 md:p-6 h-screen flex flex-col">
                <!-- Top Section: Welcome Card -->
                <div class="flex-shrink-0 mb-4">
                    <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 md:p-6 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                        <!-- Avatar user -->
                        <div class="flex items-center gap-4 mb-4">
                            <div v-if="user.avatar" class="w-16 h-16 rounded-full overflow-hidden border-3 border-white shadow-lg">
                                <img :src="user.avatar ? `/storage/${user.avatar}` : '/images/avatar-default.png'" alt="Avatar" class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl font-bold border-3 border-white shadow-lg">
                                {{ getInitials(user.nama_lengkap) }}
                            </div>
                            <div class="flex-1">
                                <div class="text-2xl md:text-3xl font-extrabold" :class="isNight ? 'text-white' : 'text-slate-800'">{{ greeting }},</div>
                                <div class="text-lg md:text-xl font-bold" :class="isNight ? 'text-indigo-200' : 'text-indigo-700'">{{ user.nama_lengkap }}</div>
                            </div>
                        </div>
                        
                        <!-- Quote Section -->
                        <div class="p-4 rounded-xl border shadow-lg animate-fade-in"
                            :class="[
                                isNight ? 'bg-slate-700/80 text-white border-slate-600' : 'bg-gradient-to-r from-indigo-50 to-purple-50 text-slate-800 border-indigo-200'
                            ]">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-semibold text-sm" :class="isNight ? 'text-indigo-300' : 'text-indigo-600'">{{ t('home.quote_of_the_day') }}</span>
                            </div>
                            <div v-if="loadingQuote" class="italic text-sm" :class="isNight ? 'text-slate-300' : 'text-slate-500'">{{ t('home.loading_quote') }}</div>
                            <template v-else>
                                <div class="italic text-sm md:text-base" :class="isNight ? 'text-slate-200' : 'text-slate-700'">"{{ quote.text }}"</div>
                                <div class="mt-1 text-right font-semibold text-xs" :class="isNight ? 'text-indigo-300' : 'text-indigo-600'">- {{ quote.author }}</div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Clock, Weather, and Announcements -->
                <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4 min-h-0">
                    <!-- Left: Clock and Weather -->
                    <div class="lg:col-span-1 flex flex-col gap-4">
                        <!-- Clock Card -->
                        <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 hover:shadow-3xl"
                            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                            <div class="flex justify-center">
                                <AnalogClock :date="time" class="scale-100 md:scale-110 animate-slide-in" />
                            </div>
                        </div>
                        
                        <!-- Weather Card -->
                        <div v-if="weather.city && weather.code" class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                            <div class="flex flex-col items-center gap-2">
                                <div class="flex items-center gap-2">
                                    <WeatherIcon :code="weather.code" />
                                </div>
                                <div class="text-lg font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">{{ weather.city }}</div>
                                <div class="text-sm" :class="isNight ? 'text-slate-300' : 'text-slate-600'">{{ weather.temp }} - {{ weather.desc }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Announcements -->
                    <div class="lg:col-span-2">
                        <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 h-full transition-all duration-500 hover:shadow-3xl"
                            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                            <AnnouncementList :is-night="isNight" />
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex-shrink-0 mt-4 text-center text-xs opacity-90" :class="isNight ? 'text-indigo-300' : 'text-indigo-500'">
                    {{ t('home.powered') }} &copy; {{ new Date().getFullYear() }}
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

/* Enhanced shadow effects */
.hover\:shadow-3xl:hover {
    box-shadow: 0 35px 60px -12px rgba(0, 0, 0, 0.25);
}

/* Glass morphism effect */
.backdrop-blur-md {
    backdrop-filter: blur(12px);
}

/* Gradient text effect */
.gradient-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Subtle glow effect */
.glow-effect {
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.1);
}

/* Smooth transitions */
* {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
</style> 