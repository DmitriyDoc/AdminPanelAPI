<template >
    <el-row v-if="singleData.filmography">
        <el-col :span="24"><div class="grid-content ep-bg-purple" />
            <h1>{{ singleData.nameActor }}</h1>
        </el-col>
        <el-col>
            <el-button type="success" link >
                <RouterLink :to="{ name: 'editPerson', params: { slug: route.params.slug, id:  route.params.id }}">
                    <el-button  type="danger">{{singleData.locale.edit}}</el-button>
                </RouterLink>
            </el-button>
        </el-col>
        <el-col :span="4"><div class="grid-content ep-bg-purple" />
            <el-image :src="singleData.info.photo" :fit="cover" style="width: 100%" />
            <ul class="list-group">
                <li class="list-group-item"><span><strong>{{singleData.locale.birthday}}</strong></span> {{ singleData.info.birthday ?? singleData.locale.empty }}</li>
                <li class="list-group-item"><span><strong>{{singleData.locale.birthday_location}}</strong></span> {{ singleData.birthdayLocation ?? singleData.locale.empty }}</li>
                <li class="list-group-item"><span><strong>{{singleData.locale.died}}</strong></span> {{ singleData.info.died ?? singleData.locale.empty}}</li>
                <li class="list-group-item"><span><strong>{{singleData.locale.died_location}}</strong></span> {{ singleData.dieLocation ?? singleData.locale.empty}}</li>
            </ul>
        </el-col>
        <el-col :span="20" ><div class="grid-content ep-bg-purple-light" />
            <el-tabs v-model="activeTabName" class="demo-tabs m-3" @tab-click="handleClick">
                <template v-for="(occupation, index) in singleData.filmography">
                    <el-tab-pane :label=$t(index) :name="index">
                        <li class="list-group-item">
                            <el-table
                                :data="occupation"
                                style="width: 100%"
                            >
                                <el-table-column type="index" label="№"/>
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
                        </li>
                    </el-tab-pane>
                </template>
            </el-tabs>
            <el-collapse v-if="singleData.info.knowfor.length" v-model="activeAccordionTab" class="m-3" accordion>
                <el-collapse-item :title="singleData.locale.known_for" name="1">
                    <div class="p-1 m-1 border bg-light" style="display: flex;">
                        <template v-for="(item, id) in singleData.info.knowfor" >
                            <div v-if="item.poster.length" style="display: flex; flex-direction: column">
                                <strong>{{item.type_film}}</strong>
                                <div style="width: 194px; height: 300px; background-color: rgb(243 243 243); margin-right: 10px">
                                    <el-image  v-if="item.poster" :src="item.poster[0].src" :fit="cover" style="object-fit: cover;width: 100%; height: 100%;" />
                                </div>
                                <div style="width: 194px; margin-right: 10px">
                                    <RouterLink :to="{ name: 'showMovie', params: { slug: item.type_film, id: item.id_movie }}">
                                        {{ item.title }}
                                    </RouterLink>
                                </div>
                            </div>
                        </template>
                    </div>
                </el-collapse-item>
            </el-collapse>
            <el-collapse v-model="activeCollapseTab" class="m-3" @change="handleChangeImages" >
                <el-collapse-item :title="singleData.locale.images" name="image" >
                    <template v-for="(image, index) in imagesData" :key="index">
                        <el-image
                            style="width: 150px; height: 150px; margin: 5px"
                            :srcset="image.srcset"
                            :zoom-rate="1.2"
                            :max-scale="7"
                            :min-scale="0.2"
                            :preview-src-list="srcListImages"
                            :initial-index="index"
                            fit="cover"
                        />
                    </template>

                    <div v-if="countImg">
                        <el-button @click="handleImageLoadMore" :type="info">{{singleData.locale.next_page}}
                            <el-icon class="el-icon--right">
                                <ArrowRight/>
                            </el-icon>
                        </el-button>
                    </div>
                </el-collapse-item>
            </el-collapse>
        </el-col>
    </el-row>
    <template v-else>
        <el-col><h2>{{$t('not_enough_data')}}</h2></el-col>
    </template>
</template>

<script lang="ts" setup>
    import { storeToRefs } from 'pinia';
    import { RouterLink } from 'vue-router'
    import { usePersonsStore } from "../store/personsStore";
    import { useMediaStore } from "../store/mediaStore";
    import { useLanguageStore } from "../store/languageStore";
    import { TabsPaneContext, ElTable } from 'element-plus';
    import { ArrowRight } from '@element-plus/icons-vue'
    import { onMounted, ref, watch } from "vue";

    const personsStore = usePersonsStore();
    const mediaStore = useMediaStore();
    const languageStore = useLanguageStore();
    const { singleData,locale, route, error, } = storeToRefs(personsStore);
    const { imagesData, srcListImages, countImg } = storeToRefs(mediaStore);
    const { watcherLang } = storeToRefs( languageStore );

    const activeTabName = ref('actor');
    const activeAccordionTab = ref('1')
    const activeCollapseTab = ref(['1']);

    watch(() => watcherLang.value, (newLang) => {
        personsStore.showItem();
    });

    onMounted(() => {
        personsStore.showItem();
    });

    const handleChangeImages = (val: string[]) => {
        //console.log(val[1]);
        mediaStore.flushState();
        mediaStore.getImages('Celebs')
    }
    const handleImageLoadMore = () => {
        mediaStore.updateImagePageSize();
    }

    const handleClick = (tab: TabsPaneContext, event: Event) => {
        //console.log(tab, event)
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
</style>
