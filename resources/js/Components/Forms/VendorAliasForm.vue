<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import LoadingSpinner from '@/Components/LoadingSpinner.vue'

/* ====[ Vue stuff ]==== */

const props = defineProps({
  vendorAliasId: {
    type: [Number, String, null],
    default: null,
  },
})

const emit = defineEmits(['loaded', 'saving', 'saved', 'deleting', 'deleted'])

/* ====[ Refs ]==== */

const loading = ref(true)
const formData = ref(null)
const vendor = ref(null)
const vendors = ref(null)

/* ====[ Computed ]==== */

const vendorOptions = computed(() => {
  const vendorList = vendors.value ?? []
  return vendorList.map((v) => `${v.name} [${v.id}]`)
})

const newVendorId = computed(() => {
  const regex = new RegExp(/\[[\d]{1,}\]/)
  const name = vendor.value ?? ''
  const match = name?.match(regex)
  const id = match ? match[0] : false
  return id ? id.replaceAll(/[\[\]]/g, '') : null
})

/* ====[ Functions ]==== */

const load = () => {
  if (!props.vendorAliasId) {
    return
  }

  loading.value = true

  axios
    .get(`/api/vendor-aliases/${props.vendorAliasId}/edit`)
    .then(
      (resp) => setVendorData(resp),
      (err) => console.error(err),
    )
    .finally(() => {
      loading.value = false
      emit('loaded')
    })
}

const setVendorData = (resp) => {
  vendors.value = JSON.parse(JSON.stringify(resp.data?.vendors ?? []))
  formData.value = JSON.parse(JSON.stringify(resp.data?.vendorAlias ?? {}))
  const aliasVendor = JSON.parse(JSON.stringify(resp.data?.vendorAlias?.vendor ?? {}))
  const vendorId = aliasVendor?.id ?? false
  const vendorName = aliasVendor?.name ?? false
  vendor.value = vendorId && vendorName ? `${vendorName} [${vendorId}]` : ''
}

const submitForm = () => {
  emit('saving')
  const url = `/api/vendor-aliases/${formData.value.id}`
  const data = {
    name: formData.value.name,
    vendor_id: newVendorId.value ?? null,
  }

  axios
    .put(url, data)
    .then(
      () => load(),
      (err) => console.error(err),
    )
    .finally(() => emit('saved'))
}

const deleteVendorAlias = () => {
  emit('deleting')

  const url = `/api/vendor-aliases/${formData.value.id}`

  axios.delete(url).then(
    () => emit('deleted'),
    (err) => console.error(err),
  )
}

/* ====[ Vue stuff ]==== */

onMounted(() => load())

defineExpose({
  deleteVendorAlias,
  submitForm,
})
</script>

<template>
  <div>
    <LoadingSpinner v-if="loading" />
    <form v-else>
      <div class="form-floating mb-3">
        <input type="text" class="form-control" id="name" placeholder="Vendor Alias Name" v-model="formData.name" />
        <label for="name">Vendor Alias Name</label>
      </div>
      <div class="mb-3">
        <label for="vendor">Vendor</label>
        <input
          v-model="vendor"
          class="form-control"
          list="vendor-datalist"
          id="vendor"
          placeholder="Select a Vendor..."
        />
        <datalist id="vendor-datalist">
          <option v-for="(v, vi) in vendorOptions" :key="`vendor-option-${vi}`" :value="v" />
        </datalist>
      </div>
    </form>
  </div>
</template>
