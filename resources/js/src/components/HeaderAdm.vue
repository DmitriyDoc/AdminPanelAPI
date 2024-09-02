<template>
    <el-menu
        :default-active="activeIndex2"
        class="el-menu-demo"
        mode="horizontal"
        background-color="#545c64"
        text-color="#fff"
        active-text-color="#ffd04b"
        @select="handleSelect"
    >
        <el-menu-item index="1"><router-link to="/">Dashboard</router-link></el-menu-item>
<!--        <el-menu-item  index="2"><router-link to="/parser">Parser</router-link></el-menu-item>-->
        <el-menu-item  index="3"><router-link to="/movies/FeatureFilm">Movies Info</router-link></el-menu-item>
        <el-menu-item  index="4"><router-link to="/persons/Celebs">Celebs Info</router-link></el-menu-item>
        <el-menu-item  index="5"><router-link to="/parser">Parser</router-link></el-menu-item>
    </el-menu>
</template>

<script setup>
    import { ref } from 'vue'

    const activeIndex = ref('1')
    const activeIndex2 = ref('1')
    const handleSelect = (key, keyPath) => {
        console.log(key, keyPath)
    }
</script>

