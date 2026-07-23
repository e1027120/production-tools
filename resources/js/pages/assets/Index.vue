<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Package, Search, Plus, Filter, CircleAlert, Activity, DollarSign, ArrowRight, ShieldAlert, X } from '@lucide/vue';

interface Asset {
    id: number;
    name: string;
    category: string;
    brand: string | null;
    model: string | null;
    serial_number: string | null;
    status: string;
    location: string | null;
    purchase_date: string | null;
    purchase_price: number | null;
    notes: string | null;
    created_at: string;
}

const props = defineProps<{
    assets: Asset[];
    stats: {
        total_count: number;
        total_value: number;
        maintenance_count: number;
        reminders_count: number;
    };
    filters: {
        category?: string;
        status?: string;
        search?: string;
    };
}>();

const page = usePage();
const auth = computed(() => page.props.auth as any);
const currentChurch = computed(() => auth.value?.currentChurch);
const userRole = computed(() => currentChurch.value?.pivot?.role || 'User');
const isReadOnly = computed(() => userRole.value === 'User');

// Local filters state
const searchQuery = ref(props.filters.search || '');
const categoryFilter = ref(props.filters.category || 'All');
const statusFilter = ref(props.filters.status || 'All');

// Show Add Modal
const showAddModal = ref(false);

// Add Asset Form
const form = useForm({
    name: '',
    category: 'Audio',
    brand: '',
    model: '',
    serial_number: '',
    status: 'Active',
    location: '',
    purchase_date: '',
    purchase_price: '' as string | number,
    notes: '',
});

// Format Price
const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

// Filter categories & statuses lists
const categories = ['All', 'Audio', 'Video', 'Lighting', 'IT', 'Instrument', 'Stage', 'Other'];
const statuses = ['All', 'Active', 'Maintenance', 'Retired', 'In Storage'];

// Trigger filter search
const handleSearch = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('search', searchQuery.value);
    url.searchParams.set('category', categoryFilter.value);
    url.searchParams.set('status', statusFilter.value);
    
    // Visit url using Inertia
    const urlString = url.pathname + url.search;
    useForm({}).get(urlString, { preserveState: true });
};

// Reset Filters
const resetFilters = () => {
    searchQuery.value = '';
    categoryFilter.value = 'All';
    statusFilter.value = 'All';
    handleSearch();
};

// Submit Add Asset
const submitAsset = () => {
    form.post('/assets', {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Asset Manager',
                href: '/assets',
            },
        ],
    },
});
</script>

<template>
    <Head title="Asset Manager" />

    <div class="space-y-6 p-4 max-w-7xl mx-auto">
        <!-- Reviewing newly migrated assets banner -->
        <div v-if="filters.review_ids" class="bg-primary/10 border border-primary/20 p-5 rounded-2xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm animate-in fade-in slide-in-from-top-4 duration-300">
            <div class="space-y-1">
                <h4 class="text-xs font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                    <ShieldAlert class="size-4 animate-pulse text-primary" /> Reviewing Migrated Assets
                </h4>
                <p class="text-xs text-muted-foreground leading-normal">
                    This screen is filtered to display the <strong>{{ assets.length }}</strong> asset(s) you just migrated from your shopping list. Please click on their details button to supply missing serial numbers, brands, models, and locations.
                </p>
            </div>
            <Link 
                href="/assets" 
                class="inline-flex items-center justify-center rounded-xl bg-primary text-primary-foreground text-xs font-bold px-4 py-2 hover:bg-primary/95 transition-all shadow-sm shrink-0 cursor-pointer"
            >
                Clear Review Filter
            </Link>
        </div>

        <!-- Top Metrics Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <div class="bg-card border border-border/40 p-5 rounded-2xl flex items-center justify-between shadow-sm relative overflow-hidden group">
                <div class="space-y-1">
                    <span class="text-xs text-muted-foreground font-semibold uppercase tracking-wider block">Total Assets</span>
                    <span class="text-3xl font-extrabold text-foreground tracking-tight block">{{ stats.total_count }}</span>
                </div>
                <div class="size-11 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                    <Package class="size-5" />
                </div>
            </div>

            <div class="bg-card border border-border/40 p-5 rounded-2xl flex items-center justify-between shadow-sm relative overflow-hidden group">
                <div class="space-y-1">
                    <span class="text-xs text-muted-foreground font-semibold uppercase tracking-wider block">Total Value</span>
                    <span class="text-2xl font-extrabold text-foreground tracking-tight block">{{ formatCurrency(stats.total_value) }}</span>
                </div>
                <div class="size-11 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-500 shrink-0">
                    <DollarSign class="size-5" />
                </div>
            </div>

            <div class="bg-card border border-border/40 p-5 rounded-2xl flex items-center justify-between shadow-sm relative overflow-hidden group">
                <div class="space-y-1">
                    <span class="text-xs text-muted-foreground font-semibold uppercase tracking-wider block">In Maintenance</span>
                    <span class="text-3xl font-extrabold text-foreground tracking-tight block">{{ stats.maintenance_count }}</span>
                </div>
                <div class="size-11 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-500 shrink-0">
                    <Activity class="size-5" />
                </div>
            </div>

            <div class="bg-card border border-border/40 p-5 rounded-2xl flex items-center justify-between shadow-sm relative overflow-hidden group">
                <div class="space-y-1">
                    <span class="text-xs text-muted-foreground font-semibold uppercase tracking-wider block">Reminders Due (7d)</span>
                    <span 
                        class="text-3xl font-extrabold tracking-tight block"
                        :class="stats.reminders_count > 0 ? 'text-red-500' : 'text-foreground'"
                    >{{ stats.reminders_count }}</span>
                </div>
                <div 
                    class="size-11 rounded-xl flex items-center justify-center shrink-0"
                    :class="stats.reminders_count > 0 ? 'bg-red-500/10 text-red-500' : 'bg-muted text-muted-foreground'"
                >
                    <CircleAlert class="size-5" :class="{ 'animate-bounce': stats.reminders_count > 0 }" />
                </div>
            </div>
        </div>

        <!-- Filter / Search Section -->
        <div class="bg-card border border-border/40 p-4 rounded-2xl space-y-4 shadow-sm">
            <div class="flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                    <!-- Search Input -->
                    <div class="relative w-full md:w-72">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 size-4 text-muted-foreground" />
                        <Input 
                            v-model="searchQuery"
                            @keyup.enter="handleSearch"
                            placeholder="Search name, brand, model, serial..."
                            class="pl-9 h-10 rounded-xl bg-muted/40 border-border/40 focus-visible:ring-primary w-full text-xs"
                        />
                    </div>

                    <!-- Category Select -->
                    <div class="w-full md:w-44 flex items-center gap-1.5">
                        <Label class="sr-only">Category</Label>
                        <select 
                            v-model="categoryFilter"
                            @change="handleSearch"
                            class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-10 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                        >
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>

                    <!-- Status Select -->
                    <div class="w-full md:w-44 flex items-center gap-1.5">
                        <Label class="sr-only">Status</Label>
                        <select 
                            v-model="statusFilter"
                            @change="handleSearch"
                            class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-10 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                        >
                            <option v-for="st in statuses" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-2 w-full md:w-auto shrink-0 justify-end">
                    <Button 
                        @click="resetFilters" 
                        variant="outline" 
                        class="rounded-xl h-10 text-xs px-4 border-border/60 hover:bg-muted/40 cursor-pointer"
                    >
                        Reset
                    </Button>
                    <Button 
                        v-if="!isReadOnly"
                        @click="showAddModal = true" 
                        class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl h-10 text-xs px-4 cursor-pointer"
                    >
                        <Plus class="size-4 mr-1.5" /> Add Asset
                    </Button>
                </div>
            </div>
        </div>

        <!-- Assets List Card/Grid -->
        <div class="bg-card border border-border/40 rounded-2xl shadow-sm overflow-hidden">
            <div v-if="assets.length > 0" class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border/40 bg-muted/20 text-[10px] uppercase font-bold text-muted-foreground tracking-wider">
                            <th class="py-3 px-4">Asset Name</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Model & Brand</th>
                            <th class="py-3 px-4">Location</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Value</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/20 text-xs">
                        <tr v-for="asset in assets" :key="asset.id" class="hover:bg-muted/20 transition-colors">
                            <td class="py-3.5 px-4">
                                <Link :href="`/assets/${asset.id}`" class="font-bold text-foreground hover:text-[#1AC18C] transition-colors">
                                    {{ asset.name }}
                                </Link>
                                <span v-if="asset.serial_number" class="block text-[10px] text-muted-foreground mt-0.5 font-mono">S/N: {{ asset.serial_number }}</span>
                                <span v-if="!asset.serial_number || !asset.brand || !asset.model || !asset.location" class="inline-flex items-center gap-1 text-[9px] font-bold text-amber-500 bg-amber-500/10 px-1 py-0.5 rounded uppercase mt-1">
                                    Needs Specs
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-medium text-muted-foreground">{{ asset.category }}</td>
                            <td class="py-3.5 px-4">
                                <span v-if="asset.brand || asset.model" class="text-foreground">
                                    {{ asset.brand }} {{ asset.model }}
                                </span>
                                <span v-else class="text-muted-foreground italic">Generic</span>
                            </td>
                            <td class="py-3.5 px-4 text-muted-foreground">{{ asset.location || '—' }}</td>
                            <td class="py-3.5 px-4">
                                <span 
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                                    :class="{
                                        'bg-emerald-500/10 text-emerald-500 border-emerald-500/20': asset.status === 'Active',
                                        'bg-amber-500/10 text-amber-500 border-amber-500/20': asset.status === 'Maintenance',
                                        'bg-blue-500/10 text-blue-500 border-blue-500/20': asset.status === 'In Storage',
                                        'bg-red-500/10 text-red-500 border-red-500/20': asset.status === 'Retired',
                                    }"
                                >
                                    {{ asset.status }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-semibold text-foreground">
                                {{ asset.purchase_price ? formatCurrency(Number(asset.purchase_price)) : '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <Link 
                                    :href="`/assets/${asset.id}`" 
                                    class="inline-flex items-center justify-center size-8 rounded-lg border border-border/50 hover:bg-muted text-muted-foreground hover:text-foreground transition-all"
                                >
                                    <ArrowRight class="size-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16 px-4 space-y-4">
                <div class="size-16 rounded-full bg-muted flex items-center justify-center text-muted-foreground mx-auto">
                    <Package class="size-8" />
                </div>
                <div class="space-y-1">
                    <h3 class="font-bold text-base text-foreground">No assets found</h3>
                    <p class="text-xs text-muted-foreground max-w-sm mx-auto leading-normal">
                        No equipment matched your filters, or this Church workspace hasn't registered technical assets yet.
                    </p>
                </div>
                <div v-if="!isReadOnly">
                    <Button 
                        @click="showAddModal = true" 
                        class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl h-9 text-xs px-4 cursor-pointer"
                    >
                        <Plus class="size-4 mr-1.5" /> Add Asset Now
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: Add Asset -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Package class="size-5 text-primary" />
                        <h3 class="font-bold text-base text-foreground">Register New Asset</h3>
                    </div>
                    <button @click="showAddModal = false" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </div>
                <form @submit.prevent="submitAsset" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="asset-name" class="text-xs text-foreground font-bold">Asset Name</Label>
                        <Input 
                            id="asset-name" 
                            v-model="form.name" 
                            placeholder="e.g. Center Stage Laser Projector" 
                            required 
                            class="rounded-xl h-9 text-xs"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="asset-category" class="text-xs text-foreground font-bold">Category</Label>
                            <select 
                                id="asset-category"
                                v-model="form.category"
                                class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-9 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="Audio">Audio</option>
                                <option value="Video">Video</option>
                                <option value="Lighting">Lighting</option>
                                <option value="IT">IT</option>
                                <option value="Instrument">Instrument</option>
                                <option value="Stage">Stage</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="asset-status" class="text-xs text-foreground font-bold">Status</Label>
                            <select 
                                id="asset-status"
                                v-model="form.status"
                                class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-9 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="Active">Active</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="In Storage">In Storage</option>
                                <option value="Retired">Retired</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="asset-brand" class="text-xs text-foreground font-bold">Brand</Label>
                            <Input id="asset-brand" v-model="form.brand" placeholder="e.g. Panasonic" class="rounded-xl h-9 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="asset-model" class="text-xs text-foreground font-bold">Model</Label>
                            <Input id="asset-model" v-model="form.model" placeholder="e.g. PT-RZ990" class="rounded-xl h-9 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="asset-serial" class="text-xs text-foreground font-bold">Serial Number</Label>
                            <Input id="asset-serial" v-model="form.serial_number" placeholder="S/N: 92830B8" class="rounded-xl h-9 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="asset-location" class="text-xs text-foreground font-bold">Location</Label>
                            <Input id="asset-location" v-model="form.location" placeholder="e.g. Sanctuary Rigging" class="rounded-xl h-9 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="asset-purchase-date" class="text-xs text-foreground font-bold">Purchase Date</Label>
                            <Input id="asset-purchase-date" type="date" v-model="form.purchase_date" class="rounded-xl h-9 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="asset-price" class="text-xs text-foreground font-bold">Purchase Price ($)</Label>
                            <Input id="asset-price" type="number" step="0.01" min="0" v-model="form.purchase_price" placeholder="0.00" class="rounded-xl h-9 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="asset-notes" class="text-xs text-foreground font-bold">Specifications / Notes</Label>
                        <textarea 
                            id="asset-notes" 
                            v-model="form.notes" 
                            placeholder="Add specs, lens details, power requirements, etc..." 
                            rows="3" 
                            class="flex w-full rounded-xl border border-input bg-transparent px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button 
                            type="button" 
                            @click="showAddModal = false" 
                            variant="outline" 
                            class="rounded-xl text-xs h-9 cursor-pointer"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit" 
                            :disabled="form.processing || !form.name.trim()" 
                            class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl text-xs h-9 cursor-pointer"
                        >
                            {{ form.processing ? 'Registering...' : 'Register' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
