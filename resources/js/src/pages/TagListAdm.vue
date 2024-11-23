<template>
    <h3 class="text-center mt-3 mb-3">Tag List:</h3>
    <h5>Search by Tag Name</h5>
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
            :total="tagsList['total']"
            :page-sizes="[20, 50, 100, 300]"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
        />
    </div>
    <template v-if="tagsList['data']">
        <el-table :data="tagsList['data']" v-loading="loader" style="width: 100%"  ref="multipleTableRef"  @selection-change="handleSelectionChange" >
            <el-table-column type="index" label="№"/>
            <el-table-column fixed prop="created_at" label="Date Create" width="160" />
            <el-table-column prop="id" label="ID Tag" width="120" />
            <el-table-column prop="tag_name" label="Tag Name" width="300" />
            <el-table-column prop="tag_name_ru" label="Tag Name Rus" width="300" />
            <el-table-column prop="value" label="Resource" width="300" >
                <template v-slot:default="scope">
                    <RouterLink :to="{ name: 'showTags', params: { tagName: scope.row.value }}"> <strong>{{ scope.row.value }}</strong></RouterLink>
                </template>
            </el-table-column>
            <el-table-column prop="updated_at" label="Date Update" width="120" />
<!--            <el-table-column prop="id" fixed="right" label="Remove" width="100">-->
<!--                <template v-slot:default="scope">-->
<!--                    <el-button link type="danger" @click="handleRemove(scope.row.id,scope.$index)" :icon="Delete" title="Remove from collection" />-->
<!--                </template>-->
<!--            </el-table-column>-->
        </el-table>
        <el-backtop :right="20" :bottom="100" />
    </template>
    <template v-else>
        <p style="text-align: center">Not Found Tag list</p>
    </template>
    <p v-if="error">{{ error }}</p>
</template>

<script lang="ts" setup >
    import { storeToRefs } from 'pinia';
    import { useTagsStore } from "../store/tagsStore";
    import { RouterLink } from 'vue-router'
    import { Delete,View,EditPen } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted,onUpdated, ref, watch, reactive} from "vue";

    const tagsStore = useTagsStore();
    const { tagsList, totalCount, currentPage, pageSize, valueSort, route, loader, error } = storeToRefs(tagsStore);

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
            value: 'tag_name_ru',
            label: 'Tag Name Rus',
        },
        {
            value: 'tag_name',
            label: 'Tag Name',
        },
        {
            value: 'value',
            label: 'Resource',
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
    watch(() => route,  tagsStore.getListTags,{deep: true, immediate: true,});

    const handleSizeChange = (val) => {
        tagsStore.updatePageSize(val);
        tagsStore.getListTags();
    }
    const handleCurrentChange = (val) => {
        tagsStore.updateCurrentPage(val);
        tagsStore.getListTags();
    }
    const handleSelectChange = (val) => {
        tagsStore.updateSort(val);
        tagsStore.getListTags();
    }
    const handleSwitchChange = (val) => {
        let spin = val ? "asc" : 'desc';
        tagsStore.updateSpin(spin);
        tagsStore.getListTags();
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                tagsStore.updateSearchQuery( queryValidateForm.query );
                tagsStore.getListTags();
            } else {
                console.log('error submit!')
                return false
            }
        })
    }
    const resetSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.resetFields();
        tagsStore.updateSearchQuery( '' );
        tagsStore.getListTags();
    }
    // const handleRemove = (id,index) => {
    //     ElMessageBox.confirm(`Are you sure? All the movies will be unbundled  from ID: ${id}. And collection will be deleted. Continue?`, 'WARNING', {
    //         confirmButtonText: 'OK',
    //         cancelButtonText: 'Cancel',
    //         type: 'warning',
    //     }).then(() => {
    //         tagsStore.removeItemFromCollection(id,index);
    //         ElMessage({
    //             type: 'success',
    //             message: 'Delete completed',
    //         })
    //     }).catch(() => {
    //         ElMessage({
    //             type: 'info',
    //             message: 'Delete canceled',
    //         })
    //     })
    // }
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
