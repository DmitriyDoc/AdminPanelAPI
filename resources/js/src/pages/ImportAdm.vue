<template>
    <el-row :gutter="20">
        <el-col :span="24">
            <h2 class="mb-4">{{ $t('import') || 'Импорт данных' }}</h2>

            <el-tabs v-model="activeTab" class="demo-tabs" @tab-click="handleTabClick">
                <!-- Вкладка 1: Точечный импорт -->
                <el-tab-pane :label="$t('single_import') || 'Точечный импорт'" name="single">
                    <el-card shadow="never" class="mt-3">
                        <el-form label-width="120px">
                            <el-form-item :label="$t('entity_type') || 'Тип сущности'">
                                <el-radio-group v-model="importStore.singleImportType">
                                    <el-radio value="movie">Фильм</el-radio>
                                    <el-radio value="tv">Сериал</el-radio>
                                    <el-radio value="person">Актер/Персона</el-radio>
                                </el-radio-group>
                            </el-form-item>

                            <el-form-item :label="$t('imdb_or_tmdb_id') || 'IMDb / TMDB ID'">
                                <el-input
                                    v-model="importStore.singleImportId"
                                    :placeholder="importStore.singleImportType === 'person' ? 'Например: nm0000115 или 2963' : 'Например: tt0137523 или 550'"
                                    style="max-width: 400px;"
                                    @keyup.enter="importStore.submitSingleImport"
                                >
                                    <template #append>
                                        <el-button
                                            :loading="importStore.isLoadingSingle"
                                            @click="importStore.submitSingleImport"
                                        >
                                            {{ $t('import') || 'Импортировать' }}
                                        </el-button>
                                    </template>
                                </el-input>
                                <div class="el-form-item__tip" style="color: #909399; font-size: 12px; margin-top: 5px;">
                                    Система автоматически определит тип ID и проверит наличие записи в базе перед импортом.
                                </div>
                            </el-form-item>
                        </el-form>
                    </el-card>
                </el-tab-pane>

                <!-- Вкладка 2: Массовый импорт -->
                <el-tab-pane :label="$t('bulk_import') || 'Массовый импорт из списков'" name="bulk">
                    <el-card shadow="never" class="mt-3">
                        <el-form inline>
                            <el-form-item label="Тип">
                                <el-select v-model="importStore.bulkEntityType" style="width: 150px" @change="handleTypeChange">
                                    <el-option label="Фильмы" value="movie" />
                                    <el-option label="Сериалы" value="tv" />
                                    <el-option label="Актеры" value="person" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="Категория">
                                <el-select v-model="importStore.bulkCategory" style="width: 200px">
                                    <el-option
                                        v-for="cat in currentCategories"
                                        :key="cat.value"
                                        :label="cat.label"
                                        :value="cat.value"
                                    />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" :loading="importStore.isLoadingList" @click="importStore.fetchListItems">
                                    Загрузить список
                                </el-button>
                            </el-form-item>
                        </el-form>

                        <el-table
                            v-if="importStore.listItems.length > 0"
                            :data="importStore.listItems"
                            style="width: 100%; margin-top: 15px"
                            @selection-change="handleSelectionChange"
                            :default-sort="{ prop: 'release_date', order: 'descending' }"
                        >
                            <el-table-column type="selection" width="55" :selectable="checkSelectable" />

                            <el-table-column label="Постер" width="80" align="center">
                                <template #default="scope">
                                    <el-image
                                        v-if="scope.row.poster_path"
                                        :src="scope.row.poster_path"
                                        fit="cover"
                                        style="width: 50px; height: 75px; border-radius: 4px;"
                                    />
                                    <span v-else class="text-gray-400">Нет</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="title" label="Название" min-width="250" show-overflow-tooltip />
                            <el-table-column label="Инфо" width="120" align="center" sortable :sort-method="sortInfo">
                                <template #default="scope">
                                    <template v-if="scope.row.release_date">
                                        {{ scope.row.release_date.substring(0, 4) }}
                                    </template>
                                    <template v-else-if="scope.row.popularity">
                                        <el-tag type="warning" size="small" effect="plain">
                                            <el-icon><Star /></el-icon> {{ Math.round(scope.row.popularity) }}
                                        </el-tag>
                                    </template>
                                    <span v-else class="text-gray-400">-</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="imdb_id" label="IMDb ID" width="120" align="center" sortable />
                            <el-table-column label="В базе" width="100" align="center" sortable :sort-method="sortByDbStatus">
                                <template #default="scope">
                                    <el-tag v-if="scope.row.exists_in_db" type="info" effect="dark">Да</el-tag>
                                    <el-tag v-else type="info" effect="plain">Нет</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="Публикация" width="150" align="center" sortable :sort-method="sortByPublishStatus">
                                <template #default="scope">
                                    <template v-if="scope.row.exists_in_db">
                                        <el-tag v-if="scope.row.published_status === 2" type="success" effect="dark">
                                            Опубликовано
                                        </el-tag>
                                        <el-tag v-else-if="scope.row.published_status === 1" type="warning">
                                            В обработке
                                        </el-tag>
                                        <el-tag v-else-if="scope.row.published_status === 0" type="info">
                                            Не опубликовано
                                        </el-tag>
                                        <el-tag v-else type="info" effect="plain">
                                            Неизвестно
                                        </el-tag>
                                    </template>
                                    <span v-else class="text-gray-400">-</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="importStore.listItems.length > 0" class="mt-4 flex justify-between items-center">
                            <el-button type="success" :disabled="importStore.selectedItems.length === 0 || importStore.isLoadingList" @click="importStore.submitBulkImport">
                                Импортировать выбранные ({{ importStore.selectedItems.length }})
                            </el-button>

                            <div class="pagination-info">
                                <el-text type="info" size="small" class="mr-3">
                                    Найдено: <strong>{{ importStore.totalResults.toLocaleString() }}</strong> записей
                                </el-text>

                                <el-pagination
                                    v-model:current-page="importStore.bulkPage"
                                    :page-size="20"
                                    :total="importStore.totalResults"
                                    :page-count="importStore.totalPages"
                                    layout="prev, pager, next, jumper"
                                    @current-change="importStore.changePage"
                                />
                            </div>
                        </div>
                        <el-empty v-else-if="!importStore.isLoadingList" description="Нажмите 'Загрузить список', чтобы увидеть элементы" class="mt-4" />
                    </el-card>
                </el-tab-pane>
            </el-tabs>
        </el-col>
    </el-row>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useImportStore } from "../store/importStore";
import { Star } from '@element-plus/icons-vue';

const importStore = useImportStore();
const activeTab = ref('single');

const currentCategories = computed(() => {
    return importStore.availableLists[importStore.bulkEntityType] || [];
});

const handleTabClick = (tab) => {
    if (tab.paneName === 'bulk' && importStore.availableLists.movie.length === 0) {
        importStore.fetchLists();
    }
};

const handleTypeChange = () => {
    // Сброс категории при смене типа, если такая категория есть
    const cats = importStore.availableLists[importStore.bulkEntityType];
    if (cats && cats.length > 0) {
        importStore.bulkCategory = cats[0].value;
    }
};

const checkSelectable = (row) => {
    return !row.exists_in_db; // Нельзя выбрать то, что уже есть в базе
};

const handleSelectionChange = (val) => {
    importStore.selectedItems = val;
};
// Сортировка по колонке "Инфо" (год или популярность)
const sortInfo = (a, b) => {
    const valA = a.release_date ? parseInt(a.release_date.substring(0, 4)) : (a.popularity || 0);
    const valB = b.release_date ? parseInt(b.release_date.substring(0, 4)) : (b.popularity || 0);
    return valA - valB;
};

// Сортировка по колонке "В базе" (Да/Нет)
const sortByDbStatus = (a, b) => {
    const valA = a.exists_in_db ? 1 : 0;
    const valB = b.exists_in_db ? 1 : 0;
    return valA - valB;
};

// Сортировка по колонке "Публикация" (2 > 1 > 0 > null)
const sortByPublishStatus = (a, b) => {
    const valA = a.published_status ?? -1;
    const valB = b.published_status ?? -1;
    return valA - valB;
};
onMounted(() => {
    importStore.fetchLists();
});
</script>

<style scoped>
.mb-4 { margin-bottom: 1.5rem; }
.mt-3 { margin-top: 1rem; }
.mt-4 { margin-top: 1.5rem; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.text-gray-400 { color: #9ca3af; font-size: 12px; }
.pagination-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.mr-3 {
    margin-right: 0.75rem;
}
</style>
