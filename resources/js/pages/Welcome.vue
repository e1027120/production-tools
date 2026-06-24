<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import { 
    ArrowRight, 
    Activity, 
    ShieldCheck, 
    Network,
    Layers,
    GraduationCap,
    Users
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <Head title="Welcome to Production Tools" />

    <div class="min-h-screen bg-background text-foreground font-sans relative overflow-hidden selection:bg-primary/20 selection:text-primary">
        <!-- Abstract gradient mesh overlay for rich premium aesthetic -->
        <div class="absolute top-[-10%] right-[-5%] w-[600px] h-[600px] rounded-full bg-gradient-to-tr from-[#1AC18C]/20 to-[#796D7F]/20 blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-[-10%] left-[-5%] w-[500px] h-[500px] rounded-full bg-gradient-to-br from-[#796D7F]/10 to-[#1AC18C]/15 blur-[100px] pointer-events-none"></div>

        <!-- Header -->
        <header class="w-full max-w-7xl mx-auto px-6 h-20 flex items-center justify-between border-b border-border/40 relative z-10">
            <div class="flex items-center gap-3">
                <div class="flex aspect-square size-9 items-center justify-center rounded-lg bg-foreground text-background">
                    <AppLogoIcon class="size-5 fill-current text-primary" />
                </div>
                <span class="text-lg font-bold tracking-tight text-foreground">
                    Production<span class="text-primary font-medium">Tools</span>
                </span>
            </div>

            <nav class="flex items-center gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 transition-all duration-200"
                >
                    Dashboard
                    <ArrowRight class="ml-2 size-4" />
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                    >
                        Log in
                    </Link>
                </template>
            </nav>
        </header>

        <!-- Hero Section -->
        <main class="max-w-7xl mx-auto px-6 py-16 lg:py-24 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <!-- Text Content -->
                <div class="lg:col-span-7 flex flex-col space-y-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary/10 text-primary border border-primary/20 w-fit text-xs font-semibold uppercase tracking-wider">
                        <Activity class="size-4 animate-pulse" />
                        Church Production Multi-Tool Platform
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.1] text-foreground">
                        Everything you need to run church production <span class="bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">flawlessly</span>.
                    </h1>

                    <p class="text-base sm:text-lg text-muted-foreground max-w-xl leading-relaxed">
                        A centralized operations platform designed specifically for church production teams. Design technical schematics, organize gear racks, switch between campuses, and train your AV volunteers—all under one unified dashboard.
                    </p>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 pt-4">
                        <a
                            href="#features"
                            class="inline-flex items-center justify-center rounded-xl border border-border/80 bg-white/60 dark:bg-card/40 backdrop-blur px-6 py-3.5 text-base font-semibold hover:bg-white dark:hover:bg-card hover:border-border transition-all duration-200"
                        >
                            Learn More
                        </a>
                    </div>

                    <div class="flex items-center gap-6 pt-6 text-sm text-muted-foreground border-t border-border/40">
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="size-5 text-primary" />
                            <span>Secure Workspaces</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Layers class="size-5 text-secondary" />
                            <span>Modular Production Suite</span>
                        </div>
                    </div>
                </div>

                <!-- Visual Graphic / Glassmorphic Card Showcase -->
                <div class="lg:col-span-5 relative">
                    <!-- Outer neon glow -->
                    <div class="absolute inset-0 bg-gradient-to-tr from-primary to-secondary rounded-2xl opacity-15 blur-xl pointer-events-none"></div>
                    
                    <!-- Decorative screen -->
                    <div class="relative bg-white/80 dark:bg-card/85 border border-border/60 rounded-2xl p-6 shadow-2xl backdrop-blur-md">
                        <div class="flex items-center justify-between border-b border-border/40 pb-4 mb-6">
                            <div class="flex gap-1.5">
                                <span class="size-3 rounded-full bg-red-400"></span>
                                <span class="size-3 rounded-full bg-yellow-400"></span>
                                <span class="size-3 rounded-full bg-green-400"></span>
                            </div>
                            <span class="text-xs font-mono text-muted-foreground/80 font-medium">production-console v2.1</span>
                        </div>

                        <!-- Mini terminal console representation -->
                        <div class="space-y-4 font-mono text-xs text-muted-foreground">
                            <div class="flex items-start gap-2">
                                <span class="text-primary font-bold">➜</span>
                                <span class="text-foreground">systemctl status production-tools</span>
                            </div>
                            <div class="pl-4 text-green-500 flex items-center gap-2">
                                <span class="size-2 rounded-full bg-green-500 animate-ping"></span>
                                <span>active (running) since Wed 2026-06-24 19:12:41</span>
                            </div>
                            <div class="pl-4">
                                sqlite db connected: <span class="text-foreground">database.sqlite</span> (OK)
                            </div>
                            
                            <div class="flex items-start gap-2 pt-2">
                                <span class="text-primary font-bold">➜</span>
                                <span class="text-foreground">php artisan platform:status</span>
                            </div>
                            <div class="bg-foreground text-background p-3 rounded-lg flex flex-col gap-1 font-mono text-[11px]">
                                <div class="flex justify-between font-bold border-b border-background/20 pb-1 mb-1">
                                    <span>MODULE</span>
                                    <span>STATUS</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Technical Diagrammer</span>
                                    <span class="text-primary">ONLINE</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Equipment Rack Builder</span>
                                    <span class="text-primary">ONLINE</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Volunteer LMS & Portal</span>
                                    <span class="text-primary">ONLINE</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Grid Section -->
            <section id="features" class="pt-24 lg:pt-32">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground">
                        High-Performance Modules
                    </h2>
                    <p class="text-muted-foreground mt-4">
                        A suite of custom, lightweight tools to manage and analyze your production infrastructure on the fly.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Feature 1: Technical Diagrams -->
                    <div class="group relative bg-white/70 dark:bg-card/60 border border-border/50 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-primary/40 transition-all duration-300 backdrop-blur">
                        <div class="size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300">
                            <Network class="size-6" />
                        </div>
                        <h3 class="font-semibold text-lg text-foreground group-hover:text-primary transition-colors">
                            Technical Diagrams
                        </h3>
                        <p class="text-muted-foreground text-sm mt-2 leading-relaxed">
                            Design stage plots and routing. Define connection ports, custom cable paths, and edit waypoints dynamically.
                        </p>
                    </div>

                    <!-- Feature 2: Equipment Racks -->
                    <div class="group relative bg-white/70 dark:bg-card/60 border border-border/50 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-primary/40 transition-all duration-300 backdrop-blur">
                        <div class="size-12 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300">
                            <Layers class="size-6" />
                        </div>
                        <h3 class="font-semibold text-lg text-foreground group-hover:text-secondary transition-colors">
                            Equipment Rack Builder
                        </h3>
                        <p class="text-muted-foreground text-sm mt-2 leading-relaxed">
                            Construct server and AV equipment racks. Arrange rack units visually for seamless control room organization.
                        </p>
                    </div>

                    <!-- Feature 3: Volunteer Training -->
                    <div class="group relative bg-white/70 dark:bg-card/60 border border-border/50 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-primary/40 transition-all duration-300 backdrop-blur">
                        <div class="size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300">
                            <GraduationCap class="size-6" />
                        </div>
                        <h3 class="font-semibold text-lg text-foreground group-hover:text-primary transition-colors">
                            Volunteer Training
                        </h3>
                        <p class="text-muted-foreground text-sm mt-2 leading-relaxed">
                            Onboard team members with multi-step courses (audio, video, text) and verify knowledge with custom builder tests.
                        </p>
                    </div>

                    <!-- Feature 4: Multi-Church Workspaces -->
                    <div class="group relative bg-white/70 dark:bg-card/60 border border-border/50 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-primary/40 transition-all duration-300 backdrop-blur">
                        <div class="size-12 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300">
                            <Users class="size-6" />
                        </div>
                        <h3 class="font-semibold text-lg text-foreground group-hover:text-secondary transition-colors">
                            Multi-Church Workspaces
                        </h3>
                        <p class="text-muted-foreground text-sm mt-2 leading-relaxed">
                            Isolate and manage configurations for different campuses, assign volunteer permissions, and audit logs.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="w-full border-t border-border/40 py-8 relative z-10 bg-white/40 dark:bg-card/30 backdrop-blur">
            <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
                <p>© 2026 Production Tools. All rights reserved.</p>
                <div class="flex gap-6">
                    <a href="#" class="hover:text-foreground transition-colors">Privacy Policy</a>
                    <a href="#" class="hover:text-foreground transition-colors">Terms of Service</a>
                </div>
            </div>
        </footer>
    </div>
</template>
