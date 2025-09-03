<template>
    <h3 class="text-center mt-3 mb-3">{{ $t('list_collections') }}</h3>
    <h5>{{ locale.search_by_resource_collection }}</h5>
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
                v-for="field in locale.collection_list_info_sort_fields"
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
            :page-sizes="[20, 50]"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
        />
    </div>
    <el-table :data="collectionsList" v-loading="loader" :empty-text="$t('data_not_found')" style="width: 100%" ref="multipleTableRef" @selection-change="handleSelectionChange">
        <el-table-column type="index" label="№" />
        <el-table-column prop="id" :label="locale.id_collection" width="120" />
        <el-table-column prop="category_id" :label="locale.id_category" width="120" />
        <el-table-column prop="value" :label="locale.resource" width="160">
            <template v-slot:default="scope">
                <el-input v-model="scope.row.value" placeholder="Resource" />
            </template>
        </el-table-column>
        <el-table-column prop="label_en" :label="locale.collection_name" width="300">
            <template v-slot:default="scope">
                <el-input v-model="scope.row.label_en" placeholder="Collection (EN)" />
            </template>
        </el-table-column>
        <el-table-column prop="label_ru" :label="locale.collection_name_rus" width="300">
            <template v-slot:default="scope">
                <el-input v-model="scope.row.label_ru" placeholder="Collection (RU)" />
            </template>
        </el-table-column>
        <el-table-column prop="cover" label="upload image" width="300">
            <template v-slot:default="scope">
                <el-upload
                    v-model:file-list="collectionImages[scope.row.id].cover"
                    class="upload-demo"
                    action="#"
                    :limit="1"
                    accept="image/jpeg,image/png,image/gif"
                    :auto-upload="false"
                    :on-preview="handlePreview"
                    :on-change="(file, fileList) => handleFileChange(file, fileList, scope.row.id, 'cover')"
                    :on-remove="(file, fileList) => handleRemove(file, fileList, scope.row.id, 'cover')"
                    list-type="picture"
                >
                    <el-icon><Plus /></el-icon>
                    <template #tip>
                        <div class="el-upload__tip">jpg/png/gif</div>
                    </template>
                </el-upload>
            </template>
        </el-table-column>
        <el-table-column prop="id" fixed="right" :label="locale.actions" width="150">
            <template v-slot:default="scope">
                <el-button link type="primary" @click="handleUpdateCollection(scope.row, scope.$index)" :icon="Refresh" title="Update collection" />
                <el-button link type="danger" @click="handleRemoveCollection(scope.row.id, scope.$index)" :icon="Delete" :title="$t('remove_from_collection')" />
            </template>
        </el-table-column>
    </el-table>
    <el-backtop :right="20" :bottom="100" />
    <el-image-viewer
        v-if="previewVisible"
        :url-list="[previewImageUrl]"
        @close="closePreview"
    />
    <p v-if="error">{{ error }}</p>
</template>

<script lang="ts" setup>
import { storeToRefs } from 'pinia';
import { useCollectionsStore } from "../store/collectionsStore";
import { useLanguageStore } from "../store/languageStore";
import { Delete, Refresh, EditPen, Plus } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import type { FormInstance } from 'element-plus';
import { ref, watch, reactive, onMounted } from "vue";

const collectionsStore = useCollectionsStore();
const languageStore = useLanguageStore();
const { collectionsList, totalCount, locale, currentPage, pageSize, valueSort, route, loader, error, collectionImagesData } = storeToRefs(collectionsStore);
const { watcherLang } = storeToRefs(languageStore);

const small = ref(false);
const background = ref(false);
const disabled = ref(false);
const defaultSpin = ref(false);
const formRef = ref<FormInstance>();
const queryValidateForm = reactive({
    query: '',
});
const previewVisible = ref(false);
const previewImageUrl = ref('');
const collectionImages = ref({});

onMounted(() => {
    collectionsStore.getListCollections().then(() => {
        initCollectionImages();
        collectionsStore.getCollectionImages();
    });
});

watch([collectionsList, collectionImagesData], () => {
    initCollectionImages();
}, { deep: true });

const initCollectionImages = async () => {
    if (!collectionsList.value) return; // Проверка на случай, если данные еще не загружены
    collectionsList.value.forEach(async (item) => {
        if (!collectionImages.value[item.id]) {
            collectionImages.value[item.id] = {
                cover: [], // Инициализация пустым массивом
            };
        }
        if (collectionImagesData.value[item.id]?.cover) {
            const url = collectionImagesData.value[item.id].cover;
            const filename = url.split('/').pop();
            const file = await urlToFile(url, filename);
            collectionImages.value[item.id].cover = file ? [{ name: filename, url, raw: file }] : [{ name: filename, url }];
        }
    });
};

const urlToFile = async (url, filename) => {
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`Failed to fetch ${url}`);
        const blob = await response.blob();
        return new File([blob], filename, { type: blob.type });
    } catch (error) {
        console.error(`Error converting URL to File: ${url}`, error);
        return null;
    }
};

const handlePreview = (file) => {
    if (file.url || file.raw) {
        previewImageUrl.value = file.url || URL.createObjectURL(file.raw);
        previewVisible.value = true;
    }
};

const closePreview = () => {
    previewVisible.value = false;
    previewImageUrl.value = '';
};

const handleFileChange = (file, fileList, collectionId, field) => {
    collectionImages.value[collectionId][field] = fileList;
};

const handleRemove = (file, fileList, collectionId, field) => {
    collectionImages.value[collectionId][field] = fileList;
    if (file.url) {
        collectionsStore.removeImage(file, collectionId);
    }
};

const handleUpdateCollection = async (row, index) => {
    try {
        // Валидация полей
        if (!row.value || !row.label_en || !row.label_ru) {
            ElMessage.error('All fields (Resource, Collection EN, Collection RU) are required');
            return;
        }

        // Обновление данных коллекции
        await collectionsStore.updateCollection({
            id: row.id,
            category_id: row.category_id,
            value: row.value,
            label_en: row.label_en,
            label_ru: row.label_ru,
        });

        // Обновление изображения, если оно есть
        const formData = new FormData();
        const images = collectionImages.value[row.id];
        if (images.cover[0]?.raw || images.cover[0]?.url) {
            const file = images.cover[0]?.raw || images.cover[0];
            formData.append(`collections[${row.id}][cover]`, file);
            await collectionsStore.updateCollectionImages(formData);
            await collectionsStore.getCollectionImages();
        }

        await collectionsStore.getListCollections();
        ElMessage.success(`Collection ${row.id} updated successfully`);
    } catch (error) {
        console.error('Update error:', error);
        ElMessage.error('Error updating collection or image');
    }
};

const handleRemoveCollection = (id, index) => {
    ElMessageBox.confirm(`Are you sure? All the movies will be unbundled from ID: ${id}. And collection will be deleted. Continue?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        collectionsStore.removeItemFromCollection(id, index);
        ElMessage({
            type: 'success',
            message: 'Delete completed',
        });
    }).catch(() => {
        ElMessage({
            type: 'info',
            message: 'Delete canceled',
        });
    });
};

const handleSizeChange = (val) => {
    collectionsStore.updatePageSize(val);
    collectionsStore.getListCollections();
};

const handleCurrentChange = (val) => {
    collectionsStore.updateCurrentPage(val);
    collectionsStore.getListCollections();
};

const handleSelectChange = (val) => {
    collectionsStore.updateSort(val);
    collectionsStore.getListCollections();
};

const handleSwitchChange = (val) => {
    let spin = val ? "asc" : 'desc';
    collectionsStore.updateSpin(spin);
    collectionsStore.getListCollections();
};

const submitSearch = (formEl: FormInstance | undefined) => {
    if (!formEl) return;
    formEl.validate((valid) => {
        if (valid) {
            collectionsStore.updateSearchQuery(queryValidateForm.query);
            collectionsStore.getListCollections();
        } else {
            console.log('error submit!');
            return false;
        }
    });
};

const resetSearch = (formEl: FormInstance | undefined) => {
    if (!formEl) return;
    formEl.resetFields();
    collectionsStore.updateSearchQuery('');
    collectionsStore.getListCollections();
};
</script>

<style lang="scss" scoped>
::v-deep(.el-menu--popup) {
    height: auto;
    max-height: 400px;
    overflow-y: auto;
}
::v-deep(.el-upload-list) {
    display: flex;
    flex-wrap: wrap;
}
::v-deep(.el-upload-list__item) {
    max-width: 150px;
    margin-right: 15px;
}
.flex-grow {
    flex-grow: 1;
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
