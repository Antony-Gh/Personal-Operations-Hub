<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, ArrowLeft, Loader2 } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div class="mb-6">
            <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-white">
                Reset your password
            </h1>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                Enter your email address and we'll send you instructions to safely reset your password.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-medium text-emerald-800 dark:text-emerald-300"
            role="status"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Email Address" class="text-xs font-medium text-neutral-700 dark:text-neutral-300" />
                <div class="relative mt-1">
                    <Mail class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5 pointer-events-none" />
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full pl-9 text-xs rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white focus:border-indigo-600 focus:ring-indigo-600"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="you@example.com"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="pt-2 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="inline-flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 transition"
                >
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Back to login</span>
                </Link>

                <button
                    type="submit"
                    class="inline-flex items-center gap-2 py-2 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition disabled:opacity-50"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                    <span>Send Reset Link</span>
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
