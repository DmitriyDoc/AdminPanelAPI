<template>
    <div v-if="chartInfo">
        <div class="chart-header mb-3 justify-center">
            <el-text tag="b" size="large">{{ locale.chart_title }} {{ chartInfo.title }}</el-text>
            <el-tag type="info" class="ml-3">{{ chartInfo.chart_id }}</el-tag>
            <span class="ml-3">({{ chartInfo.movies_count }} {{ $t('movies') }})</span>
        </div>

        <div class="mb-3">
            <RouterLink :to="{ name: 'charts' }">
                <el-button type="info" class="mt-2 mb-2">
                    {{ locale.back_to_charts || $t('back_to_charts') }}
                </el-button>
            </RouterLink>
        </div>

        <p>{{ locale.search_by_title_id }}</p>
        <el-form
            ref="formRef"
            :model="queryValidateForm"
            class="demo-ruleForm"
        >
            <el-form-item prop="query" :rules="[{}]">
                <el-input
                    v-model="queryValidateForm.query"
                    type="text"
                    autocomplete="off"
                    :placeholder="locale.search_here"
                    v-on:keydown.enter.prevent="submitSearch(formRef)"
                />
            </el-form-item>
            <el-form-item>
                <el-button @click="resetSearch(formRef)">{{ locale.reset }}</el-button>
                <el-button @click="submitSearch(formRef)">{{ locale.go }}</el-button>
            </el-form-item>
        </el-form>

        <div class="demo-pagination-block">
            <p>{{ locale.spin_by }}</p>
            <el-switch
                v-model="defaultSpin"
                class="mb-2"
                active-text="&#8595;"
                inactive-text="&#8593;"
                @change="handleSwitchChange"
            />
            <p>{{ locale.sort_by }}</p>
            <el-select
                v-model="valueSort"
                filterable
                @change="handleSelectChange"
                style="width: 240px"
            >
                <el-option
                    v-for="field in locale.table_sort_fields"
                    :key="field.value"
                    :label="field.label"
                    :value="field.value"
                />
            </el-select>
            <div class="demonstration">{{ locale.jump_to }}</div>
            <el-pagination
                v-model:current-page="currentPage"
                v-model:page-size="pageSize"
                :small="small"
                :disabled="disabled"
                :background="background"
                layout="sizes, prev, pager, next, jumper"
                :total="totalCount"
                :page-sizes="[20, 50, 100, 300]"
                @size-change="handleSizeChange"
                @current-change="handleCurrentChange"
            />
        </div>
    </div>
    <el-table :data="tableData" v-loading="loader" :empty-text="$t('data_not_found')" style="width: 100%" ref="multipleTableRef">
        <el-table-column type="index" label="№"/>
        <el-table-column fixed prop="created_at" :label="locale.created_at" width="130" />
        <el-table-column prop="poster" :label="locale.poster" width="150">
            <template v-slot:default="scope">
                <el-image :src="scope.row.poster" style="width: 100px; height: 150px" fit="cover" />
            </template>
        </el-table-column>
        <el-table-column
            prop="poster_source"
            :label="locale.poster_source"
            width="120"
            align="center"
            class-name="badge-column"
        >
            <template v-slot:default="scope">
                <div class="badge-wrapper">
                    <template v-if="scope.row.poster_source && scope.row.poster_source !== '-'">
                        <el-badge
                            v-if="scope.row.poster_source.toLowerCase() === 'tmdb'"
                            value="TMDB"
                            class="badge-tmdb-el"
                        />
                        <el-badge
                            v-else-if="scope.row.poster_source.toLowerCase() === 'imdb'"
                            value="IMDB"
                            class="badge-imdb-el"
                        />
                        <span v-else style="color: #909399; font-size: 14px;">{{ scope.row.poster_source }}</span>
                    </template>
                    <span v-else style="color: #909399; font-size: 14px;">-</span>
                </div>
            </template>
        </el-table-column>
        <el-table-column prop="published" :label="locale.status" width="170">
            <template v-slot:default="scope">
                <el-text :type="scope.row.published.status_type">
                    <strong>{{ scope.row.published.status_text }}</strong>
                </el-text>
            </template>
        </el-table-column>
        <el-table-column prop="id_movie" :label="locale.id_movie" width="120" />
        <el-table-column prop="year_release" :label="locale.year" width="100" />
        <el-table-column prop="title" :label="locale.title" width="500" />
        <el-table-column prop="updated_at" :label="locale.updated_at" width="150" />
        <el-table-column fixed="right" prop="id_movie" :label="locale.actions" width="160">
            <template v-slot:default="scope">
                <el-button
                    link
                    type="warning"
                    :icon="Refresh"
                    :title="locale.sync_movie || 'Sync with IMDB'"
                    @click="handleSyncMovie(scope.row.id_movie, scope.row.type_film_string)"
                />

                <el-button type="success" link>
                    <RouterLink :to="{ name: 'showMovie', params: { id: scope.row.id_movie }}">
                        <el-button link type="primary" :icon="View" :title="$t('details')" />
                    </RouterLink>
                </el-button>
                <el-button link type="primary">
                    <RouterLink :to="{ name: 'editMovie', params: { id: scope.row.id_movie }}">
                        <el-button link type="primary" :icon="EditPen" :title="$t('edit')" />
                    </RouterLink>
                </el-button>
            </template>
        </el-table-column>
    </el-table>

    <el-backtop :right="100" :bottom="100" />
    <p v-if="error">{{ error }}</p>
</template>

<script lang="js" setup>
import { storeToRefs } from 'pinia';
import { useChartMoviesStore } from "../store/chartMoviesStore";
import { useLanguageStore } from "../store/languageStore";
import { RouterLink, useRouter } from 'vue-router';
import { View, EditPen, Refresh } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { onMounted, ref, watch, reactive } from "vue";

const chartMoviesStore = useChartMoviesStore();
const languageStore = useLanguageStore();
const router = useRouter();
const { watcherLang } = storeToRefs(languageStore);
const { tableData, chartInfo, locale, totalCount, currentPage, pageSize, valueSort, route, loader, error } = storeToRefs(chartMoviesStore);

const small = ref(false);
const background = ref(false);
const disabled = ref(false);
const defaultSpin = ref(false);

const formRef = ref();
const queryValidateForm = reactive({
    query: '',
});

watch(() => watcherLang.value, (newLang) => {
    chartMoviesStore.getChartMovies();
});

const goBack = () => {
    router.push({ name: 'charts' });
}

// 🔧 ИСПРАВЛЕНО: используем updatePageSize вместо прямой записи
// updatePageSize сбросит страницу на 1, чтобы избежать пустых страниц
const handleSizeChange = (val) => {
    chartMoviesStore.updatePageSize(val);
    chartMoviesStore.getChartMovies();
}

const handleCurrentChange = (val) => {
    chartMoviesStore.updateCurrentPage(val);
    chartMoviesStore.getChartMovies();
}

const handleSelectChange = (val) => {
    chartMoviesStore.updateSort(val);
    chartMoviesStore.getChartMovies();
}

const handleSwitchChange = (val) => {
    let spin = val ? "asc" : 'desc';
    chartMoviesStore.updateSpin(spin);
    chartMoviesStore.getChartMovies();
}

const submitSearch = (formEl) => {
    if (!formEl) return
    formEl.validate((valid) => {
        if (valid) {
            chartMoviesStore.updateSearchQuery(queryValidateForm.query);
            chartMoviesStore.getChartMovies();
        } else {
            console.log('error submit!')
            return false
        }
    })
}

const resetSearch = (formEl) => {
    if (!formEl) return
    formEl.resetFields();
    chartMoviesStore.updateSearchQuery('');
    chartMoviesStore.getChartMovies();
}

const handleSyncMovie = (movieId, typeFilmString) => {
    ElMessageBox.confirm(
        `Start synchronization with IMDB for movie: ${movieId}?`,
        'Confirm Synchronization',
        {
            confirmButtonText: 'Yes, sync',
            cancelButtonText: 'Cancel',
            type: 'info',
        }
    ).then(() => {
        chartMoviesStore.syncMovie(movieId, typeFilmString);
    }).catch(() => {
        ElMessage({
            type: 'info',
            message: 'Synchronization canceled',
        });
    });
}

onMounted(() => {
    chartMoviesStore.getChartMovies();
});
</script>

<style lang="scss" scoped>
.chart-header {
    display: flex;
    align-items: center;
    padding: 10px 0;
}

.layout-container-demo .el-header {
    position: relative;
    background-color: var(--el-color-primary-light-7);
    color: var(--el-text-color-primary);
}

.layout-container-demo .el-aside {
    color: var(--el-text-color-primary);
    background: var(--el-color-primary-light-8);
}

.layout-container-demo .el-menu {
    border-right: none;
}

.layout-container-demo .el-main {
    padding: 0;
}

.layout-container-demo .toolbar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    right: 20px;
}
:deep(.el-table .badge-column .cell) {
    padding-top: 16px !important;
    padding-bottom: 16px !important;
    display: flex;
    align-items: center;
    justify-content: center;
}

.badge-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
}

.badge-tmdb-el :deep(.el-badge__content) {
    background-color: #e6fffa !important;
    color: #2c7a7b !important;
    border: 1px solid #81e6d9 !important;
    font-weight: 700;
    font-size: 11px;
    padding: 0 8px !important;
    height: 22px !important;
    line-height: 22px !important;
    border-radius: 11px !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    margin-top: 0 !important;
    top: 0 !important;
}

.badge-imdb-el :deep(.el-badge__content) {
    background-color: #F5C518 !important;
    color: #000000 !important;
    border: 1px solid #d4a90f !important;
    font-weight: 800;
    font-size: 11px;
    padding: 0 8px !important;
    height: 22px !important;
    line-height: 22px !important;
    border-radius: 11px !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.15);
    margin-top: 0 !important;
    top: 0 !important;
}
</style>
