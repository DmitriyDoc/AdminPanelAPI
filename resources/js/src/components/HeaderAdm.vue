<template>
    <el-menu
        :default-active="activeIndex"
        class="el-menu-demo"
        mode="horizontal"
        background-color="#545c64"
        text-color="#fff"
        active-text-color="#ffd04b"
        @select="handleSelect"
    >
        <el-menu-item index="1"><router-link to="/dashboard">{{$t('dashboard')}}</router-link></el-menu-item>
        <el-menu-item index="2"><router-link to="/parser">{{$t('parser')}}</router-link></el-menu-item>
        <el-menu-item index="3"><router-link to="/movies">{{$t('movies')}}</router-link></el-menu-item>
        <el-menu-item index="4"><router-link to="/persons">{{$t('persons')}}</router-link></el-menu-item>
        <el-menu-item index="5"><router-link to="/export"> {{$t('export')}}</router-link></el-menu-item>

        <LangSwitchAdm />
    </el-menu>
</template>

<script setup>
    import { ref } from 'vue'
    import LangSwitchAdm from "../components/LangSwitchAdm.vue";

    const activeIndex = ref('1')
    const handleSelect = (key, keyPath) => {
        //console.log(key, keyPath)
    }
</script>
<style scoped>
</style>


