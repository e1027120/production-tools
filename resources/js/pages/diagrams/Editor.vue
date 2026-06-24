<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    VueFlow, 
    useVueFlow, 
    Handle, 
    Position
} from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { 
    Network, 
    ArrowLeft, 
    Save, 
    Plus, 
    Trash2, 
    HelpCircle, 
    Info,
    Check
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Import stylesheet files
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';

interface Diagram {
    id: number;
    name: string;
    description: string | null;
    data: {
        nodes: any[];
        edges: any[];
    } | null;
}

const props = defineProps<{
    diagram: Diagram;
}>();

const nodes = ref<any[]>([]);
const edges = ref<any[]>([]);

onMounted(() => {
    if (props.diagram.data) {
        nodes.value = (props.diagram.data.nodes || []).map((node: any) => {
            if (!node.data) {
                node.data = {};
            }
            if (node.data.inputs === undefined) {
                node.data.inputs = 1;
            }
            if (node.data.outputs === undefined) {
                node.data.outputs = 1;
            }
            return node;
        });
        edges.value = (props.diagram.data.edges || []).map((edge: any) => {
            if (edge.targetHandle === 'left') {
                edge.targetHandle = 'in-1';
            }
            if (edge.sourceHandle === 'right') {
                edge.sourceHandle = 'out-1';
            }
            edge.type = 'custom';
            if (!edge.data) {
                edge.data = {};
            }
            if (!edge.data.waypoints) {
                edge.data.waypoints = [];
            }
            return edge;
        });
    }
});

// Viewport and custom edge drawing setup
const { viewport } = useVueFlow();

let dragStartMouseX = 0;
let dragStartMouseY = 0;
let dragStartPtX = 0;
let dragStartPtY = 0;
let activeDragEdge: any = null;
let activeDragPtIdx = -1;

const onWaypointMouseDown = (event: MouseEvent, edgeId: string, idx: number) => {
    const edge = edges.value.find(e => e.id === edgeId);
    if (!edge || !edge.data?.waypoints) {
        return;
    }
    
    event.preventDefault();
    event.stopPropagation();
    
    activeDragEdge = edge;
    activeDragPtIdx = idx;
    dragStartMouseX = event.clientX;
    dragStartMouseY = event.clientY;
    
    const pt = edge.data.waypoints[idx];
    dragStartPtX = pt.x;
    dragStartPtY = pt.y;
    
    window.addEventListener('mousemove', onWaypointMouseMove);
    window.addEventListener('mouseup', onWaypointMouseUp);
};

const onWaypointMouseMove = (event: MouseEvent) => {
    if (!activeDragEdge || activeDragPtIdx === -1) {
        return;
    }
    
    const deltaX = event.clientX - dragStartMouseX;
    const deltaY = event.clientY - dragStartMouseY;
    
    const zoom = (viewport && 'value' in viewport) ? (viewport.value.zoom || 1) : ((viewport as any).zoom || 1);
    
    const pt = activeDragEdge.data.waypoints[activeDragPtIdx];
    pt.x = Math.round(dragStartPtX + deltaX / zoom);
    pt.y = Math.round(dragStartPtY + deltaY / zoom);
};

const onWaypointMouseUp = () => {
    activeDragEdge = null;
    activeDragPtIdx = -1;
    window.removeEventListener('mousemove', onWaypointMouseMove);
    window.removeEventListener('mouseup', onWaypointMouseUp);
};

const addWaypoint = () => {
    if (!selectedEdge.value) {
        return;
    }
    if (!selectedEdge.value.data) {
        selectedEdge.value.data = {};
    }
    if (!selectedEdge.value.data.waypoints) {
        selectedEdge.value.data.waypoints = [];
    }

    const sourceNode = nodes.value.find(n => n.id === selectedEdge.value.source);
    const targetNode = nodes.value.find(n => n.id === selectedEdge.value.target);

    let x = 150;
    let y = 150;

    if (sourceNode && targetNode) {
        x = Math.round((sourceNode.position.x + targetNode.position.x) / 2);
        y = Math.round((sourceNode.position.y + targetNode.position.y) / 2);
    }

    const length = selectedEdge.value.data.waypoints.length;
    if (length > 0) {
        const lastPt = selectedEdge.value.data.waypoints[length - 1];
        x = lastPt.x + 30;
        y = lastPt.y + 30;
    }

    selectedEdge.value.data.waypoints.push({ x, y });
};

const deleteWaypoint = (idx: number) => {
    if (!selectedEdge.value || !selectedEdge.value.data?.waypoints) {
        return;
    }
    selectedEdge.value.data.waypoints.splice(idx, 1);
};

const getCustomPath = (sourceX: number, sourceY: number, targetX: number, targetY: number, waypoints?: { x: number; y: number }[]) => {
    let path = `M ${sourceX} ${sourceY}`;
    if (waypoints && waypoints.length > 0) {
        for (const pt of waypoints) {
            path += ` L ${pt.x} ${pt.y}`;
        }
    }
    path += ` L ${targetX} ${targetY}`;
    return path;
};

// Diagram save configuration
const diagramName = ref(props.diagram.name);
const diagramDescription = ref(props.diagram.description || '');
const isSaving = ref(false);
const saveSuccess = ref(false);

const submitSave = () => {
    isSaving.value = true;
    router.put(`/diagrams/${props.diagram.id}`, {
        name: diagramName.value,
        description: diagramDescription.value,
        data: {
            nodes: nodes.value,
            edges: edges.value,
        }
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSaving.value = false;
        },
        onSuccess: () => {
            saveSuccess.value = true;
            setTimeout(() => {
                saveSuccess.value = false;
            }, 3000);
        }
    });
};

// Cable Cable Types configuration
interface CableType {
    name: string;
    color: string;
    label: string;
}

const cableTypes: Record<string, CableType> = {
    xlr: { name: 'xlr', color: '#8B5CF6', label: 'Audio XLR (Purple)' },
    audio_cat6: { name: 'audio_cat6', color: '#F59E0B', label: 'Audio Cat6 (Yellow)' },
    sdi: { name: 'sdi', color: '#EF4444', label: 'SDI Video (Red)' },
    hdmi: { name: 'hdmi', color: '#3B82F6', label: 'HDMI Video (Blue)' },
    video_cat6: { name: 'video_cat6', color: '#F97316', label: 'Video Cat6 (Orange)' },
    net_cat6: { name: 'net_cat6', color: '#10B981', label: 'Network Cat6 (Green)' },
    generic: { name: 'generic', color: '#6B7280', label: 'Generic (Gray)' },
};

const activeCableKey = ref('generic');
const activeCableColor = computed(() => cableTypes[activeCableKey.value].color);

// New Node setup
const newNodeLabel = ref('');
const newNodeType = ref('Mixer');
const newNodeShape = ref('rounded'); // rectangle, rounded, capsule, circle
const newNodeColor = ref('slate'); // slate, emerald, rose, sky, amber, violet
const newNodeInputs = ref(1);
const newNodeOutputs = ref(1);

watch(newNodeInputs, (val) => {
    if (typeof val !== 'number' || isNaN(val)) {
        newNodeInputs.value = 1;
    } else if (val < 1) {
        newNodeInputs.value = 1;
    } else if (val > 16) {
        newNodeInputs.value = 16;
    }
});

watch(newNodeOutputs, (val) => {
    if (typeof val !== 'number' || isNaN(val)) {
        newNodeOutputs.value = 1;
    } else if (val < 1) {
        newNodeOutputs.value = 1;
    } else if (val > 16) {
        newNodeOutputs.value = 16;
    }
});

watch(() => selectedNode.value?.data?.inputs, (val) => {
    if (!selectedNode.value) {
        return;
    }
    if (val === undefined || val === null || val === '') {
        return;
    }
    let num = parseInt(val as any);
    if (isNaN(num) || num < 1) {
        selectedNode.value.data.inputs = 1;
    } else if (num > 16) {
        selectedNode.value.data.inputs = 16;
    } else {
        selectedNode.value.data.inputs = num;
    }
});

watch(() => selectedNode.value?.data?.outputs, (val) => {
    if (!selectedNode.value) {
        return;
    }
    if (val === undefined || val === null || val === '') {
        return;
    }
    let num = parseInt(val as any);
    if (isNaN(num) || num < 1) {
        selectedNode.value.data.outputs = 1;
    } else if (num > 16) {
        selectedNode.value.data.outputs = 16;
    } else {
        selectedNode.value.data.outputs = num;
    }
});

// Selections
const selectedNodeId = ref<string | null>(null);
const selectedEdgeId = ref<string | null>(null);

const selectedNode = computed(() => {
    return nodes.value.find(n => n.id === selectedNodeId.value) || null;
});

const selectedEdge = computed(() => {
    return edges.value.find(e => e.id === selectedEdgeId.value) || null;
});

const onConnect = (params: any) => {
    const edgeId = `e-${Date.now()}`;
    const newEdge = {
        id: edgeId,
        source: params.source,
        target: params.target,
        sourceHandle: params.sourceHandle,
        targetHandle: params.targetHandle,
        type: 'custom',
        animated: true,
        style: { stroke: activeCableColor.value, strokeWidth: 3 },
        data: {
            cableType: activeCableKey.value,
            waypoints: [] as { x: number; y: number }[],
        }
    };
    edges.value.push(newEdge);
};

const onEdgeUpdate = (params: any) => {
    const edge = params.edge;
    const connection = params.connection || params.newConnection;
    if (!edge || !connection) {
        return;
    }
    
    const idx = edges.value.findIndex(e => e.id === edge.id);
    if (idx !== -1) {
        const oldEdge = edges.value[idx];
        const updatedEdge = {
            ...oldEdge,
            source: connection.source,
            target: connection.target,
            sourceHandle: connection.sourceHandle,
            targetHandle: connection.targetHandle,
        };
        edges.value[idx] = updatedEdge;
    }
};

// Node operations
const addNodeToCanvas = () => {
    const id = `node-${Date.now()}`;
    
    // Default label if empty
    const label = newNodeLabel.value || `${newNodeType.value || 'Node'} #${nodes.value.length + 1}`;

    const newNode = {
        id,
        type: 'custom',
        position: { x: 150 + Math.random() * 100, y: 150 + Math.random() * 100 },
        data: {
            label,
            typeLabel: newNodeType.value,
            shape: newNodeShape.value,
            color: newNodeColor.value,
            inputs: newNodeInputs.value || 1,
            outputs: newNodeOutputs.value || 1,
        }
    };

    nodes.value.push(newNode);
    newNodeLabel.value = '';
    newNodeInputs.value = 1;
    newNodeOutputs.value = 1;
};

// Selection hooks from Vue Flow
const onPaneClick = () => {
    selectedNodeId.value = null;
    selectedEdgeId.value = null;
};

const onNodeClick = (event: any) => {
    selectedNodeId.value = event.node.id;
    selectedEdgeId.value = null;
};

const onEdgeClick = (event: any) => {
    selectedEdgeId.value = event.edge.id;
    selectedNodeId.value = null;
};

const updateSelectedNodeProperties = () => {
    // Computed makes updates reactive inside nodes.value directly
};

const deleteSelectedNode = () => {
    if (!selectedNodeId.value) return;
    const nid = selectedNodeId.value;
    
    // Remove node
    nodes.value = nodes.value.filter(n => n.id !== nid);
    
    // Remove linked edges
    edges.value = edges.value.filter(e => e.source !== nid && e.target !== nid);
    
    selectedNodeId.value = null;
};

const deleteSelectedEdge = () => {
    if (!selectedEdgeId.value) return;
    edges.value = edges.value.filter(e => e.id !== selectedEdgeId.value);
    selectedEdgeId.value = null;
};

const updateSelectedEdgeCableType = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const key = target.value;
    if (selectedEdge.value && cableTypes[key]) {
        selectedEdge.value.data.cableType = key;
        selectedEdge.value.style = { stroke: cableTypes[key].color, strokeWidth: 3 };
    }
};

// CSS class helpers
const getNodeClasses = (data: any, isSelected: boolean) => {
    const classes = [];

    // Colors mapping
    if (data.color === 'slate') classes.push('bg-[#22273C] border-slate-700 text-white');
    else if (data.color === 'emerald') classes.push('bg-emerald-950 border-emerald-500 text-emerald-100');
    else if (data.color === 'rose') classes.push('bg-rose-950 border-rose-500 text-rose-100');
    else if (data.color === 'sky') classes.push('bg-sky-950 border-sky-500 text-sky-100');
    else if (data.color === 'amber') classes.push('bg-amber-950 border-amber-500 text-amber-100');
    else if (data.color === 'violet') classes.push('bg-violet-950 border-violet-500 text-violet-100');
    else classes.push('bg-slate-900 border-slate-700 text-white');

    // Shapes mapping
    if (data.shape === 'rectangle') classes.push('rounded-none');
    else if (data.shape === 'capsule') classes.push('rounded-full px-5 py-2');
    else if (data.shape === 'circle') classes.push('rounded-full aspect-square w-20 h-20 text-center justify-center');
    else classes.push('rounded-2xl'); // default 'rounded'

    if (isSelected) {
        classes.push('ring-2 ring-[#1AC18C] border-[#1AC18C]/80');
    }

    return classes.join(' ');
};
</script>

<template>
    <Head :title="`Editor: ${diagram.name}`" />

    <div class="h-screen flex flex-col bg-background font-sans overflow-hidden">
        <!-- Top Navigation Bar -->
        <header class="h-14 border-b border-border flex items-center justify-between px-4 shrink-0 bg-card z-10">
            <div class="flex items-center gap-3">
                <Link 
                    href="/diagrams"
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                >
                    <ArrowLeft class="size-4" />
                </Link>
                <div class="flex items-center gap-2">
                    <Network class="size-5 text-primary" />
                    <input 
                        v-model="diagramName" 
                        required
                        class="bg-transparent border-0 font-bold text-sm text-foreground focus:ring-1 focus:ring-[#1AC18C]/80 rounded-lg px-2 py-1 max-w-[240px] focus:outline-none"
                    />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button 
                    @click="submitSave"
                    :disabled="isSaving"
                    class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs h-8.5"
                >
                    <Check v-if="saveSuccess" class="mr-1 size-3.5" />
                    <Save v-else class="mr-1.5 size-3.5" />
                    {{ isSaving ? 'Saving...' : saveSuccess ? 'Blueprint Saved!' : 'Save Layout' }}
                </Button>
            </div>
        </header>

        <!-- Main Workspace Editor -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Canvas Space (Left) -->
            <div class="flex-1 h-full relative bg-slate-900/10 dark:bg-black/10">
                <VueFlow
                    v-model:nodes="nodes"
                    v-model:edges="edges"
                    :edges-updatable="true"
                    @connect="onConnect"
                    @edge-update="onEdgeUpdate"
                    @pane-click="onPaneClick"
                    @node-click="onNodeClick"
                    @edge-click="onEdgeClick"
                    fit-view-on-init
                    class="w-full h-full"
                >
                    <!-- Register Custom Node Template -->
                    <template #node-custom="{ data, selected }">
                        <div 
                            :class="[
                                getNodeClasses(data, selected),
                                'border border-border/80 p-3.5 shadow-xl flex flex-col justify-center items-center font-sans relative min-w-[125px] transition-all duration-200 cursor-grab active:cursor-grabbing text-center'
                            ]"
                        >
                            <!-- Inputs (Target Handles) -->
                            <Handle 
                                v-for="idx in (data.inputs || 1)" 
                                :key="`in-${idx}`"
                                :id="`in-${idx}`"
                                type="target" 
                                :position="Position.Left" 
                                :style="{ top: `${(idx * 100) / ((data.inputs || 1) + 1)}%` }"
                                class="size-2 bg-primary border-card" 
                            />
                            <!-- Outputs (Source Handles) -->
                            <Handle 
                                v-for="idx in (data.outputs || 1)" 
                                :key="`out-${idx}`"
                                :id="`out-${idx}`"
                                type="source" 
                                :position="Position.Right" 
                                :style="{ top: `${(idx * 100) / ((data.outputs || 1) + 1)}%` }"
                                class="size-2 bg-primary border-card" 
                            />
                            
                            <span class="text-[8px] uppercase tracking-wider font-bold opacity-60 block leading-none mb-1">{{ data.typeLabel }}</span>
                            <span class="font-bold text-xs leading-tight block break-words max-w-[140px]">{{ data.label }}</span>
                            <span class="text-[8px] font-bold opacity-60 mt-1 block leading-none">In: {{ data.inputs || 1 }} | Out: {{ data.outputs || 1 }}</span>
                        </div>
                    </template>

                    <!-- Register Custom Edge Template -->
                    <template #edge-custom="{ id, sourceX, sourceY, targetX, targetY, data, selected, style, markerEnd }">
                        <path
                            :id="id"
                            :class="['vue-flow__edge-path', { 'stroke-[#1AC18C]': selected }]"
                            :d="getCustomPath(sourceX, sourceY, targetX, targetY, data?.waypoints)"
                            :style="{
                                ...style,
                                stroke: selected ? '#1AC18C' : (style?.stroke || '#6B7280'),
                                strokeWidth: selected ? 4 : (style?.strokeWidth || 3)
                            }"
                            :marker-end="markerEnd"
                        />
                        <g v-if="selected && data?.waypoints">
                            <g
                                v-for="(pt, idx) in data.waypoints"
                                :key="idx"
                                class="waypoint-handle"
                                @mousedown="onWaypointMouseDown($event, id, idx)"
                            >
                                <!-- Large invisible click/drag area -->
                                <circle
                                    :cx="pt.x"
                                    :cy="pt.y"
                                    r="16"
                                    fill="transparent"
                                />
                                <!-- Small visible indicator dot -->
                                <circle
                                    :cx="pt.x"
                                    :cy="pt.y"
                                    r="6"
                                    fill="#1AC18C"
                                    stroke="#ffffff"
                                    stroke-width="1.5"
                                    style="pointer-events: none;"
                                />
                            </g>
                        </g>
                    </template>

                    <Background pattern-color="#8B949D" :gap="16" :size="1" />
                    <Controls />
                </VueFlow>

                <div class="absolute bottom-4 left-4 bg-card border border-border/60 p-2.5 rounded-xl text-[10px] text-muted-foreground max-w-[200px] flex items-start gap-1.5 shadow-md">
                    <Info class="size-3.5 text-primary shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold text-foreground block">Connection Help</span>
                        <span>Drag from a right handle (output) and drop on a left handle (input) to connect nodes.</span>
                    </div>
                </div>
            </div>

            <!-- Properties Sidebar Panel (Right) -->
            <aside class="w-76 border-l border-border bg-card h-full overflow-y-auto p-4 flex flex-col gap-5 shrink-0 z-10 shadow-sm">
                <!-- Section 1: Add Hardware Node -->
                <div class="space-y-3.5">
                    <h2 class="font-bold text-xs uppercase text-muted-foreground tracking-wider flex items-center gap-1">
                        <Plus class="size-4 text-primary" /> Add Node
                    </h2>
                    
                    <div class="space-y-3 p-3 bg-muted/20 border border-border/40 rounded-xl">
                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Node Label</Label>
                            <Input 
                                v-model="newNodeLabel" 
                                placeholder="e.g. Camera 1 (Left)"
                                class="rounded-lg text-xs h-8 bg-background"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Node Type / Device</Label>
                            <select 
                                v-model="newNodeType"
                                class="flex h-8 w-full rounded-lg border border-input bg-background px-2.5 py-1 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                            >
                                <option value="Mixer">Mixer / Console</option>
                                <option value="Speaker">Speaker / PA</option>
                                <option value="Camera">Camera</option>
                                <option value="Switcher">Switcher / Router</option>
                                <option value="Projector">Projector / Screen</option>
                                <option value="Switch">Network Switch</option>
                                <option value="Generic">Generic Hardware</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Form Shape</Label>
                            <div class="grid grid-cols-2 gap-1">
                                <Button 
                                    type="button" 
                                    size="sm"
                                    @click="newNodeShape = 'rounded'"
                                    :variant="newNodeShape === 'rounded' ? 'default' : 'outline'"
                                    class="text-[10px] h-7 px-2 rounded-lg cursor-pointer"
                                >
                                    Rounded
                                </Button>
                                <Button 
                                    type="button" 
                                    size="sm"
                                    @click="newNodeShape = 'rectangle'"
                                    :variant="newNodeShape === 'rectangle' ? 'default' : 'outline'"
                                    class="text-[10px] h-7 px-2 rounded-lg cursor-pointer"
                                >
                                    Rectangle
                                </Button>
                                <Button 
                                    type="button" 
                                    size="sm"
                                    @click="newNodeShape = 'capsule'"
                                    :variant="newNodeShape === 'capsule' ? 'default' : 'outline'"
                                    class="text-[10px] h-7 px-2 rounded-lg cursor-pointer"
                                >
                                    Capsule
                                </Button>
                                <Button 
                                    type="button" 
                                    size="sm"
                                    @click="newNodeShape = 'circle'"
                                    :variant="newNodeShape === 'circle' ? 'default' : 'outline'"
                                    class="text-[10px] h-7 px-2 rounded-lg cursor-pointer"
                                >
                                    Circle
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Node Color Theme</Label>
                            <div class="flex items-center gap-2">
                                <button 
                                    v-for="color in ['slate', 'emerald', 'rose', 'sky', 'amber', 'violet']" 
                                    :key="color"
                                    type="button"
                                    @click="newNodeColor = color"
                                    class="size-5 rounded-full border border-border cursor-pointer transition-transform relative flex items-center justify-center shrink-0"
                                    :class="{
                                        'bg-slate-700': color === 'slate',
                                        'bg-emerald-600': color === 'emerald',
                                        'bg-rose-600': color === 'rose',
                                        'bg-sky-600': color === 'sky',
                                        'bg-amber-600': color === 'amber',
                                        'bg-violet-600': color === 'violet',
                                        'scale-125 ring-2 ring-primary ring-offset-2': newNodeColor === color,
                                    }"
                                >
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Inputs (1-16)</Label>
                                <Input 
                                    type="number"
                                    v-model.number="newNodeInputs"
                                    min="1"
                                    max="16"
                                    class="rounded-lg text-xs h-8 bg-background font-mono"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Outputs (1-16)</Label>
                                <Input 
                                    type="number"
                                    v-model.number="newNodeOutputs"
                                    min="1"
                                    max="16"
                                    class="rounded-lg text-xs h-8 bg-background font-mono"
                                />
                            </div>
                        </div>

                        <Button 
                            type="button"
                            @click="addNodeToCanvas"
                            class="w-full bg-[#22273C] hover:bg-[#22273C]/90 text-white font-bold rounded-lg cursor-pointer text-xs h-8 mt-2"
                        >
                            Add to Canvas
                        </Button>
                    </div>
                </div>

                <!-- Section 2: Active Cable Tool -->
                <div class="space-y-3.5 border-t border-border/60 pt-4">
                    <h2 class="font-bold text-xs uppercase text-muted-foreground tracking-wider flex items-center gap-1.5">
                        <span class="size-2 rounded-full" :style="{ backgroundColor: activeCableColor }"></span>
                        Active Connection Cable
                    </h2>

                    <div class="space-y-2 p-3 bg-muted/20 border border-border/40 rounded-xl">
                        <select 
                            v-model="activeCableKey"
                            class="flex h-8 w-full rounded-lg border border-input bg-background px-2.5 py-1 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                        >
                            <option v-for="(type, key) in cableTypes" :key="key" :value="key">
                                {{ type.label }}
                            </option>
                        </select>
                        <span class="text-[9px] text-muted-foreground leading-snug block">
                            Connections dragged hereafter will draw lines matching this cable color format.
                        </span>
                    </div>
                </div>

                <!-- Section 3: Properties Panel of Selected Elements -->
                <div class="space-y-3.5 border-t border-border/60 pt-4 flex-1 flex flex-col">
                    <h2 class="font-bold text-xs uppercase text-muted-foreground tracking-wider">
                        Selection Settings
                    </h2>

                    <!-- A. Selected Node Properties -->
                    <div 
                        v-if="selectedNode" 
                        class="space-y-3 p-3 bg-[#1AC18C]/5 border border-[#1AC18C]/30 rounded-xl animate-in fade-in zoom-in-95 duration-150"
                    >
                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Rename Node</Label>
                            <Input 
                                v-model="selectedNode.data.label"
                                @input="updateSelectedNodeProperties"
                                class="rounded-lg text-xs h-8 bg-background"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Device Type</Label>
                            <select 
                                v-model="selectedNode.data.typeLabel"
                                class="flex h-8 w-full rounded-lg border border-input bg-background px-2.5 py-1 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                            >
                                <option value="Mixer">Mixer / Console</option>
                                <option value="Speaker">Speaker / PA</option>
                                <option value="Camera">Camera</option>
                                <option value="Switcher">Switcher / Router</option>
                                <option value="Projector">Projector / Screen</option>
                                <option value="Switch">Network Switch</option>
                                <option value="Generic">Generic Hardware</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Form Shape</Label>
                            <div class="grid grid-cols-2 gap-1">
                                <Button 
                                    type="button" 
                                    size="sm"
                                    @click="selectedNode.data.shape = 'rounded'"
                                    :variant="selectedNode.data.shape === 'rounded' ? 'default' : 'outline'"
                                    class="text-[9px] h-6 px-1 rounded cursor-pointer"
                                >
                                    Rounded
                                </Button>
                                <Button 
                                    type="button" 
                                    size="sm"
                                    @click="selectedNode.data.shape = 'rectangle'"
                                    :variant="selectedNode.data.shape === 'rectangle' ? 'default' : 'outline'"
                                    class="text-[9px] h-6 px-1 rounded cursor-pointer"
                                >
                                    Rectangle
                                </Button>
                                <Button 
                                    type="button" 
                                    size="sm"
                                    @click="selectedNode.data.shape = 'capsule'"
                                    :variant="selectedNode.data.shape === 'capsule' ? 'default' : 'outline'"
                                    class="text-[9px] h-6 px-1 rounded cursor-pointer"
                                >
                                    Capsule
                                </Button>
                                <Button 
                                    type="button" 
                                    size="sm"
                                    @click="selectedNode.data.shape = 'circle'"
                                    :variant="selectedNode.data.shape === 'circle' ? 'default' : 'outline'"
                                    class="text-[9px] h-6 px-1 rounded cursor-pointer"
                                >
                                    Circle
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Color Theme</Label>
                            <div class="flex items-center gap-2">
                                <button 
                                    v-for="color in ['slate', 'emerald', 'rose', 'sky', 'amber', 'violet']" 
                                    :key="color"
                                    type="button"
                                    @click="selectedNode.data.color = color"
                                    class="size-5 rounded-full border border-border cursor-pointer transition-transform relative flex items-center justify-center shrink-0"
                                    :class="{
                                        'bg-slate-700': color === 'slate',
                                        'bg-emerald-600': color === 'emerald',
                                        'bg-rose-600': color === 'rose',
                                        'bg-sky-600': color === 'sky',
                                        'bg-amber-600': color === 'amber',
                                        'bg-violet-600': color === 'violet',
                                        'scale-125 ring-2 ring-primary ring-offset-2': selectedNode.data.color === color,
                                    }"
                                >
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Inputs (1-16)</Label>
                                <Input 
                                    type="number"
                                    v-model.number="selectedNode.data.inputs"
                                    min="1"
                                    max="16"
                                    class="rounded-lg text-xs h-8 bg-background font-mono"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[10px] uppercase font-bold text-muted-foreground">Outputs (1-16)</Label>
                                <Input 
                                    type="number"
                                    v-model.number="selectedNode.data.outputs"
                                    min="1"
                                    max="16"
                                    class="rounded-lg text-xs h-8 bg-background font-mono"
                                />
                            </div>
                        </div>

                        <Button 
                            type="button"
                            @click="deleteSelectedNode"
                            class="w-full bg-red-500/10 hover:bg-red-500/20 text-red-500 font-bold rounded-lg cursor-pointer text-xs h-8 mt-2"
                        >
                            <Trash2 class="mr-1.5 size-3.5" /> Delete Node
                        </Button>
                    </div>

                    <!-- B. Selected Connection (Edge) Properties -->
                    <div 
                        v-else-if="selectedEdge"
                        class="space-y-3 p-3 bg-[#1AC18C]/5 border border-[#1AC18C]/30 rounded-xl animate-in fade-in zoom-in-95 duration-150"
                    >
                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Cable Connection Type</Label>
                            <select 
                                :value="selectedEdge.data?.cableType || 'generic'"
                                @change="updateSelectedEdgeCableType"
                                class="flex h-8 w-full rounded-lg border border-input bg-background px-2.5 py-1 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                            >
                                <option v-for="(type, key) in cableTypes" :key="key" :value="key">
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Waypoint Manager -->
                        <div class="space-y-2 border-t border-border/60 pt-3 mt-3">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground block">Waypoints / Path Points</Label>
                            
                            <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                                <div 
                                    v-for="(pt, idx) in (selectedEdge.data?.waypoints || [])" 
                                    :key="idx"
                                    class="flex items-center gap-1 bg-background p-1.5 rounded border border-border/60"
                                >
                                    <span class="text-[9px] font-bold font-mono text-muted-foreground w-4">#{{ idx + 1 }}</span>
                                    <div class="flex items-center gap-1 flex-1">
                                        <input 
                                            type="number"
                                            v-model.number="pt.x"
                                            class="w-full text-[10px] h-6 bg-muted px-1.5 rounded border-0 focus:ring-1 focus:ring-[#1AC18C]/80 font-mono"
                                            placeholder="X"
                                        />
                                        <input 
                                            type="number"
                                            v-model.number="pt.y"
                                            class="w-full text-[10px] h-6 bg-muted px-1.5 rounded border-0 focus:ring-1 focus:ring-[#1AC18C]/80 font-mono"
                                            placeholder="Y"
                                        />
                                    </div>
                                    <Button 
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        @click="deleteWaypoint(idx)"
                                        class="h-6 w-6 p-0 text-red-500 hover:text-red-600 cursor-pointer"
                                    >
                                        <Trash2 class="size-3" />
                                    </Button>
                                </div>
                            </div>

                            <div class="flex gap-1.5 mt-2">
                                <Button 
                                    type="button"
                                    @click="addWaypoint"
                                    class="flex-1 bg-[#22273C] hover:bg-[#22273C]/90 text-white font-bold rounded-lg cursor-pointer text-[10px] h-7"
                                >
                                    <Plus class="mr-1 size-3" /> Add Point
                                </Button>
                            </div>
                        </div>

                        <Button 
                            type="button"
                            @click="deleteSelectedEdge"
                            class="w-full bg-red-500/10 hover:bg-red-500/20 text-red-500 font-bold rounded-lg cursor-pointer text-xs h-8 mt-2"
                        >
                            <Trash2 class="mr-1.5 size-3.5" /> Delete Cable
                        </Button>
                    </div>

                    <!-- C. Placeholder (None Selected) -->
                    <div 
                        v-else 
                        class="flex-1 flex flex-col items-center justify-center p-6 text-center border border-dashed border-border/80 rounded-xl bg-muted/5 min-h-[140px]"
                    >
                        <HelpCircle class="size-6 text-muted-foreground/60 mb-2" />
                        <span class="text-[10px] text-muted-foreground leading-normal block">
                            Click a node or cable line on the canvas to configure properties or delete it.
                        </span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

<style>
/* Inject core styling in root view namespace */
.vue-flow__node-custom {
    padding: 0;
    border: none;
    background: transparent;
}
.vue-flow__edge.custom {
    pointer-events: all !important;
}
.vue-flow__edge-path {
    stroke-dasharray: 4;
    animation: dash 15s linear infinite;
    pointer-events: stroke !important;
}
@keyframes dash {
    from {
        stroke-dashoffset: 200;
    }
    to {
        stroke-dashoffset: 0;
    }
}
.waypoint-handle {
    pointer-events: all !important;
    cursor: move !important;
}
.waypoint-handle circle:last-child {
    r: 6px;
    transition: r 0.15s ease-in-out;
}
.waypoint-handle:hover circle:last-child {
    r: 8.5px;
}
</style>
