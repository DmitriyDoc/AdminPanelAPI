<template>
    <h3>{{locale.type_movies_title}}</h3>
    <div style="margin: 20px 0;" >
        <el-radio-group v-model="typeMovie" @change="handleCurrentType(typeMovie)">
            <el-radio-button v-for="(type, index) of locale.type_movies" :label="type" :value="index" />
        </el-radio-group>
    </div>
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
        <!--        <el-form-item>-->
        <!--            <el-button type="primary" @click="submitForm(formRef)">Submit</el-button>-->
        <!--        </el-form-item>-->
    </el-form>
    <div class="demo-pagination-block" >
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
                v-for="field in locale.table_sort_fields"
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
    <el-table :data="tableData" v-loading="loader" :empty-text="$t('data_not_found')" style="width: 100%"  ref="multipleTableRef" >
        <el-table-column type="index" label="№"/>
        <el-table-column fixed prop="created_at" :label="locale.created_at" width="130" />
        <el-table-column prop="poster" :label="locale.poster" width="150" >
            <template v-slot:default="scope">
                <el-image :src="scope.row.poster" />
            </template>
        </el-table-column>
        <el-table-column prop="id_movie" :label="locale.id_movie" width="120" />
        <el-table-column prop="year_release" :label="locale.year" width="100" />
        <el-table-column prop="title" :label="locale.title" width="600" />
        <el-table-column prop="updated_at" :label="locale.updated_at" width="150" />
        <el-table-column fixed="right" prop="id_movie" :label="locale.actions" width="120">
            <template v-slot:default="scope">
                <el-button type="success" link >
                    <RouterLink :to="{ name: 'showMovie', params: { slug: typeMovie, id: scope.row.id_movie }}">
                        <el-button link type="primary" :icon="View" :title="$t('details')"/>
                    </RouterLink>
                </el-button>
                <el-button link type="primary" >
                    <RouterLink :to="{ name: 'editMovie', params: { slug: typeMovie, id: scope.row.id_movie }}">
                        <el-button link type="primary" :icon="EditPen" :title="$t('edit')"/>
                    </RouterLink>
                </el-button>
                <el-button link type="danger" @click="handleRemove(scope.row.id_movie,typeMovie,scope.$index)" :icon="Delete" :title="$t('remove')" />
             </template>
        </el-table-column>
    </el-table>
<!--    <div slot="empty">-->
<!--        <el-empty description=" " :image-size="150" />-->
<!--    </div>-->
    <el-backtop :right="100" :bottom="100" />
    <p v-if="error">{{ error }}</p>
</template>

<script lang="ts" setup>
    import { storeToRefs } from 'pinia';
    import { useMoviesStore } from "../store/moviesStore";
    import { useLanguageStore } from "../store/languageStore";
    import { RouterLink } from 'vue-router'
    import { Delete,View,EditPen } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted, ref, watch, reactive} from "vue";

    const moviesStore = useMoviesStore();
    const languageStore = useLanguageStore();
    const { watcherLang } = storeToRefs( languageStore );
    const { tableData, locale, totalCount, currentPage, pageSize, valueSort, route, loader, error, } = storeToRefs( moviesStore );

    const small = ref(false);
    const background = ref(false);
    const disabled = ref(false);
    const defaultSpin = ref(false);

    const typeMovie = ref('FeatureFilm')
    const formRef = ref<FormInstance>();
    const queryValidateForm = reactive({
        query: '',
    });

    watch(() => watcherLang.value, (newLang) => {
        moviesStore.getMovies(typeMovie.value);
    });

    const handleSizeChange = (val) => {
        pageSize.value = val;
        moviesStore.getMovies(typeMovie.value);
    }
    const handleCurrentChange = (val) => {
        moviesStore.updateCurrentPage(val);
        moviesStore.getMovies(typeMovie.value);
    }
    const handleSelectChange = (val) => {
        moviesStore.updateSort(val);
        moviesStore.getMovies(typeMovie.value);
    }
    const handleSwitchChange = (val) => {
        let spin = val ? "asc" : 'desc';
        moviesStore.updateSpin(spin);
        moviesStore.getMovies(typeMovie.value);
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                moviesStore.updateSearchQuery( queryValidateForm.query );
                moviesStore.getMovies(typeMovie.value);
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
        moviesStore.getMovies(typeMovie.value);
    }
    const handleRemove = (id,type,index) => {
        ElMessageBox.confirm(`Are you sure? Entries under ID: ${id} will be deleted. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
            moviesStore.removeItem(id,type,index);
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
    const handleCurrentType = (type) => {
        moviesStore.getMovies(type);
    }
    onMounted(  () => {
        moviesStore.getMovies(typeMovie.value);
    });
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
