<template>
    <section class="hero-carousel">
        <section className="embla">
            <div className="embla__viewport" ref="emblaRef">
                <div class="embla__container">
                    <div v-for="(slide, index) in slides" :key="index" class="embla__slide">
                        <!-- <img :src="slide.image" /> -->
                        <BaseImage :src="slide.image" alt="" customClass="embla__slide-image" />
                        <div v-if="slide.title" class="overlay">
                            {{ slide.title }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pagination -->
        <div class="dots">
            <button v-for="i in snapCount" :key="i" :class="{ active: selectedIndex === i - 1 }"
                @click="scrollTo(i - 1)" />
        </div>
    </section>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, defineAsyncComponent } from 'vue'
import emblaCarouselVue from 'embla-carousel-vue'
import Autoplay from 'embla-carousel-autoplay'
import heroImage from '@/assets/images/hero_example.png'

const BaseImage = defineAsyncComponent(
    () => import("@/components/BaseImage.vue"),
);

export interface HeroSlide {
    image: string
    title?: string
}

interface Props {
    slides?: HeroSlide[]
    autoplay?: boolean
    delay?: number
}

const props = withDefaults(defineProps<Props>(), {
    slides: () => [
        {
            image: heroImage,
            title: '多様なプレイヤーが、世界から東京に集う'
        },
        {
            image: heroImage,
            title: '多様なプレイヤーが、世界から東京に集う'
        },
        {
            image: heroImage,
            title: 'Third slide'
        }
    ],
    // autoplay: true,
    // delay: 4000
})

const plugins = props.autoplay
    ? [Autoplay({ delay: props.delay })]
    : []

const [emblaRef, emblaApi] = emblaCarouselVue(
    {
        loop: true,
        align: 'start'
    },
    plugins
)

/* ---------- State ---------- */
const selectedIndex = ref(0)
const snapCount = ref(0)

/* ---------- Lifecycle ---------- */
const onSelect = () => {
    if (!emblaApi.value) return
    selectedIndex.value = emblaApi.value.selectedScrollSnap()
}

onMounted(() => {
    if (!emblaApi.value) return

    snapCount.value = emblaApi.value.scrollSnapList().length
    emblaApi.value.on('select', onSelect)
})

watch(
    () => props.slides,
    () => emblaApi.value?.reInit()
)

/* ---------- Actions ---------- */
const scrollTo = (index: number) => {
    emblaApi.value?.scrollTo(index)
}
</script>
<style lang="scss" scoped>
.hero-carousel {
    position: relative;
}

.embla {
    max-width: 48rem;
    margin: auto;
    --slide-height: 119px;
    --slide-spacing: 13px;
    --slide-size: 90%;
}

.embla__viewport {
    overflow: hidden;
}

.embla__container {
    display: flex;
    touch-action: pan-y pinch-zoom;
    margin-left: calc(var(--slide-spacing) * -1);
}

.embla__slide {
    position: relative;
    transform: translate3d(0, 0, 0);
    flex: 0 0 var(--slide-size);
    min-width: 0;
    padding-left: var(--slide-spacing);
}

.embla__slide-image {
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    height: var(--slide-height) !important;
    user-select: none;
    border: 1px solid #F3F3F3;
}

.overlay {
    position: absolute;
    bottom: 16px;
    left: 24px;
    color: #fff;
    font-size: 20px;
    font-weight: 600;
    text-shadow: 0 2px 6px rgba(0, 0, 0, 0.6);
    display: -webkit-box;
    word-break: break-word;
    overflow: hidden;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
}

/* Dots */
.dots {
    display: flex;
    justify-content: end;
    gap: 8px;
    margin-top: 12px;
    padding-right: 16px;
}

.dots button {
    padding: 0;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #777;
    border: none;
    opacity: 0.5;
    cursor: pointer;
    transition: all 0.3s ease;
}

.dots button.active {
    width: 24px;
    border-radius: 8px;
    background: red;
    opacity: 1;
}
</style>
