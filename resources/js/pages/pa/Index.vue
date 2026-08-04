<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { 
    Volume2, 
    Plus, 
    Trash2, 
    Calendar, 
    User, 
    ArrowRight, 
    FileText,
    Settings,
    Activity,
    Sliders
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

const page = usePage();
const userRole = computed(() => (page.props.auth as any).currentChurch?.pivot?.role || 'User');
const isReadOnly = computed(() => userRole.value === 'User');

interface PaDesign {
    id: number;
    name: string;
    description: string | null;
    speakers_count: number;
    zones_count: number;
    created_by: string;
    created_at: string;
}

defineProps<{
    designs: PaDesign[];
    stats: {
        total_designs: number;
        total_speakers: number;
    };
}>();

const showCreateModal = ref(false);

const form = useForm({
    name: '',
    description: '',
});

const submitCreate = () => {
    form.post('/pa-systems', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const deleteDesign = (id: number) => {
    if (confirm('Are you sure you want to delete this PA system design? All configured speakers, zones, and custom amplifier sizing settings will be lost.')) {
        useForm({}).delete(`/pa-systems/${id}`);
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
            { title: 'PA Systems', href: '' }
        ]
    }
});
</script>

<template>
    <Head title="PA System Configurator" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6 overflow-x-auto rounded-xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-border/40 pb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                    <Volume2 class="size-6 text-[#1AC18C]" />
                </div>
                <div>
                    <h1 class="font-bold text-2xl text-foreground">PA System Configurator</h1>
                    <p class="text-xs text-muted-foreground">Configure speaker zones, model wiring configurations, and calculate matching amplifier strategies dynamically.</p>
                </div>
            </div>

            <Button 
                v-if="!isReadOnly"
                @click="showCreateModal = true"
                class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs"
            >
                <Plus class="size-4.5 mr-1" /> New PA Design
            </Button>
        </div>

        <!-- Quick Stats Banner -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="p-4 bg-muted/30 border border-border/50 rounded-xl flex items-center gap-3">
                <div class="size-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                    <Volume2 class="size-5 text-[#1AC18C]" />
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-muted-foreground block">Active Designs</span>
                    <span class="font-bold text-lg text-foreground">{{ stats.total_designs }}</span>
                </div>
            </div>
            <div class="p-4 bg-muted/30 border border-border/50 rounded-xl flex items-center gap-3">
                <div class="size-9 rounded-lg bg-[#1AC18C]/10 flex items-center justify-center text-[#1AC18C]">
                    <Sliders class="size-5 text-[#1AC18C]" />
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-muted-foreground block">Speakers Configured</span>
                    <span class="font-bold text-lg text-foreground">{{ stats.total_speakers }}</span>
                </div>
            </div>
        </div>

        <!-- Grid -->
        <div class="flex-1">
            <div v-if="designs.length === 0" class="flex flex-col justify-center items-center text-center p-12 border border-dashed border-border/80 rounded-2xl bg-card min-h-[300px] space-y-4">
                <div class="size-16 rounded-lg bg-muted flex items-center justify-center text-muted-foreground">
                    <Volume2 class="size-8 text-[#1AC18C] animate-pulse" />
                </div>
                <h3 class="font-bold text-lg text-foreground">Create Your First PA Design</h3>
                <p class="text-sm text-muted-foreground max-w-md">Configure arrays, fills, sub bass clusters, and monitors to size matching amplifiers with optimal headroom strategies.</p>
                <Button 
                    v-if="!isReadOnly"
                    @click="showCreateModal = true"
                    class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs"
                >
                    <Plus class="size-4 mr-1" /> Get Started
                </Button>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div 
                    v-for="design in designs" 
                    :key="design.id"
                    class="relative rounded-xl border border-border/60 bg-card p-5 flex flex-col justify-between group hover:border-[#1AC18C]/40 transition-all duration-300 min-h-[200px]"
                >
                    <div class="space-y-3 flex-1">
                        <div class="flex justify-between items-start">
                            <h3 class="font-bold text-base text-foreground leading-snug group-hover:text-[#1AC18C] transition-colors truncate max-w-[80%]">
                                {{ design.name }}
                            </h3>
                            <button 
                                v-if="!isReadOnly"
                                @click="deleteDesign(design.id)"
                                class="text-muted-foreground hover:text-red-500 hover:bg-red-500/10 p-1.5 rounded-lg transition-colors cursor-pointer shrink-0"
                                title="Delete PA design"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground line-clamp-3 min-h-[48px]">
                            {{ design.description || 'No description provided.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-border/40 flex items-center justify-between text-[11px] text-muted-foreground mt-4">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center gap-1">
                                <Sliders class="size-3 text-muted-foreground" />
                                {{ design.zones_count }} zones
                            </span>
                            <span class="flex items-center gap-1">
                                <Volume2 class="size-3 text-[#1AC18C]" />
                                {{ design.speakers_count }} speakers
                            </span>
                        </div>
                    </div>

                    <div class="pt-4 flex items-center justify-between mt-2">
                        <div class="flex items-center gap-1 text-[10px] text-muted-foreground font-medium">
                            <User class="size-3" />
                            <span>{{ design.created_by }}</span>
                            <span>•</span>
                            <Calendar class="size-3" />
                            <span>{{ formatDate(design.created_at) }}</span>
                        </div>

                        <Link 
                            :href="`/pa-systems/${design.id}`"
                            class="inline-flex items-center justify-center rounded-lg bg-primary text-primary-foreground text-xs font-semibold px-3 py-1.5 hover:bg-[#1AC18C] hover:text-white transition-all cursor-pointer"
                        >
                            Open Workbench
                            <ArrowRight class="ml-1 size-3" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Create PA Design -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <Volume2 class="size-5 text-[#1AC18C]" />
                        New PA Design Setup
                    </h3>
                    <button @click="showCreateModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="design-name">System Name</Label>
                        <Input 
                            id="design-name"
                            v-model="form.name"
                            placeholder="e.g. Main Auditorium FOH"
                            required
                            class="rounded-xl"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="design-desc">Description (Optional)</Label>
                        <textarea 
                            id="design-desc"
                            v-model="form.description"
                            placeholder="e.g. Center cluster with side-fills and ground subs."
                            rows="3"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showCreateModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-semibold rounded-xl cursor-pointer">
                            Create Design
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>