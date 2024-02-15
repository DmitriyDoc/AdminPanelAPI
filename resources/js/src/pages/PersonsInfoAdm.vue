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
        <el-table :data="tableData" v-loading="loader" style="width: 100%" >
            <el-table-column type="index" />
            <el-table-column fixed prop="created_at" label="Date Create" width="130" />
            <el-table-column prop="poster" label="Photo" width="130" >
                <template v-slot:default="scope">
                    <el-image :src="scope.row.poster" />
                </template>
            </el-table-column>
            <el-table-column prop="actor_id" label="ID Person" width="120" />
            <el-table-column prop="name" label="Name" width="600" />
            <el-table-column prop="updated_at" label="Date Update" width="120" />
            <el-table-column prop="actor_id" fixed="right" label="Operations" width="200">
                <template v-slot:default="scope">
                    <el-button type="success" link >
                        <RouterLink :to="{ name: 'showperson', params: { slug: route.params.slug, id: scope.row.actor_id }}">
                            Details
                        </RouterLink>
                    </el-button>
                    <el-button link type="primary" >
                        <RouterLink :to="{ name: 'editPerson', params: { slug: route.params.slug, id: scope.row.actor_id }}">
                            Edit
                        </RouterLink>
                    </el-button>
                    <el-button link type="danger" @click="handleRemove(scope.row.actor_id,scope.$index)" :icon="Delete"  />
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
    import { usePersonsStore } from "../store/personsStore";
    import { RouterLink } from 'vue-router'
    import { Delete } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox } from 'element-plus'
    import type { FormInstance } from 'element-plus'
    import type { Action } from 'element-plus'
    import { onMounted,onUpdated, ref, watch, reactive} from "vue";

    const personsStore = usePersonsStore();
    const { tableData, totalCount, currentPage, pageSize, valueSort, route, loader, error, } = storeToRefs(personsStore);

    const small = ref(false);
    const background = ref(false);
    const disabled = ref(false);
    const defaultSpin = ref(false);
    const options = ref([
        {
            value: 'actor_id',
            label: 'Actor ID',
        },
        {
            value: 'name',
            label: 'Name',
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

    watch(() => route,  personsStore.getCelebs,{deep: true, immediate: true,});

    const handleSizeChange = (val) => {
        pageSize.value = val;
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
