<template>
    <el-row v-if="singleData.locale">
        <el-col :span="24" class="mt-3">
            <h1>{{ singleData.title }}</h1>
        </el-col>
        <el-col :span="4">
            <RouterLink :to="{ name: 'showMovie', params: { id: route.params.id }}">
                <el-button  type="info" class="mt-2 mb-2" style="width: 100%;" >
                    {{ singleData.locale.back_to_show }}
                </el-button >
            </RouterLink>
            <el-image v-if="singleData.poster" :src="singleData.poster" fit="cover" class="w-full"/>
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
            <div v-if="Object.keys(percentageSync).length" class="mt-1">
                <el-progress :percentage="percentageSync.percent" :status="percentageSync.color"/>
                <el-text type="success" ><strong>{{percentageSync.action}}</strong></el-text>
            </div>
            <div ><el-text tag="mark" class="el-color-predefine__colors el-text--danger p-2 mt-2"> {{singleData.locale.sync_notice}}</el-text></div>
            <el-button type="warning" class="mt-2" style="width: 100%;" @click="submitResize()" :loading='!!disabledBtnResize'>
                {{singleData.locale.submit_resize}}
            </el-button>
            <el-button type="success" class="m-0 mt-2" style="width: 100%;" @click="submitPublished()" :disabled="propPublishBtn.disabled" :plain="propPublishBtn.plain">
                {{ singleData.locale.add_to_export}}
            </el-button>
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
            <el-steps style="max-width: 100%" :active="propsSteps.active" :finish-status="propsSteps.finishStatus" align-center>
                <el-step title="Step 1" description="Assign categories" />
                <el-step title="Step 2" description="Assign poster" />
                <el-step title="Step 3" description="Resize all images" />
                <el-step title="Step 4" description="The movie add to export" />
                <el-step title="Published!"   description="The movie move to site" />
            </el-steps>
            <el-tabs v-model="activeTabName" class="demo-tabs m-3" @tab-click="handleClick">
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
                                    {{writer}}
                                </div>
                            </template>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.cast" name="six">
                    <li class="list-group-item">
                        <template v-for="(item, index) in singleData.cast">
                            <div class="d-flex justify-content-between p-1 bg-light">
                                <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                    <strong>{{item.actor}}</strong>
                                </RouterLink>
                                <span v-if="item.role" class="dots"></span>
                                <em>{{item.role}}</em>
                            </div>
                        </template>
                    </li>
                </el-tab-pane>
            </el-tabs>
            <el-collapse v-model="activeCollapseTab" class="m-3" @change="handleChangeImages">
                <el-collapse-item :title="singleData.locale.images" name="image">
                    <template v-if="imagesData.length">
                        <div class="custom-table" ref="customTableRef">
                            <div class="table-header">
                                <div class="table-cell" style="width: 80px;">ID</div>
                                <div class="table-cell" style="width: 40px; text-align: center;">↕️</div>
                                <div class="table-cell" style="width: 55px; text-align: center;">
                                    <el-checkbox
                                        :indeterminate="selectionIndeterminate"
                                        v-model="selectAll"
                                        @change="toggleSelectAllImages"
                                    />
                                </div>
                                <div class="table-cell" style="width: 70px;">Image ID</div>
                                <div class="table-cell" style="width: 70px;">Resolution</div>
                                <div class="table-cell" style="width: 200px;">{{ singleData.locale.photo }}</div>
                                <div class="table-cell" style="width: 400px;">{{ singleData.locale.link }}</div>
                            </div>

                            <div class="table-body" ref="tableBodyRef">
                                <div
                                    v-for="(item, index) in imagesData"
                                    :key="item.id"
                                    class="table-row"
                                    :class="{ selected: multipleSelectImage.includes(item.id) }"
                                    @click="handleRowClick($event, item)"
                                    :data-id="item.id"
                                >
                                    <div class="table-cell" style="width: 100px;">{{ index + 1 }}</div>
                                    <!-- Drag Handle -->
                                    <div class="table-cell drag-handle" style="width: 40px; text-align: center; cursor: move;">
                                        <el-icon style="color: #999; font-size: 18px;">
                                            <Operation />
                                        </el-icon>
                                    </div>

                                    <!-- Selection Checkbox -->
                                    <div class="table-cell" style="width: 55px; text-align: center;">
                                        <el-checkbox
                                            :model-value="multipleSelectImage.includes(item.id)"
                                            @click="handleCheckboxClick($event, item)"
                                        />
                                    </div>

                                    <!-- ID -->
                                    <div class="table-cell" style="width: 100px;">{{ item.id }}</div>

                                    <!-- Resolution -->
                                    <div class="table-cell" style="width: 100px;">{{ item.width }}</div>

                                    <!-- Photo -->
                                    <div class="table-cell" style="width: 300px;">
                                        <el-image
                                            :src="item.srcset"
                                            fit="contain"
                                            style="width: 300px; height: 150px; object-fit: cover;"
                                        />
                                    </div>

                                    <!-- Link -->
                                    <div class="table-cell">
                                        <a :href="item.src" target="_blank" @click.stop>{{ item.src }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                            <div style="margin-top: 20px">
                            <el-button @click="handleSelectImage()" type="info">{{singleData.locale.clear_selection}}</el-button>
                            <el-button @click="handleRemoveImage()" type="danger">{{singleData.locale.delete_selection}}</el-button>
                            <el-button @click="handleMoveToPosters()" type="primary">{{singleData.locale.move_selection}}</el-button>
                            <el-button @click="handleSaveOrder()" type="success" >{{'Save Order Images'}}</el-button>
                            <template v-if="countImg > 50" >
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
                            <el-button @click="handleSelectPoster()" type="info">{{singleData.locale.clear_selection}}</el-button>
                            <el-button @click="handleRemovePoster()" type="danger">{{singleData.locale.delete_selection}}</el-button>
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
                            <el-table-column property="width" label="Resolution" width="100">
                                <template v-slot:default="scope">
                                    {{scope.row.width}}
                                </template>
                            </el-table-column>
                            <el-table-column property="src" :label="singleData.locale.photo" width="200">
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
                                <template v-if="countPoster > 50" >
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
        <el-col>
            <div slot="empty">
                <el-empty :description="$t('not_enough_data')" :image-size="150" />
            </div>
        </el-col>
    </el-row>
</template>
<script lang="ts" setup>
    import { storeToRefs } from 'pinia';
    import { useMoviesStore } from "../store/moviesStore";
    import { useMediaStore } from "../store/mediaStore";
    import { useCategoriesStore } from "../store/categoriesStore";
    import { useProgressBarStore } from "../store/progressBarStore";
    import { useLanguageStore } from "../store/languageStore";
    import Sortable from 'sortablejs';
    import { Operation } from '@element-plus/icons-vue';
    import { ElMessage, ElMessageBox, ElTable, ElButton, TabsPaneContext } from 'element-plus'
    import { ArrowRight } from '@element-plus/icons-vue'
    import { ref, reactive, watch, computed, onMounted, nextTick, onUnmounted } from "vue";
    import { useRoute } from "vue-router";

    const route = useRoute();
    const moviesStore = useMoviesStore();
    const mediaStore = useMediaStore();
    const categoryStore = useCategoriesStore();
    const progressBarStore = useProgressBarStore();
    const languageStore = useLanguageStore();

    const { singleData, disabledBtnUpdate, disabledBtnSync, locale, error } = storeToRefs(moviesStore);
    const { postersAssignInfo, imagesData, postersData, srcListImages, srcListPosters, disabledBtnResize, countImg, countPoster } = storeToRefs(mediaStore);
    const { percentageSync } = storeToRefs(progressBarStore);
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
    const posterType = ref('poster');
    const propsCascader = {
        multiple: true,
        checkStrictly: true,
    }
    const propsSteps = reactive( {
        active:0,
        finishStatus:"wait",
    });
    const propPublishBtn = reactive( {
        disabled:1,
        plain:1,
    });

    const customTableRef = ref(null);
    const tableBodyRef = ref(null);
    const selectAll = ref(false);
    const selectionIndeterminate = ref(false);
    let sortableInstance = null;

    const stepsState = computed(() => {
        const hasCategories = singleData.value.collection && Array.isArray(singleData.value.collection.id) && singleData.value.collection.id.length > 0;
        const hasAssignedPoster = singleData.value.assign_posters;
        const hasLocalPoster = typeof singleData.value.poster === 'string' && singleData.value.poster.includes('media-api.local');
        const rawPublished = singleData.value.published ? 1 : 0;
        const exported = singleData.value.published == 2 ? 1 : 0;
        const activeStep = hasCategories ? (hasAssignedPoster ? (hasLocalPoster ? (rawPublished ? (exported ? 5 : 4) : 3) : 2) : 1) : 0;
        const isPublished = activeStep === 3 ? 0 : 1;

        return {
            active: activeStep,
            finishStatus: hasCategories ? 'success' : 'wait',
            publishBtn: {
                plain: isPublished,
                disabled: isPublished,
            },
        };
    });

    watch(stepsState, (newState) => {
        propsSteps.active = newState.active;
        propsSteps.finishStatus = newState.finishStatus;
        propPublishBtn.plain = newState.publishBtn.plain;
        propPublishBtn.disabled = newState.publishBtn.disabled;
    }, { immediate: true });

    watch(() => watcherLang.value, (newLang) => {
        moviesStore.showItem();
        categoryStore.getCategories();
    });

    onMounted(() => {
        percentageSync.value = {};
        moviesStore.showItem();
        categoryStore.getCategories();

        // Ждём загрузки данных
        const timer = setInterval(() => {
            if (imagesData.value.length > 0) {
                clearInterval(timer);
                nextTick().then(() => {
                    setTimeout(initSortable, 100); // +100ms задержка
                });
            }
        }, 100);
    });

    onUnmounted(() => {
        if (sortableInstance) {
            sortableInstance.destroy();
        }
    });
    const handleCheckboxClick = (event, item) => {
        event.stopPropagation(); // Останавливаем всплытие
        event.preventDefault();  // Предотвращаем дефолтное поведение

        // Переключаем выделение
        const index = multipleSelectImage.value.indexOf(item.id);
        if (index > -1) {
            multipleSelectImage.value.splice(index, 1);
        } else {
            multipleSelectImage.value.push(item.id);
        }

        updateSelectAllState();
    };
    const handleCategoryChange = async (value) => {
        try {
            await ElMessageBox.confirm('Are you sure?', 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            });

            if (value.length <= 4) {
                await categoryStore.setCategories({
                    id_movie: singleData.value.id_movie,
                    type_film: route.params.slug,
                    categories: value ?? [],
                    tags: singleData.value.genres ?? [],
                    viewed: singleData.value.collection.viewed ?? false,
                    short: singleData.value.collection.short ?? false,
                    adult: singleData.value.collection.adult ?? false,
                });

                if (value.length === 0) {
                    ElMessage({
                        type: 'success',
                        message: 'Collection deleted',
                    });
                }
            } else {
                ElMessage({
                    type: 'info',
                    message: 'No more than 4 collections for one movie!',
                });
            }
        } catch (error) {
            ElMessage({
                type: 'info',
                message: 'Select collection canceled',
            });
        }
    };

    const handleChangeImages = (val: string[],) => {
        mediaStore.flushState();
        if (val[1]) {
            (val[1] === 'image') ? mediaStore.getImages(singleData.value.slug) : mediaStore.getPosters(singleData.value.slug);
        }
    }

    const handleImageLoadMore = () => {
        mediaStore.updateImagePageSize(singleData.value.slug);
    }

    const handlePosterLoadMore = () => {
        mediaStore.updatePosterPageSize(singleData.value.slug);
    }
    const handleClick = (tab: TabsPaneContext, event: Event) => {
        //console.log(tab, event)
    }

    // const toggleSelectImage = (rows?: []) => {
    //     if (rows) {
    //         rows.forEach((row) => {
    //             multipleTableImage.value!.toggleRowSelection(row, undefined);
    //         })
    //     } else {
    //         multipleTableImage.value!.clearSelection();
    //     }
    //
    // }
    const handleSelectPoster = (rows?: []) => {
        if (rows) {
            rows.forEach((row) => {
                multipleTablePoster.value?.toggleRowSelection(row, undefined);
            })
        } else {
            multipleTablePoster.value?.clearSelection();
        }

    }
    // const handleSelectImageChange = (val?: []) => {
    //     multipleSelectImage.value = [];
    //     val.filter(function(arr, i){
    //        multipleSelectImage.value.push(arr.id)
    //      });
    // }

    const clearPosterSelection = () => {
        multipleTablePoster.value?.clearSelection();
    };
    const handleRowClick = (event, item) => {
        // Если клик по чекбоксу — не обрабатываем
        if ((event.target as HTMLElement).closest('.el-checkbox')) {
            return;
        }

        // Переключаем выделение строки
        const index = multipleSelectImage.value.indexOf(item.id);
        if (index > -1) {
            multipleSelectImage.value.splice(index, 1);
        } else {
            multipleSelectImage.value.push(item.id);
        }

        updateSelectAllState();
    }

    const toggleSelectAllImages = () => {
        if (selectAll.value) {
            multipleSelectImage.value = imagesData.value.map(item => item.id);
        } else {
            multipleSelectImage.value = [];
        }
        updateSelectAllState();
    };

    const updateSelectAllState = () => {
        if (multipleSelectImage.value.length === 0) {
            selectAll.value = false;
            selectionIndeterminate.value = false;
        } else if (multipleSelectImage.value.length === imagesData.value.length) {
            selectAll.value = true;
            selectionIndeterminate.value = false;
        } else {
            selectionIndeterminate.value = true;
        }
    };

    // Сбросить выделение
    const handleSelectImage = () => {
        multipleSelectImage.value = [];
        selectAll.value = false;
        selectionIndeterminate.value = false;
    };

    // Следим за изменениями данных — обновляем выделение и сортировку
    watch(imagesData, () => {
        nextTick().then(() => {
            setTimeout(initSortable, 100); // Задержка после обновления данных
            const ids = new Set(multipleSelectImage.value);
            multipleSelectImage.value = imagesData.value.filter(item => ids.has(item.id)).map(item => item.id);
            updateSelectAllState();
        });
    });
    const handleSelectPosterChange = (val?: []) => {
        multipleSelectPoster.value = [];
        val.filter(function(arr, i){
            multipleSelectPoster.value.push(arr.id)
        });
    }
    const handleRemoveImage = () => {
        if (multipleSelectImage.value.length){
            getUnique(multipleSelectImage.value);
            ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectImage.value.length} pictures will be deleted. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                mediaStore.removeMultipleImages(multipleSelectImage.value,'images',singleData.value.slug);
                toggleSelectImage();
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

    const handleMoveToPosters = () => {
        if (multipleSelectImage.value.length){
            getUnique(multipleSelectImage.value);
            ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectImage.value.length} pictures will be moved to posters. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                mediaStore.moveMultipleImages(multipleSelectImage.value,singleData.value.slug);
                toggleSelectImage();
                ElMessage({
                    type: 'success',
                    message: 'Move to posters completed',
                })
            }).catch(() => {
                ElMessage({
                    type: 'info',
                    message: 'Move to posters canceled',
                })
            })
        } else {
            ElMessage.error('No pictures have been selected');
        }
    }

    const handleRemovePoster = () => {
        if (multipleSelectPoster.value.length){
            getUnique(multipleSelectPoster.value);
            ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectPoster.value.length} posters will be deleted. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                mediaStore.removeMultipleImages(multipleSelectPoster.value,'posters',singleData.value.slug);
                clearPosterSelection();
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
            ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectPoster.value.length} posters will be assign. Continue?`, 'WARNING', {
                confirmButtonText: 'OK',
                cancelButtonText: 'Cancel',
                type: 'warning',
            }).then(() => {
                mediaStore.assignPoster(multipleSelectPoster.value,category,singleData.value.slug);
                clearPosterSelection();
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
            } else {
                console.log('error submit!', fields);
            }
        })
    }

    const submitSync = () => {
        ElMessageBox.confirm(singleData.value.locale.sync_notice + '\n' + `Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            progressBarStore.getSyncCurrentPercentage();
            moviesStore.syncItem({
                id: route.params.id,
                type: singleData.value.slug,
                posterType: posterType.value,
            });
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Sync canceled',
            })
        })
    }
    const submitResize = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            mediaStore.resizeAllImages(route.params.id);
            propPublishBtn.plain = 0;
            propPublishBtn.disabled = 0;
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Resize canceled',
            })
        })
    }
    const submitPublished = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            moviesStore.publicationItem(route.params.id);
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Publication canceled',
            })
        })
    }
    const handleSaveOrder = async () => {
        try {
            const orderedIds = imagesData.value.map(item => item.id);
            await mediaStore.saveImageOrder({
                movieId: route.params.id,
                slug: singleData.value.slug,
                orderedIds: orderedIds,
            });
        } catch (error) {
            ElMessage.error('Failed to save order images');
            console.error(error);
        }
    };
    const initSortable = async () => {
        await nextTick();

        const el = tableBodyRef.value;
        if (!el) {
            console.warn('tableBodyRef is null');
            return;
        }

        if (sortableInstance) {
            sortableInstance.destroy();
        }

        sortableInstance = new Sortable(el, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: (evt) => {
                const oldIndex = evt.oldIndex;
                const newIndex = evt.newIndex;
                if (oldIndex === newIndex) return;

                const movedItem = imagesData.value.splice(oldIndex, 1)[0];
                imagesData.value.splice(newIndex, 0, movedItem);

                const selectedIds = [...multipleSelectImage.value];
                multipleSelectImage.value = selectedIds;
            },
        });
    };
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
    .dots {
        flex-grow: 1;
        border-bottom: 1px dashed black;
        margin: 0 10px 7px;
    }
    .custom-table { width: 100%; border: 1px solid #ebeef5; border-radius: 4px; overflow: hidden; }
    .table-header, .table-row { display: flex;position: relative;z-index: 1; align-items: center; min-height: 48px; border-bottom: 1px solid #ebeef5; background: #f5f7fa; }
    .table-header { font-weight: bold; color: #606266; background: #f5f7fa; }
    .table-row { background: #fff; transition: background 0.2s; }
    .table-row:hover { background: #f5f7fa; }
    .table-row.selected { background: #c1e2fe !important; }
    .table-cell { display: flex; justify-content: center; align-items: center; padding: 0 12px; box-sizing: border-box; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .table-body { max-height: 600px; overflow-y: auto; } /* Sortable стили */
    .sortable-ghost { opacity: 0.5; background: #e3f2fd !important; }
    .sortable-chosen { background: #bbdefb !important; }
    .sortable-drag { background: #90caf9 !important; z-index: 1000; box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
    .drag-handle { transition: color 0.2s; }
    .drag-handle:hover { color: #409eff; }
    .sortable-drag {
        background: #90caf9 !important;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        border-radius: 4px;
        opacity: 0.95;
    }
</style>

