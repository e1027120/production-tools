<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    ShoppingBag, 
    Plus, 
    Trash2, 
    Calendar, 
    User, 
    ArrowRight, 
    FileText,
    DollarSign,
    Share2
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface ShoppingList {
    id: number;
    name: string;
    description: string | null;
    share_token: string | null;
    shared_emails: string[];
    created_by: string;
    created_at: string;
    items_count: number;
    total_price: number;
}

defineProps<{
    lists: ShoppingList[];
}>();

const showCreateModal = ref(false);

const form = useForm({
    name: '',
    description: '',
});

const submitCreate = () => {
    form.post('/shopping-lists', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const deleteList = (id: number) => {
    if (confirm('Are you sure you want to delete this shopping list?')) {
        useForm({}).delete(`/shopping-lists/${id}`);
    }
};

const formatDate = (isoString: string) => {
    const d = new Date(isoString);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Shopping Lists', href: '' }
        ]
    }
});
</script>

<template>
    <Head title="Shopping Lists" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6 overflow-x-auto rounded-xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-border/40 pb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                    <ShoppingBag class="size-6" />
                </div>
                <div>
                    <h1 class="font-bold text-2xl text-foreground">Shopping Lists</h1>
                    <p class="text-xs text-muted-foreground">Manage multi-phase upgrade budgets, equipment items, links, and share plans with externals.</p>
                </div>
            </div>

            <Button 
                @click="showCreateModal = true"
                class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs"
            >
                <Plus class="mr-1.5 size-4" /> Create List
            </Button>
        </div>

        <!-- Lists Grid -->
        <div v-if="lists.length === 0" class="border border-border/40 rounded-2xl bg-card p-14 text-center flex flex-col items-center justify-center space-y-3">
            <ShoppingBag class="size-12 text-muted-foreground/60 animate-pulse" />
            <h3 class="font-bold text-base text-foreground">No shopping lists yet</h3>
            <p class="text-xs text-muted-foreground max-w-sm">Create lists to calculate quantities, unit prices, and phase-out Church tech upgrades.</p>
            <Button 
                @click="showCreateModal = true"
                class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer text-xs"
            >
                Get Started
            </Button>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div 
                v-for="list in lists" 
                :key="list.id"
                class="bg-card border border-border/60 hover:border-[#1AC18C]/40 rounded-2xl p-5 flex flex-col justify-between transition-all duration-300 group overflow-hidden relative"
            >
                <!-- Decorative background accent -->
                <div class="absolute -right-4 -top-4 size-20 rounded-full bg-primary/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>

                <div class="space-y-2 relative">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5 text-primary">
                            <FileText class="size-4" />
                            <span class="text-[9px] font-bold uppercase tracking-wider">Upgrade Phase</span>
                        </div>
                        <div v-if="list.share_token" class="flex items-center gap-1 text-[9px] text-[#1AC18C] bg-[#1AC18C]/10 px-1.5 py-0.5 rounded-full font-bold">
                            <Share2 class="size-2.5" /> Shared
                        </div>
                    </div>
                    <h3 class="font-bold text-base text-foreground leading-snug group-hover:text-primary transition-colors">{{ list.name }}</h3>
                    <p class="text-xs text-muted-foreground line-clamp-2 h-8">{{ list.description || 'No description provided.' }}</p>
                </div>

                <div class="pt-4 border-t border-border/40 mt-4 flex flex-col gap-1.5 text-[10px] text-muted-foreground relative">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <User class="size-3.5" />
                            <span>By: {{ list.created_by }}</span>
                        </div>
                        <span class="font-semibold text-foreground text-xs">{{ list.items_count }} items</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <Calendar class="size-3.5" />
                            <span>{{ formatDate(list.created_at) }}</span>
                        </div>
                        <div class="flex items-center text-foreground font-bold text-sm bg-primary/5 px-2 py-0.5 rounded-lg border border-primary/10">
                            <DollarSign class="size-3.5 text-primary" />
                            <span>{{ formatCurrency(list.total_price) }}</span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex gap-2 relative">
                    <Link 
                        :href="`/shopping-lists/${list.id}`"
                        class="flex-1 inline-flex items-center justify-center rounded-xl bg-primary text-primary-foreground text-xs font-semibold px-4 py-2 hover:bg-primary/95 transition-all duration-200 cursor-pointer shadow-sm"
                    >
                        Edit Details
                        <ArrowRight class="ml-1.5 size-3.5" />
                    </Link>

                    <Button 
                        @click="deleteList(list.id)"
                        variant="ghost"
                        class="size-9 p-0 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-xl cursor-pointer"
                        title="Delete List"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: Create Shopping List -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <ShoppingBag class="size-5 text-primary" />
                        New Shopping List
                    </h3>
                    <button @click="showCreateModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="l-name">List Name</Label>
                        <Input 
                            id="l-name"
                            v-model="form.name"
                            placeholder="e.g. FY26 Audio Upgrade - Stage Monitors"
                            required
                            class="rounded-xl"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="l-desc">Description</Label>
                        <textarea 
                            id="l-desc"
                            v-model="form.description"
                            placeholder="Describe phase upgrades or details..."
                            rows="3"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showCreateModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ form.processing ? 'Creating...' : 'Create List' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
