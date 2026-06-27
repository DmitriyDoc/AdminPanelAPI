<template>
    <!-- 🔧 Прогресс-бар парсинга чартов -->
    <el-card v-if="chartProgress.status" class="mb-4" shadow="never">
        <template #header>
            <div class="card-header">
                <span>{{ chartProgress.action || 'Parsing charts...' }}</span>
                <el-tag :type="statusTagType" size="small">
                    {{ chartProgress.percent || 0 }}%
                </el-tag>
            </div>
        </template>
        <el-progress
            :percentage="chartProgress.percent || 0"
            :color="progressColorStops"
            :stroke-width="22"
            :text-inside="true"
            :status="progressStatus"
        />
        <div class="progress-details mt-2" v-if="chartProgress.current_chart_id">
            <el-text type="info" size="small">
                {{ locale.currently_parsing || 'Currently parsing' }}:
                <strong>{{ chartProgress.current_chart_id }}</strong>
                <span v-if="chartProgress.current && chartProgress.total">
                    ({{ chartProgress.current }} / {{ chartProgress.total }})
                </span>
            </el-text>
        </div>
        <div class="progress-details mt-1" v-if="chartProgress.status === 'completed'">
            <el-text type="success" size="small">
                ✅ {{ locale.parsing_completed || 'Parsing completed' }}
                <span v-if="chartProgress.success_count !== undefined">
                    - {{ locale.success || 'Success' }}: {{ chartProgress.success_count }},
                    {{ locale.failed || 'Failed' }}: {{ chartProgress.failed_count }}
                </span>
            </el-text>
        </div>
        <div class="progress-details mt-1" v-if="chartProgress.status === 'error'">
            <el-text type="danger" size="small">
                ❌ {{ locale.parsing_error || 'Parsing error' }}
            </el-text>
        </div>
    </el-card>

    <!-- Панель управления парсером -->
    <el-card class="mb-4" shadow="never">
        <template #header>
            <div class="card-header">
                <span>{{ locale.parser_controls }}</span>
            </div>
        </template>

        <el-row :gutter="20">
            <!-- 🔧 Кнопка обновить все - заблокирована во время парсинга -->
            <el-col :span="6">
                <el-button
                    type="danger"
                    :loading="parserLoading"
                    :disabled="isChartParsing"
                    @click="handleSyncAll"
                    style="width: 100%"
                >
                    {{ locale.sync_all_charts }}
                </el-button>
            </el-col>

            <!-- 🔧 Форма добавления/обновления списка по ID -->
            <el-col :span="18">
                <el-form :inline="true" :model="chartForm" class="demo-form-inline">
                    <el-form-item :label="locale.chart_id">
                        <el-input
                            v-model="chartForm.id"
                            :placeholder="locale.chart_id_placeholder"
                            :disabled="isChartParsing"
                            style="width: 200px"
                            @keyup.enter="handleSyncOne(chartForm.id)"
                        />
                    </el-form-item>
                    <el-form-item>
                        <el-button
                            type="warning"
                            :loading="parserLoading"
                            :disabled="!chartForm.id || isChartParsing"
                            @click="handleSyncOne(chartForm.id)"
                        >
                            {{ locale.parse_chart }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </el-col>
        </el-row>

        <!-- 🔧 Индикатор активного парсинга -->
        <el-alert
            v-if="isChartParsing"
            :title="'Parsing in progress... Please wait.'"
            type="warning"
            :closable="false"
            show-icon
            class="mt-2"
        />
    </el-card>

    <!-- Поиск -->
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
                @keydown.enter.prevent="submitSearch(formRef)"
            />
        </el-form-item>
        <el-form-item>
            <el-button @click="resetSearch(formRef)">{{ locale.reset }}</el-button>
            <el-button @click="submitSearch(formRef)">{{ locale.go }}</el-button>
        </el-form-item>
    </el-form>

    <!-- Пагинация и сортировка -->
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

    <!-- Таблица -->
    <el-table :data="tableData" v-loading="loader" :empty-text="$t('data_not_found')" style="width: 100%" ref="multipleTableRef">
        <el-table-column type="index" label="№"/>
        <el-table-column prop="chart_id" :label="locale.chart_id" width="150" />
        <el-table-column prop="title" :label="locale.title_chart" width="500">
            <template #default="scope">
                <RouterLink
                    v-if="scope.row.chart_id"
                    :to="{ name: 'chartMovies', params: { chart_id: scope.row.chart_id } }"
                    class="chart-title-link"
                >
                    <strong>{{ scope.row.title }}</strong>
                </RouterLink>
                <span v-else>{{ scope.row.title }}</span>
            </template>
        </el-table-column>
        <el-table-column prop="movies_count" :label="locale.movies_count" width="150" />
        <el-table-column prop="last_parsed_at" :label="locale.last_parsed_at" width="180" />
        <el-table-column prop="updated_at" :label="locale.updated_at" width="150" />
        <el-table-column fixed="right" prop="chart_id" :label="locale.actions" width="140">
            <template #default="scope">
                <div class="actions-cell">
                    <!-- 🔧 Кнопка синхронизации - заблокирована во время парсинга -->
                    <el-button
                        link
                        type="warning"
                        :icon="Refresh"
                        :title="locale.sync_chart"
                        :disabled="isChartParsing"
                        @click="handleSyncOne(scope.row.chart_id)"
                    />

                    <RouterLink v-if="scope.row.chart_id" :to="{ name: 'chartMovies', params: { chart_id: scope.row.chart_id }}">
                        <el-button link type="primary" :icon="View" :title="locale.view_movies"/>
                    </RouterLink>

                    <el-button link type="danger" @click="handleRemove(scope.row.chart_id, scope.$index)" :icon="Delete" :title="locale.remove" />
                </div>
            </template>
        </el-table-column>
    </el-table>

    <el-backtop :right="100" :bottom="100" />
    <p v-if="error">{{ error }}</p>
</template>

<script setup>
import { storeToRefs } from 'pinia';
import { useChartsStore } from "../store/chartsStore";
import { useLanguageStore } from "../store/languageStore";
import { useProgressBarStore } from "../store/progressBarStore";
import { RouterLink } from 'vue-router';
import { Delete, View, Refresh } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { onMounted, onUnmounted, ref, watch, reactive, computed } from "vue";

const chartsStore = useChartsStore();
const languageStore = useLanguageStore();
const progressBarStore = useProgressBarStore();

const { watcherLang } = storeToRefs(languageStore);
const {
    tableData,
    locale,
    totalCount,
    currentPage,
    pageSize,
    valueSort,
    route,
    loader,
    error,
    parserLoading
} = storeToRefs(chartsStore);

const { chartProgress } = storeToRefs(progressBarStore);

// 🔧 Вычисление: идёт ли сейчас парсинг чартов
const isChartParsing = computed(() => {
    const status = chartProgress.value?.status;
    const percent = chartProgress.value?.percent || 0;

    // Процесс идёт, если:
    // 1. Статус 'start' или 'processing'
    // 2. ИЛИ статус 'success'/'error' (отдельного чарта), но процент ещё не 100
    if (status === 'start' || status === 'processing') {
        return true;
    }

    // Если статус 'success' или 'error' от отдельного чарта,
    // но процент < 100 — значит общий процесс ещё идёт
    if ((status === 'success' || status === 'error') && percent < 100) {
        return true;
    }

    return false;
});

// 🔧 Цвета прогресс-бара в зависимости от процента заполнения
// danger (0-25%) → warning (25-50%) → primary (50-75%) → success (75-100%)
const progressColorStops = computed(() => [
    { color: '#F56C6C', percentage: 25 },  // danger - красный
    { color: '#E6A23C', percentage: 50 },  // warning - оранжевый
    { color: '#409EFF', percentage: 75 },  // primary - синий
    { color: '#67C23A', percentage: 100 }, // success - зелёный
]);

// 🔧 Статус тега с процентом
const statusTagType = computed(() => {
    const percent = chartProgress.value?.percent || 0;
    const status = chartProgress.value?.status;

    if (status === 'completed') return 'success';
    if (status === 'error') return 'danger';
    if (percent <= 25) return 'danger';
    if (percent <= 50) return 'warning';
    if (percent <= 75) return '';  // primary (default)
    return 'success';
});

// 🔧 Статус самого el-progress (для иконки в конце)
const progressStatus = computed(() => {
    const status = chartProgress.value?.status;
    if (status === 'completed') return 'success';
    if (status === 'error') return 'exception';
    return '';
});

const small = ref(false);
const background = ref(false);
const disabled = ref(false);
const defaultSpin = ref(false);

const formRef = ref(null);
const queryValidateForm = reactive({
    query: '',
});

const chartForm = reactive({
    id: ''
});

watch(() => watcherLang.value, (newLang) => {
    chartsStore.getCharts();
});

const handleSizeChange = (val) => {
    // 🔧 updatePageSize теперь сбрасывает страницу на 1
    chartsStore.updatePageSize(val);
    chartsStore.getCharts();
};

const handleCurrentChange = (val) => {
    chartsStore.updateCurrentPage(val);
    chartsStore.getCharts();
};

const handleSelectChange = (val) => {
    chartsStore.updateSort(val);
    chartsStore.getCharts();
};

const handleSwitchChange = (val) => {
    let spin = val ? "asc" : 'desc';
    chartsStore.updateSpin(spin);
    chartsStore.getCharts();
};

const submitSearch = (formEl) => {
    if (!formEl) return;
    formEl.validate((valid) => {
        if (valid) {
            chartsStore.updateSearchQuery(queryValidateForm.query);
            chartsStore.getCharts();
        } else {
            console.log('error submit!');
            return false;
        }
    });
};

const resetSearch = (formEl) => {
    if (!formEl) return;
    formEl.resetFields();
    chartsStore.updateSearchQuery('');
    chartsStore.getCharts();
};

const handleRemove = (chartId, index) => {
    ElMessageBox.confirm(
        locale.value.confirm_delete_message?.replace(':id', chartId) || `Are you sure? Chart with ID: ${chartId} will be deleted. Continue?`,
        locale.value.confirm_delete_title || 'WARNING',
        {
            confirmButtonText: locale.value.confirm_button || 'OK',
            cancelButtonText: locale.value.cancel_button || 'Cancel',
            type: 'warning',
        }
    ).then(() => {
        chartsStore.removeItem(chartId, index);
    }).catch(() => {
        ElMessage({
            type: 'info',
            message: locale.value.delete_canceled || 'Delete canceled',
        });
    });
};

// 🔧 Защита от повторного запуска при активном парсинге
const guardParsing = () => {
    if (isChartParsing.value) {
        ElMessage({
            type: 'warning',
            message:'Parsing is already in progress. Please wait.',
        });
        return true;
    }
    return false;
};

const handleSyncAll = () => {
    if (guardParsing()) return;

    ElMessageBox.confirm(
        locale.value.confirm_sync_all_message || 'This will queue parsing for ALL charts (in0000001 - in0000313). It may take a long time. Continue?',
        locale.value.confirm_sync_all_title || 'Confirmation',
        {
            confirmButtonText: locale.value.confirm_button || 'Yes, start parsing',
            cancelButtonText: locale.value.cancel_button || 'Cancel',
            type: 'warning',
        }
    ).then(() => {
        chartsStore.syncAllCharts();
    }).catch(() => {
        ElMessage({
            type: 'info',
            message: locale.value.parsing_canceled || 'Parsing canceled',
        });
    });
};

const handleSyncOne = (chartId) => {
    if (guardParsing()) return;

    if (!chartId) {
        ElMessage({
            type: 'warning',
            message: locale.value.enter_chart_id || 'Please enter chart ID',
        });
        return;
    }

    if (!chartId.match(/^(in|ls)\d{7,10}$/)) {
        ElMessage({
            type: 'warning',
            message: locale.value.invalid_chart_id || 'Invalid chart ID format. Use format like in0000312 or ls1234567',
        });
        return;
    }

    ElMessageBox.confirm(
        locale.value.confirm_sync_one_message?.replace(':id', chartId) || `Start parsing chart with ID: ${chartId}?`,
        locale.value.confirm_sync_one_title || 'Confirm Parsing',
        {
            confirmButtonText: locale.value.confirm_button || 'Yes, parse',
            cancelButtonText: locale.value.cancel_button || 'Cancel',
            type: 'info',
        }
    ).then(() => {
        chartsStore.syncChart(chartId);
        chartForm.id = '';
    }).catch(() => {
        ElMessage({
            type: 'info',
            message: locale.value.parsing_canceled || 'Parsing canceled',
        });
    });
};

onMounted(() => {
    chartsStore.getCharts();
    progressBarStore.getChartProgressState();
});
</script>

<style lang="scss" scoped>
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chart-title-link {
    color: #000;
    text-decoration: none;
    font-weight: bold;

    &:hover {
        text-decoration: underline;
    }
}
.actions-cell {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}
.progress-details {
    margin-top: 8px;
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
</style>
