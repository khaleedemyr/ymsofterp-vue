<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import SignaturePad from 'signature_pad';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    show: Boolean,
    user: Object
});

const emit = defineEmits(['close', 'saved']);

const signaturePad = ref(null);
const canvas = ref(null);
const activeTab = ref('draw'); // 'draw' or 'upload'
const uploadedImage = ref(null);
const previewUrl = ref('');
const isLoading = ref(false);
const existingSignature = ref(props.user.signature_path ? `/storage/${props.user.signature_path}` : null);

const form = useForm({
    signature: null,
    type: 'draw' // 'draw' or 'upload'
});

// Watch for modal visibility
watch(() => props.show, (newVal) => {
    if (newVal && activeTab.value === 'draw') {
        nextTick(() => {
            initSignaturePad();
        });
    }
}, { immediate: true });

// Watch for window resize to adjust canvas
const resizeCanvas = () => {
    if (canvas.value) {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.value.width = canvas.value.offsetWidth * ratio;
        canvas.value.height = canvas.value.offsetHeight * ratio;
        const context = canvas.value.getContext("2d");
        context.scale(ratio, ratio);
        if (signaturePad.value) {
            signaturePad.value.clear(); // Clear and reinitialize
            initSignaturePad();
        }
    }
};

onMounted(() => {
    window.addEventListener('resize', resizeCanvas);
});

const initSignaturePad = () => {
    if (!canvas.value) return;
    
    // Clear existing instance
    if (signaturePad.value) {
        signaturePad.value.clear();
        signaturePad.value.off();
    }

    // Set canvas dimensions
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.value.width = canvas.value.offsetWidth * ratio;
    canvas.value.height = canvas.value.offsetHeight * ratio;
    const context = canvas.value.getContext("2d");
    context.scale(ratio, ratio);

    // Initialize new instance
    signaturePad.value = new SignaturePad(canvas.value, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 0, 0)',
        velocityFilterWeight: 0.7,
        minWidth: 0.5,
        maxWidth: 2.5,
        throttle: 16
    });
};

const clearSignature = () => {
    if (signaturePad.value) {
        signaturePad.value.clear();
    }
};

const handleFileUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        uploadedImage.value = file;
        previewUrl.value = URL.createObjectURL(file);
        form.type = 'upload';
        form.signature = file;
    }
};

const saveSignature = () => {
    if (activeTab.value === 'draw' && signaturePad.value) {
        if (!signaturePad.value.isEmpty()) {
            isLoading.value = true;
            const signatureData = signaturePad.value.toDataURL('image/png');
            // Convert base64 to blob
            fetch(signatureData)
                .then(res => res.blob())
                .then(blob => {
                    form.type = 'draw';
                    form.signature = new File([blob], 'signature.png', { type: 'image/png' });
                    submitForm();
                });
        }
    } else if (activeTab.value === 'upload' && uploadedImage.value) {
        isLoading.value = true;
        submitForm();
    }
};

const submitForm = () => {
    form.post(route('signature.store'), {
        preserveScroll: true,
        onSuccess: (response) => {
            isLoading.value = false;
            // Update existing signature preview with new path
            if (response?.props?.auth?.user?.signature_path) {
                existingSignature.value = `/storage/${response.props.auth.user.signature_path}`;
            }
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Signature saved successfully',
                timer: 2000,
                showConfirmButton: false
            });
            emit('saved');
            closeModal();
        },
        onError: () => {
            isLoading.value = false;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to save signature',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
};

const closeModal = () => {
    form.reset();
    uploadedImage.value = null;
    previewUrl.value = '';
    if (signaturePad.value) {
        signaturePad.value.clear();
    }
    emit('close');
};

const switchTab = (tab) => {
    activeTab.value = tab;
    if (tab === 'draw') {
        nextTick(() => {
            initSignaturePad();
        });
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 transition-opacity" @click="closeModal">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">E-Signature</h3>
                            
                            <!-- Existing signature preview -->
                            <div v-if="existingSignature" class="mb-4 p-4 border rounded-lg bg-gray-50">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Current Signature</h4>
                                <img :src="existingSignature" alt="Current Signature" class="max-h-32 mx-auto">
                            </div>
                            
                            <!-- Tab buttons -->
                            <div class="flex border-b mb-4">
                                <button 
                                    @click="switchTab('draw')"
                                    :class="['px-4 py-2 font-medium', activeTab === 'draw' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500']"
                                >
                                    Draw Signature
                                </button>
                                <button 
                                    @click="switchTab('upload')"
                                    :class="['px-4 py-2 font-medium', activeTab === 'upload' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500']"
                                >
                                    Upload Image
                                </button>
                            </div>

                            <!-- Draw signature tab -->
                            <div v-show="activeTab === 'draw'" class="mb-4">
                                <div class="border rounded-lg p-2 bg-white">
                                    <canvas 
                                        ref="canvas"
                                        style="touch-action: none; width: 100%; height: 200px;"
                                        class="border rounded"
                                    ></canvas>
                                </div>
                                <button 
                                    @click="clearSignature"
                                    class="mt-2 px-3 py-1 text-sm text-gray-600 border rounded hover:bg-gray-100"
                                >
                                    Clear
                                </button>
                            </div>

                            <!-- Upload image tab -->
                            <div v-show="activeTab === 'upload'" class="mb-4">
                                <div class="border rounded-lg p-4 bg-gray-50">
                                    <div v-if="!previewUrl" class="text-center">
                                        <label class="cursor-pointer inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                                            <input 
                                                type="file" 
                                                class="hidden" 
                                                accept="image/*"
                                                @change="handleFileUpload"
                                            >
                                            Choose Image
                                        </label>
                                        <p class="mt-2 text-sm text-gray-500">PNG, JPG up to 2MB</p>
                                    </div>
                                    <div v-else class="text-center">
                                        <img :src="previewUrl" class="max-h-48 mx-auto mb-2">
                                        <button 
                                            @click="uploadedImage = null; previewUrl = ''"
                                            class="px-3 py-1 text-sm text-gray-600 border rounded hover:bg-gray-100"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button 
                        @click="saveSignature"
                        :disabled="form.processing || isLoading"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="isLoading" class="mr-2">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                        Save Signature
                    </button>
                    <button 
                        @click="closeModal"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template> 