<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, LogOut, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="mb-6">
            <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-white">
                Verify your email
            </h1>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                Thanks for signing up! Before getting started, please verify your email address by clicking on the link we sent to you.
            </p>
        </div>

        <div
            v-if="verificationLinkSent"
            class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-medium text-emerald-800 dark:text-emerald-300"
            role="status"
        >
            A new verification link has been sent to the email address you provided.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="flex items-center justify-between pt-2">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="inline-flex items-center gap-1.5 text-xs text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200 transition"
                >
                    <LogOut class="w-3.5 h-3.5" />
                    <span>Log Out</span>
                </Link>

                <button
                    type="submit"
                    class="inline-flex items-center gap-2 py-2 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition disabled:opacity-50"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                    <span>Resend Verification Email</span>
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
