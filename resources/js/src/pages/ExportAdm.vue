<template>
    <h3 class="text-center mt-3 mb-3">{{ 'Экспорт данных на сайт kinospectr'}}</h3>

        <el-row :gutter="20">
            <el-col :span="4"><div class="grid-content ep-bg-purple" />
                <el-button type="danger" class="w-100">{{$t('export')}}</el-button>
            </el-col>
            <el-col :span="16"><div class="grid-content ep-bg-purple" />
                {{'ОТВЕТ:'}}
            </el-col>
        </el-row>

</template>

<script setup>
    import { storeToRefs } from 'pinia';
    import { ref,watch,onMounted } from "vue";
    import { ElMessage, ElMessageBox } from "element-plus";
    import { useLanguageStore } from "../store/languageStore";
    import { useExportStore } from "../store/exportStore";

    const languageStore = useLanguageStore();
    const exportStore = useExportStore();
    const { watcherLang } = storeToRefs( languageStore );
    const {  } = storeToRefs( exportStore );


    watch(() => watcherLang.value, (newLang) => {

    });
    onMounted(() => {

    })



</script>

<style  lang="scss" scoped>

</style>
