<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem } from '@/types';
import { ChevronsUpDown, Plus } from '@lucide/vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const auth = computed(() => page.props.auth as any);

const switchChurch = (churchId: number) => {
    router.post(`/churches/${churchId}/switch`, {}, {
        preserveState: false,
    });
};

const showCreateModal = ref(false);
const churchForm = useForm({
    name: '',
    description: '',
});

const submitCreateChurch = () => {
    churchForm.post('/churches', {
        onSuccess: () => {
            showCreateModal.value = false;
            churchForm.reset();
        },
    });
};
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 bg-background"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1 text-muted-foreground hover:text-foreground" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-3">
            <!-- Church Switcher Dropdown -->
            <DropdownMenu v-if="auth.currentChurch">
                <DropdownMenuTrigger :as-child="true">
                    <Button
                        variant="ghost"
                        class="flex items-center gap-2 px-3 py-1.5 h-9 rounded-xl border border-border/80 hover:bg-muted cursor-pointer text-xs font-semibold"
                    >
                        <span class="inline-flex size-5 rounded bg-primary/10 text-primary items-center justify-center font-mono text-[10px] uppercase font-bold shrink-0">
                            {{ auth.currentChurch.name.charAt(0) }}
                        </span>
                        <span class="truncate max-w-[120px] text-foreground font-semibold leading-none">{{ auth.currentChurch.name }}</span>
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-muted text-muted-foreground uppercase tracking-wider scale-90 shrink-0">
                            {{ auth.currentChurch.pivot?.role }}
                        </span>
                        <ChevronsUpDown class="size-3.5 text-muted-foreground shrink-0" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56 mt-1 shadow-md border border-border p-1 space-y-1">
                    <div class="px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-muted-foreground">
                        Switch Church
                    </div>
                    
                    <DropdownMenuItem
                        v-for="c in auth.churches"
                        :key="c.id"
                        class="rounded-lg text-xs flex items-center justify-between p-2 cursor-pointer font-sans"
                        :class="c.id === auth.currentChurch.id ? 'bg-primary/10 text-primary font-bold' : 'hover:bg-muted text-foreground'"
                        @click="switchChurch(c.id)"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <span class="inline-flex size-5 rounded bg-muted items-center justify-center font-mono text-[9px] uppercase font-bold shrink-0" :class="c.id === auth.currentChurch.id ? 'bg-primary/20 text-primary' : ''">
                                {{ c.name.charAt(0) }}
                            </span>
                            <span class="truncate">{{ c.name }}</span>
                        </div>
                        <span v-if="c.id === auth.currentChurch.id" class="size-2 rounded-full bg-primary shrink-0"></span>
                    </DropdownMenuItem>

                    <!-- Create Church Option -->
                    <div v-if="auth.currentChurch?.pivot?.role === 'Admin'" class="border-t border-border mt-1 pt-1">
                        <DropdownMenuItem
                            class="rounded-lg text-xs flex items-center gap-2 p-2 cursor-pointer text-primary font-bold hover:bg-primary/5 focus:bg-primary/5 focus:text-primary font-sans"
                            @click="showCreateModal = true"
                        >
                            <Plus class="size-4 text-primary shrink-0" />
                            <span>Create New Church</span>
                        </DropdownMenuItem>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- User settings dropdown -->
            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary hover:bg-muted cursor-pointer"
                    >
                        <Avatar class="size-8 overflow-hidden rounded-full border border-border">
                            <AvatarImage
                                v-if="auth.user?.avatar"
                                :src="auth.user.avatar"
                                :alt="auth.user.name"
                            />
                            <AvatarFallback
                                class="rounded-lg bg-secondary text-secondary-foreground font-semibold text-xs"
                            >
                                {{ getInitials(auth.user?.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56 mt-1 shadow-md border border-border">
                    <UserMenuContent :user="auth.user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- MODAL: Create New Church -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 cursor-default" @click.stop>
            <div class="bg-card border border-border/60 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 text-left">
                <div class="px-6 py-4 border-b border-border/40 flex items-center justify-between">
                    <h3 class="font-bold text-lg text-foreground flex items-center gap-2">
                        <span class="inline-flex size-7 rounded-lg bg-primary/10 text-primary items-center justify-center font-bold">
                            <Plus class="size-4" />
                        </span>
                        Create New Church Workspace
                    </h3>
                    <button @click="showCreateModal = false" class="text-muted-foreground hover:text-foreground text-xl leading-none cursor-pointer">&times;</button>
                </div>

                <form @submit.prevent="submitCreateChurch" class="p-6 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="nc-name">Church Name</Label>
                        <Input
                            id="nc-name"
                            v-model="churchForm.name"
                            placeholder="e.g. Grace Community Church"
                            required
                            class="rounded-xl"
                        />
                        <InputError :message="churchForm.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="nc-desc">Description (Optional)</Label>
                        <textarea
                            id="nc-desc"
                            v-model="churchForm.description"
                            placeholder="Add brief workspace description..."
                            rows="3"
                            class="flex w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        ></textarea>
                        <InputError :message="churchForm.errors.description" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/40">
                        <Button type="button" @click="showCreateModal = false" variant="outline" class="rounded-xl cursor-pointer">Cancel</Button>
                        <Button type="submit" :disabled="churchForm.processing" class="bg-primary hover:bg-primary/95 text-primary-foreground font-semibold rounded-xl cursor-pointer">
                            {{ churchForm.processing ? 'Creating...' : 'Create Workspace' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </header>
</template>
