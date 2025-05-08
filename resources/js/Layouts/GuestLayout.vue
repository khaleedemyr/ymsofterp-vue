<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-[#e8ecf4]">
        <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl flex overflow-hidden my-12">
            <!-- Panel kiri: Form Login -->
            <div class="w-full md:w-1/2 flex flex-col justify-center items-center px-8 py-12">
                <ApplicationLogo class="h-10 w-auto mb-6" />
                <slot />
            </div>
            <!-- Panel kanan: Branding -->
            <div class="hidden md:flex w-1/2 flex-col justify-between items-center bg-gradient-to-br from-[#1a237e] to-blue-500 p-10 relative">
                <div class="w-full flex flex-col items-center mt-8">
                    <img src="/images/logojustusgroup.png" alt="Justus Group Logo" class="h-[400px] mb-6 drop-shadow-xl" />
                    <h2 class="text-white text-2xl font-bold mb-2 text-center">Nice to see you again</h2>
                    <p class="text-blue-100 text-center">Enter your details and start your journey with us.</p>
                </div>
                <div class="w-full text-center text-xs text-blue-100 mb-2">&copy; {{ new Date().getFullYear() }} YMSoft. Crafted with <span class='text-pink-400'>&hearts;</span> by IT Department</div>
        </div>
        </div>
    </div>
</template>
