<template>
    <el-row v-if="singleData.genres && singleData.cast">
        <el-col :span="24">
            <h1>{{  singleData.original_title }}</h1>
        </el-col>
        <el-col :span="4">
            <el-image :src="singleData.poster" :fit="cover" />
            <el-button type="danger" style="width: 100%;" @click="submitSync()">
                Sync with IMDB
            </el-button>
        </el-col>
        <el-col :span="20">
            <el-tabs v-model="activeTabName" class="demo-tabs m-3" @tab-click="handleClick">
                <el-tab-pane label="Genres" name="first">
                    <li class="list-group-item">
                        <template v-for="(genre, index) in singleData.genres">
                            <div class="p-1 m-1 border bg-light"> {{ genre }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane label="Countries" name="second">
                    <li class="list-group-item">
                        <template v-for="(country, index) in singleData.countries">
                            <div class="p-1 m-1 border bg-light"> {{ country }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane label="Companies" name="third">
                    <li class="list-group-item">
                        <template v-for="(company, index) in singleData.companies">
                            <div class="p-1 m-1 border bg-light"> {{ company }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane label="Directors" name="four">
                    <li class="list-group-item">
                        <template v-for="(director, index) in singleData.directors">
                            <template v-for="(role, name) in director">
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showperson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{name}}</strong>
                                    </RouterLink>
                                    <em>{{role}}</em>
                                </div>
                            </template>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane label="Writers" name="five">
                    <li class="list-group-item">
                        <template v-for="(writer, index) in singleData.writers">
                            <template v-for="(role, name) in writer">
                                <div class="p-1 m-1 border bg-light">
                                    <strong>{{name}}</strong><em>{{role}}</em>
                                </div>
                            </template>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane label="Cast" name="six">
                    <li class="list-group-item">
                        <template v-for="(actor, index) in singleData.cast">
                            <template v-for="(role, name) in actor">
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showperson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{name}}</strong>
                                    </RouterLink>
                                    <em>{{role}}</em>
                                </div>
                            </template>
                        </template>
                    </li>
                </el-tab-pane>
            </el-tabs>
            <el-collapse v-model="activeCollapseTab" class="m-3" @change="handleChange">
                <el-collapse-item title="Images" name="image">
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
                <el-collapse-item title="Posters" name="poster">
                    <template v-if="postersData.length">
                        <el-table
                            ref="multipleTablePoster"
                            :data="postersData"
                            style="width: 100%"
                            @selection-change="handleSelectPosterChange"
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
                            <el-button @click="toggleSelectPoster()" type="info">Clear selection</el-button>
                            <el-button @click="toggleRemovePoster()" type="danger">Delete selection</el-button>
                            <template v-if="countPoster" >
                                <el-button :type="info" @click="handlePosterLoadMore" > Next Page
                                    <el-icon class="el-icon--right">
                                        <ArrowRight/>
                                    </el-icon>
                                </el-button>
                            </template>
                        </div>
                    </template>
                </el-collapse-item>
            </el-collapse>
            <el-form
                ref="ruleFormRef"
                :model="ruleForm"
                label-width="120px"
                class="demo-ruleForm"
                :size="formSize"
                status-icon
            >
                <el-form-item label="Title:" prop="title">
                    <el-input v-model="ruleForm.title" maxlength="150" show-word-limit />
                </el-form-item>

                <el-form-item label="Original Title:"  prop="title_oiginal">
                    <el-input v-model="ruleForm.original_title" maxlength="150" show-word-limit />
                </el-form-item>

                <el-form-item label="Year:" prop="year">
                    <el-input v-model="ruleForm.year_release" maxlength="10" show-word-limit/>
                </el-form-item>

                <el-form-item label="Release Date:" prop="release_date">
                    <el-input v-model="ruleForm.release_date" maxlength="50" show-word-limit/>
                </el-form-item>

                <el-form-item label="Restrictions:" prop="restrictions">
                    <el-input v-model="ruleForm.restrictions" maxlength="10" show-word-limit/>
                </el-form-item>

                <el-form-item label="Runtime:" prop="runtime">
                    <el-input v-model="ruleForm.runtime" maxlength="8" show-word-limit/>
                </el-form-item>

                <el-form-item label="Rating:" prop="rating">
                    <el-input v-model="ruleForm.rating" maxlength="5" show-word-limit/>
                </el-form-item>

                <el-form-item label="Movie Budget:" prop="budget">
                    <el-input v-model="ruleForm.budget" maxlength="128" show-word-limit/>
                </el-form-item>

                <el-form-item label="Story:" prop="story">
                    <el-input v-model="ruleForm.story_line" maxlength="3000" show-word-limit :autosize="{ minRows: 4, maxRows: 4 }" type="textarea" />
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" @click="submitForm(ruleFormRef)">
                        Update
                    </el-button>
                </el-form-item>

            </el-form>
        </el-col>
    </el-row>
    <el-row v-else>
        <el-col><h2>Not Enough Data ...</h2></el-col>
    </el-row>


</template>

<script lang="ts" setup>
    import {storeToRefs} from 'pinia';
    import {useMoviesStore} from "../store/moviesStore";
    import {useMediaStore} from "../store/mediaStore";
    import type {TabsPaneContext} from 'element-plus';
    import {ElMessage, ElMessageBox, ElTable} from 'element-plus'
    import {ArrowRight} from '@element-plus/icons-vue'
    import {ref, watch, reactive} from "vue";

    const moviesStore = useMoviesStore();
    const mediaStore = useMediaStore();
    const {singleData, route, error,} = storeToRefs(moviesStore);
    const {imagesData, postersData, srcListImages, srcListPosters, countImg, countPoster} = storeToRefs(mediaStore);

    const activeTabName = ref('first');
    const activeAccordionTab = ref('1')
    const activeCollapseTab = ref(['1']);

    const multipleTableImage = ref();
    const multipleTablePoster = ref();
    const multipleSelectImage = ref([]);
    const multipleSelectPoster = ref([]);

    const formSize = ref('default');
    const ruleFormRef = ref();

    const handleChange = (val: string[],) => {
        mediaStore.flushState();
        if (val[1]) {
            (val[1] === 'image') ? mediaStore.getImages() : mediaStore.getPosters();
        }
    }

    const handleImageLoadMore = () => {
        mediaStore.updateImagePageSize();
    }

    const handlePosterLoadMore = () => {
        mediaStore.updatePosterPageSize();
    }
    // const handleClick = (tab: TabsPaneContext, event: Event) => {
    //     //console.log(tab, event)
    // }

    const toggleSelectImage = (rows?: []) => {
        if (rows) {
            rows.forEach((row) => {
                multipleTableImage.value!.toggleRowSelection(row, undefined);
            })
        } else {
            multipleTableImage.value!.clearSelection();
        }

    }
    const toggleSelectPoster = (rows?: []) => {
        if (rows) {
            rows.forEach((row) => {
                multipleTablePoster.value!.toggleRowSelection(row, undefined);
            })
        } else {
            multipleTablePoster.value!.clearSelection();
        }

    }
    const handleSelectImageChange = (val?: []) => {
        multipleSelectImage.value = [];
        val.filter(function(arr, i){
           multipleSelectImage.value.push(arr.id)
         });
    }
    const handleSelectPosterChange = (val?: []) => {
        multipleSelectPoster.value = [];
        val.filter(function(arr, i){
            multipleSelectPoster.value.push(arr.id)
        });
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

    const toggleRemovePoster = () => {
        if (multipleSelectPoster.value.length){
            getUnique(multipleSelectPoster.value);
            ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectPoster.value.length} posters will be deleted. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                mediaStore.removeMultipleImages(multipleSelectPoster.value,'posters');
                multipleTablePoster.value!.clearSelection();
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

    ////EDIT FORM
    const ruleForm = reactive({
        title: singleData.value.title,
        original_title: singleData.value.original_title,
        year_release: singleData.value.year_release,
        release_date: singleData.value.release_date,
        restrictions: singleData.value.restrictions,
        runtime: singleData.value.runtime,
        rating: singleData.value.rating,
        budget: singleData.value.budget,
        story_line: singleData.value.story_line,
    });

    const submitForm = async (formEl) => {
        if (!formEl) return
        await formEl.validate((valid, fields) => {
            if (valid) {
                ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
                    confirmButtonText: 'OK',
                    cancelButtonText: 'Cancel',
                    type: 'warning',
                }).then(() => {
                    moviesStore.updateItem(ruleForm);
                    console.log('submit!');
                    ElMessage({
                        type: 'success',
                        message: 'Update completed',
                    })
                }).catch(() => {
                    ElMessage({
                        type: 'info',
                        message: 'Update canceled',
                    })
                })
2
            } else {
                console.log('error submit!', fields);
            }
        })
    }

    const submitSync = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            moviesStore.syncItem();
            console.log('submit sync!');

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

