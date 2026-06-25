<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    Ruler, 
    Plus, 
    Trash2, 
    Calendar, 
    User, 
    ArrowRight, 
    FileText,
    Settings,
    Activity
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface CablePlan {
    id: number;
    name: string;
    description: string | null;
    floor_plan_path: string | null;
    floor_plan_url: string | null;
    scale_pixels: number;
    scale_distance: number;
    scale_unit: string;
    slack_percent: number;
    cables_count: number;
    created_by: string;
    created_at: string;
}

defineProps<{
    plans: CablePlan[];
}>();

const showCreateModal = ref(false);

const form = useForm({
    name: '',
    description: '',
});

const submitCreate = () => {
    form.post('/cable-plans', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const deletePlan = (id: number) => {
    if (confirm('Are you sure you want to delete this cable plan? All drawing routes and uploaded floor plans will be lost.')) {
        useForm({}).delete(`/cable-plans/${id}`);
    }
};

const formatDate = (isoString: string) => {
    const d = new Date(isoString);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Cable Calculator', href: '' }
        ]
    }
});
</script>

<template>
    <Head title="Cable Length Calculator" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6 overflow-x-auto rounded-xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-border/40 pb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                    <Ruler class="size-6" />
                </div>
                <div>
                    <h1 class="font-bold text-2xl text-foreground">Cable Length Calculator</h1>
                    <p class="text-xs text-muted-foreground">Upload church floor plans, calibrate scale, draw signal paths, and calculate 3D conduit lengths with heights.</p>
                </div>
            </div>

            <Button 
                @click="showCreateModal = true"
                class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs"
            >
                <Plus class="mr-1.5 size-4" /> Create Cable Plan
            </Button>
        </div>

        <!-- Plans Grid -->
        <div v-if="plans.length === 0" class="border border-border/40 rounded-2xl bg-card p-14 text-center flex flex-col items-center justify-center space-y-3">
            <Ruler class="size-12 text-muted-foreground/60 animate-pulse" />
            <h3 class="font-bold text-base text-foreground">No cable plans created yet</h3>
            <p class="text-xs text-muted-foreground max-w-sm">Create your first cable plan, upload a JPEG/PNG blueprint of your sanctuary or stage, and start routing audio, video, and network lines.</p>
            <Button 
                @click="showCreateModal = true"
                class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer text-xs"
            >
                Get Started
            </Button>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div 
                v-for="plan in plans" 
                :key="plan.id"
                class="bg-card border border-border/60 hover:border-[#1AC18C]/40 rounded-2xl p-5 flex flex-col justify-between transition-all duration-300 group overflow-hidden relative"
            >
                <!-- Decorative background accent -->
                <div class="absolute -right-4 -top-4 size-20 rounded-full bg-primary/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>

                <div class="space-y-2 relative">
                    <div class="flex items-center gap-1.5 text-primary">
                        <FileText class="size-4" />
                        <span class="text-[9px] font-bold uppercase tracking-wider">Installation Map</span>
                    </div>
                    <h3 class="font-bold text-base text-foreground leading-snug group-hover:text-primary transition-colors">{{ plan.name }}</h3>
                    <p class="text-xs text-muted-foreground line-clamp-2 h-8">{{ plan.description || 'No description provided.' }}</p>
                </div>

                <!-- Preview or details -->
                <div class="my-4 aspect-video border border-border/40 rounded-xl bg-muted/40 overflow-hidden flex items-center justify-center relative">
                    <img 
                        v-if="plan.floor_plan_url" 
                        :src="plan.floor_plan_url" 
                        class="w-full h-full object-cover" 
                        alt="Floor Plan Preview"
                    />
                    <div v-else class="text-center p-4 space-y-1">
                        <Activity class="size-6 text-muted-foreground/60 mx-auto" />
                        <span class="text-[10px] text-muted-foreground block">No floor plan image uploaded</span>
                    </div>
                </div>

                <div class="pt-4 border-t border-border/40 flex flex-col gap-1.5 text-[10px] text-muted-foreground relative">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <User class="size-3.5" />
                            <span>Creator: {{ plan.created_by }}</span>
                        </div>
                        <span class="font-semibold text-foreground text-xs">{{ plan.cables_count }} runs</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <Calendar class="size-3.5" />
                            <span>{{ formatDate(plan.created_at) }}</span>
                        </div>
                        <span class="font-bold text-foreground bg-primary/5 px-2 py-0.5 rounded-lg border border-primary/10">
                            Scale: 1px = {{ (plan.scale_distance / (plan.scale_pixels || 1)).toFixed(3) }}{{ plan.scale_unit }}
                        </span>
                    </div>
                </div>

                <div class="pt-4 flex gap-2 relative">
                    <Link 
                        :href="`/cable-plans/${plan.id}`"
                        class="flex-1 inline-flex items-center justify-center rounded-xl bg-primary text-primary-foreground text-xs font-semibold px-4 py-2 hover:bg-primary/95 transition-all duration-200 cursor-pointer shadow-sm"
                    >
                        Open Calculator
                        <ArrowRight class="ml-1.5 size-3.5" />
                    </Link>

                    <Button 
                        @click="deletePlan(plan.id)"
                        variant="ghost"
                        class="size-9 p-0 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-xl cursor-pointer"
                        title="Delete Cable Plan"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: Create Cable Plan -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <Ruler class="size-5 text-primary" />
                        New Cable Plan Map
                    </h3>
                    <button @click="showCreateModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="p-name">Plan Name</Label>
                        <Input 
                            id="p-name"
                            v-model="form.name"
                            placeholder="e.g. Main Sanctuary Cable Runs"
                            required
                            class="rounded-xl"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="p-desc">Description</Label>
                        <textarea 
                            id="p-desc"
                            v-model="form.description"
                            placeholder="Describe the rooms, upgrade phase, or signal routing details..."
                            rows="3"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showCreateModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ form.processing ? 'Creating...' : 'Create Plan' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
