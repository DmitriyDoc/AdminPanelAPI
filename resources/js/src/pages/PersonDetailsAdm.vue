<template>
    <el-row v-if="singleData.filmography">
        <el-col :span="24"><div class="grid-content ep-bg-purple" />
            <h1>{{ singleData.nameActor }}</h1>
        </el-col>
        <el-col>
            <el-button type="success" link >
                <RouterLink :to="{ name: 'editPerson', params: { slug: route.params.slug, id:  route.params.id }}">
                    <el-button  type="danger">Edit</el-button>
                </RouterLink>
            </el-button>
        </el-col>
        <el-col :span="4"><div class="grid-content ep-bg-purple" />
            <el-image :src="singleData.photo" :fit="cover" style="width: 100%" />
            <ul class="list-group">
                <li class="list-group-item"><span><strong>Birthday: </strong></span>{{ singleData.birthday ?? 'empty' }}</li>
                <li class="list-group-item"><span><strong>Birthday Location: </strong></span>{{ singleData.birthdayLocation ?? 'empty' }}</li>
                <li class="list-group-item" v-if="singleData.died"><span><strong>Died: </strong></span>{{ singleData.died }}</li>
                <li class="list-group-item" v-if="singleData.dieLocation"><span><strong>Die Location: </strong></span>{{ singleData.dieLocation }}</li>
            </ul>
        </el-col>
        <el-col :span="20" ><div class="grid-content ep-bg-purple-light" />
            <el-tabs v-model="activeTabName" class="demo-tabs m-3" @tab-click="handleClick">
                <template v-for="(occupation, index) in singleData.filmography">
                    <el-tab-pane :label=index :name=index>
                        <li class="list-group-item">
                            <el-table
                                :data="occupation"
                                style="width: 100%"
                            >
                                <el-table-column type="index" label="№"/>
                                <el-table-column prop="year" label="Year" width="120" />
                                <el-table-column prop="id" label="Movie ID" width="120" />
                                <el-table-column prop="title" property="id" label="Title" width="400">
                                    <template v-slot:default="scope">
                                        <RouterLink :to="{ name: 'showmovie', params: { slug: 'FeatureFilm', id: scope.row.id }}">
                                            {{scope.row.title}}
                                        </RouterLink>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="role" label="Role" fixed="right" width="200"/>
                            </el-table>
                        </li>
                    </el-tab-pane>
                </template>
            </el-tabs>
            <el-collapse v-if="singleData.knowfor.length" v-model="activeAccordionTab" class="m-3" accordion>
                <el-collapse-item title="Known For" name="1">
                    <div class="p-1 m-1 border bg-light" style="display: flex;">
                        <template v-for="(item, id) in singleData.knowfor" >
                            <div v-if="item.poster.length" style="display: flex; flex-direction: column">
                                <strong>{{item.type_film}}</strong>
                                <div style="width: 194px; height: 300px; background-color: rgb(243 243 243); margin-right: 10px">
                                    <el-image  v-if="item.poster" :src="item.poster[0].src" :fit="cover" style="object-fit: cover;width: 100%; height: 100%;" />
                                </div>
                                <div style="width: 194px; margin-right: 10px">
                                    <RouterLink :to="{ name: 'showmovie', params: { slug: item.type_film, id: item.id_movie }}">
                                        {{ item.title }}
                                    </RouterLink>
                                </div>
                            </div>
                        </template>
                    </div>
                </el-collapse-item>
            </el-collapse>
            <el-collapse v-model="activeCollapseTab" class="m-3" @change="handleChange" >
                <el-collapse-item title="Images" name="image" >
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
                    <div v-if="countImg" class="el-image" style="width: 150px; height: 150px; text-align: center;" ><el-button :type="info" link @click="handleImageLoadMore" style="padding-top: 55px"> Next Page <el-icon class="el-icon--right" ><ArrowRight /></el-icon></el-button></div>
                </el-collapse-item>
            </el-collapse>
        </el-col>
    </el-row>
    <el-row v-else>
        <el-col> <h2>Not Enough Data ...</h2> </el-col>
    </el-row>
</template>

<script lang="ts" setup>
    import { storeToRefs } from 'pinia';
    import { RouterLink } from 'vue-router'
    import { usePersonsStore } from "../store/personsStore";
    import { useMediaStore } from "../store/mediaStore";
    import { TabsPaneContext, ElTable } from 'element-plus';
    import { ArrowRight } from '@element-plus/icons-vue'
    import { ref } from "vue";

    const moviesStore = usePersonsStore();
    const mediaStore = useMediaStore();
    const { singleData, route, error, } = storeToRefs(moviesStore);
    const { imagesData, srcListImages, countImg } = storeToRefs(mediaStore);

    const activeTabName = ref('actor');
    const activeAccordionTab = ref('1')
    const activeCollapseTab = ref(['1']);

    const handleChange = (val: string[]) => {
        //console.log(val[1]);
        mediaStore.flushState();
        mediaStore.getImages()
    }
    const handleImageLoadMore = () => {
        mediaStore.updateImagePageSize();
    }

    const handleClick = (tab: TabsPaneContext, event: Event) => {
        //console.log(tab, event)
    }
    moviesStore.showItem();
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
