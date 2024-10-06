<template>
    <h3 class="text-center mt-3 mb-3">{{sectionsData['title']??''}}</h3>
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
        <el-menu
            v-if="sectionsData['collections']"
            :default-active="activeIndex"
            class="el-menu-demo mb-4"
            mode="horizontal"
            :ellipsis="false"
            @select="handleSelect"
        >
            <div class="flex-grow" />
            <el-sub-menu index="1">
                <template #title>Collections</template>
                <template v-for="(item, index) in sectionsData['collections']">
                    <el-menu-item :index="'1-' + index">
                        <RouterLink :to="{ name: 'showCollection', params: { slug: route.params.slug, collName:  item.value }}">{{item.label}}</RouterLink>
                    </el-menu-item>
                </template>
            </el-sub-menu>
        </el-menu>
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
            :total="sectionsData['total']"
            :page-sizes="[20, 50, 100, 300]"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
        />
    </div>
    <template v-if="sectionsData['data']">
        <el-table :data="sectionsData['data']" v-loading="loader" style="width: 100%"  ref="multipleTableRef"  @selection-change="handleSelectionChange" >
            <el-table-column type="index" label="№"/>
            <el-table-column fixed prop="created_at" label="Date Create" width="130" />
            <el-table-column prop="poster" label="Cover" width="130" >
                <template v-slot:default="scope">
                    <el-image :src="scope.row.poster" />
                </template>
            </el-table-column>
            <el-table-column prop="id_movie" label="ID Movie" width="120" />
            <el-table-column prop="year" label="Year" width="100" />
            <el-table-column prop="collection" label="Collection" width="200" >
                <template v-slot:default="scope">
                    <div v-for="col in scope.row.collection" :key="col" >
                        <RouterLink :to="{ name: 'showCollection', params: { slug: route.params.slug, collName:  col['value'] }}"> <strong>{{ col['label'] }}</strong></RouterLink>
                    </div>
                </template>
            </el-table-column>
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
                    <el-button link type="danger" @click="handleRemove(scope.row.id_movie,scope.$index)" :icon="Delete" title="Remove from section" />
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
    import { useSectionStore } from "../store/sectionsStore";
    import { RouterLink } from 'vue-router'
    import { Delete,View,EditPen } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted,onUpdated, ref, watch, reactive} from "vue";

    const sectionStore = useSectionStore();
    const { sectionsData, totalCount, currentPage, pageSize, valueSort, route, loader, error } = storeToRefs(sectionStore);

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
            value: 'collection',
            label: 'Collection',
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
    const activeIndex = ref('1')
    const handleSelect = (key, keyPath) => {
        console.log(key, keyPath)
    }
    watch(() => route,  sectionStore.getDataSections,{deep: true, immediate: true,});

    const handleSizeChange = (val) => {
        sectionStore.updatePageSize(val);
        sectionStore.getDataSections();
    }
    const handleCurrentChange = (val) => {
        sectionStore.updateCurrentPage(val);
        sectionStore.getDataSections();
    }
    const handleSelectChange = (val) => {
        sectionStore.updateSort(val);
        sectionStore.getDataSections();
    }
    const handleSwitchChange = (val) => {
        let spin = val ? "asc" : 'desc';
        sectionStore.updateSpin(spin);
        sectionStore.getDataSections();
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                sectionStore.updateSearchQuery( queryValidateForm.query );
                sectionStore.getDataSections();
            } else {
                console.log('error submit!')
                return false
            }
        })
    }
    const resetSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.resetFields();
        sectionStore.updateSearchQuery( '' );
        sectionStore.getDataSections();
    }
    const handleRemove = (id,index) => {
        ElMessageBox.confirm(`Are you sure? Entries under ID: ${id} will be deleted from section. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
            sectionStore.removeItemFromSection(id,index);
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
