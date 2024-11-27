<script setup>
import { onMounted, ref } from 'vue'
import LoadingSpinner from '@/Components/LoadingSpinner.vue'
import axios from 'axios'

const props = defineProps({
  vendorId: {
    type: [Number, String, null],
    default: null,
  },
})

const emit = defineEmits(['loaded', 'saving', 'saved'])

const loading = ref(true)
const formData = ref(null)

function load() {
  if (!props.vendorId) {
    return
  }

  loading.value = true

  axios
    .get(`/api/vendors/${props.vendorId}`)
    .then(
      (resp) => setVendorData(resp),
      (err) => console.error(err),
    )
    .finally(() => {
      loading.value = false
      emit('loaded')
    })
}

function setVendorData(resp) {
  formData.value = JSON.parse(JSON.stringify(resp.data))
}

function deleteAlias(alias) {
  const url = `/api/vendor-aliases/${alias.id}`
  const data = JSON.parse(JSON.stringify(alias))
  data.vendor_id = null
  axios.put(url, data).then(
    () => load(),
    (err) => console.error(err),
  )
}

const submitForm = () => {
  emit('saving')
  const url = `/api/vendors/${formData.value.id}`
  const data = {
    name: formData.value.name,
  }

  axios
    .put(url, data)
    .then(
      () => load(),
      (err) => console.error(err),
    )
    .finally(() => emit('saved'))
}

onMounted(() => load())

defineExpose({
  submitForm,
})
</script>

<template>
  <div>
    <LoadingSpinner v-if="loading" />
    <form v-else>
      <div class="form-floating mb-3">
        <input type="text" class="form-control" id="name" placeholder="Vendor Name" v-model="formData.name" />
        <label for="name">Vendor Name</label>
      </div>
      <div class="mb-3">
        <div v-if="formData.aliases?.length < 1">No Aliases</div>
        <ul class="list-group">
          <li v-for="(alias, i) in formData.aliases" :key="`alias-button-${i}`" class="list-group-item">
            <div class="d-flex justify-content-between">
              <p class="m-0 p-0 pt-1">{{ alias.name }}</p>
              <button
                role="button"
                class="btn btn-danger btn-sm"
                title="Remove Alias"
                @click.prevent="deleteAlias(alias)"
              >
                <i class="bi bi-trash-fill"></i>
              </button>
            </div>
          </li>
        </ul>
      </div>
    </form>
  </div>
</template>
