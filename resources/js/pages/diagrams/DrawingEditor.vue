<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    ArrowLeft, 
    Save, 
    Trash2, 
    Check,
    MousePointer,
    Type,
    Square,
    Circle,
    ChevronRight,
    Palette,
    Layers,
    Copy,
    Settings,
    Download
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Diagram {
    id: number;
    name: string;
    description: string | null;
    data: {
        elements: DrawingElement[];
        canvas: {
            width: number;
            height: number;
            background: string;
            showGrid: boolean;
        };
    } | null;
}

interface DrawingElement {
    id: string;
    type: 'rectangle' | 'circle' | 'triangle' | 'star' | 'text' | 'line';
    x: number;
    y: number;
    width: number;
    height: number;
    text?: string;
    fillColor: string;
    strokeColor: string;
    strokeWidth: number;
    strokeStyle: 'solid' | 'dashed';
    textColor: string;
    fontSize: number;
    fontWeight: 'normal' | 'bold';
    fontStyle: 'normal' | 'italic';
    textAlignment: 'left' | 'center' | 'right';
    // Line specific:
    x2?: number;
    y2?: number;
    lineEnd?: 'none' | 'arrow';
}

const props = defineProps<{
    diagram: Diagram;
}>();

const elements = ref<DrawingElement[]>([]);
const canvasWidth = ref(1200);
const canvasHeight = ref(800);
const canvasBackground = ref('#ffffff');
const showGrid = ref(true);
const snapToGrid = ref(true);

const selectedElementId = ref<string | null>(null);
const activeTool = ref<'select' | 'rectangle' | 'circle' | 'triangle' | 'star' | 'text' | 'line'>('select');
const textEditElementId = ref<string | null>(null);
const textEditInput = ref('');

// Load saved data
onMounted(() => {
    if (props.diagram.data) {
        elements.value = props.diagram.data.elements || [];
        if (props.diagram.data.canvas) {
            canvasWidth.value = props.diagram.data.canvas.width || 1200;
            canvasHeight.value = props.diagram.data.canvas.height || 800;
            canvasBackground.value = props.diagram.data.canvas.background || '#ffffff';
            showGrid.value = props.diagram.data.canvas.showGrid !== false;
        }
    }
});

const selectedElement = computed(() => {
    return elements.value.find(el => el.id === selectedElementId.value) || null;
});

// Save drawing settings
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
            elements: elements.value,
            canvas: {
                width: canvasWidth.value,
                height: canvasHeight.value,
                background: canvasBackground.value,
                showGrid: showGrid.value,
            }
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

// Add Element helper
const addElement = (type: DrawingElement['type']) => {
    const id = 'element_' + Math.random().toString(36).substr(2, 9);
    let newEl: DrawingElement = {
        id,
        type,
        x: 100,
        y: 100,
        width: type === 'line' ? 200 : 150,
        height: type === 'line' ? 100 : 100,
        fillColor: type === 'text' ? 'transparent' : '#1AC18C',
        strokeColor: '#22273C',
        strokeWidth: 2,
        strokeStyle: 'solid',
        textColor: '#1E293B',
        fontSize: 14,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlignment: 'center',
    };

    if (type === 'text') {
        newEl.text = 'Double-click to edit';
        newEl.textColor = '#22273C';
        newEl.textAlignment = 'left';
    } else if (type === 'line') {
        newEl.x2 = 300;
        newEl.y2 = 100;
        newEl.lineEnd = 'arrow';
    } else {
        newEl.text = ''; // shapes can have text inside
    }

    elements.value.push(newEl);
    selectedElementId.value = id;
    activeTool.value = 'select';
};

// Mouse Drag and Resize interactions
let isDragging = false;
let isResizing = false;
let resizeDirection = ''; // tl, tr, bl, br, start, end
let dragStartX = 0;
let dragStartY = 0;
let elementStartX = 0;
let elementStartY = 0;
let elementStartWidth = 0;
let elementStartHeight = 0;
let elementStartLineX2 = 0;
let elementStartLineY2 = 0;

const startDrag = (event: MouseEvent, el: DrawingElement) => {
    if (activeTool.value !== 'select' || textEditElementId.value === el.id) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    
    selectedElementId.value = el.id;
    isDragging = true;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    elementStartX = el.x;
    elementStartY = el.y;

    if (el.type === 'line') {
        elementStartLineX2 = el.x2 || el.x;
        elementStartLineY2 = el.y2 || el.y;
    }

    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', handleMouseUp);
};

const startResize = (event: MouseEvent, direction: string, el: DrawingElement) => {
    event.preventDefault();
    event.stopPropagation();

    isResizing = true;
    resizeDirection = direction;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    elementStartX = el.x;
    elementStartY = el.y;
    elementStartWidth = el.width;
    elementStartHeight = el.height;

    if (el.type === 'line') {
        elementStartLineX2 = el.x2 || el.x;
        elementStartLineY2 = el.y2 || el.y;
    }

    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', handleMouseUp);
};

const handleMouseMove = (event: MouseEvent) => {
    if (!selectedElement.value) return;
    
    const deltaX = event.clientX - dragStartX;
    const deltaY = event.clientY - dragStartY;
    const gridVal = snapToGrid.value ? 10 : 1;

    const snap = (val: number) => Math.round(val / gridVal) * gridVal;

    if (isDragging) {
        selectedElement.value.x = snap(elementStartX + deltaX);
        selectedElement.value.y = snap(elementStartY + deltaY);

        if (selectedElement.value.type === 'line') {
            selectedElement.value.x2 = snap(elementStartLineX2 + deltaX);
            selectedElement.value.y2 = snap(elementStartLineY2 + deltaY);
        }
    } else if (isResizing) {
        const el = selectedElement.value;
        if (el.type === 'line') {
            if (resizeDirection === 'start') {
                el.x = snap(elementStartX + deltaX);
                el.y = snap(elementStartY + deltaY);
            } else if (resizeDirection === 'end') {
                el.x2 = snap(elementStartLineX2 + deltaX);
                el.y2 = snap(elementStartLineY2 + deltaY);
            }
        } else {
            if (resizeDirection.includes('r')) {
                el.width = Math.max(20, snap(elementStartWidth + deltaX));
            }
            if (resizeDirection.includes('b')) {
                el.height = Math.max(20, snap(elementStartHeight + deltaY));
            }
            if (resizeDirection.includes('l')) {
                const calculatedWidth = elementStartWidth - deltaX;
                if (calculatedWidth > 20) {
                    el.x = snap(elementStartX + deltaX);
                    el.width = snap(calculatedWidth);
                }
            }
            if (resizeDirection.includes('t')) {
                const calculatedHeight = elementStartHeight - deltaY;
                if (calculatedHeight > 20) {
                    el.y = snap(elementStartY + deltaY);
                    el.height = snap(calculatedHeight);
                }
            }
        }
    }
};

const handleMouseUp = () => {
    isDragging = false;
    isResizing = false;
    resizeDirection = '';
    window.removeEventListener('mousemove', handleMouseMove);
    window.removeEventListener('mouseup', handleMouseUp);
};

// Text Inline Editing
const enableTextEdit = (el: DrawingElement) => {
    selectedElementId.value = el.id;
    textEditElementId.value = el.id;
    textEditInput.value = el.text || '';
    nextTick(() => {
        const field = document.getElementById('text-editor-field');
        if (field) field.focus();
    });
};

const saveTextEdit = () => {
    if (textEditElementId.value !== null) {
        const el = elements.value.find(e => e.id === textEditElementId.value);
        if (el) {
            el.text = textEditInput.value;
        }
        textEditElementId.value = null;
    }
};

// Layer Depth Controls
const moveLayer = (action: 'front' | 'back' | 'forward' | 'backward') => {
    if (!selectedElementId.value) return;
    const idx = elements.value.findIndex(e => e.id === selectedElementId.value);
    if (idx === -1) return;

    const el = elements.value[idx];
    elements.value.splice(idx, 1);

    if (action === 'front') {
        elements.value.push(el);
    } else if (action === 'back') {
        elements.value.unshift(el);
    } else if (action === 'forward') {
        const targetIdx = Math.min(elements.value.length, idx + 1);
        elements.value.splice(targetIdx, 0, el);
    } else if (action === 'backward') {
        const targetIdx = Math.max(0, idx - 1);
        elements.value.splice(targetIdx, 0, el);
    }
};

// Delete & Duplicate
const deleteSelected = () => {
    if (!selectedElementId.value) return;
    elements.value = elements.value.filter(e => e.id !== selectedElementId.value);
    selectedElementId.value = null;
};

const duplicateSelected = () => {
    if (!selectedElementId.value) return;
    const el = selectedElement.value;
    if (!el) return;

    const id = 'element_' + Math.random().toString(36).substr(2, 9);
    const copy: DrawingElement = {
        ...JSON.parse(JSON.stringify(el)),
        id,
        x: el.x + 20,
        y: el.y + 20,
    };
    if (el.x2 !== undefined) copy.x2 = (el.x2 || 0) + 20;
    if (el.y2 !== undefined) copy.y2 = (el.y2 || 0) + 20;

    elements.value.push(copy);
    selectedElementId.value = id;
};

// Clean Keydown list
const handleKeydown = (event: KeyboardEvent) => {
    if (textEditElementId.value) {
        return; // editing text, allow standard input keys
    }
    if (event.key === 'Delete' || event.key === 'Backspace') {
        deleteSelected();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

// Export SVG file
const downloadSVG = () => {
    const svgEl = document.getElementById('drawing-svg-canvas');
    if (!svgEl) return;

    // Get SVG source code
    const serializer = new XMLSerializer();
    let source = serializer.serializeToString(svgEl);

    // Add XML namespaces
    if (!source.match(/^<svg[^>]+xmlns="http:\/\/www\.w3\.org\/2000\/svg"/)) {
        source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
    }
    if (!source.match(/^<svg[^>]+xmlns:xlink="http:\/\/www\.w3\.org\/1999\/xlink"/)) {
        source = source.replace(/^<svg/, '<svg xmlns:xlink="http://www.w3.org/1999/xlink"');
    }

    // Add xml declaration
    source = '<?xml version="1.0" standalone="no"?>\r\n' + source;

    // Convert to DataURL
    const url = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(source);
    
    // Create temp link and click it
    const downloadLink = document.createElement("a");
    downloadLink.href = url;
    downloadLink.download = `${diagramName.value || 'drawing'}.svg`;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
};

</script>

<template>
    <Head :title="`Drawing: ${diagram.name}`" />

    <div class="flex flex-col h-[calc(100vh-65px)] bg-muted/20">
        <!-- Editor Header -->
        <header class="h-14 bg-card border-b border-border/40 px-6 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <Link href="/diagrams" class="size-8 rounded-lg border border-border/60 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted/40 transition-colors">
                    <ArrowLeft class="size-4" />
                </Link>
                <div class="flex flex-col">
                    <input 
                        v-model="diagramName" 
                        class="font-bold text-sm bg-transparent border-0 focus:ring-0 focus:outline-none p-0 text-foreground w-64 hover:bg-muted/10 rounded px-1"
                        placeholder="Untitled Drawing"
                    />
                    <span class="text-[10px] text-muted-foreground">Freehand vector canvas drawing layout</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span v-if="saveSuccess" class="text-xs text-emerald-500 font-semibold flex items-center gap-1">
                    <Check class="size-4" /> Saved Successfully
                </span>

                <Button 
                    @click="submitSave" 
                    :disabled="isSaving"
                    class="bg-[#1AC18C] hover:bg-[#1AC18C]/95 text-white font-bold rounded-xl cursor-pointer text-xs h-9"
                >
                    <Save class="mr-1.5 size-4" />
                    {{ isSaving ? 'Saving...' : 'Save Drawing' }}
                </Button>
            </div>
        </header>

        <!-- Tool & Styling Toolbar -->
        <div class="h-12 bg-card border-b border-border/30 px-6 flex items-center justify-between shrink-0 overflow-x-auto gap-4">
            <div class="flex items-center gap-1">
                <!-- Toolbar Tools -->
                <button 
                    @click="activeTool = 'select'; selectedElementId = null"
                    class="p-2 rounded-lg hover:bg-muted/50 text-muted-foreground"
                    :class="{ 'bg-primary/10 text-primary hover:bg-primary/20': activeTool === 'select' }"
                    title="Select & Move Tool (V)"
                >
                    <MousePointer class="size-4.5" />
                </button>
                
                <button 
                    @click="addElement('text')"
                    class="p-2 rounded-lg hover:bg-muted/50 text-muted-foreground"
                    title="Add Text Block"
                >
                    <Type class="size-4.5" />
                </button>
                
                <div class="h-4 w-px bg-border/40 mx-1"></div>

                <!-- Shape Add Actions -->
                <button @click="addElement('rectangle')" class="p-2 rounded-lg hover:bg-muted/50 text-muted-foreground" title="Add Rectangle">
                    <Square class="size-4.5" />
                </button>
                
                <button @click="addElement('circle')" class="p-2 rounded-lg hover:bg-muted/50 text-muted-foreground" title="Add Oval / Circle">
                    <Circle class="size-4.5" />
                </button>

                <button @click="addElement('triangle')" class="p-2 rounded-lg hover:bg-muted/50 text-muted-foreground" title="Add Triangle">
                    <span class="font-bold text-[10px] tracking-wide border-2 border-current px-1.5 py-0.5 rounded">TRI</span>
                </button>

                <button @click="addElement('star')" class="p-2 rounded-lg hover:bg-muted/50 text-muted-foreground" title="Add Star shape">
                    <span class="font-bold text-[10px] tracking-wide border-2 border-current px-1.5 py-0.5 rounded">STAR</span>
                </button>

                <button @click="addElement('line')" class="p-2 rounded-lg hover:bg-muted/50 text-muted-foreground" title="Add Line Connector">
                    <ChevronRight class="size-4.5 rotate-45" />
                </button>
            </div>

            <!-- Context Styles (Visible when element is selected) -->
            <div v-if="selectedElement" class="flex items-center gap-3 bg-muted/30 px-3 py-1 rounded-xl border border-border/30">
                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider shrink-0">Style</span>
                
                <!-- Fill Color -->
                <div class="flex items-center gap-1" v-if="selectedElement.type !== 'line'">
                    <Label class="sr-only">Fill</Label>
                    <input 
                        type="color" 
                        v-model="selectedElement.fillColor" 
                        class="size-6 p-0.5 border border-border rounded cursor-pointer bg-card shrink-0" 
                        title="Fill Color"
                    />
                </div>

                <!-- Stroke Color -->
                <div class="flex items-center gap-1">
                    <input 
                        type="color" 
                        v-model="selectedElement.strokeColor" 
                        class="size-6 p-0.5 border border-border rounded cursor-pointer bg-card shrink-0"
                        title="Stroke Color"
                    />
                </div>

                <!-- Stroke Width -->
                <select 
                    v-model.number="selectedElement.strokeWidth"
                    class="h-7 text-xs border border-border rounded-lg bg-card px-1 focus:outline-none"
                    title="Border Width"
                >
                    <option :value="1">1px</option>
                    <option :value="2">2px</option>
                    <option :value="3">3px</option>
                    <option :value="5">5px</option>
                    <option :value="8">8px</option>
                </select>

                <!-- Border Style -->
                <select 
                    v-model="selectedElement.strokeStyle"
                    class="h-7 text-xs border border-border rounded-lg bg-card px-1 focus:outline-none"
                    title="Border Style"
                >
                    <option value="solid">Solid</option>
                    <option value="dashed">Dashed</option>
                </select>

                <div class="h-4 w-px bg-border/40 mx-0.5"></div>

                <!-- Text parameters if element supports text -->
                <div class="flex items-center gap-2" v-if="selectedElement.type === 'text' || selectedElement.type !== 'line'">
                    <input 
                        type="color" 
                        v-model="selectedElement.textColor" 
                        class="size-6 p-0.5 border border-border rounded cursor-pointer bg-card shrink-0"
                        title="Text Color"
                    />
                    
                    <select 
                        v-model.number="selectedElement.fontSize"
                        class="h-7 text-xs border border-border rounded-lg bg-card px-1 focus:outline-none w-14"
                        title="Font Size"
                    >
                        <option :value="10">10px</option>
                        <option :value="12">12px</option>
                        <option :value="14">14px</option>
                        <option :value="16">16px</option>
                        <option :value="18">18px</option>
                        <option :value="24">24px</option>
                        <option :value="32">32px</option>
                    </select>

                    <button 
                        @click="selectedElement.fontWeight = selectedElement.fontWeight === 'bold' ? 'normal' : 'bold'"
                        class="h-7 px-2 border border-border rounded-lg text-xs font-bold bg-card"
                        :class="{ 'bg-primary/10 text-primary border-primary/20': selectedElement.fontWeight === 'bold' }"
                        title="Bold Text"
                    >
                        B
                    </button>

                    <button 
                        @click="selectedElement.fontStyle = selectedElement.fontStyle === 'italic' ? 'normal' : 'italic'"
                        class="h-7 px-2 border border-border rounded-lg text-xs italic bg-card"
                        :class="{ 'bg-primary/10 text-primary border-primary/20': selectedElement.fontStyle === 'italic' }"
                        title="Italic Text"
                    >
                        I
                    </button>
                </div>

                <div class="h-4 w-px bg-border/40 mx-0.5"></div>

                <!-- Layers ordering -->
                <div class="flex items-center gap-1">
                    <Button @click="moveLayer('front')" variant="ghost" size="sm" class="h-7 px-1.5" title="Bring to Front">
                        <Layers class="size-3.5" />
                    </Button>
                    <Button @click="moveLayer('back')" variant="ghost" size="sm" class="h-7 px-1.5" title="Send to Back">
                        <Layers class="size-3.5 rotate-180" />
                    </Button>
                </div>

                <!-- Duplicate / Delete Actions -->
                <div class="flex items-center gap-1">
                    <Button @click="duplicateSelected" variant="ghost" size="sm" class="h-7 px-1.5 hover:text-primary" title="Duplicate">
                        <Copy class="size-3.5" />
                    </Button>
                    <Button @click="deleteSelected" variant="ghost" size="sm" class="h-7 px-1.5 hover:text-red-500" title="Delete">
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
            </div>

            <!-- Align Right Export Action -->
            <div class="flex items-center gap-2">
                <Button 
                    @click="downloadSVG" 
                    variant="outline"
                    class="rounded-xl text-xs h-8 border-border/60 hover:bg-muted/40 cursor-pointer"
                    title="Export vector SVG"
                >
                    <Download class="size-3.5 mr-1" /> SVG
                </Button>
            </div>
        </div>

        <!-- Left Workspace Config and Center Board -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar Panel: Canvas Setup -->
            <div class="w-64 bg-card border-r border-border/40 p-5 flex flex-col justify-between shrink-0 overflow-y-auto">
                <div class="space-y-6">
                    <div>
                        <h4 class="font-bold text-xs uppercase tracking-wider text-muted-foreground flex items-center gap-1.5 mb-3">
                            <Settings class="size-4" /> Canvas Properties
                        </h4>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1">
                                    <Label for="c-width" class="text-[10px] uppercase font-bold text-muted-foreground">Width (px)</Label>
                                    <Input id="c-width" type="number" v-model.number="canvasWidth" step="50" class="h-8 rounded-lg text-xs" />
                                </div>
                                <div class="space-y-1">
                                    <Label for="c-height" class="text-[10px] uppercase font-bold text-muted-foreground">Height (px)</Label>
                                    <Input id="c-height" type="number" v-model.number="canvasHeight" step="50" class="h-8 rounded-lg text-xs" />
                                </div>
                            </div>

                            <div class="space-y-1">
                                <Label for="c-bg" class="text-[10px] uppercase font-bold text-muted-foreground">Background Color</Label>
                                <div class="flex gap-2">
                                    <input type="color" v-model="canvasBackground" class="size-8 p-0.5 border border-border rounded cursor-pointer shrink-0" />
                                    <Input v-model="canvasBackground" class="h-8 text-xs font-mono uppercase" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-border/40 pt-4 space-y-3">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            Alignment & Grid
                        </h4>

                        <label class="flex items-center gap-2 cursor-pointer text-xs text-foreground">
                            <input type="checkbox" v-model="showGrid" class="rounded border-input text-primary" />
                            <span>Show dot alignment grid</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer text-xs text-foreground">
                            <input type="checkbox" v-model="snapToGrid" class="rounded border-input text-primary" />
                            <span>Snap coordinates to grid (10px)</span>
                        </label>
                    </div>

                    <div class="border-t border-border/40 pt-4 space-y-2">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-muted-foreground">Drawing Info</h4>
                        <textarea 
                            v-model="diagramDescription" 
                            placeholder="Enter description..." 
                            rows="4" 
                            class="flex w-full rounded-xl border border-input bg-transparent px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#1AC18C]"
                        ></textarea>
                    </div>
                </div>

                <div class="pt-4 border-t border-border/30 text-[10px] text-muted-foreground space-y-1">
                    <p class="font-semibold text-foreground">Tips:</p>
                    <p>• Click shape tools above to draw vectors.</p>
                    <p>• Double-click text shapes to edit content.</p>
                    <p>• Use handles to resize or reposition lines.</p>
                    <p>• Press Backspace / Del to delete item.</p>
                </div>
            </div>

            <!-- Center Drawing Canvas Area -->
            <div 
                class="flex-1 overflow-auto p-8 flex justify-center items-start relative select-none"
                @click="selectedElementId = null; saveTextEdit()"
            >
                <div 
                    class="shadow-xl relative select-none border border-border/60 transition-all"
                    :style="{
                        width: canvasWidth + 'px',
                        height: canvasHeight + 'px',
                        backgroundColor: canvasBackground
                    }"
                >
                    <!-- Dot Grid Background overlay -->
                    <div 
                        v-if="showGrid" 
                        class="absolute inset-0 pointer-events-none"
                        style="background-image: radial-gradient(circle, rgba(0,0,0,0.1) 1px, transparent 1px); background-size: 20px 20px;"
                    ></div>

                    <!-- SVG Canvas vector graphics -->
                    <svg 
                        id="drawing-svg-canvas"
                        class="absolute inset-0 w-full h-full cursor-default overflow-visible"
                    >
                        <defs>
                            <marker 
                                id="arrowhead" 
                                markerWidth="10" 
                                markerHeight="7" 
                                refX="6" 
                                refY="3.5" 
                                orient="auto"
                            >
                                <polygon points="0 0, 10 3.5, 0 7" fill="#22273C" />
                            </marker>
                        </defs>

                        <g v-for="el in elements" :key="el.id">
                            <!-- Rectangle -->
                            <rect 
                                v-if="el.type === 'rectangle'"
                                :x="el.x"
                                :y="el.y"
                                :width="el.width"
                                :height="el.height"
                                :fill="el.fillColor"
                                :stroke="el.strokeColor"
                                :stroke-width="el.strokeWidth"
                                :stroke-dasharray="el.strokeStyle === 'dashed' ? '4' : '0'"
                                rx="4"
                                class="cursor-pointer"
                                @mousedown="startDrag($event, el)"
                                @dblclick="enableTextEdit(el)"
                            />

                            <!-- Circle -->
                            <ellipse 
                                v-if="el.type === 'circle'"
                                :cx="el.x + el.width/2"
                                :cy="el.y + el.height/2"
                                :rx="el.width/2"
                                :ry="el.height/2"
                                :fill="el.fillColor"
                                :stroke="el.strokeColor"
                                :stroke-width="el.strokeWidth"
                                :stroke-dasharray="el.strokeStyle === 'dashed' ? '4' : '0'"
                                class="cursor-pointer"
                                @mousedown="startDrag($event, el)"
                                @dblclick="enableTextEdit(el)"
                            />

                            <!-- Triangle -->
                            <polygon 
                                v-if="el.type === 'triangle'"
                                :points="`${el.x + el.width/2},${el.y} ${el.x},${el.y + el.height} ${el.x + el.width},${el.y + el.height}`"
                                :fill="el.fillColor"
                                :stroke="el.strokeColor"
                                :stroke-width="el.strokeWidth"
                                :stroke-dasharray="el.strokeStyle === 'dashed' ? '4' : '0'"
                                class="cursor-pointer"
                                @mousedown="startDrag($event, el)"
                                @dblclick="enableTextEdit(el)"
                            />

                            <!-- Star -->
                            <polygon 
                                v-if="el.type === 'star'"
                                :points="`
                                    ${el.x + el.width*0.5},${el.y}
                                    ${el.x + el.width*0.62},${el.y + el.height*0.35}
                                    ${el.x + el.width},${el.y + el.height*0.35}
                                    ${el.x + el.width*0.7},${el.y + el.height*0.58}
                                    ${el.x + el.width*0.8},${el.y + el.height}
                                    ${el.x + el.width*0.5},${el.y + el.height*0.78}
                                    ${el.x + el.width*0.2},${el.y + el.height}
                                    ${el.x + el.width*0.3},${el.y + el.height*0.58}
                                    ${el.x},${el.y + el.height*0.35}
                                    ${el.x + el.width*0.38},${el.y + el.height*0.35}
                                `"
                                :fill="el.fillColor"
                                :stroke="el.strokeColor"
                                :stroke-width="el.strokeWidth"
                                :stroke-dasharray="el.strokeStyle === 'dashed' ? '4' : '0'"
                                class="cursor-pointer"
                                @mousedown="startDrag($event, el)"
                                @dblclick="enableTextEdit(el)"
                            />

                            <!-- Text block box -->
                            <rect 
                                v-if="el.type === 'text'"
                                :x="el.x"
                                :y="el.y"
                                :width="el.width"
                                :height="el.height"
                                fill="transparent"
                                class="cursor-pointer"
                                @mousedown="startDrag($event, el)"
                                @dblclick="enableTextEdit(el)"
                            />

                            <!-- Line / Connector -->
                            <line 
                                v-if="el.type === 'line'"
                                :x1="el.x"
                                :y1="el.y"
                                :x2="el.x2"
                                :y2="el.y2"
                                :stroke="el.strokeColor"
                                :stroke-width="el.strokeWidth"
                                :stroke-dasharray="el.strokeStyle === 'dashed' ? '4' : '0'"
                                :marker-end="el.lineEnd === 'arrow' ? 'url(#arrowhead)' : 'none'"
                                class="cursor-pointer"
                                @mousedown="startDrag($event, el)"
                            />

                            <!-- Text block labels inside shapes / textboxes -->
                            <foreignObject 
                                v-if="el.type !== 'line' && textEditElementId !== el.id && (el.text || el.type === 'text')"
                                :x="el.x + 4"
                                :y="el.y + 4"
                                :width="el.width - 8"
                                :height="el.height - 8"
                                class="pointer-events-none overflow-hidden"
                            >
                                <div 
                                    class="w-full h-full flex flex-col justify-center"
                                    :style="{
                                        color: el.textColor,
                                        fontSize: el.fontSize + 'px',
                                        fontWeight: el.fontWeight,
                                        fontStyle: el.fontStyle,
                                        textAlign: el.textAlignment
                                    }"
                                >
                                    <p class="whitespace-pre-wrap leading-tight select-none">{{ el.text }}</p>
                                </div>
                            </foreignObject>
                        </g>
                    </svg>

                    <!-- Text Editing overlay (replaces normal text in-place) -->
                    <div 
                        v-if="textEditElementId"
                        class="absolute border border-primary z-30"
                        :style="{
                            left: (elements.find(e => e.id === textEditElementId)?.x || 0) + 'px',
                            top: (elements.find(e => e.id === textEditElementId)?.y || 0) + 'px',
                            width: (elements.find(e => e.id === textEditElementId)?.width || 100) + 'px',
                            height: (elements.find(e => e.id === textEditElementId)?.height || 40) + 'px',
                        }"
                    >
                        <textarea 
                            id="text-editor-field"
                            v-model="textEditInput"
                            @blur="saveTextEdit"
                            @keydown.esc="saveTextEdit"
                            class="w-full h-full border-0 p-1 resize-none bg-white text-black leading-tight focus:outline-none focus:ring-0 text-xs"
                        ></textarea>
                    </div>

                    <!-- Selected elements corner resize handles -->
                    <div 
                        v-if="selectedElement && textEditElementId === null"
                        class="absolute border-2 border-[#1AC18C] pointer-events-none select-none z-20"
                        :style="{
                            left: selectedElement.x + 'px',
                            top: selectedElement.y + 'px',
                            width: selectedElement.width + 'px',
                            height: selectedElement.height + 'px'
                        }"
                    >
                        <!-- Normal Shape Resize Handles -->
                        <div v-if="selectedElement.type !== 'line'">
                            <div 
                                class="absolute size-2.5 bg-white border border-[#1AC18C] -left-1.5 -top-1.5 pointer-events-auto cursor-nwse-resize"
                                @mousedown="startResize($event, 'tl', selectedElement)"
                            ></div>
                            <div 
                                class="absolute size-2.5 bg-white border border-[#1AC18C] -right-1.5 -top-1.5 pointer-events-auto cursor-nesw-resize"
                                @mousedown="startResize($event, 'tr', selectedElement)"
                            ></div>
                            <div 
                                class="absolute size-2.5 bg-white border border-[#1AC18C] -left-1.5 -bottom-1.5 pointer-events-auto cursor-nesw-resize"
                                @mousedown="startResize($event, 'bl', selectedElement)"
                            ></div>
                            <div 
                                class="absolute size-2.5 bg-white border border-[#1AC18C] -right-1.5 -bottom-1.5 pointer-events-auto cursor-nwse-resize"
                                @mousedown="startResize($event, 'br', selectedElement)"
                            ></div>
                        </div>
                    </div>

                    <!-- Line Start & End Drag Handles -->
                    <div v-if="selectedElement && selectedElement.type === 'line'">
                        <div 
                            class="absolute size-3 bg-[#1AC18C] border border-white rounded-full z-20 cursor-move"
                            :style="{
                                left: (selectedElement.x - 6) + 'px',
                                top: (selectedElement.y - 6) + 'px'
                            }"
                            @mousedown="startResize($event, 'start', selectedElement)"
                            title="Start Node"
                        ></div>
                        <div 
                            class="absolute size-3 bg-[#1AC18C] border border-white rounded-full z-20 cursor-move"
                            :style="{
                                left: ((selectedElement.x2 || selectedElement.x) - 6) + 'px',
                                top: ((selectedElement.y2 || selectedElement.y) - 6) + 'px'
                            }"
                            @mousedown="startResize($event, 'end', selectedElement)"
                            title="End Node / Arrowhead"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
