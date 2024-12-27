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
        <el-menu-item index="1"><router-link to="/dashboard">Dashboard</router-link></el-menu-item>
        <el-menu-item  index="2"><router-link to="/parser">Parser</router-link></el-menu-item>
        <el-menu-item  index="3"><router-link to="/movies/FeatureFilm">Movies Info</router-link></el-menu-item>
        <el-menu-item  index="4"><router-link to="/persons/Celebs">Celebs Info</router-link></el-menu-item>
<!--        <el-menu-item  index="5"><router-link to="/parser">Parser</router-link></el-menu-item>-->

        <el-radio-group v-model="radio" size="small" @change="changeLang(radio)">
            <el-radio-button label="RU" value="ru" />
            <el-radio-button label="EN" value="en" />
        </el-radio-group>

    </el-menu>

</template>

<script setup>
    import { ref } from 'vue'
    import axios from "axios";
    import { loadLanguageAsync } from "laravel-vue-i18n";

    const activeIndex = ref('1')
    const activeIndex2 = ref('2')
    const radio = ref('ru');

    const handleSelect = (key, keyPath) => {
        console.log(key, keyPath)
    }

    const changeLang = (lang) => {
        loadLanguageAsync(lang);
        axios.get('/locale'+ '?lang=' + lang
        ).then((response) => {
            console.log(response);
        });
    }
</script>
<style scoped>
    .radio-position {
        display: flex;
        margin-left: auto;
        margin-right: 15px;
    }
</style>


