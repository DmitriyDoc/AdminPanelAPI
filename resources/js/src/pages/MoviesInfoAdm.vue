<template>
    <h3>Table {{route.params.slug}}:</h3>
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
            :total="totalCount"
            :page-sizes="[20, 50, 100, 300]"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
        />
    </div>
    <template v-if="tableData">
        <el-table :data="tableData" v-loading="loader" style="width: 100%"  ref="multipleTableRef"  @selection-change="handleSelectionChange" >
            <el-table-column type="index" />
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
            <el-table-column prop="id_movie" fixed="right" label="Operations" width="120">
                <template v-slot:default="scope">
                    <el-button type="success" link >
                        <RouterLink :to="{ name: 'showmovie', params: { slug: route.params.slug, id: scope.row.id_movie }}">
                            <el-button link type="primary" :icon="View" title="Details"/>
                        </RouterLink>
                    </el-button>
                    <el-button link type="primary" >
                        <RouterLink :to="{ name: 'editMovie', params: { slug: route.params.slug, id: scope.row.id_movie }}">
                            <el-button link type="primary" :icon="EditPen" title="Edit"/>
                        </RouterLink>
                    </el-button>
                    <el-button link type="danger" @click="handleRemove(scope.row.id_movie,scope.$index)" :icon="Delete" title="Remove" />
                 </template>
            </el-table-column>
        </el-table>
        <el-backtop :right="100" :bottom="100" />
    </template>
   <template v-else>
       <p style="text-align: center">Not Found</p>
   </template>
    <p v-if="error">{{ error }}</p>
</template>

<script lang="ts" setup >
    import { storeToRefs } from 'pinia';
    import { useMoviesStore } from "../store/moviesStore";
    import { RouterLink } from 'vue-router'
    import { Delete,View,EditPen } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted,onUpdated, ref, watch, reactive} from "vue";

    const moviesStore = useMoviesStore();
    const { tableData, totalCount, currentPage, pageSize, valueSort, route, loader, error, } = storeToRefs(moviesStore);

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
            value: 'year',
            label: 'Year',
        },
        {
            value: 'title',
            label: 'Title',
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
    const queryValidateForm = reactive({
        query: '',
    });

    watch(() => route,  moviesStore.getMovies,{deep: true, immediate: true,});

    const handleSizeChange = (val) => {
        pageSize.value = val;
        moviesStore.getMovies();
    }
    const handleCurrentChange = (val) => {
        moviesStore.updateCurrentPage(val);
        moviesStore.getMovies();
    }
    const handleSelectChange = (val) => {
        moviesStore.updateSort(val);
        moviesStore.getMovies();
    }
    const handleSwitchChange = (val) => {
        let spin = val ? "asc" : 'desc';
        moviesStore.updateSpin(spin);
        moviesStore.getMovies();
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                moviesStore.updateSearchQuery( queryValidateForm.query );
                moviesStore.getMovies();
            } else {
                console.log('error submit!')
                return false
            }
        })
    }
    const resetSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.resetFields();
        moviesStore.updateSearchQuery( '' );
        moviesStore.getMovies();
    }
    const handleRemove = (id,index) => {
        ElMessageBox.confirm(`Are you sure? Entries under ID: ${id} will be deleted. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
            moviesStore.removeItem(id,index);
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
