<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RegisterModal from '@/Components/RegisterModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const email = ref('');
const password = ref('');
const remember = ref(false);
const errorMsg = ref('');
const processing = ref(false);
const showRegisterModal = ref(false);

const submit = async () => {
    errorMsg.value = '';
    processing.value = true;
    try {
        await axios.post('/login', {
            email: email.value,
            password: password.value,
            remember: remember.value,
        });
        window.location.href = '/home';
    } catch (error) {
        errorMsg.value = error.response?.data?.message || 'Login gagal';
    } finally {
        processing.value = false;
    }
};

const openRegisterModal = () => {
    showRegisterModal.value = true;
};

const closeRegisterModal = () => {
    showRegisterModal.value = false;
};

const onRegisterSuccess = () => {
    showRegisterModal.value = false;
    // Optionally show success message or redirect
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />
        <div class="w-full max-w-md mx-auto">
            <div class="mb-6 text-center">
                <h2 class="text-2xl font-bold text-gray-800">Login</h2>
                <p class="text-gray-500">Sign in to continue to YMSoft.</p>
            </div>
            <div v-if="status" class="mb-4 font-medium text-sm text-green-600 text-center">
                {{ status }}
            </div>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="email"
                        required
                        autofocus
                        autocomplete="username"
                    />
                </div>
                <div>
                    <InputLabel for="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="password"
                        required
                        autocomplete="current-password"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <Checkbox name="remember" v-model:checked="remember" />
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Forgot your password?
                    </Link>
                </div>
                <PrimaryButton class="w-full mt-2" :class="{ 'opacity-25': processing }" :disabled="processing">
                    Log in
                </PrimaryButton>
                <div v-if="errorMsg" class="text-red-600 text-sm mt-2 text-center">{{ errorMsg }}</div>
            </form>
            
            <!-- Register Button -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600 mb-3">Belum punya akun?</p>
                <button
                    @click="openRegisterModal"
                    class="w-full px-4 py-2 text-sm font-medium text-blue-600 bg-white border border-blue-600 rounded-md hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                    Daftar Akun Baru
                </button>
            </div>
        </div>
        
        <!-- Register Modal -->
        <RegisterModal 
            :show="showRegisterModal" 
            @close="closeRegisterModal"
            @success="onRegisterSuccess"
        />
    </GuestLayout>
</template>
