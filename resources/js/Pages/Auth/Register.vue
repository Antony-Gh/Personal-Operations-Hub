<script setup lang="ts">
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { User, Mail, Lock, Eye, EyeOff, Loader2 } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create Account" />

        <div class="mb-6">
            <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-white">
                Create your workspace
            </h1>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                Set up your primary administrator account for Personal Operations Hub.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="name" value="Full Name" class="text-xs font-medium" />
                <div class="relative mt-1">
                    <User class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5 pointer-events-none" />
                    <TextInput
                        id="name"
                        type="text"
                        class="block w-full pl-9 text-xs rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white focus:border-indigo-600 focus:ring-indigo-600"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Alex Morgan"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email Address" class="text-xs font-medium" />
                <div class="relative mt-1">
                    <Mail class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5 pointer-events-none" />
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full pl-9 text-xs rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white focus:border-indigo-600 focus:ring-indigo-600"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        placeholder="you@example.com"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" class="text-xs font-medium" />
                <div class="relative mt-1">
                    <Lock class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5 pointer-events-none" />
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full pl-9 pr-10 text-xs rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white focus:border-indigo-600 focus:ring-indigo-600"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-3 top-2.5 text-neutral-400 hover:text-neutral-600"
                    >
                        <EyeOff v-if="showPassword" class="w-4 h-4" />
                        <Eye v-else class="w-4 h-4" />
                    </button>
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm Password" class="text-xs font-medium" />
                <div class="relative mt-1">
                    <Lock class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5 pointer-events-none" />
                    <TextInput
                        id="password_confirmation"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full pl-9 text-xs rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white focus:border-indigo-600 focus:ring-indigo-600"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition disabled:opacity-50"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                    <span>Create Account</span>
                </button>
            </div>

            <div class="text-center pt-2 text-xs text-neutral-500">
                <span>Already have an account? </span>
                <Link
                    :href="route('login')"
                    class="font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                >
                    Sign in
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
