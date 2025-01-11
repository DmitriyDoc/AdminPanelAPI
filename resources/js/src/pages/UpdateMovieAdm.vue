<template>
    <el-row v-if="singleData.locale">
        <el-col :span="24">
            <h1>{{ singleData.title }}</h1>
        </el-col>
        <el-col :span="4">
            <el-image v-if="singleData.poster" :src="singleData.poster" fit="cover" />
            <div class="mt-1 mb-2">
                <h5>{{singleData.locale.poster_type}}</h5>
                <el-radio-group v-model="posterType" size="small">
                    <el-radio value="poster" >[poster]</el-radio>
                    <el-radio value="product" >[product]</el-radio>
                </el-radio-group>
            </div>
            <el-button type="danger" style="width: 100%;" @click="submitSync()" :loading='!!disabledBtnSync'>
                {{singleData.locale.sync_imdb}}
            </el-button>
            <div v-if="percentageSync" class="mt-1">
                <el-progress :percentage="percentageSync" :status="statusBar"/>
            </div>
            <div ><el-text tag="mark" class="el-color-predefine__colors el-text--danger p-2 mt-2"> {{singleData.locale.sync_notice}}</el-text></div>
            <template v-if="singleData.collection">
                <div class="mt-3">
                    <h5>{{singleData.locale.check_viewed}}</h5>
                    <el-checkbox v-model="singleData.collection.viewed" :label="singleData.locale.viewed" border class="d-block pt-1" />
                </div>
                <div class="mt-3">
                    <h5>{{singleData.locale.check_short}}</h5>
                    <el-checkbox v-model="singleData.collection.short" :label="singleData.locale.short_film" border class="d-block pt-1" />
                </div>
                <div class="mt-3">
                    <h5>{{singleData.locale.check_type_content}}</h5>
                    <el-checkbox v-model="singleData.collection.adult" :label="singleData.locale.adult" border class="d-block pt-1" />
                </div>
                <div class="mt-3">
                    <h5>{{singleData.locale.assign_categories}}</h5>
                    <el-cascader v-model="singleData.collection.id" :placeholder="singleData.locale.select" :props="propsCascader" :options="optionsCats" @change="handleCategoryChange"  style="min-width: 100%;">
                        <template #default="{ node, data }">
                            <span>{{ data.label }}</span>
                            <span v-if="!node.isLeaf"> ({{ data.children.length }}) </span>
                        </template>
                    </el-cascader>
                </div>
            </template>
        </el-col>
        <el-col :span="20">
            <el-tabs v-model="activeTabName" class="demo-tabs m-3" @tab-click="handleClick()">
                <el-tab-pane :label="singleData.locale.genres" name="first">
                    <li class="list-group-item">
                        <template v-for="(genre, index) in singleData.genres">
                            <div class="p-1 m-1 border bg-light"> {{ genre }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.countries" name="second">
                    <li class="list-group-item">
                        <template v-for="(country, index) in singleData.countries">
                            <div class="p-1 m-1 border bg-light"> {{ country }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.companies" name="third">
                    <li class="list-group-item">
                        <template v-for="(company, index) in singleData.companies">
                            <div class="p-1 m-1 border bg-light"> {{ company }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.directors" name="four">
                    <li class="list-group-item">
                        <template v-for="(director, index) in singleData.directors">
                            <template v-if="typeof director === 'object'" v-for="(role, name) in director">
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{name}}</strong>
                                    </RouterLink>
                                    <em>{{role}}</em>
                                </div>
                            </template>
                            <template v-else>
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{director}}</strong>
                                    </RouterLink>
                                </div>
                            </template>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.writers" name="five">
                    <li class="list-group-item">
                        <template v-for="(writer, index) in singleData.writers">
                            <template v-if="typeof writer === 'object'" v-for="(role, name) in writer">
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{name}}</strong>
                                    </RouterLink>
                                    <em>{{role}}</em>
                                </div>
                            </template>
                            <template v-else>
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{writer}}</strong>
                                    </RouterLink>
                                </div>
                            </template>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.cast" name="six">
                    <li class="list-group-item">
                        <template v-for="(item, index) in singleData.cast">
                            <div class="p-1 m-1 border bg-light">
                                <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                    <strong>{{item.actor}}</strong>
                                </RouterLink>
                                <em>{{item.role}}</em>
                            </div>
                        </template>
                    </li>
                </el-tab-pane>
            </el-tabs>
            <el-collapse v-model="activeCollapseTab" class="m-3" @change="handleChangeImages">
                <el-collapse-item :title="singleData.locale.images" name="image">
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
                            <el-table-column property="src" :label="singleData.locale.photo" width="150">
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
                            <template v-if="countImg" >
                                <el-button type="info" @click="handleImageLoadMore" >{{singleData.locale.next_page}}
                                    <el-icon class="el-icon--right">
                                        <ArrowRight/>
                                    </el-icon>
                                </el-button>
                            </template>
                        </div>
                    </template>
                </el-collapse-item>
                <el-collapse-item :title="singleData.locale.posters" name="poster" class="posters-collapse">
                    <div class="posters-assign-block">
                        <h3 class="text-center mt-3 mb-3">{{singleData.locale.assign_poster_as}}</h3>
                        <template v-for="(item, index) in postersAssignInfo">
                            <el-badge :value="item.count" class="item me-3 mt-3" :is-dot="!item.count" type="success">
                                <el-tag>
                                    <el-button @click="toggleAssignPoster(index)" type="primary">{{item.locale}}</el-button>
                                </el-tag>
                            </el-badge>
                        </template>
                        <div class="mt-3">
                            <el-button @click="toggleSelectPoster()" type="info">{{singleData.locale.clear_selection}}</el-button>
                            <el-button @click="toggleRemovePoster()" type="danger">{{singleData.locale.delete_selection}}</el-button>
                        </div>
                    </div>
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
                            <el-table-column property="src" :label="singleData.locale.photo" width="150">
                                <template v-slot:default="scope">
                                    <el-image :src="scope.row.srcset"/>
                                </template>
                            </el-table-column>
                            <el-table-column property="status_poster" :label="singleData.locale.assign_status" width="150">
                                <template v-slot:default="scope">
                                    <el-text v-if="scope.row.status_poster" type="success" class="fw-bold">
                                        {{scope.row.status_poster}}
                                    </el-text>
                                    <el-text type="danger" class="fw-bold"  v-else>
                                        {{singleData.locale.no_assigned}}
                                    </el-text>
                                </template>
                            </el-table-column>
                            <el-table-column property="src" :label="singleData.locale.link" show-overflow-tooltip>
                                <template v-slot:default="scope">
                                    <a :href="scope.row.src" target="_blank">{{scope.row.src}}</a>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div style="margin-top: 20px">
                            <div class="mt-3">
                                <template v-if="countPoster" >
                                    <el-button type="info" @click="handlePosterLoadMore" class="w-100">
                                        {{singleData.locale.next_page}}
                                        <el-icon class="el-icon--right">
                                            <ArrowRight/>
                                        </el-icon>
                                    </el-button>
                                </template>
                            </div>
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
                <el-form-item :label="singleData.locale.title" prop="title">
                    <el-input v-model="ruleForm.title" maxlength="150" show-word-limit />
                </el-form-item>

                <el-form-item :label="singleData.locale.original_title"  prop="title_oiginal">
                    <el-input v-model="ruleForm.original_title" maxlength="150" show-word-limit />
                </el-form-item>

                <el-form-item :label="singleData.locale.year_release" prop="year">
                    <el-input v-model="ruleForm.year_release" maxlength="10" show-word-limit/>
                </el-form-item>

                <el-form-item :label="singleData.locale.release_date" prop="release_date">
                    <el-input v-model="ruleForm.release_date" maxlength="50" show-word-limit/>
                </el-form-item>

                <el-form-item :label="singleData.locale.restriction" prop="restrictions">
                    <el-input v-model="ruleForm.restrictions" maxlength="10" show-word-limit/>
                </el-form-item>

                <el-form-item :label="singleData.locale.runtime" prop="runtime">
                    <el-input v-model="ruleForm.runtime" maxlength="10" show-word-limit/>
                </el-form-item>

                <el-form-item :label="singleData.locale.rating" prop="rating">
                    <el-input v-model="ruleForm.rating" maxlength="5" show-word-limit/>
                </el-form-item>

                <el-form-item :label="singleData.locale.budget" prop="budget">
                    <el-input v-model="ruleForm.budget" maxlength="128" show-word-limit/>
                </el-form-item>

                <el-form-item :label="singleData.locale.story" prop="story">
                    <el-input v-model="ruleForm.story_line" maxlength="3000" show-word-limit :autosize="{ minRows: 4, maxRows: 4 }" type="textarea" />
                </el-form-item>

                <el-form-item>

                    <el-button type="primary" @click="submitForm(ruleFormRef)" :loading="!!disabledBtnUpdate">
                        {{singleData.locale.update}}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-col>
    </el-row>
    <el-row v-else>
        <el-col> <h2>{{$t('not_enough_data')}}</h2> </el-col>
    </el-row>
</template>

<script lang="ts" setup>
    import { storeToRefs } from 'pinia';
    import { useMoviesStore } from "../store/moviesStore";
    import { useMediaStore } from "../store/mediaStore";
    import { useCategoriesStore } from "../store/categoriesStore";
    import { useProgressBarStore } from "../store/progressBarStore";
    import { useLanguageStore } from "../store/languageStore";
    import type { TabsPaneContext } from 'element-plus';
    import { ElMessage, ElMessageBox, ElTable } from 'element-plus'
    import { ArrowRight } from '@element-plus/icons-vue'
    import { ref, watch, onMounted } from "vue";
    import { useRoute } from "vue-router";

    const route = useRoute();
    const moviesStore = useMoviesStore();
    const mediaStore = useMediaStore();
    const categoryStore = useCategoriesStore();
    const progressBarStore = useProgressBarStore();
    const languageStore = useLanguageStore();

    const { singleData, disabledBtnUpdate, disabledBtnSync, locale, error } = storeToRefs(moviesStore);
    const { postersAssignInfo, imagesData, postersData, srcListImages, srcListPosters, countImg, countPoster } = storeToRefs(mediaStore);
    const { statusBar, percentageSync } = storeToRefs(progressBarStore);
    const { optionsCats } = storeToRefs(categoryStore);
    const { watcherLang } = storeToRefs(languageStore);

    const activeTabName = ref('first');
    const activeAccordionTab = ref('1')
    const activeCollapseTab = ref(['1']);

    const multipleTableImage = ref();
    const multipleTablePoster = ref();
    const multipleSelectImage = ref([]);
    const multipleSelectPoster = ref([]);

    const formSize = ref('default');
    const ruleFormRef = ref();
    const ruleForm = ref(singleData);
    const propsCascader = {
        multiple: true,
        checkStrictly: true,
    }
    const posterType = ref('poster');


    watch(() => watcherLang.value, (newLang) => {
        moviesStore.showItem();
        categoryStore.getCategories();
    });

    onMounted(() => {
        moviesStore.showItem();
        categoryStore.getCategories();
        percentageSync.value = 0;
    })

    const handleCategoryChange = (value) => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            if (value.length <= 4) {
                categoryStore.setCategories({
                    id_movie: singleData.value.id_movie,
                    type_film: route.params.slug,
                    categories: value??[],
                    tags: singleData.value.genres??[],
                    viewed: singleData.value.collection.viewed ?? false,
                    short: singleData.value.collection.short ?? false,
                    adult: singleData.value.collection.adult ?? false,
                })
                if (value.length === 0){
                    ElMessage({
                        type: 'success',
                        message: 'Collection deleted',
                    })
                }
            } else {
                ElMessage({
                    type: 'info',
                    message: 'No more than 4 collections for one movie!',
                })
            }
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Select collection canceled',
            })
        })

    }

    const handleChangeImages = (val: string[],) => {
        mediaStore.flushState();
        if (val[1]) {
            (val[1] === 'image') ? mediaStore.getImages(route.params.slug) : mediaStore.getPosters();
        }
    }

    const handleImageLoadMore = () => {
        mediaStore.updateImagePageSize(route.params.slug);
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
    const toggleAssignPoster = (category) => {
        if (multipleSelectPoster.value.length){
            //getUnique(multipleSelectPoster.value);
            ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectPoster.value.length} posters will be assign. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                mediaStore.assignPoster(multipleSelectPoster.value,category);
                toggleSelectPoster();
                ElMessage({
                    type: 'success',
                    message: 'Assign completed',
                })
            }).catch(() => {
                ElMessage({
                    type: 'info',
                    message: 'Categorization canceled',
                })
            })
        } else {
            ElMessage.error('No pictures have been selected');
        }
    }
    const getUnique = (arr) => {
        return arr.filter((el, ind) => ind === arr.indexOf(el));
    };

    const submitForm = async (formEl) => {
        if (!formEl) return
        await formEl.validate((valid, fields) => {
            if (valid) {
                ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
                    confirmButtonText: 'OK',
                    cancelButtonText: 'Cancel',
                    type: 'warning',
                }).then(() => {
                    moviesStore.updateItem({
                        title: ruleForm.value.title,
                        original_title: ruleForm.value.original_title,
                        year_release: ruleForm.value.year_release,
                        release_date: ruleForm.value.release_date,
                        restrictions: ruleForm.value.restrictions,
                        runtime: ruleForm.value.runtime,
                        rating: ruleForm.value.rating,
                        budget: ruleForm.value.budget,
                        story_line: ruleForm.value.story_line,
                    });
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
            moviesStore.syncItem({
                id: route.params.id,
                type: route.params.slug,
                posterType: posterType.value,
            });
            progressBarStore.getSyncCurrentPercentage('syncMoviePercentageBar');
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
    .posters-collapse  :deep(.el-collapse-item__content){
        max-height: 1000px;
        overflow-y: scroll;
    }
    .posters-assign-block {
        position: sticky;
        top: 0px;
        z-index: 100;
        padding: 10px;
        background-color: aliceblue;
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

