<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    show: Boolean,
    mode: String, // 'create' | 'edit'
    budget: Object,
    categories: Array,
    subCategories: Array,
    outlets: Array,
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
    category_id: '',
    sub_category_id: '',
    outlet_id: '',
    budget: '',
});

const errors = ref({});

// Computed property for filtered sub categories
const filteredSubCategories = computed(() => {
    if (!form.category_id || !props.subCategories) return [];
    return props.subCategories.filter(sub => sub.category_id == form.category_id && sub.status === 'active');
});

// Watch for category changes to reset sub category
watch(() => form.category_id, (newCategoryId) => {
    form.sub_category_id = '';
});

// Watch for budget changes (edit mode)
watch(() => props.budget, (newBudget) => {
    if (newBudget && props.mode === 'edit') {
        form.category_id = newBudget.category_id || '';
        form.sub_category_id = newBudget.sub_category_id || '';
        form.outlet_id = newBudget.outlet_id || '';
        form.budget = newBudget.budget || '';
    }
}, { immediate: true });

// Reset form when modal opens for create mode
watch(() => props.show, (show) => {
    if (show && props.mode === 'create') {
        form.reset();
        errors.value = {};
    }
});

const submitForm = () => {
    // Validation
    errors.value = {};
    
    if (!form.category_id) {
        errors.value.category_id = 'Category is required';
        return;
    }
    if (!form.sub_category_id) {
        errors.value.sub_category_id = 'Sub Category is required';
        return;
    }
    if (!form.outlet_id) {
        errors.value.outlet_id = 'Outlet is required';
        return;
    }
    if (!form.budget || form.budget <= 0) {
        errors.value.budget = 'Budget must be greater than 0';
        return;
    }

    // Show loading
    Swal.fire({
        title: 'Menyimpan Data...',
        text: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    if (props.mode === 'create') {
        form.post('/locked-budget-food-categories', {
            onSuccess: (page) => {
                Swal.close();
                Swal.fire('Berhasil', 'Data berhasil disimpan!', 'success');
                emit('success');
                closeModal();
            },
            onError: (errorBag) => {
                Swal.close();
                errors.value = errorBag;
            }
        });
    } else {
        form.put(`/locked-budget-food-categories/${props.budget.id}`, {
            onSuccess: (page) => {
                Swal.close();
                Swal.fire('Berhasil', 'Data berhasil diperbarui!', 'success');
                emit('success');
                closeModal();
            },
            onError: (errorBag) => {
                Swal.close();
                errors.value = errorBag;
            }
        });
    }
};

const closeModal = () => {
    emit('close');
    form.reset();
    errors.value = {};
};

const formatBudget = (value) => {
    const numericValue = value.replace(/[^\d]/g, '');
    return numericValue;
};

const onBudgetInput = (event) => {
    const formatted = formatBudget(event.target.value);
    form.budget = formatted;
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ mode === 'create' ? 'Tambah Budget' : 'Edit Budget' }}
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitForm">
                    <div class="space-y-4">
                        <!-- Category Selection -->
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="category_id"
                                v-model="form.category_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': errors.category_id }"
                            >
                                <option value="">Select Category</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                            <p v-if="errors.category_id" class="mt-1 text-sm text-red-600">{{ errors.category_id }}</p>
                        </div>

                        <!-- Sub Category Selection -->
                        <div>
                            <label for="sub_category_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Sub Category <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="sub_category_id"
                                v-model="form.sub_category_id"
                                :disabled="!form.category_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                :class="{ 'border-red-500': errors.sub_category_id }"
                            >
                                <option value="">Select Sub Category</option>
                                <option v-for="subCategory in filteredSubCategories" :key="subCategory.id" :value="subCategory.id">
                                    {{ subCategory.name }}
                                </option>
                            </select>
                            <p v-if="errors.sub_category_id" class="mt-1 text-sm text-red-600">{{ errors.sub_category_id }}</p>
                        </div>

                        <!-- Outlet Selection -->
                        <div>
                            <label for="outlet_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Outlet <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="outlet_id"
                                v-model="form.outlet_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': errors.outlet_id }"
                            >
                                <option value="">Select Outlet</option>
                                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                                    {{ outlet.nama_outlet }}
                                </option>
                            </select>
                            <p v-if="errors.outlet_id" class="mt-1 text-sm text-red-600">{{ errors.outlet_id }}</p>
                        </div>

                        <!-- Budget Input -->
                        <div>
                            <label for="budget" class="block text-sm font-medium text-gray-700 mb-1">
                                Budget <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">Rp</span>
                                </div>
                                <input
                                    id="budget"
                                    type="text"
                                    :value="form.budget"
                                    @input="onBudgetInput"
                                    placeholder="0"
                                    class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': errors.budget }"
                                />
                            </div>
                            <p v-if="errors.budget" class="mt-1 text-sm text-red-600">{{ errors.budget }}</p>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-6 flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ mode === 'create' ? 'Simpan' : 'Update' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
