<template>
    <h3>All Count Tables:</h3>
    <el-row :gutter="16" v-loading="movieStore.loader">
        <CountCardAdm v-if="countCard" v-for="cart of countCard"
        :key="cart.key"
        :countCard="cart"
        />
    </el-row>
    <p v-if="error">{{ error }}</p>
</template>

<script setup>
    import CountCardAdm from "../components/CountCardAdm.vue";
    import { storeToRefs } from 'pinia';
    import { useDashboardStore } from "../store/dashboardStore";
    import { onMounted } from "vue";

    const movieStore = useDashboardStore();
    const { countCard, error } = storeToRefs(movieStore);

    onMounted( async () => {
        movieStore.getMoviesCount();
    });


</script>

<style scoped>

</style>
