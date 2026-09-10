<div
    wire:loading.flex
    class="fixed inset-0 z-[100] items-center justify-center bg-slate-900/30 backdrop-blur-sm"
>
    <div class="flex items-center gap-3 rounded-xl bg-white px-6 py-4 shadow-xl">
        <svg
            class="h-5 w-5 animate-spin text-slate-700"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            ></circle>

            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            ></path>
        </svg>

        <span class="text-sm font-medium text-slate-700">
            Bitte warten …
        </span>
    </div>
</div>
