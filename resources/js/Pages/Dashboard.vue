<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  transactions: {
    type: Array,
    default: () => [],
  },
})

const thead = ref(['Date', 'Description', 'Type', 'Amount'])

const tbody = computed(() => {
    return props.transactions.map(t => {
    return [
      t.transaction_date,
      t.description,
      t.type,
      t.amount
    ]
  })
})
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <table class="table w-100">
                            <thead>
                                <tr class="bg-primary">
                                    <th v-for="(th, thi) in thead" :key="`th-${thi}`">
                                        {{ th }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, rowi) in tbody" :key="`row-${rowi}`">
                                    <td v-for="(cell, celli) in row" :key="`cell-${rowi}-${celli}`">
                                        {{ cell }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
