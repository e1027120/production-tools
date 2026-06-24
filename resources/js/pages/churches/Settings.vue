<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Users, 
    Settings, 
    UserPlus, 
    Shield, 
    Trash2, 
    Edit, 
    Plus, 
    Building2,
    Check
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    modules: string[];
}

interface Church {
    id: number;
    name: string;
    description: string | null;
}

const props = defineProps<{
    church: Church;
    members: Member[];
    userRole: string;
}>();

const isAdmin = computed(() => props.userRole === 'Admin');
const isManager = computed(() => props.userRole === 'Manager' || props.userRole === 'Admin');

// Church details form
const churchForm = useForm({
    name: props.church.name,
    description: props.church.description || '',
});

const submitChurchUpdate = () => {
    if (!isAdmin.value) return;
    churchForm.put('/church/settings', {
        preserveScroll: true,
    });
};

// Add member form & modal state
const showAddModal = ref(false);
const memberForm = useForm({
    email: '',
    role: 'User',
    modules: ['racks'], // default module access
});

const submitAddMember = () => {
    memberForm.post('/church/users', {
        onSuccess: () => {
            showAddModal.value = false;
            memberForm.reset();
        },
    });
};

// Edit member modal state
const showEditModal = ref(false);
const editingMember = ref<Member | null>(null);
const editForm = useForm({
    role: 'User',
    modules: [] as string[],
});

const openEditModal = (member: Member) => {
    editingMember.value = member;
    editForm.role = member.role;
    editForm.modules = [...member.modules];
    showEditModal.value = true;
};

const submitEditMember = () => {
    if (!editingMember.value) return;
    editForm.put(`/church/users/${editingMember.value.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
            editingMember.value = null;
            editForm.reset();
        },
    });
};

// Remove member flow
const removeMember = (member: Member) => {
    if (confirm(`Are you sure you want to remove ${member.name} from the church?`)) {
        useForm({}).delete(`/church/users/${member.id}`, {
            preserveScroll: true,
        });
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Church Settings', href: '' }
        ]
    }
});
</script>

<template>
    <Head :title="`Settings: ${church.name}`" />

        <div class="max-w-4xl mx-auto p-6 space-y-6">
            <!-- 1. Church Details Panel -->
            <div class="bg-card border border-border/60 rounded-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 border-b border-border/40 pb-4">
                    <div class="size-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                        <Building2 class="size-5" />
                    </div>
                    <div>
                        <h2 class="font-bold text-lg text-foreground">Church Workspace Profile</h2>
                        <p class="text-xs text-muted-foreground">Workspace properties and information settings.</p>
                    </div>
                </div>

                <form @submit.prevent="submitChurchUpdate" class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="c-name">Church / Team Name</Label>
                        <Input 
                            id="c-name" 
                            v-model="churchForm.name" 
                            :disabled="!isAdmin" 
                            placeholder="e.g. Grace Fellowship" 
                            class="max-w-md rounded-xl"
                            required
                        />
                        <InputError :message="churchForm.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="c-desc">Description</Label>
                        <textarea 
                            id="c-desc" 
                            v-model="churchForm.description" 
                            :disabled="!isAdmin" 
                            placeholder="Add workspace description..." 
                            rows="3"
                            class="flex w-full max-w-md rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                        <InputError :message="churchForm.errors.description" />
                    </div>

                    <div v-if="isAdmin" class="pt-2">
                        <Button 
                            type="submit" 
                            :disabled="churchForm.processing" 
                            class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer"
                        >
                            <Check v-if="churchForm.wasSuccessful" class="mr-2 size-4" />
                            {{ churchForm.processing ? 'Saving...' : churchForm.wasSuccessful ? 'Saved Workspace!' : 'Save Details' }}
                        </Button>
                    </div>
                    <div v-else class="text-xs text-amber-500 font-semibold bg-amber-500/10 border border-amber-500/20 p-3 rounded-xl max-w-md">
                        Only church Workspace Administrators can rename or update the church details.
                    </div>
                </form>
            </div>

            <!-- 2. Members Management Panel -->
            <div class="bg-card border border-border/60 rounded-2xl p-6 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-border/40 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-lg bg-[#1AC18C]/10 flex items-center justify-center text-[#1AC18C]">
                            <Users class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-bold text-lg text-foreground">Workspace Members</h2>
                            <p class="text-xs text-muted-foreground">Manage user accounts, roles, and allowed modules.</p>
                        </div>
                    </div>

                    <Button 
                        v-if="isManager" 
                        @click="showAddModal = true" 
                        class="bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-xl cursor-pointer text-xs"
                    >
                        <UserPlus class="mr-1.5 size-4" /> Invite Member
                    </Button>
                </div>

                <!-- Members Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse text-left">
                        <thead>
                            <tr class="border-b border-border/60 text-muted-foreground font-semibold">
                                <th class="py-3 px-2">Member</th>
                                <th class="py-3 px-2">Role</th>
                                <th class="py-3 px-2">Module Access</th>
                                <th v-if="isManager" class="py-3 px-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in members" :key="member.id" class="border-b border-border/40 hover:bg-muted/10">
                                <td class="py-3.5 px-2">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-foreground">{{ member.name }}</span>
                                        <span class="text-xs text-muted-foreground">{{ member.email }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-2">
                                    <span 
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                        :class="{
                                            'bg-violet-500/10 text-violet-500': member.role === 'Admin',
                                            'bg-blue-500/10 text-blue-500': member.role === 'Manager',
                                            'bg-neutral-500/10 text-neutral-500': member.role === 'User',
                                        }"
                                    >
                                        <Shield class="size-3" />
                                        {{ member.role }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-2">
                                    <div class="flex flex-wrap gap-1">
                                        <!-- Admins / Managers show implicit all access -->
                                        <span 
                                            v-if="['Admin', 'Manager'].includes(member.role)"
                                            class="px-2 py-0.5 rounded bg-[#1AC18C]/10 text-[#1AC18C] text-[10px] font-bold uppercase tracking-wider"
                                        >
                                            All Modules
                                        </span>
                                        <template v-else>
                                            <span 
                                                v-for="mod in member.modules" 
                                                :key="mod"
                                                class="px-2 py-0.5 rounded bg-muted text-muted-foreground text-[10px] font-bold uppercase tracking-wider border border-border"
                                            >
                                                {{ mod === 'racks' ? 'Rack Builder' : mod }}
                                            </span>
                                            <span 
                                                v-if="member.modules.length === 0" 
                                                class="text-xs text-muted-foreground italic"
                                            >
                                                No modules assigned
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td v-if="isManager" class="py-3.5 px-2 text-right">
                                    <div class="flex justify-end gap-2" v-if="member.id !== $page.props.auth.user.id">
                                        <Button 
                                            @click="openEditModal(member)" 
                                            size="sm" 
                                            variant="ghost" 
                                            class="h-8 px-2 hover:bg-muted text-muted-foreground hover:text-foreground rounded-lg cursor-pointer"
                                        >
                                            <Edit class="size-4" />
                                        </Button>
                                        <Button 
                                            @click="removeMember(member)" 
                                            size="sm" 
                                            variant="ghost" 
                                            class="h-8 px-2 hover:bg-red-500/10 text-muted-foreground hover:text-red-500 rounded-lg cursor-pointer"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground italic px-2">Current User</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: Invite Member -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <UserPlus class="size-5 text-primary" />
                        Invite Workspace Member
                    </h3>
                    <button @click="showAddModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
                </div>

                <form @submit.prevent="submitAddMember" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="m-email">User Email Address</Label>
                        <Input 
                            id="m-email" 
                            v-model="memberForm.email" 
                            type="email" 
                            placeholder="user@example.com" 
                            required
                            class="rounded-xl"
                        />
                        <InputError :message="memberForm.errors.email" />
                    </div>

                    <div class="space-y-1.5">
                        <Label>Workspace Role</Label>
                        <select v-model="memberForm.role" class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option value="Admin">Admin (Full Access & Settings)</option>
                            <option value="Manager">Manager (Manage Users & Modules)</option>
                            <option value="User">User (Standard Access)</option>
                        </select>
                        <InputError :message="memberForm.errors.role" />
                    </div>

                    <!-- Module Rights (Only relevant for User role) -->
                    <div v-if="memberForm.role === 'User'" class="space-y-2 pt-1 border-t border-border/40">
                        <Label class="text-xs uppercase text-muted-foreground tracking-wider font-bold">Grant Module Rights</Label>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2.5 p-3 rounded-xl bg-muted/40 border border-border/40">
                                <input 
                                    id="mod-racks" 
                                    type="checkbox" 
                                    value="racks" 
                                    v-model="memberForm.modules"
                                    class="size-4.5 rounded border-input text-primary focus:ring-primary cursor-pointer"
                                />
                                <Label for="mod-racks" class="cursor-pointer">
                                    <span class="font-semibold text-xs text-foreground block">19" Rack Builder</span>
                                    <span class="text-[10px] text-muted-foreground block font-normal">Design and compile equipment cabinets.</span>
                                </Label>
                            </div>
                            <div class="flex items-center gap-2.5 p-3 rounded-xl bg-muted/40 border border-border/40">
                                <input 
                                    id="mod-trainings" 
                                    type="checkbox" 
                                    value="trainings" 
                                    v-model="memberForm.modules"
                                    class="size-4.5 rounded border-input text-primary focus:ring-primary cursor-pointer"
                                />
                                <Label for="mod-trainings" class="cursor-pointer">
                                    <span class="font-semibold text-xs text-foreground block">Ministry Trainings</span>
                                    <span class="text-[10px] text-muted-foreground block font-normal">Create and assign training workflows for ministries.</span>
                                </Label>
                            </div>
                            <div class="flex items-center gap-2.5 p-3 rounded-xl bg-muted/40 border border-border/40">
                                <input 
                                    id="mod-diagrams" 
                                    type="checkbox" 
                                    value="diagrams" 
                                    v-model="memberForm.modules"
                                    class="size-4.5 rounded border-input text-primary focus:ring-primary cursor-pointer"
                                />
                                <Label for="mod-diagrams" class="cursor-pointer">
                                    <span class="font-semibold text-xs text-foreground block">Technical Diagrams</span>
                                    <span class="text-[10px] text-muted-foreground block font-normal">Draw and map AV, audio, and network signal cables.</span>
                                </Label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showAddModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="memberForm.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ memberForm.processing ? 'Inviting...' : 'Invite' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Edit Member -->
        <div v-if="showEditModal && editingMember" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <Edit class="size-5 text-primary" />
                        Edit Member Permissions
                    </h3>
                    <button @click="showEditModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
                </div>

                <form @submit.prevent="submitEditMember" class="p-6 space-y-4">
                    <div class="space-y-1">
                        <Label class="text-[10px] uppercase font-bold text-muted-foreground">Editing Permissions For</Label>
                        <div class="font-bold text-base text-foreground leading-snug">{{ editingMember.name }}</div>
                        <div class="text-xs text-muted-foreground leading-none">{{ editingMember.email }}</div>
                    </div>

                    <div class="space-y-1.5 pt-2 border-t border-border/40">
                        <Label>Workspace Role</Label>
                        <select v-model="editForm.role" class="flex h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option value="Admin">Admin (Full Access & Settings)</option>
                            <option value="Manager">Manager (Manage Users & Modules)</option>
                            <option value="User">User (Standard Access)</option>
                        </select>
                        <InputError :message="editForm.errors.role" />
                    </div>

                    <!-- Module Rights (Only relevant for User role) -->
                    <div v-if="editForm.role === 'User'" class="space-y-2 pt-1 border-t border-border/40">
                        <Label class="text-xs uppercase text-muted-foreground tracking-wider font-bold">Grant Module Rights</Label>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2.5 p-3 rounded-xl bg-muted/40 border border-border/40">
                                <input 
                                    id="edit-mod-racks" 
                                    type="checkbox" 
                                    value="racks" 
                                    v-model="editForm.modules"
                                    class="size-4.5 rounded border-input text-primary focus:ring-primary cursor-pointer"
                                />
                                <Label for="edit-mod-racks" class="cursor-pointer">
                                    <span class="font-semibold text-xs text-foreground block">19" Rack Builder</span>
                                    <span class="text-[10px] text-muted-foreground block font-normal">Design and compile equipment cabinets.</span>
                                </Label>
                            </div>
                            <div class="flex items-center gap-2.5 p-3 rounded-xl bg-muted/40 border border-border/40">
                                <input 
                                    id="edit-mod-trainings" 
                                    type="checkbox" 
                                    value="trainings" 
                                    v-model="editForm.modules"
                                    class="size-4.5 rounded border-input text-primary focus:ring-primary cursor-pointer"
                                />
                                <Label for="edit-mod-trainings" class="cursor-pointer">
                                    <span class="font-semibold text-xs text-foreground block">Ministry Trainings</span>
                                    <span class="text-[10px] text-muted-foreground block font-normal">Create and assign training workflows for ministries.</span>
                                </Label>
                            </div>
                            <div class="flex items-center gap-2.5 p-3 rounded-xl bg-muted/40 border border-border/40">
                                <input 
                                    id="edit-mod-diagrams" 
                                    type="checkbox" 
                                    value="diagrams" 
                                    v-model="editForm.modules"
                                    class="size-4.5 rounded border-input text-primary focus:ring-primary cursor-pointer"
                                />
                                <Label for="edit-mod-diagrams" class="cursor-pointer">
                                    <span class="font-semibold text-xs text-foreground block">Technical Diagrams</span>
                                    <span class="text-[10px] text-muted-foreground block font-normal">Draw and map AV, audio, and network signal cables.</span>
                                </Label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showEditModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="editForm.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
</template>
