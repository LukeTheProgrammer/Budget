<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import LoadingSpinner from '@/Components/LoadingSpinner.vue'

/* ====[ Vue stuff ]==== */

const props = defineProps({
  vendorId: {
    type: [Number, String, null],
    default: null,
  },
})

const emit = defineEmits(['loaded', 'saving', 'saved', 'deleting', 'deleted'])

/* ====[ Refs ]==== */

const loading = ref(true)
const savingAlias = ref(false)
const formData = ref(null)
const newAlias = ref(null)
const vendorAliases = ref([])

/* ====[ Computed ]==== */

const newAliasSubmitDisabled = computed(() => !newAlias.value && !savingAlias.value)

const aliasOptions = computed(() => {
  const aliases = vendorAliases.value ?? []
  return aliases.map((a) => `${a.name} [${a.id}]`)
})

const newAliasId = computed(() => {
  const regex = new RegExp(/\[[\d]{1,}\]/)
  const name = newAlias.value ?? ''
  const match = name?.match(regex)
  const id = match ? match[0] : false
  return id ? id.replaceAll(/[\[\]]/g, '') : null
})

const newAliasName = computed(() => {
  const regex = new RegExp(/(?:\[.*\])/g)
  const name = newAlias.value ?? ''
  return name ? name.replaceAll(regex, '') : null
})

/* ====[ Functions ]==== */

const load = () => {
  if (!props.vendorId) {
    return
  }

  loading.value = true

  axios
    .get(`/api/vendors/${props.vendorId}/edit`)
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
  vendorAliases.value = JSON.parse(JSON.stringify(resp.data?.aliases ?? []))
  formData.value = JSON.parse(JSON.stringify(resp.data?.vendor ?? {}))
  newAlias.value = null
}

const associateAlias = () => {
  if (!newAlias.value) {
    alert('A vendor alias must be selected')
    return
  }

  savingAlias.value = true

  return newAliasId.value ? associateNewAlias() : createNewAlias()
}

const createNewAlias = () => {
  const url = '/api/vendor-aliases'
  const data = {
    name: newAliasName.value,
    vendor_id: props.vendorId,
  }

  axios
    .post(url, data)
    .then(
      () => load(),
      (err) => console.error(err),
    )
    .finally(() => (savingAlias.value = false))
}

const associateNewAlias = () => {
  const url = `/api/vendor-aliases/${newAliasId.value}`
  const data = {
    vendor_id: props.vendorId,
  }

  axios
    .put(url, data)
    .then(
      () => load(),
      (err) => console.error(err),
    )
    .finally(() => (savingAlias.value = false))
}

const disassociateAlias = (alias) => {
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

const deleteVendor = () => {
  emit('deleting')

  const url = `/api/vendors/${formData.value.id}`

  axios.delete(url).then(
    () => emit('deleted'),
    (err) => console.error(err),
  )
}

/* ====[ Vue stuff ]==== */

onMounted(() => load())

defineExpose({
  deleteVendor,
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
        <p>Aliases</p>
        <div class="d-flex mb-3">
          <div class="flex-grow-1 me-3">
            <input
              v-model="newAlias"
              class="form-control"
              list="alias-datalist"
              id="alias-search"
              placeholder="Add Alias"
            />
            <datalist id="alias-datalist">
              <option v-for="(a, ai) in aliasOptions" :key="`allias-option-${ai}`" :value="a" />
            </datalist>
          </div>
          <div class="d-flex justify-content-end align-items-end">
            <button
              role="button"
              class="btn btn-success"
              title="Add New Alias"
              :disabled="newAliasSubmitDisabled"
              @click.prevent="associateAlias"
            >
              <LoadingSpinner v-if="savingAlias.value" />
              <i v-else class="bi bi-plus-square-fill"></i>
            </button>
          </div>
        </div>
        <div v-if="formData.aliases?.length < 1">No Aliases</div>
        <ul class="list-group">
          <li v-for="(alias, i) in formData.aliases" :key="`alias-button-${i}`" class="list-group-item">
            <div class="d-flex justify-content-between">
              <p class="m-0 p-0 pt-1">{{ alias.name }}</p>
              <button
                role="button"
                class="btn btn-danger btn-sm"
                title="Remove Alias"
                @click.prevent="disassociateAlias(alias)"
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
