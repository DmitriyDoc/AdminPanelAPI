<template>
    <h3 class="text-center mt-3 mb-3"> {{locale.data_for_export}} </h3>
    <el-row :gutter="20" >
        <el-col class="d-flex flex-row-reverse">
            <el-switch v-model="modelSwitchAllExport"/><el-text>{{ locale.export_all_tables }}</el-text>
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
    <el-row :gutter="20" >
        <el-col >
            <el-table v-if="tableData['id_movie']" :data="tableData" :v-loading="loader" :empty-text="$t('data_not_found')" style="width: 100%"  ref="multipleTableRef" >
                <el-table-column type="index" label="№"/>
                <el-table-column fixed prop="created_at" :label="locale.created_at" width="130" />
                <el-table-column prop="poster" :label="locale.poster" width="100" >
                    <template v-slot:default="scope">
                        <el-image :src="scope.row.poster" />
                    </template>
                </el-table-column>
                <el-table-column prop="published" :label="locale.status" width="130" >
                    <template v-slot:default="scope">
                        <el-text :type="scope.row.published.status_type">
                            <strong>{{scope.row.published.status_text}}</strong>
                        </el-text>
                    </template>
                </el-table-column>
                <el-table-column prop="id_movie" :label="locale.id_movie" width="120" />
                <el-table-column prop="year_release" :label="locale.year" width="100" />
                <el-table-column prop="title" :label="locale.title" width="600" />
                <el-table-column prop="updated_at" :label="locale.updated_at" width="150" />
                <el-table-column fixed="right" prop="id_movie" :label="locale.actions" width="120">
                    <template v-slot:default="scope">
                        <el-button type="success" link >
                            <RouterLink :to="{ name: 'showMovie', params: { id: scope.row.id_movie }}">
                                <el-button link type="primary" :icon="View" :title="$t('details')"/>
                            </RouterLink>
                        </el-button>
                        <el-button link type="primary" >
                            <RouterLink :to="{ name: 'editMovie', params: { id: scope.row.id_movie }}">
                                <el-button link type="primary" :icon="EditPen" :title="$t('edit')"/>
                            </RouterLink>
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div  v-else slot="empty">
                <el-empty :description="locale.no_movies_for_export" :image-size="150" />
            </div>

            <div class="demo-pagination-block"  v-if="totalCount >= 100">
                <div class="demonstration">{{locale.jump_to}}</div>
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :small="false"
                    :disabled="false"
                    :background="false"
                    layout="prev, pager, next"
                    :total="totalCount"
                    @current-change="handleCurrentChange"
                />
            </div>
        </el-col>
    </el-row>
</template>

<script setup>
    import { storeToRefs } from 'pinia';
    import { ref,watch,onMounted } from "vue";
    import { ElMessage, ElMessageBox } from "element-plus";
    import { useLanguageStore } from "../store/languageStore";
    import { useExportStore } from "../store/exportStore";
    import {Delete, EditPen, View} from "@element-plus/icons-vue";
    import {RouterLink} from "vue-router";

    const languageStore = useLanguageStore();
    const exportStore = useExportStore();
    const { watcherLang } = storeToRefs( languageStore );
    const {
        tableData,
        locale,
        loader,
        totalCount,
        pageSize,
        currentPage,
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
        exportStore.getExportMovies();
    });
    onMounted(() => {
        exportStore.getExportMovies();
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
    const handleCurrentChange = (val) => {
        exportStore.updateCurrentPage(val);
        exportStore.getExportMovies();
    }

</script>

<style  lang="scss" scoped>

</style>
