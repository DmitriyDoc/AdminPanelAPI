<template>
    <el-aside width="250px">
        <el-container class="layout-container-demo" style="height: auto">
            <el-scrollbar>
                <el-menu :default-openeds="['1', '2']">
                    <el-sub-menu index="1">
                        <template #title>
                            <el-icon><message /></el-icon><h5>Types</h5>
                        </template>
                        <el-menu-item-group>
                            <template #title><h6>Movies List Info:  </h6></template>
                            <RouterLink to="/movies/FeatureFilm"><el-menu-item index="1-1">Feature Film</el-menu-item></RouterLink>
                            <RouterLink to="/movies/MiniSeries"><el-menu-item index="1-1">Mini Series</el-menu-item></RouterLink>
                            <RouterLink to="/movies/ShortFilm"><el-menu-item index="1-1">Short Film</el-menu-item></RouterLink>
                            <RouterLink to="/movies/TvMovie"><el-menu-item index="1-1">Tv Movie</el-menu-item></RouterLink>
                            <RouterLink to="/movies/TvSeries"><el-menu-item index="1-1">Tv Series</el-menu-item></RouterLink>
                            <RouterLink to="/movies/TvShort"><el-menu-item index="1-1">Tv Short</el-menu-item></RouterLink>
                            <RouterLink to="/movies/TvSpecial"><el-menu-item index="1-1">Tv Special</el-menu-item></RouterLink>
                            <RouterLink to="/movies/Video"><el-menu-item index="1-1">Video</el-menu-item></RouterLink>
                        </el-menu-item-group>
<!--                        <el-menu-item-group title="Group 2">-->
<!--                            <el-menu-item index="1-3">Option 3</el-menu-item>-->
<!--                        </el-menu-item-group>-->
<!--                        <el-sub-menu index="1-4">-->
<!--                            <template #title>Option4</template>-->
<!--                            <el-menu-item index="1-4-1">Option 4-1</el-menu-item>-->
<!--                        </el-sub-menu>-->
                    </el-sub-menu>
                    <el-sub-menu index="2">
                        <template #title>
                            <el-icon><icon-menu /></el-icon><h5>Sections</h5>
                        </template>
                        <el-menu-item-group>
                            <template v-for="(item, index) in sections">
                                <RouterLink :to="'/section/'+item.value">
                                    <el-menu-item :index="'2-'+index" class="section-color-line" :style="{borderLeftColor: item.value}">
                                        {{item.title}}
                                    </el-menu-item>
                                </RouterLink>
                            </template>
                        </el-menu-item-group>
<!--                        <el-menu-item-group title="Group 2">-->
<!--                            <el-menu-item index="2-3">Option 3</el-menu-item>-->
<!--                        </el-menu-item-group>-->
<!--                        <el-sub-menu index="2-4">-->
<!--                            <template #title>Option 4</template>-->
<!--                            <el-menu-item index="2-4-1">Option 4-1</el-menu-item>-->
<!--                        </el-sub-menu>-->
                    </el-sub-menu>
                    <el-sub-menu index="3">
                        <template #title>
                            <el-icon><setting /></el-icon><h5>Categories</h5>
                        </template>
                        <el-menu-item-group>
                            <template #title><h6>Franchise:</h6></template>
                            <RouterLink to="/categories/franchise/"><el-menu-item index="3-1">Add Franchise</el-menu-item></RouterLink>
                            <RouterLink to="/categories/franchise/list"><el-menu-item index="3-1">List Franchise</el-menu-item></RouterLink>
<!--                            <el-menu-item index="3-2">Option 2</el-menu-item>-->
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <template #title><h6>Collection:</h6></template>
                            <RouterLink to="/categories/collection/"><el-menu-item index="3-1">Add Collection</el-menu-item></RouterLink>
                            <RouterLink to="/categories/collection/list"><el-menu-item index="3-1">List Collection</el-menu-item></RouterLink>
<!--                            <el-menu-item index="3-2">Option 2</el-menu-item>-->
                        </el-menu-item-group>
                        <el-menu-item-group>
                            <template #title><h6>Tags:</h6></template>
                            <RouterLink to="/categories/tags/list"><el-menu-item index="3-1">List Tags</el-menu-item></RouterLink>
                        </el-menu-item-group>
<!--                        <el-menu-item-group title="Group 2">-->
<!--                            <el-menu-item index="3-3">Option 3</el-menu-item>-->
<!--                        </el-menu-item-group>-->
<!--                        <el-sub-menu index="3-4">-->
<!--                            <template #title>Option 4</template>-->
<!--                            <el-menu-item index="3-4-1">Option 4-1</el-menu-item>-->
<!--                        </el-sub-menu>-->
                    </el-sub-menu>
                </el-menu>
            </el-scrollbar>
        </el-container>
    </el-aside>
</template>

<script setup>
    import {storeToRefs} from "pinia";
    import { RouterLink } from 'vue-router'
    import {useSectionStore} from "../store/sectionsStore";
    const sectionStore = useSectionStore();
    const { sections } = storeToRefs(sectionStore);

    sectionStore.getSections();
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
