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
    Download,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Upload
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
const zoom = ref(1); // 1 = 100%

const selectedElementIds = ref<string[]>([]);
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

const selectedElements = computed(() => {
    return elements.value.filter(el => selectedElementIds.value.includes(el.id));
});

const selectedElement = computed(() => {
    return elements.value.find(el => el.id === selectedElementIds.value[0]) || null;
});

const canGroup = computed(() => {
    return selectedElementIds.value.length > 1;
});

const canUngroup = computed(() => {
    return selectedElements.value.some(el => !!el.groupId);
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

const workspaceContainer = ref<HTMLDivElement | null>(null);
const canvasContainer = ref<HTMLDivElement | null>(null);

// Wheel Zoom Interaction (Cmd + Scroll / Cmd + Trackpad wheel)
const handleWheel = (event: WheelEvent) => {
    if (event.metaKey || event.ctrlKey) {
        event.preventDefault();
        const step = 0.25;
        if (event.deltaY < 0) {
            zoom.value = Math.min(2, zoom.value + step);
        } else {
            zoom.value = Math.max(0.25, zoom.value - step);
        }
    }
};

onMounted(() => {
    if (workspaceContainer.value) {
        workspaceContainer.value.addEventListener('wheel', handleWheel, { passive: false });
    }
});

onUnmounted(() => {
    if (workspaceContainer.value) {
        workspaceContainer.value.removeEventListener('wheel', handleWheel);
    }
});

// Dynamic viewport coordinate finder to place new shapes exactly in the visible view center
const getCanvasCenter = (width: number, height: number) => {
    if (!workspaceContainer.value || !canvasContainer.value) {
        return { x: 100, y: 100 };
    }
    const wsRect = workspaceContainer.value.getBoundingClientRect();
    const canvasRect = canvasContainer.value.getBoundingClientRect();

    const viewCenterX = wsRect.left + wsRect.width / 2;
    const viewCenterY = wsRect.top + wsRect.height / 2;

    const relativeX = viewCenterX - canvasRect.left;
    const relativeY = viewCenterY - canvasRect.top;

    const canvasX = relativeX / zoom.value;
    const canvasY = relativeY / zoom.value;

    const gridVal = snapToGrid.value ? 5 : 1;
    const snap = (val: number) => Math.round(val / gridVal) * gridVal;

    return {
        x: Math.max(0, snap(canvasX - width / 2)),
        y: Math.max(0, snap(canvasY - height / 2))
    };
};

// Add Element helper
const addElement = (type: DrawingElement['type']) => {
    const id = 'element_' + Math.random().toString(36).substr(2, 9);
    const width = type === 'line' ? 200 : 150;
    const height = type === 'line' ? 100 : 100;
    
    // Dynamically calculate coordinate offsets based on viewport center
    const center = getCanvasCenter(width, height);

    let newEl: DrawingElement = {
        id,
        type,
        x: center.x,
        y: center.y,
        width,
        height,
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
        newEl.x2 = center.x + 200;
        newEl.y2 = center.y + 100;
        newEl.lineEnd = 'arrow';
    } else {
        newEl.text = ''; // shapes can have text inside
    }

    elements.value.push(newEl);
    selectedElementIds.value = [id];
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

const startCoords = ref<Array<{ id: string; x: number; y: number; x2?: number; y2?: number }>>([]);

const startDrag = (event: MouseEvent, el: DrawingElement) => {
    if (activeTool.value !== 'select' || textEditElementId.value === el.id) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    
    // Select element and its group members
    const groupMembers = el.groupId 
        ? elements.value.filter(e => e.groupId === el.groupId).map(e => e.id)
        : [el.id];

    if (event.shiftKey) {
        // Shift select toggles selection of whole group
        const alreadySelected = selectedElementIds.value.includes(el.id);
        if (alreadySelected) {
            selectedElementIds.value = selectedElementIds.value.filter(id => !groupMembers.includes(id));
        } else {
            groupMembers.forEach(id => {
                if (!selectedElementIds.value.includes(id)) {
                    selectedElementIds.value.push(id);
                }
            });
        }
    } else {
        // Normal select: if already selected, don't clear (to allow group dragging)
        if (!selectedElementIds.value.includes(el.id)) {
            selectedElementIds.value = [...groupMembers];
        }
    }

    isDragging = true;
    dragStartX = event.clientX;
    dragStartY = event.clientY;

    // Save starting position of all selected elements for relative dragging
    startCoords.value = selectedElements.value.map(item => ({
        id: item.id,
        x: item.x,
        y: item.y,
        x2: item.x2,
        y2: item.y2,
    }));

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
    if (selectedElementIds.value.length === 0) return;
    
    const deltaX = (event.clientX - dragStartX) / zoom.value;
    const deltaY = (event.clientY - dragStartY) / zoom.value;
    const gridVal = snapToGrid.value ? 5 : 1;

    const snap = (val: number) => Math.round(val / gridVal) * gridVal;

    if (isDragging) {
        startCoords.value.forEach(start => {
            const el = elements.value.find(e => e.id === start.id);
            if (el) {
                el.x = snap(start.x + deltaX);
                el.y = snap(start.y + deltaY);
                if (el.type === 'line' && start.x2 !== undefined) {
                    el.x2 = snap(start.x2 + deltaX);
                    el.y2 = snap(start.y2 + deltaY);
                }
            }
        });
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

// Canvas Resizing Interactions
let isResizingCanvas = false;
let canvasStartWidth = 0;
let canvasStartHeight = 0;

const startCanvasResize = (event: MouseEvent) => {
    isResizingCanvas = true;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    canvasStartWidth = canvasWidth.value;
    canvasStartHeight = canvasHeight.value;

    window.addEventListener('mousemove', handleCanvasMouseMove);
    window.addEventListener('mouseup', handleCanvasMouseUp);
};

const handleCanvasMouseMove = (event: MouseEvent) => {
    if (!isResizingCanvas) return;
    const deltaX = (event.clientX - dragStartX) / zoom.value;
    const deltaY = (event.clientY - dragStartY) / zoom.value;
    const gridVal = snapToGrid.value ? 5 : 1;

    const snap = (val: number) => Math.round(val / gridVal) * gridVal;
    canvasWidth.value = Math.max(200, snap(canvasStartWidth + deltaX));
    canvasHeight.value = Math.max(200, snap(canvasStartHeight + deltaY));
};

const handleCanvasMouseUp = () => {
    isResizingCanvas = false;
    window.removeEventListener('mousemove', handleCanvasMouseMove);
    window.removeEventListener('mouseup', handleCanvasMouseUp);
};

// Text Inline Editing
const enableTextEdit = (el: DrawingElement) => {
    selectedElementIds.value = [el.id];
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
    if (selectedElementIds.value.length === 0) return;
    
    // Find absolute indices in array
    const indices = selectedElementIds.value
        .map(id => elements.value.findIndex(e => e.id === id))
        .filter(idx => idx !== -1)
        .sort((a, b) => a - b);

    if (indices.length === 0) return;

    if (action === 'front') {
        const items = indices.map(idx => elements.value[idx]);
        elements.value = elements.value.filter((_, idx) => !indices.includes(idx));
        elements.value.push(...items);
    } else if (action === 'back') {
        const items = indices.map(idx => elements.value[idx]);
        elements.value = elements.value.filter((_, idx) => !indices.includes(idx));
        elements.value.unshift(...items);
    } else if (action === 'forward') {
        for (let i = indices.length - 1; i >= 0; i--) {
            const idx = indices[i];
            if (idx < elements.value.length - 1) {
                const el = elements.value[idx];
                elements.value.splice(idx, 1);
                elements.value.splice(idx + 1, 0, el);
            }
        }
    } else if (action === 'backward') {
        for (let i = 0; i < indices.length; i++) {
            const idx = indices[i];
            if (idx > 0) {
                const el = elements.value[idx];
                elements.value.splice(idx, 1);
                elements.value.splice(idx - 1, 0, el);
            }
        }
    }
};

// Delete & Duplicate
const deleteSelected = () => {
    if (selectedElementIds.value.length === 0) return;

    // Delete selected elements + any other elements in their groups
    const groupIdsToDelete = selectedElements.value.map(e => e.groupId).filter(Boolean) as string[];

    elements.value = elements.value.filter(el => {
        if (selectedElementIds.value.includes(el.id)) return false;
        if (el.groupId && groupIdsToDelete.includes(el.groupId)) return false;
        return true;
    });

    selectedElementIds.value = [];
};

const duplicateSelected = () => {
    if (selectedElementIds.value.length === 0) return;

    const duplicatedIds: string[] = [];
    const groupMapping: Record<string, string> = {};

    const newCopies = selectedElements.value.map(el => {
        const newId = 'element_' + Math.random().toString(36).substr(2, 9);
        duplicatedIds.push(newId);

        const copy: DrawingElement = JSON.parse(JSON.stringify(el));
        copy.id = newId;
        copy.x = el.x + 20;
        copy.y = el.y + 20;
        if (el.x2 !== undefined) copy.x2 = (el.x2 || 0) + 20;
        if (el.y2 !== undefined) copy.y2 = (el.y2 || 0) + 20;

        if (el.groupId) {
            if (!groupMapping[el.groupId]) {
                groupMapping[el.groupId] = 'group_' + Math.random().toString(36).substr(2, 9);
            }
            copy.groupId = groupMapping[el.groupId];
        }

        return copy;
    });

    elements.value.push(...newCopies);
    selectedElementIds.value = duplicatedIds;
};

// Group / Ungroup
const groupSelected = () => {
    if (selectedElementIds.value.length < 2) return;
    const newGroupId = 'group_' + Math.random().toString(36).substr(2, 9);
    selectedElements.value.forEach(el => {
        el.groupId = newGroupId;
    });
};

const ungroupSelected = () => {
    const groupIdsToClear = [...new Set(selectedElements.value.map(el => el.groupId).filter(Boolean))];
    elements.value.forEach(el => {
        if (el.groupId && groupIdsToClear.includes(el.groupId)) {
            delete el.groupId;
        }
    });
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

// SVG Importing Interactions
const triggerSVGImport = () => {
    const fileInput = document.getElementById('import-svg-file');
    if (fileInput) fileInput.click();
};

const handleSVGImport = (event: Event) => {
    const fileInput = event.target as HTMLInputElement;
    if (!fileInput.files || fileInput.files.length === 0) return;

    const file = fileInput.files[0];
    const reader = new FileReader();
    
    reader.onload = (e) => {
        const text = e.target?.result as string;
        if (!text) return;

        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'image/svg+xml');
            
            const parserError = doc.querySelector('parsererror');
            if (parserError) {
                alert('Invalid SVG file format');
                return;
            }

            const svgElement = doc.querySelector('svg');
            if (!svgElement) return;

            // Set canvas size from SVG attributes if defined
            const viewBox = svgElement.getAttribute('viewBox');
            if (viewBox) {
                const parts = viewBox.split(/[\s,]+/).map(Number);
                if (parts.length === 4) {
                    canvasWidth.value = parts[2];
                    canvasHeight.value = parts[3];
                }
            } else {
                const widthAttr = svgElement.getAttribute('width');
                const heightAttr = svgElement.getAttribute('height');
                if (widthAttr && heightAttr) {
                    canvasWidth.value = parseInt(widthAttr, 10);
                    canvasHeight.value = parseInt(heightAttr, 10);
                }
            }

            const styleAttr = svgElement.getAttribute('style');
            if (styleAttr) {
                const bgMatch = styleAttr.match(/background-color:\s*(#[a-fA-F0-9]{3,8}|[a-zA-Z]+)/);
                if (bgMatch) {
                    canvasBackground.value = bgMatch[1];
                }
            }

            const newElements: DrawingElement[] = [];
            const makeId = (type: string) => type + '_' + Math.random().toString(36).substr(2, 9);

            // 1. Rectangles
            const rects = doc.querySelectorAll('rect');
            rects.forEach(rect => {
                const x = parseFloat(rect.getAttribute('x') || '0');
                const y = parseFloat(rect.getAttribute('y') || '0');
                const width = parseFloat(rect.getAttribute('width') || '100');
                const height = parseFloat(rect.getAttribute('height') || '100');
                const fill = rect.getAttribute('fill') || '#1AC18C';
                const stroke = rect.getAttribute('stroke') || '#22273C';
                const strokeWidth = parseFloat(rect.getAttribute('stroke-width') || '2');
                const dash = rect.getAttribute('stroke-dasharray');
                const isDashed = dash && dash !== '0' && dash !== 'none';

                let textContent = '';
                let textColor = '#1E293B';
                let fontSize = 14;
                let fontWeight: 'normal' | 'bold' = 'normal';
                let fontStyle: 'normal' | 'italic' = 'normal';
                let textAlignment: 'left' | 'center' | 'right' = 'center';

                const parentG = rect.parentElement;
                if (parentG && parentG.tagName.toLowerCase() === 'g') {
                    const fo = parentG.querySelector('foreignObject');
                    if (fo) {
                        const p = fo.querySelector('p');
                        if (p) textContent = p.textContent || '';
                        
                        const textDiv = fo.querySelector('div');
                        if (textDiv) {
                            textColor = textDiv.style.color || textColor;
                            fontSize = parseInt(textDiv.style.fontSize, 10) || fontSize;
                            fontWeight = (textDiv.style.fontWeight === 'bold') ? 'bold' : 'normal';
                            fontStyle = (textDiv.style.fontStyle === 'italic') ? 'italic' : 'normal';
                            textAlignment = (textDiv.style.textAlign as any) || textAlignment;
                        }
                    }
                }

                const isTextOnly = fill === 'transparent' || fill === 'none';

                newElements.push({
                    id: makeId(isTextOnly ? 'text' : 'rectangle'),
                    type: isTextOnly ? 'text' : 'rectangle',
                    x,
                    y,
                    width,
                    height,
                    fillColor: fill,
                    strokeColor: stroke,
                    strokeWidth,
                    strokeStyle: isDashed ? 'dashed' : 'solid',
                    text: textContent,
                    textColor,
                    fontSize,
                    fontWeight,
                    fontStyle,
                    textAlignment
                });
            });

            // 2. Circles / Ellipses
            const ellipses = doc.querySelectorAll('ellipse');
            ellipses.forEach(el => {
                const cx = parseFloat(el.getAttribute('cx') || '0');
                const cy = parseFloat(el.getAttribute('cy') || '0');
                const rx = parseFloat(el.getAttribute('rx') || '50');
                const ry = parseFloat(el.getAttribute('ry') || '50');
                const fill = el.getAttribute('fill') || '#1AC18C';
                const stroke = el.getAttribute('stroke') || '#22273C';
                const strokeWidth = parseFloat(el.getAttribute('stroke-width') || '2');
                const dash = el.getAttribute('stroke-dasharray');
                const isDashed = dash && dash !== '0' && dash !== 'none';

                newElements.push({
                    id: makeId('circle'),
                    type: 'circle',
                    x: cx - rx,
                    y: cy - ry,
                    width: rx * 2,
                    height: ry * 2,
                    fillColor: fill,
                    strokeColor: stroke,
                    strokeWidth,
                    strokeStyle: isDashed ? 'dashed' : 'solid',
                    textColor: '#1E293B',
                    fontSize: 14,
                    fontWeight: 'normal',
                    fontStyle: 'normal',
                    textAlignment: 'center'
                });
            });

            // 3. Polygons
            const polygons = doc.querySelectorAll('polygon');
            polygons.forEach(poly => {
                const pointsAttr = poly.getAttribute('points') || '';
                const pointPairs = pointsAttr.trim().split(/[\s,]+/).filter(Boolean);
                
                const fill = poly.getAttribute('fill') || '#1AC18C';
                const stroke = poly.getAttribute('stroke') || '#22273C';
                const strokeWidth = parseFloat(poly.getAttribute('stroke-width') || '2');
                const dash = poly.getAttribute('stroke-dasharray');
                const isDashed = dash && dash !== '0' && dash !== 'none';

                if (poly.parentElement?.tagName.toLowerCase() === 'marker') return;

                if (pointPairs.length === 6) {
                    const coords = pointPairs.map(Number);
                    const xs = [coords[0], coords[2], coords[4]];
                    const ys = [coords[1], coords[3], coords[5]];
                    const minX = Math.min(...xs);
                    const maxX = Math.max(...xs);
                    const minY = Math.min(...ys);
                    const maxY = Math.max(...ys);

                    newElements.push({
                        id: makeId('triangle'),
                        type: 'triangle',
                        x: minX,
                        y: minY,
                        width: maxX - minX,
                        height: maxY - minY,
                        fillColor: fill,
                        strokeColor: stroke,
                        strokeWidth,
                        strokeStyle: isDashed ? 'dashed' : 'solid',
                        textColor: '#1E293B',
                        fontSize: 14,
                        fontWeight: 'normal',
                        fontStyle: 'normal',
                        textAlignment: 'center'
                    });
                } else if (pointPairs.length === 20) {
                    const coords = pointPairs.map(Number);
                    const xs = coords.filter((_, i) => i % 2 === 0);
                    const ys = coords.filter((_, i) => i % 2 === 1);
                    const minX = Math.min(...xs);
                    const maxX = Math.max(...xs);
                    const minY = Math.min(...ys);
                    const maxY = Math.max(...ys);

                    newElements.push({
                        id: makeId('star'),
                        type: 'star',
                        x: minX,
                        y: minY,
                        width: maxX - minX,
                        height: maxY - minY,
                        fillColor: fill,
                        strokeColor: stroke,
                        strokeWidth,
                        strokeStyle: isDashed ? 'dashed' : 'solid',
                        textColor: '#1E293B',
                        fontSize: 14,
                        fontWeight: 'normal',
                        fontStyle: 'normal',
                        textAlignment: 'center'
                    });
                }
            });

            // 4. Lines
            const lines = doc.querySelectorAll('line');
            lines.forEach(line => {
                const x1 = parseFloat(line.getAttribute('x1') || '0');
                const y1 = parseFloat(line.getAttribute('y1') || '0');
                const x2 = parseFloat(line.getAttribute('x2') || '100');
                const y2 = parseFloat(line.getAttribute('y2') || '100');
                const stroke = line.getAttribute('stroke') || '#22273C';
                const strokeWidth = parseFloat(line.getAttribute('stroke-width') || '2');
                const dash = line.getAttribute('stroke-dasharray');
                const isDashed = dash && dash !== '0' && dash !== 'none';
                const markerEnd = line.getAttribute('marker-end');
                const hasArrow = markerEnd && markerEnd !== 'none';

                newElements.push({
                    id: makeId('line'),
                    type: 'line',
                    x: x1,
                    y: y1,
                    x2: x2,
                    y2: x2,
                    width: Math.abs(x2 - x1),
                    height: Math.abs(y2 - y1),
                    fillColor: 'transparent',
                    strokeColor: stroke,
                    strokeWidth,
                    strokeStyle: isDashed ? 'dashed' : 'solid',
                    lineEnd: hasArrow ? 'arrow' : 'none',
                    textColor: '#1E293B',
                    fontSize: 14,
                    fontWeight: 'normal',
                    fontStyle: 'normal',
                    textAlignment: 'center'
                });
            });

            if (newElements.length > 0) {
                elements.value = [...elements.value, ...newElements];
            } else {
                alert('No compatible vector shapes found in the SVG');
            }

        } catch (err) {
            console.error(err);
            alert('Failed to read or parse SVG data');
        }
    };

    reader.readAsText(file);
    fileInput.value = '';
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
                    @click="activeTool = 'select'; selectedElementIds = []; textEditElementId = null"
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

                    <div class="h-4 w-px bg-border/40 mx-0.5"></div>

                    <button 
                        @click="selectedElement.textAlignment = 'left'"
                        class="h-7 px-1.5 border border-border rounded-lg bg-card flex items-center justify-center text-muted-foreground hover:text-foreground cursor-pointer"
                        :class="{ 'bg-primary/10 text-primary border-primary/20': selectedElement.textAlignment === 'left' }"
                        title="Align Left"
                    >
                        <AlignLeft class="size-3.5" />
                    </button>
                    <button 
                        @click="selectedElement.textAlignment = 'center'"
                        class="h-7 px-1.5 border border-border rounded-lg bg-card flex items-center justify-center text-muted-foreground hover:text-foreground cursor-pointer"
                        :class="{ 'bg-primary/10 text-primary border-primary/20': selectedElement.textAlignment === 'center' }"
                        title="Align Center"
                    >
                        <AlignCenter class="size-3.5" />
                    </button>
                    <button 
                        @click="selectedElement.textAlignment = 'right'"
                        class="h-7 px-1.5 border border-border rounded-lg bg-card flex items-center justify-center text-muted-foreground hover:text-foreground cursor-pointer"
                        :class="{ 'bg-primary/10 text-primary border-primary/20': selectedElement.textAlignment === 'right' }"
                        title="Align Right"
                    >
                        <AlignRight class="size-3.5" />
                    </button>
                </div>

                <div class="h-4 w-px bg-border/40 mx-0.5"></div>

                <!-- Group / Ungroup controls -->
                <div class="flex items-center gap-1" v-if="canGroup || canUngroup">
                    <Button 
                        v-if="canGroup" 
                        @click="groupSelected" 
                        variant="ghost" 
                        size="sm" 
                        class="h-7 px-2 text-[10px] text-foreground font-bold hover:bg-[#1AC18C]/10 hover:text-[#1AC18C] border border-border/60 rounded-lg shrink-0" 
                        title="Group selected shapes"
                    >
                        Group
                    </Button>
                    <Button 
                        v-if="canUngroup" 
                        @click="ungroupSelected" 
                        variant="ghost" 
                        size="sm" 
                        class="h-7 px-2 text-[10px] text-foreground font-bold hover:bg-red-500/10 hover:text-red-500 border border-border/60 rounded-lg shrink-0" 
                        title="Ungroup shapes"
                    >
                        Ungroup
                    </Button>
                    <div class="h-4 w-px bg-border/40 mx-0.5"></div>
                </div>

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

            <!-- Align Right Export & Zoom Action -->
            <div class="flex items-center gap-2">
                <!-- Zoom Selector -->
                <div class="flex items-center gap-1 bg-muted/40 rounded-xl px-2 py-0.5 border border-border/60 mr-1.5 h-8 shrink-0">
                    <button 
                        @click="zoom = Math.max(0.25, zoom - 0.25)" 
                        class="w-5 h-5 flex items-center justify-center text-muted-foreground hover:text-foreground text-xs font-bold leading-none cursor-pointer hover:bg-muted rounded-md"
                        title="Zoom Out"
                    >
                        -
                    </button>
                    <select 
                        v-model.number="zoom"
                        class="bg-transparent border-0 text-[10px] font-bold text-foreground focus:outline-none cursor-pointer px-1 py-0.5 text-center focus:ring-0 select-none appearance-none"
                    >
                        <option :value="0.25">25%</option>
                        <option :value="0.5">50%</option>
                        <option :value="0.75">75%</option>
                        <option :value="1">100%</option>
                        <option :value="1.25">125%</option>
                        <option :value="1.5">150%</option>
                        <option :value="2">200%</option>
                    </select>
                    <button 
                        @click="zoom = Math.min(2, zoom + 0.25)" 
                        class="w-5 h-5 flex items-center justify-center text-muted-foreground hover:text-foreground text-xs font-bold leading-none cursor-pointer hover:bg-muted rounded-md"
                        title="Zoom In"
                    >
                        +
                    </button>
                </div>

                <!-- Import SVG Trigger -->
                <input 
                    type="file" 
                    id="import-svg-file" 
                    accept=".svg" 
                    class="hidden" 
                    @change="handleSVGImport"
                />
                <Button 
                    @click="triggerSVGImport" 
                    variant="outline"
                    class="rounded-xl text-xs h-8 border-border/60 hover:bg-muted/40 cursor-pointer mr-1"
                    title="Import shapes from vector SVG file"
                >
                    <Upload class="size-3.5 mr-1" /> Import SVG
                </Button>

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
                            <span>Snap coordinates to grid (5px)</span>
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
                ref="workspaceContainer"
                class="flex-1 overflow-auto p-12 relative select-none"
                @click="selectedElementIds = []; saveTextEdit()"
            >
                <!-- Scroll Sizing Wrapper to establish zoomed DOM scroll size -->
                <div 
                    :style="{
                        width: (canvasWidth * zoom) + 'px',
                        height: (canvasHeight * zoom) + 'px',
                        position: 'relative'
                    }"
                >
                    <div 
                        ref="canvasContainer"
                        class="shadow-xl relative select-none border border-border/60 transition-transform origin-top-left"
                        :style="{
                            width: canvasWidth + 'px',
                            height: canvasHeight + 'px',
                            backgroundColor: canvasBackground,
                            transform: `scale(${zoom})`
                        }"
                    >
                    <!-- Dot Grid Background overlay -->
                    <div 
                        v-if="showGrid" 
                        class="absolute inset-0 pointer-events-none"
                        style="background-image: radial-gradient(circle, rgba(0,0,0,0.1) 1px, transparent 1px); background-size: 10px 10px;"
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
                                @click.stop=""
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
                                @click.stop=""
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
                                @click.stop=""
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
                                @click.stop=""
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
                                @click.stop=""
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
                                @click.stop=""
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
                        v-for="el in selectedElements"
                        :key="'border_' + el.id"
                        class="absolute border border-dashed border-[#1AC18C]/80 pointer-events-none select-none z-20"
                        :style="{
                            left: el.x + 'px',
                            top: el.y + 'px',
                            width: el.width + 'px',
                            height: el.height + 'px'
                        }"
                        @click.stop=""
                    >
                        <!-- Normal Shape Resize Handles (Only show for single-selection) -->
                        <div v-if="selectedElementIds.length === 1 && el.type !== 'line'">
                            <div 
                                class="absolute size-2.5 bg-white border border-[#1AC18C] -left-1.5 -top-1.5 pointer-events-auto cursor-nwse-resize"
                                @mousedown="startResize($event, 'tl', el)"
                            ></div>
                            <div 
                                class="absolute size-2.5 bg-white border border-[#1AC18C] -right-1.5 -top-1.5 pointer-events-auto cursor-nesw-resize"
                                @mousedown="startResize($event, 'tr', el)"
                            ></div>
                            <div 
                                class="absolute size-2.5 bg-white border border-[#1AC18C] -left-1.5 -bottom-1.5 pointer-events-auto cursor-nesw-resize"
                                @mousedown="startResize($event, 'bl', el)"
                            ></div>
                            <div 
                                class="absolute size-2.5 bg-white border border-[#1AC18C] -right-1.5 -bottom-1.5 pointer-events-auto cursor-nwse-resize"
                                @mousedown="startResize($event, 'br', el)"
                            ></div>
                        </div>
                    </div>

                    <!-- Line Start & End Drag Handles (Only show for single-selection) -->
                    <div v-if="selectedElementIds.length === 1 && selectedElement && selectedElement.type === 'line'" @click.stop="">
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

                    <!-- Canvas Resize Handle -->
                    <div 
                        class="absolute bottom-0 right-0 size-3.5 bg-[#1AC18C]/20 border-r-2 border-b-2 border-[#1AC18C] cursor-se-resize flex items-end justify-end pointer-events-auto z-10 rounded-br"
                        @mousedown.stop.prevent="startCanvasResize"
                        title="Drag to resize canvas sheet"
                    >
                        <svg class="size-2 text-[#1AC18C]" viewBox="0 0 10 10">
                            <line x1="0" y1="10" x2="10" y2="0" stroke="currentColor" stroke-width="1.5" />
                            <line x1="4" y1="10" x2="10" y2="4" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
</template>
