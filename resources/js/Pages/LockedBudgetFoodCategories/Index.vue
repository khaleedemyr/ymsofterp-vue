<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import LockedBudgetFormModal from './LockedBudgetFormModal.vue';

const props = defineProps({
    budgets: Object,
    categories: Array,
    subCategories: Array,
    outlets: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.category_id || '');
const outletFilter = ref(props.filters?.outlet_id || '');
const perPage = ref(props.filters?.per_page || 10);

const showModal = ref(false);
const modalMode = ref('create'); // 'create' | 'edit'
const selectedBudget = ref(null);

const debouncedSearch = debounce(() => {
    applyFilters();
}, 400);

function onSearchInput() {
    debouncedSearch();
}

function applyFilters() {
    const params = {
        search: search.value,
        category_id: categoryFilter.value,
        outlet_id: outletFilter.value,
        per_page: perPage.value,
    };
    
    router.get('/locked-budget-food-categories', params, { 
        preserveState: true, 
        replace: true 
    });
}

function clearFilters() {
    search.value = '';
    categoryFilter.value = '';
    outletFilter.value = '';
    perPage.value = 10;
    applyFilters();
}

function goToPage(url) {
    if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
    modalMode.value = 'create';
    selectedBudget.value = null;
    showModal.value = true;
}

function openEdit(budget) {
    modalMode.value = 'edit';
    selectedBudget.value = budget;
    showModal.value = true;
}

async function deleteBudget(budget) {
    const result = await Swal.fire({
        title: 'Hapus Budget?',
        text: `Yakin ingin menghapus budget untuk ${budget.category?.name} - ${budget.outlet?.nama_outlet}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    });
    
    if (!result.isConfirmed) return;
    
    router.delete(`/locked-budget-food-categories/${budget.id}`, {
        onSuccess: () => {
            Swal.fire('Berhasil', 'Budget berhasil dihapus!', 'success');
        },
        onError: () => {
            Swal.fire('Error', 'Terjadi kesalahan saat menghapus data', 'error');
        }
    });
}

function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID');
}

function closeModal() {
    showModal.value = false;
}

function reload() {
    router.reload({ preserveState: true, replace: true });
}

const filteredBudgets = computed(() => {
    if (!props.budgets || !props.budgets.data) return [];
    return props.budgets.data;
});
</script>

<template>
    <AppLayout title="Locked Budget Food Categories">
        <div class="max-w-7xl w-full mx-auto py-8 px-2">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-lock text-blue-500"></i> Locked Budget Food Categories
                </h1>
                <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Budget
                </button>
            </div>

            <!-- Search Bar -->
            <div class="mb-4">
                <input
                    v-model="search"
                    @input="onSearchInput"
                    type="text"
                    placeholder="Cari kategori/outlet/budget..."
                    class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                />
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select
                        v-model="categoryFilter"
                        @change="applyFilters"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Categories</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                </div>

                <!-- Outlet Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
                    <select
                        v-model="outletFilter"
                        @change="applyFilters"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Outlets</option>
                        <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                            {{ outlet.nama_outlet }}
                        </option>
                    </select>
                </div>

                <!-- Per Page -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
                    <select
                        v-model="perPage"
                        @change="applyFilters"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            <!-- Clear Filters Button -->
            <div class="mb-4">
                <button
                    @click="clearFilters"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                >
                    Clear Filters
                </button>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
                <table class="w-full min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">KATEGORI</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">SUB KATEGORI</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">OUTLET</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">BUDGET</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">CREATED AT</th>
                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="filteredBudgets.length === 0">
                            <td colspan="6" class="text-center py-10 text-gray-400">Tidak ada data budget.</td>
                        </tr>
                        <tr v-for="budget in filteredBudgets" :key="budget.id" class="hover:bg-blue-50 transition shadow-sm">
                            <td class="px-6 py-3 font-semibold">{{ budget.category?.name || '-' }}</td>
                            <td class="px-6 py-3">{{ budget.sub_category?.name || '-' }}</td>
                            <td class="px-6 py-3">{{ budget.outlet?.nama_outlet || '-' }}</td>
                            <td class="px-6 py-3">
                                <span class="font-semibold text-green-600">Rp {{ formatNumber(budget.budget) }}</span>
                            </td>
                            <td class="px-6 py-3 text-gray-500">{{ formatDate(budget.created_at) }}</td>
                            <td class="px-6 py-3">
                                <div class="flex gap-2">
                                    <button @click="openEdit(budget)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                        Edit
                                    </button>
                                    <button @click="deleteBudget(budget)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="budgets && budgets.links" class="flex justify-end mt-4 gap-2">
                <button
                    v-for="link in budgets.links"
                    :key="link.label"
                    :disabled="!link.url"
                    @click="goToPage(link.url)"
                    v-html="link.label"
                    class="px-3 py-1 rounded-lg border text-sm font-semibold"
                    :class="[
                        link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
                        !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                    ]"
                />
            </div>

            <!-- Modal -->
            <LockedBudgetFormModal
                :show="showModal"
                :mode="modalMode"
                :budget="selectedBudget"
                :categories="categories"
                :sub-categories="subCategories"
                :outlets="outlets"
                @close="closeModal"
                @success="reload"
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.bg-3d {
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15), 0 1.5px 4px 0 rgba(31, 38, 135, 0.08);
}
</style>