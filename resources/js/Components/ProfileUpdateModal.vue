<script setup>
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';
import { ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const activeTab = ref('profile');

const form = useForm({
    nama_lengkap: '',
    email: '',
    avatar: null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const previewUrl = ref(null);
const isLoading = ref(false);

const fetchUser = async () => {
    try {
        const { data } = await axios.get('/api/user');
        form.nama_lengkap = data.name;
        form.email = data.email;
        previewUrl.value = data.avatar ? `/storage/${data.avatar}` : null;
    } catch (e) {
        // handle error
    }
};

watch(() => props.show, (val) => {
    if (val) {
        activeTab.value = 'profile';
        const user = usePage().props.auth.user;
        form.nama_lengkap = user.nama_lengkap;
        form.email = user.email;
        previewUrl.value = user.avatar ? `/storage/${user.avatar}` : null;
        form.avatar = null;
    }
});

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.avatar = file;
        previewUrl.value = URL.createObjectURL(file);
    }
};

const submitProfile = () => {
    isLoading.value = true;
    console.log('SUBMIT DATA:', form.nama_lengkap, form.email, form.avatar);
    form.post(route('profile.update'), {
        _method: 'patch',
        preserveScroll: true,
        onSuccess: () => {
            Inertia.reload({ only: ['auth'] });
            Swal.fire('Success', 'Profile updated successfully!', 'success');
            emit('close');
            if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
                URL.revokeObjectURL(previewUrl.value);
            }
        },
        onFinish: () => {
            isLoading.value = false;
        }
    });
};

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            emit('close');
        },
    });
};
</script>

<template>
    <Modal :show="show" @close="emit('close')">
        <div class="p-6 min-w-[350px] max-w-md">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                Update Profile
            </h2>
            <div class="flex border-b mb-4">
                <button :class="['px-4 py-2 -mb-px font-semibold', activeTab === 'profile' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'profile'">Profile</button>
                <button :class="['px-4 py-2 -mb-px font-semibold', activeTab === 'password' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500']" @click="activeTab = 'password'">Password</button>
            </div>
            <div v-if="activeTab === 'profile'">
                <form @submit.prevent="submitProfile" class="space-y-6">
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
                        <InputLabel for="nama_lengkap" value="Nama Lengkap" />
                        <TextInput
                            id="nama_lengkap"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.nama_lengkap"
                            required
                            autofocus
                            autocomplete="name"
                        />
                        <InputError class="mt-2" :message="form.errors.nama_lengkap" />
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

                    <div class="flex justify-end gap-4">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="form.processing || isLoading">
                            <span v-if="isLoading">Saving...</span>
                            <span v-else>SAVE CHANGES</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
            <div v-else-if="activeTab === 'password'">
                <form @submit.prevent="submitPassword" class="space-y-6">
                    <div>
                        <InputLabel for="current_password" value="Current Password" />
                        <TextInput
                            id="current_password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="passwordForm.current_password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="passwordForm.errors.current_password" />
                    </div>
                    <div>
                        <InputLabel for="password" value="New Password" />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="passwordForm.password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="passwordForm.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="passwordForm.errors.password_confirmation" />
                    </div>
                    <div class="flex justify-end gap-4">
                        <button
                            type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            @click="emit('close')"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="passwordForm.processing">
                            Update Password
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </Modal>
</template> 