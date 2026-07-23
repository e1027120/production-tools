<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Package, Calendar, MapPin, Wrench, CircleAlert, CheckCircle2, 
    Trash2, Edit, ChevronLeft, Activity, DollarSign, Clock, User, Plus, X 
} from '@lucide/vue';

interface Asset {
    id: number;
    church_id: number;
    name: string;
    category: string;
    brand: string | null;
    model: string | null;
    serial_number: string | null;
    status: string;
    location: string | null;
    purchase_date: string | null;
    purchase_price: number | null;
    notes: string | null;
    created_at: string;
    creator?: {
        name: string;
    };
}

interface MaintenanceLog {
    id: number;
    service_type: string;
    status: string;
    performed_by: string | null;
    cost: number;
    service_date: string;
    notes: string | null;
    created_at: string;
    creator?: {
        name: string;
    };
}

interface ServiceReminder {
    id: number;
    title: string;
    frequency: string;
    next_due_date: string;
    status: string;
    notes: string | null;
    created_at: string;
    creator?: {
        name: string;
    };
}

const props = defineProps<{
    asset: Asset;
    logs: MaintenanceLog[];
    reminders: ServiceReminder[];
}>();

const page = usePage();
const auth = computed(() => page.props.auth as any);
const currentChurch = computed(() => auth.value?.currentChurch);
const userRole = computed(() => currentChurch.value?.pivot?.role || 'User');
const isReadOnly = computed(() => userRole.value === 'User');

// Navigation Tabs
const activeTab = ref('logs'); // 'logs' | 'reminders'

// Modal states
const showEditModal = ref(false);
const showLogModal = ref(false);
const showReminderModal = ref(false);

// Edit Asset Form
const editForm = useForm({
    name: props.asset.name,
    category: props.asset.category,
    brand: props.asset.brand || '',
    model: props.asset.model || '',
    serial_number: props.asset.serial_number || '',
    status: props.asset.status,
    location: props.asset.location || '',
    purchase_date: props.asset.purchase_date ? props.asset.purchase_date.substring(0, 10) : '',
    purchase_price: props.asset.purchase_price || '',
    notes: props.asset.notes || '',
});

// New Log Form
const logForm = useForm({
    service_type: 'Routine',
    status: 'Completed',
    performed_by: '',
    cost: '0' as string | number,
    service_date: new Date().toISOString().substring(0, 10),
    notes: '',
});

// New Reminder Form
const reminderForm = useForm({
    title: '',
    frequency: 'Monthly',
    next_due_date: '',
    notes: '',
});

// Helpers
const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const getReminderDueStatus = (dueDateStr: string) => {
    const due = new Date(dueDateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    due.setHours(0, 0, 0, 0);

    const diffTime = due.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) return 'overdue';
    if (diffDays <= 7) return 'due-soon';
    return 'normal';
};

const totalMaintenanceCost = computed(() => {
    return props.logs.reduce((sum, log) => sum + Number(log.cost), 0);
});

// Actions
const submitEdit = () => {
    editForm.put(`/assets/${props.asset.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
        }
    });
};

const submitDelete = () => {
    if (confirm('Are you sure you want to delete this asset? All maintenance logs and reminders will be deleted permanently.')) {
        useForm({}).delete(`/assets/${props.asset.id}`);
    }
};

const submitLog = () => {
    logForm.post(`/assets/${props.asset.id}/logs`, {
        onSuccess: () => {
            showLogModal.value = false;
            logForm.reset('service_type', 'status', 'performed_by', 'cost', 'notes');
            logForm.service_date = new Date().toISOString().substring(0, 10);
        }
    });
};

const submitReminder = () => {
    reminderForm.post(`/assets/${props.asset.id}/reminders`, {
        onSuccess: () => {
            showReminderModal.value = false;
            reminderForm.reset();
        }
    });
};

const completeReminder = (reminderId: number) => {
    if (confirm('Mark this service reminder as complete? This will auto-create a completed maintenance log and reschedule the next occurrence.')) {
        useForm({}).post(`/assets/${props.asset.id}/reminders/${reminderId}/complete`, {
            preserveScroll: true
        });
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Asset Manager', href: '/assets' },
            { title: 'Asset Specs', href: '' }
        ]
    }
});
</script>

<template>
    <Head :title="`Asset: ${asset.name}`" />

    <div class="space-y-6 p-4 max-w-7xl mx-auto">
        <!-- Back Button Header -->
        <div class="flex items-center justify-between">
            <Link href="/assets" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground font-semibold transition-colors">
                <ChevronLeft class="size-4" /> Back to Assets
            </Link>

            <div class="flex gap-2" v-if="!isReadOnly">
                <Button 
                    @click="showEditModal = true" 
                    variant="outline" 
                    class="rounded-xl text-xs h-9 border-border/60 hover:bg-muted/40 cursor-pointer"
                >
                    <Edit class="size-4.5 mr-1.5" /> Edit Specs
                </Button>
                <Button 
                    @click="submitDelete" 
                    variant="ghost" 
                    class="rounded-xl text-xs h-9 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 cursor-pointer"
                >
                    <Trash2 class="size-4.5 mr-1.5" /> Delete
                </Button>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <!-- Left Specifications Sheet Card -->
            <div class="md:col-span-1 bg-card border border-border/40 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
                <div class="p-6 space-y-6">
                    <div class="space-y-2">
                        <div class="size-11 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                            <Package class="size-5.5" />
                        </div>
                        <div>
                            <h2 class="font-extrabold text-lg text-foreground tracking-tight">{{ asset.name }}</h2>
                            <span class="text-[10px] text-muted-foreground font-mono block uppercase tracking-wider mt-0.5">{{ asset.category }} MODULE</span>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-border/40 text-xs">
                        <div class="flex justify-between py-1.5 border-b border-border/10">
                            <span class="text-muted-foreground">Brand:</span>
                            <span class="font-semibold text-foreground">{{ asset.brand || '—' }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-border/10">
                            <span class="text-muted-foreground">Model:</span>
                            <span class="font-semibold text-foreground">{{ asset.model || '—' }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-border/10">
                            <span class="text-muted-foreground">Serial Number:</span>
                            <span class="font-mono text-foreground">{{ asset.serial_number || '—' }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-border/10">
                            <span class="text-muted-foreground">Location:</span>
                            <span class="font-semibold text-foreground inline-flex items-center gap-1">
                                <MapPin class="size-3 text-muted-foreground" /> {{ asset.location || '—' }}
                            </span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-border/10">
                            <span class="text-muted-foreground">Status:</span>
                            <span 
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider"
                                :class="{
                                    'bg-emerald-500/10 text-emerald-500': asset.status === 'Active',
                                    'bg-amber-500/10 text-amber-500': asset.status === 'Maintenance',
                                    'bg-blue-500/10 text-blue-500': asset.status === 'In Storage',
                                    'bg-red-500/10 text-red-500': asset.status === 'Retired',
                                }"
                            >
                                {{ asset.status }}
                            </span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-border/10">
                            <span class="text-muted-foreground">Purchase Date:</span>
                            <span class="font-semibold text-foreground">{{ asset.purchase_date ? formatDate(asset.purchase_date) : '—' }}</span>
                        </div>
                        <div class="flex justify-between py-1.5">
                            <span class="text-muted-foreground">Purchase Value:</span>
                            <span class="font-bold text-foreground">{{ asset.purchase_price ? formatCurrency(Number(asset.purchase_price)) : '—' }}</span>
                        </div>
                    </div>

                    <div v-if="asset.notes" class="pt-4 border-t border-border/40 space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">Specifications & Notes</span>
                        <p class="text-xs text-muted-foreground whitespace-pre-wrap leading-relaxed">{{ asset.notes }}</p>
                    </div>
                </div>

                <div class="p-5 border-t border-border/30 bg-muted/5 flex items-center gap-2 text-[10px] text-muted-foreground">
                    <User class="size-3.5" />
                    <span>Registered by {{ asset.creator?.name || 'Workspace Creator' }}</span>
                </div>
            </div>

            <!-- Right Details, Maintenance Logs, and Service Reminders Tabs Area -->
            <div class="md:col-span-2 space-y-4">
                <!-- Navigation Tabs Bar -->
                <div class="flex border-b border-border/40 p-1 bg-muted/20 rounded-xl max-w-sm">
                    <button 
                        @click="activeTab = 'logs'"
                        class="flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5"
                        :class="activeTab === 'logs' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    >
                        <Wrench class="size-3.5" /> Maintenance History
                    </button>
                    <button 
                        @click="activeTab = 'reminders'"
                        class="flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5"
                        :class="activeTab === 'reminders' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    >
                        <Clock class="size-3.5" /> Service Reminders
                    </button>
                </div>

                <!-- TAB 1: Maintenance History -->
                <div v-if="activeTab === 'logs'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground font-semibold">Total Maintenance Spend:</span>
                            <span class="font-extrabold text-sm text-[#1AC18C]">{{ formatCurrency(totalMaintenanceCost) }}</span>
                        </div>
                        <Button 
                            v-if="!isReadOnly"
                            @click="showLogModal = true"
                            size="sm"
                            class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-lg text-[10px] h-8 cursor-pointer"
                        >
                            <Plus class="size-3.5 mr-1" /> Log Service
                        </Button>
                    </div>

                    <!-- Maintenance Timeline -->
                    <div v-if="logs.length > 0" class="space-y-3">
                        <div v-for="log in logs" :key="log.id" class="bg-card border border-border/40 p-4 rounded-2xl shadow-sm relative overflow-hidden group">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-foreground">{{ log.service_type }} Service</span>
                                        <span 
                                            class="inline-flex px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider"
                                            :class="log.status === 'Completed' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500'"
                                        >{{ log.status }}</span>
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-1 flex items-center gap-1">
                                        <span>On {{ formatDate(log.service_date) }}</span>
                                        <span v-if="log.performed_by">• By {{ log.performed_by }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-xs text-foreground block">{{ formatCurrency(Number(log.cost)) }}</span>
                                </div>
                            </div>
                            <p v-if="log.notes" class="text-xs text-muted-foreground/90 mt-3 border-t border-border/10 pt-2 leading-relaxed">{{ log.notes }}</p>
                        </div>
                    </div>

                    <div v-else class="text-center py-12 bg-card border border-dashed border-border/80 rounded-2xl p-6">
                        <Wrench class="size-8 text-muted-foreground mx-auto mb-2" />
                        <h4 class="font-bold text-xs text-foreground">No maintenance records logged</h4>
                        <p class="text-[10px] text-muted-foreground max-w-xs mx-auto mt-1 leading-normal">
                            All repairs, cleaning checks, and calibrations will appear here once logged.
                        </p>
                    </div>
                </div>

                <!-- TAB 2: Service Reminders -->
                <div v-if="activeTab === 'reminders'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-muted-foreground font-semibold">{{ reminders.length }} Scheduled Reminders</span>
                        <Button 
                            v-if="!isReadOnly"
                            @click="showReminderModal = true"
                            size="sm"
                            class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-lg text-[10px] h-8 cursor-pointer"
                        >
                            <Plus class="size-3.5 mr-1" /> Add Reminder
                        </Button>
                    </div>

                    <!-- Reminders Grid/List -->
                    <div v-if="reminders.length > 0" class="space-y-3">
                        <div 
                            v-for="reminder in reminders" 
                            :key="reminder.id" 
                            class="bg-card border p-4 rounded-2xl shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-3 relative overflow-hidden group"
                            :class="{
                                'border-red-500/30 bg-red-500/5': getReminderDueStatus(reminder.next_due_date) === 'overdue',
                                'border-amber-500/30 bg-amber-500/5': getReminderDueStatus(reminder.next_due_date) === 'due-soon',
                                'border-border/40': getReminderDueStatus(reminder.next_due_date) === 'normal',
                            }"
                        >
                            <div class="grow min-w-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-xs text-foreground truncate">{{ reminder.title }}</h4>
                                    <span class="px-1.5 py-0.5 rounded text-[8px] bg-muted font-bold text-muted-foreground uppercase tracking-wider">{{ reminder.frequency }}</span>
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-[10px]">
                                    <span 
                                        class="font-semibold inline-flex items-center gap-1"
                                        :class="{
                                            'text-red-500': getReminderDueStatus(reminder.next_due_date) === 'overdue',
                                            'text-amber-600 dark:text-amber-400': getReminderDueStatus(reminder.next_due_date) === 'due-soon',
                                            'text-muted-foreground': getReminderDueStatus(reminder.next_due_date) === 'normal',
                                        }"
                                    >
                                        <CircleAlert v-if="getReminderDueStatus(reminder.next_due_date) !== 'normal'" class="size-3" />
                                        Next Due: {{ formatDate(reminder.next_due_date) }}
                                    </span>
                                </div>
                                <p v-if="reminder.notes" class="text-[11px] text-muted-foreground mt-2 border-t border-border/10 pt-1.5 leading-normal max-w-md">{{ reminder.notes }}</p>
                            </div>

                            <div v-if="!isReadOnly && reminder.status === 'Active'" class="shrink-0 flex items-center justify-end w-full md:w-auto">
                                <Button 
                                    @click="completeReminder(reminder.id)"
                                    size="sm"
                                    variant="outline"
                                    class="rounded-lg text-[10px] h-8 bg-card border-emerald-500/30 text-emerald-500 hover:bg-emerald-500/10 cursor-pointer"
                                >
                                    <CheckCircle2 class="size-3.5 mr-1" /> Mark Done
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-12 bg-card border border-dashed border-border/80 rounded-2xl p-6">
                        <Clock class="size-8 text-muted-foreground mx-auto mb-2" />
                        <h4 class="font-bold text-xs text-foreground">No reminders set</h4>
                        <p class="text-[10px] text-muted-foreground max-w-xs mx-auto mt-1 leading-normal">
                            Configure periodic reminders (Monthly, Annually) to receive alerts when equipment filters need cleaning or systems need calibration.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Edit Asset Specs -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Package class="size-5 text-primary" />
                        <h3 class="font-bold text-base text-foreground">Edit Asset Specs</h3>
                    </div>
                    <button @click="showEditModal = false" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </div>
                <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="edit-name" class="text-xs text-foreground font-bold">Asset Name</Label>
                        <Input id="edit-name" v-model="editForm.name" required class="rounded-xl h-9 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="edit-category" class="text-xs text-foreground font-bold">Category</Label>
                            <select 
                                id="edit-category"
                                v-model="editForm.category"
                                class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-9 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="Audio">Audio</option>
                                <option value="Video">Video</option>
                                <option value="Lighting">Lighting</option>
                                <option value="IT">IT</option>
                                <option value="Instrument">Instrument</option>
                                <option value="Stage">Stage</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit-status" class="text-xs text-foreground font-bold">Status</Label>
                            <select 
                                id="edit-status"
                                v-model="editForm.status"
                                class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-9 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="Active">Active</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="In Storage">In Storage</option>
                                <option value="Retired">Retired</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="edit-brand" class="text-xs text-foreground font-bold">Brand</Label>
                            <Input id="edit-brand" v-model="editForm.brand" class="rounded-xl h-9 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit-model" class="text-xs text-foreground font-bold">Model</Label>
                            <Input id="edit-model" v-model="editForm.model" class="rounded-xl h-9 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="edit-serial" class="text-xs text-foreground font-bold">Serial Number</Label>
                            <Input id="edit-serial" v-model="editForm.serial_number" class="rounded-xl h-9 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit-location" class="text-xs text-foreground font-bold">Location</Label>
                            <Input id="edit-location" v-model="editForm.location" class="rounded-xl h-9 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="edit-purchase-date" class="text-xs text-foreground font-bold">Purchase Date</Label>
                            <Input id="edit-purchase-date" type="date" v-model="editForm.purchase_date" class="rounded-xl h-9 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="edit-price" class="text-xs text-foreground font-bold">Purchase Price ($)</Label>
                            <Input id="edit-price" type="number" step="0.01" min="0" v-model="editForm.purchase_price" class="rounded-xl h-9 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit-notes" class="text-xs text-foreground font-bold">Specifications / Notes</Label>
                        <textarea 
                            id="edit-notes" 
                            v-model="editForm.notes" 
                            rows="3" 
                            class="flex w-full rounded-xl border border-input bg-transparent px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button 
                            type="button" 
                            @click="showEditModal = false" 
                            variant="outline" 
                            class="rounded-xl text-xs h-9 cursor-pointer"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit" 
                            :disabled="editForm.processing || !editForm.name.trim()" 
                            class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl text-xs h-9 cursor-pointer"
                        >
                            {{ editForm.processing ? 'Saving...' : 'Save Specs' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Log Service / Maintenance Record -->
        <div v-if="showLogModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Wrench class="size-5 text-primary" />
                        <h3 class="font-bold text-base text-foreground">Log Maintenance Service</h3>
                    </div>
                    <button @click="showLogModal = false" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </div>
                <form @submit.prevent="submitLog" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="log-type" class="text-xs text-foreground font-bold">Service Type</Label>
                            <select 
                                id="log-type"
                                v-model="logForm.service_type"
                                class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-9 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="Routine">Routine Maintenance</option>
                                <option value="Repair">Repair</option>
                                <option value="Calibration">Calibration</option>
                                <option value="Upgrade">Upgrade</option>
                                <option value="Emergency">Emergency Fix</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="log-status" class="text-xs text-foreground font-bold">Status</Label>
                            <select 
                                id="log-status"
                                v-model="logForm.status"
                                class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-9 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="Completed">Completed</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="log-date" class="text-xs text-foreground font-bold">Service Date</Label>
                            <Input id="log-date" type="date" v-model="logForm.service_date" required class="rounded-xl h-9 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="log-cost" class="text-xs text-foreground font-bold">Service Cost ($)</Label>
                            <Input id="log-cost" type="number" step="0.01" min="0" v-model="logForm.cost" placeholder="0.00" class="rounded-xl h-9 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="log-performer" class="text-xs text-foreground font-bold">Performed By (Contractor / Tech)</Label>
                        <Input id="log-performer" v-model="logForm.performed_by" placeholder="e.g. Center Stage Audio Services" class="rounded-xl h-9 text-xs" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="log-notes" class="text-xs text-foreground font-bold">Service Details / Work Done</Label>
                        <textarea 
                            id="log-notes" 
                            v-model="logForm.notes" 
                            placeholder="Describe details of repairs, parts replaced, diagnostic findings..." 
                            rows="4" 
                            class="flex w-full rounded-xl border border-input bg-transparent px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button 
                            type="button" 
                            @click="showLogModal = false" 
                            variant="outline" 
                            class="rounded-xl text-xs h-9 cursor-pointer"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit" 
                            :disabled="logForm.processing || !logForm.service_date" 
                            class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl text-xs h-9 cursor-pointer"
                        >
                            {{ logForm.processing ? 'Saving...' : 'Save Record' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Add Service Reminder -->
        <div v-if="showReminderModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Clock class="size-5 text-primary" />
                        <h3 class="font-bold text-base text-foreground">Add Service Reminder</h3>
                    </div>
                    <button @click="showReminderModal = false" class="text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </div>
                <form @submit.prevent="submitReminder" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="rem-title" class="text-xs text-foreground font-bold">Service / Action Title</Label>
                        <Input 
                            id="rem-title" 
                            v-model="reminderForm.title" 
                            placeholder="e.g. Projector Filter Replacement" 
                            required 
                            class="rounded-xl h-9 text-xs"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="rem-freq" class="text-xs text-foreground font-bold">Frequency Interval</Label>
                            <select 
                                id="rem-freq"
                                v-model="reminderForm.frequency"
                                class="flex w-full rounded-xl border border-input bg-card px-3 py-2 text-xs shadow-sm h-9 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                            >
                                <option value="One-time">One-time Task</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Quarterly">Quarterly</option>
                                <option value="Semi-Annually">Semi-Annually</option>
                                <option value="Annually">Annually</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="rem-due" class="text-xs text-foreground font-bold">Next Due Date</Label>
                            <Input id="rem-due" type="date" v-model="reminderForm.next_due_date" required class="rounded-xl h-9 text-xs" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="rem-notes" class="text-xs text-foreground font-bold">Reminder Notes</Label>
                        <textarea 
                            id="rem-notes" 
                            v-model="reminderForm.notes" 
                            placeholder="Describe any step-by-step instructions or parts/lubricants needed for this routine check..." 
                            rows="3" 
                            class="flex w-full rounded-xl border border-input bg-transparent px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-primary"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button 
                            type="button" 
                            @click="showReminderModal = false" 
                            variant="outline" 
                            class="rounded-xl text-xs h-9 cursor-pointer"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit" 
                            :disabled="reminderForm.processing || !reminderForm.title.trim() || !reminderForm.next_due_date" 
                            class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl text-xs h-9 cursor-pointer"
                        >
                            {{ reminderForm.processing ? 'Adding...' : 'Add Reminder' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
