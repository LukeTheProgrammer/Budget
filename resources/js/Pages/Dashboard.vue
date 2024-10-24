<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { parse, format } from '@formkit/tempo'

const props = defineProps({
  transactions: {
    type: Array,
    default: () => [],
  },
})

const thead = ref(['Date', 'Description', 'Type', 'Amount'])

const tbody = computed(() => {
    return props.transactions.map(t => {
        const transDate = parse(t.transaction_date)

        return [
            format(transDate, 'YYYY-MM-DD'),
            t.vendor.name,
            t.type,
            t.amount
        ]
  })
})
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <h1>Dashboard</h1>
        </template>

        <div class="card p-4">
            <table class="table table-hover">
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
    </AppLayout>
</template>
