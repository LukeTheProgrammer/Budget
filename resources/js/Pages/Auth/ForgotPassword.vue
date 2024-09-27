<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import LoginCard from '@/Pages/Auth/LoginCard.vue';
import Link from '@/Components/Link.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <LoginCard>

            <div class="">
                Forgot your password? No problem. Just let us know your email
                address and we will email you a password reset link that will allow
                you to choose a new one.
            </div>

            <div v-if="status" class="alert alert-success" role="alert">
                {{ status }}
            </div>

            <form @submit.prevent="submit">
                <div class="mt-4">
                    <div class="form-floating">
                        <TextInput
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                        />
                        <InputLabel for="email" value="Email" />
                    </div>

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-4">
                    <button class="btn btn-primary w-100" :disabled="form.processing">
                        Email Password Reset Link
                    </button>
                </div>

                <div class="mt-4">
                    <Link route="login">
                        Login
                    </Link>
                </div>
            </form>
        </LoginCard>
    </GuestLayout>
</template>
