<template>
    <el-row v-if="singleData.filmography">
        <el-col :span="24"><div class="grid-content ep-bg-purple" />
            <h1>{{ singleData.nameActor }}</h1>
        </el-col>
        <el-col :span="4"><div class="grid-content ep-bg-purple" />
            <el-image :src="singleData.photo" :fit="cover" style="width: 100%" />
            <el-button type="danger" style="width: 100%;" @click="submitSync()">
                Sync with IMDB
            </el-button>
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
                            <template v-for="(item, id) in occupation">
                                <div class="p-1 m-1 border bg-light">
                                    <strong>{{ item.year }}</strong>
                                    <RouterLink :to="{ name: 'showmovie', params: { slug: 'all', id: id }}">
                                        {{ item.title }}
                                    </RouterLink>
                                    <em>{{ item.role  }}</em>
                                </div>
                            </template>
                        </li>
                    </el-tab-pane>
                </template>
            </el-tabs>
            <el-collapse v-if="singleData.knowfor.length" v-model="activeAccordionTab" class="m-3" accordion>
                <el-collapse-item title="Known For" name="1">
                    <div class="p-1 m-1 border bg-light" style="display: flex;">
                        <template v-for="(item, id) in singleData.knowfor" >
                            <div style="display: flex; flex-direction: column">
                                <strong>{{item.type_film}}</strong>
                                <div style="width: 194px; height: 300px; background-color: rgb(243 243 243); margin-right: 10px">
                                    <el-image  v-if="item.poster" :src="item.poster.src" :fit="cover" style="object-fit: cover;width: 100%; height: 100%;" />
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
                            <el-table-column property="src" label="Preview" width="120">
                                <template v-slot:default="scope">
                                    <el-image :src="scope.row.src"/>
                                </template>
                            </el-table-column>
                            <el-table-column property="src" label="Link" show-overflow-tooltip>
                                <template v-slot:default="scope">
                                    <a :href="scope.row.src" target="_blank">{{scope.row.src}}</a>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div style="margin-top: 20px">
                            <el-button @click="toggleSelectImage()" type="info">Clear selection</el-button>
                            <el-button @click="toggleRemoveImage()" type="danger">Delete selection</el-button>
                            <template v-if="countImg" >
                                <el-button :type="info" @click="handleImageLoadMore" > Next Page
                                    <el-icon class="el-icon--right">
                                        <ArrowRight/>
                                    </el-icon>
                                </el-button>
                            </template>
                        </div>
                    </template>
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
    import type { TabsPaneContext } from 'element-plus';
    import { ArrowRight } from '@element-plus/icons-vue'
    import { ref, watch, reactive} from "vue";
    import {ElMessage, ElMessageBox} from "element-plus";

    const moviesStore = usePersonsStore();
    const mediaStore = useMediaStore();
    const personsStore = usePersonsStore();
    const { singleData, route, error, } = storeToRefs(moviesStore);
    const { imagesData, srcListImages, countImg } = storeToRefs(mediaStore);

    const activeTabName = ref('actor');
    const activeAccordionTab = ref('1')
    const activeCollapseTab = ref(['1']);

    const multipleTableImage = ref();
    const multipleSelectImage = ref([]);

    const handleChange = (val: string[]) => {
        //console.log(val[1]);
        mediaStore.flushState();
        mediaStore.getImages()
    }
    const handleImageLoadMore = () => {
        mediaStore.updateImagePageSize();
    }

    // const handleClick = (tab: TabsPaneContext, event: Event) => {
    //     //console.log(tab, event)
    // }
    const handleSelectImageChange = (val?: []) => {
        multipleSelectImage.value = [];
        val.filter(function(arr, i){
            multipleSelectImage.value.push(arr.id)
        });
    }

    const toggleSelectImage = (rows?: []) => {
        if (rows) {
            rows.forEach((row) => {
                multipleTableImage.value!.toggleRowSelection(row, undefined);
            })
        } else {
            multipleTableImage.value!.clearSelection();
        }

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
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            personsStore.syncItem();
            console.log('submit sync person!');

            ElMessage({
                type: 'success',
                message: 'Sync with IMDB completed',
            })
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
</style>
