<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Bold, Italic, Underline, List, ListOrdered, Eraser } from '@lucide/vue';

const props = defineProps<{
    modelValue?: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editorRef = ref<HTMLDivElement | null>(null);

onMounted(() => {
    if (editorRef.value) {
        editorRef.value.innerHTML = props.modelValue || '';
    }
});

watch(() => props.modelValue, (newVal) => {
    if (editorRef.value && editorRef.value.innerHTML !== newVal) {
        editorRef.value.innerHTML = newVal || '';
    }
});

const onInput = () => {
    if (editorRef.value) {
        emit('update:modelValue', editorRef.value.innerHTML);
    }
};

const onBlur = () => {
    if (editorRef.value) {
        emit('update:modelValue', editorRef.value.innerHTML);
    }
};

const exec = (command: string, value: string = '') => {
    document.execCommand(command, false, value);
    onInput();
    if (editorRef.value) {
        editorRef.value.focus();
    }
};
</script>

<template>
    <div class="border border-border/80 rounded-xl overflow-hidden bg-background focus-within:ring-1 focus-within:ring-[#1AC18C]/80 transition-all duration-200">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-1.5 p-2 border-b border-border/40 bg-muted/10">
            <button
                type="button"
                @click="exec('bold')"
                class="p-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                title="Bold"
            >
                <Bold class="size-4" />
            </button>
            <button
                type="button"
                @click="exec('italic')"
                class="p-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                title="Italic"
            >
                <Italic class="size-4" />
            </button>
            <button
                type="button"
                @click="exec('underline')"
                class="p-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                title="Underline"
            >
                <Underline class="size-4" />
            </button>
            
            <div class="w-px h-4 bg-border/80 mx-1"></div>
            
            <button
                type="button"
                @click="exec('insertUnorderedList')"
                class="p-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                title="Bullet List"
            >
                <List class="size-4" />
            </button>
            <button
                type="button"
                @click="exec('insertOrderedList')"
                class="p-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                title="Numbered List"
            >
                <ListOrdered class="size-4" />
            </button>
            
            <div class="w-px h-4 bg-border/80 mx-1"></div>
            
            <button
                type="button"
                @click="exec('formatBlock', 'H2')"
                class="p-1 px-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer text-xs font-extrabold transition-colors leading-none h-7 flex items-center"
                title="Heading 2"
            >
                H2
            </button>
            <button
                type="button"
                @click="exec('formatBlock', 'H3')"
                class="p-1 px-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer text-xs font-extrabold transition-colors leading-none h-7 flex items-center"
                title="Heading 3"
            >
                H3
            </button>
            <button
                type="button"
                @click="exec('formatBlock', 'P')"
                class="p-1 px-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer text-xs font-bold transition-colors leading-none h-7 flex items-center"
                title="Paragraph"
            >
                P
            </button>
            
            <div class="w-px h-4 bg-border/80 mx-1"></div>
            
            <button
                type="button"
                @click="exec('removeFormat')"
                class="p-1.5 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                title="Clear Formatting"
            >
                <Eraser class="size-4" />
            </button>
        </div>

        <!-- Contenteditable Area -->
        <div
            ref="editorRef"
            contenteditable="true"
            @input="onInput"
            @blur="onBlur"
            class="p-3.5 min-h-[160px] text-sm text-foreground focus:outline-none overflow-y-auto prose dark:prose-invert max-w-none prose-sm prose-p:my-1 prose-ul:my-1 prose-ol:my-1 cursor-text"
            :placeholder="placeholder || 'Start typing training contents...'"
        ></div>
    </div>
</template>

<style scoped>
div[contenteditable]:empty::before {
    content: attr(placeholder);
    color: var(--color-muted-foreground, #8B949D);
    opacity: 0.7;
    font-style: italic;
    pointer-events: none;
    display: block;
}
</style>
