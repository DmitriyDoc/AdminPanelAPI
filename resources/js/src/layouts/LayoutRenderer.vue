<script setup>
    import { computed } from 'vue'
    import LayoutFull from './LayoutFull.vue';
    import LayoutMain from './LayoutMain.vue';
    import useAppRouter from "../composables/useAppRouter";

    const { routeMeta } = useAppRouter();
    const layout = computed(() =>
        routeMeta.value?.layout ? routeMeta.value.layout : 'sign_in',
    );
</script>

<template>
    <div class="common-layout" v-if="layout === 'full'" >
        <LayoutMain/>
    </div>

    <div v-else-if="layout === 'sign_in'" >
        <LayoutFull />
    </div>
</template>
