<template>
    <el-aside width="250px">
        <el-container class="layout-container-demo" style="height: auto">
            <el-scrollbar>
                <el-menu :default-openeds="['1', '2']">
                    <el-sub-menu index="1">
                        <template #title>
                            <el-icon><icon-menu /></el-icon><h3>{{$t('sections')}}</h3>
                        </template>
                        <el-menu-item-group>
                            <template v-for="(item, index) in sections">
                                <RouterLink :to="'/section/'+item.value">
                                    <el-menu-item :index="'2-'+index" class="section-color-line" :style="{borderLeftColor: item.value}">
                                        {{item.title_ru??item.title_en}}
                                    </el-menu-item>
                                </RouterLink>
                            </template>
                        </el-menu-item-group>
                    </el-sub-menu>
                    <el-sub-menu index="2">
                        <template #title>
                            <el-icon><setting /></el-icon><h3>{{$t('categories')}}</h3>
                        </template>
                        <el-menu-item-group>
                            <template #title><h4>{{$t('franchise')}}</h4></template>
                            <RouterLink to="/categories/franchise/"><el-menu-item index="3-1">{{$t('add_franchise')}}</el-menu-item></RouterLink>
                            <RouterLink to="/categories/franchise/list"><el-menu-item index="3-1">{{$t('list_franchises')}}</el-menu-item></RouterLink>
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <template #title><h4>{{$t('collection')}}</h4></template>
                            <RouterLink to="/categories/collection/"><el-menu-item index="3-1">{{$t('add_collection')}}</el-menu-item></RouterLink>
                            <RouterLink to="/categories/collection/list"><el-menu-item index="3-1">{{$t('list_collections')}}</el-menu-item></RouterLink>
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <template #title><h4>{{$t('tags')}}</h4></template>
                            <RouterLink to="/categories/tags/list"><el-menu-item index="3-1">{{$t('list_tags')}}</el-menu-item></RouterLink>
                        </el-menu-item-group>
                    </el-sub-menu>
                </el-menu>
            </el-scrollbar>
        </el-container>
    </el-aside>
</template>

<script setup>
    import {storeToRefs} from "pinia";
    import { RouterLink } from 'vue-router'
    import { useSectionStore } from "../store/sectionsStore";
    import { useLanguageStore } from "../store/languageStore";
    import { onMounted, watch } from "vue";
    const sectionStore = useSectionStore();
    const languageStore = useLanguageStore();
    const { watcherLang } = storeToRefs( languageStore );
    const { sections } = storeToRefs(sectionStore);

    watch(() => watcherLang.value, (newLang) => {
        sectionStore.getSections();
    });
    onMounted(  () => {
        sectionStore.getSections();
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
    .section-color-line {
        border-left-color: gray;
        border-left-width: thick;
        border-left-style: solid;
    }
</style>
