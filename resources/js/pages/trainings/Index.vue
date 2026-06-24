<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    BookOpen, 
    GraduationCap, 
    Plus, 
    Users, 
    Trash2, 
    Edit, 
    Calendar, 
    CheckCircle, 
    XCircle, 
    Clock, 
    ArrowRight,
    Search,
    BookOpenCheck,
    FileText
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Training {
    id: number;
    title: string;
    description: string | null;
    ministry: string | null;
    has_test: boolean;
    passing_score: number;
    steps_count: number;
    assignments_count: number;
    attempts_count: number;
}

interface Assignment {
    id: number;
    training_id: number;
    title: string;
    description: string | null;
    ministry: string | null;
    due_at: string | null;
    status: string;
    assigned_by: string;
}

interface Attempt {
    id: number;
    title: string;
    ministry: string | null;
    score: number;
    passed: boolean;
    completed_at: string;
}

interface Member {
    id: number;
    name: string;
    email: string;
}

interface Report {
    id: number;
    user_name: string;
    user_email: string;
    training_title: string;
    ministry: string | null;
    score: number;
    passed: boolean;
    completed_at: string;
}

const props = defineProps<{
    trainings: Training[];
    assignments: Assignment[];
    myHistory: Attempt[];
    churchMembers: Member[];
    reports: Report[];
    userRole: string;
}>();

const activeTab = ref('my-trainings');
const activeAdminTab = ref('directory');
const isManager = computed(() => ['Admin', 'Manager'].includes(props.userRole));

// Search filtering for admin
const adminSearch = ref('');
const filteredTrainings = computed(() => {
    return props.trainings.filter(t => 
        t.title.toLowerCase().includes(adminSearch.value.toLowerCase()) || 
        (t.ministry && t.ministry.toLowerCase().includes(adminSearch.value.toLowerCase()))
    );
});

// Search filtering for reports
const reportSearch = ref('');
const filteredReports = computed(() => {
    return props.reports.filter(r => 
        r.user_name.toLowerCase().includes(reportSearch.value.toLowerCase()) || 
        r.training_title.toLowerCase().includes(reportSearch.value.toLowerCase())
    );
});

// Assign Modal State
const showAssignModal = ref(false);
const selectedTrainingForAssign = ref<Training | null>(null);
const assignForm = useForm({
    user_ids: [] as number[],
    due_at: '',
});

const openAssignModal = (training: Training) => {
    selectedTrainingForAssign.value = training;
    assignForm.reset();
    showAssignModal.value = true;
};

const submitAssign = () => {
    if (!selectedTrainingForAssign.value) return;
    assignForm.post(`/trainings/${selectedTrainingForAssign.value.id}/assign`, {
        onSuccess: () => {
            showAssignModal.value = false;
            selectedTrainingForAssign.value = null;
        }
    });
};

const deleteTraining = (id: number) => {
    if (confirm('Are you sure you want to delete this training? This will delete all steps, tests, and user completion logs.')) {
        useForm({}).delete(`/trainings/${id}`);
    }
};

const formatDate = (isoString: string | null) => {
    if (!isoString) return 'No due date';
    const d = new Date(isoString);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Ministry Trainings" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6 overflow-x-auto rounded-xl">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-border/40 pb-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                    <GraduationCap class="size-6" />
                </div>
                <div>
                    <h1 class="font-bold text-2xl text-foreground">Ministry Trainings</h1>
                    <p class="text-xs text-muted-foreground">Learn standard procedures, workflows, and test your knowledge.</p>
                </div>
            </div>

            <!-- Tab Switcher -->
            <div class="flex bg-muted/60 p-1 rounded-xl items-center self-stretch sm:self-auto">
                <button 
                    @click="activeTab = 'my-trainings'" 
                    class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer"
                    :class="activeTab === 'my-trainings' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                >
                    <span class="flex items-center justify-center gap-1.5">
                        <BookOpenCheck class="size-3.5" /> My Trainings
                    </span>
                </button>
                <button 
                    v-if="isManager"
                    @click="activeTab = 'manage-trainings'" 
                    class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer"
                    :class="activeTab === 'manage-trainings' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                >
                    <span class="flex items-center justify-center gap-1.5">
                        <Users class="size-3.5" /> Manage & Reports
                    </span>
                </button>
            </div>
        </div>

        <!-- TAB 1: User Dashboard -->
        <div v-if="activeTab === 'my-trainings'" class="space-y-6">
            <!-- Pending Assignments -->
            <div>
                <h2 class="font-bold text-lg text-foreground mb-4 flex items-center gap-2">
                    <Clock class="size-5 text-amber-500" /> 
                    Assigned Trainings
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-500">
                        {{ assignments.filter(a => a.status !== 'completed').length }}
                    </span>
                </h2>

                <div v-if="assignments.filter(a => a.status !== 'completed').length === 0" class="border border-border/40 rounded-2xl bg-card p-10 text-center flex flex-col items-center justify-center space-y-3">
                    <CheckCircle class="size-10 text-[#1AC18C]" />
                    <h3 class="font-bold text-base text-foreground">You are fully caught up!</h3>
                    <p class="text-xs text-muted-foreground max-w-sm">No pending training workflows are currently assigned to you.</p>
                </div>

                <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div 
                        v-for="assign in assignments.filter(a => a.status !== 'completed')" 
                        :key="assign.id"
                        class="bg-card border border-border/60 hover:border-primary/40 rounded-2xl p-5 flex flex-col justify-between transition-all duration-300 relative group overflow-hidden"
                    >
                        <!-- Ministry badge -->
                        <span 
                            v-if="assign.ministry" 
                            class="absolute top-4 right-4 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-primary/10 text-primary"
                        >
                            {{ assign.ministry }}
                        </span>

                        <div class="space-y-3 pr-14">
                            <h3 class="font-bold text-base text-foreground leading-snug group-hover:text-primary transition-colors">{{ assign.title }}</h3>
                            <p class="text-xs text-muted-foreground line-clamp-2 h-8">{{ assign.description || 'No description provided.' }}</p>
                        </div>

                        <div class="pt-4 border-t border-border/40 mt-4 flex items-center justify-between text-[11px] text-muted-foreground">
                            <div class="flex items-center gap-1">
                                <Calendar class="size-3.5 text-muted-foreground" />
                                <span>Due: {{ formatDate(assign.due_at) }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="size-1.5 rounded-full bg-amber-500"></span>
                                <span class="capitalize">{{ assign.status === 'failed' ? 'Retry required' : assign.status }}</span>
                            </div>
                        </div>

                        <div class="pt-4">
                            <Link 
                                :href="`/trainings/${assign.training_id}/play`"
                                class="w-full inline-flex items-center justify-center rounded-xl bg-primary text-primary-foreground text-xs font-semibold px-4 py-2 hover:bg-primary/95 transition-all duration-200 cursor-pointer"
                            >
                                Start Course
                                <ArrowRight class="ml-1.5 size-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completed History -->
            <div>
                <h2 class="font-bold text-lg text-foreground mb-4">Completed History</h2>
                
                <div v-if="myHistory.length === 0" class="text-center py-6 text-xs text-muted-foreground italic">
                    You have not completed any trainings yet.
                </div>

                <div v-else class="bg-card border border-border/40 rounded-2xl overflow-hidden">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-muted/10 border-b border-border/60 text-muted-foreground font-semibold">
                                <th class="p-3.5">Training</th>
                                <th class="p-3.5">Ministry</th>
                                <th class="p-3.5">Last Score</th>
                                <th class="p-3.5">Result</th>
                                <th class="p-3.5 text-right">Date Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attempt in myHistory" :key="attempt.id" class="border-b border-border/40 hover:bg-muted/10">
                                <td class="p-3.5 font-bold text-foreground">{{ attempt.title }}</td>
                                <td class="p-3.5 text-muted-foreground">{{ attempt.ministry || '—' }}</td>
                                <td class="p-3.5 font-mono font-bold">{{ attempt.score }}%</td>
                                <td class="p-3.5">
                                    <span 
                                        class="inline-flex items-center gap-1 font-semibold"
                                        :class="attempt.passed ? 'text-[#1AC18C]' : 'text-red-500'"
                                    >
                                        <CheckCircle v-if="attempt.passed" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                        {{ attempt.passed ? 'Passed' : 'Failed' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right text-muted-foreground">{{ formatDate(attempt.completed_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: Manage & Reports -->
        <div v-if="activeTab === 'manage-trainings' && isManager" class="space-y-6">
            <!-- Admin Sub-tab Switcher -->
            <div class="flex items-center justify-between border-b border-border/40 pb-2">
                <div class="flex gap-4">
                    <button 
                        @click="activeAdminTab = 'directory'" 
                        class="pb-2 text-xs font-semibold border-b-2 cursor-pointer transition-all"
                        :class="activeAdminTab === 'directory' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    >
                        Trainings Directory
                    </button>
                    <button 
                        @click="activeAdminTab = 'reports'" 
                        class="pb-2 text-xs font-semibold border-b-2 cursor-pointer transition-all"
                        :class="activeAdminTab === 'reports' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    >
                        Church Completion Logs
                    </button>
                </div>

                <Link 
                    v-if="activeAdminTab === 'directory'"
                    href="/trainings/create"
                    class="inline-flex items-center justify-center rounded-xl bg-primary text-primary-foreground text-xs font-semibold px-3.5 py-1.5 hover:bg-primary/95 transition-all duration-200 cursor-pointer shadow-sm"
                >
                    <Plus class="mr-1.5 size-4" /> Create Training
                </Link>
            </div>

            <!-- Directory Panel -->
            <div v-if="activeAdminTab === 'directory'" class="space-y-4">
                <!-- Search bar -->
                <div class="relative max-w-sm">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input 
                        v-model="adminSearch"
                        placeholder="Search trainings by title or ministry..."
                        class="pl-9 rounded-xl text-xs"
                    />
                </div>

                <div v-if="filteredTrainings.length === 0" class="text-center py-10 border border-border/40 rounded-2xl bg-card text-muted-foreground text-xs italic">
                    No trainings found.
                </div>

                <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div 
                        v-for="t in filteredTrainings" 
                        :key="t.id"
                        class="bg-card border border-border/60 rounded-2xl p-5 flex flex-col justify-between hover:border-primary/30 transition-all duration-200"
                    >
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-4">
                                <span 
                                    v-if="t.ministry" 
                                    class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-muted text-muted-foreground border border-border/60"
                                >
                                    {{ t.ministry }}
                                </span>
                                <span class="text-[9px] font-bold uppercase text-muted-foreground" v-else>General</span>

                                <span class="text-xs text-muted-foreground font-semibold flex items-center gap-0.5">
                                    <FileText class="size-3.5" /> {{ t.steps_count }} steps
                                </span>
                            </div>

                            <h3 class="font-bold text-base text-foreground leading-snug">{{ t.title }}</h3>
                            <p class="text-xs text-muted-foreground line-clamp-2">{{ t.description || 'No description.' }}</p>
                        </div>

                        <!-- Stats & Metadata -->
                        <div class="grid grid-cols-2 gap-2 pt-4 border-t border-border/40 mt-4 text-[10px] text-muted-foreground">
                            <div>
                                <span class="block text-muted-foreground uppercase text-[8px] font-bold">Assignments</span>
                                <span class="font-bold text-foreground text-xs">{{ t.assignments_count }} users</span>
                            </div>
                            <div>
                                <span class="block text-muted-foreground uppercase text-[8px] font-bold">Passing score</span>
                                <span class="font-bold text-foreground text-xs">{{ t.passing_score }}%</span>
                            </div>
                        </div>

                        <div class="pt-4 flex gap-2">
                            <Button 
                                @click="openAssignModal(t)"
                                class="flex-1 bg-muted hover:bg-muted/80 text-foreground font-semibold text-xs rounded-xl cursor-pointer"
                            >
                                <Users class="mr-1.5 size-3.5" /> Assign
                            </Button>
                            
                            <Link 
                                :href="`/trainings/${t.id}/edit`"
                                class="inline-flex size-9 items-center justify-center rounded-xl bg-muted text-muted-foreground hover:text-foreground hover:bg-muted/80 cursor-pointer"
                                title="Edit Training"
                            >
                                <Edit class="size-4" />
                            </Link>

                            <Button 
                                @click="deleteTraining(t.id)"
                                variant="ghost"
                                class="size-9 p-0 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-xl cursor-pointer"
                                title="Delete Training"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Panel -->
            <div v-if="activeAdminTab === 'reports'" class="space-y-4">
                <!-- Search bar -->
                <div class="relative max-w-sm">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input 
                        v-model="reportSearch"
                        placeholder="Search logs by member or training title..."
                        class="pl-9 rounded-xl text-xs"
                    />
                </div>

                <div v-if="filteredReports.length === 0" class="text-center py-10 border border-border/40 rounded-2xl bg-card text-muted-foreground text-xs italic">
                    No completion records found.
                </div>

                <div v-else class="bg-card border border-border/40 rounded-2xl overflow-hidden">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-muted/10 border-b border-border/60 text-muted-foreground font-semibold">
                                <th class="p-3.5">Member</th>
                                <th class="p-3.5">Training</th>
                                <th class="p-3.5">Ministry</th>
                                <th class="p-3.5">Score</th>
                                <th class="p-3.5">Result</th>
                                <th class="p-3.5 text-right">Date Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in filteredReports" :key="log.id" class="border-b border-border/40 hover:bg-muted/10">
                                <td class="p-3.5">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-foreground">{{ log.user_name }}</span>
                                        <span class="text-[10px] text-muted-foreground">{{ log.user_email }}</span>
                                    </div>
                                </td>
                                <td class="p-3.5 font-bold text-foreground">{{ log.training_title }}</td>
                                <td class="p-3.5 text-muted-foreground">{{ log.ministry || '—' }}</td>
                                <td class="p-3.5 font-mono font-bold">{{ log.score }}%</td>
                                <td class="p-3.5">
                                    <span 
                                        class="inline-flex items-center gap-1 font-semibold"
                                        :class="log.passed ? 'text-[#1AC18C]' : 'text-red-500'"
                                    >
                                        <CheckCircle v-if="log.passed" class="size-3.5" />
                                        <XCircle v-else class="size-3.5" />
                                        {{ log.passed ? 'Passed' : 'Failed' }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right text-muted-foreground">{{ formatDate(log.completed_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: Assign Training -->
        <div v-if="showAssignModal && selectedTrainingForAssign" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <Users class="size-5 text-primary" />
                        Assign Training Workflow
                    </h3>
                    <button @click="showAssignModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitAssign" class="p-6 space-y-4">
                    <div class="space-y-1">
                        <span class="text-[10px] uppercase font-bold text-muted-foreground block">Selected Workflow</span>
                        <div class="font-bold text-base text-foreground leading-snug">{{ selectedTrainingForAssign.title }}</div>
                        <div class="text-xs text-muted-foreground block">{{ selectedTrainingForAssign.ministry || 'General' }} Ministry</div>
                    </div>

                    <!-- Member Checklist -->
                    <div class="space-y-2 pt-2 border-t border-border/40">
                        <Label class="text-xs font-bold text-muted-foreground uppercase">Select Church Members</Label>
                        
                        <div class="max-h-48 overflow-y-auto border border-border/60 rounded-xl divide-y divide-border/40 p-1">
                            <div 
                                v-for="m in churchMembers" 
                                :key="m.id"
                                class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-muted/40"
                            >
                                <input 
                                    :id="`assign-user-${m.id}`"
                                    type="checkbox" 
                                    :value="m.id"
                                    v-model="assignForm.user_ids"
                                    class="size-4 rounded border-input text-primary focus:ring-primary cursor-pointer animate-none"
                                />
                                <Label :for="`assign-user-${m.id}`" class="cursor-pointer flex-1">
                                    <span class="font-semibold text-xs text-foreground block">{{ m.name }}</span>
                                    <span class="text-[10px] text-muted-foreground block leading-none">{{ m.email }}</span>
                                </Label>
                            </div>
                            <div v-if="churchMembers.length === 0" class="text-center py-6 text-xs text-muted-foreground italic">
                                No church members available.
                            </div>
                        </div>
                        <span class="text-[10px] text-red-500 font-semibold" v-if="assignForm.errors.user_ids">{{ assignForm.errors.user_ids }}</span>
                    </div>

                    <!-- Due date -->
                    <div class="space-y-1.5 pt-2 border-t border-border/40">
                        <Label for="due-date">Due Date (Optional)</Label>
                        <Input 
                            id="due-date"
                            type="date"
                            v-model="assignForm.due_at"
                            class="rounded-xl text-xs"
                        />
                        <span class="text-[10px] text-red-500 font-semibold" v-if="assignForm.errors.due_at">{{ assignForm.errors.due_at }}</span>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showAssignModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="assignForm.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ assignForm.processing ? 'Assigning...' : 'Assign Training' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
