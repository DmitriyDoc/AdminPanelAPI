<template>
    <div class="partition-management">
        <h3 class="text-center mt-3 mb-3">{{ $t('partition_management') }}</h3>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- Категории -->
            <el-tab-pane :label="$t('categories')" name="categories">
                <div class="tab-content">
                    <el-button type="primary" @click="openCreateDialog('category')" class="mb-3">
                        {{ $t('create_category') }}
                    </el-button>

                    <el-table :data="categories" border stripe>
                        <el-table-column prop="id" :label="$t('id')" width="80" />
                        <el-table-column prop="index_id" :label="$t('index_id')" />
                        <el-table-column prop="value" :label="$t('value')" />
                        <el-table-column :label="$t('title')"><template #default="{ row }">{{ row.label }}</template></el-table-column>
                        <el-table-column :label="$t('actions')" width="200">
                            <template #default="{ row }">
                                <el-button size="small" @click="editCategory(row)">{{ $t('edit') }}</el-button>
                                <el-button size="small" type="danger" @click="confirmDeleteCategory(row)">{{ $t('delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </el-tab-pane>

            <!-- Коллекции -->
            <el-tab-pane :label="$t('collections')" name="collections">
                <div class="tab-content">
                    <div class="mb-3 flex gap-4">
                        <el-button type="primary" @click="openCreateDialog('collection')">
                            {{ $t('create_collection') }}
                        </el-button>
                        <el-input
                            v-model="collectionSearchQuery"
                            :placeholder="$t('search_collections')"
                            clearable
                            class="flex-1"
                        >
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                    </div>

                    <el-table :data="paginatedCollections" border stripe>
                        <el-table-column prop="id" :label="$t('id')" width="80" />
                        <el-table-column prop="value" :label="$t('value')" />
                        <el-table-column :label="$t('label')"><template #default="{ row }">{{ row.label }}</template></el-table-column>
                        <el-table-column :label="$t('category')" width="200">
                            <template #default="{ row }">{{ getCategoryTitle(row.category_id) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('cover')" width="200">
                            <template #default="{ row }">
                                <div class="cover-upload-wrapper" :class="{ 'image-saved': hasSavedImage(row.id) && !hasNewRawFile(row.id) }">
                                    <el-upload
                                        :file-list="collectionImagesMap[row.id]?.cover || []"
                                        class="cover-upload"
                                        action="#"
                                        :limit="1"
                                        accept="image/jpeg,image/png,image/gif"
                                        :auto-upload="false"
                                        :on-preview="handleImagePreview"
                                        :on-change="(file, fileList) => handleImageFileChange(file, fileList, row.id)"
                                        :on-remove="(file, fileList) => handleImageRemove(file, fileList, row.id)"
                                        @update:file-list="(fileList) => handleImageFileUpdate(fileList, row.id)"
                                        list-type="picture"
                                    >
                                        <div class="upload-trigger">
                                            <el-icon size="20"><Plus /></el-icon>
                                        </div>
                                    </el-upload>
                                </div>
                                <el-button
                                    v-if="hasNewRawFile(row.id)"
                                    size="small"
                                    type="success"
                                    @click="handleImageUpload(row.id)"
                                    class="mt-1"
                                >
                                    Save image
                                </el-button>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('actions')" width="160">
                            <template #default="{ row }">
                                <div class="actions-column">
                                    <el-button size="small" @click="editCollection(row)">{{ $t('edit') }}</el-button>
                                    <el-button size="small" type="warning" @click="openMoveCollectionDialog(row)">{{ $t('move_to_section') }}</el-button>
                                    <el-button size="small" type="info" @click="openAssignFranchiseDialog(row)">{{ $t('assign_franchise') }}</el-button>
                                    <el-button size="small" type="danger" @click="confirmDeleteCollection(row)">{{ $t('delete') }}</el-button>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-pagination
                        v-model:current-page="collectionsPage"
                        v-model:page-size="collectionsPageSize"
                        :page-sizes="[50, 100, 200, 500]"
                        :total="filteredCollections.length"
                        layout="total, sizes, prev, pager, next, jumper"
                        class="mt-4"
                    />
                </div>
            </el-tab-pane>

            <!-- Франшизы -->
            <el-tab-pane :label="$t('franchises')" name="franchises">
                <div class="tab-content">
                    <div class="mb-3 flex gap-4">
                        <el-button type="primary" @click="openCreateDialog('franchise')">
                            {{ $t('create_franchise') }}
                        </el-button>
                        <el-input
                            v-model="franchiseSearchQuery"
                            :placeholder="$t('search_franchises')"
                            clearable
                            class="flex-1"
                        >
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                    </div>

                    <el-table :data="paginatedFranchises" border stripe>
                        <el-table-column prop="id" :label="$t('id')" width="80" />
                        <el-table-column prop="value" :label="$t('value')" />
                        <el-table-column :label="$t('label')"><template #default="{ row }">{{ row.label }}</template></el-table-column>
                        <el-table-column :label="$t('collections_count')" width="150">
                            <template #default="{ row }">{{ getFranchiseCollectionsCount(row.id) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('actions')" width="350">
                            <template #default="{ row }">
                                <el-button size="small" @click="editFranchise(row)">{{ $t('edit') }}</el-button>
                                <el-button size="small" type="info" @click="openManageFranchiseCollectionsDialog(row)">{{ $t('manage_collections') }}</el-button>
                                <el-button size="small" type="danger" @click="confirmDeleteFranchise(row)">{{ $t('delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-pagination
                        v-model:current-page="franchisesPage"
                        v-model:page-size="franchisesPageSize"
                        :page-sizes="[50, 100, 200, 500]"
                        :total="filteredFranchises.length"
                        layout="total, sizes, prev, pager, next, jumper"
                        class="mt-4"
                    />
                </div>
            </el-tab-pane>

            <!-- Дерево таксономий -->
            <el-tab-pane :label="$t('taxonomy_tree')" name="taxonomy">
                <div class="tab-content">
                    <div class="mb-3">
                        <el-alert
                            :title="$t('drag_to_reassign')"
                            type="info"
                            show-icon
                            class="mb-3"
                        />
                    </div>

                    <div class="tree-wrapper">
                        <el-tree
                            ref="treeRef"
                            :data="taxonomyTree"
                            node-key="id"
                            :props="treeProps"
                            draggable
                            :allow-drop="allowDrop"
                            :allow-drag="allowDrag"
                            @node-drop="handleDrop"
                            default-expand-all
                            :expand-on-click-node="false"
                        >
                            <template #default="{ node, data }">
                <span class="custom-tree-node">
                  <el-tag :type="data.nodeType === 'category' ? 'primary' : 'success'" size="small">
                    {{ data.nodeType === 'category' ? $t('section') : $t('collection') }}
                  </el-tag>
                  <span class="ml-2">{{ node.label }}</span>
                  <el-tag v-if="data.franchise" type="warning" size="small" class="ml-2">
                    {{ data.franchise }}
                  </el-tag>
                </span>
                            </template>
                        </el-tree>
                    </div>
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- Dialog для создания/редактирования -->
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px">
            <el-form :model="formData" :rules="formRules" ref="formRef" label-width="120px">

                <!-- ============ CATEGORY FIELDS ============ -->
                <template v-if="dialogType === 'category'">
                    <el-form-item :label="$t('value')" prop="value">
                        <el-input v-model="formData.value" />
                    </el-form-item>
                    <el-form-item :label="$t('title_en')" prop="title_en">
                        <el-input v-model="formData.title_en" />
                    </el-form-item>
                    <el-form-item :label="$t('title_ru')" prop="title_ru">
                        <el-input v-model="formData.title_ru" />
                    </el-form-item>
                    <el-form-item :label="$t('index_id')" prop="index_id">
                        <el-input v-model="formData.index_id" />
                    </el-form-item>
                </template>

                <!-- ============ COLLECTION FIELDS ============ -->
                <template v-if="dialogType === 'collection'">
                    <el-form-item :label="$t('value')" prop="value">
                        <el-input v-model="formData.value" />
                    </el-form-item>
                    <el-form-item :label="$t('label_en')" prop="label_en">
                        <el-input v-model="formData.label_en" />
                    </el-form-item>
                    <el-form-item :label="$t('label_ru')" prop="label_ru">
                        <el-input v-model="formData.label_ru" />
                    </el-form-item>
                    <el-form-item :label="$t('category')" prop="category_id">
                        <el-select v-model="formData.category_id" :placeholder="$t('select_category')" style="width: 100%">
                            <el-option v-for="cat in categories" :key="cat.id" :label="cat.label" :value="cat.id" />
                        </el-select>
                    </el-form-item>
                </template>

                <!-- ============ FRANCHISE FIELDS ============ -->
                <template v-if="dialogType === 'franchise'">
                    <el-form-item :label="$t('label_en')" prop="label_en">
                        <el-input v-model="formData.label_en" />
                    </el-form-item>
                    <el-form-item :label="$t('label_ru')" prop="label_ru">
                        <el-input v-model="formData.label_ru" />
                    </el-form-item>

                    <!-- Выбор секции и коллекции только при СОЗДАНИИ (не редактировании) -->
                    <template v-if="!formData.id">
                        <el-form-item :label="$t('section')">
                            <el-select
                                v-model="franchiseFormCategoryId"
                                :placeholder="$t('select_section')"
                                clearable
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :label="cat.label"
                                    :value="cat.id"
                                />
                            </el-select>
                        </el-form-item>

                        <el-form-item :label="$t('collection')">
                            <el-select
                                v-model="franchiseFormCollectionId"
                                :placeholder="$t('select_collection')"
                                filterable
                                clearable
                                :disabled="!franchiseFormCategoryId"
                                style="width: 100%"
                            >
                                <el-option
                                    v-for="col in collectionsByCategory"
                                    :key="col.id"
                                    :label="`${col.label} (${col.value})`"
                                    :value="col.id"
                                />
                            </el-select>
                        </el-form-item>
                    </template>
                </template>

            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" @click="submitForm">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>

        <!-- Dialog для перемещения коллекции -->
        <el-dialog v-model="moveCollectionDialogVisible" :title="$t('move_collection_to_section')" width="500px">
            <el-form label-width="150px">
                <el-form-item :label="$t('collection')">
                    <el-input :value="assignFranchiseData.collection?.label" disabled />
                </el-form-item>
                <el-form-item :label="$t('current_section')">
                    <el-input :value="getCategoryTitle(moveCollectionData.collection?.category_id)" disabled />
                </el-form-item>
                <el-form-item :label="$t('new_section')">
                    <el-tree-select
                        v-model="moveCollectionData.newCategoryId"
                        :data="categoriesTreeSelect"
                        :render-after-expand="false"
                        check-strictly
                        :placeholder="$t('select_section')"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('inherit_franchise')">
                    <el-switch v-model="moveCollectionData.inheritFranchise" />
                    <span class="ml-2 text-gray-500 text-xs">{{ $t('inherit_franchise_hint') }}</span>
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="moveCollectionDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" @click="confirmMoveCollection">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>

        <!-- Dialog для назначения франшизы коллекции -->
        <el-dialog v-model="assignFranchiseDialogVisible" :title="$t('assign_franchise')" width="600px">
            <!-- Изменено: label-width с 150px на 180px -->
            <el-form label-width="180px">
                <el-form-item :label="$t('collection')">
                    <el-input :value="assignFranchiseData.collection?.label_en" disabled />
                </el-form-item>
                <el-form-item :label="$t('current_franchise')">
                    <el-input :value="getFranchiseForCollection(assignFranchiseData.collection?.id)" disabled />
                </el-form-item>
                <el-form-item :label="$t('new_franchise')">
                    <el-select
                        v-model="assignFranchiseData.newFranchiseId"
                        filterable
                        clearable
                        :placeholder="$t('select_franchise')"
                        style="width: 100%"
                    >
                        <el-option v-for="f in franchises" :key="f.id" :label="f.label" :value="f.id" />
                    </el-select>
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="assignFranchiseDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" @click="confirmAssignFranchise">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>

        <!-- Dialog для управления коллекциями франшизы (Transfer) -->
        <el-dialog v-model="manageFranchiseDialogVisible" :title="$t('manage_collections_for_franchise')" width="900px">
            <div class="mb-3">
                <el-tag type="primary" size="large">{{ manageFranchiseData.franchise?.label }}</el-tag>
            </div>

            <el-transfer
                v-model="manageFranchiseData.assignedCollectionIds"
                :data="transferCollectionsData"
                :titles="[$t('available'), $t('assigned')]"
                filterable
                :filter-method="transferFilterMethod"
                :filter-placeholder="$t('search')"
            />

            <template #footer>
                <el-button @click="manageFranchiseDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" @click="confirmManageFranchiseCollections">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>
    </div>
    <el-image-viewer
        v-if="previewVisible"
        :url-list="[previewImageUrl]"
        @close="closeImagePreview"
    />
</template>

<script setup>
import { storeToRefs } from 'pinia';
import { ref, watch, onMounted, onBeforeUnmount, computed } from "vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { Search, Plus } from '@element-plus/icons-vue';
import { useCategoriesStore } from "../store/categoriesStore";
import { useLanguageStore } from "../store/languageStore";
import * as yup from 'yup';

const categoryStore = useCategoriesStore();
const languageStore = useLanguageStore();
const { optionsCats, franchiseLocale, collectionImages } = storeToRefs(categoryStore);
const { watcherLang } = storeToRefs(languageStore);

const activeTab = ref('categories');
const categories = ref([]);
const collections = ref([]);
const franchises = ref([]);
const collectionFranchises = ref({}); // { collectionId: { id, franchise_id, label_en } }

// Search
const collectionSearchQuery = ref('');
const franchiseSearchQuery = ref('');
let searchDebounce = null;
const debouncedCollectionSearch = ref('');
const debouncedFranchiseSearch = ref('');

// Pagination
const collectionsPage = ref(1);
const collectionsPageSize = ref(50);
const franchisesPage = ref(1);
const franchisesPageSize = ref(50);

// Dialogs
const dialogVisible = ref(false);
const dialogType = ref('');
const dialogTitle = ref('');
const formData = ref({});
const formRef = ref(null);

// Collection images
const collectionImagesMap = ref({});
const previewVisible = ref(false);
const previewImageUrl = ref('');

// Move collection dialog
const moveCollectionDialogVisible = ref(false);
const moveCollectionData = ref({ collection: null, newCategoryId: null, inheritFranchise: true });

// Assign franchise dialog
const assignFranchiseDialogVisible = ref(false);
const assignFranchiseData = ref({ collection: null, newFranchiseId: null });

// Manage franchise collections dialog
const manageFranchiseDialogVisible = ref(false);
const manageFranchiseData = ref({ franchise: null, assignedCollectionIds: [] });

// Tree
const treeRef = ref(null);
const treeProps = {
    children: 'children',
    label: 'label',
};
const collectionsCountMap = ref({});
const franchiseCollections = ref({});
const franchiseFormCategoryId = ref(null);
const franchiseFormCollectionId = ref(null);

const collectionsByCategory = computed(() => {
    if (!franchiseFormCategoryId.value) return [];
    return collections.value.filter(c => c.category_id === franchiseFormCategoryId.value);
});
// Live search с debounce
watch(collectionSearchQuery, (val) => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        debouncedCollectionSearch.value = val && val.length >= 2 ? val : '';
        collectionsPage.value = 1;
    }, 300);
});

watch(franchiseSearchQuery, (val) => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        debouncedFranchiseSearch.value = val && val.length >= 2 ? val : '';
        franchisesPage.value = 1;
    }, 300);
});

onBeforeUnmount(() => clearTimeout(searchDebounce));

// Filtered lists
const filteredCollections = computed(() => {
    if (!debouncedCollectionSearch.value) return collections.value;
    const q = debouncedCollectionSearch.value.toLowerCase();
    return collections.value.filter(c =>
        (c.value || '').toLowerCase().includes(q) ||
        (c.label || '').toLowerCase().includes(q) ||
        String(c.id).includes(q) ||
        (getCategoryTitle(c.category_id) || '').toLowerCase().includes(q)
    );
});

const filteredFranchises = computed(() => {
    if (!debouncedFranchiseSearch.value) return franchises.value;
    const q = debouncedFranchiseSearch.value.toLowerCase();
    return franchises.value.filter(f =>
        (f.value || '').toLowerCase().includes(q) ||
        (f.label || '').toLowerCase().includes(q) ||
        String(f.id).includes(q)
    );
});

const paginatedCollections = computed(() => {
    const start = (collectionsPage.value - 1) * collectionsPageSize.value;
    return filteredCollections.value.slice(start, start + collectionsPageSize.value);
});

const paginatedFranchises = computed(() => {
    const start = (franchisesPage.value - 1) * franchisesPageSize.value;
    return filteredFranchises.value.slice(start, start + franchisesPageSize.value);
});

// Tree data
const taxonomyTree = computed(() => {
    return categories.value.map(cat => {
        const catCollections = collections.value.filter(c => c.category_id === cat.id);
        return {
            id: `cat-${cat.id}`,
            label: `${cat.label} (${cat.value})`,
            nodeType: 'category',
            categoryId: cat.id,
            children: catCollections.map(col => ({
                id: `col-${col.id}`,
                label: `${col.label} (${col.value})`,
                nodeType: 'collection',
                collectionId: col.id,
                category_id: col.category_id,
                franchise: getFranchiseForCollection(col.id),
            })),
        };
    });
});

const categoriesTreeSelect = computed(() => {
    return categories.value.map(cat => ({
        value: cat.id,
        label: `${cat.label} (${cat.value})`,
    }));
});

// Transfer data for franchise management
const transferCollectionsData = computed(() => {
    return collections.value.map(c => ({
        key: c.id,
        label: `${c.label} (${c.value})`,
        disabled: false,
    }));
});

const transferFilterMethod = (query, item) => {
    if (!query) return true;
    const q = query.toLowerCase();
    return item.label.toLowerCase().includes(q);
};

const formRules = computed(() => {
    const rules = {};
    if (dialogType.value === 'category') {
        rules.value = yup.string().required('Required');
        rules.title_en = yup.string().required('Required');
        rules.title_ru = yup.string().required('Required');
    } else if (dialogType.value === 'collection') {
        rules.value = yup.string().required('Required');
        rules.label_en = yup.string().required('Required');
        rules.label_ru = yup.string().required('Required');
        rules.category_id = yup.number().required('Required');
    } else if (dialogType.value === 'franchise') {
        rules.label_en = yup.string().required('Required');
        rules.label_ru = yup.string().required('Required');
    }
    return rules;
});
onMounted(async () => {
    await loadData();
    categoryStore.getLocale();
});

watch(() => watcherLang.value, () => {
    categoryStore.getLocale();
});

const loadData = async () => {
    try {
        const response = await categoryStore.getCategoriesList();
        categories.value = response.categories;
        collections.value = response.collections;
        franchises.value = response.franchises;
        collectionFranchises.value = response.collectionFranchises || {};
        franchiseCollections.value = response.franchiseCollections || {};
        collectionsCountMap.value = response.collectionsCountMap || {};

        await categoryStore.getCollectionImages();
        await initCollectionImages();
    } catch (error) {
        ElMessage.error('Failed to load data');
    }
};

const getCategoryTitle = (categoryId) => {
    const cat = categories.value.find(c => c.id === categoryId);
    return cat ? cat.label : '';
};

const getFranchiseForCollection = (collectionId) => {
    const cf = collectionFranchises.value[collectionId];
    if (!cf || !cf.franchise_id) return '';
    const f = franchises.value.find(fr => fr.id === cf.franchise_id);
    return f ? f.label : '';
};

const getFranchiseCollectionsCount = (franchiseId) => {
    return collectionsCountMap.value[franchiseId] || 0;
};

// Create/Edit
const openCreateDialog = (type) => {
    dialogType.value = type;
    dialogTitle.value = type === 'category' ? 'Create Category' :
        type === 'collection' ? 'Create Collection' : 'Create Franchise';
    formData.value = {};
    franchiseFormCategoryId.value = null;
    franchiseFormCollectionId.value = null;
    dialogVisible.value = true;
};

const editCategory = (row) => {
    dialogType.value = 'category';
    dialogTitle.value = 'Edit Category';
    formData.value = { ...row };
    dialogVisible.value = true;
};
const editCollection = (row) => {
    dialogType.value = 'collection';
    dialogTitle.value = 'Edit Collection';
    formData.value = { ...row };
    dialogVisible.value = true;
};
const editFranchise = (row) => {
    dialogType.value = 'franchise';
    dialogTitle.value = 'Edit Franchise';
    formData.value = {
        id: row.id,
        value: row.value,
        label_en: row.label_en,
        label_ru: row.label_ru,
    };
    dialogVisible.value = true;
};

const submitForm = async () => {
    try {
        await formRef.value.validate();
        const t = dialogType.value;

        let dataToSend = { ...formData.value };

        if (t === 'franchise' && !formData.value.id && franchiseFormCollectionId.value) {
            dataToSend.collectionId = franchiseFormCollectionId.value;
        }

        if (formData.value.id) {
            await categoryStore[`update${t.charAt(0).toUpperCase() + t.slice(1)}`](formData.value.id, dataToSend);
        } else {
            await categoryStore[`create${t.charAt(0).toUpperCase() + t.slice(1)}`](dataToSend);
        }
        ElMessage.success('Saved');
        dialogVisible.value = false;
        await loadData();
    } catch (error) {
        if (error.name !== 'ValidationError') ElMessage.error('Failed to save');
    }
};

// Delete with confirmation
const confirmDeleteCategory = async (row) => {
    try {
        await ElMessageBox.confirm(
            `Delete category "${row.label}"?`, 'Warning',
            { confirmButtonText: 'OK', cancelButtonText: 'Cancel', type: 'warning' }
        );
        await categoryStore.deleteCategory(row.id);
        ElMessage.success('Deleted');
        await loadData();
    } catch (e) { if (e !== 'cancel') ElMessage.error('Delete failed'); }
};

const confirmDeleteCollection = async (row) => {
    try {
        await ElMessageBox.confirm(
            `Delete collection "${row.label}"?`, 'Warning',
            { confirmButtonText: 'OK', cancelButtonText: 'Cancel', type: 'warning' }
        );
        await categoryStore.deleteCollection(row.id);
        ElMessage.success('Deleted');
        await loadData();
    } catch (e) { if (e !== 'cancel') ElMessage.error('Delete failed'); }
};

const confirmDeleteFranchise = async (row) => {
    try {
        await ElMessageBox.confirm(
            `Delete franchise "${row.label}"?`, 'Warning',
            { confirmButtonText: 'OK', cancelButtonText: 'Cancel', type: 'warning' }
        );
        await categoryStore.deleteFranchise(row.id);
        ElMessage.success('Deleted');
        await loadData();
    } catch (e) { if (e !== 'cancel') ElMessage.error('Delete failed'); }
};

// Move collection to another section
const openMoveCollectionDialog = (row) => {
    moveCollectionData.value = {
        collection: row,
        newCategoryId: row.category_id,
        inheritFranchise: true,
    };
    moveCollectionDialogVisible.value = true;
};

const confirmMoveCollection = async () => {
    if (!moveCollectionData.value.newCategoryId) {
        ElMessage.warning('Select a section');
        return;
    }
    try {
        await ElMessageBox.confirm(
            `Move collection "${moveCollectionData.value.collection.label}" to new section?`,
            'Confirm',
            { confirmButtonText: 'OK', cancelButtonText: 'Cancel', type: 'warning' }
        );
        await categoryStore.moveCollection({
            collectionId: moveCollectionData.value.collection.id,
            newCategoryId: moveCollectionData.value.newCategoryId,
            inheritFranchise: moveCollectionData.value.inheritFranchise,
        });
        ElMessage.success('Moved');
        moveCollectionDialogVisible.value = false;
        await loadData();
    } catch (e) { if (e !== 'cancel') ElMessage.error('Move failed'); }
};

// Assign franchise to collection
const openAssignFranchiseDialog = (row) => {
    const cf = collectionFranchises.value[row.id];
    assignFranchiseData.value = {
        collection: row,
        newFranchiseId: cf?.franchise_id || null,
    };
    assignFranchiseDialogVisible.value = true;
};

const confirmAssignFranchise = async () => {
    try {
        await ElMessageBox.confirm(
            `Change franchise for "${assignFranchiseData.value.collection.label}"?`,
            'Confirm',
            { confirmButtonText: 'OK', cancelButtonText: 'Cancel', type: 'warning' }
        );
        await categoryStore.assignFranchiseToCollection({
            collectionId: assignFranchiseData.value.collection.id,
            franchiseId: assignFranchiseData.value.newFranchiseId,
        });
        ElMessage.success('Franchise assigned');
        assignFranchiseDialogVisible.value = false;
        await loadData();
    } catch (e) { if (e !== 'cancel') ElMessage.error('Failed'); }
};

// Manage franchise collections via Transfer
const openManageFranchiseCollectionsDialog = (row) => {
    const franchiseId = Number(row.id);

    // Берём напрямую из franchiseCollections — это массив collection_id
    const assignedIds = (franchiseCollections.value[franchiseId] || []).map(Number);

    manageFranchiseData.value = {
        franchise: row,
        assignedCollectionIds: assignedIds,
    };
    manageFranchiseDialogVisible.value = true;
};

const confirmManageFranchiseCollections = async () => {
    try {
        await ElMessageBox.confirm(
            `Save changes for franchise "${manageFranchiseData.value.franchise.label}"?`,
            'Confirm',
            { confirmButtonText: 'OK', cancelButtonText: 'Cancel', type: 'warning' }
        );
        await categoryStore.syncFranchiseCollections({
            franchiseId: manageFranchiseData.value.franchise.id,
            collectionIds: manageFranchiseData.value.assignedCollectionIds,
        });
        ElMessage.success('Saved');
        manageFranchiseDialogVisible.value = false;
        await loadData();
    } catch (e) { if (e !== 'cancel') ElMessage.error('Failed'); }
};

// Tree DnD
const allowDrag = (node) => {
    return node.data.nodeType === 'collection';
};

const allowDrop = (draggingNode, dropNode, type) => {
    // Коллекцию можно перетаскивать только в категорию
    if (draggingNode.data.nodeType !== 'collection') return false;
    if (type === 'inner' && dropNode.data.nodeType === 'category') return true;
    return false;
};

const handleDrop = async (draggingNode, dropNode, dropType) => {
    const collectionId = draggingNode.data.collectionId;
    const newCategoryId = dropNode.data.categoryId;
    const oldCategoryId = draggingNode.data.category_id;

    if (newCategoryId === oldCategoryId) return;

    try {
        await ElMessageBox.confirm(
            `Move collection to "${dropNode.data.label}"?`,
            'Confirm',
            { confirmButtonText: 'OK', cancelButtonText: 'Cancel', type: 'warning' }
        );
        await categoryStore.moveCollection({
            collectionId,
            newCategoryId,
            inheritFranchise: true,
        });
        ElMessage.success('Moved');
        await loadData();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('Move failed');
        await loadData(); // откатить визуал
    }
};
// ===================== Collection images =====================

const initCollectionImages = async () => {
    if (!collections.value) return;

    for (const item of collections.value) {
        if (!collectionImagesMap.value[item.id]) {
            collectionImagesMap.value[item.id] = { cover: [] };
        }

        if (collectionImages.value[item.id]?.cover) {
            const url = collectionImages.value[item.id].cover;
            const filename = url.split('/').pop();
            const file = await urlToFile(url, filename);
            collectionImagesMap.value[item.id].cover = file
                ? [{ name: filename, url, raw: file }]
                : [{ name: filename, url }];
        } else {
            collectionImagesMap.value[item.id].cover = [];
        }
    }
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
// Показывать триггер (плюсик) только если файла нет или есть новый raw-файл
const shouldShowUploadTrigger = (collectionId) => {
    const cover = collectionImagesMap.value[collectionId]?.cover || [];
    // Если файлов нет вообще — показываем плюсик
    if (cover.length === 0) return true;
    // Если есть файл с raw (новый, не сохранённый) — показываем плюсик для замены
    return cover.some(f => f.raw);
};
// Есть ли сохранённое изображение на сервере (файл без raw — значит уже загружен)
const hasSavedImage = (collectionId) => {
    const cover = collectionImagesMap.value[collectionId]?.cover || [];
    return cover.some(f => !f.raw && f.url);
};
// Есть ли новый несохранённый файл (raw)
const hasNewRawFile = (collectionId) => {
    const cover = collectionImagesMap.value[collectionId]?.cover || [];
    return cover.some(f => f.raw);
};
const handleImagePreview = (file) => {
    if (file.url || file.raw) {
        previewImageUrl.value = file.url || URL.createObjectURL(file.raw);
        previewVisible.value = true;
    }
};
const handleImageFileUpdate = (fileList, collectionId) => {
    if (!collectionImagesMap.value[collectionId]) {
        collectionImagesMap.value[collectionId] = { cover: [] };
    }
    collectionImagesMap.value[collectionId].cover = fileList;
};
const closeImagePreview = () => {
    previewVisible.value = false;
    previewImageUrl.value = '';
};

const handleImageFileChange = (file, fileList, collectionId) => {
    collectionImagesMap.value[collectionId].cover = fileList;
};

const handleImageRemove = async (file, fileList, collectionId) => {
    collectionImagesMap.value[collectionId].cover = fileList;
    if (file.url) {
        await categoryStore.deleteCollectionImage(collectionId, file.name);
        await initCollectionImages();
    }
};

const handleImageUpload = async (collectionId) => {
    const fileList = collectionImagesMap.value[collectionId]?.cover || [];
    if (!fileList.length || !fileList[0].raw) return;

    const formData = new FormData();
    formData.append('collectionId', collectionId);
    formData.append('cover', fileList[0].raw);

    await categoryStore.updateCollectionImage(formData);
    await initCollectionImages();
};
</script>

<style scoped>
.partition-management { padding: 20px; }
.tab-content { padding: 20px; }
.mb-3 { margin-bottom: 16px; }
.mt-3 { margin-top: 16px; }
.mt-4 { margin-top: 20px; }
.ml-2 { margin-left: 8px; }
.flex { display: flex; }
.gap-4 { gap: 16px; }
.flex-1 { flex: 1; }

.tree-wrapper {
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    padding: 16px;
    max-height: 600px;
    overflow: auto;
}

.custom-tree-node {
    display: flex;
    align-items: center;
    font-size: 14px;
}
::v-deep(.cover-upload .el-upload-list) {
    display: flex;
    flex-wrap: wrap;
}
::v-deep(.cover-upload .el-upload-list__item) {
    max-width: 150px;
    margin-right: 10px;
}
::v-deep(.cover-upload .el-upload) {
    width: 80px;
    height: 80px;
    border: 1px dashed #d9d9d9;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: border-color 0.2s;
}
::v-deep(.cover-upload .el-upload:hover) {
    border-color: var(--el-color-primary);
}

/* Вертикальные кнопки в колонке actions */
.actions-column {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.actions-column .el-button {
    margin-left: 0 !important;
    width: 100%;
}

/* Триггер загрузки изображения */
.upload-trigger {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed #d9d9d9;
    border-radius: 6px;
    cursor: pointer;
    transition: border-color 0.2s;
}
.upload-trigger:hover {
    border-color: var(--el-color-primary);
}

/* Широкий список файлов в колонке cover */
::v-deep(.cover-upload .el-upload-list) {
    display: flex;
    flex-direction: column;
    width: 100%;
}
::v-deep(.cover-upload .el-upload-list__item) {
    max-width: 100%;
    width: 100%;
    margin-right: 0;
}
::v-deep(.cover-upload .el-upload-list--picture .el-upload-list__item) {
    height: 80px;
    padding: 8px;
}
::v-deep(.cover-upload .el-upload-list__item-thumbnail) {
    width: 60px;
    height: 60px;
    object-fit: cover;
}
/* === Решение проблемы узких блоков в Transfer (управление коллекциями франшизы) === */

/* Выравниваем сам контейнер transfer и задаем отступы между блоками */
:deep(.el-transfer) {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: 16px; /* Расстояние между левой панелью, кнопками и правой панелью */
}

/* Принудительно задаем ширину каждой из двух панелей ~400px */
:deep(.el-transfer-panel) {
    width: 400px !important;
}

/* Аккуратно центрируем кнопки перемещения между панелями */
:deep(.el-transfer__buttons) {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 12px; /* Расстояние между кнопками "влево" и "вправо" */
    padding: 0 8px;
}

/* Опционально: фиксируем высоту области списка, чтобы он выглядел аккуратно
   и имел внутренний скролл, если коллекций очень много */
:deep(.el-transfer-panel__body) {
    height: 400px;
}

/* Улучшаем читаемость элементов внутри списка transfer */
:deep(.el-transfer-panel__item) {
    padding: 8px 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
