<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    budget: Object,
    categories: Array,
    outlets: Array,
});

const form = useForm({
    category_id: props.budget?.category_id || '',
    outlet_id: props.budget?.outlet_id || '',
    budget: props.budget?.budget || '',
});

const errors = ref({});
const isSubmitting = ref(false);

const submitForm = () => {
    if (!form.category_id) {
        errors.value.category_id = 'Category is required';
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

    errors.value = {};
    isSubmitting.value = true;

    form.put(`/locked-budget-food-categories/${props.budget.id}`, {
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: (errorBag) => {
            errors.value = errorBag;
            isSubmitting.value = false;
        }
    });
};

const formatBudget = (value) => {
    // Remove non-numeric characters
    const numericValue = value.replace(/[^\d]/g, '');
    return numericValue;
};

const onBudgetInput = (event) => {
    const formatted = formatBudget(event.target.value);
    form.budget = formatted;
};
</script>

<template>
    <AppLayout title="Edit Locked Budget Food Category">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Edit Locked Budget Food Category
                </h2>
                <a
                    href="/locked-budget-food-categories"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                >
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali
                </a>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form @submit.prevent="submitForm">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Category Selection -->
                                <div>
                                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
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

                                <!-- Outlet Selection -->
                                <div>
                                    <label for="outlet_id" class="block text-sm font-medium text-gray-700 mb-2">
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
                                <div class="md:col-span-2">
                                    <label for="budget" class="block text-sm font-medium text-gray-700 mb-2">
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
                            <div class="mt-8 flex justify-end space-x-4">
                                <a
                                    href="/locked-budget-food-categories"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    Batal
                                </a>
                                <button
                                    type="submit"
                                    :disabled="isSubmitting"
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="isSubmitting">Menyimpan...</span>
                                    <span v-else>Update</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>