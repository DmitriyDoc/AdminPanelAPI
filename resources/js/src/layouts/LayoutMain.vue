<template>
    <el-container>
        <HeaderUser class="h-full px-4 flex-between"/>
        <el-header>
            <HeaderAdm />
        </el-header>
        <el-container>
            <AsideAdm></AsideAdm>
            <el-container>
                <main class="h-[calc(100%-80px)] w-full relative">
                    <PageLoader :loading="asyncLoading" />

                    <div class="container mx-auto px-4 lg:px-0 h-full">
                        <RouterView v-slot="{ Component }">
                            <template v-if="Component">
                                <Suspense
                                    @pending="asyncLoading = true"
                                    @resolve="asyncLoading = false"
                                >
                                    <component :is="Component"></component>
                                    <template #fallback>
                                        <SuspenseFallback />
                                    </template>
                                </Suspense>
                            </template>
                        </RouterView>
                    </div>
                    <el-footer class="container mt-3">&copy;2025 Spectrum Admin Panel</el-footer>
                </main>
            </el-container>

        </el-container>
    </el-container>
</template>

<script setup>
    import { ref } from 'vue';
    import { Menu as IconMenu, Message, Setting } from '@element-plus/icons-vue'
    import HeaderAdm from "../components/HeaderAdm.vue";
    import AsideAdm from "../components/AsideAdm.vue";
    import HeaderUser from "../components/HeaderUser.vue";
    import PageLoader from './PageLoader.vue';

    const asyncLoading = ref(false);
</script>

<style scoped>

</style>
