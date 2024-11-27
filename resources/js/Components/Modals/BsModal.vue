<script setup>
import { onMounted, ref, useTemplateRef } from 'vue'
import { Modal } from 'bootstrap'

const props = defineProps({
  title: {
    type: String,
    default: 'Modal',
  },
})

const emit = defineEmits(['closed'])

const modalRef = useTemplateRef('theModal')

const modalId = Math.random().toString(36).substr(2, 9)

const modal = ref(null)
const showModal = () => modal.value.show()
const hideModal = () => modal.value.hide()

defineExpose({
  showModal,
  hideModal,
})

onMounted(() => {
  modal.value = new Modal('#' + modalId, {
    backdrop: true,
    focus: true,
    keyboard: true,
  })

  modalRef.value.addEventListener('hidden.bs.modal', () => emit('closed'))
})
</script>

<template>
  <div class="modal fade" :id="modalId" ref="theModal" tabindex="-1" aria-labelledby="bsModalTitle" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="bsModalTitle">{{ props.title }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" />
        </div>
        <div class="modal-body">
          <slot />
        </div>
        <div class="modal-footer">
          <slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </slot>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.modal.show {
  display: block;
  background: rgba(0, 0, 0, 0.5);
}
</style>
