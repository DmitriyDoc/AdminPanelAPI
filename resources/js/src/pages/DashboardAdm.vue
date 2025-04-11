<template>
    <h3 class="text-center mt-3 mb-3">{{ title }}</h3>
    <el-row :gutter="16" justify="center">
        <el-progress type="dashboard" v-show="loader" :percentage="percentage" :color="colors">
            <template #default="{ percentage }">
                <span class="percentage-value">{{ percentage }}%</span>
                <span class="percentage-label">{{$t('progressing')}}</span>
            </template>
        </el-progress>
            <CountCardAdm v-show="!loader" v-if="countCard" v-for="cart of countCard.data"
                  :key="cart.key"
                  :countCard="cart"
                  :locale="countCard.locale.last_update"
            />
    </el-row>
    <p v-if="error">{{ error }}</p>
</template>

<script setup>
    import { storeToRefs } from 'pinia';
    import CountCardAdm from "../components/CountCardAdm.vue";
    import { Check } from '@element-plus/icons-vue'
    import { useDashboardStore } from "../store/dashboardStore";
    import { useProgressBarStore } from "../store/progressBarStore";
    import { useLanguageStore } from "../store/languageStore";
    import { onMounted, ref, watch } from "vue";

    const dashboardStore = useDashboardStore();
    const progressBarStore = useProgressBarStore();
    const languageStore = useLanguageStore();
    const { countCard, title, loader, error } = storeToRefs(dashboardStore);
    const { percentage } = storeToRefs(progressBarStore);
    const { watcherLang } = storeToRefs( languageStore );
    const colors = [
        { color: '#f56c6c', percentage: 20 },
        { color: '#e6a23c', percentage: 40 },
        { color: '#6f7ad3', percentage: 60 },
        { color: '#1989fa', percentage: 80 },
        { color: '#5cb87a', percentage: 100 },
    ];
    const getDashboardStore = () => {
        percentage.value = 0;
        if (loader.value){
            progressBarStore.getCurrentPercentage();
            dashboardStore.getMoviesCount();
        }
    }
    watch(() => watcherLang.value, (newLang) => {
        loader.value = true;
        getDashboardStore()
    });

    onMounted(  () => {
        getDashboardStore();
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
