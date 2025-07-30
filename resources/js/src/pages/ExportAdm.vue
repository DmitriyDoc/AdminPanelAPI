<template>
    <h3 class="text-center mt-3 mb-3">Data export for site  Kinospectr</h3>
    <el-row :gutter="20" >
        <el-col class="d-flex flex-row-reverse">
            <el-switch v-model="modelSwitchAllExport"/><el-text>Export all tables</el-text>
        </el-col>
    </el-row>
    <el-row :gutter="20">
        <el-col :span="6"><div class="grid-content ep-bg-purple" />
            <el-button type="danger" @click="submitExportTaxonomy()" :loading="spinBtnExportTaxonomy" :disabled="disableBtnTaxonomyExport" class="w-100 mb-3">{{$t('export_taxonomies')}}</el-button>
        </el-col>
        <el-col :span="14"><div class="grid-content ep-bg-purple" />
            <el-text type="success" tag="b"> {{messageExportTaxonomy}} </el-text>
            <el-row class="mb-2" v-if="countsExportTaxonomy">
                <el-col v-for="(count,key) in countsExportTaxonomy">
                    <el-text >{{key}} : <strong>{{count}}</strong></el-text>
                </el-col>
            </el-row>
        </el-col>
    </el-row>
    <el-row :gutter="20">
        <el-col :span="6"><div class="grid-content ep-bg-purple" />
            <el-button type="danger" @click="submitExportTags()" :loading="spinBtnExportTag" :disabled="disableBtnTagExport" class="w-100 mb-3">{{$t('export_tags')}}</el-button>
        </el-col>
        <el-col :span="14"><div class="grid-content ep-bg-purple" />
            <el-text type="success" tag="b"> {{messageExportTag}} </el-text>
            <el-row class="mb-2" v-if="countsExportTag">
                <el-col v-for="(count,key) in countsExportTag">
                    <el-text >{{key}} : <strong>{{count}}</strong></el-text>
                </el-col>
            </el-row>
        </el-col>
    </el-row>
    <el-row :gutter="20">
        <el-col :span="6"><div class="grid-content ep-bg-purple" />
            <el-button type="danger" @click="submitExportMovies()" :loading="spinBtnExportMovie" :disabled="disableBtnMovieExport" class="w-100 mb-3">{{$t('export_movies')}}</el-button>
        </el-col>
        <el-col :span="14"><div class="grid-content ep-bg-purple" />
            <el-text :type="messageExportMovie.type" tag="b"> {{messageExportMovie.text}} </el-text>
            <el-row class="mb-2" v-if="countsExportMovie">
                <el-col><el-text>Number of exported movies: <strong>{{countsExportMovie}}</strong></el-text></el-col>
            </el-row>
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
    const {
        messageExportMovie,
        messageExportTaxonomy,
        messageExportTag,
        countsExportMovie,
        countsExportTaxonomy,
        countsExportTag,
        spinBtnExportMovie,
        spinBtnExportTaxonomy,
        spinBtnExportTag,
        disableBtnMovieExport,
        disableBtnTaxonomyExport,
        disableBtnTagExport } = storeToRefs( exportStore );

    const modelSwitchAllExport = ref(false);
    watch(() => watcherLang.value, (newLang) => {

    });
    onMounted(() => {

    })
    const submitExportTaxonomy = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            exportStore.exportTaxonomy(modelSwitchAllExport.value);
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Export canceled',
            })
        })
    }
    const submitExportTags = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            exportStore.exportTags(modelSwitchAllExport.value);
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Export canceled',
            })
        })
    }
    const submitExportMovies = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            exportStore.exportMovies(modelSwitchAllExport.value);
            modelSwitchAllExport.value = false;
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Export canceled',
            })
        })
    }


</script>

<style  lang="scss" scoped>

</style>
