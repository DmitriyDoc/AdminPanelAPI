<template>
    <el-row v-if="singleData.filmography">
        <el-col :span="24"><div class="grid-content ep-bg-purple" />
            <h1>{{ singleData.nameActor }}</h1>
        </el-col>
        <el-col :span="4"><div class="grid-content ep-bg-purple" />
            <el-image :src="singleData.info.photo" :fit="cover" style="width: 100%" />
            <div class="mt-1 mb-2">
                <h5>{{singleData.locale.image_type}}</h5>
                <el-radio-group v-model="imageType" size="small">
                    <el-radio value="event">[event]</el-radio>
                    <el-radio value="publicity">[publicity]</el-radio>
                </el-radio-group>
            </div>
            <el-button type="danger" style="width: 100%;" @click="submitSync()">
                {{singleData.locale.sync_imdb}}
            </el-button>
            <div v-if="percentageSync" class="mt-1">
                <el-progress :percentage="percentageSync" :status="statusBar"/>
            </div>
            <ul class="list-group mt-2">
                <li class="list-group-item"><span><strong>{{singleData.locale.birthday}}</strong></span> {{ singleData.info.birthday ?? singleData.locale.empty }}</li>
                <li class="list-group-item"><span><strong>{{singleData.locale.birthday_location}}</strong></span> {{ singleData.birthdayLocation ?? singleData.locale.empty }}</li>
                <li class="list-group-item"><span><strong>{{singleData.locale.died}}</strong></span> {{ singleData.info.died ?? singleData.locale.empty}}</li>
                <li class="list-group-item"><span><strong>{{singleData.locale.died_location}}</strong></span> {{ singleData.dieLocation ?? singleData.locale.empty}}</li>
            </ul>
        </el-col>
        <el-col :span="20" ><div class="grid-content ep-bg-purple-light" />
            <el-tabs v-model="activeTabName" class="demo-tabs m-3" @tab-click="handleClick">
                <template v-for="(occupation, index) in singleData.filmography">
                    <el-tab-pane :label=$t(index) :name=index>
                        <li class="list-group-item">
                            <el-table
                                ref="multipleTableCeleb"
                                :data="occupation"
                                style="width: 100%"
                                @selection-change="handleSelectionChange"
                            >
                                <el-table-column type="selection" width="55" />
                                <el-table-column type="index" label="№" width="50"/>
                                <el-table-column prop="year" :label="singleData.locale.year" width="120" />
                                <el-table-column prop="id" :label="singleData.locale.id_movie" width="120" />
                                <el-table-column prop="title" property="id" :label="singleData.locale.title" width="400">
                                    <template v-slot:default="scope">
                                        <RouterLink :to="{ name: 'showMovie', params: { slug: 'FeatureFilm', id: scope.row.id }}">
                                            {{scope.row.title}}
                                        </RouterLink>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="role" :label="singleData.locale.role" fixed="right" width="200"/>
                            </el-table>
<!--                            <el-button @click="toggleSelectionCeleb()" type="info">Clear selection</el-button>-->
                            <el-button type="danger" @click="handleRemoveItems()" class="mt-3">{{singleData.locale.delete_selection}}</el-button>
                        </li>
                    </el-tab-pane>
                </template>
            </el-tabs>
            <el-collapse v-if="singleData.info.knowfor.length" v-model="activeAccordionTab" class="m-3" accordion>
                <el-collapse-item :title="singleData.locale.known_for" name="1">
                    <div class="p-1 m-1 border bg-light" style="display: flex;">

                        <template v-for="(item, id) in singleData.info.knowfor" >
                            <div style="display: flex; flex-direction: column">
                                <p><strong>{{item.type_film}}</strong></p>
                                <p><em>{{item.original_title}}</em></p>
                                <div style=" margin-right: 10px">
                                    <RouterLink :to="{ name: 'showMovie', params: { slug: item.type_film_slug, id: item.id_movie }}">
                                        <div style="width: 194px; height: 300px; background-color: rgb(243 243 243); margin-right: 10px">
                                            <el-image  v-if="item.poster" :src="item.poster" :fit="cover" style="object-fit: cover;width: 100%; height: 100%;" />
                                        </div>
                                    </RouterLink>
                                    <p>{{ item.title }}</p>
                                </div>
                            </div>
                        </template>
                    </div>
                </el-collapse-item>
            </el-collapse>
            <el-collapse v-model="activeCollapseTab" class="m-3" @change="handleChangeImages" >
                <el-collapse-item :title="singleData.locale.images" name="image" >
                    <template v-if="imagesData.length">
                        <el-table
                            ref="multipleTableImage"
                            :data="imagesData"
                            style="width: 100%"
                            @selection-change="handleSelectImageChange"
                        >
                            <el-table-column type="selection" width="55"/>
                            <el-table-column property="id" label="ID" width="100">
                                <template v-slot:default="scope">
                                    {{scope.row.id}}
                                </template>
                            </el-table-column>
                            <el-table-column property="src" :label="singleData.locale.photo" width="200">
                                <template v-slot:default="scope">
                                    <el-image :src="scope.row.srcset"/>
                                </template>
                            </el-table-column>
                            <el-table-column property="src" :label="singleData.locale.link" show-overflow-tooltip>
                                <template v-slot:default="scope">
                                    <a :href="scope.row.src" target="_blank">{{scope.row.src}}</a>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div style="margin-top: 20px">
                            <el-button @click="toggleSelectImage()" type="info">{{singleData.locale.clear_selection}}</el-button>
                            <el-button @click="toggleRemoveImage()" type="danger">{{singleData.locale.delete_selection}}</el-button>
                            <el-button :type="info" @click="handleImageLoadMore">
                                {{singleData.locale.next_page}}
                                <el-icon class="el-icon--right">
                                    <ArrowRight/>
                                </el-icon>
                            </el-button>
                        </div>
                    </template>
                 </el-collapse-item>
            </el-collapse>
        </el-col>
    </el-row>
    <el-row v-else>
        <el-col><h2>{{$t('not_enough_data')}}</h2></el-col>
    </el-row>
</template>

<script lang="ts" setup>
    import { storeToRefs } from 'pinia';
    import { RouterLink } from 'vue-router'
    import { usePersonsStore } from "../store/personsStore";
    import { useMediaStore } from "../store/mediaStore";
    import { useProgressBarStore } from "../store/progressBarStore";
    import { useLanguageStore } from "../store/languageStore";
    import type { TabsPaneContext } from 'element-plus';
    import { ArrowRight } from '@element-plus/icons-vue'
    import { ref, watch, reactive, onMounted } from "vue";
    import { ElMessage, ElMessageBox, ElTable } from "element-plus";

    const mediaStore = useMediaStore();
    const personsStore = usePersonsStore();
    const progressBarStore = useProgressBarStore();
    const languageStore = useLanguageStore();

    const { singleData, route, error, } = storeToRefs(personsStore);
    const { statusBar, percentageSync } = storeToRefs(progressBarStore);
    const { imagesData, srcListImages, countImg } = storeToRefs(mediaStore);
    const { watcherLang } = storeToRefs( languageStore );


    const activeTabName = ref('actor');
    const activeAccordionTab = ref('1')
    const activeCollapseTab = ref(['1']);

    const multipleTableImage = ref();
    const multipleSelectImage = ref([]);

    const multipleTableCeleb = ref()
    const multipleSelection = ref([])
    const imageType = ref('event');

    watch(() => watcherLang.value, (newLang) => {
        personsStore.showItem();
    });

    onMounted(() => {
        personsStore.showItem();
        percentageSync.value = 0;
    });

    const toggleSelectionCeleb = () => {
        //console.log(multipleTableCeleb.value);
        //multipleTableCeleb.value!.clearSelection();
    }
    const handleRemoveItems = () => {
        if (multipleSelection.value.length){
            let dataRemoveKey = [];
            multipleSelection.value.forEach((item, index) => {
                dataRemoveKey.push(item.id);
            });
            personsStore.removeFilmographyItems(dataRemoveKey,activeTabName.value);
        }
    }
    const handleSelectionChange = (val) => {
        multipleSelection.value = val
        // if (multipleSelection.value.length){
        //     console.log(multipleSelection.value);
        // }
    }
    const handleChangeImages = (val: string[]) => {
        //console.log(val[1]);
        mediaStore.flushState();
        mediaStore.getImages('Celebs')
    }
    const handleImageLoadMore = () => {
        mediaStore.updateImagePageSize('Celebs');
    }

    const handleClick = (tab: TabsPaneContext, event: Event) => {
        //console.log(tab, event)
        multipleSelection.value = [];
    }
    const handleSelectImageChange = (val) => {
        multipleSelectImage.value = [];
        val.filter(function(arr, i){
            multipleSelectImage.value.push(arr.id)
        });
    }

    const toggleSelectImage = () => {
        //console.log(multipleTableImage.value);
        multipleTableImage.value!.clearSelection();
    }
    const toggleRemoveImage = () => {
        if (multipleSelectImage.value.length){
            getUnique(multipleSelectImage.value);
            ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectImage.value.length} pictures will be deleted. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                mediaStore.removeMultipleImages(multipleSelectImage.value,'images');
                multipleTableImage.value!.clearSelection();
                ElMessage({
                    type: 'success',
                    message: 'Delete completed',
                })
            }).catch(() => {
                ElMessage({
                    type: 'info',
                    message: 'Delete canceled',
                })
            })
        } else {
            ElMessage.error('No pictures have been selected');
        }
    }
    const getUnique = (arr) => {
        return arr.filter((el, ind) => ind === arr.indexOf(el));
    };
    const submitSync = () => {
        percentageSync.value = 0;
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            personsStore.syncItem(imageType.value);
            progressBarStore.getSyncCurrentPercentage('syncPersonPercentageBar');
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Sync canceled',
            })
        })
    }

</script>

<style lang="scss" scoped>
    .el-row {
        margin-bottom: 20px;
    }
    .el-row:last-child {
        margin-bottom: 0;
    }
    .el-col {
        border-radius: 4px;
    }

    .grid-content {
        border-radius: 4px;
        min-height: 36px;
    }
    .el-progress :deep(.el-progress__text){
        min-width: 0;
    }
    .image-type {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
    }
</style>
