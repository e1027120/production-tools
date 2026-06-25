<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { 
    BookOpen, 
    CheckCircle2, 
    Circle, 
    RotateCcw, 
    Music, 
    Image as ImageIcon, 
    Video, 
    ArrowLeft 
} from '@lucide/vue';
import { Button } from '@/components/ui/button';

interface Step {
    id: number;
    title: string;
    content: string | null;
    image_path: string | null;
    audio_path: string | null;
    video_url: string | null;
    sort_order: number;
}

const props = defineProps<{
    training: {
        id: number;
        type: string;
        title: string;
        description: string | null;
        ministry: string | null;
        church_name: string;
        steps: Step[];
    };
}>();

const checkedSteps = ref<Record<number, boolean>>({});

// Load check state from localStorage on mount
onMounted(() => {
    try {
        const saved = localStorage.getItem(`church_manual_checklist_${props.training.id}`);
        if (saved) {
            checkedSteps.value = JSON.parse(saved);
        }
    } catch (e) {
        console.error('Failed to load checklist state:', e);
    }
});

// Toggle a step checkmark
const toggleStep = (stepId: number) => {
    checkedSteps.value[stepId] = !checkedSteps.value[stepId];
    saveState();
};

// Save state to localStorage
const saveState = () => {
    try {
        localStorage.setItem(
            `church_manual_checklist_${props.training.id}`, 
            JSON.stringify(checkedSteps.value)
        );
    } catch (e) {
        console.error('Failed to save checklist state:', e);
    }
};

// Reset all checkmarks
const resetChecklist = () => {
    if (confirm('Are you sure you want to reset your checklist progress?')) {
        checkedSteps.value = {};
        saveState();
    }
};

// Calculate completion details
const totalSteps = computed(() => props.training.steps.length);
const completedCount = computed(() => {
    return props.training.steps.filter(s => checkedSteps.value[s.id]).length;
});
const progressPercent = computed(() => {
    if (totalSteps.value === 0) return 0;
    return Math.round((completedCount.value / totalSteps.value) * 100);
});

// Helper to embed YouTube videos
const getYoutubeEmbedUrl = (url: string | null) => {
    if (!url) return '';
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    const videoId = (match && match[2].length === 11) ? match[2] : null;
    return videoId ? `https://www.youtube.com/embed/${videoId}` : '';
};
</script>

<template>
    <Head :title="`${training.title} - Operator Checklist`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 font-sans pb-16 selection:bg-[#1AC18C]/30 selection:text-white">
        <!-- Floating sticky progress bar header -->
        <div class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800/80 px-4 py-3 shadow-md">
            <div class="max-w-3xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <div class="size-8 rounded-lg bg-[#1AC18C]/20 flex items-center justify-center text-[#1AC18C]">
                        <BookOpen class="size-4.5" />
                    </div>
                    <div class="flex-1 sm:flex-initial">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block leading-none">
                            {{ training.church_name }}
                        </span>
                        <span class="text-xs font-semibold text-slate-200 line-clamp-1">
                            {{ training.title }}
                        </span>
                    </div>
                </div>

                <!-- Progress status and reset button -->
                <div class="flex items-center justify-between sm:justify-end gap-4 w-full sm:w-auto border-t sm:border-t-0 border-slate-800 pt-2 sm:pt-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono font-bold text-slate-300">
                            {{ completedCount }}/{{ totalSteps }} Done
                        </span>
                        <div class="w-24 sm:w-32 bg-slate-800 h-2 rounded-full overflow-hidden">
                            <div 
                                class="bg-[#1AC18C] h-full rounded-full transition-all duration-500 shadow-[0_0_8px_#1AC18C]"
                                :style="{ width: `${progressPercent}%` }"
                            ></div>
                        </div>
                        <span class="text-xs font-mono text-[#1AC18C] font-bold w-10 text-right">
                            {{ progressPercent }}%
                        </span>
                    </div>

                    <button 
                        @click="resetChecklist"
                        class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-slate-200 transition-colors cursor-pointer"
                        title="Reset progress"
                    >
                        <RotateCcw class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Body -->
        <main class="max-w-3xl mx-auto px-4 mt-8 space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-br from-slate-800 to-slate-800/40 border border-slate-800 rounded-3xl p-6 relative overflow-hidden shadow-xl">
                <div class="absolute -right-10 -top-10 size-32 rounded-full bg-[#1AC18C]/5 blur-2xl"></div>

                <div class="relative space-y-3">
                    <div class="flex items-center gap-2">
                        <span 
                            v-if="training.ministry" 
                            class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-[#1AC18C]/15 text-[#1AC18C] border border-[#1AC18C]/20"
                        >
                            {{ training.ministry }} Category
                        </span>
                        <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-500/15 text-blue-400 border border-blue-500/20">
                            System Guide
                        </span>
                    </div>

                    <h1 class="font-black text-2xl sm:text-3xl text-white tracking-tight leading-tight">
                        {{ training.title }}
                    </h1>
                    <p class="text-xs text-slate-300 leading-relaxed max-w-2xl" v-if="training.description">
                        {{ training.description }}
                    </p>
                </div>
            </div>

            <!-- Steps Checklist Container -->
            <div class="space-y-4 pt-2">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest px-1">
                    Checklist Procedures
                </h2>

                <div 
                    v-for="(step, idx) in training.steps" 
                    :key="step.id"
                    class="bg-slate-800/40 border rounded-2xl p-5 flex gap-4 transition-all duration-300"
                    :class="[
                        checkedSteps[step.id] 
                            ? 'border-[#1AC18C]/50 bg-slate-800/10 shadow-[0_0_12px_-3px_rgba(26,193,140,0.15)]' 
                            : 'border-slate-800/80 hover:border-slate-700'
                    ]"
                >
                    <!-- Left Column: Checkbox -->
                    <div class="shrink-0 pt-0.5">
                        <button 
                            @click="toggleStep(step.id)"
                            class="size-7 rounded-full flex items-center justify-center cursor-pointer transition-all focus:outline-none"
                            :class="[
                                checkedSteps[step.id]
                                    ? 'text-[#1AC18C] hover:scale-105'
                                    : 'text-slate-600 hover:text-slate-400 hover:scale-105'
                            ]"
                        >
                            <CheckCircle2 v-if="checkedSteps[step.id]" class="size-7 fill-emerald-500/10" />
                            <Circle v-else class="size-7" />
                        </button>
                    </div>

                    <!-- Right Column: Step content -->
                    <div class="flex-1 space-y-3 min-w-0">
                        <div class="flex items-center justify-between gap-4">
                            <h3 
                                @click="toggleStep(step.id)"
                                class="font-bold text-sm sm:text-base text-slate-100 cursor-pointer select-none leading-snug transition-all"
                                :class="{ 'line-through text-slate-500': checkedSteps[step.id] }"
                            >
                                <span class="text-[#1AC18C] font-mono mr-1.5">{{ idx + 1 }}.</span>
                                {{ step.title }}
                            </h3>
                        </div>

                        <!-- Rich Text Content Instruction -->
                        <div 
                            v-if="step.content" 
                            class="text-xs text-slate-300 prose prose-invert max-w-none leading-relaxed prose-xs"
                            v-html="step.content"
                            :class="{ 'opacity-60': checkedSteps[step.id] }"
                        ></div>

                        <!-- Media Display if any -->
                        <div 
                            class="space-y-3 pt-2 animate-in fade-in duration-200" 
                            v-if="step.image_path || step.audio_path || step.video_url"
                            :class="{ 'opacity-40 pointer-events-none': checkedSteps[step.id] }"
                        >
                            <!-- Image attachment -->
                            <div v-if="step.image_path" class="relative rounded-xl overflow-hidden border border-slate-700/60 max-w-lg shadow-sm">
                                <img 
                                    :src="`/storage/${step.image_path}`" 
                                    class="w-full h-auto object-cover max-h-[300px]" 
                                    alt="Step Guide Image"
                                />
                            </div>

                            <!-- Audio attachment -->
                            <div v-if="step.audio_path" class="flex items-center gap-2 p-2 bg-slate-800/80 rounded-xl max-w-md border border-slate-700/50">
                                <Music class="size-4.5 text-[#1AC18C] shrink-0 ml-1" />
                                <audio controls :src="`/storage/${step.audio_path}`" class="w-full h-8 text-xs bg-transparent"></audio>
                            </div>

                            <!-- Video attachment -->
                            <div v-if="step.video_url" class="aspect-video max-w-lg w-full rounded-xl overflow-hidden border border-slate-700/60 shadow-sm">
                                <iframe 
                                    :src="getYoutubeEmbedUrl(step.video_url)"
                                    class="w-full h-full"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer banner reset -->
            <div class="flex justify-center pt-6 border-t border-slate-800/50">
                <Button 
                    @click="resetChecklist"
                    variant="outline"
                    class="rounded-xl border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 hover:border-slate-700 gap-1.5 text-xs font-semibold cursor-pointer"
                >
                    <RotateCcw class="size-3.5" />
                    Reset Checklist Progress
                </Button>
            </div>
        </main>
    </div>
</template>
