<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, nextTick, ref, useTemplateRef } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AgGridTable from '@/Components/Tables/AgGridTable.vue'
import AgGridCellButton from '@/Components/Tables/Partials/AgGridCellButton.vue'
import VendorModal from '@/Components/Modals/VendorModal.vue'

const props = defineProps({
  vendors: {
    type: Array,
    default: () => [],
  },
})

const modalRef = useTemplateRef('vendorModal')
const vendorModalId = ref(null)

const vendorBtnClick = (params) => {
  vendorModalId.value = params.data.id
  nextTick(() => modalRef.value.showModal())
}

const onVendorModalClosed = () => {
  vendorModalId.value = null
}

const colDefs = ref([
  { field: 'name', headerName: 'Vendor Name', cellClass: ['align-middle'] },
  { field: 'aliases', headerName: 'Aliases', cellClass: ['align-middle'] },
  {
    field: 'id',
    headerName: '',
    cellClass: ['align-middle', 'text-end'],
    cellRendererSelector: (params) => {
      return {
        component: AgGridCellButton,
        params: {
          label: 'Vendor',
          handleClick: function (params) {
            vendorBtnClick(params)
          },
        },
      }
    },
  },
])

const rowData = computed(() => {
  return props.vendors.map((v) => {
    const aliases = v?.aliases?.map((a) => a.name)
    return {
      id: v.id,
      name: v.name,
      aliases: aliases?.join(', '),
    }
  })
})
</script>

<template>
  <Head title="Vendors" />

  <AppLayout>
    <template #header>
      <h1>Vendors</h1>
    </template>

    <div style="height: 50vh">
      <AgGridTable :col-defs="colDefs" :row-data="rowData" simple-filter-label="Search Vendors" />
      <VendorModal v-if="vendorModalId" :vendor-id="vendorModalId" ref="vendorModal" @closed="onVendorModalClosed" />
    </div>
  </AppLayout>
</template>
