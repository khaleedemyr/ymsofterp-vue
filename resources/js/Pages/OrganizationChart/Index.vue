<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrganizationChart from '@/Components/OrganizationChart.vue';
</script>

<template>
    <AppLayout>
        <Head title="Organization Chart" />
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <OrganizationChart />
            </div>
        </div>
    </AppLayout>
</template>
