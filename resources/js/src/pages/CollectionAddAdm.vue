<template>
    <h3>Add Collection:</h3>
    <el-row>
        <el-col :span="6">
            <div class="mt-3">
                <h5>Select section:</h5>
                <el-cascader  v-model="categoryId" placeholder="select ..." :props="propsCascader" :options="optionsCats" @change="handleCategoryChange"  style="min-width: 100%;">
                    <template #default="{ data }">
                        <span>{{ data.label }}</span>
                    </template>
                </el-cascader>
            </div>
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>The name of the new collection</span>
                    <el-input
                        v-model="nameCollection"
                        maxlength="50"
                        placeholder="Input collection ..."
                        show-word-limit
                        type="text"
                    />
                </div>
            </div>
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>Ru_ru name of the new collection</span>
                    <el-input
                        v-model="nameRuCollection"
                        maxlength="50"
                        placeholder="Input Ru_ru collection ..."
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

    const nameCollection = ref('');
    const nameRuCollection = ref('');
    const categoryId = ref(null);
    const propsCascader = {
        checkStrictly: true,
    }

    const handleAddFranchise = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            categoryStore.setCategoryCollection({
                category_id: categoryId.value,
                label: nameCollection.value,
                label_ru: nameRuCollection.value,
            });
            categoryId.value = null;
            nameCollection.value = '';
            nameRuCollection.value = '';
            ElMessage({
                type: 'success',
                message: 'Collection added',
            })
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Addition of a new collection has been canceled',
            })
        })
    }
    const handleCategoryChange = (value) => {
        for (var el of optionsCats.value) {
            if (value[0] === el.value){
                categoryId.value = el.id;
            }
        }
    }
    categoryStore.getCategoriesCollection();

</script>

<style scoped>

</style>
