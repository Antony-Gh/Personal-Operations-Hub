<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import {
    Smartphone,
    X,
    Wifi,
    Check,
    Copy,
    ExternalLink,
    ShieldCheck,
    AlertTriangle,
    QrCode,
    Sparkles,
    Apple,
    Layers,
} from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

interface MobileStatus {
    primary_ip: string;
    primary_url: string;
    port: number;
    bound_to_all_interfaces: boolean;
    firewall_rule_active: boolean;
    available_ips: Array<{
        ip: string;
        name: string;
        type: string;
        is_primary: boolean;
    }>;
    qr_svg: string;
}

const loading = ref(false);
const statusData = ref<MobileStatus | null>(null);
const copied = ref(false);
const activeTab = ref<'ios' | 'android' | 'remote'>('ios');
const enablingNetwork = ref(false);
const enableMessage = ref<string | null>(null);

const fetchStatus = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/mobile/status');
        statusData.value = response.data;
    } catch (err) {
        console.error('Failed to load mobile network status', err);
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.isOpen,
    (newVal) => {
        if (newVal && !statusData.value) {
            fetchStatus();
        }
    }
);

onMounted(() => {
    if (props.isOpen) {
        fetchStatus();
    }
});

const copyUrl = async () => {
    if (!statusData.value?.primary_url) return;
    try {
        await navigator.clipboard.writeText(statusData.value.primary_url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Fallback
    }
};

const enableAllInterfaces = async () => {
    enablingNetwork.value = true;
    enableMessage.value = null;
    try {
        const res = await axios.post('/mobile/enable');
        enableMessage.value = res.data.message || 'Configured! Run enable-mobile-access.ps1 to apply.';
        await fetchStatus();
    } catch (err) {
        enableMessage.value = 'Failed to update bind configuration.';
    } finally {
        enablingNetwork.value = false;
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm transition-opacity" @click="emit('close')"></div>

        <div class="min-h-full flex items-center justify-center p-4">
            <div
                class="relative w-full max-w-lg bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-2xl shadow-2xl p-6 overflow-hidden transition-all text-neutral-900 dark:text-neutral-100"
                @click.stop
            >
                <!-- Close Button -->
                <button
                    @click="emit('close')"
                    class="absolute top-4 right-4 p-1.5 rounded-lg text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                    title="Close"
                >
                    <X class="w-5 h-5" />
                </button>

                <!-- Header -->
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800/80 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0 shadow-sm">
                        <Smartphone class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-neutral-900 dark:text-white">
                            Connect Mobile Phone
                        </h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            Run locally on your iPhone or Android via home Wi-Fi
                        </p>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="py-12 flex flex-col items-center justify-center gap-3 text-neutral-400">
                    <div class="w-6 h-6 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-xs">Detecting local network interfaces...</span>
                </div>

                <!-- Main Content -->
                <div v-else-if="statusData" class="space-y-5">
                    <!-- QR Code and URL Box -->
                    <div class="p-4 rounded-xl bg-neutral-50 dark:bg-neutral-800/60 border border-neutral-200 dark:border-neutral-700/80 flex flex-col sm:flex-row items-center gap-4">
                        <!-- QR Code Container -->
                        <div class="shrink-0 p-2 bg-white rounded-xl shadow-sm border border-neutral-200 dark:border-neutral-700 flex items-center justify-center">
                            <div v-html="statusData.qr_svg" class="w-36 h-36 flex items-center justify-center"></div>
                        </div>

                        <!-- URL & Copy Action -->
                        <div class="flex-1 min-w-0 flex flex-col justify-between self-stretch text-center sm:text-left">
                            <div>
                                <span class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wider block mb-1">
                                    Local Wi-Fi Address
                                </span>
                                <div class="font-mono text-sm font-semibold text-indigo-600 dark:text-indigo-400 break-all select-all">
                                    {{ statusData.primary_url }}
                                </div>
                                <p class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-1">
                                    Scan QR with phone camera or tap copy below:
                                </p>
                            </div>

                            <div class="mt-3 flex items-center justify-center sm:justify-start gap-2">
                                <button
                                    @click="copyUrl"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-neutral-700 border border-neutral-300 dark:border-neutral-600 hover:bg-neutral-100 dark:hover:bg-neutral-600 text-neutral-700 dark:text-neutral-200 shadow-sm transition"
                                >
                                    <Check v-if="copied" class="w-3.5 h-3.5 text-emerald-600" />
                                    <Copy v-else class="w-3.5 h-3.5 text-neutral-500" />
                                    <span>{{ copied ? 'Copied Link!' : 'Copy Link' }}</span>
                                </button>
                                <a
                                    :href="statusData.primary_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="p-1.5 rounded-lg text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-200 border border-transparent hover:border-neutral-300 dark:hover:border-neutral-600 transition"
                                    title="Open locally"
                                >
                                    <ExternalLink class="w-4 h-4" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Network Status Pill Strip -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2.5 rounded-lg border flex items-center gap-2" :class="statusData.bound_to_all_interfaces ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 border-amber-500/20 text-amber-700 dark:text-amber-300'">
                            <Wifi class="w-4 h-4 shrink-0" />
                            <div class="truncate">
                                <div class="font-medium text-[11px]">Server Host</div>
                                <div class="text-[11px] opacity-90 truncate">
                                    {{ statusData.bound_to_all_interfaces ? 'All Interfaces (0.0.0.0)' : 'Localhost (127.0.0.1)' }}
                                </div>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-lg border flex items-center gap-2" :class="statusData.firewall_rule_active ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 border-amber-500/20 text-amber-700 dark:text-amber-300'">
                            <ShieldCheck v-if="statusData.firewall_rule_active" class="w-4 h-4 shrink-0" />
                            <AlertTriangle v-else class="w-4 h-4 shrink-0" />
                            <div class="truncate">
                                <div class="font-medium text-[11px]">Windows Firewall</div>
                                <div class="text-[11px] opacity-90 truncate">
                                    {{ statusData.firewall_rule_active ? 'Port 8000 Allowed' : 'Rule Pending' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enable Helper Note if not configured -->
                    <div v-if="!statusData.bound_to_all_interfaces || !statusData.firewall_rule_active" class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2.5">
                        <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div class="flex-1 min-w-0">
                            <p class="font-medium">One-Click Setup Required for Mobile Access</p>
                            <p class="text-[11px] mt-0.5 text-amber-700 dark:text-amber-400">
                                Run this command in PowerShell to configure Windows Firewall and bind to all local network interfaces:
                            </p>
                            <code class="block mt-1.5 p-1.5 rounded bg-amber-100 dark:bg-amber-900/40 font-mono text-[11px] text-amber-950 dark:text-amber-200 select-all">
                                .\scripts\enable-mobile-access.ps1
                            </code>
                        </div>
                    </div>

                    <!-- Step-by-Step Install Tabs -->
                    <div>
                        <div class="flex items-center gap-2 border-b border-neutral-200 dark:border-neutral-800 text-xs mb-3">
                            <button
                                @click="activeTab = 'ios'"
                                class="pb-2 font-medium transition relative"
                                :class="activeTab === 'ios' ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600' : 'text-neutral-500 hover:text-neutral-700 dark:text-neutral-400'"
                            >
                                iPhone / iPad (Safari)
                            </button>
                            <button
                                @click="activeTab = 'android'"
                                class="pb-2 font-medium transition relative"
                                :class="activeTab === 'android' ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600' : 'text-neutral-500 hover:text-neutral-700 dark:text-neutral-400'"
                            >
                                Android (Chrome)
                            </button>
                            <button
                                @click="activeTab = 'remote'"
                                class="pb-2 font-medium transition relative"
                                :class="activeTab === 'remote' ? 'text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600' : 'text-neutral-500 hover:text-neutral-700 dark:text-neutral-400'"
                            >
                                Remote / Away from Home
                            </button>
                        </div>

                        <!-- iOS Guide -->
                        <div v-if="activeTab === 'ios'" class="text-xs space-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-start gap-2">
                                <span class="w-4 h-4 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold text-[10px] flex items-center justify-center shrink-0 mt-0.5">1</span>
                                <span>Connect iPhone to your home Wi-Fi and open the link in <strong>Safari</strong>.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="w-4 h-4 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold text-[10px] flex items-center justify-center shrink-0 mt-0.5">2</span>
                                <span>Tap the <strong>Share</strong> button (box with upward arrow) at the bottom toolbar.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="w-4 h-4 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold text-[10px] flex items-center justify-center shrink-0 mt-0.5">3</span>
                                <span>Scroll down and select <strong>"Add to Home Screen"</strong>, then tap <strong>Add</strong>.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="w-4 h-4 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold text-[10px] flex items-center justify-center shrink-0 mt-0.5">4</span>
                                <span>Launch from your home screen — it runs in full-screen standalone mode without browser chrome!</span>
                            </div>
                        </div>

                        <!-- Android Guide -->
                        <div v-else-if="activeTab === 'android'" class="text-xs space-y-2 text-neutral-600 dark:text-neutral-400">
                            <div class="flex items-start gap-2">
                                <span class="w-4 h-4 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold text-[10px] flex items-center justify-center shrink-0 mt-0.5">1</span>
                                <span>Connect phone to home Wi-Fi and open the link in <strong>Google Chrome</strong>.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="w-4 h-4 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold text-[10px] flex items-center justify-center shrink-0 mt-0.5">2</span>
                                <span>Tap the <strong>Three Dots (⋮)</strong> menu in the upper right.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="w-4 h-4 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold text-[10px] flex items-center justify-center shrink-0 mt-0.5">3</span>
                                <span>Tap <strong>"Install app"</strong> or <strong>"Add to Home screen"</strong>.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="w-4 h-4 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-semibold text-[10px] flex items-center justify-center shrink-0 mt-0.5">4</span>
                                <span>Confirm installation to get full standalone PWA caching and home screen access!</span>
                            </div>
                        </div>

                        <!-- Remote / Tailscale Guide -->
                        <div v-else class="text-xs space-y-2 text-neutral-600 dark:text-neutral-400">
                            <p>
                                When away from home Wi-Fi (e.g. coffee shop or cellular data), you can use <strong>Tailscale</strong> (free mesh VPN) for zero-config encrypted access:
                            </p>
                            <ol class="list-decimal pl-4 space-y-1 text-[11px]">
                                <li>Install Tailscale on this PC and on your phone.</li>
                                <li>Log in with the same account on both devices.</li>
                                <li>Access your PC's private Tailscale IP (e.g. <code class="font-mono">http://100.x.y.z:8000</code>) securely from anywhere in the world!</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-6 pt-4 border-t border-neutral-200 dark:border-neutral-800 flex justify-end">
                    <button
                        @click="emit('close')"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-medium bg-neutral-100 hover:bg-neutral-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 text-neutral-700 dark:text-neutral-200 transition"
                    >
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

