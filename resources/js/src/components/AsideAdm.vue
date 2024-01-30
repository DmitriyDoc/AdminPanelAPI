<template>
    <el-aside width="200px">
        <el-container class="layout-container-demo" style="height: auto">
            <el-scrollbar>
                <el-menu :default-openeds="['1', '3']">
                    <el-sub-menu index="1">
                        <template #title>
                            <el-icon><message /></el-icon>Navigator One
                        </template>
                        <el-menu-item-group>
                            <template #title>Movies List Info:</template>
                            <RouterLink to="/movies/FeatureFilm"><el-menu-item index="1-1">Feature Film</el-menu-item></RouterLink>
                            <RouterLink to="/movies/MiniSeries"><el-menu-item index="1-1">Mini Series</el-menu-item></RouterLink>
                            <RouterLink to="/movies/ShortFilm"><el-menu-item index="1-1">Short Film</el-menu-item></RouterLink>
                            <RouterLink to="/movies/TvMovie"><el-menu-item index="1-1">Tv Movie</el-menu-item></RouterLink>
                            <RouterLink to="/movies/TvSeries"><el-menu-item index="1-1">Tv Series</el-menu-item></RouterLink>
                            <RouterLink to="/movies/TvShort"><el-menu-item index="1-1">Tv Short</el-menu-item></RouterLink>
                            <RouterLink to="/movies/TvSpecial"><el-menu-item index="1-1">Tv Special</el-menu-item></RouterLink>
                            <RouterLink to="/movies/Video"><el-menu-item index="1-1">Video</el-menu-item></RouterLink>
                        </el-menu-item-group>
                        <el-menu-item-group title="Group 2">
                            <el-menu-item index="1-3">Option 3</el-menu-item>
                        </el-menu-item-group>
                        <el-sub-menu index="1-4">
                            <template #title>Option4</template>
                            <el-menu-item index="1-4-1">Option 4-1</el-menu-item>
                        </el-sub-menu>
                    </el-sub-menu>
                    <el-sub-menu index="2">
                        <template #title>
                            <el-icon><icon-menu /></el-icon>Navigator Two
                        </template>
                        <el-menu-item-group>
                            <template #title>Group 1</template>
                            <el-menu-item index="2-1">Option 1</el-menu-item>
                            <el-menu-item index="2-2">Option 2</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group title="Group 2">
                            <el-menu-item index="2-3">Option 3</el-menu-item>
                        </el-menu-item-group>
                        <el-sub-menu index="2-4">
                            <template #title>Option 4</template>
                            <el-menu-item index="2-4-1">Option 4-1</el-menu-item>
                        </el-sub-menu>
                    </el-sub-menu>
                    <el-sub-menu index="3">
                        <template #title>
                            <el-icon><setting /></el-icon>Navigator Three
                        </template>
                        <el-menu-item-group>
                            <template #title>Group 1</template>
                            <el-menu-item index="3-1">Option 1</el-menu-item>
                            <el-menu-item index="3-2">Option 2</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group title="Group 2">
                            <el-menu-item index="3-3">Option 3</el-menu-item>
                        </el-menu-item-group>
                        <el-sub-menu index="3-4">
                            <template #title>Option 4</template>
                            <el-menu-item index="3-4-1">Option 4-1</el-menu-item>
                        </el-sub-menu>
                    </el-sub-menu>
                </el-menu>
            </el-scrollbar>
        </el-container>
    </el-aside>
</template>

<script>
    import { RouterLink } from 'vue-router'
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
