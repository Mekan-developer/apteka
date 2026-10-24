<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import MedicalCrossIcon from '@/components/MedicalCrossIcon.vue';
import { Toaster } from '@/components/ui/sonner';

const name = usePage().props.name;
</script>

<template>
    <div>
        <div class="grid min-h-svh md:grid-cols-[1fr_2fr]">
            <aside
                class="relative hidden flex-col justify-between overflow-hidden bg-linear-to-br from-emerald-700 to-emerald-900 p-8 text-white md:flex lg:p-12"
            >
                <MedicalCrossIcon class="size-20 lg:size-28" />
                <p class="text-5xl font-semibold tracking-tight lg:text-7xl">
                    {{ name }}
                </p>
                <div aria-hidden="true" />
            </aside>

            <main
                class="dark flex items-center justify-center bg-slate-950 bg-[radial-gradient(rgb(255_255_255/0.08)_1px,transparent_1px)] bg-[length:22px_22px] px-4 py-10 text-foreground"
            >
                <div
                    class="w-full max-w-sm rounded-2xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl shadow-black/40 backdrop-blur sm:p-7"
                >
                    <div class="mb-6 flex items-center justify-center gap-3">
                        <div
                            class="flex size-12 items-center justify-center rounded-xl border border-emerald-500/40 bg-emerald-500/10 text-emerald-500"
                        >
                            <MedicalCrossIcon class="size-7" />
                        </div>
                        <span
                            class="text-3xl font-semibold tracking-tight text-emerald-100"
                        >
                            {{ name }}
                        </span>
                    </div>
                    <slot />
                </div>
            </main>
        </div>
        <Toaster />
    </div>
</template>
