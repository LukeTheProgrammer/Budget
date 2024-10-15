<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { parse, format } from '@formkit/tempo';
import BarChart from '@/Components/Charts/BarChart.vue';
import { create, all } from 'mathjs';

const math = create(all);

const props = defineProps({
    transactions: {
        type: Array,
        default: () => [],
    },
});

const dataLoaded = ref(false);

let chartData = {};

const setChartData = (resp) => {
    console.log(JSON.stringify(resp));

    const sortable = Object.entries(resp.chartData)
        .sort(([, a], [, b]) => a - b)
        .reduce((r, [k, v]) => ({ ...r, [k]: v }), {});

    const data = Object.values(sortable) ?? [];

    const dataSet = {
        label: 'USD',
        data: data.map((n) => math.round(math.abs(n), 2)),
    };

    chartData = {
        labels: Object.keys(sortable),
        datasets: [dataSet],
        // datasets: [ { data: Object.values(sortable) } ],
    };

    dataLoaded.value = true;
};

const apiData = axios.get('/reports/data').then(
    (resp) => setChartData(resp.data),
    (err) => console.error(err),
);

// const chartData = computed(() => {
//     let data = {}

//     props.transactions.map((t) => {
//         const label = t?.category
//         const amountStr = t?.amount
//         const amount = parseFloat(amountStr)?.toFixed(2)

//         const total = data[label] ?? 0
//         const sum = math.add(total, amount)

//         // console.log('label', label)
//         // console.log('amount', amountStr, amount, isNaN(amount))

//         if (label === '') {
//             return
//         }

//         if (isNaN(amount) || isNaN(total) || isNaN(sum)) {
//             return
//         }

//         data[label] = math.round(sum, 2)
//         // console.log(label, data[label])
//     })

//     // console.log(Object.entries(data));
//     // console.log(Object.entries(data).sort(([,a],[,b]) => a-b));

//     const sortable = Object.entries(data)
//         .sort(([,a],[,b]) => a-b)
//         .reduce((r, [k, v]) => ({ ...r, [k]: v }), {});

//     // console.log(sortable);

//     const dataSet = {
//         label: 'USD',
//         data: Object.values(sortable).map(n => math.round(math.abs(n), 2)),
//     }

//     return {
//         labels: Object.keys(sortable),
//         datasets: [ dataSet ],
//         // datasets: [ { data: Object.values(sortable) } ],
//     }
// })

const chartOptions = computed(() => {
    return {
        responsive: true,
        indexAxis: 'y',
        plugins: {
            datalabels: {
                // Position of the labels
                // (start, end, center, etc.)
                anchor: 'end',
                // Alignment of the labels
                // (start, end, center, etc.)F
                align: 'end',
                // Color of the labels
                // color: 'blue',
                font: {
                    weight: 'bold',
                },
                formatter: (value, context) => {
                    const formatter = new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: 'USD',
                    });

                    return formatter.format(value);
                },
            },
        },
    };
});
</script>

<template>
    <Head title="Reports" />

    <AppLayout>
        <template #header>
            <h1>Reports</h1>
        </template>

        <div class="card p-4">
            <BarChart
                v-if="dataLoaded"
                :chart-options="chartOptions"
                :chart-data="chartData"
            />
        </div>
    </AppLayout>
</template>
