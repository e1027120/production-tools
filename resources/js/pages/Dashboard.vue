<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';
import { Server, Layers, Zap, Sliders, ArrowRight, ShoppingBag, FileText, Share2, DollarSign, GraduationCap, Network, Activity } from '@lucide/vue';

defineProps<{
    stats: {
        totalRacks: number;
        totalDevices: number;
        totalPower: number;
        totalCatalogDevices: number;
        totalShoppingLists: number;
        sharedShoppingLists: number;
        totalBudgetPrice: number;
        totalTrainings: number;
        pendingAssignments: number;
        totalDiagrams: number;
    }
}>();

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <!-- 19" Racks Module Card -->
            <div class="relative aspect-video rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-lg bg-[#1AC18C]/10 flex items-center justify-center text-[#1AC18C]">
                                <Server class="size-4.5" />
                            </div>
                            <span class="font-bold text-sm tracking-tight text-foreground">19" Rack Builder</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-500 uppercase tracking-wider">
                            Active
                        </span>
                    </div>

                    <!-- Stats grid -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 pt-1">
                        <div class="flex items-center gap-1.5 text-xs">
                            <Server class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Racks:</span>
                            <span class="font-bold text-foreground">{{ stats.totalRacks }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <Layers class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Devices:</span>
                            <span class="font-bold text-foreground">{{ stats.totalDevices }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <Zap class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Power:</span>
                            <span class="font-bold text-foreground">{{ stats.totalPower }}W</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <Sliders class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Catalog:</span>
                            <span class="font-bold text-foreground">{{ stats.totalCatalogDevices }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative pt-2">
                    <Link href="/racks" class="w-full inline-flex items-center justify-center rounded-lg bg-[#22273C] dark:bg-muted text-white dark:text-foreground text-xs font-semibold px-4 py-2 hover:bg-[#1AC18C] hover:text-white dark:hover:bg-[#1AC18C] dark:hover:text-white transition-all duration-200 shadow-sm cursor-pointer group/btn">
                        Open Rack Builder
                        <ArrowRight class="ml-1.5 size-3.5 group-hover/btn:translate-x-0.5 transition-transform duration-200" />
                    </Link>
                </div>
            </div>

            <!-- Shopping Lists Module Card -->
            <div class="relative aspect-video rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-lg bg-[#1AC18C]/10 flex items-center justify-center text-[#1AC18C]">
                                <ShoppingBag class="size-4.5" />
                            </div>
                            <span class="font-bold text-sm tracking-tight text-foreground">Shopping Lists</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-500 uppercase tracking-wider">
                            Active
                        </span>
                    </div>

                    <!-- Stats grid -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 pt-1">
                        <div class="flex items-center gap-1.5 text-xs">
                            <FileText class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Lists:</span>
                            <span class="font-bold text-foreground">{{ stats.totalShoppingLists }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <Share2 class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Shared:</span>
                            <span class="font-bold text-foreground">{{ stats.sharedShoppingLists }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs col-span-2">
                            <DollarSign class="size-3.5 text-[#1AC18C] shrink-0" />
                            <span class="text-muted-foreground font-semibold">Total Budget:</span>
                            <span class="font-bold text-[#1AC18C]">{{ formatCurrency(stats.totalBudgetPrice) }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative pt-2">
                    <Link href="/shopping-lists" class="w-full inline-flex items-center justify-center rounded-lg bg-[#22273C] dark:bg-muted text-white dark:text-foreground text-xs font-semibold px-4 py-2 hover:bg-[#1AC18C] hover:text-white dark:hover:bg-[#1AC18C] dark:hover:text-white transition-all duration-200 shadow-sm cursor-pointer group/btn">
                        Open Shopping Lists
                        <ArrowRight class="ml-1.5 size-3.5 group-hover/btn:translate-x-0.5 transition-transform duration-200" />
                    </Link>
                </div>
            </div>

            <!-- Trainings Module Card -->
            <div class="relative aspect-video rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-lg bg-[#1AC18C]/10 flex items-center justify-center text-[#1AC18C]">
                                <GraduationCap class="size-4.5" />
                            </div>
                            <span class="font-bold text-sm tracking-tight text-foreground">Ministry Trainings</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-500 uppercase tracking-wider">
                            Active
                        </span>
                    </div>

                    <!-- Stats grid -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 pt-1">
                        <div class="flex items-center gap-1.5 text-xs">
                            <GraduationCap class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Workflows:</span>
                            <span class="font-bold text-foreground">{{ stats.totalTrainings }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <Activity class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Pending:</span>
                            <span class="font-bold text-foreground">{{ stats.pendingAssignments }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative pt-2">
                    <Link href="/trainings" class="w-full inline-flex items-center justify-center rounded-lg bg-[#22273C] dark:bg-muted text-white dark:text-foreground text-xs font-semibold px-4 py-2 hover:bg-[#1AC18C] hover:text-white dark:hover:bg-[#1AC18C] dark:hover:text-white transition-all duration-200 shadow-sm cursor-pointer group/btn">
                        Open Trainings
                        <ArrowRight class="ml-1.5 size-3.5 group-hover/btn:translate-x-0.5 transition-transform duration-200" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Technical Diagrams Widget & Full Width Area -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Technical Diagrams Widget Card -->
            <div class="relative aspect-video rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-lg bg-[#1AC18C]/10 flex items-center justify-center text-[#1AC18C]">
                                <Network class="size-4.5" />
                            </div>
                            <span class="font-bold text-sm tracking-tight text-foreground">Technical Diagrams</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-500 uppercase tracking-wider">
                            Active
                        </span>
                    </div>

                    <!-- Stats grid -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 pt-1">
                        <div class="flex items-center gap-1.5 text-xs col-span-2">
                            <FileText class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Blueprints & Flows:</span>
                            <span class="font-bold text-foreground">{{ stats.totalDiagrams }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative pt-2">
                    <Link href="/diagrams" class="w-full inline-flex items-center justify-center rounded-lg bg-[#22273C] dark:bg-muted text-white dark:text-foreground text-xs font-semibold px-4 py-2 hover:bg-[#1AC18C] hover:text-white dark:hover:bg-[#1AC18C] dark:hover:text-white transition-all duration-200 shadow-sm cursor-pointer group/btn">
                        Open Diagrams
                        <ArrowRight class="ml-1.5 size-3.5 group-hover/btn:translate-x-0.5 transition-transform duration-200" />
                    </Link>
                </div>
            </div>

            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:col-span-2"
            >
                <PlaceholderPattern />
            </div>
        </div>
    </div>
</template>
