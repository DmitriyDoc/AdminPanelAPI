<template>
    <h3>All Count Tables:</h3>
    <el-row :gutter="16"  justify="center">
        <el-progress type="dashboard" v-show="loader" :percentage="percentage" :color="colors">
            <template #default="{ percentage }">
                <span class="percentage-value">{{ percentage }}%</span>
                <span class="percentage-label">Progressing</span>
            </template>
        </el-progress>
        <CountCardAdm v-show="!loader" v-if="countCard" v-for="cart of countCard"
            :key="cart.key"
            :countCard="cart"
        />
    </el-row>
    <p v-if="error">{{ error }}</p>
</template>

<script setup>
    import CountCardAdm from "../components/CountCardAdm.vue";
    import { Check } from '@element-plus/icons-vue'
    import { storeToRefs } from 'pinia';
    import { useDashboardStore } from "../store/dashboardStore";
    import { onMounted } from "vue";

    const dashboardStore = useDashboardStore();
    const { countCard, percentage, loader, error } = storeToRefs(dashboardStore);
    const colors = [
        { color: '#f56c6c', percentage: 20 },
        { color: '#e6a23c', percentage: 40 },
        { color: '#6f7ad3', percentage: 60 },
        { color: '#1989fa', percentage: 80 },
        { color: '#5cb87a', percentage: 100 },
    ];
     onMounted(  () => {
         if (loader.value){
             dashboardStore.getCurrentPercentage();
             dashboardStore.getMoviesCount();
         }
     });

</script>

<style scoped>
    .percentage-value {
        display: block;
        margin-top: 10px;
        font-size: 28px;
    }
    .percentage-label {
        display: block;
        margin-top: 10px;
        font-size: 12px;
    }
</style>
