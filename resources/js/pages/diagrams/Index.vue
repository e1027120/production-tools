<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    Network, 
    Plus, 
    Trash2, 
    Calendar, 
    User, 
    ArrowRight, 
    FileText,
    Activity,
    Palette
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface Diagram {
    id: number;
    name: string;
    type: string;
    description: string | null;
    created_by: string;
    created_at: string;
}

defineProps<{
    diagrams: Diagram[];
}>();

const showCreateModal = ref(false);

const form = useForm({
    name: '',
    type: 'blueprint',
    description: '',
});

const submitCreate = () => {
    form.post('/diagrams', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const deleteDiagram = (id: number) => {
    if (confirm('Are you sure you want to delete this technical diagram?')) {
        useForm({}).delete(`/diagrams/${id}`);
    }
};

const formatDate = (isoString: string) => {
    const d = new Date(isoString);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Technical Diagrams" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6 overflow-x-auto rounded-xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-border/40 pb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                    <Network class="size-6" />
                </div>
                <div>
                    <h1 class="font-bold text-2xl text-foreground">Technical Diagrams</h1>
                    <p class="text-xs text-muted-foreground">Map and blueprint your church audio, AV, network, and lighting layouts.</p>
                </div>
            </div>

            <Button 
                @click="showCreateModal = true"
                class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs"
            >
                <Plus class="mr-1.5 size-4" /> Create Diagram
            </Button>
        </div>

        <!-- Diagrams Grid -->
        <div v-if="diagrams.length === 0" class="border border-border/40 rounded-2xl bg-card p-14 text-center flex flex-col items-center justify-center space-y-3">
            <Activity class="size-12 text-muted-foreground/60 animate-pulse" />
            <h3 class="font-bold text-base text-foreground">No technical diagrams yet</h3>
            <p class="text-xs text-muted-foreground max-w-sm">Create your first blueprint to document signal flows, camera lines, and hardware cables.</p>
            <Button 
                @click="showCreateModal = true"
                class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer text-xs"
            >
                Get Started
            </Button>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div 
                v-for="d in diagrams" 
                :key="d.id"
                class="bg-card border border-border/60 hover:border-[#1AC18C]/40 rounded-2xl p-5 flex flex-col justify-between transition-all duration-300 group overflow-hidden relative"
            >
                <!-- Decorative background accent -->
                <div class="absolute -right-4 -top-4 size-20 rounded-full bg-primary/5 group-hover:scale-125 transition-transform duration-500 blur-xl"></div>

                <div class="space-y-2 relative">
                    <div class="flex items-center gap-1.5" :class="d.type === 'drawing' ? 'text-violet-500 dark:text-violet-400' : 'text-primary'">
                        <component :is="d.type === 'drawing' ? Palette : FileText" class="size-4 shrink-0" />
                        <span class="text-[9px] font-bold uppercase tracking-wider">{{ d.type === 'drawing' ? 'Free Drawing' : 'Technical Blueprint' }}</span>
                    </div>
                    <h3 class="font-bold text-base text-foreground leading-snug group-hover:text-primary transition-colors">{{ d.name }}</h3>
                    <p class="text-xs text-muted-foreground line-clamp-2 h-8">{{ d.description || 'No description provided.' }}</p>
                </div>

                <div class="pt-4 border-t border-border/40 mt-4 flex flex-col gap-1.5 text-[10px] text-muted-foreground relative">
                    <div class="flex items-center gap-1.5">
                        <User class="size-3.5" />
                        <span>Created by: {{ d.created_by }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Calendar class="size-3.5" />
                        <span>Date: {{ formatDate(d.created_at) }}</span>
                    </div>
                </div>

                <div class="pt-4 flex gap-2 relative">
                    <Link 
                        :href="`/diagrams/${d.id}`"
                        class="flex-1 inline-flex items-center justify-center rounded-xl bg-primary text-primary-foreground text-xs font-semibold px-4 py-2 hover:bg-primary/95 transition-all duration-200 cursor-pointer shadow-sm"
                    >
                        Open Editor
                        <ArrowRight class="ml-1.5 size-3.5" />
                    </Link>

                    <Button 
                        @click="deleteDiagram(d.id)"
                        variant="ghost"
                        class="size-9 p-0 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-xl cursor-pointer"
                        title="Delete Diagram"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- MODAL: Create Diagram -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <component :is="form.type === 'drawing' ? Palette : Network" class="size-5 text-primary" />
                        {{ form.type === 'drawing' ? 'New Free Drawing' : 'New Technical Blueprint' }}
                    </h3>
                    <button @click="showCreateModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="d-name">Name</Label>
                        <Input 
                            id="d-name"
                            v-model="form.name"
                            :placeholder="form.type === 'drawing' ? 'e.g. Sanctuary Stage Layout' : 'e.g. FOH to Stage Signal Flow'"
                            required
                            class="rounded-xl"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Document Type</Label>
                        <div class="grid grid-cols-2 gap-3">
                            <label 
                                class="flex items-center gap-2.5 p-3 rounded-xl border border-border bg-card cursor-pointer hover:border-primary/50 transition-colors"
                                :class="{ 'border-primary bg-primary/5': form.type === 'blueprint' }"
                            >
                                <input type="radio" v-model="form.type" value="blueprint" class="sr-only" />
                                <div class="size-4 rounded-full border border-input flex items-center justify-center shrink-0">
                                    <div class="size-2 rounded-full bg-primary" v-if="form.type === 'blueprint'"></div>
                                </div>
                                <div class="min-w-0">
                                    <span class="font-bold text-xs block text-foreground">Blueprint</span>
                                    <span class="text-[9px] text-muted-foreground block truncate">Network & signals</span>
                                </div>
                            </label>
                            <label 
                                class="flex items-center gap-2.5 p-3 rounded-xl border border-border bg-card cursor-pointer hover:border-primary/50 transition-colors"
                                :class="{ 'border-primary bg-primary/5': form.type === 'drawing' }"
                            >
                                <input type="radio" v-model="form.type" value="drawing" class="sr-only" />
                                <div class="size-4 rounded-full border border-input flex items-center justify-center shrink-0">
                                    <div class="size-2 rounded-full bg-primary" v-if="form.type === 'drawing'"></div>
                                </div>
                                <div class="min-w-0">
                                    <span class="font-bold text-xs block text-foreground">Free Drawing</span>
                                    <span class="text-[9px] text-muted-foreground block truncate">Shapes & vectors</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="d-desc">Description</Label>
                        <textarea 
                            id="d-desc"
                            v-model="form.description"
                            :placeholder="form.type === 'drawing' ? 'Briefly describe what this drawing layout documents...' : 'Briefly describe what setup this diagram blueprints...'"
                            rows="3"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showCreateModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ form.processing ? 'Creating...' : (form.type === 'drawing' ? 'Create Drawing' : 'Create Diagram') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
