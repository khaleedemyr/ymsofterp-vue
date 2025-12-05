  <div v-if="can('view-outlet-internal-use')" class="flex items-center gap-2 px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg cursor-pointer" @click="router.visit('/outlet-internal-use')">
    <i class="fa-solid fa-boxes-stacked text-blue-500"></i>
    <span>Outlet Internal Use</span>
  </div>
  <div v-if="can('view-retail-food')" class="flex items-center gap-2 px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg cursor-pointer" @click="router.visit('/retail-food')">
    <i class="fa-solid fa-store text-blue-500"></i>
    <span>Retail Food</span>
  </div>
  <div class="flex items-center gap-2 px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg cursor-pointer" @click="router.visit('/item-supplier')">
    <i class="fa-solid fa-link text-blue-500"></i>
    <span>Items Supplier</span>
  </div>
  <div class="flex items-center gap-2 px-4 py-2 text-gray-600 hover:bg-blue-50 hover:text-blue-700 rounded-lg cursor-pointer" @click="router.visit('/promos')">
    <i class="fa-solid fa-tag text-blue-500"></i>
    <span>Promos</span>
  </div> 