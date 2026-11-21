<template>
    <h2 class="text-center text-2xl mt-3 mb-3"> {{locale.data_for_export}} </h2>
    <el-row :gutter="20">
        <el-col :span="6">
            <el-button type="warning" @click="submitExportTaxonomy()" :loading="spinBtnExportTaxonomy" :disabled="disableBtnTaxonomyExport || totalCount === 0" class="w-100 mb-3">{{$t('export_taxonomies')}}</el-button>
        </el-col>
        <el-col :span="14">
            <el-text type="success" tag="b"> {{messageExportTaxonomy}} </el-text>
        </el-col>
    </el-row>
    <el-row :gutter="20">
        <el-col :span="6">
            <el-button type="warning" @click="submitExportTags()" :loading="spinBtnExportTag" :disabled="disableBtnTagExport || totalCount === 0" class="w-100 mb-3">{{$t('export_tags')}}</el-button>
        </el-col>
        <el-col :span="14">
            <el-text type="success" tag="b"> {{messageExportTag}} </el-text>
        </el-col>
    </el-row>
    <el-row :gutter="20">
        <el-col :span="6">
            <el-button type="warning" @click="submitExportMovies()" :loading="spinBtnExportMovie" :disabled="totalCount === 0" class="w-100 mb-3">{{$t('export_movies')}}</el-button>
        </el-col>
        <el-col :span="14">
            <el-text :type="messageExportMovie.type" tag="b"> {{messageExportMovie.text}} </el-text>
        </el-col>
    </el-row>
    <el-row :gutter="20">
        <el-col :span="6">
            <el-button type="warning" @click="submitExportIndex()"  :loading="spinBtnExportIndexImg"  class="w-100 mb-3">{{$t('export_index_images')}}</el-button>
        </el-col>
    </el-row>
    <el-row :gutter="20">
        <el-col :span="6">
            <el-button type="warning" @click="submitExportPostersCol()"  :loading="spinBtnExportCollections"  class="w-100 mb-3">Export posters collections</el-button>
        </el-col>
    </el-row>
    <el-row :gutter="20" >
        <el-col v-if="tableData.length">
            <el-table :data="tableData" :v-loading="loader" :empty-text="$t('data_not_found')" style="width: 100%"  ref="multipleTableRef" >
                <el-table-column type="index" label="№"/>
                <el-table-column fixed prop="created_at" :label="locale.created_at" width="130" />
                <el-table-column prop="poster" :label="locale.poster" width="100" >
                    <template v-slot:default="scope">
                        <el-image :src="scope.row.poster" />
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
            <div class="demo-pagination-block"  v-if="totalCount >= 20">
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
        <el-col v-else slot="empty">
            <el-empty :description="locale.no_movies_for_export" :image-size="150" />
        </el-col>
    </el-row>

    <el-collapse v-model="activeCollapse" class="mt-6 mb-6 sync-spoiler">
        <el-collapse-item name="full_sync" >
            <template #title>
                <h3 class="spoiler-title">⚠️ Резервная полная синхронизация ⚠️</h3>
            </template>
            <el-row :gutter="20" class="mb-4 mt-6">
                <el-col :span="6">
                    <el-button
                        type="danger"
                        disabled
                        @click="submitFullExportMovies()"
                        :loading="spinBtnFullMovie"
                        class="w-100 mb-3"
                    >
                        Полная синхр. фильмов
                    </el-button>
                </el-col>
                <el-col :span="14">
                    <el-text :type="messageExportFullMovie.type" tag="b"> {{ messageExportFullMovie.text }} </el-text>
                    <el-row class="mb-2" v-if="countsExportFullMovie > 0">
                        <el-col><el-text>Обновлено/Отправлено: <strong>{{ countsExportFullMovie }}</strong></el-text></el-col>
                    </el-row>
                    <el-text type="info" size="small" class="d-block mt-1">
                        Принудительно обновит данные всех опубликованных фильмов (статус 2) на целевом сервере.
                    </el-text>
                </el-col>
            </el-row>
            <el-row :gutter="20" class="mb-4">
                <el-col :span="6">
                    <el-button
                        type="danger"
                        @click="submitFullExportTaxonomy()"
                        :loading="spinBtnFullTaxonomy"
                        class="w-100 mb-3"
                    >
                        Полная синхр. таксономий
                    </el-button>
                </el-col>
                <el-col :span="14">
                    <el-text type="info" size="small">
                        Полностью очистит и заново заполнит таблицы: Категории, Коллекции, Франшизы и их связи.
                    </el-text>
                </el-col>
            </el-row>
            <el-row :gutter="20">
                <el-col :span="6">
                    <el-button
                        type="danger"
                        @click="submitFullExportTags()"
                        :loading="spinBtnFullTags"
                        class="w-100 mb-3"
                    >
                        Полная синхр. тегов
                    </el-button>
                </el-col>
                <el-col :span="14">
                    <el-text type="info" size="small">
                        Полностью очистит и заново заполнит таблицы: Теги и их связи с фильмами.
                    </el-text>
                </el-col>
            </el-row>
        </el-collapse-item>
    </el-collapse>
</template>

<script setup>
import { storeToRefs } from 'pinia';
import { ref, watch, onMounted, reactive } from "vue";
import axios from 'axios';
import { ElMessage, ElMessageBox, ElNotification } from "element-plus";
import { useLanguageStore } from "../store/languageStore";
import { useExportStore } from "../store/exportStore";
import { EditPen, View } from "@element-plus/icons-vue";
import { RouterLink } from "vue-router";

const languageStore = useLanguageStore();
const exportStore = useExportStore();
const { watcherLang } = storeToRefs(languageStore);

const spinBtnFullTaxonomy = ref(false);
const spinBtnFullTags = ref(false);
const spinBtnFullMovie = ref(false);
const messageExportFullMovie = reactive({ type: "", text: "" });
const countsExportFullMovie = ref(0);
const activeCollapse = ref([]);

// ... остальной ваш код ...
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
    spinBtnExportIndexImg,
    spinBtnExportTaxonomy,
    spinBtnExportTag,
    disableBtnMovieExport,
    disableBtnTaxonomyExport,
    disableBtnTagExport
} = storeToRefs(exportStore);

const translateKey = (key) => {
    const map = {
        'categories': 'Категории',
        'collections': 'Коллекции',
        'franchises': 'Франшизы',
        'collections_categories_pivots': 'Связи (Коллекции/Франшизы)',
        'tags': 'Теги',
        'tags_movies_pivots': 'Связи (Теги/Фильмы)'
    };
    return map[key] || key;
};

watch(() => watcherLang.value, (newLang) => {
    exportStore.getExportMovies();
});

onMounted(() => {
    exportStore.getExportMovies();
});

const submitExportTaxonomy = () => {
    ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        exportStore.exportTaxonomy();
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Export canceled' });
    });
};

const submitExportTags = () => {
    ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        exportStore.exportTags();
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Export canceled' });
    });
};

const submitExportIndex = () => {
    ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        exportStore.exportIndex();
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Export Index canceled' });
    });
};

const submitExportPostersCol = () => {
    ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        exportStore.exportCollections();
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Export Collection posters canceled' });
    });
};

const submitExportMovies = () => {
    ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        exportStore.exportMovies();
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Export canceled' });
    });
};

const handleCurrentChange = (val) => {
    exportStore.updateCurrentPage(val);
    exportStore.getExportMovies();
};

const submitFullExportMovies = async () => {
    ElMessageBox.confirm(
        'ВНИМАНИЕ! Это действие отправит все опубликованные фильмы на целевой сервер для принудительного обновления данных. Процесс может занять время. Продолжить?',
        'ПОЛНАЯ СИНХРОНИЗАЦИЯ ФИЛЬМОВ',
        {
            confirmButtonText: 'Да, запустить синхронизацию',
            cancelButtonText: 'Отмена',
            type: 'warning',
        }
    ).then(async () => {
        spinBtnFullMovie.value = true;
        try {
            const response = await axios.post('/movies/send-full');
            const resData = response.data.data ? response.data.data : response.data;
            const msgType = resData.type || 'success';

            // Обновляем локальные реактивные переменные
            messageExportFullMovie.type = msgType;
            messageExportFullMovie.text = resData.message;

            if (resData.sent_count !== undefined) {
                countsExportFullMovie.value = resData.sent_count;
            }

            ElMessage({
                type: msgType,
                message: resData.message || 'Синхронизация фильмов завершена',
                duration: msgType === 'warning' ? 5000 : 3000,
            });

            if (resData.skipped_details && Array.isArray(resData.skipped_details) && resData.skipped_details.length > 0) {
                const errorListHtml = resData.skipped_details.map(error => {
                    return `<li style="margin-bottom: 8px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; font-size: 13px;">
                        <strong style="color: #856404;">⚠️ ${error}</strong>
                    </li>`;
                }).join('');

                ElNotification({
                    title: `⚠️ Пропущено фильмов: ${resData.skipped_details.length}`,
                    type: 'warning',
                    duration: 0,
                    dangerouslyUseHTMLString: true,
                    message: `<div style="max-height: 400px; overflow-y: auto; margin-top: 10px;">
                        <p style="margin-bottom: 12px; color: #666; font-weight: bold;">Следующие фильмы не прошли валидацию:</p>
                        <ul style="list-style: none; padding: 0; margin: 0;">${errorListHtml}</ul>
                    </div>`,
                    position: 'top-right',
                });
            }

        } catch (error) {
            console.error(error);
            ElMessage({ type: 'error', message: error.message || 'Ошибка соединения при полной синхронизации фильмов' });
        } finally {
            spinBtnFullMovie.value = false;
        }
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Операция отменена' });
    });
};

const submitFullExportTaxonomy = async () => {
    ElMessageBox.confirm(
        'ВНИМАНИЕ! Это действие полностью УДАЛИТ все существующие таксономии на целевом сервере и заменит их текущими данными. Продолжить?',
        'ПОЛНАЯ СИНХРОНИЗАЦИЯ',
        {
            confirmButtonText: 'Да, выполнить полную синхронизацию',
            cancelButtonText: 'Отмена',
            type: 'warning',
        }
    ).then(async () => {
        spinBtnFullTaxonomy.value = true;
        try {
            const response = await axios.post('/taxonomies/send-full');
            const resData = response.data;

            if (resData.success && resData.data) {
                ElMessage({ type: 'success', message: resData.data.message || 'Синхронизация завершена' });

                const details = resData.data.details || {};
                const reportHtml = Object.entries(details).map(([key, info]) => {
                    const statusClass = info.status === 'success' ? 'success' : 'error';
                    const icon = info.status === 'success' ? '✅' : '❌';
                    return `<div class="sync-report-item">
                            <span class="sync-report-label">${icon} ${translateKey(key)}</span>
                            <span class="sync-report-value ${statusClass}">${info.count} записей</span>
                        </div>`;
                }).join('');

                ElNotification({
                    title: '📊 Детальный отчет: Таксономии',
                    type: 'success',
                    duration: 0,
                    dangerouslyUseHTMLString: true,
                    customClass: 'sync-report-notification',
                    message: `<div class="sync-report-container">${reportHtml}</div>`,
                    position: 'top-right',
                });
            } else {
                throw new Error(resData.message || 'Ошибка синхронизации');
            }
        } catch (error) {
            console.error(error);
            ElMessage({ type: 'error', message: error.message || 'Ошибка соединения при полной синхронизации' });
        } finally {
            spinBtnFullTaxonomy.value = false;
        }
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Операция отменена' });
    });
};

const submitFullExportTags = async () => {
    ElMessageBox.confirm(
        'ВНИМАНИЕ! Это действие полностью УДАЛИТ все существующие теги на целевом сервере и заменит их текущими данными. Продолжить?',
        'ПОЛНАЯ СИНХРОНИЗАЦИЯ',
        {
            confirmButtonText: 'Да, выполнить полную синхронизацию',
            cancelButtonText: 'Отмена',
            type: 'warning',
        }
    ).then(async () => {
        spinBtnFullTags.value = true;
        try {
            const response = await axios.post('/tags/send-full');
            const resData = response.data;

            if (resData.success && resData.data) {
                ElMessage({ type: 'success', message: resData.data.message || 'Синхронизация завершена' });

                const details = resData.data.details || {};
                const reportHtml = Object.entries(details).map(([key, info]) => {
                    const statusClass = info.status === 'success' ? 'success' : 'error';
                    const icon = info.status === 'success' ? '✅' : '❌';
                    return `<div class="sync-report-item">
                            <span class="sync-report-label">${icon} ${translateKey(key)}</span>
                            <span class="sync-report-value ${statusClass}">${info.count} записей</span>
                        </div>`;
                }).join('');

                ElNotification({
                    title: '📊 Детальный отчет: Теги',
                    type: 'success',
                    duration: 0,
                    dangerouslyUseHTMLString: true,
                    customClass: 'sync-report-notification',
                    message: `<div class="sync-report-container">${reportHtml}</div>`,
                    position: 'top-right',
                });
            } else {
                throw new Error(resData.message || 'Ошибка синхронизации');
            }
        } catch (error) {
            console.error(error);
            ElMessage({ type: 'error', message: error.message || 'Ошибка соединения при полной синхронизации' });
        } finally {
            spinBtnFullTags.value = false;
        }
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Операция отменена' });
    });
};
</script>

<style  lang="scss" scoped>
:deep(.el-table .badge-column .cell) {
    padding-top: 16px !important;
    padding-bottom: 16px !important;
    display: flex;
    align-items: center;
    justify-content: center;
}
:deep(.el-notification) {
    width: auto !important;
    max-width: 500px !important;

    .el-notification__content {
        text-align: left !important;

        p {
            margin: 0;
            line-height: 1.6;
        }
    }
}

.sync-report-container {
    min-width: 380px;
    max-width: 450px;

    .sync-report-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #ebeef5;
        gap: 12px;

        &:last-child {
            border-bottom: none;
        }

        .sync-report-label {
            flex: 1;
            font-size: 13px;
            color: #606266;
            word-break: break-word;
        }

        .sync-report-value {
            flex-shrink: 0;
            font-weight: 600;
            font-size: 13px;
            text-align: right;

            &.success {
                color: #67c23a;
            }

            &.error {
                color: #f56c6c;
            }
        }
    }
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
.sync-spoiler {
    border: 1px solid #faecd8;
    border-radius: 4px;
    background-color: #fdf6ec;

    :deep(.el-collapse-item__header) {
        background-color: transparent;
        font-weight: bold;
        color: #e6a23c;
    }

    :deep(.el-collapse-item__content) {
        padding-bottom: 20px;
        background-color: #ffffff;
        border-radius: 0 0 4px 4px;
    }
}

.spoiler-title {
    margin: 0;
    text-align: center;
    font-size: 16px;
    color: #ff0000;
}
</style>
