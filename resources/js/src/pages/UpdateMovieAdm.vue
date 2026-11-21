<template>
    <el-row v-if="singleData.locale">
        <el-col :span="4">
            <RouterLink :to="{ name: 'showMovie', params: { id: route.params.id }}">
                <el-button type="info" class="mt-2 mb-2" style="width: 100%;">
                    {{ singleData.locale.back_to_show }}
                </el-button>
            </RouterLink>

            <el-image v-if="singleData.poster" :src="singleData.poster" fit="cover" class="w-full" style="border-radius: 4px;" />

            <div class="mt-1 mb-2">
                <h5>{{ singleData.locale.poster_type }}</h5>
                <el-radio-group v-model="posterType" size="small">
                    <el-radio value="poster">[poster]</el-radio>
                    <el-radio value="product">[product]</el-radio>
                </el-radio-group>
            </div>

            <el-button type="warning" style="width: 100%;" @click="submitSync('full_sync')" :loading='!!disabledBtnSync'>
                {{ singleData.locale.sync_full }}
            </el-button>

            <!-- История синхронизации (оставляем, если нужна, или можно тоже перевести в уведомления) -->
            <div v-if="syncActionsHistory.length" class="mt-2 sync-checklist-container">
                <ul class="checklist">
                    <li v-for="(action, index) in syncActionsHistory" :key="index" class="checklist-item">
                        <el-icon color="#67C23A" size="18"><Check /></el-icon>
                        <el-text type="success" class="action-text">{{ action }}</el-text>
                    </li>
                </ul>
            </div>

            <div>
                <el-text tag="mark" class="el-color-predefine__colors el-text--danger p-2 mt-2" style="display: block; border-radius: 4px;">
                    {{ singleData.locale.sync_notice }}
                </el-text>
            </div>

            <el-button type="warning" class="mt-2" style="width: 100%;" @click="submitResize()" :loading='!!disabledBtnResize'>
                {{ singleData.locale.submit_resize }}
            </el-button>


            <el-button type="success" class="m-0 mt-2" style="width: 100%;" @click="submitPublished()" :disabled="propPublishBtn.disabled" :plain="propPublishBtn.plain">
                {{ singleData.locale.add_to_export }}
            </el-button>

            <template v-if="singleData.collection">
                <div class="mt-3">
                    <h5>{{ singleData.locale.check_viewed }}</h5>
                    <el-checkbox v-model="singleData.collection.viewed" :label="singleData.locale.viewed" border class="d-block pt-1" />
                </div>
                <div class="mt-3">
                    <h5>{{ singleData.locale.check_short }}</h5>
                    <el-checkbox v-model="singleData.collection.short" :label="singleData.locale.short_film" border class="d-block pt-1" />
                </div>
                <div class="mt-3">
                    <h5>{{ singleData.locale.check_type_content }}</h5>
                    <el-checkbox v-model="singleData.collection.adult" :label="singleData.locale.adult" border class="d-block pt-1" />
                </div>
                <div class="mt-3">
                    <h5>{{ singleData.locale.assign_categories }}</h5>
                    <el-cascader
                        v-model="singleData.collection.id"
                        :placeholder="singleData.locale.select"
                        :props="propsCascader"
                        :options="optionsCats"
                        @change="handleCategoryChange"
                        style="min-width: 100%;"
                    >
                        <template #default="{ node, data }">
                            <span>{{ data.label }}</span>
                            <span v-if="!node.isLeaf"> ({{ data.children.length }}) </span>
                        </template>
                    </el-cascader>
                </div>
            </template>
        </el-col>

        <el-col :span="20" class="mt-4">
            <el-steps style="max-width: 100%" :active="propsSteps.active" :finish-status="propsSteps.finishStatus" align-center>
                <el-step :title="singleData.locale.step + ' 1'" :description="singleData.locale.assign_are_categories" />
                <el-step :title="singleData.locale.step + ' 2'" :description="singleData.locale.assign_are_poster" />
                <el-step :title="singleData.locale.step + ' 3'" :description="singleData.locale.resize_all_images" />
                <el-step :title="singleData.locale.step + ' 4'" :description="singleData.locale.movie_add_to_export" />
                <el-step :title="singleData.locale.published" :description="singleData.locale.movie_move_to_site" />
            </el-steps>

            <el-tabs v-model="activeTabName" class="demo-tabs m-3" @tab-click="handleClick">
                <el-tab-pane :label="singleData.locale.genres" name="first">
                    <li class="list-group-item">
                        <template v-for="(genre, index) in singleData.genres" :key="index">
                            <div class="p-1 m-1 border bg-light"> {{ genre }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.countries" name="second">
                    <li class="list-group-item">
                        <template v-for="(country, index) in singleData.countries" :key="index">
                            <div class="p-1 m-1 border bg-light"> {{ country }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.companies" name="third">
                    <li class="list-group-item">
                        <template v-for="(company, index) in singleData.companies" :key="index">
                            <div class="p-1 m-1 border bg-light"> {{ company }}</div>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.directors" name="four">
                    <li class="list-group-item">
                        <template v-for="(director, index) in singleData.directors" :key="index">
                            <template v-if="typeof director === 'object'" v-for="(role, name) in director" :key="name">
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{ name }}</strong>
                                    </RouterLink>
                                    <em> {{ role }}</em>
                                </div>
                            </template>
                            <template v-else>
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{ director }}</strong>
                                    </RouterLink>
                                </div>
                            </template>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.writers" name="five">
                    <li class="list-group-item">
                        <template v-for="(writer, index) in singleData.writers" :key="index">
                            <template v-if="typeof writer === 'object'" v-for="(role, name) in writer" :key="name">
                                <div class="p-1 m-1 border bg-light">
                                    <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                        <strong>{{ name }}</strong>
                                    </RouterLink>
                                    <em> {{ role }}</em>
                                </div>
                            </template>
                            <template v-else>
                                <div class="p-1 m-1 border bg-light">{{ writer }}</div>
                            </template>
                        </template>
                    </li>
                </el-tab-pane>
                <el-tab-pane :label="singleData.locale.cast" name="six">
                    <li class="list-group-item">
                        <template v-for="(item, index) in singleData.cast" :key="index">
                            <div class="d-flex justify-content-between p-1 bg-light">
                                <RouterLink :to="{ name: 'showPerson', params: { slug: 'Celebs', id: index }}">
                                    <strong>{{ item.actor }}</strong>
                                </RouterLink>
                                <span v-if="item.role" class="dots"></span>
                                <em>{{ item.role }}</em>
                            </div>
                        </template>
                    </li>
                </el-tab-pane>
            </el-tabs>

            <div class="d-flex gap-2 m-3">
                <el-badge
                    :value="singleData.assigned_media_source !== 'none' ? (singleData.assigned_media_source === 'tmdb' ? 'TMDB' : 'IMDb') : ''"
                    :hidden="singleData.assigned_media_source === 'none'"
                    class="source-badge-el"
                    :class="singleData.assigned_media_source === 'tmdb' ? 'badge-tmdb-el' : 'badge-imdb-el'"
                >
                    <el-button type="info" size="large" @click="openImagesModal">
                        <el-icon class="mr-1"><Picture /></el-icon>
                        <span>{{ singleData.locale.images }}</span>
                    </el-button>
                </el-badge>
                <el-badge
                    :value="singleData.assigned_media_source !== 'none' ? (singleData.assigned_media_source === 'tmdb' ? 'TMDB' : 'IMDb') : ''"
                    :hidden="singleData.assigned_media_source === 'none'"
                    class="source-badge-el"
                    :class="singleData.assigned_media_source === 'tmdb' ? 'badge-tmdb-el' : 'badge-imdb-el'"
                >
                    <el-button type="info" size="large" @click="openPostersModal">
                        <el-icon class="mr-1"><PictureFilled /></el-icon>
                        <span>{{ singleData.locale.posters }}</span>
                    </el-button>
                </el-badge>
            </div>

            <!-- МОДАЛКА ИЗОБРАЖЕНИЙ -->
            <el-dialog
                v-model="mediaStore.isImagesModalVisible"
                :title="singleData.locale.images || 'Изображения'"
                width="85%"
                top="5vh"
                destroy-on-close
            >
                <el-radio-group v-model="mediaStore.activeImageSource" class="mb-3" @change="handleImageSourceChange">
                    <el-radio-button value="tmdb">
                        TMDB ({{ mediaStore.imagesDataTmdb.length }})
                    </el-radio-button>
                    <el-radio-button value="imdb">
                        IMDB ({{ mediaStore.imagesDataImdb.length }})
                    </el-radio-button>
                </el-radio-group>

                <div v-if="currentImagesData.length" class="custom-table" ref="customTableRef">
                    <div class="table-header">
                        <div class="table-cell" style="width: 50px;">ID</div>
                        <div class="table-cell" style="width: 80px;">{{ singleData.locale.poster_source }}</div>
                        <div class="table-cell" style="width: 40px; text-align: center;">↕️</div>
                        <div class="table-cell" style="width: 50px; text-align: center;">
                            <el-checkbox :indeterminate="selectionIndeterminate" v-model="selectAll" @change="toggleSelectAllImages" />
                        </div>
                        <div class="table-cell" style="width: 100px;">{{ singleData.locale.resolution }}</div>
                        <div class="table-cell" style="width: 250px;">{{ singleData.locale.photo }}</div>
                        <div class="table-cell">{{ singleData.locale.link }}</div>
                    </div>

                    <div class="table-body" ref="tableBodyRef">
                        <div
                            v-for="(item, index) in currentImagesData"
                            :key="item.id"
                            class="table-row"
                            :class="{ selected: multipleSelectImage.includes(item.id), 'source-tmdb': item.source === 'tmdb', 'source-imdb': item.source === 'imdb' }"
                            @click="handleRowClick($event, item)"
                        >
                            <div class="table-cell" style="width: 50px;">{{ index + 1 }}</div>
                            <div class="table-cell" style="width: 80px;">
                                <span class="source-badge" :class="`badge-${item.source}`">
                                    {{ item.source.toUpperCase() }}
                                </span>
                            </div>
                            <div class="table-cell drag-handle" style="width: 40px; text-align: center; cursor: move;">
                                <el-icon style="color: #999; font-size: 18px;"><Operation /></el-icon>
                            </div>
                            <div class="table-cell" style="width: 50px; text-align: center;">
                                <el-checkbox :model-value="multipleSelectImage.includes(item.id)" @click="handleCheckboxClick($event, item)" />
                            </div>
                            <div class="table-cell" style="width: 100px;">{{ item.width }}x{{ item.height }}</div>
                            <div class="table-cell" style="width: 250px;">
                                <el-image :src="item.srcset" fit="contain" style="width: 100%; height: 100px; object-fit: cover;" />
                            </div>
                            <div class="table-cell">
                                <a :href="item.src" target="_blank" @click.stop class="text-truncate" style="max-width: 100%; display: block;">{{ item.src }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex flex-wrap gap-2">
                    <el-button @click="handleSelectImage()" type="info">
                        {{ singleData.locale.clear_selection }}
                    </el-button>
                    <el-button @click="handleRemoveImage()" type="danger">
                        {{ singleData.locale.delete_selection }}
                    </el-button>
                    <el-button @click="handleMoveToPosters()" type="primary">
                        {{ singleData.locale.move_selection }}
                    </el-button>
                    <el-button @click="handleSaveOrder()" type="success">
                        {{ singleData.locale.save_order_images }}
                    </el-button>
                    <el-button @click="submitSync('images_sync')" type="warning" :loading='!!disabledBtnSync'>
                        {{ singleData.locale.images_sync }}
                    </el-button>

                    <template v-if="currentImagesHasMore">
                        <el-button type="info" @click="handleImageLoadMore">
                            {{ singleData.locale.next_page }}
                            <el-icon class="el-icon--right"><ArrowRight /></el-icon>
                        </el-button>
                    </template>
                </div>
            </el-dialog>

            <!-- МОДАЛКА ПОСТЕРОВ -->
            <el-dialog
                v-model="mediaStore.isPostersModalVisible"
                :title="singleData.locale.posters || 'Постеры'"
                width="85%"
                top="5vh"
                destroy-on-close
            >
                <div class="posters-assign-block">
                    <div class="poster-source-selector">
                        <el-radio-group v-model="mediaStore.activePosterSource">
                            <el-radio-button value="tmdb">
                                TMDB ({{ mediaStore.postersDataTmdb.length }})
                            </el-radio-button>
                            <el-radio-button value="imdb">
                                IMDB ({{ mediaStore.postersDataImdb.length }})
                            </el-radio-button>
                        </el-radio-group>
                    </div>
                    <div class="poster-assign-controls">
                        <h5>
                            {{ singleData.locale.assign_poster_as }}
                        </h5>
                        <div class="d-flex flex-wrap justify-content-center gap-3">
                            <template v-for="(info, key) in mediaStore.postersAssignInfo" :key="key">
                                <el-badge :value="info.count" :is-dot="!info.count" type="success">
                                    <el-button @click="toggleAssignPoster(key)" type="primary" class="assign-poster-btn">
                                        {{ info.locale }}
                                    </el-button>
                                </el-badge>
                            </template>
                        </div>
                    </div>
                </div>

                <el-table
                    ref="multipleTablePoster"
                    :data="currentPostersData"
                    style="width: 100%"
                    @selection-change="handleSelectPosterChange"
                    :default-sort="{ prop: 'locale', order: 'ascending' }"
                >
                    <el-table-column type="selection" width="55" />
                    <el-table-column property="id" label="ID" width="80" />
                    <el-table-column property="source" :label="singleData.locale.poster_source" width="90" align="center" sortable>
                        <template v-slot:default="scope">
                            <span class="source-badge" :class="`badge-${scope.row.source}`">
                                {{ scope.row.source.toUpperCase() }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column property="locale" label="🌐" width="80" align="center" sortable :sort-method="customLocaleSort">
                        <template v-slot:default="scope">
                            <span class="locale-badge" :style="getLocaleBadgeStyle(scope.row.locale)">
                                {{ scope.row.locale || 'null' }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column property="width" :label="singleData.locale.resolution" width="120">
                        <template v-slot:default="scope">{{ scope.row.width }}x{{ scope.row.height }}</template>
                    </el-table-column>
                    <el-table-column property="srcset" :label="singleData.locale.photo" width="150">
                        <template v-slot:default="scope">
                            <el-image :src="scope.row.srcset" fit="contain" class="poster-image" />
                        </template>
                    </el-table-column>
                    <el-table-column property="status_poster" :label="singleData.locale.assign_status" width="150">
                        <template v-slot:default="scope">
                            <el-text v-if="scope.row.status_poster" type="success" class="fw-bold">{{ scope.row.status_poster }}</el-text>
                            <el-text type="danger" class="fw-bold" v-else>{{ singleData.locale.no_assigned }}</el-text>
                        </template>
                    </el-table-column>
                    <el-table-column property="src" :label="singleData.locale.link" show-overflow-tooltip>
                        <template v-slot:default="scope">
                            <a :href="scope.row.src" target="_blank">{{ scope.row.src }}</a>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="mt-4 d-flex flex-wrap gap-2">
                    <el-button @click="handleSelectPoster()" type="info">
                        {{ singleData.locale.clear_selection }}
                    </el-button>
                    <el-button @click="handleRemovePoster()" type="danger">
                        {{ singleData.locale.delete_selection }}
                    </el-button>
                    <el-button @click="submitSync('posters_sync')" type="warning" :loading='!!disabledBtnSync'>
                        {{ singleData.locale.posters_sync }}
                    </el-button>

                    <template v-if="currentPostersHasMore">
                        <el-button type="info" @click="handlePosterLoadMore" class="w-100">
                            {{ singleData.locale.next_page }}
                            <el-icon class="el-icon--right"><ArrowRight /></el-icon>
                        </el-button>
                    </template>
                </div>
            </el-dialog>

            <el-form
                ref="ruleFormRef"
                :model="ruleForm"
                label-width="120px"
                class="demo-ruleForm mt-3"
                :size="formSize"
                status-icon
            >
                <el-form-item :label="singleData.locale.title" prop="title">
                    <el-input v-model="ruleForm.title" maxlength="150" show-word-limit />
                </el-form-item>
                <el-form-item :label="singleData.locale.original_title" prop="title_oiginal">
                    <el-input v-model="ruleForm.original_title" maxlength="150" show-word-limit />
                </el-form-item>
                <el-form-item :label="singleData.locale.year_release" prop="year">
                    <el-input v-model="ruleForm.year_release" maxlength="10" show-word-limit />
                </el-form-item>
                <el-form-item :label="singleData.locale.release_date" prop="release_date">
                    <el-input v-model="ruleForm.release_date" maxlength="50" show-word-limit />
                </el-form-item>
                <el-form-item :label="singleData.locale.restriction" prop="restrictions">
                    <el-input v-model="ruleForm.restrictions" maxlength="10" show-word-limit />
                </el-form-item>
                <el-form-item :label="singleData.locale.runtime" prop="runtime">
                    <el-input v-model="ruleForm.runtime" maxlength="10" show-word-limit />
                </el-form-item>
                <el-form-item :label="singleData.locale.rating" prop="rating">
                    <el-input v-model="ruleForm.rating" maxlength="5" show-word-limit />
                </el-form-item>
                <el-form-item :label="singleData.locale.budget" prop="budget">
                    <el-input v-model="ruleForm.budget" maxlength="128" show-word-limit />
                </el-form-item>
                <el-form-item :label="singleData.locale.story" prop="story">
                    <el-input v-model="ruleForm.story_line" maxlength="3000" show-word-limit :autosize="{ minRows: 4, maxRows: 4 }" type="textarea" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="submitForm(ruleFormRef)" :loading="!!disabledBtnUpdate">
                        {{ singleData.locale.update }}
                    </el-button>
                    <el-button type="warning" @click="submitSync('info_sync')" :loading='disabledBtnInfoSync'>
                        {{ singleData.locale.sync_data}}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-col>
    </el-row>

    <el-row v-else>
        <el-col :span="24">
            <div class="text-center p-5">
                <el-empty :description="$t('not_enough_data') || 'Недостаточно данных'" :image-size="150" />
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
import { Operation, ArrowRight, Picture, PictureFilled, Check, Close, Warning, InfoFilled } from '@element-plus/icons-vue';
// 👇 Добавили ElNotification в импорт
import { ElMessage, ElMessageBox, ElNotification, TabsPaneContext } from 'element-plus';
import { ref, reactive, watch, computed, onMounted, nextTick, onUnmounted } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
const moviesStore = useMoviesStore();
const mediaStore = useMediaStore();
const categoryStore = useCategoriesStore();
const progressBarStore = useProgressBarStore();
const languageStore = useLanguageStore();

const { singleData, disabledBtnUpdate, disabledBtnInfoSync, disabledBtnSync, locale, error } = storeToRefs(moviesStore);
const { postersAssignInfo, imagesData, postersData, disabledBtnResize, countImg, countPoster } = storeToRefs(mediaStore);
const { percentageSync, syncActionsHistory, resizeActionsHistory } = storeToRefs(progressBarStore);
const { optionsCats } = storeToRefs(categoryStore);
const { watcherLang } = storeToRefs(languageStore);

const activeTabName = ref('first');
const multipleTablePoster = ref();
const multipleSelectImage = ref([]);
const multipleSelectPoster = ref([]);
const formSize = ref('default');
const ruleFormRef = ref();
const ruleForm = ref(singleData);
const posterType = ref('poster');
const customTableRef = ref(null);
const tableBodyRef = ref(null);
const selectAll = ref(false);
const selectionIndeterminate = ref(false);
let sortableInstance = null;

// 👇 Переменная для отслеживания длины истории, чтобы показывать уведомление только для новых записей
let lastResizeHistoryLength = 0;

const propsCascader = {
    multiple: true,
    checkStrictly: true,
};

const propsSteps = reactive({
    active: 0,
    finishStatus: "wait",
});

const propPublishBtn = reactive({
    disabled: 1,
    plain: 1,
});

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

const currentImagesHasMore = computed(() => {
    return mediaStore.activeImageSource === 'tmdb'
        ? mediaStore.hasMoreImagesTmdb
        : mediaStore.hasMoreImagesImdb;
});

const currentPostersHasMore = computed(() => {
    return mediaStore.activePosterSource === 'tmdb'
        ? mediaStore.hasMorePostersTmdb
        : mediaStore.hasMorePostersImdb;
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

// 👇 Watcher для автоматического показа push-уведомлений при изменении истории ресайза
watch(resizeActionsHistory, (newHistory) => {
    if (newHistory.length > lastResizeHistoryLength) {
        const lastAction = newHistory[newHistory.length - 1];
        // Показываем уведомление только для финальных статусов (не для 'info')
        if (lastAction.status === 'success' || lastAction.status === 'warning' || lastAction.status === 'error') {
            showResizeNotification(lastAction);
        }
        lastResizeHistoryLength = newHistory.length;
    }
}, { deep: true });

onMounted(() => {
    syncActionsHistory.value = [];
    resizeActionsHistory.value = []; // Очищаем при монтировании
    lastResizeHistoryLength = 0;
    moviesStore.showItem();
    categoryStore.getCategories();
});

onUnmounted(() => {
    if (sortableInstance) {
        sortableInstance.destroy();
    }
});

const currentImagesData = computed(() => {
    return mediaStore.activeImageSource === 'tmdb'
        ? mediaStore.imagesDataTmdb
        : mediaStore.imagesDataImdb;
});

const currentPostersData = computed(() => {
    return mediaStore.activePosterSource === 'tmdb'
        ? mediaStore.postersDataTmdb
        : mediaStore.postersDataImdb;
});

const showResizeNotification = (action: any) => {
    if (!action || action.status === 'info') return;

    const statusIcon = action.status === 'success' ? '✅' : (action.status === 'warning' ? '⚠️' : '❌');
    const categoryName = getCategoryName(action.category) || action.title || 'Преобразование изображений';

    let summaryText = '';
    if (action.status === 'error') {
        summaryText = `Ошибка: не обработано <strong>${action.total - action.success_count}</strong> из <strong>${action.total}</strong>`;
    } else if (action.status === 'warning') {
        summaryText = `Частично: обработано <strong>${action.success_count}</strong> из <strong>${action.total}</strong>`;
    }

    let messageHtml = `<div style="max-height: 350px; overflow-y: auto; margin-top: 10px; padding-right: 5px;">`;
    messageHtml += `<p style="margin-bottom: 12px; color: #606266; font-size: 14px;">${summaryText}</p>`;

    // Блок ошибок и предупреждений (выводим первым, так как это важнее всего)
    if (action.issues && action.issues.length > 0) {
        const issuesHtml = action.issues.map((issue: string) =>
            `<li style="margin-bottom: 6px; padding: 8px; background: #fef0f0; border-left: 3px solid #f56c6c; border-radius: 4px; font-size: 13px; color: #f56c6c; word-break: break-word;">
                ⚠️ ${issue}
            </li>`
        ).join('');
        messageHtml += `<p style="margin-top: 12px; margin-bottom: 6px; font-weight: bold; color: #f56c6c; font-size: 13px;">Ошибки и предупреждения:</p>`;
        messageHtml += `<ul style="list-style: none; padding: 0; margin: 0;">${issuesHtml}</ul>`;
    }

    // Блок обработанных файлов (если их немного, или скроллящийся список)
    if (action.files && action.files.length > 0) {
        const filesHtml = action.files.map((file: string) =>
            `<li style="margin-bottom: 4px; font-size: 12px; color: #67c23a; word-break: break-all;">✓ ${file}</li>`
        ).join('');
        messageHtml += `<p style="margin-top: 12px; margin-bottom: 6px; font-weight: bold; color: #67c23a; font-size: 13px;">Обработанные файлы (${action.files.length}):</p>`;
        messageHtml += `<ul style="list-style: none; padding: 0; margin: 0; max-height: 120px; overflow-y: auto; background: #f0f9ff; padding: 8px; border-radius: 4px;">${filesHtml}</ul>`;
    }

    messageHtml += `</div>`;

    ElNotification({
        title: `${statusIcon} ${categoryName}`,
        type: action.status === 'error' ? 'error' : (action.status === 'warning' ? 'warning' : 'success'),
        duration: action.status === 'error' || action.status === 'warning' ? 0 : 5000, // Ошибки не исчезают сами
        dangerouslyUseHTMLString: true,
        customClass: 'sync-report-notification',
        message: messageHtml,
        position: 'top-right',
    });
};

const openImagesModal = () => {
    mediaStore.isImagesModalVisible = true;
    if (imagesData.value.length === 0) {
        mediaStore.getImages(singleData.value.slug);
    }
    nextTick(() => {
        setTimeout(initSortable, 200);
    });
};

const openPostersModal = () => {
    mediaStore.isPostersModalVisible = true;
    if (postersData.value.length === 0) {
        mediaStore.getPosters(singleData.value.slug);
    }
};

const handleImageSourceChange = () => {
    nextTick(() => {
        setTimeout(initSortable, 200);
    });
};

const handleCheckboxClick = (event: Event, item: any) => {
    event.stopPropagation();
    event.preventDefault();

    const index = multipleSelectImage.value.indexOf(item.id);
    if (index > -1) {
        multipleSelectImage.value.splice(index, 1);
    } else {
        multipleSelectImage.value.push(item.id);
    }
    updateSelectAllState();
};

const handleCategoryChange = async (value: any) => {
    try {
        await ElMessageBox.confirm('Are you sure?', 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        });

        if (value.length <= 4) {
            await categoryStore.setCategories({
                id_movie: singleData.value.id_movie,
                categories: value ?? [],
                tags: singleData.value.genres ?? [],
                viewed: singleData.value.collection.viewed ?? false,
                short: singleData.value.collection.short ?? false,
                adult: singleData.value.collection.adult ?? false,
            });

            if (value.length === 0) {
                ElMessage({ type: 'success', message: 'Collection deleted' });
            }
        } else {
            ElMessage({ type: 'info', message: 'No more than 4 collections for one movie!' });
        }
    } catch (error) {
        ElMessage({ type: 'info', message: 'Select collection canceled' });
    }
};

const handleImageLoadMore = () => {
    if (!currentImagesHasMore.value) return;
    mediaStore.updateImagePageSize(singleData.value.slug);
};

const handlePosterLoadMore = () => {
    if (!currentPostersHasMore.value) return;
    mediaStore.updatePosterPageSize(singleData.value.slug);
};

const handleClick = (tab: TabsPaneContext, event: Event) => {};

const handleSelectPoster = (rows?: []) => {
    if (rows) {
        rows.forEach((row) => {
            multipleTablePoster.value?.toggleRowSelection(row, undefined);
        });
    } else {
        multipleTablePoster.value?.clearSelection();
    }
};

const clearPosterSelection = () => {
    multipleTablePoster.value?.clearSelection();
};

const handleRowClick = (event: Event, item: any) => {
    if ((event.target as HTMLElement).closest('.el-checkbox')) {
        return;
    }

    const index = multipleSelectImage.value.indexOf(item.id);
    if (index > -1) {
        multipleSelectImage.value.splice(index, 1);
    } else {
        multipleSelectImage.value.push(item.id);
    }
    updateSelectAllState();
};

const toggleSelectAllImages = () => {
    if (selectAll.value) {
        multipleSelectImage.value = currentImagesData.value.map((item: any) => item.id);
    } else {
        multipleSelectImage.value = [];
    }
    updateSelectAllState();
};

const updateSelectAllState = () => {
    if (multipleSelectImage.value.length === 0) {
        selectAll.value = false;
        selectionIndeterminate.value = false;
    } else if (multipleSelectImage.value.length === currentImagesData.value.length) {
        selectAll.value = true;
        selectionIndeterminate.value = false;
    } else {
        selectionIndeterminate.value = true;
    }
};

const handleSelectImage = () => {
    multipleSelectImage.value = [];
    selectAll.value = false;
    selectionIndeterminate.value = false;
};

watch(currentImagesData, () => {
    nextTick(() => {
        setTimeout(initSortable, 100);
        const ids = new Set(multipleSelectImage.value);
        multipleSelectImage.value = currentImagesData.value.filter((item: any) => ids.has(item.id)).map((item: any) => item.id);
        updateSelectAllState();
    });
});

const handleSelectPosterChange = (val?: []) => {
    multipleSelectPoster.value = [];
    val?.filter(function(arr: any) {
        multipleSelectPoster.value.push(arr.id);
    });
};

const getUnique = (arr: any[]) => {
    return arr.filter((el, ind) => ind === arr.indexOf(el));
};

const handleRemoveImage = () => {
    if (multipleSelectImage.value.length) {
        getUnique(multipleSelectImage.value);
        ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectImage.value.length} pictures will be deleted. Continue?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            mediaStore.removeMultipleImages(multipleSelectImage.value, 'images', singleData.value.slug);
            handleSelectImage();
            ElMessage({ type: 'success', message: 'Delete completed' });
        }).catch(() => {
            ElMessage({ type: 'info', message: 'Delete canceled' });
        });
    } else {
        ElMessage.error('No pictures have been selected');
    }
};

const handleMoveToPosters = () => {
    if (multipleSelectImage.value.length) {
        getUnique(multipleSelectImage.value);
        ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectImage.value.length} pictures will be moved to posters. Continue?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            mediaStore.moveMultipleImages(multipleSelectImage.value, singleData.value.slug);
            handleSelectImage();
            ElMessage({ type: 'success', message: 'Move to posters completed' });
        }).catch(() => {
            ElMessage({ type: 'info', message: 'Move to posters canceled' });
        });
    } else {
        ElMessage.error('No pictures have been selected');
    }
};

const handleRemovePoster = () => {
    if (multipleSelectPoster.value.length) {
        getUnique(multipleSelectPoster.value);
        ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectPoster.value.length} posters will be deleted. Continue?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            mediaStore.removeMultipleImages(multipleSelectPoster.value, 'posters', singleData.value.slug);
            clearPosterSelection();
            ElMessage({ type: 'success', message: 'Delete completed' });
        }).catch(() => {
            ElMessage({ type: 'info', message: 'Delete canceled' });
        });
    } else {
        ElMessage.error('No pictures have been selected');
    }
};

const toggleAssignPoster = (category: string) => {
    if (multipleSelectPoster.value.length) {
        ElMessageBox.confirm(`Are you sure? Selected ${multipleSelectPoster.value.length} posters will be assign. Continue?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            mediaStore.assignPoster(multipleSelectPoster.value, category, singleData.value.slug);
            clearPosterSelection();
            ElMessage({ type: 'success', message: 'Assign completed' });
        }).catch(() => {
            ElMessage({ type: 'info', message: 'Categorization canceled' });
        });
    } else {
        ElMessage.error('No pictures have been selected');
    }
};

const submitForm = async (formEl: any) => {
    if (!formEl) return;
    await formEl.validate((valid: boolean, fields: any) => {
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
                ElMessage({ type: 'info', message: 'Update canceled' });
            });
        } else {
            console.log('error submit!', fields);
        }
    });
};

const submitSync = (typeSync: string) => {
    ElMessageBox.confirm(singleData.value.locale.sync_notice + '\n' + `Are you sure?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        syncActionsHistory.value = [];
        progressBarStore.getSyncCurrentPercentage();
        moviesStore.syncItem({
            id: route.params.id,
            type: singleData.value.slug,
            posterType: posterType.value,
            typeSync: typeSync,
        });
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Sync canceled' });
    });
};

const submitResize = () => {
    ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        // Очищаем историю перед новым запуском, чтобы уведомления не дублировались при повторном нажатии
        resizeActionsHistory.value = [];
        lastResizeHistoryLength = 0;

        progressBarStore.getResizeCurrentPercentage();
        mediaStore.resizeAllImages(route.params.id, singleData.value.slug);
        propPublishBtn.plain = 0;
        propPublishBtn.disabled = 0;
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Resize canceled' });
    });
};

const submitPublished = () => {
    ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
        confirmButtonText: 'OK',
        cancelButtonText: 'Cancel',
        type: 'warning',
    }).then(() => {
        moviesStore.publicationItem(route.params.id);
    }).catch(() => {
        ElMessage({ type: 'info', message: 'Publication canceled' });
    });
};

const handleSaveOrder = async () => {
    try {
        const orderedIds = currentImagesData.value.map((item: any) => item.id);
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
    if (!el) return;

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

            const currentData = currentImagesData.value;
            const movedItem = currentData.splice(oldIndex, 1)[0];
            currentData.splice(newIndex, 0, movedItem);

            const selectedIds = [...multipleSelectImage.value];
            multipleSelectImage.value = selectedIds;
        },
    });
};

const getLocaleBadgeStyle = (localeStr: string | null | undefined) => {
    if (!localeStr) return { backgroundColor: '#f4f4f5', color: '#909399' };

    let hash = 0;
    for (let i = 0; i < localeStr.length; i++) {
        hash = localeStr.charCodeAt(i) + ((hash << 5) - hash);
    }

    const hue = Math.abs(hash) % 360;
    const bg = `hsl(${hue}, 75%, 88%)`;
    const text = `hsl(${hue}, 75%, 30%)`;

    return { backgroundColor: bg, color: text };
};

const customLocaleSort = (a: any, b: any) => {
    const locA = a.locale ? String(a.locale).toLowerCase().trim() : '';
    const locB = b.locale ? String(b.locale).toLowerCase().trim() : '';

    const getPriority = (loc: string) => {
        if (!loc) return 0;
        if (loc === 'ru') return 1;
        if (loc === 'en') return 2;
        return 3;
    };

    const priorityA = getPriority(locA);
    const priorityB = getPriority(locB);

    if (priorityA !== priorityB) {
        return priorityA - priorityB;
    }

    return locA.localeCompare(locB);
};

const getCategoryName = (category: string) => {
    const names: Record<string, string> = {
        'original_poster': 'Оригинальный постер',
        'russian_poster': 'Русский постер',
        'alt_posters': 'Альтернативные постеры',
        'characters_posters': 'Постеры персонажей',
        'wallpaper': 'Фоновое изображение (Wallpaper)',
        'backdrops': 'Изображения (Backdrops)',
    };
    return names[category] || category;
};

const getColor = (status: string) => {
    const colors: Record<string, string> = {
        'info': '#909399',
        'success': '#67C23A',
        'warning': '#E6A23C',
        'error': '#F56C6C',
    };
    return colors[status] || '#909399';
};
</script>

<style lang="scss" scoped>
.el-row { margin-bottom: 20px; }
.el-row:last-child { margin-bottom: 0; }
.el-col { border-radius: 4px; }
.grid-content { border-radius: 4px; min-height: 36px; }
.el-progress :deep(.el-progress__text) { min-width: 0; }
.image-type { display: flex; align-items: flex-start; justify-content: space-between; }
.dots { flex-grow: 1; border-bottom: 1px dashed black; margin: 0 10px 7px; }
.custom-table { width: 100%; border: 1px solid #ebeef5; border-radius: 4px; overflow: hidden; }
.table-header, .table-row { display: flex; position: relative; z-index: 1; align-items: center; min-height: 48px; border-bottom: 1px solid #ebeef5; }
.table-header { font-weight: bold; color: #606266; background: #f5f7fa; }
.table-row { background: #fff; transition: background 0.2s; }
.table-row:hover { background: #f5f7fa; }
.table-row.selected { background: #c1e2fe !important; }
.table-cell { display: flex; justify-content: center; align-items: center; padding: 0 12px; box-sizing: border-box; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.table-body { max-height: 600px; overflow-y: auto; }
.sortable-ghost { opacity: 0.5; background: #e3f2fd !important; }
.sortable-chosen { background: #bbdefb !important; }
.sortable-drag { background: #90caf9 !important; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.2); border-radius: 4px; opacity: 0.95; }
.drag-handle { transition: color 0.2s; }
.drag-handle:hover { color: #409eff; }
.source-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
.badge-tmdb { background-color: #e6fffa; color: #2c7a7b; border: 1px solid #81e6d9; }
.badge-imdb { background-color: #F5C518; color: #000000; border: 1px solid #d4a90f; font-weight: 800; }
.table-row.source-tmdb { border-left: 3px solid #38b2ac; }
.table-row.source-imdb { border-left: 3px solid #F5C518; }
.locale-badge { display: inline-flex; align-items: center; justify-content: center; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; transition: all 0.2s ease; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); min-width: 45px; }
.poster-image { width: 100%; height: auto; max-height: 200px; object-fit: contain; }
.posters-assign-block { position: sticky; top: 0; z-index: 10; padding: 16px 24px; background-color: #ffffff; border-bottom: 1px solid #ebeef5; border-radius: 8px 8px 0 0; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03); display: flex; align-items: center; justify-content: space-between; gap: 40px; }
.poster-source-selector { flex-shrink: 0; }
.poster-assign-controls { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; }
.poster-assign-controls h5 { margin: 0 0 20px; font-size: 13px; font-weight: 600; color: #909399; text-transform: uppercase; letter-spacing: 0.5px; }
.assign-poster-btn { min-width: 120px; }
.checklist-item { display: flex; align-items: flex-start; margin-bottom: 12px; gap: 12px; animation: slideIn 0.3s ease-out forwards; &:last-child { margin-bottom: 0; } }
.action-content { flex: 1; min-width: 0; overflow: hidden; }
.detailed-action { width: 100%; max-width: 100%; overflow: hidden; }
.action-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.issue-list { display: flex; flex-direction: column; gap: 4px; word-break: break-word; }
.issue-item { font-size: 13px; display: flex; align-items: center; }
.ml-2 { margin-left: 8px; }
.mr-1 { margin-right: 4px; }
.mb-1 { margin-bottom: 4px; }
.text-muted { color: #909399; font-size: 13px; }
.text-danger { color: #F56C6C; }
.sync-checklist-container { background-color: #f9fafb; border-radius: 6px; padding: 8px 12px; border: 1px solid #e5e7eb; }
.checklist { list-style: none; padding: 0; margin: 0; }
.action-text { font-weight: 500; font-size: 14px; }
@keyframes slideIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
.source-badge-el { margin-right: 40px; }
.badge-tmdb-el :deep(.el-badge__content) { background-color: #e6fffa !important; color: #2c7a7b !important; border: 1px solid #81e6d9 !important; font-weight: 700; font-size: 11px; padding: 0 8px !important; height: 20px !important; line-height: 18px !important; border-radius: 10px !important; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
.badge-imdb-el :deep(.el-badge__content) { background-color: #F5C518 !important; color: #000000 !important; border: 1px solid #d4a90f !important; font-weight: 800; font-size: 11px; padding: 0 8px !important; height: 20px !important; line-height: 18px !important; border-radius: 10px !important; box-shadow: 0 1px 2px rgba(0,0,0,0.15); }

:deep(.el-notification) {
    width: auto !important;
    max-width: 450px !important;

    .el-notification__content {
        text-align: left !important;
        p {
            margin: 0;
            line-height: 1.5;
        }
    }
}

:deep(.sync-report-notification) {
    .el-notification__content {
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    }
}
</style>
