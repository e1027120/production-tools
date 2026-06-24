<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    ArrowLeft, 
    Save, 
    Plus, 
    Trash2, 
    Download, 
    Printer, 
    AlertTriangle, 
    Check, 
    HelpCircle,
    Sliders,
    Zap,
    Scale,
    Thermometer,
    Grid,
    Search
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Define TS Interfaces
interface Device {
    id: string;
    brand: string;
    name: string;
    type: string;
    u_height: number;
    position: number; // 1-indexed position starting from bottom
    power_consumption: number; // Watts
    heat_dissipation: number; // BTU/h
    weight: number; // kg
}

interface Rack {
    id: number;
    name: string;
    size: number;
    description: string | null;
    devices: Device[] | null;
}

const props = defineProps<{
    rack: Rack;
    catalog: any[];
}>();

// UI States
const activeCategory = ref('All');
const selectedDeviceToPlace = ref<any | null>(null);
const hoverPosition = ref<number | null>(null);
const searchQuery = ref('');

// Custom Device Form States
const showCustomForm = ref(false);
const catalogForm = useForm({
    brand: '',
    name: '',
    type: 'Audio',
    u_height: 1,
    power_consumption: 50,
    weight: 2.5,
});

// Inertia Form Setup
const form = useForm({
    name: props.rack.name,
    size: props.rack.size,
    description: props.rack.description || '',
    devices: props.rack.devices || [],
});

// Filtered Catalog
const filteredCatalog = computed(() => {
    let items = props.catalog;
    if (activeCategory.value !== 'All') {
        items = items.filter(dev => dev.type === activeCategory.value);
    }
    if (searchQuery.value.trim() !== '') {
        const query = searchQuery.value.toLowerCase();
        items = items.filter(dev => {
            return dev.brand.toLowerCase().includes(query) || 
                   dev.name.toLowerCase().includes(query) ||
                   dev.type.toLowerCase().includes(query);
        });
    }
    return items;
});

// Save Function
const saveLayout = () => {
    form.put(`/racks/${props.rack.id}`, {
        preserveScroll: true,
    });
};

// Check if specific slot is occupied
const getDeviceAtSlot = (slotNumber: number): Device | null => {
    const devices = form.devices as Device[];
    return devices.find(dev => {
        return slotNumber >= dev.position && slotNumber < (dev.position + dev.u_height);
    }) || null;
};

// Validate if placement is possible (bounds & collisions)
const isPlacementValid = (position: number, height: number): boolean => {
    if (position + height - 1 > form.size) return false;
    for (let i = 0; i < height; i++) {
        if (getDeviceAtSlot(position + i) !== null) return false;
    }
    return true;
};

// Start placement flow
const selectDeviceForPlacement = (item: any) => {
    selectedDeviceToPlace.value = item;
};

// Cancel placement flow
const cancelPlacement = () => {
    selectedDeviceToPlace.value = null;
    hoverPosition.value = null;
};

// Perform placement on slot click
const placeDevice = (position: number) => {
    if (!selectedDeviceToPlace.value) return;
    
    const height = selectedDeviceToPlace.value.u_height;
    if (!isPlacementValid(position, height)) return;

    const newDevice: Device = {
        id: 'dev_' + Math.random().toString(36).substr(2, 9),
        brand: selectedDeviceToPlace.value.brand,
        name: selectedDeviceToPlace.value.name,
        type: selectedDeviceToPlace.value.type,
        u_height: height,
        position: position,
        power_consumption: selectedDeviceToPlace.value.power_consumption,
        heat_dissipation: Math.round(selectedDeviceToPlace.value.power_consumption * 3.412),
        weight: selectedDeviceToPlace.value.weight,
    };

    form.devices.push(newDevice);
    selectedDeviceToPlace.value = null;
    hoverPosition.value = null;
};

// Remove Device
const removeDevice = (id: string) => {
    form.devices = form.devices.filter(dev => dev.id !== id);
};

// Create custom device templates
const createCustomDevice = () => {
    catalogForm.post('/catalog-devices', {
        onSuccess: () => {
            showCustomForm.value = false;
            catalogForm.reset();
        },
    });
};

// Report Calculations
const report = computed(() => {
    const devices = form.devices as Device[];
    const totalPower = devices.reduce((sum, dev) => sum + dev.power_consumption, 0);
    const totalWeight = devices.reduce((sum, dev) => sum + dev.weight, 0);
    const totalHeat = Math.round(totalPower * 3.412); // W to BTU/h
    
    // Slot calculations
    const uOccupied = devices.reduce((sum, dev) => sum + dev.u_height, 0);
    const pctOccupied = Math.round((uOccupied / form.size) * 100);

    // Center of gravity calculation (weight distribution)
    let centerOfGravity = 0;
    if (totalWeight > 0) {
        let weightedSum = 0;
        devices.forEach(dev => {
            const devMidPoint = dev.position + (dev.u_height / 2);
            weightedSum += devMidPoint * dev.weight;
        });
        centerOfGravity = Math.round((weightedSum / totalWeight) * 10) / 10;
    }

    const isTopHeavy = totalWeight > 10 && centerOfGravity > (form.size / 2);

    return {
        totalPower,
        totalWeight,
        totalHeat,
        uOccupied,
        pctOccupied,
        centerOfGravity,
        isTopHeavy
    };
});

// Device Color Helper
const getDeviceColorClass = (type: string): string => {
    switch (type) {
        case 'Audio':
            return 'bg-[#796D7F] text-white border-[#615667]';
        case 'Video':
            return 'bg-[#1AC18C]/90 text-white border-[#139f72]';
        case 'Network':
            return 'bg-[#22273C] text-white border-[#161a29]';
        case 'Power':
            return 'bg-amber-700 text-white border-amber-800';
        default:
            return 'bg-neutral-500 text-white border-neutral-600';
    }
};

// Exporters
const exportJSON = () => {
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(form.devices, null, 2));
    const dlAnchorElem = document.createElement('a');
    dlAnchorElem.setAttribute("href", dataStr);
    dlAnchorElem.setAttribute("download", `${form.name.toLowerCase().replace(/ /g, '_')}_devices.json`);
    dlAnchorElem.click();
};

const exportCSV = () => {
    const devices = form.devices as Device[];
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "U Position,Brand,Model,Type,U Height,Power (Watts),Heat (BTU/h),Weight (kg)\n";
    
    // Sort from top down for readable inventory list
    const sorted = [...devices].sort((a, b) => b.position - a.position);
    sorted.forEach(dev => {
        csvContent += `${dev.position}U,${dev.brand},"${dev.name}",${dev.type},${dev.u_height},${dev.power_consumption},${dev.heat_dissipation},${dev.weight}\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const dlAnchorElem = document.createElement('a');
    dlAnchorElem.setAttribute("href", encodedUri);
    dlAnchorElem.setAttribute("download", `${form.name.toLowerCase().replace(/ /g, '_')}_inventory.csv`);
    dlAnchorElem.click();
};

const triggerPrint = () => {
    window.print();
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Racks Directory', href: '/racks' },
            { title: 'Visual Builder', href: '' }
        ]
    }
});
</script>

<template>
    <Head :title="`Build: ${form.name}`" />

    <!-- Print Optimized Sheet Container -->
    <div class="print-only hidden p-8 max-w-4xl mx-auto font-sans">
        <div class="flex justify-between items-start border-b pb-6 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">{{ form.name }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ form.description || 'Production rack schematic and specification sheet.' }}</p>
                <div class="flex gap-4 mt-3 text-xs text-slate-600">
                    <span><strong>Size:</strong> {{ form.size }}U</span>
                    <span><strong>Devices:</strong> {{ form.devices.length }}</span>
                </div>
            </div>
            <div class="text-right">
                <span class="text-lg font-bold text-[#1AC18C]">OpsPlatform</span>
                <p class="text-xs text-slate-400">Spec Sheet Auto-generated</p>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-8 items-start">
            <!-- Graphical Rack -->
            <div class="col-span-5 flex flex-col items-center">
                <h3 class="text-sm font-bold text-slate-700 mb-4 uppercase tracking-wider">Elevation Diagram</h3>
                <div class="w-full max-w-[280px] bg-slate-900 border-4 border-slate-700 rounded-xl p-2 relative shadow-inner">
                    <div class="grid relative gap-0.5" :style="{ gridTemplateRows: `repeat(${form.size}, 32px)`, gridTemplateColumns: `repeat(12, 1fr)` }">
                        <!-- U Numbering Slots -->
                        <template v-for="u in form.size" :key="'print-slot-' + u">
                            <!-- Left label -->
                            <div 
                                :style="{ gridRowStart: u, gridRowEnd: u + 1 }"
                                class="col-start-1 col-end-2 text-center text-[7px] font-mono text-slate-500 font-bold border-r border-b border-slate-800/20 py-1 flex items-center justify-center"
                            >
                                U{{ form.size - u + 1 }}
                            </div>

                            <!-- Middle space (only if unoccupied) -->
                            <div 
                                v-if="!getDeviceAtSlot(form.size - u + 1)"
                                :style="{ gridRowStart: u, gridRowEnd: u + 1 }"
                                class="col-start-2 col-end-12 border-b border-slate-800/20 h-full"
                            ></div>

                            <!-- Right label -->
                            <div 
                                :style="{ gridRowStart: u, gridRowEnd: u + 1 }"
                                class="col-start-12 col-end-13 text-center text-[7px] font-mono text-slate-500 font-bold border-l border-b border-slate-800/20 py-1 flex items-center justify-center"
                            >
                                U{{ form.size - u + 1 }}
                            </div>
                        </template>

                        <!-- Placed devices rendering -->
                        <div 
                            v-for="dev in (form.devices as Device[])" 
                            :key="'print-dev-' + dev.id"
                            class="col-start-2 col-end-12 mx-1 my-0.5 rounded border flex flex-col items-center justify-center p-1 text-[8px] font-bold text-white text-center leading-none overflow-hidden"
                            :class="getDeviceColorClass(dev.type)"
                            :style="{ 
                                gridRowStart: form.size - (dev.position + dev.u_height - 1) + 1,
                                gridRowEnd: form.size - dev.position + 2,
                                zIndex: 10 
                            }"
                        >
                            <div class="truncate w-full text-[6px] uppercase tracking-wider opacity-85 leading-none">{{ dev.brand }}</div>
                            <div class="truncate w-full font-bold text-[8px] leading-tight my-0.5">{{ dev.name }}</div>
                            <div class="truncate w-full text-[6px] font-normal opacity-85 leading-none">({{ dev.power_consumption }}W, {{ dev.weight }}kg)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report and Inventory Table -->
            <div class="col-span-7 space-y-6">
                <!-- Summary Metrics -->
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wider text-left">Calculated Metrics</h3>
                    <table class="w-full text-sm border-collapse text-left">
                        <tbody>
                            <tr class="border-b"><td class="py-2 text-slate-500">Total Weight</td><td class="py-2 font-bold">{{ report.totalWeight }} kg</td></tr>
                            <tr class="border-b"><td class="py-2 text-slate-500">Power Consumption</td><td class="py-2 font-bold">{{ report.totalPower }} Watts</td></tr>
                            <tr class="border-b"><td class="py-2 text-slate-500">Heat Output</td><td class="py-2 font-bold">{{ report.totalHeat }} BTU/h</td></tr>
                            <tr class="border-b"><td class="py-2 text-slate-500">Rack Utilization</td><td class="py-2 font-bold">{{ report.uOccupied }} / {{ form.size }} U ({{ report.pctOccupied }}%)</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Device Inventory -->
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wider text-left">Device Inventory</h3>
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="border-b bg-slate-100 text-slate-600 font-bold uppercase"><th class="p-2">Pos</th><th class="p-2">Brand</th><th class="p-2">Model</th><th class="p-2">U</th><th class="p-2">Power</th><th class="p-2">Weight</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="dev in [...form.devices].sort((a,b) => b.position - a.position)" :key="dev.id" class="border-b">
                                <td class="p-2 font-mono font-bold text-slate-700">{{ dev.position }}U</td>
                                <td class="p-2">{{ dev.brand }}</td>
                                <td class="p-2 font-semibold">{{ dev.name }}</td>
                                <td class="p-2">{{ dev.u_height }}U</td>
                                <td class="p-2">{{ dev.power_consumption }}W</td>
                                <td class="p-2">{{ dev.weight }}kg</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Screen Builder (Interactive Workspace) -->
    <div class="flex h-full flex-1 flex-col gap-6 p-6 screen-only bg-background relative z-10">
        <!-- Floating Active Placement Bar -->
        <div v-if="selectedDeviceToPlace" class="fixed top-20 left-1/2 transform -translate-x-1/2 bg-foreground text-background shadow-2xl px-6 py-3.5 rounded-full flex items-center gap-4 z-50 animate-bounce">
            <div class="flex items-center gap-2 text-sm font-bold">
                <span class="inline-block size-3 rounded-full bg-primary animate-ping"></span>
                Placing: <span class="text-primary font-semibold">{{ selectedDeviceToPlace.brand }} {{ selectedDeviceToPlace.name }}</span> ({{ selectedDeviceToPlace.u_height }}U)
            </div>
            <div class="flex gap-2">
                <Button @click="cancelPlacement" variant="outline" size="sm" class="h-8 rounded-full border-background/20 bg-transparent text-background hover:bg-background hover:text-foreground">
                    Cancel
                </Button>
            </div>
        </div>

        <!-- Top Header Controls -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-border/40 pb-4">
            <div class="flex items-center gap-3">
                <Link href="/racks" class="size-9 rounded-xl border border-border/80 flex items-center justify-center hover:bg-muted transition-colors">
                    <ArrowLeft class="size-5 text-foreground" />
                </Link>
                <div>
                    <div class="flex items-center gap-3">
                        <input 
                            v-model="form.name" 
                            type="text" 
                            class="text-xl font-bold bg-transparent border-b border-transparent hover:border-border/60 focus:border-primary focus:outline-none py-0 px-1 w-64 rounded"
                            title="Click to edit name"
                        />
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-muted text-muted-foreground">{{ form.size }}U Rack</span>
                    </div>
                    <input 
                        v-model="form.description" 
                        type="text" 
                        placeholder="Click to add description..."
                        class="text-xs text-muted-foreground bg-transparent border-b border-transparent hover:border-border/60 focus:border-primary focus:outline-none py-0 px-1 w-96 rounded mt-1"
                    />
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <Button @click="saveLayout" :disabled="form.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                    <Check v-if="form.wasSuccessful" class="mr-2 size-4" />
                    <Save v-else class="mr-2 size-4" />
                    {{ form.processing ? 'Saving...' : form.wasSuccessful ? 'Saved Layout!' : 'Save Layout' }}
                </Button>

                <Button @click="triggerPrint" variant="outline" class="rounded-xl cursor-pointer">
                    <Printer class="mr-2 size-4" />
                    Print
                </Button>

                <div class="relative group">
                    <Button variant="outline" class="rounded-xl cursor-pointer">
                        <Download class="mr-2 size-4" />
                        Export
                    </Button>
                    <!-- Dropdown Content for export -->
                    <div class="absolute right-0 top-11 hidden group-hover:block bg-card border border-border rounded-xl shadow-xl w-40 overflow-hidden z-20 py-1">
                        <button @click="exportJSON" class="w-full text-left px-4 py-2 hover:bg-muted text-xs font-semibold text-foreground">Export JSON</button>
                        <button @click="exportCSV" class="w-full text-left px-4 py-2 hover:bg-muted text-xs font-semibold text-foreground">Export CSV</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inner Layout -->
        <div class="grid grid-cols-12 gap-6 items-start grow">
            
            <!-- 1. Catalog Panel (Left - 3 Cols) -->
            <div class="col-span-12 lg:col-span-3 bg-card border border-border/60 rounded-2xl p-4 flex flex-col gap-4 max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Sliders class="size-4 text-primary" />
                        Device Catalog
                    </h3>
                    <Button 
                        @click="showCustomForm = !showCustomForm" 
                        size="sm" 
                        variant="ghost" 
                        class="h-7 px-2 text-primary hover:bg-primary/10 rounded-lg cursor-pointer"
                    >
                        <Plus class="size-4 mr-1" /> Add Device
                    </Button>
                </div>

                <!-- Real-time Search Field -->
                <div class="relative w-full">
                    <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 size-4 text-muted-foreground" />
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Search catalog..." 
                        class="pl-9 h-9 text-xs rounded-xl bg-muted/40 border-border/40 focus-visible:ring-primary w-full"
                    />
                </div>

                <!-- Custom Form Overlay inline -->
                <div v-if="showCustomForm" class="bg-muted p-4 rounded-xl border border-border/60 space-y-3 animate-in slide-in-from-top-4 duration-200">
                    <h4 class="text-xs font-bold uppercase text-foreground">Add Device to Catalog</h4>
                    
                    <div class="space-y-1">
                        <Label class="text-[10px]" for="c-brand">Brand</Label>
                        <Input id="c-brand" v-model="catalogForm.brand" placeholder="e.g. Behringer" class="h-8 text-xs rounded-lg" required />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-[10px]" for="c-name">Model Name</Label>
                        <Input id="c-name" v-model="catalogForm.name" placeholder="e.g. MX-800" class="h-8 text-xs rounded-lg" required />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-[10px]">Type</Label>
                            <select v-model="catalogForm.type" class="flex h-8 w-full rounded-lg border border-input bg-background px-2 py-1 text-xs focus-visible:outline-none">
                                <option>Audio</option>
                                <option>Video</option>
                                <option>Network</option>
                                <option>Power</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px]">Height</Label>
                            <select v-model="catalogForm.u_height" class="flex h-8 w-full rounded-lg border border-input bg-background px-2 py-1 text-xs focus-visible:outline-none">
                                <option :value="1">1U</option>
                                <option :value="2">2U</option>
                                <option :value="3">3U</option>
                                <option :value="4">4U</option>
                                <option :value="5">5U</option>
                                <option :value="6">6U</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-[10px]" for="c-pow">Power (W)</Label>
                            <Input id="c-pow" v-model="catalogForm.power_consumption" type="number" class="h-8 text-xs rounded-lg" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px]" for="c-wt">Weight (kg)</Label>
                            <Input id="c-wt" v-model="catalogForm.weight" type="number" step="0.1" class="h-8 text-xs rounded-lg" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button @click="showCustomForm = false" size="sm" variant="outline" class="h-7 text-xs rounded-lg cursor-pointer">Cancel</Button>
                        <Button @click="createCustomDevice" size="sm" :disabled="catalogForm.processing" class="h-7 text-xs bg-primary text-primary-foreground rounded-lg cursor-pointer">
                            {{ catalogForm.processing ? 'Adding...' : 'Add' }}
                        </Button>
                    </div>
                </div>

                <!-- Tabs Filter -->
                <div class="flex flex-wrap gap-1 bg-muted p-1 rounded-xl">
                    <button 
                        v-for="cat in ['All', 'Audio', 'Video', 'Network', 'Power']" 
                        :key="cat"
                        @click="activeCategory = cat"
                        class="px-2.5 py-1 text-xs font-semibold rounded-lg grow transition-all"
                        :class="activeCategory === cat ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Devices Catalog List -->
                <div class="space-y-2 max-h-[50vh] overflow-y-auto pr-1">
                    <div 
                        v-for="item in filteredCatalog" 
                        :key="item.brand + '_' + item.name"
                        class="bg-muted/40 hover:bg-muted border border-border/40 hover:border-primary/20 p-3 rounded-xl cursor-pointer transition-all flex flex-col gap-1.5"
                        @click="selectDeviceForPlacement(item)"
                    >
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-foreground">{{ item.brand }}</span>
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-background" :class="getDeviceColorClass(item.type)">
                                {{ item.u_height }}U
                            </span>
                        </div>
                        <div class="font-semibold text-xs text-foreground truncate">{{ item.name }}</div>
                        <div class="flex justify-between text-[10px] text-muted-foreground">
                            <span>{{ item.power_consumption }}W</span>
                            <span>{{ item.weight }}kg</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Visual Builder Enclosure (Middle - 5 Cols) -->
            <div class="col-span-12 md:col-span-7 lg:col-span-5 flex flex-col items-center gap-4">
                <div class="text-xs text-muted-foreground text-center flex items-center gap-1.5 font-medium">
                    <HelpCircle class="size-4" />
                    <span>Click device in catalog, then select slot in rack to mount.</span>
                </div>

                <!-- 19 Inch Rack Enclosure Frame -->
                <div class="w-full max-w-[420px] bg-slate-900 border-[10px] border-slate-800 rounded-3xl p-3 relative shadow-2xl flex flex-col">
                    <!-- Rack top ventilation grill graphic -->
                    <div class="w-full h-4 border-b border-slate-700/60 flex justify-between px-6 opacity-30 mb-2">
                        <span v-for="i in 8" :key="i" class="w-1.5 h-full bg-slate-500 rounded-sm"></span>
                    </div>

                    <!-- Inner Rack Space Grid -->
                    <div class="grid grid-cols-12 relative bg-[#161a29]/65 p-2 rounded-xl border border-slate-800/80" :style="{ gridTemplateRows: `repeat(${form.size}, 46px)`, gap: '4px' }">
                        
                        <!-- Grid loop of U positions / Background slots -->
                        <template v-for="u in form.size" :key="'slot-' + u">
                            <!-- Left label -->
                            <div 
                                :style="{ gridRowStart: u, gridRowEnd: u + 1 }"
                                class="col-start-1 col-end-2 flex items-center justify-center font-mono text-[9px] font-bold text-slate-500 select-none h-full border-r border-b border-slate-800/30 py-2"
                            >
                                U{{ form.size - u + 1 }}
                            </div>

                            <!-- Middle Bay container (only if unoccupied) -->
                            <div 
                                v-if="!getDeviceAtSlot(form.size - u + 1)"
                                :style="{ gridRowStart: u, gridRowEnd: u + 1 }"
                                class="col-start-2 col-end-12 h-full rounded transition-colors relative flex items-center justify-center"
                                :class="{
                                    'bg-green-500/10 cursor-pointer border border-dashed border-green-500/30': selectedDeviceToPlace && isPlacementValid(form.size - u + 1, selectedDeviceToPlace.u_height),
                                    'bg-red-500/5 cursor-not-allowed border border-dashed border-red-500/20': selectedDeviceToPlace && !isPlacementValid(form.size - u + 1, selectedDeviceToPlace.u_height),
                                    'hover:bg-slate-800/25 border-b border-slate-800/30 cursor-pointer': !selectedDeviceToPlace
                                }"
                                @mouseenter="hoverPosition = (form.size - u + 1)"
                                @mouseleave="hoverPosition = null"
                                @click="placeDevice(form.size - u + 1)"
                            >
                                <!-- Placement Hover Preview Graphic -->
                                <div 
                                    v-if="selectedDeviceToPlace && hoverPosition === (form.size - u + 1) && isPlacementValid(form.size - u + 1, selectedDeviceToPlace.u_height)"
                                    class="absolute inset-x-1 inset-y-0.5 rounded border-2 border-dashed border-green-500 bg-green-500/20 text-green-500 flex items-center justify-center font-bold text-[10px] pointer-events-none"
                                    :style="{ 
                                        height: `${selectedDeviceToPlace.u_height * 46 + (selectedDeviceToPlace.u_height - 1) * 4 - 4}px`, 
                                        bottom: 'auto',
                                        top: '0.5px',
                                        transform: `translateY(-${(selectedDeviceToPlace.u_height - 1) * 50}px)`,
                                        zIndex: 20
                                    }"
                                >
                                    Place: {{ selectedDeviceToPlace.brand }} {{ selectedDeviceToPlace.name }} ({{ selectedDeviceToPlace.u_height }}U)
                                </div>
                            </div>

                            <!-- Right label -->
                            <div 
                                :style="{ gridRowStart: u, gridRowEnd: u + 1 }"
                                class="col-start-12 col-end-13 flex items-center justify-center font-mono text-[9px] font-bold text-slate-500 select-none h-full border-l border-b border-slate-800/30 py-2"
                            >
                                U{{ form.size - u + 1 }}
                            </div>
                        </template>

                        <!-- Placed device overlays -->
                        <div 
                            v-for="device in (form.devices as Device[])" 
                            :key="device.id"
                            class="col-start-2 col-end-12 mx-1 my-0.5 rounded-lg border-2 flex items-center justify-between px-4 text-xs tracking-tight shadow-lg"
                            :class="getDeviceColorClass(device.type)"
                            :style="{
                                gridRowStart: form.size - (device.position + device.u_height - 1) + 1,
                                gridRowEnd: form.size - device.position + 2,
                                zIndex: 10
                            }"
                        >
                            <div class="flex items-center gap-x-2 text-left grow min-w-0 mr-2 py-1 overflow-hidden">
                                <span class="text-[9px] uppercase tracking-wider opacity-90 font-bold bg-black/15 px-1.5 py-0.5 rounded shrink-0">{{ device.brand }}</span>
                                <span class="font-bold text-xs truncate leading-tight grow md:grow-0">{{ device.name }}</span>
                                <span class="text-[10px] opacity-85 font-semibold shrink-0 font-mono">({{ device.power_consumption }}W, {{ device.weight }}kg)</span>
                            </div>
                            
                            <button 
                                @click.stop="removeDevice(device.id)"
                                class="text-white hover:text-red-400 p-1 hover:bg-black/20 rounded transition-colors cursor-pointer shrink-0"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Report & Specifications (Right - 4 Cols) -->
            <div class="col-span-12 md:col-span-5 lg:col-span-4 flex flex-col gap-6 max-h-[80vh] overflow-y-auto">
                <!-- Rack Report Card -->
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-5">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                        <Sliders class="size-4" />
                        Rack Analysis
                    </h3>

                    <!-- Weight analysis -->
                    <div class="flex items-start gap-3">
                        <div class="size-9 rounded-lg bg-muted flex items-center justify-center text-muted-foreground">
                            <Scale class="size-5" />
                        </div>
                        <div class="grow space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-muted-foreground">Total Weight:</span>
                                <span class="text-foreground font-bold">{{ report.totalWeight }} kg</span>
                            </div>
                            <!-- COG Alert -->
                            <div v-if="report.isTopHeavy" class="bg-amber-500/10 border border-amber-500/20 p-2.5 rounded-xl text-[11px] text-amber-600 flex items-start gap-2 mt-1 leading-normal font-semibold">
                                <AlertTriangle class="size-4 shrink-0 mt-0.5" />
                                <div>
                                    Top-Heavy Load: COG is at U{{ report.centerOfGravity }}. 
                                    Recommend moving UPS/heavier systems to bottom.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Power analysis -->
                    <div class="flex items-start gap-3">
                        <div class="size-9 rounded-lg bg-muted flex items-center justify-center text-muted-foreground">
                            <Zap class="size-5" />
                        </div>
                        <div class="grow space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-muted-foreground">Power Consumption:</span>
                                <span class="text-foreground font-bold">{{ report.totalPower }} W</span>
                            </div>
                            <div class="text-[10px] text-muted-foreground">
                                Capacity: {{ Math.round((report.totalPower / 1440) * 100) }}% of 15A 120V Circuit (1440W max load)
                            </div>
                            <!-- High Load Alert -->
                            <div v-if="report.totalPower > 1440" class="bg-red-500/10 border border-red-500/25 p-2 rounded-xl text-[10px] text-red-500 flex items-center gap-1.5 font-bold">
                                <AlertTriangle class="size-3.5" />
                                <span>Power exceeds safe 15A single outlet load.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Thermal load analysis -->
                    <div class="flex items-start gap-3">
                        <div class="size-9 rounded-lg bg-muted flex items-center justify-center text-muted-foreground">
                            <Thermometer class="size-5" />
                        </div>
                        <div class="grow space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-muted-foreground">Thermal Dissipation:</span>
                                <span class="text-foreground font-bold">{{ report.totalHeat }} BTU/h</span>
                            </div>
                            <!-- Cooling Advisory -->
                            <div v-if="report.totalHeat > 1500" class="bg-blue-500/10 border border-blue-500/20 p-2.5 rounded-xl text-[10px] text-blue-600 flex items-start gap-2 leading-tight font-semibold">
                                <Thermometer class="size-4 shrink-0 mt-0.5" />
                                <span>Thermal load is high. Installing active exhaust fans (1U fan tray) at the top of the rack is recommended.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Space occupied analysis -->
                    <div class="flex items-start gap-3">
                        <div class="size-9 rounded-lg bg-muted flex items-center justify-center text-muted-foreground">
                            <Grid class="size-5" />
                        </div>
                        <div class="grow space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-muted-foreground">Space Utilization:</span>
                                <span class="text-foreground font-bold">{{ report.uOccupied }} / {{ form.size }} U ({{ report.pctOccupied }}%)</span>
                            </div>
                            <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden">
                                <div class="bg-[#1AC18C] h-full" :style="{ width: `${report.pctOccupied}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Installed Inventory List Card -->
                <div class="bg-card border border-border/60 rounded-2xl p-5 space-y-3">
                    <h3 class="font-bold text-sm uppercase tracking-wider text-muted-foreground">
                        Inventory List ({{ form.devices.length }} items)
                    </h3>
                    
                    <div v-if="form.devices.length > 0" class="space-y-2 max-h-[30vh] overflow-y-auto pr-1">
                        <div 
                            v-for="dev in [...form.devices].sort((a,b) => b.position - a.position)" 
                            :key="dev.id" 
                            class="flex justify-between items-center p-2 bg-muted/30 border border-border/30 rounded-lg text-xs"
                        >
                            <div>
                                <span class="font-mono text-[10px] font-bold px-1 py-0.5 rounded bg-muted text-muted-foreground mr-2">{{ dev.position }}U</span>
                                <span class="font-semibold text-foreground">{{ dev.brand }} {{ dev.name }}</span>
                            </div>
                            <button @click="removeDevice(dev.id)" class="text-muted-foreground hover:text-red-500 transition-colors">
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-xs text-muted-foreground italic text-center py-6">
                        No devices installed in this rack layout.
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style>
/* Optimized Print Styling rules for physical Spec Sheet printing */
@media print {
    .screen-only {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    body {
        background-color: white !important;
        color: black !important;
    }
}
</style>
