<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    Check,
    Download,
    Upload,
    Table
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

const page = usePage();
const userRole = computed(() => (page.props.auth as any).currentChurch?.pivot?.role || 'User');
const isReadOnly = computed(() => userRole.value === 'User');

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
            ensurePortDetailsInitialized(node);
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
    if (isReadOnly.value) return;
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
    if (isReadOnly.value) return;
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
    usb: { name: 'usb', color: '#06B6D4', label: 'USB (Cyan)' },
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

// Detailed I/O Table Modal State & Logic
const showIoModal = ref(false);

const ensurePortDetailsInitialized = (node: any) => {
    if (!node) return;
    if (!node.data) {
        node.data = {};
    }
    if (!node.data.portDetails) {
        node.data.portDetails = {
            inputs: {},
            outputs: {}
        };
    }
    if (!node.data.portDetails.inputs) {
        node.data.portDetails.inputs = {};
    }
    if (!node.data.portDetails.outputs) {
        node.data.portDetails.outputs = {};
    }
    
    const numInputs = node.data.inputs || 1;
    for (let i = 1; i <= numInputs; i++) {
        const key = `in-${i}`;
        if (!node.data.portDetails.inputs[key]) {
            node.data.portDetails.inputs[key] = { name: '', description: '' };
        }
    }
    
    const numOutputs = node.data.outputs || 1;
    for (let i = 1; i <= numOutputs; i++) {
        const key = `out-${i}`;
        if (!node.data.portDetails.outputs[key]) {
            node.data.portDetails.outputs[key] = { name: '', description: '' };
        }
    }
};

const getPortConnectionInfo = (nodeId: string, type: 'in' | 'out', portIndex: number) => {
    const handleId = `${type}-${portIndex}`;
    
    if (type === 'in') {
        const edge = edges.value.find(e => e.target === nodeId && e.targetHandle === handleId);
        if (!edge) return null;
        
        const sourceNode = nodes.value.find(n => n.id === edge.source);
        const sourceNodeLabel = sourceNode ? sourceNode.data.label : 'Unknown Device';
        
        let sourcePortDesc = '';
        if (edge.sourceHandle) {
            const match = edge.sourceHandle.match(/out-(\d+)/);
            if (match) {
                sourcePortDesc = `Output #${match[1]}`;
            } else {
                sourcePortDesc = edge.sourceHandle;
            }
        }
        
        const cableKey = edge.data?.cableType || 'generic';
        const cableLabel = cableTypes[cableKey]?.label || 'Generic';
        
        return {
            description: `From: ${sourceNodeLabel} (${sourcePortDesc})`,
            cable: cableLabel,
            color: cableTypes[cableKey]?.color || '#6B7280'
        };
    } else {
        const edge = edges.value.find(e => e.source === nodeId && e.sourceHandle === handleId);
        if (!edge) return null;
        
        const targetNode = nodes.value.find(n => n.id === edge.target);
        const targetNodeLabel = targetNode ? targetNode.data.label : 'Unknown Device';
        
        let targetPortDesc = '';
        if (edge.targetHandle) {
            const match = edge.targetHandle.match(/in-(\d+)/);
            if (match) {
                targetPortDesc = `Input #${match[1]}`;
            } else {
                targetPortDesc = edge.targetHandle;
            }
        }
        
        const cableKey = edge.data?.cableType || 'generic';
        const cableLabel = cableTypes[cableKey]?.label || 'Generic';
        
        return {
            description: `To: ${targetNodeLabel} (${targetPortDesc})`,
            cable: cableLabel,
            color: cableTypes[cableKey]?.color || '#6B7280'
        };
    }
};

const openIoModal = () => {
    if (selectedNode.value) {
        ensurePortDetailsInitialized(selectedNode.value);
        showIoModal.value = true;
    }
};

const getSourceNodeOutputsCount = (sourceId: string) => {
    const node = nodes.value.find(n => n.id === sourceId);
    return node ? (node.data.outputs || 1) : 1;
};

const getTargetNodeInputsCount = (targetId: string) => {
    const node = nodes.value.find(n => n.id === targetId);
    return node ? (node.data.inputs || 1) : 1;
};

const getPortName = (nodeId: string, type: 'in' | 'out', portIndex: number) => {
    const node = nodes.value.find(n => n.id === nodeId);
    if (!node || !node.data.portDetails) return '';
    const key = `${type}-${portIndex}`;
    const details = type === 'in' 
        ? node.data.portDetails.inputs?.[key] 
        : node.data.portDetails.outputs?.[key];
    return details && details.name ? `(${details.name})` : '';
};

const updateEdgeSource = (event: Event) => {
    if (isReadOnly.value || !selectedEdge.value) return;
    const newSource = (event.target as HTMLSelectElement).value;
    selectedEdge.value.source = newSource;
    selectedEdge.value.sourceHandle = 'out-1';
};

const updateEdgeSourceHandle = (event: Event) => {
    if (isReadOnly.value || !selectedEdge.value) return;
    selectedEdge.value.sourceHandle = (event.target as HTMLSelectElement).value;
};

const updateEdgeTarget = (event: Event) => {
    if (isReadOnly.value || !selectedEdge.value) return;
    const newTarget = (event.target as HTMLSelectElement).value;
    selectedEdge.value.target = newTarget;
    selectedEdge.value.targetHandle = 'in-1';
};

const updateEdgeTargetHandle = (event: Event) => {
    if (isReadOnly.value || !selectedEdge.value) return;
    selectedEdge.value.targetHandle = (event.target as HTMLSelectElement).value;
};

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
    if (isReadOnly.value) return;
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
    if (event.node) {
        ensurePortDetailsInitialized(event.node);
    }
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

// Blueprint Export / Import utilities
const exportBlueprint = () => {
    const exportData = {
        name: diagramName.value,
        description: diagramDescription.value,
        data: {
            nodes: nodes.value,
            edges: edges.value
        }
    };
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exportData, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", `${diagramName.value || 'blueprint'}_export.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
};

const triggerBlueprintImport = () => {
    const fileInput = document.getElementById('import-blueprint-file');
    if (fileInput) fileInput.click();
};

const handleBlueprintImport = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    const reader = new FileReader();

    reader.onload = (e) => {
        try {
            const jsonText = e.target?.result as string;
            const imported = JSON.parse(jsonText);

            if (!imported.data || !Array.isArray(imported.data.nodes) || !Array.isArray(imported.data.edges)) {
                alert('Invalid blueprint JSON structure. Missing nodes or edges.');
                return;
            }

            if (confirm('Do you want to import this blueprint? This will replace your current technical layout.')) {
                if (imported.name) diagramName.value = imported.name;
                if (imported.description !== undefined) diagramDescription.value = imported.description || '';
                
                nodes.value = imported.data.nodes.map((node: any) => {
                    if (!node.data) node.data = {};
                    if (node.data.inputs === undefined) node.data.inputs = 1;
                    if (node.data.outputs === undefined) node.data.outputs = 1;
                    return node;
                });

                edges.value = imported.data.edges.map((edge: any) => {
                    if (edge.targetHandle === 'left') edge.targetHandle = 'in-1';
                    if (edge.sourceHandle === 'right') edge.sourceHandle = 'out-1';
                    edge.type = 'custom';
                    if (!edge.data) edge.data = {};
                    if (!edge.data.waypoints) edge.data.waypoints = [];
                    return edge;
                });
            }
        } catch (err) {
            console.error(err);
            alert('Failed to parse blueprint JSON file.');
        }
    };

    reader.readAsText(file);
    input.value = '';
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
                <!-- Import Blueprint Trigger -->
                <input 
                    type="file" 
                    id="import-blueprint-file" 
                    accept=".json" 
                    class="hidden" 
                    @change="handleBlueprintImport"
                />
                <Button 
                    v-if="!isReadOnly"
                    @click="triggerBlueprintImport" 
                    variant="outline"
                    class="rounded-xl text-xs h-8.5 border-border/60 hover:bg-muted/40 cursor-pointer"
                    title="Import Blueprint Layout JSON"
                >
                    <Upload class="size-3.5 mr-1" /> Import
                </Button>

                <Button 
                    @click="exportBlueprint" 
                    variant="outline"
                    class="rounded-xl text-xs h-8.5 border-border/60 hover:bg-muted/40 cursor-pointer mr-2"
                    title="Export Blueprint Layout JSON"
                >
                    <Download class="size-3.5 mr-1" /> Export
                </Button>

                <Button 
                    v-if="!isReadOnly"
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
                    :edges-updatable="!isReadOnly"
                    :nodes-draggable="!isReadOnly"
                    :nodes-connectable="!isReadOnly"
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
                                'border border-border/80 p-3.5 shadow-xl flex flex-col justify-center items-center font-sans relative min-w-[200px] transition-all duration-200 cursor-grab active:cursor-grabbing text-center'
                            ]"
                            :style="{
                                height: `${Math.max(80, (Math.max(data.inputs || 1, data.outputs || 1) + 1) * 24)}px`
                            }"
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
                            <!-- Input Port Names -->
                            <span 
                                v-for="idx in (data.inputs || 1)"
                                :key="`in-label-${idx}`"
                                :style="{ top: `${(idx * 100) / ((data.inputs || 1) + 1)}%` }"
                                class="absolute left-3.5 transform -translate-y-1/2 text-[8px] font-mono text-muted-foreground/80 pointer-events-none whitespace-nowrap overflow-hidden max-w-[70px] text-left"
                                :title="data.portDetails?.inputs?.[`in-${idx}`]?.name || ''"
                            >
                                {{ data.portDetails?.inputs?.[`in-${idx}`]?.name || '' }}
                            </span>

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
                            <!-- Output Port Names -->
                            <span 
                                v-for="idx in (data.outputs || 1)"
                                :key="`out-label-${idx}`"
                                :style="{ top: `${(idx * 100) / ((data.outputs || 1) + 1)}%` }"
                                class="absolute right-3.5 transform -translate-y-1/2 text-[8px] font-mono text-muted-foreground/80 pointer-events-none whitespace-nowrap overflow-hidden max-w-[70px] text-right"
                                :title="data.portDetails?.outputs?.[`out-${idx}`]?.name || ''"
                            >
                                {{ data.portDetails?.outputs?.[`out-${idx}`]?.name || '' }}
                            </span>
                            
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
                        <path
                            :d="getCustomPath(sourceX, sourceY, targetX, targetY, data?.waypoints)"
                            fill="none"
                            stroke="transparent"
                            stroke-width="16"
                            class="vue-flow__edge-interaction"
                            style="pointer-events: stroke !important; cursor: pointer;"
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
                <div v-if="!isReadOnly" class="space-y-3.5">
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
                <div v-if="!isReadOnly" class="space-y-3.5 border-t border-border/60 pt-4">
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
                                :disabled="isReadOnly"
                                v-model="selectedNode.data.label"
                                @input="updateSelectedNodeProperties"
                                class="rounded-lg text-xs h-8 bg-background"
                            />
                        </div>

                        <div class="space-y-1">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground">Device Type</Label>
                            <select 
                                :disabled="isReadOnly"
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

                        <div class="space-y-1" v-if="!isReadOnly">
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

                        <div class="space-y-1" v-if="!isReadOnly">
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
                                    :disabled="isReadOnly"
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
                                    :disabled="isReadOnly"
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
                            @click="openIoModal"
                            class="w-full bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-lg cursor-pointer text-xs h-8 mt-4"
                        >
                            <Table class="mr-1.5 size-3.5" /> {{ isReadOnly ? 'View I/O Table' : 'Configure I/O Table' }}
                        </Button>

                        <Button 
                            v-if="!isReadOnly"
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
                                :disabled="isReadOnly"
                                :value="selectedEdge.data?.cableType || 'generic'"
                                @change="updateSelectedEdgeCableType"
                                class="flex h-8 w-full rounded-lg border border-input bg-background px-2.5 py-1 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                            >
                                <option v-for="(type, key) in cableTypes" :key="key" :value="key">
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Connection Ends Configurator -->
                        <div class="space-y-3 border-t border-border/60 pt-3 mt-3">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground block">Source Endpoint</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1">
                                    <Label class="text-[9px] text-muted-foreground">Device</Label>
                                    <select 
                                        :disabled="isReadOnly"
                                        :value="selectedEdge.source" 
                                        @change="updateEdgeSource"
                                        class="flex h-7 w-full rounded-md border border-input bg-background px-2 py-0.5 text-[10px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                                    >
                                        <option v-for="node in nodes" :key="node.id" :value="node.id">
                                            {{ node.data.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-[9px] text-muted-foreground">Port</Label>
                                    <select 
                                        :disabled="isReadOnly"
                                        :value="selectedEdge.sourceHandle" 
                                        @change="updateEdgeSourceHandle"
                                        class="flex h-7 w-full rounded-md border border-input bg-background px-2 py-0.5 text-[10px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                                    >
                                        <option v-for="idx in getSourceNodeOutputsCount(selectedEdge.source)" :key="idx" :value="`out-${idx}`">
                                            Out #{{ idx }} {{ getPortName(selectedEdge.source, 'out', idx) }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 border-t border-border/60 pt-3 mt-3">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground block">Target Endpoint</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1">
                                    <Label class="text-[9px] text-muted-foreground">Device</Label>
                                    <select 
                                        :disabled="isReadOnly"
                                        :value="selectedEdge.target" 
                                        @change="updateEdgeTarget"
                                        class="flex h-7 w-full rounded-md border border-input bg-background px-2 py-0.5 text-[10px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                                    >
                                        <option v-for="node in nodes" :key="node.id" :value="node.id">
                                            {{ node.data.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-[9px] text-muted-foreground">Port</Label>
                                    <select 
                                        :disabled="isReadOnly"
                                        :value="selectedEdge.targetHandle" 
                                        @change="updateEdgeTargetHandle"
                                        class="flex h-7 w-full rounded-md border border-input bg-background px-2 py-0.5 text-[10px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                                    >
                                        <option v-for="idx in getTargetNodeInputsCount(selectedEdge.target)" :key="idx" :value="`in-${idx}`">
                                            In #{{ idx }} {{ getPortName(selectedEdge.target, 'in', idx) }}
                                        </option>
                                    </select>
                                </div>
                            </div>
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
                                            :disabled="isReadOnly"
                                            type="number"
                                            v-model.number="pt.x"
                                            class="w-full text-[10px] h-6 bg-muted px-1.5 rounded border-0 focus:ring-1 focus:ring-[#1AC18C]/80 font-mono"
                                            placeholder="X"
                                        />
                                        <input 
                                            :disabled="isReadOnly"
                                            type="number"
                                            v-model.number="pt.y"
                                            class="w-full text-[10px] h-6 bg-muted px-1.5 rounded border-0 focus:ring-1 focus:ring-[#1AC18C]/80 font-mono"
                                            placeholder="Y"
                                        />
                                    </div>
                                    <Button 
                                        v-if="!isReadOnly"
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

                            <div class="flex gap-1.5 mt-2" v-if="!isReadOnly">
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
                            v-if="!isReadOnly"
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

        <!-- MODAL: Detailed Input-Output Mapping Table -->
        <div v-if="showIoModal && selectedNode" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-4xl max-h-[85vh] shadow-2xl flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between bg-muted/20">
                    <div class="flex items-center gap-2">
                        <Table class="size-5 text-primary" />
                        <div>
                            <h3 class="font-bold text-base text-foreground">Device I/O Table</h3>
                            <p class="text-[10px] text-muted-foreground mt-0.5">
                                Map port names and descriptions. Connection routes are automatically resolved from the diagram canvas.
                            </p>
                        </div>
                    </div>
                    <button @click="showIoModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <div class="flex items-center justify-between bg-secondary/20 border border-border/40 p-4 rounded-xl">
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground block">Active Device</span>
                            <span class="text-sm font-bold text-foreground">{{ selectedNode.data.label }}</span>
                        </div>
                        <div class="flex gap-4 text-right">
                            <div>
                                <span class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground block">Input Ports</span>
                                <span class="text-xs font-mono font-bold text-foreground">{{ selectedNode.data.inputs || 1 }} Configured</span>
                            </div>
                            <div>
                                <span class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground block">Output Ports</span>
                                <span class="text-xs font-mono font-bold text-foreground">{{ selectedNode.data.outputs || 1 }} Configured</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6 items-start">
                        <!-- Left Column: Inputs -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-1.5 pb-2 border-b border-border/40">
                                <span class="size-2 rounded-full bg-blue-500"></span>
                                <h4 class="font-bold text-xs text-foreground uppercase tracking-wider">Device Inputs</h4>
                            </div>

                            <div class="space-y-3">
                                <div 
                                    v-for="idx in (selectedNode.data.inputs || 1)" 
                                    :key="`in-${idx}`"
                                    class="p-4 rounded-xl border border-border/60 bg-muted/10 space-y-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-mono font-bold text-blue-500 bg-blue-500/10 px-2 py-0.5 rounded-full">
                                            Input #{{ idx }}
                                        </span>
                                        
                                        <!-- Automatic Connection Type status -->
                                        <div v-if="getPortConnectionInfo(selectedNode.id, 'in', idx)" class="text-[10px] text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-full font-semibold flex items-center gap-1">
                                            <span class="size-1.5 rounded-full" :style="{ backgroundColor: getPortConnectionInfo(selectedNode.id, 'in', idx).color }"></span>
                                            {{ getPortConnectionInfo(selectedNode.id, 'in', idx).description }}
                                            <span class="opacity-75 font-mono text-[9px]">({{ getPortConnectionInfo(selectedNode.id, 'in', idx).cable }})</span>
                                        </div>
                                        <div v-else class="text-[10px] text-muted-foreground italic bg-muted/40 px-2 py-0.5 rounded-full">
                                            Unconnected
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <div class="space-y-1">
                                            <Label :for="`in-${idx}-name`" class="text-[9px] uppercase font-bold text-muted-foreground">Port Name</Label>
                                            <Input 
                                                :id="`in-${idx}-name`"
                                                v-model="selectedNode.data.portDetails.inputs[`in-${idx}`].name"
                                                :disabled="isReadOnly"
                                                placeholder="e.g. Stage Left Vocal Mic"
                                                class="h-8 rounded-lg text-xs"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <Label :for="`in-${idx}-desc`" class="text-[9px] uppercase font-bold text-muted-foreground">Description / Notes</Label>
                                            <textarea 
                                                :id="`in-${idx}-desc`"
                                                v-model="selectedNode.data.portDetails.inputs[`in-${idx}`].description"
                                                :disabled="isReadOnly"
                                                placeholder="e.g. Wireless Handheld SM58 transmitter Channel 1..."
                                                rows="2"
                                                class="flex w-full rounded-lg border border-input bg-background px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Outputs -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-1.5 pb-2 border-b border-border/40">
                                <span class="size-2 rounded-full bg-orange-500"></span>
                                <h4 class="font-bold text-xs text-foreground uppercase tracking-wider">Device Outputs</h4>
                            </div>

                            <div class="space-y-3">
                                <div 
                                    v-for="idx in (selectedNode.data.outputs || 1)" 
                                    :key="`out-${idx}`"
                                    class="p-4 rounded-xl border border-border/60 bg-muted/10 space-y-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-mono font-bold text-orange-500 bg-orange-500/10 px-2 py-0.5 rounded-full">
                                            Output #{{ idx }}
                                        </span>
                                        
                                        <!-- Automatic Connection Type status -->
                                        <div v-if="getPortConnectionInfo(selectedNode.id, 'out', idx)" class="text-[10px] text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-full font-semibold flex items-center gap-1">
                                            <span class="size-1.5 rounded-full" :style="{ backgroundColor: getPortConnectionInfo(selectedNode.id, 'out', idx).color }"></span>
                                            {{ getPortConnectionInfo(selectedNode.id, 'out', idx).description }}
                                            <span class="opacity-75 font-mono text-[9px]">({{ getPortConnectionInfo(selectedNode.id, 'out', idx).cable }})</span>
                                        </div>
                                        <div v-else class="text-[10px] text-muted-foreground italic bg-muted/40 px-2 py-0.5 rounded-full">
                                            Unconnected
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <div class="space-y-1">
                                            <Label :for="`out-${idx}-name`" class="text-[9px] uppercase font-bold text-muted-foreground">Port Name</Label>
                                            <Input 
                                                :id="`out-${idx}-name`"
                                                v-model="selectedNode.data.portDetails.outputs[`out-${idx}`].name"
                                                :disabled="isReadOnly"
                                                placeholder="e.g. Left PA Main Amp XLR"
                                                class="h-8 rounded-lg text-xs"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <Label :for="`out-${idx}-desc`" class="text-[9px] uppercase font-bold text-muted-foreground">Description / Notes</Label>
                                            <textarea 
                                                :id="`out-${idx}-desc`"
                                                v-model="selectedNode.data.portDetails.outputs[`out-${idx}`].description"
                                                :disabled="isReadOnly"
                                                placeholder="e.g. Line out routed to left amp rack FOH array..."
                                                rows="2"
                                                class="flex w-full rounded-lg border border-input bg-background px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]/80"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-border/40 flex justify-end bg-muted/20">
                    <Button 
                        type="button" 
                        @click="showIoModal = false" 
                        class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl text-xs h-9 px-6 cursor-pointer"
                    >
                        Done
                    </Button>
                </div>
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
    pointer-events: none !important;
}
.vue-flow__edge-interaction {
    pointer-events: stroke !important;
    cursor: pointer;
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
