<template>
    <div class="common-layout">
        <el-container>
            <el-header>
                <HeaderAdm></HeaderAdm>
            </el-header>
            <el-container>
               <AsideAdm></AsideAdm>
                <el-container>
                    <el-main>
                        <router-view></router-view>
                    </el-main>
                    <el-footer>&copy;2024 Spectrum Admin Panel</el-footer>
                </el-container>
            </el-container>
        </el-container>
    </div>
</template>
<script setup>
    import { ref, defineComponent } from 'vue'
    import { Menu as IconMenu, Message, Setting } from '@element-plus/icons-vue'
    import HeaderAdm from "./components/HeaderAdm.vue";
    import AsideAdm from "./components/AsideAdm.vue";
</script>

<style lang="scss" scoped>

</style>
