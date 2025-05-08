<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

function handleLogin() {
    if (props.canLogin) {
        router.visit(route('dashboard'));
    } else {
        router.visit(route('login'));
    }
}

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="YMSoft ERP" />
    <div class="min-h-screen flex flex-col justify-center items-center bg-gradient-to-br from-gray-50 via-white to-blue-100">
        <div class="w-full max-w-md bg-white/90 rounded-2xl shadow-xl p-8 flex flex-col items-center">
            <img src="/images/logo.png" alt="YMSoft ERP Logo" class="h-20 mb-6 drop-shadow-lg" />
            <h1 class="text-3xl font-extrabold text-gray-900 mb-2 tracking-wide text-center">YMSoft ERP</h1>
            <!-- Tagline kosong, bisa diisi nanti -->
            <p class="mb-8 text-gray-500 text-center">Crafted with ❤️ by IT Department-Justus Group</p>
            <button
                @click="handleLogin"
                class="w-full py-3 rounded-lg text-lg font-semibold text-white bg-[#1a237e] hover:bg-[#283593] transition-all duration-200 shadow-md focus:outline-none focus:ring-2 focus:ring-[#1a237e]/50 focus:ring-offset-2 text-center"
            >
                {{ props.canLogin ? 'Go to Dashboard' : 'Login' }}
            </button>
        </div>
        <div class="mt-10 text-xs text-gray-400">&copy; {{ new Date().getFullYear() }} YMSoft ERP. All rights reserved.</div>
    </div>
</template>
