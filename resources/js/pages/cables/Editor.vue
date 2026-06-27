<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    Ruler, 
    ArrowLeft, 
    Upload, 
    Plus, 
    Trash2, 
    Save, 
    Download, 
    HelpCircle, 
    Edit3,
    MousePointer,
    Tag,
    ChevronRight,
    Sliders,
    Layers,
    Info,
    Check,
    Ruler as RulerIcon
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Point {
    x: number;
    y: number;
}

interface CableType {
    id: number;
    church_id: number;
    name: string;
    color: string;
    price_per_m: number;
}

interface CableRun {
    id: string;
    name: string;
    type: string;
    color: string;
    points: Point[];
    notes?: string;
    qty?: number;
}

interface CablePlan {
    id: number;
    name: string;
    description: string | null;
    floor_plan_path: string | null;
    floor_plan_url: string | null;
    scale_pixels: number;
    scale_distance: number;
    scale_unit: 'm' | 'ft';
    slack_percent: number;
    room_height: number;
    cables: CableRun[];
    created_by: string;
}

const props = defineProps<{
    plan: CablePlan;
    cableTypes: CableType[];
}>();

// Editor modes: 'select' (idle), 'draw' (placing path points), 'calibrate' (defining scale ruler)
const editorMode = ref<'select' | 'draw' | 'calibrate'>('select');

// Workspace states
const cablesList = ref<CableRun[]>([...props.plan.cables]);
const activeCableId = ref<string | null>(cablesList.value[0]?.id || null);
const planScalePixels = ref(props.plan.scale_pixels || 100);
const planScaleDistance = ref(props.plan.scale_distance || 5);
const planScaleUnit = ref<'m' | 'ft'>(props.plan.scale_unit || 'm');
const planSlackPercent = ref(props.plan.slack_percent || 10);
const planRoomHeight = ref(props.plan.room_height || 3.5);
const calibrationPoints = ref<{x: number, y: number}[]>([]);

// Plan edit forms
const showUploadModal = ref(false);
const showCreateCableModal = ref(false);

const uploadForm = useForm({
    floor_plan: null as File | null,
});

const uploadError = ref<string | null>(null);

const handleFileUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        uploadForm.floor_plan = target.files[0];
    }
};

const submitUpload = () => {
    if (!uploadForm.floor_plan) return;
    uploadForm.post(`/cable-plans/${props.plan.id}/upload`, {
        onSuccess: () => {
            showUploadModal.value = false;
            uploadForm.reset();
        }
    });
};

// Cable creation state
const newCableForm = ref({
    name: '',
    type: props.cableTypes[0]?.name || '',
    color: props.cableTypes[0]?.color || '#3B82F6',
    notes: '',
});

const onNewCableTypeChange = () => {
    const selected = props.cableTypes.find(t => t.name === newCableForm.value.type);
    if (selected) {
        newCableForm.value.color = selected.color;
    }
};

const onActiveCableTypeChange = () => {
    if (activeCable.value) {
        const selected = props.cableTypes.find(t => t.name === activeCable.value.type);
        if (selected) {
            activeCable.value.color = selected.color;
        }
    }
};

const addCableRun = () => {
    const id = 'cable_' + Date.now();
    const newCable: CableRun = {
        id,
        name: newCableForm.value.name || `Cable Run #${cablesList.value.length + 1}`,
        type: newCableForm.value.type || props.cableTypes[0]?.name || '',
        color: newCableForm.value.color,
        points: [],
        notes: newCableForm.value.notes,
        qty: 1,
    };
    cablesList.value.push(newCable);
    activeCableId.value = id;
    showCreateCableModal.value = false;
    newCableForm.value = {
        name: '',
        type: props.cableTypes[0]?.name || '',
        color: props.cableTypes[0]?.color || '#3B82F6',
        notes: '',
    };
    editorMode.value = 'draw';
};

// Cable Types Management State & Handlers
const showManageTypesModal = ref(false);
const typeForm = useForm({
    name: '',
    color: '#3B82F6',
    price_per_m: 1.00,
});

const addCableType = () => {
    typeForm.post('/cable-types', {
        preserveScroll: true,
        onSuccess: () => {
            typeForm.reset();
        }
    });
};

const updateCableType = (type: CableType) => {
    useForm({
        name: type.name,
        color: type.color,
        price_per_m: type.price_per_m,
    }).put(`/cable-types/${type.id}`, {
        preserveScroll: true,
    });
};

const deleteCableType = (id: number) => {
    if (confirm('Delete this cable type? Any drawn cable runs using this type will remain but cost calculations will show $0.00.')) {
        useForm({}).delete(`/cable-types/${id}`, {
            preserveScroll: true,
        });
    }
};

const deleteCableRun = (id: string) => {
    if (confirm('Delete this cable run and all its drawn points?')) {
        cablesList.value = cablesList.value.filter(c => c.id !== id);
        if (activeCableId.value === id) {
            activeCableId.value = cablesList.value[0]?.id || null;
        }
    }
};

const duplicateCableRun = (cable: CableRun) => {
    const id = 'cable_' + Date.now();
    const clonedCable: CableRun = {
        id,
        name: `${cable.name} (Copy)`,
        type: cable.type,
        color: getRandomColor(cable.color),
        points: JSON.parse(JSON.stringify(cable.points)),
        notes: cable.notes ? `${cable.notes}` : '',
        qty: 1,
    };
    cablesList.value.push(clonedCable);
    activeCableId.value = id;
};

const getRandomColor = (current: string) => {
    const colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4'];
    const filtered = colors.filter(c => c.toLowerCase() !== current.toLowerCase());
    return filtered[Math.floor(Math.random() * filtered.length)] || '#10B981';
};

// Drag and drop logic for moving visual canvas path point vertices
const isDragging = ref(false);
const draggedCableId = ref<string | null>(null);
const draggedPointIdx = ref<number | null>(null);

const startDrag = (event: MouseEvent, cableId: string, pointIdx: number) => {
    event.preventDefault();
    event.stopPropagation();
    
    activeCableId.value = cableId;
    editorMode.value = 'select';
    
    isDragging.value = true;
    draggedCableId.value = cableId;
    draggedPointIdx.value = pointIdx;
    
    window.addEventListener('mousemove', onDrag);
    window.addEventListener('mouseup', endDrag);
};

const onDrag = (event: MouseEvent) => {
    if (!isDragging.value || draggedCableId.value === null || draggedPointIdx.value === null || !canvasRef.value) return;
    
    const rect = canvasRef.value.getBoundingClientRect();
    const scaleFactor = zoomLevel.value / 100;
    
    const dragX = (event.clientX - rect.left) / scaleFactor;
    const dragY = (event.clientY - rect.top) / scaleFactor;
    
    const newX = Math.max(0, Math.min(imageWidth.value, Math.round(dragX)));
    const newY = Math.max(0, Math.min(imageHeight.value, Math.round(dragY)));
    
    const cable = cablesList.value.find(c => c.id === draggedCableId.value);
    if (cable && cable.points[draggedPointIdx.value]) {
        cable.points[draggedPointIdx.value].x = newX;
        cable.points[draggedPointIdx.value].y = newY;
    }
};

const endDrag = () => {
    isDragging.value = false;
    draggedCableId.value = null;
    draggedPointIdx.value = null;
    
    window.removeEventListener('mousemove', onDrag);
    window.removeEventListener('mouseup', endDrag);
};

onUnmounted(() => {
    window.removeEventListener('mousemove', onDrag);
    window.removeEventListener('mouseup', endDrag);
});

// Canvas drawing handles
const canvasRef = ref<HTMLDivElement | null>(null);
const zoomLevel = ref<number>(100);

const imageWidth = ref(800);
const imageHeight = ref(500);

const onImageLoad = (event: Event) => {
    const img = event.target as HTMLImageElement;
    const naturalWidth = img.naturalWidth || 800;
    const naturalHeight = img.naturalHeight || 500;
    
    const maxBaseWidth = 1200;
    const maxBaseHeight = 800;
    
    let width = naturalWidth;
    let height = naturalHeight;
    
    if (width > maxBaseWidth) {
        height = (maxBaseWidth / width) * height;
        width = maxBaseWidth;
    }
    if (height > maxBaseHeight) {
        width = (maxBaseHeight / height) * width;
        height = maxBaseHeight;
    }
    
    imageWidth.value = Math.round(width);
    imageHeight.value = Math.round(height);
};

const activeCable = computed(() => {
    return cablesList.value.find(c => c.id === activeCableId.value) || null;
});

// Calibration calculations
const currentScaleRatio = computed(() => {
    if (planScalePixels.value <= 0) return 0;
    return planScaleDistance.value / planScalePixels.value;
});

// Real-time horizontal path calculation with room height vertical drops/rises added
const calculateRunLength = (cable: CableRun) => {
    const points = cable.points;
    if (points.length < 2) return 0;
    let len = 0;
    const ratio = currentScaleRatio.value;
    for (let i = 0; i < points.length - 1; i++) {
        const p1 = points[i];
        const p2 = points[i + 1];
        const dx = (p2.x - p1.x) * ratio;
        const dy = (p2.y - p1.y) * ratio;
        len += Math.sqrt(dx * dx + dy * dy);
    }
    
    // Add 2 * room height to single run
    const singleLength = len + (2 * (planRoomHeight.value || 3.5));
    
    // Apply slack percent
    const singleLengthWithSlack = singleLength * (1 + planSlackPercent.value / 100);
    
    // Multiply by Qty
    const qty = cable.qty || 1;
    return singleLengthWithSlack * qty;
};

const calculateRunRawLength = (cable: CableRun) => {
    const points = cable.points;
    if (points.length < 2) return 0;
    let len = 0;
    const ratio = currentScaleRatio.value;
    for (let i = 0; i < points.length - 1; i++) {
        const p1 = points[i];
        const p2 = points[i + 1];
        const dx = (p2.x - p1.x) * ratio;
        const dy = (p2.y - p1.y) * ratio;
        len += Math.sqrt(dx * dx + dy * dy);
    }
    // Return single raw horizontal length + vertical drop without slack or Qty multiplier
    return len + (2 * (planRoomHeight.value || 3.5));
};

const getCableTypePrice = (typeName: string) => {
    const type = props.cableTypes.find(t => t.name === typeName);
    return type ? type.price_per_m : 0;
};

const calculateRunCost = (cable: CableRun) => {
    return calculateRunLength(cable) * getCableTypePrice(cable.type);
};

// Summarize requirements by cable type
const totalsByType = computed(() => {
    const totals: Record<string, { length: number; price_per_m: number; color: string; cost: number }> = {};
    
    props.cableTypes.forEach(t => {
        totals[t.name] = {
            length: 0,
            price_per_m: t.price_per_m,
            color: t.color,
            cost: 0,
        };
    });

    cablesList.value.forEach(c => {
        const len = calculateRunLength(c);
        if (totals[c.type]) {
            totals[c.type].length += len;
            totals[c.type].cost += len * totals[c.type].price_per_m;
        } else {
            if (!totals[c.type]) {
                totals[c.type] = {
                    length: 0,
                    price_per_m: 0,
                    color: c.color || '#9ca3af',
                    cost: 0,
                };
            }
            totals[c.type].length += len;
        }
    });

    return totals;
});

const grandTotalCost = computed(() => {
    let sum = 0;
    Object.values(totalsByType.value).forEach(t => {
        sum += t.cost;
    });
    return sum;
});

// Canvas click listener to place vertex or ruler calibration point
const onCanvasClick = (event: MouseEvent) => {
    if (!canvasRef.value) return;
    const rect = canvasRef.value.getBoundingClientRect();
    // Translate click to image coordinate space by adjusting for zoom
    const scaleFactor = zoomLevel.value / 100;
    const clickX = (event.clientX - rect.left) / scaleFactor;
    const clickY = (event.clientY - rect.top) / scaleFactor;

    if (editorMode.value === 'draw') {
        if (!activeCable.value) {
            alert('Please select or create an active cable run to start drawing.');
            return;
        }
        activeCable.value.points.push({
            x: Math.round(clickX),
            y: Math.round(clickY)
        });
    } else if (editorMode.value === 'calibrate') {
        calibrationPoints.value.push({ x: Math.round(clickX), y: Math.round(clickY) });
        if (calibrationPoints.value.length === 2) {
            const dx = calibrationPoints.value[1].x - calibrationPoints.value[0].x;
            const dy = calibrationPoints.value[1].y - calibrationPoints.value[0].y;
            const pxDist = Math.sqrt(dx * dx + dy * dy);
            
            const distStr = prompt(`Calibration: Enter the real-world distance between these two points in ${planScaleUnit.value}:`, '10');
            if (distStr !== null) {
                const distVal = parseFloat(distStr);
                if (!isNaN(distVal) && distVal > 0) {
                    planScalePixels.value = Math.round(pxDist);
                    planScaleDistance.value = distVal;
                }
            }
            calibrationPoints.value = [];
            editorMode.value = 'select';
        }
    }
};

// Remove single point from active cable
const removePoint = (idx: number) => {
    if (activeCable.value) {
        activeCable.value.points.splice(idx, 1);
    }
};

// Save modifications back to DB
const saveForm = useForm({
    name: props.plan.name,
    description: props.plan.description || '',
    scale_pixels: planScalePixels.value,
    scale_distance: planScaleDistance.value,
    scale_unit: planScaleUnit.value,
    slack_percent: planSlackPercent.value,
    room_height: planRoomHeight.value,
    cables: [] as CableRun[],
});

const isSaved = ref(false);
const savePlan = () => {
    saveForm.name = props.plan.name;
    saveForm.scale_pixels = planScalePixels.value;
    saveForm.scale_distance = planScaleDistance.value;
    saveForm.scale_unit = planScaleUnit.value;
    saveForm.slack_percent = planSlackPercent.value;
    saveForm.room_height = planRoomHeight.value;
    saveForm.cables = cablesList.value;

    saveForm.put(`/cable-plans/${props.plan.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isSaved.value = true;
            setTimeout(() => { isSaved.value = false; }, 2500);
        }
    });
};

// CSV Bill of Materials Export
const exportBOM = () => {
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Cable ID,Cable Name,Cable Type,Color,Quantity,Raw Single Length,Slack Percentage,Total Length (with Slack),Unit,Price per unit,Total Cost,Notes\r\n";
    
    cablesList.value.forEach(c => {
        const raw = calculateRunRawLength(c).toFixed(2);
        const tot = calculateRunLength(c).toFixed(2);
        const qty = c.qty || 1;
        const price = getCableTypePrice(c.type);
        const cost = (parseFloat(tot) * price).toFixed(2);
        csvContent += `"${c.id}","${c.name}","${c.type}","${c.color}",${qty},${raw},${planSlackPercent.value}%,${tot},"${planScaleUnit.value}","$${price.toFixed(2)}","$${cost}","${c.notes || ''}"\r\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `${props.plan.name.replace(/\s+/g, '_')}_BOM.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Cable Calculator', href: '/cable-plans' },
            { title: 'Editor', href: '' }
        ]
    }
});
</script>

<template>
    <Head :title="`Editor: ${plan.name}`" />

    <div class="flex h-full flex-1 flex-col gap-5 p-6 overflow-x-auto rounded-xl">
        <!-- Top bar/Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-border/40 pb-4">
            <div class="space-y-1.5 flex-1">
                <div class="flex items-center gap-2">
                    <Link href="/cable-plans" class="inline-flex items-center text-xs text-muted-foreground hover:text-primary transition-colors">
                        <ArrowLeft class="mr-1 size-3.5" /> Back to Plans
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <h1 class="font-bold text-2xl text-foreground">{{ plan.name }}</h1>
                    <span class="text-xs text-muted-foreground bg-primary/5 border border-primary/10 px-2 py-0.5 rounded-full">
                        Slack: {{ planSlackPercent }}%
                    </span>
                </div>
                <p class="text-xs text-muted-foreground max-w-2xl">{{ plan.description || 'No description provided.' }}</p>
            </div>

            <!-- Toolbar actions -->
            <div class="flex flex-wrap gap-2">
                <Button 
                    @click="showUploadModal = true"
                    variant="outline" 
                    size="sm" 
                    class="h-9 text-xs cursor-pointer rounded-xl bg-card"
                >
                    <Upload class="mr-1.5 size-3.5" /> 
                    {{ plan.floor_plan_path ? 'Replace Floor Plan' : 'Upload Floor Plan' }}
                </Button>
                <Button 
                    @click="exportBOM"
                    variant="outline" 
                    size="sm" 
                    class="h-9 text-xs cursor-pointer rounded-xl bg-card"
                    :disabled="cablesList.length === 0"
                >
                    <Download class="mr-1.5 size-3.5" /> Export BOM
                </Button>
                <Button 
                    @click="savePlan"
                    class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs h-9"
                    :disabled="saveForm.processing"
                >
                    <Check v-if="isSaved" class="mr-1.5 size-4" />
                    <Save v-else class="mr-1.5 size-4" />
                    {{ saveForm.processing ? 'Saving...' : isSaved ? 'Saved Design!' : 'Save Plan' }}
                </Button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-4 items-start">
            <!-- Left: Sidebar Tools (All Panels) -->
            <div class="xl:col-span-1 space-y-5">
                <!-- 1. Calibration Scale & Config -->
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center gap-2 border-b border-border/40 pb-3">
                        <Sliders class="size-4.5 text-primary" />
                        <h3 class="font-bold text-xs uppercase tracking-wider text-muted-foreground">Calibration settings</h3>
                    </div>

                    <div class="space-y-3.5">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Unit</Label>
                                <select v-model="planScaleUnit" class="flex h-9 w-full rounded-xl border border-input bg-background px-2 py-1 text-[11px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option value="m">Meters (m)</option>
                                    <option value="ft">Feet (ft)</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Slack (%)</Label>
                                <Input type="number" min="0" max="100" v-model.number="planSlackPercent" class="rounded-xl h-9 px-2 text-[11px]" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Height ({{ planScaleUnit }})</Label>
                                <Input type="number" step="0.1" min="0" v-model.number="planRoomHeight" class="rounded-xl h-9 px-2 text-[11px]" />
                            </div>
                        </div>

                        <!-- Calibration tools -->
                        <div class="p-3 bg-muted/40 rounded-xl border border-border/40 space-y-2">
                            <div class="flex justify-between items-center text-xs">
                                <span class="font-bold text-foreground">Scale Calibration</span>
                                <span class="text-[10px] text-muted-foreground">1px = {{ currentScaleRatio.toFixed(3) }}{{ planScaleUnit }}</span>
                            </div>
                            <p class="text-[10px] text-muted-foreground leading-relaxed">
                                Draw a known reference line on the floor plan image to set exact physical coordinate ratios.
                            </p>
                            <Button 
                                type="button" 
                                @click="editorMode = 'calibrate'"
                                variant="outline" 
                                size="sm" 
                                class="w-full text-xs h-8 cursor-pointer rounded-lg bg-card"
                                :class="editorMode === 'calibrate' ? 'border-primary ring-1 ring-primary text-primary' : ''"
                            >
                                <RulerIcon class="mr-1.5 size-3.5" /> Calibrate Ruler Line
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- 2. Cables Segment Runs -->
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-border/40 pb-3">
                        <div class="flex items-center gap-2">
                            <Layers class="size-4.5 text-primary" />
                            <h3 class="font-bold text-xs uppercase tracking-wider text-muted-foreground">Cable Runs</h3>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button 
                                @click="showManageTypesModal = true"
                                size="sm" 
                                variant="ghost" 
                                class="h-7 px-2 hover:bg-muted text-muted-foreground rounded-lg text-[10px]"
                            >
                                <Settings class="size-3 mr-1" /> Types
                            </Button>
                            <Button 
                                @click="showCreateCableModal = true"
                                size="sm" 
                                variant="ghost" 
                                class="h-7 px-2 hover:bg-muted text-primary rounded-lg text-[10px]"
                            >
                                <Plus class="size-3.5 mr-1" /> Add
                            </Button>
                        </div>
                    </div>

                    <div class="space-y-2.5 max-h-[250px] overflow-y-auto">
                        <div 
                            v-for="cable in cablesList" 
                            :key="cable.id"
                            @click="activeCableId = cable.id; editorMode = 'draw'"
                            class="p-2.5 rounded-xl border border-border/50 bg-muted/20 hover:bg-muted/40 cursor-pointer flex justify-between items-center transition-all"
                            :class="activeCableId === cable.id ? 'border-[#1AC18C] bg-[#1AC18C]/5' : ''"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="size-3 rounded-full shrink-0" :style="{ backgroundColor: cable.color }"></span>
                                <div class="min-w-0">
                                    <span class="font-bold text-xs text-foreground block truncate" :title="cable.name">{{ cable.name }}</span>
                                    <span class="text-[10px] text-muted-foreground block font-medium">{{ cable.type }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <div class="text-right">
                                    <span class="text-xs font-bold text-foreground block">{{ calculateRunLength(cable).toFixed(1) }}{{ planScaleUnit }}</span>
                                    <span v-if="getCableTypePrice(cable.type) > 0" class="text-[9px] text-muted-foreground block font-medium">${{ calculateRunCost(cable).toFixed(2) }}</span>
                                </div>
                                <button 
                                    @click.stop="deleteCableRun(cable.id)"
                                    class="text-muted-foreground hover:text-red-500 cursor-pointer animate-none"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                        </div>
                        <div v-if="cablesList.length === 0" class="text-center py-6 text-muted-foreground italic text-xs">
                            No cable runs defined yet. Click "Add" to start.
                        </div>
                    </div>
                </div>

                <!-- 3. Active Cable Run Details -->
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center gap-2 border-b border-border/40 pb-3">
                        <Edit3 class="size-4.5 text-primary" />
                        <h3 class="font-bold text-xs uppercase tracking-wider text-muted-foreground">Cable Run Details</h3>
                    </div>

                    <div v-if="activeCable" class="space-y-4">
                        <div class="space-y-1.5">
                            <span class="text-[10px] uppercase font-bold text-muted-foreground block">Active Cable</span>
                            <div class="font-bold text-sm text-foreground">{{ activeCable.name }}</div>
                            <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                                <div class="text-[10px] text-[#1AC18C] font-semibold bg-[#1AC18C]/10 px-2 py-0.5 rounded-full inline-block">
                                    Total: {{ calculateRunLength(activeCable).toFixed(1) }}{{ planScaleUnit }}
                                </div>
                                <div v-if="getCableTypePrice(activeCable.type) > 0" class="text-[10px] text-primary font-semibold bg-primary/10 px-2 py-0.5 rounded-full inline-block">
                                    Cost: ${{ calculateRunCost(activeCable).toFixed(2) }}
                                </div>
                            </div>
                        </div>

                        <!-- Properties Card -->
                        <div class="space-y-3.5 p-3.5 bg-muted/40 rounded-xl border border-border/40">
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Cable Name</Label>
                                <Input v-model="activeCable.name" class="rounded-xl h-8 px-2 text-xs bg-card" />
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="space-y-1">
                                    <Label class="text-[10px] uppercase font-bold text-muted-foreground">Cable Type</Label>
                                    <select v-model="activeCable.type" @change="onActiveCableTypeChange" class="flex h-8 w-full rounded-xl border border-input bg-card px-2 py-1 text-[11px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                        <option v-for="type in cableTypes" :key="type.id" :value="type.name">
                                            {{ type.name }} (${{ type.price_per_m.toFixed(2) }}/{{ planScaleUnit }})
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-[10px] uppercase font-bold text-muted-foreground">Color Accent</Label>
                                    <div class="flex items-center gap-1.5">
                                        <Input type="color" v-model="activeCable.color" class="rounded-xl h-8 p-1 cursor-pointer bg-card w-10 shrink-0 border border-input" />
                                        <span class="text-[9px] text-muted-foreground font-mono truncate uppercase">{{ activeCable.color }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Notes</Label>
                                <textarea v-model="activeCable.notes" rows="2" class="flex w-full rounded-xl border border-input bg-card px-2.5 py-1.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80" placeholder="e.g. conduit section..."></textarea>
                            </div>
                        </div>

                        <!-- Quantity control & Path Duplication -->
                        <div class="grid grid-cols-2 gap-2.5 items-end p-3.5 bg-muted/40 rounded-xl border border-border/40">
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Quantity</Label>
                                <Input type="number" min="1" v-model.number="activeCable.qty" class="rounded-xl h-8 px-2 text-xs bg-card" />
                            </div>
                            <div>
                                <Button 
                                    type="button" 
                                    @click="duplicateCableRun(activeCable)" 
                                    variant="outline" 
                                    size="sm" 
                                    class="w-full text-[10px] font-bold h-8 cursor-pointer rounded-lg bg-card border-primary/20 text-primary hover:bg-primary/5 px-1.5"
                                >
                                    <Plus class="size-3 mr-1 shrink-0" /> Duplicate Path
                                </Button>
                            </div>
                        </div>

                        <!-- Vertex lists (no height input) -->
                        <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1">
                            <div 
                                v-for="(p, idx) in activeCable.points" 
                                :key="idx"
                                class="flex items-center justify-between gap-2 p-2 bg-muted/40 border border-border/40 rounded-xl text-xs"
                            >
                                <span class="font-bold text-muted-foreground">Pt {{ idx + 1 }}</span>
                                <span class="text-[10px] text-muted-foreground font-mono">({{ p.x }}, {{ p.y }})</span>
                                <button 
                                    @click="removePoint(idx)" 
                                    class="text-muted-foreground hover:text-red-500 cursor-pointer"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                            <div v-if="activeCable.points.length === 0" class="text-center py-6 text-muted-foreground italic text-xs">
                                Tap on the floor plan map to place cable routing points.
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-muted-foreground italic text-xs">
                        Select a cable run from the sidebar to view points.
                    </div>
                </div>

                <!-- 4. Consolidated Bill of Materials Summary -->
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-4 shadow-sm">
                    <div class="flex items-center gap-2 border-b border-border/40 pb-3">
                        <Tag class="size-4.5 text-primary" />
                        <h3 class="font-bold text-xs uppercase tracking-wider text-muted-foreground">Material Cost & Lengths</h3>
                    </div>

                    <div class="space-y-2.5">
                        <div 
                            v-for="(info, type) in totalsByType" 
                            :key="type"
                            class="flex justify-between items-start py-1.5 border-b border-border/20 text-xs"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="size-2.5 rounded-full shrink-0" :style="{ backgroundColor: info.color }"></span>
                                <div class="min-w-0">
                                    <span class="font-semibold text-foreground block truncate" :title="type">{{ type }}</span>
                                    <span class="text-[9px] text-muted-foreground block font-medium">${{ info.price_per_m.toFixed(2) }}/{{ planScaleUnit }}</span>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-bold text-foreground block">{{ info.length.toFixed(1) }}{{ planScaleUnit }}</span>
                                <span v-if="info.cost > 0" class="text-[10px] font-bold text-[#1AC18C] block">${{ info.cost.toFixed(2) }}</span>
                            </div>
                        </div>
                        
                        <!-- Grand Total Cost -->
                        <div v-if="grandTotalCost > 0" class="flex justify-between items-center pt-2.5 border-t border-double border-primary/30 text-xs">
                            <span class="font-bold text-foreground uppercase tracking-wider text-[10px]">Grand Total Cost</span>
                            <span class="font-black text-sm text-[#1AC18C]">${{ grandTotalCost.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Interactive SVG Canvas Workspace (Occupies remaining 3 columns) -->
            <div class="xl:col-span-3 space-y-4">
                <div class="bg-card border border-border/60 rounded-2xl p-4 flex flex-col gap-4 shadow-sm relative overflow-hidden">
                    <!-- Zoom & Editor Mode Toolbar -->
                    <div class="flex flex-wrap justify-between items-center gap-4 bg-muted/30 p-2.5 border border-border/40 rounded-xl">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xs font-bold text-muted-foreground">Mode:</span>
                            <div class="inline-flex rounded-lg border border-border bg-card p-1">
                                <button 
                                    @click="editorMode = 'select'"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold rounded-md cursor-pointer transition-colors"
                                    :class="editorMode === 'select' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                                >
                                    <MousePointer class="size-3" /> Select / Edit
                                </button>
                                <button 
                                    @click="editorMode = 'draw'"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-semibold rounded-md cursor-pointer transition-colors"
                                    :class="editorMode === 'draw' ? 'bg-[#1AC18C] text-white' : 'text-muted-foreground hover:bg-muted'"
                                >
                                    <Edit3 class="size-3" /> Draw Path
                                </button>
                            </div>
                        </div>

                        <!-- Zoom controls -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-muted-foreground">Zoom:</span>
                            <input type="range" min="50" max="200" v-model.number="zoomLevel" class="w-24 cursor-pointer" />
                            <span class="text-xs font-mono w-10 text-right">{{ zoomLevel }}%</span>
                        </div>
                    </div>

                    <!-- Canvas Box -->
                    <div class="border border-border/40 rounded-xl overflow-auto bg-muted/20 relative min-h-[450px] max-h-[700px] flex items-start justify-start p-4">
                        <!-- Outer scroll spacer wrapper -->
                        <div 
                            :style="{ 
                                width: (imageWidth * (zoomLevel / 100)) + 'px',
                                height: (imageHeight * (zoomLevel / 100)) + 'px',
                                position: 'relative'
                            }"
                            class="shrink-0"
                        >
                            <!-- Inner Canvas container wrapper adjusted for zoom -->
                            <div 
                                ref="canvasRef"
                                @click="onCanvasClick"
                                class="absolute top-0 left-0 select-none cursor-crosshair origin-top-left"
                                :style="{ 
                                    width: imageWidth + 'px',
                                    height: imageHeight + 'px',
                                    transform: `scale(${zoomLevel / 100})`
                                }"
                            >
                                <!-- Floor plan image -->
                                <img 
                                    v-if="plan.floor_plan_url" 
                                    :src="plan.floor_plan_url" 
                                    @load="onImageLoad"
                                    class="block pointer-events-none w-full h-full" 
                                    alt="Church Floor Plan"
                                />
                                <div 
                                    v-else 
                                    class="w-full h-full bg-card border border-dashed border-border/80 flex flex-col justify-center items-center text-center p-8 space-y-3 rounded-xl pointer-events-none"
                                >
                                    <Ruler class="size-12 text-muted-foreground/40 animate-pulse" />
                                    <h3 class="font-bold text-sm text-foreground">Upload a floor plan image</h3>
                                    <p class="text-xs text-muted-foreground max-w-sm">Use the "Upload Floor Plan" button at the top to import a church blueprint layout to start drawing conduits.</p>
                                </div>

                                <!-- SVG Overlay to draw cable runs -->
                                <svg 
                                    class="absolute inset-0 w-full h-full pointer-events-none"
                                    style="z-index: 10;"
                                >
                                    <!-- Drawn cables -->
                                    <g v-for="cable in cablesList" :key="cable.id">
                                        <!-- Render path segments -->
                                        <path 
                                            v-if="cable.points.length > 1"
                                            :d="cable.points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')"
                                            fill="none"
                                            :stroke="cable.color"
                                            stroke-width="3"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            :opacity="activeCableId === cable.id ? 1 : 0.6"
                                        />
                                        
                                        <!-- Render vertices nodes -->
                                        <circle 
                                            v-for="(p, idx) in cable.points" 
                                            :key="idx"
                                            :cx="p.x" 
                                            :cy="p.y" 
                                            r="6" 
                                            :fill="cable.color"
                                            stroke="#ffffff"
                                            stroke-width="1.5"
                                            :opacity="activeCableId === cable.id ? 1 : 0.7"
                                            class="cursor-pointer pointer-events-auto hover:stroke-[3px] transition-all"
                                            @mousedown.stop.prevent="startDrag($event, cable.id, idx)"
                                            @dblclick.stop="removePoint(idx)"
                                            :title="activeCableId === cable.id ? 'Drag to move. Double-click to remove.' : 'Click to select this run'"
                                        />
                                    </g>

                                    <!-- Ruler Calibration feedback visual -->
                                    <g v-if="editorMode === 'calibrate' && calibrationPoints.length > 0">
                                        <circle 
                                            :cx="calibrationPoints[0].x" 
                                            :cy="calibrationPoints[0].y" 
                                            r="6" 
                                            fill="#E11D48" 
                                            stroke="#ffffff" 
                                            stroke-width="1.5" 
                                        />
                                        <line 
                                            v-if="calibrationPoints.length === 2"
                                            :x1="calibrationPoints[0].x"
                                            :y1="calibrationPoints[0].y"
                                            :x2="calibrationPoints[1].x"
                                            :y2="calibrationPoints[1].y"
                                            stroke="#E11D48"
                                            stroke-width="2"
                                            stroke-dasharray="4"
                                        />
                                    </g>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Upload Floor Plan -->
        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <Upload class="size-5 text-primary" />
                        Upload Floor Plan Map
                    </h3>
                    <button @click="showUploadModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitUpload" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="blueprint-file">Select Image File (PNG, JPG, SVG - Max 5MB)</Label>
                        <input 
                            id="blueprint-file"
                            type="file"
                            accept="image/*"
                            @change="handleFileUpload"
                            required
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-xs text-muted-foreground focus-visible:outline-none"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showUploadModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="uploadForm.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ uploadForm.processing ? 'Uploading...' : 'Upload Image' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Add Cable Run -->
        <div v-if="showCreateCableModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <Plus class="size-5 text-primary" />
                        Add New Cable Run
                    </h3>
                    <button @click="showCreateCableModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="addCableRun" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="cable-name">Cable Name / Run ID</Label>
                        <Input 
                            id="cable-name"
                            v-model="newCableForm.name"
                            placeholder="e.g. Stage Box A Link"
                            required
                            class="rounded-xl"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label for="cable-type">Cable Type</Label>
                            <select id="cable-type" v-model="newCableForm.type" @change="onNewCableTypeChange" class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option v-for="type in cableTypes" :key="type.id" :value="type.name">
                                    {{ type.name }} (${{ type.price_per_m.toFixed(2) }}/{{ planScaleUnit }})
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="cable-color">Color Accent</Label>
                            <Input 
                                id="cable-color"
                                type="color"
                                v-model="newCableForm.color"
                                class="rounded-xl h-10 p-1 cursor-pointer"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="cable-notes">Notes / Location Details (Optional)</Label>
                        <textarea 
                            id="cable-notes"
                            v-model="newCableForm.notes"
                            placeholder="e.g. conduit tray section B, FOH routing tray..."
                            rows="2"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showCreateCableModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">Add Cable</Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Manage Cable Types -->
        <div v-if="showManageTypesModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <Settings class="size-5 text-primary" />
                        Manage Cable Types & Pricing
                    </h3>
                    <button @click="showManageTypesModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Current Types List Table -->
                    <div class="space-y-2">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-muted-foreground">Configured Cable Types</h4>
                        <div class="border border-border/40 rounded-xl overflow-hidden bg-muted/20 max-h-[220px] overflow-y-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-border/40 bg-muted/40 text-[10px] uppercase font-bold text-muted-foreground">
                                        <th class="p-3">Type Name</th>
                                        <th class="p-3 w-28">Color</th>
                                        <th class="p-3 w-32">Price per {{ planScaleUnit }}</th>
                                        <th class="p-3 w-24 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="type in cableTypes" :key="type.id" class="border-b border-border/20 last:border-0 hover:bg-muted/10">
                                        <td class="p-3">
                                            <Input v-model="type.name" class="h-8 px-2 text-xs rounded-lg bg-card" />
                                        </td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-2">
                                                <Input type="color" v-model="type.color" class="h-8 p-1 cursor-pointer w-10 shrink-0 border border-input rounded-lg bg-card" />
                                                <span class="text-[10px] text-muted-foreground font-mono uppercase">{{ type.color }}</span>
                                            </div>
                                        </td>
                                        <td class="p-3">
                                            <Input type="number" step="0.01" min="0" v-model.number="type.price_per_m" class="h-8 px-2 text-xs rounded-lg bg-card" />
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex justify-end gap-1.5">
                                                <Button 
                                                    @click="updateCableType(type)"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-8 px-2 bg-primary/5 hover:bg-primary/10 border-primary/20 text-primary font-bold text-[10px] rounded-lg cursor-pointer"
                                                    title="Save changes"
                                                >
                                                    Save
                                                </Button>
                                                <Button 
                                                    @click="deleteCableType(type.id)"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 w-8 p-0 text-muted-foreground hover:text-red-500 rounded-lg cursor-pointer"
                                                    title="Delete Type"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="cableTypes.length === 0">
                                        <td colspan="4" class="p-8 text-center text-muted-foreground italic">No cable types configured.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Add New Type Form -->
                    <form @submit.prevent="addCableType" class="p-4 bg-muted/30 border border-border/40 rounded-xl space-y-4">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-muted-foreground">Add Custom Cable Type</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="space-y-1.5">
                                <Label for="t-name" class="text-[10px] uppercase font-bold text-muted-foreground">Type Name</Label>
                                <Input id="t-name" v-model="typeForm.name" placeholder="e.g. Fiber SM" required class="rounded-xl h-9 text-xs bg-card" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="t-color" class="text-[10px] uppercase font-bold text-muted-foreground">Default Color</Label>
                                <div class="flex gap-2 items-center">
                                    <Input id="t-color" type="color" v-model="typeForm.color" class="rounded-xl h-9 p-1 cursor-pointer w-12 border border-input bg-card" />
                                    <span class="text-xs text-muted-foreground font-mono uppercase">{{ typeForm.color }}</span>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="t-price" class="text-[10px] uppercase font-bold text-muted-foreground">Price per {{ planScaleUnit }}</Label>
                                <Input id="t-price" type="number" step="0.01" min="0" v-model.number="typeForm.price_per_m" required class="rounded-xl h-9 text-xs bg-card" />
                            </div>
                        </div>
                        <div class="flex justify-end pt-2 border-t border-border/20">
                            <Button type="submit" :disabled="typeForm.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer text-xs h-9">
                                {{ typeForm.processing ? 'Adding...' : 'Add Cable Type' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
