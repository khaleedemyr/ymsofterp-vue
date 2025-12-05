<template>
    <Modal :show="show" @close="close">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Edit Good Receive
            </h2>

            <div class="mt-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">PO Number</label>
                        <div class="mt-1 text-sm text-gray-900">{{ gr?.po_number }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Supplier</label>
                        <div class="mt-1 text-sm text-gray-900">{{ gr?.supplier_name }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Receive Date</label>
                        <div class="mt-1 text-sm text-gray-900">{{ gr?.receive_date }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Received By</label>
                        <div class="mt-1 text-sm text-gray-900">{{ gr?.received_by_name }}</div>
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="text-md font-medium text-gray-900 mb-4">Items</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Received</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="item in gr?.items" :key="item.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.item_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ item.unit_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ formatNumber(item.price) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <input
                                            type="number"
                                            v-model="item.qty_received"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            :min="0"
                                            step="0.01"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="close">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="save" :disabled="saving">
                        {{ saving ? 'Saving...' : 'Save Changes' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script>
import { defineComponent } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

export default defineComponent({
    components: {
        Modal,
        PrimaryButton,
        SecondaryButton,
    },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        gr: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            saving: false,
        };
    },
    methods: {
        close() {
            this.$emit('close');
        },
        formatNumber(number) {
            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(number);
        },
        save() {
            if (!this.gr) return;

            this.saving = true;
            const items = this.gr.items.map(item => ({
                id: item.id,
                qty_received: parseFloat(item.qty_received)
            }));

            axios.put(`/food-good-receive/${this.gr.id}`, { items })
                .then(response => {
                    this.$emit('success');
                    this.$toast.success('Good Receive berhasil diupdate');
                })
                .catch(error => {
                    console.error('Error updating GR:', error);
                    let msg = 'Terjadi kesalahan saat mengupdate Good Receive';
                    if (error.response && error.response.data) {
                        msg = error.response.data.message || msg;
                    }
                    this.$toast.error(msg);
                })
                .finally(() => {
                    this.saving = false;
                });
        },
    },
});
</script> 