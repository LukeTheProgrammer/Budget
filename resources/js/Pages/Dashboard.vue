<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SideDrawer from '@/Components/SideDrawer.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { parse, format } from '@formkit/tempo'

const props = defineProps({
  transactions: {
    type: Array,
    default: () => [],
  },
  vendors: {
    type: Array,
    default: () => [],
  },
})

const thead = ref(['Date', 'Description', 'Type', 'Amount'])

const tbody = computed(() => {
  return props.transactions.map((t) => {
    const transDate = parse(t.transaction_date)

    return [format(transDate, 'YYYY-MM-DD'), t.vendor.name, t.type, t.amount]
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
      <div class="row">
        <div class="col-3">
          <div class="form-floating">
            <select class="form-select" id="vendor">
              <option v-for="v in props.vendors" :key="v.id" :value="v.id">
                {{ v.name }}
              </option>
            </select>
            <label for="vendor">Vendors</label>
          </div>
        </div>
      </div>
    </div>

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
