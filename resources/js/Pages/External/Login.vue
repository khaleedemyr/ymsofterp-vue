<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('external.login.store'));
};
</script>

<template>
    <Head title="External Login" />

    <div class="min-h-screen bg-gray-100 flex items-center justify-center px-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Login External</h1>
            <p class="text-sm text-gray-500 mb-6">Akses khusus report revenue.</p>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500"
                        required
                    />
                    <p v-if="form.errors.email" class="text-sm text-red-600 mt-1">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500"
                        required
                    />
                    <p v-if="form.errors.password" class="text-sm text-red-600 mt-1">{{ form.errors.password }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-gray-300" />
                    Ingat saya
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-2.5 rounded-lg bg-blue-700 text-white font-semibold hover:bg-blue-800 disabled:opacity-60"
                >
                    Masuk
                </button>
            </form>
        </div>
    </div>
</template>
