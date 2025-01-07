<template>
    <h3 class="text-center mt-3 mb-3">{{$t('add_collection')}}</h3>
    <el-row>
        <el-col :span="6">
            <div class="mt-3">
                <h5>{{collectionLocale.select_section}}</h5>
                <el-cascader  v-model="categoryId" :placeholder="collectionLocale.select" :props="propsCascader" :options="optionsCats" @change="handleCategoryChange"  style="min-width: 100%;">
                    <template #default="{ data }">
                        <span>{{ data.label }}</span>
                    </template>
                </el-cascader>
            </div>
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>{{collectionLocale.name_of_the_new_collection}}</span>
                    <el-input
                        v-model="nameCollection"
                        maxlength="50"
                        :placeholder="collectionLocale.enter_name"
                        show-word-limit
                        type="text"
                    />
                </div>
            </div>
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>{{collectionLocale.ru_name_of_the_new_collection}}</span>
                    <el-input
                        v-model="nameRuCollection"
                        maxlength="50"
                        :placeholder="collectionLocale.enter_name"
                        show-word-limit
                        type="text"
                    />
                </div>
            </div>
            <div>
                <button @click="handleAddFranchise" class="btn btn-primary">{{collectionLocale.btn_add_new}}</button>
            </div>
        </el-col>
    </el-row>

</template>

<script setup>
    import { storeToRefs } from 'pinia';
    import { onMounted, ref, watch } from "vue";
    import { ElMessage, ElMessageBox } from "element-plus";
    import { useCategoriesStore } from "../store/categoriesStore";
    import { useLanguageStore } from "../store/languageStore";

    const languageStore = useLanguageStore();
    const categoryStore = useCategoriesStore();
    const { optionsCats, collectionLocale } = storeToRefs(categoryStore);
    const { watcherLang } = storeToRefs(languageStore);

    const nameCollection = ref('');
    const nameRuCollection = ref('');
    const categoryId = ref(null);
    const propsCascader = {
        checkStrictly: true,
    }

    onMounted(  () => {
        categoryStore.getCategoriesCollection();
        categoryStore.getLocale();
    });
    watch(() => watcherLang.value, (newLang) => {
        categoryStore.getLocale();
    });
    const handleAddFranchise = () => {
        categoryStore.setCategoryCollection({
            label_en: nameCollection.value,
            label_ru: nameRuCollection.value,
            category_id: categoryId.value,
        });
        categoryId.value = null;
        nameCollection.value = '';
        nameRuCollection.value = '';
    }
    const handleCategoryChange = (value) => {
        for (var el of optionsCats.value) {
            if (value[0] === el.value){
                categoryId.value = el.id;
            }
        }
    }
</script>

<style scoped>

</style>
