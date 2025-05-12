<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    avatar: null,
});

const previewUrl = ref(user.avatar ? `/storage/${user.avatar}` : null);

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.avatar = file;
        previewUrl.value = URL.createObjectURL(file);
    }
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Update your account's profile information and email address.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'), {
                preserveScroll: true,
                onSuccess: () => {
                    if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
                        URL.revokeObjectURL(previewUrl.value);
                    }
                }
            })"
            class="mt-6 space-y-6"
        >
            <!-- Avatar Upload -->
            <div>
                <InputLabel for="avatar" value="Profile Picture" />
                <div class="mt-2 flex items-center gap-4">
                    <div class="relative">
                        <div class="w-20 h-20 rounded-full overflow-hidden border-2 border-gray-200">
                            <img 
                                v-if="previewUrl" 
                                :src="previewUrl" 
                                alt="Avatar preview" 
                                class="w-full h-full object-cover"
                            />
                            <div 
                                v-else 
                                class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-400"
                            >
                                <i class="fas fa-user text-2xl"></i>
                            </div>
                        </div>
                        <label 
                            for="avatar-upload" 
                            class="absolute bottom-0 right-0 bg-blue-500 text-white rounded-full p-1 cursor-pointer hover:bg-blue-600"
                        >
                            <i class="fas fa-camera"></i>
                        </label>
                        <input 
                            id="avatar-upload" 
                            type="file" 
                            class="hidden" 
                            accept="image/*"
                            @change="handleFileUpload"
                        />
                    </div>
                    <div class="text-sm text-gray-500">
                        Click the camera icon to change your profile picture
                    </div>
                </div>
                <InputError class="mt-2" :message="form.errors.avatar" />
            </div>

            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
