<template>
    <picture>
        <template v-if="size">
            <source type="image/webp" :srcset="fixedWebpSrc" />
            <img :src="fixedImgSrc" :alt="alt" v-bind="$attrs" />
        </template>
        <template v-else>
            <source type="image/webp" :srcset="webpSrcset" :sizes="sizesAttr" />
            <img
                :src="originalUrl"
                :srcset="originalSrcset"
                :sizes="sizesAttr"
                :alt="alt"
                v-bind="$attrs"
            />
        </template>
    </picture>
</template>

<script setup>
/**
 * Responsive <picture> component for images optimized by wardtech/laravel-image-toolkit.
 *
 * Props:
 *   src       - Image path relative to public (e.g. "images/hero.jpg")
 *   alt       - Alt text (required)
 *   size      - Fixed size variant (e.g. 300). Omit for responsive srcset.
 *   sizes     - Sizes attribute for responsive mode (default: "100vw")
 *   widths    - Available widths (default: [150, 300, 500, 1000])
 *   baseUrl   - Base URL prefix (default: "" — assumes paths relative to domain root)
 */
import { computed } from 'vue';

const props = defineProps({
    src: { type: String, required: true },
    alt: { type: String, required: true },
    size: { type: Number, default: null },
    sizes: { type: String, default: '100vw' },
    widths: { type: Array, default: () => [150, 300, 500, 1000] },
    baseUrl: { type: String, default: '' },
});

defineOptions({ inheritAttrs: false });

const cleanSrc = computed(() =>
    props.src.replace(/\.\.\//g, '').replace(/^\//, '')
);

const pathWithoutExt = computed(() => {
    const lastDot = cleanSrc.value.lastIndexOf('.');
    return lastDot !== -1 ? cleanSrc.value.substring(0, lastDot) : cleanSrc.value;
});

const extension = computed(() => {
    const lastDot = cleanSrc.value.lastIndexOf('.');
    return lastDot !== -1 ? cleanSrc.value.substring(lastDot + 1) : 'jpg';
});

const url = (path) => `${props.baseUrl}/${path}`;

const sizesAttr = computed(() => props.sizes ?? '100vw');

const originalUrl = computed(() => url(cleanSrc.value));

// Fixed size mode
const fixedWebpSrc = computed(() =>
    url(`${pathWithoutExt.value}-${props.size}.webp`)
);
const fixedImgSrc = computed(() =>
    url(`${pathWithoutExt.value}-${props.size}.${extension.value}`)
);

// Responsive mode
const webpSrcset = computed(() =>
    props.widths
        .map((w) => `${url(`${pathWithoutExt.value}-${w}.webp`)} ${w}w`)
        .concat(`${url(`${pathWithoutExt.value}.webp`)} 9999w`)
        .join(', ')
);

const originalSrcset = computed(() =>
    props.widths
        .map((w) => `${url(`${pathWithoutExt.value}-${w}.${extension.value}`)} ${w}w`)
        .concat(`${originalUrl.value} 9999w`)
        .join(', ')
);
</script>
