<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { parse, format } from '@formkit/tempo'

const props = defineProps({
  vendors: {
    type: Array,
    default: () => [],
  },
})

const searchTerm = ref(null);

const thead = ref(['Name'])

const tbody = computed(() => {
  const searchStr = searchTerm.value?.toLowerCase() ?? ''

  return props.vendors
    .filter((v) => {
      if (!searchTerm.value) {
        return true;
      }

      const name = v.name?.toLowerCase()?.replaceAll(/[^a-z]/g, '') ?? ''

      return name?.length && name.includes(searchStr)
    })
    .map((v) => {
      // const transDate = parse(t.transaction_date)
      // return [format(transDate, 'YYYY-MM-DD'), t.vendor.name, t.type, t.amount]

      return [v.name]
    })
})
</script>

<template>
  <Head title="Vendors" />

  <AppLayout>
    <template #header>
      <h1>Vendors</h1>
    </template>

    <div class="card p-4">
      <input type="text" class="form-control" placeholder="Search" v-model="searchTerm" />
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
