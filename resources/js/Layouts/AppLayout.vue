<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import ESignatureModal from '@/Components/ESignatureModal.vue';
import NavLink from '@/Components/NavLink.vue';

const sidebarOpen = ref(true);
const showLang = ref(false);
const { locale, t } = useI18n();

const menuGroups = [
    {
        title: () => t('sidebar.main'),
        icon: 'fa-solid fa-bars',
        menus: [
            { name: () => t('sidebar.dashboard'), icon: 'fa-solid fa-home', route: '/home' },
            { name: () => 'Dashboard Outlet', icon: 'fa-solid fa-map-location-dot', route: '/dashboard-outlet' },
            { name: () => t('sidebar.users'), icon: 'fa-solid fa-users', route: '/users' },
        ],
    },
   
    {
        title: () => t('sidebar.maintenance'),
        icon: 'fa-solid fa-screwdriver-wrench',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.mt_dashboard'), icon: 'fa-solid fa-gauge', route: route('dashboard.maintenance') },
            { name: () => t('sidebar.maintenance_order'), icon: 'fa-solid fa-clipboard-check', route: '/maintenance-order' },
            { name: () => 'Kalender Jadwal', icon: 'fa-solid fa-calendar-alt', route: '/maintenance-order/schedule-calendar' },
            { name: () => 'MT PO Payment', icon: 'fa-solid fa-money-bill-wave', route: route('mt-po-payment.index') },
        ],
    },
    {
        title: () => 'Master Data',
        icon: 'fa-solid fa-database',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Categories', icon: 'fa-solid fa-tags', route: '/categories' },
            { name: () => 'Sub Category', icon: 'fa-solid fa-tag', route: '/sub-categories' },
            { name: () => 'Units', icon: 'fa-solid fa-ruler', route: '/units' },
            { name: () => 'Items', icon: 'fa-solid fa-boxes-stacked', route: '/items' },
            { name: () => 'Menu Type', icon: 'fa-solid fa-list', route: '/menu-types' },
            { name: () => 'Modifiers', icon: 'fa-solid fa-sliders', route: '/modifiers' },
            { name: () => 'Modifier Options', icon: 'fa-solid fa-sliders', route: '/modifier-options' },
            { name: () => 'Warehouses', icon: 'fa-solid fa-warehouse', route: '/warehouses' },
            { name: () => 'Warehouse Division', icon: 'fa-solid fa-sitemap', route: '/warehouse-divisions' },
            { name: () => 'Outlets', icon: 'fa-solid fa-store', route: '/outlets' },
            { name: () => 'Customers', icon: 'fa-solid fa-users', route: '/customers' },
            { name: () => 'Regions', icon: 'fa-solid fa-globe-asia', route: '/regions' },
        ],
    },
    {
        title: () => 'Warehouse Management',
        icon: 'fa-solid fa-warehouse',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Purchase Requisition Foods', icon: 'fa-solid fa-file-invoice', route: '/pr-foods' },
        ],
    },
];

const languages = [
    { code: 'id', label: 'Indonesia' },
    { code: 'en', label: 'English' },
];
const currentLang = ref('id');

function toggleGroup(group) {
    if (group.collapsible) group.open.value = !group.open.value;
}

function setLang(code) {
    currentLang.value = code;
    locale.value = code;
    localStorage.setItem('currentLang', code);
}

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

const user = usePage().props.auth?.user || { name: 'User', avatar: null };
const avatarUrl = user.avatar || '/images/avatar-default.png';
const showProfileDropdown = ref(false);
const showESignatureModal = ref(false);

// Notification state
const notifications = ref([]);
const showNotifDropdown = ref(false);
const unreadCount = ref(0);
const loading = ref(false);
const lastNotificationIds = ref(new Set()); // Track last known notification IDs
const notificationSound = ref(null);

// Function to play notification sound
function playNotificationSound() {
    if (!notificationSound.value) return;
    
    try {
        notificationSound.value.currentTime = 0; // Reset audio to start
        notificationSound.value.play().catch(error => {
            console.log('Audio play failed:', error);
        });
    } catch (error) {
        console.log('Audio play failed:', error);
    }
}

async function fetchNotifications() {
    try {
        loading.value = true;
        const response = await axios.get('/api/notifications');
        const newNotifications = response.data;
        
        // Check for new unread notifications
        newNotifications.forEach(notif => {
            if (!lastNotificationIds.value.has(notif.id) && !notif.is_read) {
                // This is a new unread notification, show toast
                showToast({ 
                    title: notif.type === 'success' ? 'Success' : notif.type === 'error' ? 'Error' : 'Info',
                    message: notif.message, 
                    type: notif.type 
                });
                
                // Play notification sound once
                playNotificationSound();
            }
        });
        
        // Update last known notification IDs
        lastNotificationIds.value = new Set(newNotifications.map(n => n.id));
        
        notifications.value = newNotifications;
    } catch (error) {
        console.error('Error fetching notifications:', error);
    } finally {
        loading.value = false;
    }
}

async function fetchUnreadCount() {
    try {
        const response = await axios.get('/api/notifications/unread-count');
        unreadCount.value = response.data.count;
    } catch (error) {
        console.error('Error fetching unread count:', error);
    }
}

async function markAsRead(id) {
    try {
        await axios.post(`/api/notifications/${id}/read`);
        await fetchUnreadCount();
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

async function markAllAsRead() {
    try {
        await axios.post('/api/notifications/read-all');
        await fetchUnreadCount();
        await fetchNotifications();
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
    }
}

function handleNotifClick(notif) {
    markAsRead(notif.id);
    showToast({ 
        title: notif.type === 'success' ? 'Success' : notif.type === 'error' ? 'Error' : 'Info',
        message: notif.message, 
        type: notif.type 
    });
    showNotifDropdown.value = false;
}

// Fetch notifications on mount and every 30 seconds
onMounted(async () => {
    await fetchNotifications();
    await fetchUnreadCount();
    
    setInterval(async () => {
        await fetchNotifications();
        await fetchUnreadCount();
    }, 30000);
});

// Toast state
const toasts = ref([]);
function showToast({ title, message, type = 'info', duration = 4000 }) {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, title, message, type });
    setTimeout(() => {
        removeToast(id);
    }, duration);
}

function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}

// Example: show toast on mount (can be removed)
onMounted(() => {
    // showToast({ title: 'Welcome', message: 'You have 2 new notifications!', type: 'success' });
});
</script>

<template>
<div class="min-h-screen flex bg-gray-100">
    <!-- Audio element for notification sound -->
    <audio ref="notificationSound" src="/sounds/aya_gawean_anyar_ringtone.mp3" preload="auto"></audio>
    
    <!-- Sidebar -->
    <aside :class="['transition-all duration-300 bg-white shadow-lg border-r border-gray-200 flex flex-col fixed z-30 h-full', sidebarOpen ? 'w-64' : 'w-20']">
        <div class="flex items-center justify-between h-20 border-b border-gray-200 px-4">
            <img v-if="sidebarOpen" src="/images/logo.png" alt="Logo" class="h-12 w-auto transition-all duration-300" />
            <img v-else src="/images/logo-icon.png" alt="Logo Icon" class="h-10 w-10 transition-all duration-300" />
            <button @click="toggleSidebar" class="text-gray-400 hover:text-blue-500 transition-all ml-2">
                <i :class="sidebarOpen ? 'fas fa-angle-double-left' : 'fas fa-angle-double-right'"></i>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto py-4">
            <div v-for="group in menuGroups" :key="group.title" class="mb-2">
                <div class="px-6 py-2 text-xs font-bold text-gray-500 uppercase flex items-center gap-2 justify-between cursor-pointer"
                    @click="toggleGroup(group)" v-if="group.collapsible">
                    <span class="text-base"><i :class="group.icon"></i></span>
                    <span v-if="sidebarOpen">{{ typeof group.title === 'function' ? group.title() : group.title }}</span>
                    <svg v-if="sidebarOpen" :class="['w-4 h-4 transition-transform', group.open.value ? 'rotate-90' : '']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                </div>
                <div v-else class="px-6 py-2 text-xs font-bold text-gray-500 uppercase flex items-center gap-2" v-if="sidebarOpen || group.icon">
                    <span class="text-base"><i :class="group.icon"></i></span>
                    <span v-if="sidebarOpen">{{ typeof group.title === 'function' ? group.title() : group.title }}</span>
                </div>
                <div v-show="!group.collapsible || group.open.value">
                    <Link v-for="menu in group.menus" :key="menu.name" :href="menu.route" class="flex items-center gap-3 px-4 py-2 my-1 rounded-lg text-gray-700 hover:bg-blue-100 transition-all"
                        :class="sidebarOpen ? 'justify-start' : 'justify-center'">
                        <span class="text-lg w-7 flex justify-center"><i :class="menu.icon"></i></span>
                        <span v-if="sidebarOpen" class="whitespace-nowrap">{{ typeof menu.name === 'function' ? menu.name() : menu.name }}</span>
                    </Link>
                </div>
            </div>
            <NavLink :href="route('announcement.index')" :active="route().current('announcement.index')" class="flex items-center gap-3 px-4 py-2 my-1 rounded-lg text-gray-700 hover:bg-blue-100 transition-all"
                :class="sidebarOpen ? 'justify-start' : 'justify-center'">
                <span class="text-lg w-7 flex justify-center"><i class="fa fa-bullhorn mr-2"></i></span>
                <span v-if="sidebarOpen" class="whitespace-nowrap">Announcement</span>
            </NavLink>
        </nav>
    </aside>
    <!-- Main Content -->
    <div :class="['flex-1 flex flex-col min-h-screen transition-all duration-300', sidebarOpen ? 'ml-64' : 'ml-20']">
        <!-- Navbar -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center px-6 justify-between shadow-sm">
            <div class="flex items-center gap-4">
                <button @click="toggleSidebar" class="md:hidden text-gray-500 focus:outline-none">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div class="flex items-center gap-4">
                <!-- Language -->
                <div class="relative">
                    <button class="flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100" @click="showLang = !showLang">
                        <img :src="currentLang === 'id' ? '/images/indonesia.png' : '/images/united-states.png'" alt="Lang" class="w-5 h-5 rounded-full" />
                        <span class="hidden md:inline">{{ languages.find(l => l.code === currentLang)?.label }}</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div v-if="showLang" class="absolute right-0 mt-2 w-40 bg-white border rounded shadow z-50">
                        <div v-for="lang in languages" :key="lang.code" @click="setLang(lang.code); showLang = false" class="flex items-center gap-2 px-4 py-2 hover:bg-blue-50 cursor-pointer">
                            <img :src="lang.code === 'id' ? '/images/indonesia.png' : '/images/united-states.png'" alt="Lang" class="w-5 h-5 rounded-full" />
                            <span>{{ lang.label }}</span>
                        </div>
                    </div>
                </div>
                <!-- Fullscreen -->
                <button @click="toggleFullscreen" class="p-2 rounded hover:bg-gray-100">
                    <i class="fas fa-expand"></i>
                </button>
                <!-- Notif -->
                <div class="relative">
                    <button class="p-2 rounded hover:bg-gray-100 relative" @click="showNotifDropdown = !showNotifDropdown">
                        <i class="fas fa-bell"></i>
                        <span v-if="unreadCount > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1">{{ unreadCount }}</span>
                    </button>
                    <div v-if="showNotifDropdown" class="absolute right-0 mt-2 w-80 bg-white border rounded shadow-lg z-50 max-h-96 overflow-y-auto">
                        <div class="flex items-center justify-between px-4 py-2 border-b">
                            <span class="font-bold text-gray-700">Notifikasi</span>
                            <button class="text-xs text-blue-500 hover:underline" @click="markAllAsRead">Tandai semua dibaca</button>
                        </div>
                        <div v-if="loading" class="px-4 py-6 text-center">
                            <i class="fas fa-spinner fa-spin text-blue-500"></i>
                        </div>
                        <div v-else-if="notifications.length === 0" class="px-4 py-6 text-center text-gray-400">Tidak ada notifikasi</div>
                        <div v-else>
                            <div v-for="notif in notifications" :key="notif.id" @click="handleNotifClick(notif)" class="px-4 py-3 border-b last:border-b-0 cursor-pointer hover:bg-blue-50 flex gap-2" :class="notif.is_read ? 'bg-gray-50' : 'bg-blue-50/50'">
                                <div class="flex-shrink-0 mt-1">
                                    <i :class="['fas', notif.type === 'success' ? 'fa-check-circle text-green-400' : notif.type === 'error' ? 'fa-exclamation-circle text-red-400' : 'fa-info-circle text-blue-400']"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-sm text-gray-800">{{ notif.type === 'success' ? 'Success' : notif.type === 'error' ? 'Error' : 'Info' }}</div>
                                    <div class="text-xs text-gray-600">{{ notif.message }}</div>
                                    <div class="text-xs text-gray-400 mt-1">{{ notif.time }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Avatar -->
                <div class="relative">
                    <button @click="showProfileDropdown = !showProfileDropdown" class="flex items-center gap-2 focus:outline-none">
                        <img :src="avatarUrl" alt="Avatar" class="w-9 h-9 rounded-full object-cover border-2 border-blue-200" />
                        <span class="hidden md:inline font-semibold text-gray-700">{{ user.name }}</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div v-if="showProfileDropdown" class="absolute right-0 mt-2 w-64 bg-white border rounded shadow z-50">
                        <div class="px-4 py-3 border-b">
                            <div class="font-bold text-gray-800">{{ user.nama_lengkap }}</div>
                            <div class="text-xs text-gray-500" v-if="user.jabatan">{{ user.jabatan }}</div>
                            <div class="text-xs text-gray-500" v-if="user.divisi">{{ user.divisi }}</div>
                            <div class="text-xs text-gray-500" v-if="user.outlet">{{ user.outlet }}</div>
                        </div>
                        <Link href="/profile" class="flex items-center gap-2 px-4 py-2 hover:bg-blue-50">
                            <i class="fa-solid fa-user"></i> {{ t('profile.profile') }}
                        </Link>
                        <button @click="showESignatureModal = true; showProfileDropdown = false" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50">
                            <i class="fa-solid fa-pen-nib"></i> {{ t('profile.esign') }}
                        </button>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50"
                        >
                                <i class="fa-solid fa-right-from-bracket"></i> {{ t('profile.logout') }}
                        </Link>
                    </div>
                </div>
            </div>
        </header>
        <!-- Slot konten -->
        <main class="flex-1 p-6 bg-gray-50">
            <slot />
        </main>
        
        <!-- Toast Notification Container -->
        <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 items-end">
            <transition-group name="toast-slide" tag="div">
                <div v-for="toast in toasts" :key="toast.id" 
                    class="min-w-[300px] max-w-sm bg-white border-l-4 shadow-lg px-4 py-3 rounded-lg mb-2 flex flex-col gap-1 transform transition-all duration-300"
                    :class="[
                        toast.type === 'success' ? 'border-green-500' : 
                        toast.type === 'error' ? 'border-red-500' : 
                        'border-blue-500'
                    ]">
                    <div class="flex items-start gap-2">
                        <i :class="[
                            'fas mt-1',
                            toast.type === 'success' ? 'fa-check-circle text-green-500' : 
                            toast.type === 'error' ? 'fa-exclamation-circle text-red-500' : 
                            'fa-info-circle text-blue-500'
                        ]"></i>
                        <div class="flex-1">
                            <div class="font-bold text-gray-800">{{ toast.title }}</div>
                            <div class="text-sm text-gray-600">{{ toast.message }}</div>
                        </div>
                        <button @click="removeToast(toast.id)" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </transition-group>
        </div>
    </div>

    <ESignatureModal 
        :show="showESignatureModal"
        :user="user"
        @close="showESignatureModal = false"
        @saved="showProfileDropdown = false"
    />
</div>
</template>

<style scoped>
.fas { font-family: 'Font Awesome 5 Free'; font-weight: 900; }

.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.3s ease;
}

.toast-slide-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-slide-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.toast-slide-move {
    transition: transform 0.3s ease;
}
</style> 