<template>
    <h3>Add Franchise:</h3>
    <el-row>
        <el-col :span="6">
            <div class="mt-3">
                <h5>Select collection:</h5>
                <el-cascader  v-model="categoryId" placeholder="select ..." :props="propsCascader" :options="optionsCats" @change="handleCategoryChange"  style="min-width: 100%;">
                    <template #default="{ node, data }">
                        <span>{{ data.label }}</span>
                        <span v-if="!node.isLeaf"> ({{ data.children.length }}) </span>
                    </template>
                </el-cascader>
            </div>
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>The name of the new franchise</span>
                    <el-input
                        v-model="nameFranchise"
                        maxlength="50"
                        placeholder="Input franchise ..."
                        show-word-limit
                        type="text"
                    />
                </div>
            </div>
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>Ru_ru name of the new franchise</span>
                    <el-input
                        v-model="nameRuFranchise"
                        maxlength="50"
                        placeholder="Input Ru_ru franchise ..."
                        show-word-limit
                        type="text"
                    />
                </div>
            </div>
            <div>
                <button @click="handleAddFranchise" class="btn btn-primary" type="submit">Add New</button>
            </div>
        </el-col>
    </el-row>

</template>

<script setup>
    import { storeToRefs } from 'pinia';
    import { ref } from "vue";
    import { ElMessage, ElMessageBox} from "element-plus";
    import { useCategoriesStore } from "../store/categoriesStore";

    const categoryStore = useCategoriesStore();
    const { optionsCats } = storeToRefs(categoryStore);
    const propsCascader = {
        multiple: true,
        checkStrictly: true,
    }
    const nameFranchise = ref('');
    const nameRuFranchise = ref('');
    const categoryId = ref([]);
    const categoriesArr = ref([]);

    const handleAddFranchise = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            categoryStore.setCategoryFranchise({
                collection: categoriesArr.value,
                label: nameFranchise.value,
                label_ru: nameRuFranchise.value,
            });
            categoriesArr.value = [];
            categoryId.value = [];
            nameFranchise.value = '';
            nameRuFranchise.value = '';
            ElMessage({
                type: 'success',
                message: 'Franchise added',
            })
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Addition of a new franchise has been canceled',
            })
        })
    }
    const handleCategoryChange = (value) => {
        categoriesArr.value = [];
        value.forEach(obj => {
            categoriesArr.value.push(obj[1]);
        })
    }
    categoryStore.getCategoriesFranchise();

</script>

<style scoped>

</style>
