<script setup>
import { ref, computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    success: {
        type: Boolean,
        default: false,
    },
    message: {
        type: String,
        default: '',
    },
    alreadyVerified: {
        type: Boolean,
        default: false,
    },
    member: {
        type: Object,
        default: null,
    },
});

const resending = ref(false);
const resendMessage = ref('');
const resendError = ref('');

const iconClass = computed(() => {
    if (props.success) {
        return props.alreadyVerified ? 'text-yellow-500' : 'text-green-500';
    }
    return 'text-red-500';
});

const iconSymbol = computed(() => {
    if (props.success) {
        return props.alreadyVerified ? 'ℹ' : '✓';
    }
    return '✗';
});

const resendVerification = async () => {
    if (!props.member?.email) {
        resendError.value = 'Email not found. Please contact support.';
        return;
    }

    resending.value = true;
    resendMessage.value = '';
    resendError.value = '';

    try {
        const response = await fetch('/api/mobile/member/auth/resend-verification', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                email: props.member.email,
            }),
        });

        const data = await response.json();

        if (data.success) {
            resendMessage.value = data.message || 'Verification email has been sent. Please check your inbox.';
        } else {
            resendError.value = data.message || 'Failed to send verification email. Please try again.';
        }
    } catch (error) {
        resendError.value = 'An error occurred. Please try again later.';
    } finally {
        resending.value = false;
    }
};
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification - Justus Group" />

        <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-yellow-400 via-yellow-300 to-yellow-500 py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md w-full space-y-8 bg-white rounded-2xl shadow-2xl p-8">
                <!-- Logo -->
                <div class="text-center">
                    <h1 class="text-4xl font-bold">
                        <span class="text-black">JUST</span><span class="text-yellow-500">US</span>
                    </h1>
                    <p class="text-gray-600 text-sm tracking-widest mt-1">GROUP</p>
                </div>

                <!-- Icon -->
                <div class="text-center">
                    <div :class="['text-6xl mb-4', iconClass]">
                        {{ iconSymbol }}
                    </div>
                </div>

                <!-- Title -->
                <h2 class="text-center text-2xl font-bold text-gray-900">
                    <span v-if="success && !alreadyVerified">Email Verified!</span>
                    <span v-else-if="alreadyVerified">Already Verified</span>
                    <span v-else>Verification Failed</span>
                </h2>

                <!-- Message -->
                <div 
                    :class="[
                        'rounded-lg p-4 text-center',
                        success && !alreadyVerified ? 'bg-green-50 text-green-800 border border-green-200' :
                        alreadyVerified ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' :
                        'bg-red-50 text-red-800 border border-red-200'
                    ]"
                >
                    <p class="text-sm">{{ message || 'Processing...' }}</p>
                </div>

                <!-- Member Info (if available) -->
                <div v-if="member" class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <p class="text-sm">
                        <span class="font-semibold text-gray-700">Member ID:</span>
                        <span class="text-gray-900 ml-2">{{ member.member_id }}</span>
                    </p>
                    <p class="text-sm">
                        <span class="font-semibold text-gray-700">Name:</span>
                        <span class="text-gray-900 ml-2">{{ member.nama_lengkap }}</span>
                    </p>
                    <p class="text-sm">
                        <span class="font-semibold text-gray-700">Email:</span>
                        <span class="text-gray-900 ml-2">{{ member.email }}</span>
                    </p>
                </div>

                <!-- Success Message -->
                <div v-if="success && !alreadyVerified" class="text-center text-gray-600 text-sm">
                    <p>You can now use all features of the Justus Group member app.</p>
                </div>

                <!-- Resend Button (if failed) -->
                <div v-if="!success && member" class="space-y-3">
                    <PrimaryButton
                        @click="resendVerification"
                        :disabled="resending"
                        class="w-full"
                    >
                        <span v-if="resending" class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Sending...
                        </span>
                        <span v-else>Resend Verification Email</span>
                    </PrimaryButton>

                    <!-- Resend Messages -->
                    <div v-if="resendMessage" class="bg-green-50 text-green-800 text-sm rounded-lg p-3 border border-green-200">
                        {{ resendMessage }}
                    </div>
                    <div v-if="resendError" class="bg-red-50 text-red-800 text-sm rounded-lg p-3 border border-red-200">
                        {{ resendError }}
                    </div>
                </div>

                <!-- Contact Support (if no member info) -->
                <div v-if="!success && !member" class="text-center text-gray-600 text-sm">
                    <p>Please contact support if you continue to experience issues.</p>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

