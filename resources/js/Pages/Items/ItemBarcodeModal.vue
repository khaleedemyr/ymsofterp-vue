<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Manage Barcodes for {{ item?.name }}
            </h2>

            <div class="mt-6">
                <div class="flex gap-2">
                    <TextInput
                        v-model="newBarcode"
                        type="text"
                        class="flex-1"
                        placeholder="Enter barcode"
                        @keyup.enter="addBarcode"
                    />
                    <PrimaryButton @click="addBarcode">
                        Add
                    </PrimaryButton>
                </div>

                <div class="mt-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Barcode
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="barcode in barcodes" :key="barcode.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ barcode.barcode }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button
                                        @click="deleteBarcode(barcode)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal">
                    Close
                </SecondaryButton>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    item: Object,
});

const emit = defineEmits(['close']);

const barcodes = ref(props.item?.barcodes || []);
const newBarcode = ref('');

const form = useForm({
    barcode: '',
});

const addBarcode = () => {
    if (!newBarcode.value) return;

    form.barcode = newBarcode.value;
    form.post(route('items.barcodes.store', props.item.id), {
        preserveScroll: true,
        onSuccess: () => {
            barcodes.value.push({
                id: Date.now(), // Temporary ID until server response
                barcode: newBarcode.value,
            });
            newBarcode.value = '';
        },
    });
};

const deleteBarcode = (barcode) => {
    if (!confirm('Are you sure you want to delete this barcode?')) return;

    form.delete(route('items.barcodes.destroy', [props.item.id, barcode.id]), {
        preserveScroll: true,
        onSuccess: () => {
            barcodes.value = barcodes.value.filter(b => b.id !== barcode.id);
        },
    });
};

const closeModal = () => {
    emit('close');
};
</script> 