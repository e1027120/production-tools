<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Server, Plus, Trash2, Eye, Calendar, Sparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Define the shape of a Rack
interface Device {
    id: string;
    brand: string;
    name: string;
    type: string;
    u_height: number;
    position: number;
    power_consumption: number;
    heat_dissipation: number;
    weight: number;
}

interface Rack {
    id: number;
    name: string;
    size: number;
    description: string | null;
    devices: Device[] | null;
    created_at: string;
}

const props = defineProps<{
    racks: Rack[];
}>();

// Form and Modal states
const showCreateModal = ref(false);

const form = useForm({
    name: '',
    size: 24,
    description: '',
});

const submitCreate = () => {
    form.post('/racks', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const deleteRack = (id: number) => {
    if (confirm('Are you sure you want to delete this rack? All placed devices will be removed.')) {
        useForm({}).delete(`/racks/${id}`);
    }
};

// Computations
const getUUsed = (rack: Rack): number => {
    return (rack.devices || []).reduce((sum, dev) => sum + dev.u_height, 0);
};

const getPercentUsed = (rack: Rack): number => {
    const pct = (getUUsed(rack) / rack.size) * 100;
    return Math.min(100, Math.round(pct));
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Racks Directory',
                href: '/racks',
            },
        ],
    },
});
</script>

<template>
    <Head title="Racks Directory" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <!-- Top bar with description and Add Rack button -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Production 19" Racks</h1>
                <p class="text-sm text-muted-foreground">Manage and design your production equipment layout, power, and thermal distribution.</p>
            </div>
            
            <Button @click="showCreateModal = true" class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                <Plus class="mr-2 size-4" />
                Create New Rack
            </Button>
        </div>

        <!-- Racks Grid -->
        <div v-if="racks.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div 
                v-for="rack in racks" 
                :key="rack.id"
                class="bg-card border border-border/60 hover:border-primary/30 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between"
            >
                <div class="flex flex-col gap-3">
                    <!-- Icon and Name -->
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-foreground text-background flex items-center justify-center">
                            <Server class="size-5 text-primary" />
                        </div>
                        <div>
                            <h3 class="font-bold text-foreground leading-tight">{{ rack.name }}</h3>
                            <span class="text-xs text-muted-foreground">ID: #{{ rack.id }}</span>
                        </div>
                    </div>

                    <!-- Description -->
                    <p class="text-sm text-muted-foreground line-clamp-2 h-10 mt-1">
                        {{ rack.description || 'No description provided for this production rack.' }}
                    </p>

                    <!-- Metrics/Progress bar -->
                    <div class="mt-2 space-y-1.5">
                        <div class="flex justify-between text-xs font-semibold">
                            <span class="text-muted-foreground">Space Occupied:</span>
                            <span class="text-foreground">{{ getUUsed(rack) }} / {{ rack.size }} U ({{ getPercentUsed(rack) }}%)</span>
                        </div>
                        <div class="w-full bg-muted rounded-full h-2 overflow-hidden">
                            <div 
                                class="bg-primary h-full rounded-full transition-all duration-300"
                                :style="{ width: `${getPercentUsed(rack)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-between border-t border-border/40 mt-6 pt-4 text-xs text-muted-foreground">
                    <div class="flex items-center gap-1.5">
                        <Calendar class="size-3.5" />
                        <span>{{ formatDate(rack.created_at) }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link :href="`/racks/${rack.id}`">
                            <Button size="sm" variant="outline" class="h-8 rounded-lg cursor-pointer">
                                <Eye class="mr-1.5 size-3.5" />
                                Build
                            </Button>
                        </Link>
                        <Button 
                            @click="deleteRack(rack.id)" 
                            size="sm" 
                            variant="ghost" 
                            class="h-8 rounded-lg text-destructive hover:bg-destructive/10 hover:text-destructive cursor-pointer"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="flex flex-col items-center justify-center py-20 text-center bg-card border border-dashed border-border/80 rounded-2xl p-8 gap-4">
            <div class="size-16 rounded-full bg-muted flex items-center justify-center text-muted-foreground">
                <Server class="size-8" />
            </div>
            <div class="space-y-1.5">
                <h3 class="font-bold text-lg text-foreground">No Racks Created Yet</h3>
                <p class="text-sm text-muted-foreground max-w-sm">Create a rack to start building layout diagrams, load analytics, and heat reports.</p>
            </div>
            <Button @click="showCreateModal = true" class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                <Plus class="mr-2 size-4" />
                Add Your First Rack
            </Button>
        </div>

        <!-- Modal Dialog for Creating Racks -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground">Create New Production Rack</h3>
                    <button @click="showCreateModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="name">Rack Name</Label>
                        <Input 
                            id="name" 
                            v-model="form.name" 
                            type="text" 
                            placeholder="e.g. Broadcast Rack A, Main FOH Stage" 
                            required 
                            class="rounded-xl"
                        />
                        <div v-if="form.errors.name" class="text-xs text-destructive mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="size">Rack Size (U Height)</Label>
                        <select 
                            id="size" 
                            v-model="form.size" 
                            class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="8">8U Rack</option>
                            <option :value="12">12U Rack</option>
                            <option :value="16">16U Rack</option>
                            <option :value="24">24U Rack</option>
                            <option :value="32">32U Rack</option>
                            <option :value="42">42U Rack</option>
                            <option :value="48">48U Rack</option>
                        </select>
                        <div v-if="form.errors.size" class="text-xs text-destructive mt-1">{{ form.errors.size }}</div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="description">Description (Optional)</Label>
                        <textarea 
                            id="description" 
                            v-model="form.description" 
                            rows="3" 
                            placeholder="Short summary of what this rack is used for..."
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                        <div v-if="form.errors.description" class="text-xs text-destructive mt-1">{{ form.errors.description }}</div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-border/40">
                        <Button type="button" variant="outline" @click="showCreateModal = false" class="rounded-xl cursor-pointer">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing" class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ form.processing ? 'Creating...' : 'Create Rack' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
