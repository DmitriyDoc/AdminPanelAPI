<template>
    <h3 class="text-center mt-3 mb-3">{{tagsData['title']??''}}</h3>
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
<!--        <el-form-item>-->
<!--            <el-button type="primary" @click="submitForm(formRef)">Submit</el-button>-->
<!--        </el-form-item>-->
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
            :total="tagsData['total']"
            :page-sizes="[20, 50, 100, 300]"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
        />
    </div>
    <template v-if="tagsData['data']">
        <el-table :data="tagsData['data']" v-loading="loader" style="width: 100%"  ref="multipleTableRef"  @selection-change="handleSelectionChange" >
            <el-table-column type="index" label="№"/>
            <el-table-column fixed prop="created_at" label="Date Create" width="130" />
            <el-table-column prop="poster" label="Cover" width="130" >
                <template v-slot:default="scope">
                    <el-image :src="scope.row.poster" />
                </template>
            </el-table-column>
            <el-table-column prop="id_movie" label="ID Movie" width="120" />
            <el-table-column prop="year" label="Year" width="100" />
            <el-table-column prop="title" label="Title" width="600" />
            <el-table-column prop="updated_at" label="Date Update" width="120" />
            <el-table-column prop="id_movie" property="type_film" fixed="right" label="Operations" width="120">
                <template v-slot:default="scope">
                    <el-button type="success" link >
                        <RouterLink :to="{ name: 'showmovie', params: { slug: scope.row.type_film, id: scope.row.id_movie }}">
                            <el-button link type="primary" :icon="View" title="Details"/>
                        </RouterLink>
                    </el-button>
                    <el-button link type="primary" >
                        <RouterLink :to="{ name: 'editMovie', params: { slug: scope.row.type_film, id: scope.row.id_movie }}">
                            <el-button link type="primary" :icon="EditPen" title="Edit"/>
                        </RouterLink>
                    </el-button>
                 </template>
            </el-table-column>
        </el-table>
        <el-backtop :right="20" :bottom="100" />
    </template>
    <template v-else>
        <p style="text-align: center">Not Found</p>
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
    const { tagsData, totalCount, currentPage, pageSize, valueSort, route, loader, error } = storeToRefs(tagsStore);

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
            value: 'id_movie',
            label: 'ID movie',
        },
        {
            value: 'title',
            label: 'Title',
        },
        {
            value: 'year',
            label: 'Year',
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
    watch(() => route,  tagsStore.getDataTags,{deep: true, immediate: true,});

    const handleSizeChange = (val) => {
        tagsStore.updatePageSize(val);
        tagsStore.getDataTags();
    }
    const handleCurrentChange = (val) => {
        tagsStore.updateCurrentPage(val);
        tagsStore.getDataTags();
    }
    const handleSelectChange = (val) => {
        tagsStore.updateSort(val);
        tagsStore.getDataTags();
    }
    const handleSwitchChange = (val) => {
        let spin = val ? "asc" : 'desc';
        tagsStore.updateSpin(spin);
        tagsStore.getDataTags();
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                tagsStore.updateSearchQuery( queryValidateForm.query );
                tagsStore.getDataTags();
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
        tagsStore.getDataTags();
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
