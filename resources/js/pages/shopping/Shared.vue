<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { 
    ShoppingBag, 
    ExternalLink, 
    Building2,
    DollarSign,
    Info
} from '@lucide/vue';

interface SharedListItem {
    id: number;
    name: string;
    unit_price: number;
    quantity: number;
    link: string | null;
    comments: string | null;
    total_price: number;
}

interface SharedList {
    name: string;
    description: string | null;
    church_name: string;
    items: SharedListItem[];
    total_price: number;
}

defineProps<{
    list: SharedList;
}>();

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};
</script>

<template>
    <Head :title="`Shared Shopping List: ${list.name}`" />

    <div class="min-h-screen bg-background text-foreground flex flex-col antialiased">
        <!-- Top clean premium navigation header -->
        <header class="border-b border-border/40 bg-card/60 backdrop-blur-md sticky top-0 z-10 px-6 py-4">
            <div class="max-w-6xl mx-auto flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                        <ShoppingBag class="size-5" />
                    </div>
                    <div>
                        <h1 class="text-sm font-bold leading-tight">{{ list.name }}</h1>
                        <span class="text-[10px] text-muted-foreground flex items-center gap-1">
                            <Building2 class="size-3 text-muted-foreground/60" />
                            {{ list.church_name }} Church Production Upgrade
                        </span>
                    </div>
                </div>

                <div class="text-xs font-semibold text-foreground bg-[#1AC18C]/10 border border-[#1AC18C]/20 px-3 py-1 rounded-full flex items-center">
                    <span class="text-muted-foreground mr-1.5 font-normal">Est. Budget:</span>
                    <span class="font-bold text-[#1AC18C]">{{ formatCurrency(list.total_price) }}</span>
                </div>
            </div>
        </header>

        <!-- Main Workspace Area -->
        <main class="flex-1 max-w-6xl w-full mx-auto p-6 space-y-6">
            <!-- Alert Badge explaining read-only access -->
            <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex gap-2.5 items-start">
                <Info class="size-4.5 shrink-0 mt-0.5" />
                <div>
                    <span class="font-bold text-xs block leading-tight">Shared Guest View Mode</span>
                    <span class="text-[10px] block text-amber-500/90 font-normal">You are viewing a real-time snapshot spreadsheet of this equipment checklist. No login required.</span>
                </div>
            </div>

            <!-- Description Block -->
            <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-2 relative overflow-hidden shadow-sm">
                <div class="absolute -right-4 -top-4 size-20 rounded-full bg-primary/5 blur-xl"></div>
                <h3 class="font-bold text-xs uppercase tracking-wider text-muted-foreground">List Info & Notes</h3>
                <p class="text-xs text-foreground/80 leading-relaxed max-w-3xl">
                    {{ list.description || 'This church production team has not provided an additional description notes for this upgrade list.' }}
                </p>
            </div>

            <!-- Spreadsheet Board -->
            <div class="bg-card border border-border/60 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-border/40 bg-muted/20 flex justify-between items-center">
                    <span class="text-xs uppercase tracking-wider font-bold text-muted-foreground">Equipment Budget Checklist</span>
                    <span class="text-xs font-bold text-foreground bg-primary/5 border border-primary/10 px-2.5 py-0.5 rounded-full">
                        {{ list.items.length }} line items
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/10 border-b border-border/60 text-muted-foreground font-bold uppercase tracking-wider text-[10px]">
                                <th class="py-3 px-4">Item Name</th>
                                <th class="py-3 px-3 text-right">Unit Price</th>
                                <th class="py-3 px-3 text-center">Qty</th>
                                <th class="py-3 px-3 text-right">Subtotal</th>
                                <th class="py-3 px-3 text-center">Order Link</th>
                                <th class="py-3 px-4">Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in list.items" :key="item.id" class="border-b border-border/40 hover:bg-muted/5 transition-colors">
                                <td class="py-3.5 px-4 font-bold text-foreground">{{ item.name }}</td>
                                <td class="py-3.5 px-3 text-right text-muted-foreground">{{ formatCurrency(item.unit_price) }}</td>
                                <td class="py-3.5 px-3 text-center font-semibold">{{ item.quantity }}</td>
                                <td class="py-3.5 px-3 text-right font-bold text-foreground bg-primary/5">{{ formatCurrency(item.total_price) }}</td>
                                <td class="py-3.5 px-3 text-center">
                                    <a 
                                        v-if="item.link" 
                                        :href="item.link" 
                                        target="_blank" 
                                        class="inline-flex size-7 items-center justify-center bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-all"
                                        title="Purchase link"
                                    >
                                        <ExternalLink class="size-3.5" />
                                    </a>
                                    <span v-else class="text-muted-foreground/40">-</span>
                                </td>
                                <td class="py-3.5 px-4 text-muted-foreground max-w-[250px] truncate" :title="item.comments || ''">
                                    {{ item.comments || '-' }}
                                </td>
                            </tr>
                            <tr v-if="list.items.length === 0">
                                <td colspan="6" class="py-12 text-center text-muted-foreground italic">
                                    This list doesn't contain any upgrade products yet.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-muted/30 border-t-2 border-border/80 font-bold text-foreground text-sm">
                                <td colspan="3" class="py-4 px-4 text-right">Total Price:</td>
                                <td class="py-4 px-3 text-right bg-primary/10 text-primary border border-primary/20">{{ formatCurrency(list.total_price) }}</td>
                                <td colspan="2" class="py-4 px-4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="border-t border-border/40 py-8 text-center text-[10px] text-muted-foreground bg-card/10 mt-auto">
            <span>Powered by Church Production Workspace platform tools.</span>
        </footer>
    </div>
</template>
