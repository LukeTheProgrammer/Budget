<script setup>
import Checkbox from '@/Components/Checkbox.vue'
import CheckboxLabel from '@/Components/CheckboxLabel.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import Link from '@/Components/Link.vue'
import LoginCard from '@/Pages/Auth/LoginCard.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, useForm } from '@inertiajs/vue3'

defineProps({
  canResetPassword: {
    type: Boolean,
  },
  status: {
    type: String,
  },
})

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="Log in" />

    <LoginCard>
      <div v-if="status" class="alert alert-success" role="alert">
        {{ status }}
      </div>

      <form @submit.prevent="submit">
        <div class="mt-4">
          <div class="form-floating">
            <TextInput id="email" type="email" v-model="form.email" required autofocus autocomplete="username" />
            <InputLabel for="email" value="Email" />
          </div>

          <InputError class="mt-2" :message="form.errors.email" />
        </div>

        <div class="mt-4">
          <div class="form-floating">
            <TextInput
              id="password"
              type="password"
              class="mt-1 block w-full"
              v-model="form.password"
              required
              autocomplete="current-password"
            />
            <InputLabel for="password" value="Password" />
          </div>

          <InputError class="mt-2" :message="form.errors.password" />
        </div>

        <div class="mt-4 form-check">
          <Checkbox name="remember" v-model:checked="form.remember" />
          <CheckboxLabel value="Remember me" for="remember" />
        </div>

        <div class="mt-4">
          <button class="btn btn-primary w-100" :disabled="form.processing">Log in</button>
        </div>

        <div class="mt-4">
          <Link v-if="canResetPassword" route="password.request"> Forgot your password? </Link>
        </div>
      </form>
    </LoginCard>
  </GuestLayout>
</template>
