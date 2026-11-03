<script setup lang="ts">
import { ref } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Lock, Mail, ArrowRight, Loader2 } from 'lucide-vue-next';

defineProps<{
    canResetPassword?: boolean;
    canRegister?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Sign In" />

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-white">
                Welcome back
            </h1>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                Sign in to your personal operations, tasks, and daily planning workspace.
            </p>
        </div>

        <!-- Session Status (e.g. password reset confirmation) -->
        <div
            v-if="status"
            class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-medium text-emerald-800 dark:text-emerald-300"
            role="status"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email Field -->
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
                        :aria-invalid="!!form.errors.email"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- Password Field with Show/Hide Toggle -->
            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Password" class="text-xs font-medium text-neutral-700 dark:text-neutral-300" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-[11px] font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 focus:outline-none focus:underline transition"
                    >
                        Forgot password?
                    </Link>
                </div>

                <div class="relative mt-1">
                    <Lock class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5 pointer-events-none" />
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full pl-9 pr-10 text-xs rounded-xl border-neutral-300 dark:border-neutral-700 dark:bg-neutral-950 dark:text-white focus:border-indigo-600 focus:ring-indigo-600"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                        :aria-invalid="!!form.errors.password"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-3 top-2.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 focus:outline-none"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    >
                        <EyeOff v-if="showPassword" class="w-4 h-4" />
                        <Eye v-else class="w-4 h-4" />
                    </button>
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <Checkbox
                        name="remember"
                        v-model:checked="form.remember"
                        class="rounded-md border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span class="text-xs text-neutral-600 dark:text-neutral-400">Remember this device</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-semibold shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-900"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                    <span>{{ form.processing ? 'Signing in...' : 'Sign In to Hub' }}</span>
                    <ArrowRight v-if="!form.processing" class="w-4 h-4" />
                </button>
            </div>

            <!-- Registration Link (Only shown if registration is explicitly enabled) -->
            <div v-if="canRegister" class="text-center pt-2 text-xs text-neutral-500">
                <span>Don't have an account? </span>
                <Link
                    :href="route('register')"
                    class="font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 focus:outline-none focus:underline"
                >
                    Create account
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
