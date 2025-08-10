<template>
    <el-radio-group v-model="currentLang" size="small" @change="handleChangeLang(currentLang)" >
        <el-radio-button label="RU" value="ru" />
        <el-radio-button label="EN" value="en" />
    </el-radio-group>
</template>

<script setup>
    import { useLanguageStore } from "../store/languageStore";
    import {storeToRefs} from "pinia";

    const languageStore = useLanguageStore();
    const { currentLang, loader, error } = storeToRefs(languageStore);

    const handleChangeLang = (lang) => {
        languageStore.changeLang(lang);
    }
</script>

<style scoped lang="scss">
    .radio-position {
        display: flex;
        margin-left: auto;
        margin-right: 15px;
    }
</style>
