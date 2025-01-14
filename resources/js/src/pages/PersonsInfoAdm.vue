<template>
    <p>{{locale.search_by_name_id}}</p>
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
    <div class="demo-pagination-block"  >
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
                v-for="item in locale.person_sort_fields"
                :key="item.value"
                :label="item.label"
                :value="item.value"
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
            :page-sizes="[20, 50]"
            @size-change="handleSizeChange"
            @current-change="handleCurrentChange"
        />
    </div>
    <el-table :data="tableData" v-loading="loader" :empty-text="$t('data_not_found')"  style="width: 100%" ref="multipleTableRef">
        <el-table-column type="index" label="№"/>
        <el-table-column fixed prop="created_at" :label="locale.created_at" width="130" />
        <el-table-column prop="poster" :label="locale.photo" width="130" >
            <template v-slot:default="scope">
                <el-image :src="scope.row.poster" />
            </template>
        </el-table-column>
        <el-table-column prop="id_celeb" :label="locale.id_person" width="120" />
        <el-table-column prop="nameActor" :label="locale.name" width="600" />
<!--            <el-table-column prop="updated_at" label="Date Update" width="120" />-->
        <el-table-column prop="id_celeb" fixed="right" :label="locale.actions" width="200">
            <template v-slot:default="scope">
                <el-button type="success" link >
                    <RouterLink :to="{ name: 'showPerson', params: { id: scope.row.id_celeb }}">
                        <el-button link type="primary" :icon="View" :title="$t('details')"/>
                    </RouterLink>
                </el-button>
                <el-button link type="primary" >
                    <RouterLink :to="{ name: 'editPerson', params: { id: scope.row.id_celeb }}">
                        <el-button link type="primary" :icon="EditPen" :title="$t('edit')"/>
                    </RouterLink>
                </el-button>
                <el-button link type="danger" @click="handleRemove(scope.row.id_celeb,scope.$index)" :icon="Delete" :title="$t('remove')" />
             </template>
        </el-table-column>
    </el-table>
    <el-backtop :right="100" :bottom="100" />
    <p v-if="error">{{ error }}</p>
</template>

<script lang="ts" setup >
    import { storeToRefs } from 'pinia';
    import { usePersonsStore } from "../store/personsStore";
    import { useLanguageStore } from "../store/languageStore";
    import { RouterLink } from 'vue-router'
    import { Delete,View,EditPen } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted, ref, watch, reactive} from "vue";

    const personsStore = usePersonsStore();
    const languageStore = useLanguageStore();
    const { tableData, locale, totalCount, currentPage, pageSize, valueSort, route, loader, error, } = storeToRefs(personsStore);
    const { watcherLang } = storeToRefs( languageStore );

    const small = ref(false);
    const background = ref(false);
    const disabled = ref(false);
    const defaultSpin = ref(false);

    watch(() => watcherLang.value, (newLang) => {
        personsStore.getCelebs();
    });
    onMounted(  () => {
        personsStore.getCelebs();
    });
    const formRef = ref<FormInstance>();
    const queryValidateForm = reactive({
        query: '',
    });

    const handleSizeChange = (val) => {
        personsStore.updatePageSize(val);
        personsStore.getCelebs();
    }
    const handleCurrentChange = (val) => {
        personsStore.updateCurrentPage(val);
        personsStore.getCelebs();
    }
    const handleSelectChange = (val) => {
        personsStore.updateSort(val);
        personsStore.getCelebs();
    }
    const handleSwitchChange = (val) => {
        let spin = val ? "asc" : 'desc';
        personsStore.updateSpin(spin);
        personsStore.getCelebs();
    }
    const submitSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.validate((valid) => {
            if (valid) {
                personsStore.updateSearchQuery( queryValidateForm.query );
                personsStore.getCelebs();
            } else {
                console.log('error submit!')
                return false
            }
        })
    }
    const resetSearch = (formEl: FormInstance | undefined) => {
        if (!formEl) return
        formEl.resetFields();
        personsStore.updateSearchQuery( '' );
        personsStore.getCelebs();
    }
    const handleRemove = (id,index) => {
        ElMessageBox.confirm(`Are you sure? Entries under ID: ${id} will be deleted. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
            personsStore.removeItem(id,index);
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
