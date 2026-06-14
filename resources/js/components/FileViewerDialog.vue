<script setup lang="ts">
import { Download, ExternalLink } from 'lucide-vue-next';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed } from 'vue';

const props = defineProps<{
    visible: boolean;
    viewUrl: string;
    downloadUrl: string;
    filename: string;
    mime?: string;
}>();

const emit = defineEmits<{
    'update:visible': [value: boolean];
}>();

const dialogVisible = computed<boolean>({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const lowerFilename = computed(() => props.filename.toLowerCase());

const isPdf = computed(
    () =>
        props.mime?.startsWith('application/pdf') ||
        lowerFilename.value.endsWith('.pdf'),
);

const isImage = computed(
    () =>
        props.mime?.startsWith('image/') ||
        /\.(png|jpe?g|gif|webp|bmp|svg|avif)$/.test(lowerFilename.value),
);

function openInNewTab(): void {
    window.open(props.viewUrl, '_blank');
}
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        :modal="true"
        :maximizable="true"
        :style="{ width: '70vw' }"
        :header="filename"
    >
        <div>
            <iframe
                v-if="isPdf"
                :src="viewUrl"
                class="h-[75vh] w-full"
                :title="filename"
            />
            <img
                v-else-if="isImage"
                :src="viewUrl"
                :alt="filename"
                class="max-w-full"
            />
            <div
                v-else
                class="flex flex-col items-start gap-3 py-8 text-sm text-muted-foreground"
            >
                <span>Preview not available for this file type.</span>
                <a :href="downloadUrl" :download="filename">
                    <Button label="Download" severity="secondary" size="small">
                        <template #icon>
                            <Download class="size-4" />
                        </template>
                    </Button>
                </a>
            </div>
        </div>

        <template #footer>
            <Button
                label="Open in new tab"
                severity="secondary"
                text
                @click="openInNewTab"
            >
                <template #icon>
                    <ExternalLink class="size-4" />
                </template>
            </Button>
            <a :href="downloadUrl" :download="filename">
                <Button label="Download">
                    <template #icon>
                        <Download class="size-4" />
                    </template>
                </Button>
            </a>
        </template>
    </Dialog>
</template>
