<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    ShoppingBag, 
    ArrowLeft, 
    Plus, 
    Trash2, 
    Edit, 
    ExternalLink, 
    Share2, 
    Mail, 
    Copy, 
    Check, 
    X,
    Save
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface ShoppingListItem {
    id: number;
    name: string;
    unit_price: number;
    quantity: number;
    link: string | null;
    comments: string | null;
    total_price: number;
}

interface ShoppingList {
    id: number;
    name: string;
    description: string | null;
    share_token: string | null;
    shared_emails: string[];
    created_by: string;
    created_at: string;
    items: ShoppingListItem[];
    total_price: number;
}

const props = defineProps<{
    list: ShoppingList;
}>();

// Editor / Form states
const showItemModal = ref(false);
const editingItem = ref<ShoppingListItem | null>(null);

const itemForm = useForm({
    name: '',
    unit_price: 0,
    quantity: 1,
    link: '',
    comments: '',
});

const openAddItem = () => {
    editingItem.value = null;
    itemForm.reset();
    itemForm.clearErrors();
    showItemModal.value = true;
};

const openEditItem = (item: ShoppingListItem) => {
    editingItem.value = item;
    itemForm.name = item.name;
    itemForm.unit_price = item.unit_price;
    itemForm.quantity = item.quantity;
    itemForm.link = item.link || '';
    itemForm.comments = item.comments || '';
    itemForm.clearErrors();
    showItemModal.value = true;
};

const submitItem = () => {
    if (editingItem.value) {
        itemForm.put(`/shopping-lists/${props.list.id}/items/${editingItem.value.id}`, {
            onSuccess: () => {
                showItemModal.value = false;
                itemForm.reset();
            }
        });
    } else {
        itemForm.post(`/shopping-lists/${props.list.id}/items`, {
            onSuccess: () => {
                showItemModal.value = false;
                itemForm.reset();
            }
        });
    }
};

const deleteItem = (item: ShoppingListItem) => {
    if (confirm(`Remove ${item.name} from list?`)) {
        useForm({}).delete(`/shopping-lists/${props.list.id}/items/${item.id}`);
    }
};

// Share toggles
const copied = ref(false);
const shareEmail = ref('');
const shareEmailForm = useForm({ email: '' });

const copyShareLink = () => {
    const url = `${window.location.origin}/shopping-lists/shared/${props.list.share_token}`;
    navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const togglePublicShare = () => {
    useForm({}).post(`/shopping-lists/${props.list.id}/toggle-share`, {
        preserveScroll: true
    });
};

const submitShareEmail = () => {
    shareEmailForm.email = shareEmail.value;
    shareEmailForm.post(`/shopping-lists/${props.list.id}/share-email`, {
        preserveScroll: true,
        onSuccess: () => {
            shareEmail.value = '';
            shareEmailForm.reset();
        }
    });
};

const removeEmail = (email: string) => {
    useForm({ email }).post(`/shopping-lists/${props.list.id}/remove-shared-email`, {
        preserveScroll: true
    });
};

// List details form for inline edit name/desc
const showEditListModal = ref(false);
const listForm = useForm({
    name: props.list.name,
    description: props.list.description || '',
});

const submitListUpdate = () => {
    listForm.put(`/shopping-lists/${props.list.id}`, {
        onSuccess: () => {
            showEditListModal.value = false;
        }
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Shopping Lists', href: '/shopping-lists' },
            { title: 'Editor', href: '' }
        ]
    }
});
</script>

<template>
    <Head :title="`Shopping List: ${list.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6 overflow-x-auto rounded-xl">
        <!-- Top bar/Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-border/40 pb-4">
            <div class="space-y-1.5 flex-1">
                <div class="flex items-center gap-2">
                    <Link href="/shopping-lists" class="inline-flex items-center text-xs text-muted-foreground hover:text-primary transition-colors">
                        <ArrowLeft class="mr-1 size-3.5" /> Back to Lists
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <h1 class="font-bold text-2xl text-foreground">{{ list.name }}</h1>
                    <Button 
                        @click="showEditListModal = true" 
                        variant="ghost" 
                        size="sm" 
                        class="h-8 px-2 text-muted-foreground hover:text-foreground cursor-pointer rounded-lg border border-border/30 hover:bg-muted"
                    >
                        <Edit class="size-4" />
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground max-w-2xl">{{ list.description || 'No description provided.' }}</p>
            </div>

            <Button 
                @click="openAddItem"
                class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs self-start md:self-auto"
            >
                <Plus class="mr-1.5 size-4" /> Add Item
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <!-- Left: Items spreadsheet table -->
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-card border border-border/60 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-border/40 flex justify-between items-center bg-muted/20">
                        <span class="text-xs uppercase tracking-wider font-bold text-muted-foreground">List Items Spreadsheet</span>
                        <span class="text-xs font-semibold text-foreground bg-primary/10 border border-primary/20 px-2 py-0.5 rounded-full">
                            Total List Price: {{ formatCurrency(list.total_price) }}
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
                                    <th class="py-3 px-3 text-center">Link</th>
                                    <th class="py-3 px-4">Comments</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
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
                                            title="View Online Order Product Link"
                                        >
                                            <ExternalLink class="size-3.5" />
                                        </a>
                                        <span v-else class="text-muted-foreground/40">-</span>
                                    </td>
                                    <td class="py-3.5 px-4 text-muted-foreground max-w-[150px] truncate" :title="item.comments || ''">
                                        {{ item.comments || '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <Button 
                                                @click="openEditItem(item)" 
                                                size="sm" 
                                                variant="ghost" 
                                                class="size-8 p-0 hover:bg-muted text-muted-foreground hover:text-foreground rounded-lg"
                                            >
                                                <Edit class="size-3.5" />
                                            </Button>
                                            <Button 
                                                @click="deleteItem(item)" 
                                                size="sm" 
                                                variant="ghost" 
                                                class="size-8 p-0 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-lg"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="list.items.length === 0">
                                    <td colspan="7" class="py-12 text-center text-muted-foreground italic">
                                        No items on this list yet. Click "Add Item" to start building your upgrade spreadsheet.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-muted/30 border-t-2 border-border/80 font-bold text-foreground text-sm">
                                    <td colspan="3" class="py-4 px-4 text-right">Grand Total:</td>
                                    <td class="py-4 px-3 text-right bg-primary/10 text-primary border border-primary/20">{{ formatCurrency(list.total_price) }}</td>
                                    <td colspan="3" class="py-4 px-4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Share Settings Panel -->
            <div class="space-y-6">
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center gap-2 border-b border-border/40 pb-3">
                        <Share2 class="size-5 text-primary" />
                        <h3 class="font-bold text-sm text-foreground">External Sharing & Guest Links</h3>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center bg-muted/40 p-3 rounded-xl border border-border/40">
                            <div>
                                <span class="font-semibold text-xs text-foreground block">Toggle Public Guest Link</span>
                                <span class="text-[10px] text-muted-foreground block">Generate a secure token for unauthenticated users.</span>
                            </div>
                            <button 
                                @click="togglePublicShare"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                :class="list.share_token ? 'bg-[#1AC18C]' : 'bg-muted'"
                            >
                                <span 
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                    :class="list.share_token ? 'translate-x-5' : 'translate-x-0'"
                                />
                            </button>
                        </div>

                        <!-- Guest URL Copy field -->
                        <div v-if="list.share_token" class="space-y-1.5 p-3.5 bg-muted/20 border border-[#1AC18C]/20 rounded-xl space-y-2">
                            <span class="text-[10px] uppercase font-bold text-[#1AC18C] tracking-wide block">Active Share Link</span>
                            <div class="flex gap-2">
                                <input 
                                    type="text" 
                                    readonly 
                                    :value="`${$page.props.auth ? window?.location?.origin : ''}/shopping-lists/shared/${list.share_token}`" 
                                    class="flex-1 bg-background text-[10px] px-2.5 py-1.5 rounded-lg border border-input focus:outline-none select-all truncate text-muted-foreground"
                                />
                                <Button 
                                    @click="copyShareLink" 
                                    variant="outline" 
                                    size="sm" 
                                    class="h-8 text-xs cursor-pointer bg-card rounded-lg"
                                >
                                    <Check v-if="copied" class="size-3.5 text-green-500 mr-1" />
                                    <Copy v-else class="size-3.5 mr-1" />
                                    {{ copied ? 'Copied' : 'Copy' }}
                                </Button>
                            </div>
                        </div>

                        <!-- Email Invites for shared list -->
                        <div v-if="list.share_token" class="space-y-3 pt-3 border-t border-border/40">
                            <span class="font-semibold text-xs text-foreground block">Share via Email notification</span>
                            
                            <form @submit.prevent="submitShareEmail" class="flex gap-2">
                                <div class="flex-1">
                                    <Input 
                                        type="email" 
                                        v-model="shareEmail" 
                                        placeholder="external@church.org" 
                                        required 
                                        class="rounded-xl h-9 text-xs"
                                    />
                                    <InputError :message="shareEmailForm.errors.email" />
                                </div>
                                <Button 
                                    type="submit" 
                                    size="sm"
                                    class="bg-primary text-xs font-bold rounded-xl h-9 px-3 cursor-pointer"
                                    :disabled="shareEmailForm.processing"
                                >
                                    <Mail class="mr-1.5 size-3.5" /> Share
                                </Button>
                            </form>

                            <div v-if="list.shared_emails.length > 0" class="space-y-2">
                                <span class="text-[10px] text-muted-foreground uppercase font-bold tracking-wider block">Shared Externals</span>
                                <div class="flex flex-col gap-1.5">
                                    <div 
                                        v-for="email in list.shared_emails" 
                                        :key="email" 
                                        class="flex justify-between items-center p-2 bg-muted/30 border border-border/40 rounded-lg text-xs"
                                    >
                                        <span class="truncate max-w-[180px]" :title="email">{{ email }}</span>
                                        <button @click="removeEmail(email)" class="text-muted-foreground hover:text-red-500 cursor-pointer">
                                            <X class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Add/Edit List Item -->
        <div v-if="showItemModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <ShoppingBag class="size-5 text-primary" />
                        {{ editingItem ? 'Edit Spreadsheet Item' : 'Add Item to Spreadsheet' }}
                    </h3>
                    <button @click="showItemModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitItem" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="item-name">Item Name</Label>
                        <Input 
                            id="item-name"
                            v-model="itemForm.name"
                            placeholder="e.g. Behringer X32 Digital Console"
                            required
                            class="rounded-xl"
                        />
                        <InputError :message="itemForm.errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="item-price">Unit Price ($)</Label>
                            <Input 
                                id="item-price"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model.number="itemForm.unit_price"
                                required
                                class="rounded-xl"
                            />
                            <InputError :message="itemForm.errors.unit_price" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="item-qty">Quantity</Label>
                            <Input 
                                id="item-qty"
                                type="number"
                                min="1"
                                v-model.number="itemForm.quantity"
                                required
                                class="rounded-xl"
                            />
                            <InputError :message="itemForm.errors.quantity" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="item-link">Online Order Product URL (Optional)</Label>
                        <Input 
                            id="item-link"
                            type="url"
                            v-model="itemForm.link"
                            placeholder="https://sweetwater.com/store/detail/..."
                            class="rounded-xl"
                        />
                        <InputError :message="itemForm.errors.link" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="item-comments">Comments / Notes (Optional)</Label>
                        <textarea 
                            id="item-comments"
                            v-model="itemForm.comments"
                            placeholder="e.g. Needs cat5e cable extensions and mounting bracket brackets..."
                            rows="2"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        ></textarea>
                        <InputError :message="itemForm.errors.comments" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showItemModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="itemForm.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ itemForm.processing ? 'Saving...' : 'Save Item' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Edit List Details (Name/Desc) -->
        <div v-if="showEditListModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <ShoppingBag class="size-5 text-primary" />
                        Edit Shopping List Info
                    </h3>
                    <button @click="showEditListModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitListUpdate" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="list-edit-name">List Name</Label>
                        <Input 
                            id="list-edit-name"
                            v-model="listForm.name"
                            required
                            class="rounded-xl"
                        />
                        <InputError :message="listForm.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="list-edit-desc">Description</Label>
                        <textarea 
                            id="list-edit-desc"
                            v-model="listForm.description"
                            rows="3"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        ></textarea>
                        <InputError :message="listForm.errors.description" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showEditListModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="listForm.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            <Save class="size-4 mr-1.5" /> Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
