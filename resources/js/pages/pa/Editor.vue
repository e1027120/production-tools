<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { 
    Volume2, 
    Plus, 
    Trash2, 
    Save, 
    Sliders, 
    Zap, 
    Activity, 
    Layers, 
    ArrowLeft, 
    Download, 
    Info,
    CheckCircle2,
    Settings,
    Shield
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    design: {
        id: number;
        name: string;
        description: string | null;
        data: {
            zones: Array<{
                id: string;
                name: string;
                type: 'sub' | 'top' | 'monitor' | 'fill' | 'array';
                qty: number;
                impedance: number;
                power_rms: number;
                power_peak?: number;
                sensitivity: number;
                wiring: 'parallel' | 'series' | 'series_parallel';
                target_distance: number;
            }>;
            selectedStrategy: 'discrete' | 'parallel' | 'bridged' | 'biamp';
            headroomFactor: number;
        };
        created_by: string;
        created_at: string;
    };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any).currentChurch?.pivot?.role || 'User');
const isReadOnly = computed(() => userRole.value === 'User');

// Top level Inertia Form Setup using Flat Structure to avoid "data" name collision
const form = useForm({
    name: props.design.name,
    description: props.design.description || '',
    zones: props.design.data?.zones || [],
    selectedStrategy: props.design.data?.selectedStrategy || 'discrete',
    headroomFactor: props.design.data?.headroomFactor || 1.5,
});

const activeZoneId = ref<string | null>(form.zones[0]?.id || null);
const isSaved = ref(false);

const activeZone = computed(() => {
    const zone = form.zones.find(z => z.id === activeZoneId.value) || null;
    if (zone && zone.power_peak === undefined) {
        zone.power_peak = zone.power_rms * 4;
    }
    return zone;
});

// Add speaker zone
const addZone = () => {
    const id = 'zone_' + Date.now();
    const newZone = {
        id,
        name: `Zone #${form.zones.length + 1}`,
        type: 'top' as const,
        qty: 2,
        impedance: 8,
        power_rms: 400,
        power_peak: 1600,
        sensitivity: 97,
        wiring: 'parallel' as const,
        target_distance: 10,
    };
    form.zones.push(newZone);
    activeZoneId.value = id;
};

const deleteZone = (id: string) => {
    form.zones = form.zones.filter(z => z.id !== id);
    if (activeZoneId.value === id) {
        activeZoneId.value = form.zones[0]?.id || null;
    }
};

// Calculations
const calculateLoadImpedance = (zone: typeof form.zones[0]) => {
    const qty = Math.max(1, zone.qty);
    const imp = zone.impedance;
    if (zone.wiring === 'parallel') {
        return Number((imp / qty).toFixed(2));
    } else if (zone.wiring === 'series') {
        return Number((imp * qty).toFixed(2));
    } else if (zone.wiring === 'series_parallel') {
        if (qty < 4) return Number((imp / qty).toFixed(2));
        return Number((2 * (imp / (qty / 2))).toFixed(2));
    }
    return imp;
};

const calculateTotalRMS = (zone: typeof form.zones[0]) => {
    return zone.power_rms * zone.qty;
};

const calculateTargetAmpPower = (zone: typeof form.zones[0]) => {
    return Math.round(calculateTotalRMS(zone) * form.headroomFactor);
};

const calculateMaxSPL = (zone: typeof form.zones[0]) => {
    const qty = Math.max(1, zone.qty);
    const ampPower = calculateTargetAmpPower(zone);
    if (ampPower <= 0) return { continuous: 0, peak: 0 };

    const sens = zone.sensitivity;
    // Continuous SPL: based on matched amp power
    const singleMaxContSPL = sens + 10 * Math.log10(ampPower / qty);
    
    // Peak SPL: based on speaker cabinet peak spec limit
    const peakRating = zone.power_peak || (zone.power_rms * 4);
    const singleMaxPeakSPL = sens + 10 * Math.log10(peakRating);

    const coherent = zone.type === 'array' || zone.type === 'sub';
    const summationDb = coherent ? 20 * Math.log10(qty) : 10 * Math.log10(qty);

    const contAt1m = singleMaxContSPL + summationDb - (10 * Math.log10(qty));
    const peakAt1m = singleMaxPeakSPL + summationDb - (10 * Math.log10(qty));

    const dist = Math.max(1, zone.target_distance);
    const attenuation = 20 * Math.log10(dist);

    return {
        continuous: Number((contAt1m - attenuation).toFixed(1)),
        peak: Number((peakAt1m - attenuation).toFixed(1))
    };
};

// Thomann Scraped Power Amplifiers Catalog
const AMP_CATALOG = [
    { name: 'the t.amp E-400', power_8: 120, power_4: 190, bridge_8: 380, price: '€129', brand: 'the t.amp' },
    { name: 'the t.amp E-800', power_8: 360, power_4: 490, bridge_8: 990, price: '€199', brand: 'the t.amp' },
    { name: 'the t.amp E-1200', power_8: 500, power_4: 800, bridge_8: 1600, price: '€289', brand: 'the t.amp' },
    { name: 'the t.amp E-1500', power_8: 850, power_4: 1220, bridge_8: 2440, price: '€349', brand: 'the t.amp' },
    { name: 'the t.amp TSA 4-700', power_8: 490, power_4: 810, bridge_8: 1600, price: '€449', brand: 'the t.amp' },
    { name: 'the t.amp TSA 4-1300', power_8: 1220, power_4: 1670, bridge_8: 4000, price: '€599', brand: 'the t.amp' },
    { name: 'Behringer NX3000', power_8: 440, power_4: 900, bridge_8: 1500, bridge_4: 3000, price: '€279', brand: 'Behringer' },
    { name: 'Behringer NX6000', power_8: 1600, power_4: 3000, price: '€499', brand: 'Behringer' },
    { name: 'Crown XLS 1502', power_8: 300, power_4: 525, bridge_8: 1050, bridge_4: 1550, price: '€429', brand: 'Crown' }
];

const getThomannRecommendations = (targetPower: number, loadImpedance: number, bridged: boolean) => {
    const matches: Array<{ name: string; specs: string; price: string; link: string }> = [];
    const isFourOhm = loadImpedance <= 5.5;

    AMP_CATALOG.forEach(amp => {
        if (bridged) {
            if (!isFourOhm && amp.bridge_8 && amp.bridge_8 >= targetPower) {
                matches.push({
                    name: amp.name,
                    specs: `${amp.bridge_8}W Bridged @ 8Ω`,
                    price: amp.price,
                    link: "https://www.thomann.at/search_dir.html?sw=" + encodeURIComponent(amp.name)
                });
            } else if (isFourOhm && (amp.bridge_4 || amp.bridge_8) && (amp.bridge_4 || amp.bridge_8) >= targetPower) {
                const power = amp.bridge_4 || amp.bridge_8 || 0;
                const load = amp.bridge_4 ? 4 : 8;
                matches.push({
                    name: amp.name,
                    specs: `${power}W Bridged @ ${load}Ω`,
                    price: amp.price,
                    link: "https://www.thomann.at/search_dir.html?sw=" + encodeURIComponent(amp.name)
                });
            }
        } else {
            if (!isFourOhm && amp.power_8 >= targetPower) {
                matches.push({
                    name: amp.name,
                    specs: `${amp.power_8}W RMS @ 8Ω`,
                    price: amp.price,
                    link: "https://www.thomann.at/search_dir.html?sw=" + encodeURIComponent(amp.name)
                });
            } else if (isFourOhm && amp.power_4 && amp.power_4 >= targetPower) {
                matches.push({
                    name: amp.name,
                    specs: `${amp.power_4}W RMS @ 4Ω`,
                    price: amp.price,
                    link: "https://www.thomann.at/search_dir.html?sw=" + encodeURIComponent(amp.name)
                });
            }
        }
    });

    matches.sort((a, b) => {
        const powerA = parseInt(a.specs);
        const powerB = parseInt(b.specs);
        return powerA - powerB;
    });

    return matches.slice(0, 2);
};

// Strategic Recommendations Generator
const strategies = computed(() => {
    return [
        {
            key: 'discrete',
            name: 'Discrete Channels (Stereo/Zone Match)',
            description: 'Assigns dedicated amplifier channels to each speaker zone. Recommends standard headroom amp sizing.',
            pros: ['Maximum control', 'Individual EQ & alignment delay per zone', 'Safer operation'],
            cons: ['Highest hardware cost', 'Requires more rack spaces & channels'],
            suitability: 'Main front-of-house arrays, stereo fills, separate stage monitor mixes.',
        },
        {
            key: 'parallel',
            name: 'High-Density Parallel Chaining',
            description: 'Chains speakers in parallel to drop load impedance down to 4 or 2 ohms, maximizing amplifier channel density.',
            pros: ['Lowest hardware cost', 'Fewer amplifiers required'],
            cons: ['Runs amplifiers hotter', 'No individual speaker control within a zone', 'Heavier speaker cables required'],
            suitability: 'Distributed ceiling arrays, side-fills, parallel subwoofer clusters.',
        },
        {
            key: 'bridged',
            name: 'Bridged Mono Sub-Drive',
            description: 'Bridges pairs of amplifier channels to drive subwoofers with extreme voltage swing and dynamic headroom.',
            pros: ['Extreme low-end punch', 'Extracts maximum power from standard amps'],
            cons: ['Limits setup to mono per amp pair', 'Impedance load on each channel is halved'],
            suitability: 'Heavy subwoofers, low-frequency pressure arrays.',
        },
        {
            key: 'biamp',
            name: 'Multi-Way Active Bi-Amp / Tri-Amp',
            description: 'Splits full-range boxes into High-Frequency (HF) and Low-Frequency (LF) circuits driven by active DSP crossovers.',
            pros: ['Best acoustic performance', 'Prevents clipping/burning compression drivers', 'Phase alignment precision'],
            cons: ['Requires double the amplifier channels', 'Requires professional measurement & tuning'],
            suitability: 'High-power concert arrays, professional monitor wedges.',
        }
    ];
});

// Amp outputs recommendation based on active zones and selected strategy
const ampRecommendations = computed(() => {
    const channels: Array<{
        zoneName: string;
        speakerInfo: string;
        targetPower: number;
        loadImpedance: number;
        recommendation: string;
        matchingAmps: Array<{ name: string; specs: string; price: string; link: string }>;
        bridged?: boolean;
        biampHF?: boolean;
        biampLF?: boolean;
    }> = [];

    form.zones.forEach(zone => {
        const imp = calculateLoadImpedance(zone);
        const power = calculateTargetAmpPower(zone);

        if (form.selectedStrategy === 'discrete') {
            const matches = getThomannRecommendations(power, imp, false);
            channels.push({
                zoneName: `${zone.name}`,
                speakerInfo: `${zone.qty}x ${zone.type.toUpperCase()} (${zone.wiring})`,
                targetPower: power,
                loadImpedance: imp,
                recommendation: `Provide 1x amp channel capable of delivering ≥ ${power}W RMS continuous at ${imp} ohms.`,
                matchingAmps: matches
            });
        } 
        else if (form.selectedStrategy === 'parallel') {
            const matches = getThomannRecommendations(power, imp, false);
            channels.push({
                zoneName: `${zone.name} (Parallel)`,
                speakerInfo: `${zone.qty}x ${zone.type.toUpperCase()} wired in Parallel`,
                targetPower: power,
                loadImpedance: imp,
                recommendation: `Provide 1x high-current channel capable of delivering ≥ ${power}W RMS at ${imp} ohms. Verify amp is stable at ${imp} ohms.`,
                matchingAmps: matches
            });
        } 
        else if (form.selectedStrategy === 'bridged') {
            if (zone.type === 'sub') {
                const matches = getThomannRecommendations(power, imp, true);
                channels.push({
                    zoneName: `${zone.name} (Bridged Mono)`,
                    speakerInfo: `${zone.qty}x SUB (${zone.wiring})`,
                    targetPower: power,
                    loadImpedance: imp,
                    bridged: true,
                    recommendation: `Bridge 2x channels of a stereo amplifier to deliver ≥ ${power}W mono at ${imp} ohms. Amp must be stable at ${imp} ohms bridged.`,
                    matchingAmps: matches
                });
            } else {
                const matches = getThomannRecommendations(power, imp, false);
                channels.push({
                    zoneName: `${zone.name}`,
                    speakerInfo: `${zone.qty}x ${zone.type.toUpperCase()}`,
                    targetPower: power,
                    loadImpedance: imp,
                    recommendation: `Provide 1x standard channel capable of delivering ≥ ${power}W RMS at ${imp} ohms.`,
                    matchingAmps: matches
                });
            }
        } 
        else if (form.selectedStrategy === 'biamp') {
            if (zone.type === 'top' || zone.type === 'array') {
                const lfPower = Math.round(power * 0.8);
                const hfPower = Math.round(power * 0.2);
                const lfMatches = getThomannRecommendations(lfPower, imp, false);
                const hfMatches = getThomannRecommendations(hfPower, imp, false);
                channels.push({
                    zoneName: `${zone.name} - Low/Mid Frequency (LF)`,
                    speakerInfo: `${zone.qty}x LF Drivers (Parallel)`,
                    targetPower: lfPower,
                    loadImpedance: imp,
                    biampLF: true,
                    recommendation: `Provide 1x channel delivering ≥ ${lfPower}W RMS at ${imp} ohms. Crossover: 80Hz - 1.6kHz.`,
                    matchingAmps: lfMatches
                });
                channels.push({
                    zoneName: `${zone.name} - High Frequency (HF)`,
                    speakerInfo: `${zone.qty}x HF Compression Drivers`,
                    targetPower: hfPower,
                    loadImpedance: imp,
                    biampHF: true,
                    recommendation: `Provide 1x channel delivering ≥ ${hfPower}W RMS at ${imp} ohms. Crossover: 1.6kHz - 20kHz with active peak limiter.`,
                    matchingAmps: hfMatches
                });
            } else {
                const matches = getThomannRecommendations(power, imp, false);
                channels.push({
                    zoneName: `${zone.name}`,
                    speakerInfo: `${zone.qty}x ${zone.type.toUpperCase()}`,
                    targetPower: power,
                    loadImpedance: imp,
                    recommendation: `Provide 1x standard channel capable of delivering ≥ ${power}W RMS at ${imp} ohms.`,
                    matchingAmps: matches
                });
            }
        }
    });

    return channels;
});

// Save Design payload
const saveDesign = () => {
    form
        .transform((data) => ({
            name: data.name,
            description: data.description,
            data: {
                zones: data.zones,
                selectedStrategy: data.selectedStrategy,
                headroomFactor: data.headroomFactor,
            }
        }))
        .put(`/pa-systems/${props.design.id}`, {
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
    csvContent += "Zone ID,Zone Name,Speaker Type,Quantity,Unit Impedance (ohms),Single Speaker RMS (W),Single Speaker Peak (W),Sensitivity (dB),Wiring,Total Impedance (ohms),Total RMS Power (W),Amp Target Power (W),SPL Target Distance (m),Continuous SPL (dB),Peak SPL (dB)\r\n";
    
    form.zones.forEach(z => {
        const totalImp = calculateLoadImpedance(z);
        const totalRms = calculateTotalRMS(z);
        const ampPower = calculateTargetAmpPower(z);
        const spl = calculateMaxSPL(z);
        const peak = z.power_peak || (z.power_rms * 4);
        csvContent += `"${z.id}","${z.name}","${z.type}",${z.qty},${z.impedance},${z.power_rms},${peak},${z.sensitivity},"${z.wiring}",${totalImp},${totalRms},${ampPower},${z.target_distance},${spl.continuous},${spl.peak}\r\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `${form.name.replace(/\s+/g, '_')}_PA_BOM.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'PA Systems', href: '/pa-systems' },
            { title: 'Editor', href: '' }
        ]
    }
});
</script>

<template>
    <Head :title="`${form.name} - PA Designer`" />

    <div class="flex h-full flex-col gap-4 p-4 lg:p-6 overflow-hidden">
        <!-- Top Toolbar Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-border/40 pb-3">
            <div class="flex items-center gap-2">
                <Link href="/pa-systems" class="size-8.5 rounded-lg border border-border flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted/40 transition-colors">
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <input 
                        v-model="form.name" 
                        class="font-bold text-lg bg-transparent border-0 focus:ring-0 p-0 text-foreground" 
                        placeholder="PA System Name" 
                        :disabled="isReadOnly"
                    />
                    <div class="text-[10px] text-muted-foreground font-mono flex items-center gap-1.5 mt-0.5">
                        <span>Created by: {{ design.created_by }}</span>
                        <span>•</span>
                        <span>Strategy: {{ form.selectedStrategy.toUpperCase() }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <Button 
                    @click="exportBOM" 
                    variant="outline" 
                    size="sm" 
                    class="rounded-xl h-9 text-xs gap-1.5 cursor-pointer font-semibold border-border/60 hover:bg-muted/40"
                >
                    <Download class="size-4" /> Export CSV
                </Button>
                <Button 
                    v-if="!isReadOnly"
                    @click="saveDesign" 
                    size="sm" 
                    class="bg-[#1AC18C] hover:bg-[#1AC18C]/90 text-white rounded-xl h-9 text-xs gap-1.5 cursor-pointer font-bold"
                >
                    <Save class="size-4" />
                    {{ isSaved ? 'Saved!' : 'Save Design' }}
                </Button>
            </div>
        </div>

        <!-- Main Workspace splits -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 flex-1 overflow-hidden min-h-0">
            <!-- Left Grid: Speaker Zones (cols 7) -->
            <div class="lg:col-span-7 flex flex-col gap-4 overflow-y-auto pr-1">
                <div class="flex justify-between items-center">
                    <h2 class="font-bold text-sm text-foreground flex items-center gap-1.5">
                        <Sliders class="size-4 text-[#1AC18C]" /> Configured Speaker Zones
                    </h2>
                    <Button 
                        v-if="!isReadOnly"
                        @click="addZone" 
                        size="xs" 
                        variant="outline" 
                        class="rounded-lg h-7 text-[10px] gap-1 cursor-pointer border-dashed border-primary/40 text-primary hover:bg-primary/5"
                    >
                        <Plus class="size-3" /> Add Zone
                    </Button>
                </div>

                <!-- Empty zones placeholder -->
                <div v-if="form.zones.length === 0" class="flex flex-col justify-center items-center text-center p-8 border border-dashed border-border/80 rounded-2xl bg-card/50 min-h-[220px] space-y-2">
                    <Volume2 class="size-8 text-muted-foreground animate-pulse" />
                    <h4 class="font-semibold text-xs text-foreground">No Speaker Zones Configured</h4>
                    <p class="text-[10px] text-muted-foreground max-w-xs">Click "Add Zone" above to define speaker clusters (e.g. Subs, FOH tops) for your calculations.</p>
                </div>

                <!-- Zones Grid List -->
                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <div 
                        v-for="zone in form.zones" 
                        :key="zone.id"
                        @click="activeZoneId = zone.id"
                        class="p-4 rounded-xl border cursor-pointer transition-all duration-300 relative group flex flex-col justify-between min-h-[140px]"
                        :class="[
                            activeZoneId === zone.id 
                                ? 'bg-muted/30 border-[#1AC18C]/80 ring-1 ring-[#1AC18C]/30 shadow-md' 
                                : 'bg-card border-border/60 hover:border-border-hover/80 hover:bg-muted/10'
                        ]"
                    >
                        <div>
                            <div class="flex justify-between items-start">
                                <span class="font-bold text-xs text-foreground leading-tight truncate max-w-[80%]">
                                    {{ zone.name }}
                                </span>
                                <button 
                                    v-if="!isReadOnly"
                                    @click.stop="deleteZone(zone.id)"
                                    class="text-muted-foreground hover:text-red-500 hover:bg-red-500/10 p-1 rounded-md transition-colors cursor-pointer shrink-0 opacity-0 group-hover:opacity-100"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                            
                            <div class="flex items-center gap-1.5 mt-1.5">
                                <span class="text-[9px] font-bold uppercase tracking-wider bg-primary/10 text-primary px-1.5 py-0.5 rounded">
                                    {{ zone.type }}
                                </span>
                                <span class="text-[10px] text-muted-foreground">
                                    {{ zone.qty }}x • {{ zone.impedance }}Ω • {{ zone.power_rms }}W
                                </span>
                            </div>
                        </div>

                        <!-- Real-time metrics values -->
                        <div class="mt-4 pt-3 border-t border-border/40 grid grid-cols-3 gap-1.5 text-center">
                            <div>
                                <span class="text-[8px] uppercase font-bold text-muted-foreground block">Load</span>
                                <span class="font-mono text-xs font-bold text-foreground">{{ calculateLoadImpedance(zone) }}Ω</span>
                            </div>
                            <div>
                                <span class="text-[8px] uppercase font-bold text-muted-foreground block">RMS</span>
                                <span class="font-mono text-xs font-bold text-foreground">{{ calculateTotalRMS(zone) }}W</span>
                            </div>
                            <div>
                                <span class="text-[8px] uppercase font-bold text-muted-foreground block">SPL Cont/Peak</span>
                                <span class="font-mono text-[10px] font-bold text-[#1AC18C]">{{ calculateMaxSPL(zone).continuous }}/{{ calculateMaxSPL(zone).peak }} dB</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Strategic Matching Recommendations Section -->
                <div class="mt-4 pt-4 border-t border-border/40 space-y-4">
                    <h2 class="font-bold text-sm text-foreground flex items-center gap-1.5">
                        <CheckCircle2 class="size-4.5 text-[#1AC18C]" /> Recommended Amp Channels
                    </h2>
                    
                    <div class="space-y-2 bg-muted/40 p-4 rounded-xl border border-border/50">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] uppercase font-bold text-muted-foreground">Strategy:</span>
                            <div class="flex gap-1.5">
                                <button 
                                    v-for="strat in strategies" 
                                    :key="strat.key"
                                    @click="form.selectedStrategy = strat.key"
                                    class="px-2 py-1 rounded text-[10px] font-bold transition-all cursor-pointer border"
                                    :class="[
                                        form.selectedStrategy === strat.key 
                                            ? 'bg-[#1AC18C] text-white border-transparent' 
                                            : 'bg-card text-muted-foreground border-border hover:bg-muted'
                                    ]"
                                >
                                    {{ strat.key.toUpperCase() }}
                                </button>
                            </div>
                        </div>

                        <!-- Strategy summary description -->
                        <div class="p-3 bg-card rounded-lg border border-border/40 space-y-1 mt-2.5">
                            <h4 class="font-bold text-xs text-foreground">
                                {{ strategies.find(s => s.key === form.selectedStrategy)?.name }}
                            </h4>
                            <p class="text-[10px] text-muted-foreground">
                                {{ strategies.find(s => s.key === form.selectedStrategy)?.description }}
                            </p>
                            <div class="pt-2 flex flex-wrap gap-2 text-[9px] font-bold uppercase">
                                <span class="text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded">Pros: {{ strategies.find(s => s.key === form.selectedStrategy)?.pros[0] }}</span>
                                <span class="text-amber-600 bg-amber-50 dark:bg-amber-500/10 px-1.5 py-0.5 rounded">Cons: {{ strategies.find(s => s.key === form.selectedStrategy)?.cons[0] }}</span>
                            </div>
                        </div>

                        <!-- Amplifier outputs list -->
                        <div class="space-y-2 pt-2.5">
                            <div 
                                v-for="(rec, idx) in ampRecommendations" 
                                :key="idx"
                                class="flex flex-col gap-2 p-3.5 bg-card border border-border/40 rounded-xl animate-in fade-in duration-200"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                    <div>
                                        <span class="font-bold text-foreground block">{{ rec.zoneName }}</span>
                                        <span class="text-[10px] text-muted-foreground">{{ rec.speakerInfo }} → Target Amp Channel Load: {{ rec.loadImpedance }} ohms</span>
                                    </div>
                                    <div class="text-left sm:text-right shrink-0">
                                        <span class="inline-block px-2 py-0.5 rounded font-mono text-[10px] font-bold tracking-tight bg-primary/10 text-primary mb-1">
                                            {{ rec.targetPower }}W RMS
                                        </span>
                                        <p class="text-[9px] text-muted-foreground leading-none max-w-[200px]">{{ rec.recommendation }}</p>
                                    </div>
                                </div>
                                
                                <!-- Thomann Matching Recommendations -->
                                <div class="pt-2 border-t border-border/30">
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground block mb-1.5">Thomann Recommended Amplifiers:</span>
                                    <div class="flex flex-wrap gap-2">
                                        <a 
                                            v-for="amp in rec.matchingAmps" 
                                            :key="amp.name"
                                            :href="amp.link" 
                                            target="_blank"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-500/5 hover:bg-emerald-500/10 border border-emerald-500/25 hover:border-emerald-500/40 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold transition-all cursor-pointer"
                                            title="View details on Thomann"
                                        >
                                            <Zap class="size-3 text-emerald-500" />
                                            {{ amp.name }} ({{ amp.specs }} • {{ amp.price }})
                                        </a>
                                        <span v-if="rec.matchingAmps.length === 0" class="text-[10px] text-muted-foreground italic">No standard amplifier matches found for this load/power profile.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Sidebar properties & configuration (cols 5) -->
            <div class="lg:col-span-5 flex flex-col gap-4 overflow-y-auto bg-muted/20 p-4 border border-border/50 rounded-2xl">
                <div>
                    <h3 class="font-bold text-sm text-foreground flex items-center gap-1.5 pb-2 border-b border-border/30">
                        <Settings class="size-4 text-primary" /> Active Zone Properties
                    </h3>
                </div>

                <div v-if="!activeZone" class="text-center py-12 text-muted-foreground italic text-xs">
                    Select a speaker zone on the left to configure specs.
                </div>

                <div v-else class="space-y-4">
                    <!-- Zone edit inputs -->
                    <div class="space-y-1.5">
                        <Label for="zone-name">Zone Label</Label>
                        <Input :disabled="isReadOnly" v-model="activeZone.name" class="rounded-xl h-9 bg-card px-2.5 text-xs" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="zone-type">Speaker Type</Label>
                            <select :disabled="isReadOnly" v-model="activeZone.type" class="flex h-9 w-full rounded-xl border border-input bg-card px-2.5 py-1 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option value="top">Top (Full Range)</option>
                                <option value="sub">Subwoofer</option>
                                <option value="monitor">Stage Wedge Monitor</option>
                                <option value="fill">Front/Side Fill</option>
                                <option value="array">Line Array</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="zone-qty">Quantity</Label>
                            <Input :disabled="isReadOnly" type="number" min="1" v-model.number="activeZone.qty" class="rounded-xl h-9 bg-card px-2.5 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <Label for="zone-imp">Impedance (Ω)</Label>
                            <Input :disabled="isReadOnly" type="number" min="1" v-model.number="activeZone.impedance" class="rounded-xl h-9 bg-card px-2.5 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="zone-power">RMS Power (W)</Label>
                            <Input :disabled="isReadOnly" type="number" min="10" v-model.number="activeZone.power_rms" class="rounded-xl h-9 bg-card px-2.5 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="zone-peak">Peak Power (W)</Label>
                            <Input :disabled="isReadOnly" type="number" min="10" v-model.number="activeZone.power_peak" class="rounded-xl h-9 bg-card px-2.5 text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="zone-sens">Sensitivity (dB @ 1W/1m)</Label>
                            <Input :disabled="isReadOnly" type="number" min="70" v-model.number="activeZone.sensitivity" class="rounded-xl h-9 bg-card px-2.5 text-xs" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="zone-wiring">Wiring Mode</Label>
                            <select :disabled="isReadOnly" v-model="activeZone.wiring" class="flex h-9 w-full rounded-xl border border-input bg-card px-2.5 py-1 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option value="parallel">Parallel Chaining</option>
                                <option value="series">Series Chaining</option>
                                <option value="series_parallel" :disabled="activeZone.qty < 4">Series-Parallel (Requires ≥4)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label for="zone-dist">SPL Target Distance (m)</Label>
                            <Input :disabled="isReadOnly" type="number" min="1" v-model.number="activeZone.target_distance" class="rounded-xl h-9 bg-card px-2.5 text-xs" />
                        </div>
                    </div>

                    <!-- Visual wiring diagram SVG -->
                    <div class="pt-3 border-t border-border/30">
                        <Label class="text-[10px] uppercase font-bold text-muted-foreground block mb-2">Wiring Diagram Preview</Label>
                        <div class="bg-card border border-border/50 rounded-xl p-3 flex justify-center items-center overflow-hidden min-h-[140px]">
                            <!-- SVG wiring graphic rendering dynamically -->
                            <svg viewBox="0 0 280 120" class="w-full max-w-[280px]">
                                <!-- Amplifier output terminals left side -->
                                <rect x="10" y="45" width="40" height="30" rx="3" fill="#2D3748" />
                                <text x="30" y="63" fill="#FFF" font-size="8" text-anchor="middle" font-family="monospace">AMP</text>
                                <circle cx="45" cy="52" r="3" fill="#E53E3E" />
                                <text x="45" y="47" fill="#E53E3E" font-size="7" text-anchor="middle">+</text>
                                <circle cx="45" cy="68" r="3" fill="#718096" />
                                <text x="45" y="78" fill="#718096" font-size="7" text-anchor="middle">-</text>

                                <!-- Speaker cabs right side -->
                                <g v-for="i in Math.min(4, activeZone.qty)" :key="i">
                                    <rect :x="90 + (i-1)*45" y="35" width="30" height="50" rx="2" fill="#4A5568" stroke="#718096" stroke-width="1" />
                                    <!-- Cone circle graphic -->
                                    <circle :cx="105 + (i-1)*45" cy="60" r="11" fill="none" stroke="#2D3748" stroke-width="2" />
                                    <circle :cx="105 + (i-1)*45" cy="60" r="4" fill="#2D3748" />
                                    <!-- terminals -->
                                    <circle :cx="96 + (i-1)*45" cy="42" r="1.5" fill="#E53E3E" />
                                    <circle :cx="114 + (i-1)*45" cy="42" r="1.5" fill="#718096" />
                                </g>

                                <!-- Wire lines based on wiring model -->
                                <g v-if="activeZone.wiring === 'parallel'">
                                    <!-- Positives wire routing -->
                                    <path d="M 45 52 L 70 52 L 70 20 L 231 20 L 231 42" fill="none" stroke="#E53E3E" stroke-dasharray="2" stroke-width="1.5" />
                                    <!-- Negatives wire routing -->
                                    <path d="M 45 68 L 75 68 L 75 105 L 249 105 L 249 42" fill="none" stroke="#718096" stroke-dasharray="2" stroke-width="1.5" />
                                    
                                    <!-- Speaker drop down taps -->
                                    <path v-for="i in Math.min(4, activeZone.qty)" :key="`p-${i}`" :d="`M ${96 + (i-1)*45} 20 L ${96 + (i-1)*45} 42`" fill="none" stroke="#E53E3E" stroke-width="1.2" />
                                    <path v-for="i in Math.min(4, activeZone.qty)" :key="`n-${i}`" :d="`M ${114 + (i-1)*45} 105 L ${114 + (i-1)*45} 42`" fill="none" stroke="#718096" stroke-width="1.2" />
                                </g>

                                <g v-else-if="activeZone.wiring === 'series'">
                                    <!-- Positive to first Speaker -->
                                    <path d="M 45 52 L 70 52 L 70 20 L 96 20 L 96 42" fill="none" stroke="#E53E3E" stroke-width="1.5" />
                                    
                                    <!-- Negative of last speaker to amp negative -->
                                    <path :d="`M ${114 + (Math.min(4, activeZone.qty)-1)*45} 42 L ${114 + (Math.min(4, activeZone.qty)-1)*45} 105 L 75 105 L 75 68 L 45 68`" fill="none" stroke="#718096" stroke-width="1.5" />
                                    
                                    <!-- Serial links between boxes -->
                                    <path v-for="i in (Math.min(4, activeZone.qty) - 1)" :key="`link-${i}`" :d="`M ${114 + (i-1)*45} 42 L ${114 + (i-1)*45} 25 L ${96 + i*45} 25 L ${96 + i*45} 42`" fill="none" stroke="#ECC94B" stroke-width="1.5" />
                                </g>
                            </svg>
                        </div>
                    </div>

                    <!-- Amp Sizing Configuration slider -->
                    <div class="pt-3 border-t border-border/30">
                        <div class="flex justify-between items-center mb-1.5">
                            <Label class="text-[10px] uppercase font-bold text-muted-foreground block">Headroom Margin Sizing</Label>
                            <span class="text-[11px] font-bold text-foreground">{{ form.headroomFactor }}x</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <input 
                                :disabled="isReadOnly"
                                type="range" 
                                min="1.0" 
                                max="2.0" 
                                step="0.25" 
                                v-model.number="form.headroomFactor" 
                                class="w-full h-1.5 bg-muted rounded-lg appearance-none cursor-pointer accent-primary" 
                            />
                        </div>
                        <p class="text-[9px] text-muted-foreground mt-1 flex items-center gap-1">
                            <Info class="size-3 shrink-0" />
                            <span>{{ form.headroomFactor === 1.0 ? 'Matches Speaker RMS directly (underpowered risk).' : (form.headroomFactor === 1.5 ? 'Adds +1.76dB safe headroom (standard recommendation).' : 'Matches Program Peak for maximum dynamic transient headroom (+3dB).') }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
