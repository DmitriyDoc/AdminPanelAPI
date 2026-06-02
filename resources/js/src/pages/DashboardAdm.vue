<template>
    <h3 class="text-center mt-3 mb-3">{{ title }}</h3>
    <div class="font-bold">
        {{ countCard?.locale?.published_total ?? '' }}
        {{ countCard?.total_published ?? '' }}
    </div>
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
                      :locale="countCard.locale"
        />
    </el-row>


    <!-- 📊 ГРАФИК 1: Распределение по коллекциям -->
    <el-card v-if="!loader && collectionGraph?.data?.length"
             class="mt-4 chart-card" shadow="hover">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <span class="font-bold text-lg text-gray-800">Распределение по коллекциям</span>

                    <!-- 🔹 Переключатель: Группировка / По убыванию -->
                    <el-switch
                        v-model="collectionsViewMode"
                        active-value="flat"
                        inactive-value="grouped"
                        active-text="По убыванию"
                        inactive-text="По категориям"
                        size="small"
                        inline-prompt
                    />
                </div>

                <div class="text-sm text-gray-500">
                    Фильмов в коллекциях:
                    <span class="font-bold text-blue-600">{{ collectionGraph.meta.total_in_graph }}</span>
                </div>
            </div>
        </template>

        <div class="chart-wrapper">
            <!-- Ось Y -->
            <div class="y-axis">
                <span v-for="(tick, idx) in yConfigCollections.ticks" :key="tick"
                      class="y-label"
                      :style="{ top: (idx / (yConfigCollections.ticks.length - 1)) * 100 + '%' }">
                    {{ tick }}
                </span>
            </div>

            <div class="chart-scroll">
                <div class="chart-body">
                    <!-- Сетка -->
                    <div class="grid">
                        <div v-for="(tick, idx) in yConfigCollections.ticks" :key="idx"
                             class="grid-line"
                             :style="{ top: (idx / (yConfigCollections.ticks.length - 1)) * 100 + '%' }">
                        </div>
                    </div>

                    <!-- Столбцы (используем displayCollectionsData) -->
                    <div class="bars-container">
                        <div v-for="cat in displayCollectionsData" :key="cat.id" class="category-block">
                            <div class="bars-group">
                                <div
                                    v-for="coll in cat.collections"
                                    :key="coll.id"
                                    class="bar-wrapper"
                                    @mouseenter="showTooltipCollections(coll, cat, $event)"
                                    @mouseleave="hideTooltip"
                                >
                                    <div class="bar-value-top">{{ coll.count }}</div>
                                    <div
                                        class="bar"
                                        :class="getColorClass(cat.color_key)"
                                        :style="{ height: getBarHeightCollections(coll.count) + 'px' }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Название категории показываем только в группированном режиме -->
                            <div v-if="collectionsViewMode === 'grouped' && cat.name" class="category-title">
                                {{ cat.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Легенда (скрываем в плоском режиме) -->
        <div class="legend" v-if="collectionsViewMode === 'grouped'">
            <div v-for="cat in collectionsLegendItems" :key="cat.id" class="legend-item">
                <span class="legend-dot" :class="getColorClass(cat.color_key)"></span>
                <span class="legend-label">
                    {{ cat.name }}
                    <span class="legend-sum">({{ cat.collections.reduce((s,c) => s + c.count, 0) }})</span>
                </span>
            </div>
        </div>

        <!-- Тултип -->
        <div v-if="tooltip.visible && activeTooltip === 'collections'"
             class="custom-tooltip"
             :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }"
        >
            <div class="tooltip-header">
                <span class="tooltip-collection" :class="tooltip.textColorClass">{{ tooltip.collLabel }}</span>
            </div>
            <div class="tooltip-body">
                <div class="tooltip-count-large">{{ tooltip.count }}</div>
                <div class="tooltip-label-small">фильмов</div>
            </div>
            <div class="tooltip-footer">
                Категория: <strong>{{ tooltip.catName }}</strong>
            </div>
            <div class="tooltip-arrow"></div>
        </div>
    </el-card>

    <!-- 📊 ГРАФИК 2: Статистика по годам (НОВЫЙ) -->
    <el-card v-if="!loader && yearsGraph?.data?.by_count?.length"
             class="mt-4 chart-card" shadow="hover">
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <span class="font-bold text-lg text-gray-800">Статистика по годам</span>

                    <!-- Под-переключатель внутри графика по годам -->
                    <el-radio-group
                        v-model="yearsViewMode"
                        size="mini"
                        @change="onYearsViewChange"
                    >
                        <el-radio-button label="count">Количество</el-radio-button>
                        <el-radio-button label="rating">Рейтинг</el-radio-button>
                    </el-radio-group>
                </div>

                <div class="text-sm text-gray-500">
                    Всего фильмов:
                    <span class="font-bold text-blue-600">{{ yearsGraph?.meta?.total_published ?? 0 }}</span>
                </div>
            </div>
        </template>

        <div class="chart-wrapper">
            <!-- Ось Y (динамическая) -->
            <div class="y-axis">
                <span v-for="(tick, idx) in yConfigYears.ticks" :key="tick"
                      class="y-label"
                      :style="{ top: (idx / (yConfigYears.ticks.length - 1)) * 100 + '%' }">
                    {{ yearsViewMode === 'rating' ? (tick === 0 ? '0' : tick) : tick }}
                </span>
            </div>

            <div class="chart-scroll">
                <div class="chart-body">
                    <!-- Сетка -->
                    <div class="grid">
                        <div v-for="(tick, idx) in yConfigYears.ticks" :key="idx"
                             class="grid-line"
                             :style="{ top: (idx / (yConfigYears.ticks.length - 1)) * 100 + '%' }">
                        </div>
                    </div>

                    <!-- Столбцы -->
                    <div class="bars-container">
                        <!-- Режим: по количеству -->
                        <template v-if="yearsViewMode === 'count'">
                            <div v-for="year in yearsGraph.data.by_count" :key="year.id" class="category-block">
                                <div class="bars-group">
                                    <div
                                        v-for="coll in year.collections"
                                        :key="coll.id"
                                        class="bar-wrapper"
                                        @mouseenter="showTooltipYears(coll, year, $event)"
                                        @mouseleave="hideTooltip"
                                    >
                                        <div class="bar-value-top">{{ coll.count }}</div>
                                        <div
                                            class="bar"
                                            :class="getColorClass(year.color_key)"
                                            :style="{ height: getBarHeightYears(coll.count) + 'px' }"
                                        ></div>
                                    </div>
                                </div>
                                <div class="category-title">{{ year.name }}</div>
                            </div>
                        </template>

                        <!-- Режим: по рейтингу -->
                        <template v-if="yearsViewMode === 'rating'">
                            <div v-for="year in yearsGraph.data.by_rating" :key="year.id" class="category-block">
                                <div class="bars-group">
                                    <div
                                        v-for="coll in year.collections"
                                        :key="coll.id"
                                        class="bar-wrapper"
                                        @mouseenter="showTooltipYears(coll, year, $event)"
                                        @mouseleave="hideTooltip"
                                    >
                                        <div class="bar-value-top">{{ coll.count }}</div>
                                        <div
                                            class="bar"
                                            :class="getColorClass(coll.color_key)"
                                            :style="{ height: getBarHeightYears(coll.count) + 'px' }"
                                            :title="coll.label"
                                        ></div>
                                    </div>
                                </div>
                                <div class="category-title">{{ year.name }}</div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Легенда для режима "по рейтингу" -->
        <div class="legend" v-if="yearsViewMode === 'rating'">
            <div v-for="bucket in ratingLegend" :key="bucket.id" class="legend-item">
                <span class="legend-dot" :class="getColorClass(bucket.color_key)"></span>
                <span class="legend-label">{{ bucket.name }}</span>
            </div>
        </div>

        <!-- Тултип для графика по годам -->
        <div v-if="tooltip.visible && activeTooltip === 'years'"
             class="custom-tooltip"
             :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }"
        >
            <div class="tooltip-header">
                <span class="tooltip-collection" :class="tooltip.textColorClass">
                    {{ yearsViewMode === 'count' ? tooltip.collLabel : `Рейтинг ${tooltip.collLabel}` }}
                </span>
            </div>
            <div class="tooltip-body">
                <div class="tooltip-count-large">{{ tooltip.count }}</div>
                <div class="tooltip-label-small">
                    {{ yearsViewMode === 'count' ? 'фильмов' : 'фильмов в диапазоне' }}
                </div>
            </div>
            <div class="tooltip-footer">
                Год: <strong>{{ tooltip.catName }}</strong>
            </div>
            <div class="tooltip-arrow"></div>
        </div>
    </el-card>

    <!-- Пустые состояния -->
    <el-empty v-if="!loader && !collectionGraph?.data?.length"
              description="Нет данных для графика по коллекциям" class="mt-6" />
    <el-empty v-if="!loader && !yearsGraph?.data?.by_count?.length"
              description="Нет данных для графика по годам" class="mt-6" />

    <p v-if="error" class="text-red-500 text-center mt-4">{{ error }}</p>
</template>

<script setup>
import {storeToRefs} from 'pinia';
import CountCardAdm from "../components/CountCardAdm.vue";
import {useDashboardStore} from "../store/dashboardStore";
import {useProgressBarStore} from "../store/progressBarStore";
import {useLanguageStore} from "../store/languageStore";
import {ref, computed, onMounted, watch, onUnmounted, nextTick} from "vue";

const dashboardStore = useDashboardStore();
const progressBarStore = useProgressBarStore();
const languageStore = useLanguageStore();
const {countCard, title, loader, error} = storeToRefs(dashboardStore);
const {percentage} = storeToRefs(progressBarStore);
const {watcherLang} = storeToRefs(languageStore);

const colors = [
    {color: '#f56c6c', percentage: 20},
    {color: '#e6a23c', percentage: 40},
    {color: '#6f7ad3', percentage: 60},
    {color: '#1989fa', percentage: 80},
    {color: '#5cb87a', percentage: 100},
];

// ============================================================================
// 🔹 ЛОКАЛЬНЫЕ ДАННЫЕ ГРАФИКОВ (остаёмся в компоненте)
// ============================================================================
const collectionGraph = ref(null);
const yearsGraph = ref(null);

// ============================================================================
// 🔹 UI-СОСТОЯНИЕ (только в компоненте)
// ============================================================================
const collectionsViewMode = ref('grouped'); // 'grouped' | 'flat'
const yearsViewMode = ref('count');          // 'count' | 'rating'
const tooltip = ref({ visible: false, x: 0, y: 0, collLabel: '', count: 0, catName: '', textColorClass: '' });
const activeTooltip = ref(null); // 'collections' | 'years'
let progressTimer = null;

// ============================================================================
// 🔹 ЦВЕТОВЫЕ КАРТЫ (общие утилиты)
// ============================================================================
const colorMap = {
    'yellow': 'bg-yellow-400', 'green': 'bg-green-400', 'blue': 'bg-blue-400',
    'cyan': 'bg-cyan-400', 'purple': 'bg-purple-400', 'black': 'bg-gray-700',
    'brown': 'bg-amber-600', 'red': 'bg-red-400', 'tan': 'bg-orange-300',
    'silver': 'bg-gray-400', 'gray': 'bg-gray-400',
};
const textColorMap = {
    'yellow': 'text-yellow-400', 'green': 'text-green-400', 'blue': 'text-blue-400',
    'cyan': 'text-cyan-400', 'purple': 'text-purple-400', 'black': 'text-gray-700',
    'brown': 'text-amber-600', 'red': 'text-red-400', 'tan': 'text-orange-300',
    'silver': 'text-gray-400', 'gray': 'text-gray-400',
};
const getColorClass = (colorKey) => colorMap[colorKey] || 'bg-gray-400';
const getTextColorClass = (colorKey) => textColorMap[colorKey] || 'text-gray-400';

// ============================================================================
// 🔹 ГРАФИК 1: Вычисляемые свойства (используют store-утилиты)
// ============================================================================
const displayCollectionsData = computed(() => {
    const data = collectionGraph.value?.data;
    if (!data) return [];

    if (collectionsViewMode.value === 'flat') {
        const all = dashboardStore.flattenCollections(data);
        const sorted = all.sort((a, b) => b.count - a.count || a.label?.localeCompare(b.label));
        return sorted.map((item, idx) => ({
            id: `flat-${item.category_id}-${item.id}-${idx}`,
            name: '',
            color_key: item.color_key,
            collections: [{
                id: item.id,
                label: item.label,
                count: item.count,
                category_name: item.category_name
            }]
        }));
    }
    return data;
});

const collectionsLegendItems = computed(() => {
    if (collectionsViewMode.value === 'flat') return [];
    return collectionGraph.value?.data || [];
});

const maxCountCollections = computed(() => {
    return dashboardStore.findCollectionsMax(displayCollectionsData.value);
});

const yConfigCollections = computed(() => {
    return dashboardStore.getYAxisConfig(maxCountCollections.value);
});

const getBarHeightCollections = (count) => {
    const availableHeight = 320;
    if (!yConfigCollections.value.max) return 0;
    return Math.max((count / yConfigCollections.value.max) * availableHeight, 4);
};

const showTooltipCollections = (coll, cat, event) => {
    const rect = event.currentTarget.getBoundingClientRect();
    const catName = cat.name || coll.category_name || 'Без категории';
    tooltip.value = {
        visible: true,
        x: rect.left + rect.width / 2,
        y: rect.top - 10,
        collLabel: coll.label,
        count: coll.count,
        catName: catName,
        textColorClass: getTextColorClass(cat.color_key)
    };
    activeTooltip.value = 'collections';
};

// ============================================================================
// 🔹 ГРАФИК 2: Вычисляемые свойства (используют store-утилиты)
// ============================================================================
const ratingLegend = computed(() => {
    return dashboardStore.getRatingLegend(yearsGraph.value?.data);
});

const maxCountYears = computed(() => {
    return dashboardStore.findYearsMax(yearsGraph.value?.data, yearsViewMode.value);
});

const yConfigYears = computed(() => {
    return dashboardStore.getYAxisConfig(maxCountYears.value);
});

const getBarHeightYears = (count) => {
    const availableHeight = 320;
    if (!yConfigYears.value.max) return 0;
    return Math.max((count / yConfigYears.value.max) * availableHeight, 4);
};

const showTooltipYears = (coll, year, event) => {
    const rect = event.currentTarget.getBoundingClientRect();
    tooltip.value = {
        visible: true,
        x: rect.left + rect.width / 2,
        y: rect.top - 10,
        collLabel: coll.label,
        count: coll.count,
        catName: year.name,
        textColorClass: getTextColorClass(coll.color_key || year.color_key)
    };
    activeTooltip.value = 'years';
};

// ============================================================================
// 🔹 ЗАГРУЗКА ДАННЫХ
// ============================================================================
const loadCollectionGraph = async () => {
    collectionGraph.value = await dashboardStore.fetchCollectionGraph();
};

const loadYearsGraph = async () => {
    yearsGraph.value = await dashboardStore.fetchYearsGraph();
};

const hideTooltip = () => {
    tooltip.value.visible = false;
    activeTooltip.value = null;
};

const startProgressSimulation = () => {
    percentage.value = 0;
    let current = 0;
    progressTimer = setInterval(() => {
        if (current < 90) {
            current += Math.random() * 4 + 2;
            percentage.value = Math.floor(current);
        } else {
            clearInterval(progressTimer);
        }
    }, 120);
};

const stopProgressSimulation = () => {
    if (progressTimer) clearInterval(progressTimer);
};

const getDashboardStore = async () => {
    loader.value = true;
    error.value = null;
    stopProgressSimulation();
    startProgressSimulation();

    try {
        await Promise.all([
            dashboardStore.getMoviesCount(),
            loadCollectionGraph(),
            loadYearsGraph()
        ]);
        percentage.value = 100;
        setTimeout(() => { loader.value = false; }, 350);
    } catch (e) {
        error.value = e.message || 'Ошибка';
        loader.value = false;
    } finally {
        stopProgressSimulation();
    }
};

const onYearsViewChange = async () => {
    await nextTick();
};

// ============================================================================
// 🔹 ХУКИ
// ============================================================================
watch(() => watcherLang.value, () => getDashboardStore());
onMounted(() => getDashboardStore());
onUnmounted(() => stopProgressSimulation());
</script>

<style scoped lang="scss">
$chart-height: 320px;

.percentage-value {
    display: block; margin-top: 10px; font-size: 28px;
}
.percentage-label {
    display: block; margin-top: 10px; font-size: 12px;
}
.chart-card {
    :deep(.el-card__header) {
        padding: 16px 20px;
        border-bottom: 2px solid #ebeef5;
    }
}
.chart-wrapper {
    display: flex; gap: 12px; padding: 20px;
}

/* Ось Y */
.y-axis {
    width: 45px; flex-shrink: 0; position: relative;
    height: $chart-height; padding-right: 8px; margin-top: 30px;
}
.y-label {
    position: absolute; right: 0; font-size: 11px; color: #94a3b8;
    text-align: right; font-weight: 500; line-height: 1;
    transform: translateY(-50%); white-space: nowrap;
}

/* Скролл */
.chart-scroll {
    flex: 1; overflow-x: auto; overflow-y: visible;
    scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;
    &::-webkit-scrollbar { height: 8px; }
    &::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    &::-webkit-scrollbar-thumb {
        background: #94a3b8; border-radius: 4px;
        &:hover { background: #64748b; }
    }
}

/* Тело графика */
.chart-body {
    position: relative; height: $chart-height;
    margin-top: 30px; margin-bottom: 40px; min-width: max-content;
}

/* Сетка */
.grid {
    position: absolute; top: 0; left: 0; width: 100%; height: $chart-height;
    pointer-events: none; z-index: 10;
    .grid-line {
        position: absolute; left: 0; right: -1000px;
        border-top: 1px solid rgba(226, 232, 240, 0.4);
        &:first-child { top: 0 !important; }
        &:last-child { top: 100% !important; }
    }
}

/* Контейнер столбцов */
.bars-container {
    position: relative; height: $chart-height;
    display: flex; align-items: flex-end; gap: 24px; padding: 0 20px 0; z-index: 1;
    transition: gap 0.2s ease;
}

.category-block {
    flex-shrink: 0; display: flex; flex-direction: column;
    align-items: center; gap: 8px;
}

.bars-group {
    display: flex; align-items: flex-end; gap: 4px;
    height: 100%; position: relative; z-index: 5;
}

.bar-wrapper {
    position: relative; width: 28px; cursor: pointer;
    display: flex; flex-direction: column; align-items: center;
    height: 100%; justify-content: flex-end;
    &:hover { z-index: 20; }
}

.bar {
    width: 100%; min-height: 4px; border-radius: 4px 4px 0 0;
    transition: all 0.2s; opacity: 0.85;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transform-origin: bottom; animation: growBar 0.6s ease-out forwards;
    &:hover {
        opacity: 1; filter: brightness(1.1);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
}

/* Цифры над столбцами */
.bar-value-top {
    position: absolute; bottom: 100%; margin-bottom: 5px;
    left: 0; right: 0; font-size: 10px; font-weight: bold;
    color: #64748b; text-align: center; white-space: nowrap; z-index: 15;
}

.category-title {
    position: absolute; top: 100%; margin-top: 8px;
    font-size: 11px; font-weight: 600; color: #475569;
    text-align: center; width: 100%; max-width: 120px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* Легенда */
.legend {
    display: flex; flex-wrap: wrap; gap: 16px;
    padding: 16px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;
}
.legend-item {
    display: flex; align-items: center; gap: 6px; font-size: 12px;
}
.legend-dot { width: 12px; height: 12px; border-radius: 2px; flex-shrink: 0; }
.legend-label { color: #475569; font-weight: 500; }
.legend-sum { color: #94a3b8; font-weight: 400; margin-left: 3px; }

/* Тултип */
.custom-tooltip {
    position: fixed; background: #1e293b; color: white;
    padding: 12px 16px; border-radius: 8px; font-size: 13px;
    pointer-events: none; z-index: 9999;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    transform: translate(-50%, -100%); margin-top: -15px; min-width: 180px;

    .tooltip-header {
        margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #334155;
        .tooltip-collection { font-weight: bold; font-size: 14px; }
    }
    .tooltip-body {
        display: flex; align-items: baseline; gap: 8px; margin-bottom: 8px;
        .tooltip-count-large { font-size: 28px; font-weight: bold; color: #fff; }
        .tooltip-label-small { color: #cbd5e1; font-size: 12px; }
    }
    .tooltip-footer {
        font-size: 11px; color: #94a3b8; padding-top: 8px;
        border-top: 1px solid #334155;
        strong { color: #e2e8f0; }
    }
    .tooltip-arrow {
        position: absolute; bottom: -8px; left: 50%;
        transform: translateX(-50%) rotate(45deg);
        width: 16px; height: 16px; background: #1e293b;
    }
}

/* Анимации */
@keyframes growBar {
    from { transform: scaleY(0); opacity: 0; }
    to { transform: scaleY(1); opacity: 0.85; }
}
.chart-card { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
