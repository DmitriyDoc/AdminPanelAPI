<template>
    <h3 class="text-center mt-3 mb-3">{{$t('add_franchise')}}</h3>
    <el-row>
        <el-col :span="6">
            <div class="mt-3">
                <h5>{{franchiseLocale.select_collection}}</h5>
                <el-cascader  v-model="categoryId" :placeholder="franchiseLocale.select" :props="propsCascader" :options="optionsCats" @change="handleCategoryChange"  style="min-width: 100%;">
                    <template #default="{ node, data }">
                        <span>{{ data.label }}</span>
                        <span v-if="!node.isLeaf"> ({{ data.children.length }}) </span>
                    </template>
                </el-cascader>
            </div>
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>{{franchiseLocale.name_of_the_new_franchise}}</span>
                    <el-input
                        v-model="nameFranchise"
                        maxlength="50"
                        :placeholder="franchiseLocale.enter_name"
                        show-word-limit
                        type="text"
                    />
                </div>
            </div>
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>{{franchiseLocale.ru_name_of_the_new_franchise}}</span>
                    <el-input
                        v-model="nameRuFranchise"
                        maxlength="50"
                        :placeholder="franchiseLocale.enter_name"
                        show-word-limit
                        type="text"
                    />
                </div>
            </div>
            <div>
                <button @click="handleAddFranchise" class="btn btn-primary">{{franchiseLocale.btn_add_new}}</button>
            </div>
        </el-col>
    </el-row>
</template>

<script setup>
    import { storeToRefs } from 'pinia';
    import {onMounted, ref, watch} from "vue";
    import { ElMessage, ElMessageBox} from "element-plus";
    import { useCategoriesStore } from "../store/categoriesStore";
    import {useLanguageStore} from "../store/languageStore";

    const languageStore = useLanguageStore();
    const categoryStore = useCategoriesStore();
    const { optionsCats, franchiseLocale } = storeToRefs(categoryStore);
    const { watcherLang } = storeToRefs( languageStore );
    const propsCascader = {
        multiple: true,
        checkStrictly: true,
    }
    const nameFranchise = ref('');
    const nameRuFranchise = ref('');
    const categoryId = ref([]);
    const categoriesArr = ref([]);

    onMounted(  () => {
        categoryStore.getCategoriesFranchise();
        categoryStore.getLocale();
    });
    watch(() => watcherLang.value, (newLang) => {
        categoryStore.getLocale();
    });
    const handleAddFranchise = () => {
        categoryStore.setCategoryFranchise({
            collection: categoriesArr.value,
            label_en: nameFranchise.value,
            label_ru: nameRuFranchise.value,
        });
        categoriesArr.value = [];
        categoryId.value = [];
        nameFranchise.value = '';
        nameRuFranchise.value = '';
    }
    const handleCategoryChange = (value) => {
        categoriesArr.value = [];
        value.forEach(obj => {
            categoriesArr.value.push(obj[1]);
        })
    }
</script>

<style scoped>

</style>
