<template>
    <h3 class="text-center mt-3 mb-3">{{title}}</h3>
    <p>{{locale.search_by_title_id}}</p>
    <el-form
        ref="formRef"
        :model="queryValidateForm"
        class="demo-ruleForm"
    >
        <el-form-item prop="query" :rules="[{}]">
            <el-input
                v-model.query="queryValidateForm.query"
                type="text"
                autocomplete="off"
                :placeholder="locale.search_here"
                v-on:keydown.enter.prevent = "submitSearch(formRef)"
            />
        </el-form-item>
        <el-form-item>
            <el-button @click="resetSearch(formRef)">{{locale.reset}}</el-button>
            <el-button @click="submitSearch(formRef)">{{locale.go}}</el-button>
        </el-form-item>
    </el-form>
    <div class="demo-pagination-block" >
        <el-menu
            v-if="frinchises"
            :default-active="activeMenuIndex"
             class="el-menu-demo mb-4"
            mode="horizontal"
            :ellipsis="false"
            @select="handleSelect"
        >
            <div class="flex-grow" />
            <el-sub-menu index="1" :popper-append-to-body="false">
                <template #title>{{$t('franchise')}}</template>
                <template v-for="(item, index) in frinchises">
                    <el-menu-item :index="'1-' + index" >
                        <RouterLink :to="{ name: 'showFranchise', params: { slug: route.params.slug, collName: route.params.collName, franName:  item.value }}">{{item.label}}</RouterLink>
                    </el-menu-item>
                </template>
            </el-sub-menu>
        </el-menu>
        <p>{{locale.spin_by}}</p>
        <el-switch
            v-model="defaultSpin"
            class="mb-2"
            active-text="&#8595;"
            inactive-text="&#8593;"
            @change="handleSwitchChange"
        />
        <p>{{locale.sort_by}}</p>
        <el-select
            v-model="valueSort"
            filterable
            @change="handleSelectChange"
            style="width: 240px"
        >
            <el-option
                v-for="field in locale.collection_sort_fields"
                :key="field.value"
                :label="field.label"
                :value="field.value"
            />
        </el-select>
        <div class="demonstration">{{locale.jump_to}}</div>
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
    <el-table :data="collectionsData" v-loading="loader" :empty-text="$t('data_not_found')"  style="width: 100%"  ref="multipleTableRef"  @selection-change="handleSelectionChange" >
        <el-table-column type="index" label="№"/>
        <el-table-column fixed prop="created_at" :label="locale.created_at" width="130" />
        <el-table-column prop="poster" :label="locale.poster" width="130" >
            <template v-slot:default="scope">
                <el-image :src="scope.row.poster" />
            </template>
        </el-table-column>
        <el-table-column prop="published" :label="locale.status" width="170" >
            <template v-slot:default="scope">
                <el-text :type="scope.row.published.status_type">
                    <strong>{{scope.row.published.status_text}}</strong>
                </el-text>
            </template>
        </el-table-column>
        <el-table-column prop="id_movie" :label="locale.id_movie" width="120" />
        <el-table-column prop="year" :label="locale.year" width="100" />
        <el-table-column prop="franchise" :label="locale.franchise" width="200" >
            <template v-slot:default="scope">
                <template v-if="scope.row.franchise">
                    <div v-for="fran in scope.row.franchise" :key="fran" >
                        <RouterLink :to="{ name: 'showFranchise', params: { slug: route.params.slug, collName: route.params.collName, franName:  fran['value'] }}"> <strong>{{ fran['label'] }}</strong></RouterLink>
                    </div>
                </template>
            </template>
        </el-table-column>
        <el-table-column prop="title" :label="locale.title" width="600" />
        <el-table-column prop="updated_at" :label="locale.updated_at" width="120" />
        <el-table-column prop="id_movie" property="type_film" fixed="right" :label="locale.actions" width="120">
            <template v-slot:default="scope">
                <el-button type="success" link >
                    <RouterLink :to="{ name: 'showMovie', params: { slug: scope.row.type_film, id: scope.row.id_movie }}">
                        <el-button link type="primary" :icon="View" :title="$t('details')"/>
                    </RouterLink>
                </el-button>
                <el-button link type="primary" >
                    <RouterLink :to="{ name: 'editMovie', params: { slug: scope.row.type_film, id: scope.row.id_movie }}">
                        <el-button link type="primary" :icon="EditPen" :title="$t('edit')"/>
                    </RouterLink>
                </el-button>
                <el-button link type="danger" @click="handleRemove(scope.row.id_movie,scope.$index)" :icon="Delete" :title="locale.remove_from_collection" />
             </template>
        </el-table-column>
    </el-table>
    <el-backtop :right="20" :bottom="100" />
    <p v-if="error">{{ error }}</p>
</template>

<script lang="ts" setup >
    import { storeToRefs } from 'pinia';
    import { useCollectionsStore } from "../store/collectionsStore";
    import { useLanguageStore } from "../store/languageStore";
    import { RouterLink } from 'vue-router'
    import { Delete,View,EditPen } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted, ref, watch, reactive} from "vue";

    const languageStore = useLanguageStore();
    const collectionsStore = useCollectionsStore();
    const { watcherLang } = storeToRefs( languageStore );
    const { collectionsData, totalCount, frinchises, title, locale, currentPage, pageSize, valueSort, route, loader, error } = storeToRefs(collectionsStore);

    const small = ref(false);
    const background = ref(false);
    const disabled = ref(false);
    const defaultSpin = ref(false);
    const formRef = ref<FormInstance>();
    const activeMenuIndex = ref('1')
    const queryValidateForm = reactive({
        query: '',
    });

    // const handleSelect = (key, keyPath) => {
    //     console.log(key, keyPath)
    // }

    watch(() => [route, watcherLang.value],  collectionsStore.getDataCollections,{deep: true, immediate: true,});


    const handleSizeChange = (val) => {
        collectionsStore.updatePageSize(val);
        collectionsStore.getDataCollections();
    }
    const handleCurrentChange = (val) => {
        collectionsStore.updateCurrentPage(val);
        collectionsStore.getDataCollections();
    }
    const handleSelectChange = (val) => {
        collectionsStore.updateSort(val);
        collectionsStore.getDataCollections();
    }
    const handleSwitchChange = (val) => {
        let spin = val ? "asc" : 'desc';
        collectionsStore.updateSpin(spin);
        collectionsStore.getDataCollections();
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                collectionsStore.updateSearchQuery( queryValidateForm.query );
                collectionsStore.getDataCollections();
            } else {
                console.log('error submit!')
                return false
            }
        })
    }
    const resetSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.resetFields();
        collectionsStore.updateSearchQuery( '' );
        collectionsStore.getDataCollections();
    }
    const handleRemove = (id,index) => {
        ElMessageBox.confirm(`Are you sure? Entries under ID: ${id} will be deleted from collection. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
            collectionsStore.removeItemFromCollection(id,index);
            ElMessage({
                type: 'success',
                message: 'Delete completed',
            })
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Delete canceled',
            })
        })
    }
</script>

<style lang="scss" scoped>
    ::v-deep(.el-menu--popup){
        height: auto;
        max-height: 400px;
        overflow-y: auto;
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
