<template>
    <h3 class="text-center mt-3 mb-3">Collection List:</h3>
    <h5>Search by Slug Collection</h5>
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
                    placeholder="Search here"
                    v-on:keydown.enter.prevent = "submitSearch(formRef)"
                />
            </el-form-item>
            <el-form-item>
                <el-button @click="resetSearch(formRef)">Reset</el-button>
                <el-button @click="submitSearch(formRef)">Go!</el-button>
            </el-form-item>
<!--            <el-form-item>-->
<!--                <el-button type="primary" @click="submitForm(formRef)">Submit</el-button>-->
<!--            </el-form-item>-->
        </el-form>

    <div class="demo-pagination-block"  v-loading="loader">
        <p>Spin by:</p>
        <el-switch
            v-model="defaultSpin"
            class="mb-2"
            active-text="ASC"
            inactive-text="DESC"
            @change="handleSwitchChange"
        />
        <p>Sort by:</p>
        <el-select
            v-model="valueSort"
            filterable
            @change="handleSelectChange"
            placeholder="Select"
            style="width: 240px"
        >
            <el-option
                v-for="item in options"
                :key="item.value"
                :label="item.label"
                :value="item.value"
            />
        </el-select>
        <div class="demonstration">Jump to</div>
        <el-pagination
            v-model:current-page="currentPage"
            v-model:page-size="pageSize"
            :small="small"
            :disabled="disabled"
            :background="background"
            layout="sizes, prev, pager, next, jumper"
            :total="collectionsList['total']"
            :page-sizes="[20, 50, 100, 300]"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
        />
    </div>
    <template v-if="collectionsList['data']">
        <el-table :data="collectionsList['data']" v-loading="loader" style="width: 100%"  ref="multipleTableRef"  @selection-change="handleSelectionChange" >
            <el-table-column type="index" label="№"/>
            <el-table-column fixed prop="created_at" label="Date Create" width="160" />
            <el-table-column prop="id" label="ID Collection" width="120" />
            <el-table-column prop="category_id" label="ID Category" width="120" />
            <el-table-column prop="value" label="Slug" width="160" />
            <el-table-column prop="label" label="Title_Eng" width="300" />
            <el-table-column prop="label_ru" label="Title_Rus" width="300" />
            <el-table-column prop="updated_at" label="Date Update" width="120" />
            <el-table-column prop="id" fixed="right" label="Remove" width="100">
                <template v-slot:default="scope">
                    <el-button link type="danger" @click="handleRemove(scope.row.id,scope.$index)" :icon="Delete" title="Remove from collection" />
                </template>
            </el-table-column>
        </el-table>
        <el-backtop :right="20" :bottom="100" />
    </template>
    <template v-else>
        <p style="text-align: center">Not Found Collection list</p>
    </template>
    <p v-if="error">{{ error }}</p>
</template>

<script lang="ts" setup >
    import { storeToRefs } from 'pinia';
    import { useCollectionsStore } from "../store/collectionsStore";
    import { RouterLink } from 'vue-router'
    import { Delete,View,EditPen } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted,onUpdated, ref, watch, reactive} from "vue";

    const collectionsStore = useCollectionsStore();
    const { collectionsList, totalCount, currentPage, pageSize, valueSort, route, loader, error } = storeToRefs(collectionsStore);

    const small = ref(false);
    const background = ref(false);
    const disabled = ref(false);
    const defaultSpin = ref(false);
    const options = ref([
        {
            value: 'id',
            label: 'ID',
        },
        {
            value: 'category_id',
            label: 'ID Category',
        },
        {
            value: 'value',
            label: 'Slug',
        },
        {
            value: 'label',
            label: 'Title_Eng',
        },
        {
            value: 'label_ru',
            label: 'Title_Rus',
        },
        {
            value: 'created_at',
            label: 'Created date',
            disabled: true,
        },
        {
            value: 'updated_at',
            label: 'Updated date',
        },
    ]);
    const formRef = ref<FormInstance>();
    const activeMenuIndex = ref('1')
    const queryValidateForm = reactive({
        query: '',
    });

    // const handleSelect = (key, keyPath) => {
    //     console.log(key, keyPath)
    // }
    watch(() => route,  collectionsStore.getListCollections,{deep: true, immediate: true,});

    const handleSizeChange = (val) => {
        collectionsStore.updatePageSize(val);
        collectionsStore.getListCollections();
    }
    const handleCurrentChange = (val) => {
        collectionsStore.updateCurrentPage(val);
        collectionsStore.getListCollections();
    }
    const handleSelectChange = (val) => {
        collectionsStore.updateSort(val);
        collectionsStore.getListCollections();
    }
    const handleSwitchChange = (val) => {
        let spin = val ? "asc" : 'desc';
        collectionsStore.updateSpin(spin);
        collectionsStore.getListCollections();
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                collectionsStore.updateSearchQuery( queryValidateForm.query );
                collectionsStore.getListCollections();
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
        collectionsStore.getListCollections();
    }
    const handleRemove = (id,index) => {
        ElMessageBox.confirm(`Are you sure? All the movies will be unbundled  from ID: ${id}. And collection will be deleted. Continue?`, 'WARNING', {
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
