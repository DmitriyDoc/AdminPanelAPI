<template>
    <template v-if="franchiseData.data">
        <h3 class="text-center mt-3 mb-3">{{franchiseData.title}}</h3>
        <el-page-header :icon="null" >
            <template #extra>
                <p>{{franchiseData.locale.display}}</p>
                <div class="flex items-center">
                    <el-switch
                        v-model="displaySwitch"
                        class="mb-2"
                        :active-text="franchiseData.locale.display_timeline"
                        :inactive-text="franchiseData.locale.display_table"
                        @change="handleSwitchDisplay"
                    />
                </div>
            </template>
        </el-page-header>
        <div v-if="displaySwitch">
            <el-timeline style="max-width: 800px">
                <h4>{{franchiseData.locale.timeline_franchise}} ({{franchiseData.data.length}} {{franchiseData.locale.timeline_movies}} )</h4>
                <el-timeline-item v-for="item in yearsDiapason()" :timestamp="item" placement="top"  >
                    <template v-for="movie in franchiseData['data']">
                        <el-card v-if="item == movie.year" shadow="hover" >
                            <div class="common-layout">
                                <el-container>
                                    <el-aside width="100px">
                                        <el-image :src="movie.poster" :fit="cover" style="width: 80%"/>
                                    </el-aside>
                                    <div>
                                        <el-text class="mx-1" tag="mark">{{movie.type_film}}</el-text>
                                        <h4>{{movie.title}}</h4>
                                        <RouterLink :to="{ name: 'showMovie', params: { slug: movie.type_film, id: movie.id_movie }}">
                                            <el-button link type="primary" > {{franchiseData.locale.details}} </el-button>
                                        </RouterLink>
                                    </div>
                                </el-container>
                            </div>
                        </el-card>
                    </template>
                </el-timeline-item>
            </el-timeline>
        </div>
        <div v-else>
            <p>{{franchiseData.locale.search_by_title_id}}</p>
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
                        :placeholder="franchiseData.locale.search_here"
                        v-on:keydown.enter.prevent = "submitSearch(formRef)"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button @click="resetSearch(formRef)">{{franchiseData.locale.reset}}</el-button>
                    <el-button @click="submitSearch(formRef)">{{franchiseData.locale.go}}</el-button>
                </el-form-item>
                <!--        <el-form-item>-->
                <!--            <el-button type="primary" @click="submitForm(formRef)">Submit</el-button>-->
                <!--        </el-form-item>-->
            </el-form>

<!--            <div class="demo-pagination-block"  v-loading="loader">-->
<!--                <p>Spin by:</p>-->
<!--                <el-switch-->
<!--                    v-model="defaultSpin"-->
<!--                    class="mb-2"-->
<!--                    active-text="ASC"-->
<!--                    inactive-text="DESC"-->
<!--                    @change="handleSwitchChange"-->
<!--                />-->
<!--                <p>Sort by:</p>-->
<!--                <el-select-->
<!--                    v-model="valueSort"-->
<!--                    filterable-->
<!--                    @change="handleSelectChange"-->
<!--                    placeholder="Select"-->
<!--                    style="width: 240px"-->
<!--                >-->
<!--                    <el-option-->
<!--                        v-for="item in options"-->
<!--                        :key="item.value"-->
<!--                        :label="item.label"-->
<!--                        :value="item.value"-->
<!--                    />-->
<!--                </el-select>-->
<!--                <div class="demonstration">Jump to</div>-->
<!--                <el-pagination-->
<!--                    v-model:current-page="currentPage"-->
<!--                    v-model:page-size="pageSize"-->
<!--                    :small="small"-->
<!--                    :disabled="disabled"-->
<!--                    :background="background"-->
<!--                    layout="sizes, prev, pager, next, jumper"-->
<!--                    :total="franchiseData['total']"-->
<!--                    :page-sizes="[20, 50, 100, 300]"-->
<!--                    @size-change="handleSizeChange"-->
<!--                    @current-change="handleCurrentChange"-->
<!--                />-->
<!--            </div>-->
            <el-table :data="franchiseData.data" v-loading="loader" style="width: 100%"  ref="multipleTableRef"  @selection-change="handleSelectionChange" >
                <el-table-column type="index" label="№"/>
                <el-table-column fixed prop="created_at" :label="$t('created_at')" width="130" />
                <el-table-column prop="poster" :label="$t('poster')" width="130" >
                    <template v-slot:default="scope">
                        <el-image :src="scope.row.poster" />
                    </template>
                </el-table-column>
                <el-table-column prop="id_movie" :label="$t('id_movie')" width="120" />
                <el-table-column prop="year" :label="$t('year_release')" width="100" />
                <el-table-column prop="title" :label="$t('title')" width="600" />
                <el-table-column prop="updated_at" :label="$t('updated_at')" width="120" />
                <el-table-column prop="id_movie" property="type_film_link" fixed="right" :label="$t('actions')" width="120">
                    <template v-slot:default="scope">
                        <el-button type="success" link >
                            <RouterLink :to="{ name: 'showMovie', params: { slug: scope.row.type_film_link, id: scope.row.id_movie }}">
                                <el-button link type="primary" :icon="View" :title="$t('details')"/>
                            </RouterLink>
                        </el-button>
                        <el-button link type="primary" >
                            <RouterLink :to="{ name: 'editMovie', params: { slug: scope.row.type_film_link, id: scope.row.id_movie }}">
                                <el-button link type="primary" :icon="EditPen" :title="$t('edit')"/>
                            </RouterLink>
                        </el-button>
                        <!--                    <el-button link type="danger" @click="handleRemove(scope.row.id_movie,scope.$index)" :icon="Delete" title="Remove from franchise" />-->
                    </template>
                </el-table-column>
            </el-table>
        </div>
        <el-backtop :right="20" :bottom="100" />
    </template>
    <template v-else>
        <p style="text-align: center">{{$t('data_not_found')}}</p>
    </template>
    <p v-if="error">{{ error }}</p>
</template>

<script lang="ts" setup >
    import { storeToRefs } from 'pinia';
    import { useFranchiseStore } from "../store/franchiseStore";
    import { useLanguageStore } from "../store/languageStore";
    import { RouterLink } from 'vue-router'
    import { Delete,View,EditPen } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted,onUpdated, ref, watch, reactive } from "vue";

    const franchiseStore = useFranchiseStore();
    const languageStore = useLanguageStore();
    const { watcherLang } = storeToRefs( languageStore );
    const { franchiseData, totalCount, currentPage, pageSize, valueSort, route, loader, error } = storeToRefs(franchiseStore);

    const small = ref(false);
    const background = ref(false);
    const disabled = ref(false);
    const defaultSpin = ref(false);
    const displaySwitch = ref(false);

    const formRef = ref<FormInstance>();
    const activeMenuIndex = ref('1')
    const queryValidateForm = reactive({
        query: '',
    });

    // const handleSelect = (key, keyPath) => {
    //     console.log(key, keyPath)
    // }

    watch(() => [route, watcherLang.value],  franchiseStore.getDataFranchise,{deep: true, immediate: true,});

    const rangeYears = ref(0);
    const yearsDiapason = () => {
        function range(start, end) {
            var foo = [];
            for (var i = start; i <= end; i++) {
                foo.push(i);
            }
            return foo;
        }

        //1986,new Date().getFullYear()
        return range(1896, new Date().getFullYear()).reverse();
    };

    const handleSizeChange = (val) => {
        franchiseStore.updatePageSize(val);
        franchiseStore.getDataFranchise();
    }
    const handleCurrentChange = (val) => {
        franchiseStore.updateCurrentPage(val);
        franchiseStore.getDataFranchise();
    }
    const handleSelectChange = (val) => {
        franchiseStore.updateSort(val);
        franchiseStore.getDataFranchise();
    }
    const handleSwitchChange = (val) => {
        displaySwitch.value = val ? true : false;
    }
    const handleSwitchDisplay = (val) => {
        let spin = val ? "asc" : 'desc';
        franchiseStore.updateSpin(spin);
        franchiseStore.getDataFranchise();
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                franchiseStore.updateSearchQuery( queryValidateForm.query );
                franchiseStore.getDataFranchise();
            } else {
                console.log('error submit!')
                return false
            }
        })
    }
    const resetSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.resetFields();
        franchiseStore.updateSearchQuery( '' );
        franchiseStore.getDataFranchise();
    }
    const handleRemove = (id,index) => {
        ElMessageBox.confirm(`Are you sure? Entries under ID: ${id} will be deleted from collection. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
            franchiseStore.removeItemFromFranchise(id,index);
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
    :deep(.el-page-header__left){
        visibility: hidden;
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
