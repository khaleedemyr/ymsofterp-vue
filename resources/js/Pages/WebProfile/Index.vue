<template>
  <AppLayout title="Web Profile Dashboard">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
          Web Profile Management
        </h1>
        <p class="text-gray-600">Kelola konten website profile Anda</p>
      </div>

      <!-- Dashboard Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Pages Card -->
        <Link href="/web-profile/pages" class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-l-4 border-blue-500">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-1">Pages</h3>
              <p class="text-sm text-gray-500">Kelola halaman website</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
              <i class="fa-solid fa-file-lines text-blue-600 text-2xl"></i>
            </div>
          </div>
        </Link>

        <!-- Banners Card -->
        <Link href="/web-profile/banners" class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-l-4 border-green-500">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-1">Banners</h3>
              <p class="text-sm text-gray-500">Kelola banner slider</p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
              <i class="fa-solid fa-images text-green-600 text-2xl"></i>
            </div>
          </div>
        </Link>

        <!-- Brands Card -->
        <Link href="/web-profile/brands" class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-l-4 border-yellow-500">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 mb-1">Brands</h3>
              <p class="text-sm text-gray-500">Kelola brand restaurant</p>
            </div>
            <div class="bg-yellow-100 p-3 rounded-full">
              <i class="fa-solid fa-store text-yellow-600 text-2xl"></i>
            </div>
          </div>
        </Link>
      </div>

      <!-- Quick Actions -->
      <div class="mt-8 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <Link href="/web-profile/create" class="flex items-center gap-3 p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
            <i class="fa-solid fa-plus-circle text-blue-600 text-xl"></i>
            <span class="text-gray-700 font-medium">Create Page</span>
          </Link>
          <Link href="/web-profile/banners/create" class="flex items-center gap-3 p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
            <i class="fa-solid fa-image text-green-600 text-xl"></i>
            <span class="text-gray-700 font-medium">Create Banner</span>
          </Link>
          <Link href="/web-profile/brands/create" class="flex items-center gap-3 p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors">
            <i class="fa-solid fa-store text-yellow-600 text-xl"></i>
            <span class="text-gray-700 font-medium">Create Brand</span>
          </Link>
          <a href="/" target="_blank" class="flex items-center gap-3 p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
            <i class="fa-solid fa-external-link-alt text-purple-600 text-xl"></i>
            <span class="text-gray-700 font-medium">View Website</span>
          </a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>
