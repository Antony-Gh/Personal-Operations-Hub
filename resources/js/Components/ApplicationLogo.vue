<template>
    <svg
        viewBox="0 0 40 40"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        class="h-10 w-10"
        aria-hidden="true"
    >
        <!-- Rounded Square Container -->
        <rect
            x="2"
            y="2"
            width="36"
            height="36"
            rx="10"
            class="fill-indigo-600 dark:fill-indigo-500"
        />
        <!-- Inner Hub Concentric Nodes -->
        <circle cx="20" cy="20" r="5" fill="white" />
        <circle cx="20" cy="10" r="2.5" fill="white" fill-opacity="0.85" />
        <circle cx="20" cy="30" r="2.5" fill="white" fill-opacity="0.85" />
        <circle cx="10" cy="20" r="2.5" fill="white" fill-opacity="0.85" />
        <circle cx="30" cy="20" r="2.5" fill="white" fill-opacity="0.85" />
        <!-- Interconnecting Operational Vectors -->
        <path
            d="M20 12.5V15M20 25V27.5M12.5 20H15M25 20H27.5"
            stroke="white"
            stroke-width="2"
            stroke-linecap="round"
            stroke-opacity="0.9"
        />
    </svg>
</template>
