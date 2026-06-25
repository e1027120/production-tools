<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';
import { Server, Layers, Zap, Sliders, ArrowRight, ShoppingBag, FileText, Share2, DollarSign, GraduationCap, Network, Activity, Ruler } from '@lucide/vue';

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
        totalCablePlans: number;
        totalCableRuns: number;
        totalCablesLength: number;
    };
    latestRacks: Array<{
        id: number;
        name: string;
        size: number;
        devices_count: number;
        power: number;
    }>;
    latestShoppingLists: Array<{
        id: number;
        name: string;
        items_count: number;
        total_price: number;
        is_shared: boolean;
    }>;
    latestTrainings: Array<{
        id: number;
        title: string;
        ministry: string;
        steps_count: number;
    }>;
    latestDiagrams: Array<{
        id: number;
        name: string;
        description: string | null;
    }>;
    latestCablePlans: Array<{
        id: number;
        name: string;
        runs_count: number;
        length: number;
        unit: string;
    }>;
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

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <!-- 19" Racks Module Card -->
            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300 min-h-[320px]">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3 flex-1">
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
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 pt-1 pb-3 border-b border-sidebar-border/30">
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

                    <!-- Quick List Table -->
                    <div class="space-y-2 pt-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex justify-between">
                            <span>Latest Racks</span>
                            <span>Specs</span>
                        </div>
                        <div class="space-y-1.5 max-h-[140px] overflow-y-auto pr-1">
                            <div v-for="rack in latestRacks" :key="rack.id" class="flex justify-between items-center text-xs group/item hover:bg-muted/40 p-1.5 rounded-lg transition-colors">
                                <Link :href="`/racks/${rack.id}`" class="font-semibold text-foreground hover:text-[#1AC18C] transition-colors truncate max-w-[150px]" title="Edit rack layout">
                                    {{ rack.name }}
                                </Link>
                                <span class="text-[10px] text-muted-foreground font-mono shrink-0">
                                    {{ rack.size }}U • {{ rack.devices_count }} dev • {{ rack.power }}W
                                </span>
                            </div>
                            <div v-if="latestRacks.length === 0" class="text-[11px] text-muted-foreground italic text-center py-2">
                                No racks built yet.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative pt-4 mt-auto border-t border-sidebar-border/30">
                    <Link href="/racks" class="w-full inline-flex items-center justify-center rounded-lg bg-[#22273C] dark:bg-muted text-white dark:text-foreground text-xs font-semibold px-4 py-2 hover:bg-[#1AC18C] hover:text-white dark:hover:bg-[#1AC18C] dark:hover:text-white transition-all duration-200 shadow-sm cursor-pointer group/btn">
                        Open Rack Builder
                        <ArrowRight class="ml-1.5 size-3.5 group-hover/btn:translate-x-0.5 transition-transform duration-200" />
                    </Link>
                </div>
            </div>

            <!-- Shopping Lists Module Card -->
            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300 min-h-[320px]">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3 flex-1">
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
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 pt-1 pb-3 border-b border-sidebar-border/30">
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

                    <!-- Quick List Table -->
                    <div class="space-y-2 pt-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex justify-between">
                            <span>Latest Lists</span>
                            <span>Totals</span>
                        </div>
                        <div class="space-y-1.5 max-h-[140px] overflow-y-auto pr-1">
                            <div v-for="list in latestShoppingLists" :key="list.id" class="flex justify-between items-center text-xs group/item hover:bg-muted/40 p-1.5 rounded-lg transition-colors">
                                <Link :href="`/shopping-lists/${list.id}`" class="font-semibold text-foreground hover:text-[#1AC18C] transition-colors truncate max-w-[150px]" title="View shopping list">
                                    {{ list.name }}
                                </Link>
                                <span class="text-[10px] text-muted-foreground font-mono shrink-0">
                                    {{ list.items_count }} items • {{ formatCurrency(list.total_price) }}
                                </span>
                            </div>
                            <div v-if="latestShoppingLists.length === 0" class="text-[11px] text-muted-foreground italic text-center py-2">
                                No shopping lists created yet.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative pt-4 mt-auto border-t border-sidebar-border/30">
                    <Link href="/shopping-lists" class="w-full inline-flex items-center justify-center rounded-lg bg-[#22273C] dark:bg-muted text-white dark:text-foreground text-xs font-semibold px-4 py-2 hover:bg-[#1AC18C] hover:text-white dark:hover:bg-[#1AC18C] dark:hover:text-white transition-all duration-200 shadow-sm cursor-pointer group/btn">
                        Open Shopping Lists
                        <ArrowRight class="ml-1.5 size-3.5 group-hover/btn:translate-x-0.5 transition-transform duration-200" />
                    </Link>
                </div>
            </div>

            <!-- Trainings Module Card -->
            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300 min-h-[320px]">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3 flex-1">
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
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 pt-1 pb-3 border-b border-sidebar-border/30">
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

                    <!-- Quick List Table -->
                    <div class="space-y-2 pt-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex justify-between">
                            <span>Latest Modules</span>
                            <span>Ministry / Steps</span>
                        </div>
                        <div class="space-y-1.5 max-h-[140px] overflow-y-auto pr-1">
                            <div v-for="training in latestTrainings" :key="training.id" class="flex justify-between items-center text-xs group/item hover:bg-muted/40 p-1.5 rounded-lg transition-colors">
                                <Link :href="`/trainings/${training.id}`" class="font-semibold text-foreground hover:text-[#1AC18C] transition-colors truncate max-w-[150px]" title="View training details">
                                    {{ training.title }}
                                </Link>
                                <span class="text-[10px] text-muted-foreground font-mono shrink-0">
                                    {{ training.ministry }} • {{ training.steps_count }} steps
                                </span>
                            </div>
                            <div v-if="latestTrainings.length === 0" class="text-[11px] text-muted-foreground italic text-center py-2">
                                No training modules built yet.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative pt-4 mt-auto border-t border-sidebar-border/30">
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
            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300 min-h-[320px]">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3 flex-1">
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
                    <div class="grid grid-cols-1 gap-y-2 pt-1 pb-3 border-b border-sidebar-border/30">
                        <div class="flex items-center gap-1.5 text-xs">
                            <FileText class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Blueprints & Flows:</span>
                            <span class="font-bold text-foreground">{{ stats.totalDiagrams }}</span>
                        </div>
                    </div>

                    <!-- Quick List Table -->
                    <div class="space-y-2 pt-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex justify-between">
                            <span>Latest Diagrams</span>
                            <span>Description</span>
                        </div>
                        <div class="space-y-1.5 max-h-[140px] overflow-y-auto pr-1">
                            <div v-for="diagram in latestDiagrams" :key="diagram.id" class="flex justify-between items-center text-xs group/item hover:bg-muted/40 p-1.5 rounded-lg transition-colors">
                                <Link :href="`/diagrams/${diagram.id}`" class="font-semibold text-foreground hover:text-[#1AC18C] transition-colors truncate max-w-[140px]" title="Edit technical diagram">
                                    {{ diagram.name }}
                                </Link>
                                <span class="text-[10px] text-muted-foreground truncate max-w-[140px] shrink-0 font-medium">
                                    {{ diagram.description || 'No description' }}
                                </span>
                            </div>
                            <div v-if="latestDiagrams.length === 0" class="text-[11px] text-muted-foreground italic text-center py-2">
                                No diagrams created yet.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative pt-4 mt-auto border-t border-sidebar-border/30">
                    <Link href="/diagrams" class="w-full inline-flex items-center justify-center rounded-lg bg-[#22273C] dark:bg-muted text-white dark:text-foreground text-xs font-semibold px-4 py-2 hover:bg-[#1AC18C] hover:text-white dark:hover:bg-[#1AC18C] dark:hover:text-white transition-all duration-200 shadow-sm cursor-pointer group/btn">
                        Open Diagrams
                        <ArrowRight class="ml-1.5 size-3.5 group-hover/btn:translate-x-0.5 transition-transform duration-200" />
                    </Link>
                </div>
            </div>

            <!-- Cable Calculator Widget Card -->
            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-card p-5 flex flex-col justify-between overflow-hidden group hover:border-[#1AC18C]/40 transition-all duration-300 min-h-[320px]">
                <!-- Decorative background elements -->
                <div class="absolute -right-4 -top-4 size-24 rounded-full bg-[#1AC18C]/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>
                
                <div class="relative space-y-3 flex-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="size-8 rounded-lg bg-[#1AC18C]/10 flex items-center justify-center text-[#1AC18C]">
                                <Ruler class="size-4.5" />
                            </div>
                            <span class="font-bold text-sm tracking-tight text-foreground">Cable Calculator</span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-500 uppercase tracking-wider">
                            Active
                        </span>
                    </div>

                    <!-- Stats grid -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 pt-1 pb-3 border-b border-sidebar-border/30">
                        <div class="flex items-center gap-1.5 text-xs">
                            <FileText class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Maps:</span>
                            <span class="font-bold text-foreground">{{ stats.totalCablePlans }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <Sliders class="size-3.5 text-muted-foreground shrink-0" />
                            <span class="text-muted-foreground">Runs:</span>
                            <span class="font-bold text-foreground">{{ stats.totalCableRuns }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs col-span-2">
                            <Activity class="size-3.5 text-[#1AC18C] shrink-0" />
                            <span class="text-muted-foreground font-semibold">Total length:</span>
                            <span class="font-bold text-[#1AC18C]">{{ stats.totalCablesLength.toFixed(1) }}m</span>
                        </div>
                    </div>

                    <!-- Quick List Table -->
                    <div class="space-y-2 pt-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground flex justify-between">
                            <span>Latest Maps</span>
                            <span>Runs / Length</span>
                        </div>
                        <div class="space-y-1.5 max-h-[140px] overflow-y-auto pr-1">
                            <div v-for="plan in latestCablePlans" :key="plan.id" class="flex justify-between items-center text-xs group/item hover:bg-muted/40 p-1.5 rounded-lg transition-colors">
                                <Link :href="`/cable-plans/${plan.id}`" class="font-semibold text-foreground hover:text-[#1AC18C] transition-colors truncate max-w-[150px]" title="Edit cable blueprint">
                                    {{ plan.name }}
                                </Link>
                                <span class="text-[10px] text-muted-foreground font-mono shrink-0">
                                    {{ plan.runs_count }} runs • {{ plan.length.toFixed(1) }}{{ plan.unit }}
                                </span>
                            </div>
                            <div v-if="latestCablePlans.length === 0" class="text-[11px] text-muted-foreground italic text-center py-2">
                                No plans created yet.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative pt-4 mt-auto border-t border-sidebar-border/30">
                    <Link href="/cable-plans" class="w-full inline-flex items-center justify-center rounded-lg bg-[#22273C] dark:bg-muted text-white dark:text-foreground text-xs font-semibold px-4 py-2 hover:bg-[#1AC18C] hover:text-white dark:hover:bg-[#1AC18C] dark:hover:text-white transition-all duration-200 shadow-sm cursor-pointer group/btn">
                        Open Calculator
                        <ArrowRight class="ml-1.5 size-3.5 group-hover/btn:translate-x-0.5 transition-transform duration-200" />
                    </Link>
                </div>
            </div>

            <div class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border min-h-[320px] overflow-hidden">
                <PlaceholderPattern />
            </div>
        </div>
    </div>
</template>
