<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    GraduationCap, 
    ArrowLeft, 
    ArrowRight, 
    CheckCircle, 
    HelpCircle, 
    Music, 
    Play,
    BookOpen,
    Eye
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

interface Step {
    id: number;
    title: string;
    content: string | null;
    image_path: string | null;
    audio_path: string | null;
    video_url: string | null;
}

interface Option {
    id: number;
    question_id: number;
    option_text: string | null;
    image_path: string | null;
}

interface Question {
    id: number;
    question_text: string;
    image_path: string | null;
    type: 'single' | 'multiple';
    options: Option[];
}

const props = defineProps<{
    training: {
        id: number;
        title: string;
        description: string | null;
        passing_score: number;
        has_test: boolean;
    };
    steps: Step[];
    questions: Question[];
}>();

const currentStepIdx = ref(0);
const isTakingTest = ref(false);

const activeStep = computed(() => props.steps[currentStepIdx.value] || null);
const isLastStep = computed(() => currentStepIdx.value === props.steps.length - 1);

// Answer Form State
// Single choice answers are represented as individual option IDs (number)
// Multiple choice answers are represented as arrays of option IDs (array of numbers)
const form = useForm({
    answers: {} as Record<number, number | number[]>,
});

const selectChoice = (questionId: number, optionId: number, isMultiple: boolean) => {
    if (isMultiple) {
        if (!Array.isArray(form.answers[questionId])) {
            form.answers[questionId] = [];
        }
        const currentAnswers = form.answers[questionId] as number[];
        const idx = currentAnswers.indexOf(optionId);
        if (idx > -1) {
            currentAnswers.splice(idx, 1);
        } else {
            currentAnswers.push(optionId);
        }
    } else {
        form.answers[questionId] = optionId;
    }
};

const isOptionSelected = (questionId: number, optionId: number) => {
    const val = form.answers[questionId];
    if (Array.isArray(val)) {
        return val.includes(optionId);
    }
    return val === optionId;
};

const getYoutubeEmbedUrl = (url: string | null) => {
    if (!url) return '';
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    const videoId = (match && match[2].length === 11) ? match[2] : null;
    return videoId ? `https://www.youtube.com/embed/${videoId}` : '';
};

const nextStep = () => {
    if (currentStepIdx.value < props.steps.length - 1) {
        currentStepIdx.value++;
    }
};

const prevStep = () => {
    if (currentStepIdx.value > 0) {
        currentStepIdx.value--;
    }
};

const startTest = () => {
    // Initialize form answers structures
    props.questions.forEach(q => {
        if (q.type === 'multiple') {
            form.answers[q.id] = [];
        } else {
            form.answers[q.id] = null as any;
        }
    });
    isTakingTest.value = true;
};

const submitTest = () => {
    // Validate that all questions have answers
    let allAnswered = true;
    props.questions.forEach(q => {
        const val = form.answers[q.id];
        if (q.type === 'multiple') {
            if (!Array.isArray(val) || val.length === 0) {
                allAnswered = false;
            }
        } else {
            if (val === null || val === undefined) {
                allAnswered = false;
            }
        }
    });

    if (props.questions.length > 0 && !allAnswered) {
        alert('Please answer all questions before submitting.');
        return;
    }

    form.post(`/trainings/${props.training.id}/submit`);
};
</script>

<template>
    <Head :title="training.title" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6 overflow-x-auto rounded-xl">
        <!-- Back button link -->
        <div class="flex items-center gap-2">
            <Link 
                href="/trainings"
                class="inline-flex size-8 items-center justify-center rounded-xl bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
            >
                <ArrowLeft class="size-4" />
            </Link>
            <span class="text-xs font-semibold text-muted-foreground">Exit Player</span>
        </div>

        <!-- Header -->
        <div class="flex items-center gap-3 border-b border-border/40 pb-4">
            <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                <GraduationCap class="size-6" />
            </div>
            <div class="flex-1">
                <h1 class="font-bold text-xl text-foreground leading-tight">{{ training.title }}</h1>
                <p class="text-xs text-muted-foreground line-clamp-1" v-if="training.description">{{ training.description }}</p>
            </div>
        </div>

        <div class="max-w-4xl mx-auto w-full">
            <!-- 1. Learning Slides (v-if="!isTakingTest") -->
            <div v-if="!isTakingTest && activeStep" class="bg-card border border-border/60 rounded-2xl p-6 md:p-8 space-y-6 shadow-sm">
                <!-- Progress Header -->
                <div class="flex justify-between items-center border-b border-border/40 pb-3">
                    <span class="text-xs font-bold text-primary uppercase tracking-wide">
                        Step {{ currentStepIdx + 1 }} of {{ steps.length }}
                    </span>
                    <span class="text-xs text-muted-foreground font-semibold">
                        {{ activeStep.title }}
                    </span>
                </div>

                <!-- Media Display Area -->
                <div class="space-y-4">
                    <!-- YouTube video iframe player -->
                    <div v-if="activeStep.video_url" class="aspect-video w-full rounded-2xl overflow-hidden border border-border shadow-sm">
                        <iframe 
                            :src="getYoutubeEmbedUrl(activeStep.video_url)"
                            class="w-full h-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>

                    <!-- Step image -->
                    <div v-if="activeStep.image_path" class="rounded-2xl overflow-hidden border border-border/60 max-h-[380px] flex items-center justify-center bg-muted/20">
                        <img 
                            :src="`/storage/${activeStep.image_path}`" 
                            :alt="activeStep.title" 
                            class="max-h-[380px] object-contain w-full"
                        />
                    </div>

                    <!-- Step audio player -->
                    <div v-if="activeStep.audio_path" class="p-4 rounded-xl bg-muted/40 border border-border/40 flex flex-col gap-2">
                        <div class="flex items-center gap-2 text-xs font-semibold text-foreground">
                            <Music class="size-4 text-primary animate-pulse" /> Listen to step audio explanation:
                        </div>
                        <audio controls class="w-full focus:outline-none">
                            <source :src="`/storage/${activeStep.audio_path}`" />
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                </div>

                <!-- Rich text block description -->
                <div 
                    v-if="activeStep.content" 
                    v-html="activeStep.content" 
                    class="prose dark:prose-invert max-w-none text-foreground text-sm leading-relaxed font-sans prose-p:my-2 prose-ul:my-2 prose-ol:my-2 prose-li:my-1"
                ></div>

                <!-- Navigation panel -->
                <div class="pt-6 border-t border-border/40 mt-6 flex justify-between gap-4">
                    <Button 
                        type="button" 
                        @click="prevStep" 
                        :disabled="currentStepIdx === 0"
                        variant="outline"
                        class="rounded-xl cursor-pointer text-xs"
                    >
                        Previous Step
                    </Button>

                    <Button 
                        v-if="!isLastStep"
                        type="button" 
                        @click="nextStep" 
                        class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer text-xs"
                    >
                        Next Step <ArrowRight class="ml-1 size-3.5" />
                    </Button>
                    
                    <Button 
                        v-else-if="training.has_test"
                        type="button" 
                        @click="startTest" 
                        class="bg-[#1AC18C] hover:bg-[#1AC18C]/90 text-white font-bold rounded-xl cursor-pointer text-xs"
                    >
                        Proceed to Test <HelpCircle class="ml-1 size-3.5" />
                    </Button>

                    <Button 
                        v-else
                        type="button" 
                        @click="submitTest" 
                        :disabled="form.processing"
                        class="bg-[#1AC18C] hover:bg-[#1AC18C]/90 text-white font-bold rounded-xl cursor-pointer text-xs"
                    >
                        {{ form.processing ? 'Completing...' : 'Complete Training' }} <CheckCircle class="ml-1 size-3.5" />
                    </Button>
                </div>
            </div>

            <!-- 2. Test Taker Sheet (v-if="isTakingTest") -->
            <div v-if="isTakingTest" class="bg-card border border-border/60 rounded-2xl p-6 md:p-8 space-y-6 shadow-sm animate-in fade-in slide-in-from-bottom-3 duration-300">
                <div class="flex justify-between items-center border-b border-border/40 pb-3">
                    <span class="text-xs font-bold text-[#1AC18C] uppercase tracking-wide flex items-center gap-1.5">
                        <HelpCircle class="size-4 animate-bounce" /> Evaluation Test (Requires {{ training.passing_score }}% to pass)
                    </span>
                    <button 
                        @click="isTakingTest = false" 
                        class="text-xs text-muted-foreground hover:text-foreground font-semibold cursor-pointer"
                    >
                        Review steps
                    </button>
                </div>

                <!-- Questions Sheet -->
                <div class="space-y-8">
                    <div 
                        v-for="(q, qIdx) in questions" 
                        :key="q.id"
                        class="space-y-4 border-b border-border/40 pb-6 last:border-b-0 last:pb-0"
                    >
                        <div class="space-y-2">
                            <span class="text-[10px] font-bold text-muted-foreground uppercase">Question {{ qIdx + 1 }}</span>
                            <h3 class="font-bold text-base text-foreground">{{ q.question_text }}</h3>
                            <span class="text-[10px] text-muted-foreground italic leading-none block">
                                ({{ q.type === 'multiple' ? 'Select all correct answers' : 'Select one correct answer' }})
                            </span>
                        </div>

                        <!-- Question Image -->
                        <div v-if="q.image_path" class="rounded-xl overflow-hidden max-h-48 border border-border/60 max-w-sm">
                            <img :src="`/storage/${q.image_path}`" class="object-cover w-full h-full" />
                        </div>

                        <!-- Choices list -->
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            <div 
                                v-for="opt in q.options" 
                                :key="opt.id"
                                @click="selectChoice(q.id, opt.id, q.type === 'multiple')"
                                class="flex items-center gap-3 p-3.5 rounded-xl border border-border/80 hover:border-primary/50 bg-muted/5 hover:bg-muted/15 cursor-pointer transition-all duration-200"
                                :class="{
                                    'border-[#1AC18C]/60 bg-[#1AC18C]/5 shadow-sm': isOptionSelected(q.id, opt.id),
                                }"
                            >
                                <!-- Radio/Checkbox selector graphics -->
                                <div class="size-4 rounded flex items-center justify-center shrink-0" :class="[
                                    q.type === 'multiple' ? 'rounded-md' : 'rounded-full',
                                    isOptionSelected(q.id, opt.id) ? 'bg-[#1AC18C] text-white' : 'border border-input bg-card'
                                ]">
                                    <div v-if="isOptionSelected(q.id, opt.id)" class="size-1.5 rounded-full bg-white"></div>
                                </div>

                                <div class="flex-1 space-y-2">
                                    <span class="text-xs font-semibold text-foreground leading-snug">{{ opt.option_text }}</span>
                                    
                                    <!-- Choice Image if exists -->
                                    <div v-if="opt.image_path" class="rounded-lg overflow-hidden max-h-24 border border-border/60 w-32">
                                        <img :src="`/storage/${opt.image_path}`" class="object-cover w-full h-full" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Action -->
                <div class="pt-6 border-t border-border/40 mt-6 flex justify-end gap-2">
                    <Button 
                        type="button" 
                        @click="isTakingTest = false" 
                        variant="outline"
                        class="rounded-xl cursor-pointer text-xs"
                    >
                        Back to Slides
                    </Button>
                    <Button 
                        type="button" 
                        @click="submitTest" 
                        :disabled="form.processing"
                        class="bg-[#1AC18C] hover:bg-[#1AC18C]/90 text-white font-bold rounded-xl cursor-pointer text-xs"
                    >
                        {{ form.processing ? 'Submitting...' : 'Submit Test' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
