<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    GraduationCap, 
    ArrowLeft, 
    Plus, 
    Trash2, 
    HelpCircle, 
    PlayCircle, 
    Music, 
    Image as ImageIcon, 
    Video, 
    AlertCircle,
    Check
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RteEditor from '@/components/RteEditor.vue';
import InputError from '@/components/InputError.vue';

interface Step {
    id?: number;
    title: string;
    content: string;
    video_url: string;
    image_path?: string | null;
    audio_path?: string | null;
    image_file?: File | null;
    audio_file?: File | null;
}

interface Option {
    id?: number;
    option_text: string;
    is_correct: boolean;
    image_path?: string | null;
    image_file?: File | null;
}

interface Question {
    id?: number;
    question_text: string;
    type: 'single' | 'multiple';
    image_path?: string | null;
    image_file?: File | null;
    options: Option[];
}

const props = defineProps<{
    training: {
        id: number;
        title: string;
        description: string | null;
        ministry: string | null;
        has_test: boolean;
        passing_score: number;
        steps: any[];
        test_questions: any[];
    } | null;
}>();

const isEditing = ref(false);

const form = useForm({
    title: '',
    description: '',
    ministry: '',
    has_test: false,
    passing_score: 80,
    steps: [] as Step[],
    questions: [] as Question[],
});

onMounted(() => {
    if (props.training) {
        isEditing.value = true;
        form.title = props.training.title;
        form.description = props.training.description || '';
        form.ministry = props.training.ministry || '';
        form.has_test = props.training.has_test;
        form.passing_score = props.training.passing_score;
        
        // Map steps
        form.steps = props.training.steps.map(s => ({
            id: s.id,
            title: s.title,
            content: s.content || '',
            video_url: s.video_url || '',
            image_path: s.image_path,
            audio_path: s.audio_path,
            image_file: null,
            audio_file: null,
        }));

        // Map questions
        form.questions = props.training.test_questions.map(q => ({
            id: q.id,
            question_text: q.question_text,
            type: q.type,
            image_path: q.image_path,
            image_file: null,
            options: q.options.map((o: any) => ({
                id: o.id,
                option_text: o.option_text || '',
                is_correct: o.is_correct,
                image_path: o.image_path,
                image_file: null,
            })),
        }));
    } else {
        // Add one default step
        addStep();
    }
});

// Step operations
const addStep = () => {
    form.steps.push({
        title: '',
        content: '',
        video_url: '',
        image_file: null,
        audio_file: null,
    });
};

const removeStep = (index: number) => {
    if (form.steps.length > 1) {
        form.steps.splice(index, 1);
    } else {
        alert('A training must have at least one step.');
    }
};

// Question operations
const addQuestion = () => {
    form.questions.push({
        question_text: '',
        type: 'single',
        image_file: null,
        options: [
            { option_text: '', is_correct: false },
            { option_text: '', is_correct: false },
        ],
    });
};

const removeQuestion = (index: number) => {
    form.questions.splice(index, 1);
};

// Option operations
const addOption = (qIdx: number) => {
    form.questions[qIdx].options.push({
        option_text: '',
        is_correct: false,
    });
};

const removeOption = (qIdx: number, oIdx: number) => {
    if (form.questions[qIdx].options.length > 2) {
        form.questions[qIdx].options.splice(oIdx, 1);
    } else {
        alert('Each question must have at least 2 choices.');
    }
};

// Handle file assignments
const handleStepImage = (event: Event, index: number) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        form.steps[index].image_file = input.files[0];
    }
};

const handleStepAudio = (event: Event, index: number) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        form.steps[index].audio_file = input.files[0];
    }
};

const handleQuestionImage = (event: Event, index: number) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        form.questions[index].image_file = input.files[0];
    }
};

const handleOptionImage = (event: Event, qIdx: number, oIdx: number) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        form.questions[qIdx].options[oIdx].image_file = input.files[0];
    }
};

const submitForm = () => {
    if (isEditing.value && props.training) {
        // Multipart updates must be sent via POST with _method spoofing
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(`/trainings/${props.training.id}`);
    } else {
        form.post('/trainings');
    }
};
</script>

<template>
    <Head :title="isEditing ? 'Edit Training' : 'Create Training'" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6 overflow-x-auto rounded-xl">
        <!-- Back navigation link -->
        <div class="flex items-center gap-2">
            <Link 
                href="/trainings"
                class="inline-flex size-8 items-center justify-center rounded-xl bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
            >
                <ArrowLeft class="size-4" />
            </Link>
            <span class="text-xs font-semibold text-muted-foreground">Back to Trainings</span>
        </div>

        <!-- Header -->
        <div class="flex items-center gap-3 border-b border-border/40 pb-4">
            <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                <GraduationCap class="size-6" />
            </div>
            <div>
                <h1 class="font-bold text-2xl text-foreground">{{ isEditing ? 'Edit Training Workspace' : 'Create Training Workspace' }}</h1>
                <p class="text-xs text-muted-foreground">Assemble step-by-step documentation, audio uploads, videos, and evaluation tests.</p>
            </div>
        </div>

        <form @submit.prevent="submitForm" class="grid gap-6 lg:grid-cols-3 items-start">
            <!-- Left panel: Details & Settings -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-4">
                    <h2 class="font-bold text-base text-foreground border-b border-border/40 pb-2">Training Properties</h2>

                    <div class="space-y-1.5">
                        <Label for="t-title">Course Title</Label>
                        <Input 
                            id="t-title"
                            v-model="form.title"
                            placeholder="e.g. FOH Audio Mixer Workflow"
                            required
                            class="rounded-xl text-xs"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="t-ministry">Ministry / Category</Label>
                        <select 
                            id="t-ministry"
                            v-model="form.ministry"
                            class="flex h-9 w-full rounded-xl border border-input bg-background px-3 py-1.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        >
                            <option value="">Select Ministry (Optional)</option>
                            <option value="Audio">Audio / Sound</option>
                            <option value="Video">Video / Cameras</option>
                            <option value="Lighting">Lighting / LX</option>
                            <option value="Projection">Projection / Media</option>
                            <option value="Worship">Worship Team</option>
                            <option value="Service">Service Hosting</option>
                        </select>
                        <InputError :message="form.errors.ministry" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="t-desc">Description</Label>
                        <textarea 
                            id="t-desc"
                            v-model="form.description"
                            placeholder="Provide a short description of what this training covers..."
                            rows="4"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <!-- Test toggle settings -->
                    <div class="pt-2 border-t border-border/40 space-y-3">
                        <div class="flex items-center justify-between">
                            <Label for="has-test" class="font-bold text-xs text-foreground cursor-pointer block">Attach Optional Test</Label>
                            <input 
                                id="has-test"
                                type="checkbox"
                                v-model="form.has_test"
                                class="size-4.5 rounded border-input text-primary focus:ring-primary cursor-pointer"
                            />
                        </div>

                        <div v-if="form.has_test" class="space-y-1.5 pt-2 animate-in fade-in slide-in-from-top-2 duration-200">
                            <Label for="pass-score">Passing Score Percentage</Label>
                            <div class="flex items-center gap-2">
                                <Input 
                                    id="pass-score"
                                    type="number"
                                    v-model="form.passing_score"
                                    min="1"
                                    max="100"
                                    class="rounded-xl text-xs w-24"
                                />
                                <span class="text-xs text-muted-foreground">% correct required to pass</span>
                            </div>
                            <InputError :message="form.errors.passing_score" />
                        </div>
                    </div>
                </div>

                <!-- Submit Button Area -->
                <div class="space-y-3">
                    <Button 
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-[#1AC18C] hover:bg-[#1AC18C]/90 text-white font-bold rounded-xl cursor-pointer p-5"
                    >
                        <Check v-if="form.wasSuccessful" class="mr-1.5 size-4.5" />
                        {{ form.processing ? 'Saving Workflow...' : isEditing ? 'Save Changes' : 'Create Training Workflow' }}
                    </Button>
                    <Link 
                        href="/trainings"
                        class="w-full inline-flex items-center justify-center rounded-xl border border-border/80 bg-background text-muted-foreground hover:text-foreground text-xs font-semibold px-4 py-2 hover:bg-muted/30 transition-all duration-200 cursor-pointer"
                    >
                        Cancel
                    </Link>
                </div>
            </div>

            <!-- Right panel: Steps Builder & Test Builder -->
            <div class="lg:col-span-2 space-y-6">
                <!-- 1. Steps Builder Section -->
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-5">
                    <div class="flex justify-between items-center border-b border-border/40 pb-3">
                        <div>
                            <h2 class="font-bold text-base text-foreground flex items-center gap-2">
                                <PlayCircle class="size-5 text-primary" /> Training Steps
                            </h2>
                            <p class="text-[11px] text-muted-foreground">Each training is built from consecutive learning steps.</p>
                        </div>
                        <Button 
                            type="button" 
                            @click="addStep" 
                            size="sm" 
                            class="bg-muted hover:bg-muted/80 text-foreground rounded-xl cursor-pointer text-xs px-3"
                        >
                            <Plus class="mr-1 size-3.5" /> Add Step
                        </Button>
                    </div>

                    <!-- Steps List -->
                    <div class="space-y-4">
                        <div 
                            v-for="(step, sIdx) in form.steps" 
                            :key="sIdx"
                            class="border border-border/60 rounded-2xl p-4 bg-muted/5 relative space-y-4"
                        >
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center justify-center size-6 rounded-full bg-primary/10 text-primary text-[10px] font-bold">
                                    {{ sIdx + 1 }}
                                </span>
                                
                                <Button 
                                    type="button" 
                                    @click="removeStep(sIdx)" 
                                    variant="ghost" 
                                    class="h-8 px-2 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-lg cursor-pointer"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <div class="space-y-1.5">
                                <Label>Step Title</Label>
                                <Input 
                                    v-model="step.title"
                                    placeholder="e.g. Console Power-On Sequence"
                                    required
                                    class="rounded-xl text-xs bg-background"
                                />
                            </div>

                            <!-- RTE editor -->
                            <div class="space-y-1.5">
                                <Label>Step Contents (Rich Text)</Label>
                                <RteEditor 
                                    v-model="step.content" 
                                    placeholder="Type markdown or styled procedural text for this step..."
                                />
                            </div>

                            <!-- Step Media Fields -->
                            <div class="grid gap-4 sm:grid-cols-3 pt-2">
                                <!-- Audio upload -->
                                <div class="space-y-1.5">
                                    <Label class="flex items-center gap-1">
                                        <Music class="size-3.5 text-muted-foreground" /> Audio File
                                    </Label>
                                    <input 
                                        type="file" 
                                        accept="audio/*"
                                        @change="handleStepAudio($event, sIdx)"
                                        class="block w-full text-xs text-muted-foreground file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-semibold file:bg-primary/10 file:text-primary file:cursor-pointer hover:file:bg-primary/20"
                                    />
                                    <div v-if="step.audio_path" class="text-[10px] text-emerald-500 font-semibold truncate flex items-center gap-0.5">
                                        <Check class="size-3" /> Existing audio loaded
                                    </div>
                                </div>

                                <!-- Image upload -->
                                <div class="space-y-1.5">
                                    <Label class="flex items-center gap-1">
                                        <ImageIcon class="size-3.5 text-muted-foreground" /> Step Image
                                    </Label>
                                    <input 
                                        type="file" 
                                        accept="image/*"
                                        @change="handleStepImage($event, sIdx)"
                                        class="block w-full text-xs text-muted-foreground file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-semibold file:bg-primary/10 file:text-primary file:cursor-pointer hover:file:bg-primary/20"
                                    />
                                    <div v-if="step.image_path" class="text-[10px] text-emerald-500 font-semibold truncate flex items-center gap-0.5">
                                        <Check class="size-3" /> Existing image loaded
                                    </div>
                                </div>

                                <!-- Video URL -->
                                <div class="space-y-1.5">
                                    <Label class="flex items-center gap-1">
                                        <Video class="size-3.5 text-muted-foreground" /> YouTube Link
                                    </Label>
                                    <Input 
                                        v-model="step.video_url"
                                        placeholder="https://youtube.com/watch?v=..."
                                        class="rounded-xl text-xs bg-background"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Test Questions Builder (Gated by has_test) -->
                <div v-if="form.has_test" class="bg-card border border-border/60 rounded-2xl p-5 space-y-5 animate-in fade-in slide-in-from-bottom-2 duration-300">
                    <div class="flex justify-between items-center border-b border-border/40 pb-3">
                        <div>
                            <h2 class="font-bold text-base text-foreground flex items-center gap-2">
                                <HelpCircle class="size-5 text-primary" /> Evaluation Test Builder
                            </h2>
                            <p class="text-[11px] text-muted-foreground">Construct multiple choice evaluations users must complete to pass.</p>
                        </div>
                        <Button 
                            type="button" 
                            @click="addQuestion" 
                            size="sm" 
                            class="bg-muted hover:bg-muted/80 text-foreground rounded-xl cursor-pointer text-xs px-3"
                        >
                            <Plus class="mr-1 size-3.5" /> Add Question
                        </Button>
                    </div>

                    <div v-if="form.questions.length === 0" class="text-center py-6 text-xs text-muted-foreground border border-dashed border-border/80 rounded-xl flex items-center justify-center gap-1.5">
                        <AlertCircle class="size-4 text-amber-500" />
                        No questions created yet. Add at least one question.
                    </div>

                    <!-- Questions List -->
                    <div class="space-y-6">
                        <div 
                            v-for="(q, qIdx) in form.questions" 
                            :key="qIdx"
                            class="border border-border/60 rounded-2xl p-5 bg-card/60 space-y-4 relative"
                        >
                            <div class="flex items-center justify-between border-b border-border/40 pb-2">
                                <span class="text-xs font-bold text-muted-foreground uppercase tracking-wide">Question {{ qIdx + 1 }}</span>
                                
                                <Button 
                                    type="button" 
                                    @click="removeQuestion(qIdx)" 
                                    variant="ghost" 
                                    class="h-8 px-2 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-lg cursor-pointer"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="sm:col-span-2 space-y-1.5">
                                    <Label>Question Text</Label>
                                    <Input 
                                        v-model="q.question_text"
                                        placeholder="e.g. Which button initializes the console mixer config?"
                                        required
                                        class="rounded-xl text-xs bg-background"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label>Question Type</Label>
                                    <select 
                                        v-model="q.type"
                                        class="flex h-9 w-full rounded-xl border border-input bg-background px-3 py-1.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                                    >
                                        <option value="single">Single Choice (Radio)</option>
                                        <option value="multiple">Multiple Choice (Checkboxes)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="space-y-1.5">
                                    <Label class="flex items-center gap-1">
                                        <ImageIcon class="size-3.5 text-muted-foreground" /> Question Image
                                    </Label>
                                    <input 
                                        type="file" 
                                        accept="image/*"
                                        @change="handleQuestionImage($event, qIdx)"
                                        class="block w-full text-xs text-muted-foreground file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-semibold file:bg-primary/10 file:text-primary file:cursor-pointer hover:file:bg-primary/20"
                                    />
                                    <div v-if="q.image_path" class="text-[10px] text-emerald-500 font-semibold truncate flex items-center gap-0.5">
                                        <Check class="size-3" /> Existing image loaded
                                    </div>
                                </div>
                            </div>

                            <!-- Options (Choice answers) -->
                            <div class="space-y-3.5 pt-2">
                                <div class="flex justify-between items-center">
                                    <Label class="text-xs uppercase text-muted-foreground tracking-wide font-bold">Answer Choices</Label>
                                    <Button 
                                        type="button" 
                                        @click="addOption(qIdx)" 
                                        size="sm" 
                                        variant="outline" 
                                        class="text-[10px] h-7 px-2.5 rounded-lg cursor-pointer"
                                    >
                                        <Plus class="mr-1 size-3" /> Add Choice
                                    </Button>
                                </div>

                                <div class="space-y-2">
                                    <div 
                                        v-for="(o, oIdx) in q.options" 
                                        :key="oIdx"
                                        class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 p-3 rounded-xl bg-muted/20 border border-border/40"
                                    >
                                        <!-- Correct mark checkbox -->
                                        <div class="flex items-center gap-2">
                                            <input 
                                                type="checkbox"
                                                v-model="o.is_correct"
                                                class="size-4.5 rounded border-input text-primary focus:ring-primary cursor-pointer"
                                                title="Mark as correct answer"
                                            />
                                            <span class="text-[10px] font-bold text-muted-foreground block sm:hidden">Correct</span>
                                        </div>

                                        <!-- Option text -->
                                        <div class="flex-1">
                                            <Input 
                                                v-model="o.option_text"
                                                placeholder="Choice answer text..."
                                                required
                                                class="rounded-xl text-xs bg-background h-8"
                                            />
                                        </div>

                                        <!-- Option image file upload -->
                                        <div class="w-full sm:w-44 flex items-center gap-2">
                                            <input 
                                                type="file" 
                                                accept="image/*"
                                                @change="handleOptionImage($event, qIdx, oIdx)"
                                                class="block w-full text-[10px] text-muted-foreground file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[9px] file:font-semibold file:bg-primary/10 file:text-primary file:cursor-pointer hover:file:bg-primary/20"
                                            />
                                            <div v-if="o.image_path" class="text-[10px] text-emerald-500 font-semibold" title="Existing image exists">
                                                <Check class="size-3.5" />
                                            </div>
                                        </div>

                                        <!-- Delete choice button -->
                                        <Button 
                                            type="button" 
                                            @click="removeOption(qIdx, oIdx)" 
                                            variant="ghost" 
                                            class="h-8 px-2 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-lg cursor-pointer"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
