<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    budget: Object,
});

function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID');
}
</script>

<template>
    <AppLayout title="Detail Locked Budget Food Category">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Detail Locked Budget Food Category
                </h2>
                <div class="flex space-x-2">
                    <a
                        :href="`/locked-budget-food-categories/${budget.id}/edit`"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                    >
                        <i class="fas fa-edit mr-2"></i>
                        Edit
                    </a>
                    <a
                        href="/locked-budget-food-categories"
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali
                    </a>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Category Information -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Category Information</h3>
                                <div class="space-y-2">
                                    <div>
                                        <span class="font-medium text-gray-600">Category Name:</span>
                                        <span class="ml-2 text-gray-900">{{ budget.category?.name || '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-600">Category ID:</span>
                                        <span class="ml-2 text-gray-900">{{ budget.category_id || '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Outlet Information -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Outlet Information</h3>
                                <div class="space-y-2">
                                    <div>
                                        <span class="font-medium text-gray-600">Outlet Name:</span>
                                        <span class="ml-2 text-gray-900">{{ budget.outlet?.nama_outlet || '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-600">Outlet ID:</span>
                                        <span class="ml-2 text-gray-900">{{ budget.outlet_id || '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Budget Information -->
                            <div class="bg-blue-50 p-4 rounded-lg md:col-span-2">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Budget Information</h3>
                                <div class="space-y-2">
                                    <div>
                                        <span class="font-medium text-gray-600">Budget Amount:</span>
                                        <span class="ml-2 text-2xl font-bold text-blue-600">Rp {{ formatNumber(budget.budget) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Timestamp Information -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Created Information</h3>
                                <div class="space-y-2">
                                    <div>
                                        <span class="font-medium text-gray-600">Created At:</span>
                                        <span class="ml-2 text-gray-900">{{ formatDate(budget.created_at) }}</span>
                                    </div>
                                    <div v-if="budget.creator">
                                        <span class="font-medium text-gray-600">Created By:</span>
                                        <span class="ml-2 text-gray-900">{{ budget.creator.nama_lengkap || budget.creator.name || '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Updated Information -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Updated Information</h3>
                                <div class="space-y-2">
                                    <div>
                                        <span class="font-medium text-gray-600">Updated At:</span>
                                        <span class="ml-2 text-gray-900">{{ formatDate(budget.updated_at) }}</span>
                                    </div>
                                    <div v-if="budget.updater">
                                        <span class="font-medium text-gray-600">Updated By:</span>
                                        <span class="ml-2 text-gray-900">{{ budget.updater.nama_lengkap || budget.updater.name || '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>