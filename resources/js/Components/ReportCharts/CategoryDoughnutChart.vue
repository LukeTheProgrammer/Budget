<script setup>
import { computed, ref } from 'vue'
import { parse, format } from '@formkit/tempo'
import DoughnutChart from '@/Components/Charts/DoughnutChart.vue'
import { create, all } from 'mathjs'

const math = create(all)

const props = defineProps({
  // transactions: {
  //     type: Array,
  //     default: () => [],
  // },
})

const dataLoaded = ref(false)

let chartData = {}

const setChartData = (resp) => {
  console.log(JSON.stringify(resp))

  const sortable = Object.entries(resp.chartData)
    .sort(([, a], [, b]) => a - b)
    .reduce((r, [k, v]) => ({ ...r, [k]: v }), {})

  const data = Object.values(sortable) ?? []

  const dataSet = {
    label: 'USD',
    data: data.map((n) => math.round(math.abs(n), 2)),
  }

  chartData = {
    labels: Object.keys(sortable),
    datasets: [dataSet],
    // datasets: [ { data: Object.values(sortable) } ],
  }

  dataLoaded.value = true
}

const apiData = axios.get('/reports/data').then(
  (resp) => setChartData(resp.data),
  (err) => console.error(err),
)

const chartOptions = computed(() => {
  return {
    responsive: true,
    plugins: {
      // https://chartjs-plugin-datalabels.netlify.app
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
          })
          return formatter.format(value)
        },
      },
    },
  }
})
</script>

<template>
  <div>
    <DoughnutChart v-if="dataLoaded" :chart-options="chartOptions" :chart-data="chartData" />
  </div>
</template>
