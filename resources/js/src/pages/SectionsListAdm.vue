<template>
    <h3 class="text-center mt-3 mb-3">Назначение разделов</h3>
    <el-tabs v-model="activeSectionId" :tab-position="tabPosition" class="mt-5">
        <el-tab-pane v-for="(item) in sections" :key="item.id" :label="item.title_ru ?? item.title_en" :name="item.id.toString()">
            <div><strong>ID: </strong>{{ item.id }}</div>
            <div><strong>Value: </strong>{{ item.value }}</div>
            <div><strong>Index movie ID: <el-text class="mx-1" type="success">{{item.index_id}}</el-text></strong></div>
            <div class="mt-3">
                <strong>Сменить ID для индекса текущей секции: </strong>
                <el-input v-model="item.index_id" maxlength="30" :placeholder="item.index_id" type="text" class="el-col-xl-6" />
            </div>
            <div class="mt-3">
                <strong>Название секции (RU): </strong>
                <el-input v-model="item.title_ru" maxlength="30" :placeholder="item.title_ru" show-word-limit type="text" class="el-col-xl-6" />
            </div>
            <div class="mt-3">
                <strong>Название секции (EN): </strong>
                <el-input v-model="item.title_en" maxlength="30" :placeholder="item.title_en" show-word-limit type="text" class="el-col-xl-6" />
            </div>
            <el-button type="primary" @click="handleUpdateSection(item)" class="btn btn-primary my-4">Обновить секцию</el-button>
            <el-button type="warning" @click="handleSyncWithTMDB(item)" class="btn btn-warning my-4 ml-2" :loading="tmdbLoading">
                <el-icon><Refresh /></el-icon>
                Синхронизация с TMDB API
            </el-button>
            <el-button type="info" @click="handleOpenSuggestedMovies(item)" class="btn btn-info my-4 ml-2" :loading="suggestedMoviesLoading">
                <el-icon><Film /></el-icon>
                Назначить фильм секции
            </el-button>
            <el-collapse v-model="activeCollapse" class="mt-3">
                <el-collapse-item title="Ручная настройки изображений и постеров" name="images">
                    <el-form
                        label-width="200px"
                        v-if="sectionImages[item.id]"
                        :rules="formRules"
                        :ref="setFormRef(item.id)"
                        :model="sectionImages[item.id]"
                    >
                        <!-- Логотип раздела -->
                        <el-form-item label="Логотип раздела" prop="section_logo">
                            <el-row :gutter="20">
                                <el-upload
                                    v-model:file-list="sectionImages[item.id].section_logo"
                                    class="upload-demo"
                                    action="#"
                                    :limit="1"
                                    accept="image/jpeg,image/png,image/gif"
                                    :auto-upload="false"
                                    :on-preview="handlePreview"
                                    :on-change="(file, fileList) => handleFileChange(file, fileList, item.id, 'section_logo')"
                                    :on-remove="(file, fileList) => handleRemove(file, fileList, item.id, 'section_logo')"
                                    list-type="picture-card"
                                >
                                    <el-icon><Plus /></el-icon>
                                    <template #tip>
                                        <div class="el-upload__tip">jpg/png/gif</div>
                                    </template>
                                </el-upload>
                            </el-row>
                        </el-form-item>

                        <!-- Бэкдроп -->
                        <el-form-item label="Бэкдроп" prop="backdrop">
                            <el-row :gutter="20">
                                <el-upload
                                    v-model:file-list="sectionImages[item.id].backdrop"
                                    class="upload-demo"
                                    action="#"
                                    :limit="1"
                                    accept="image/jpeg,image/png,image/gif"
                                    :auto-upload="false"
                                    :on-preview="handlePreview"
                                    :on-change="(file, fileList) => handleFileChange(file, fileList, item.id, 'backdrop')"
                                    :on-remove="(file, fileList) => handleRemove(file, fileList, item.id, 'backdrop')"
                                    list-type="picture-card"
                                >
                                    <el-icon><Plus /></el-icon>
                                    <template #tip>
                                        <div class="el-upload__tip">jpg/png/gif</div>
                                    </template>
                                </el-upload>
                            </el-row>
                        </el-form-item>

                        <!-- Логотип фильма (RU) -->
                        <el-form-item label="Логотип фильма (RU)">
                            <el-row :gutter="20">
                                <el-upload
                                    v-model:file-list="sectionImages[item.id].film_logo_ru"
                                    class="upload-demo"
                                    action="#"
                                    :limit="1"
                                    accept="image/jpeg,image/png,image/gif"
                                    :auto-upload="false"
                                    :on-preview="handlePreview"
                                    :on-change="(file, fileList) => handleFileChange(file, fileList, item.id, 'film_logo_ru')"
                                    :on-remove="(file, fileList) => handleRemove(file, fileList, item.id, 'film_logo_ru')"
                                    list-type="picture-card"
                                >
                                    <el-icon><Plus /></el-icon>
                                    <template #tip>
                                        <div class="el-upload__tip">jpg/png/gif</div>
                                    </template>
                                </el-upload>
                            </el-row>
                        </el-form-item>

                        <!-- Логотип фильма (EN) -->
                        <el-form-item label="Логотип фильма (EN)">
                            <el-row :gutter="20">
                                <el-upload
                                    v-model:file-list="sectionImages[item.id].film_logo_en"
                                    class="upload-demo"
                                    action="#"
                                    :limit="1"
                                    accept="image/jpeg,image/png,image/gif"
                                    :auto-upload="false"
                                    :on-preview="handlePreview"
                                    :on-change="(file, fileList) => handleFileChange(file, fileList, item.id, 'film_logo_en')"
                                    :on-remove="(file, fileList) => handleRemove(file, fileList, item.id, 'film_logo_en')"
                                    list-type="picture-card"
                                >
                                    <el-icon><Plus /></el-icon>
                                    <template #tip>
                                        <div class="el-upload__tip">jpg/png/gif</div>
                                    </template>
                                </el-upload>
                            </el-row>
                        </el-form-item>

                        <!-- Постеры -->
                        <el-form-item label="Постеры (7-10)">
                            <el-row :gutter="20">
                                <el-input
                                    v-model="posterIdsInput[item.id]"
                                    placeholder="Введите ID через запятую (ttxxxxxxx,ttxxxxxxx,...)"
                                    @input="handlePosterIdsInput(item.id)"
                                    class="mt-2"
                                ></el-input>
                                <div class="mt-2">
                                    <el-tag
                                        v-for="(id, index) in sectionImages[item.id].poster_ids"
                                        :key="index"
                                        closable
                                        @close="removePosterId(item.id, index)"
                                        class="mr-2 mb-2"
                                    >
                                        {{ id }}
                                    </el-tag>
                                </div>
                                <el-alert
                                    v-if="sectionImages[item.id].poster_ids.length > 0 && (sectionImages[item.id].poster_ids.length < 7 || sectionImages[item.id].poster_ids.length > 10)"
                                    type="warning"
                                    :closable="false"
                                    show-icon
                                >
                                    Если ID указаны, должно быть от 7 до 10.
                                </el-alert>
                                <el-upload
                                    v-model:file-list="sectionImages[item.id].posters"
                                    class="upload-demo"
                                    action="#"
                                    :limit="10"
                                    accept="image/jpeg,image/png"
                                    :auto-upload="false"
                                    :on-preview="handlePreview"
                                    :on-remove="(file, fileList) => handleRemove(file, fileList, item.id, 'posters')"
                                    list-type="picture"
                                    multiple
                                >
                                    <template #tip>
                                        <div class="el-upload__tip">jpg/png/gif, от 7 до 10 постеров</div>
                                    </template>
                                </el-upload>
                            </el-row>
                        </el-form-item>
                    </el-form>
                    <div v-else class="text-center py-4 text-gray-500">Загрузка данных секции...</div>
                </el-collapse-item>
            </el-collapse>
        </el-tab-pane>
    </el-tabs>
    <el-button
        type="success"
        @click="handleUpdateImages"
        class="mt-4"
        :loading="updatingImages"
    >
        Обновить изображения для текущей секции
    </el-button>

    <!-- Диалог выбора изображений TMDB -->
    <el-dialog
        v-model="tmdbDialogVisible"
        title="Выбор изображений из TMDB"
        width="90%"
        :close-on-click-modal="false"
    >
        <div v-if="tmdbImages">
            <el-alert
                :title="'Фильм: ' + tmdbImages.title + ' (TMDB ID: ' + tmdbImages.tmdb_id + ')'"
                type="info"
                :closable="false"
                show-icon
                class="mb-4"
            />

            <!-- Логотипы RU -->
            <div class="mb-6">
                <h4 class="mb-3">
                    🇷🇺 Логотипы (RU) - выберите 1
                    <el-tag v-if="tmdbImages?.logos?.ru?.length" size="small" type="info" class="ml-2">
                        {{ tmdbImages.logos.ru.length }} шт.
                    </el-tag>
                </h4>
                <el-row :gutter="20">
                    <el-col :xs="12" :sm="8" :md="6" :lg="6" :xl="4" v-for="(logo, index) in tmdbImages?.logos?.ru || []" :key="'ru-' + index">
                        <el-card
                            :body-style="{ padding: '15px', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }"
                            :class="{ 'selected-card': selectedTMDBImages.logos.ru?.path === logo.path }"
                            @click="selectTMDBLogo('ru', logo)"
                            class="image-card"
                            style="cursor: pointer; margin-bottom: 15px;"
                        >
                            <div style="width: 100%; text-align: center;">
                                <img
                                    :src="logo.url_w200"
                                    :alt="'Logo RU ' + index"
                                    style="max-width: 100%; max-height: 150px; object-fit: contain;"
                                    loading="lazy"
                                />
                                <div class="text-center mt-2 text-sm text-gray-500">
                                    {{ logo.width }}×{{ logo.height }}
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="24" v-if="!tmdbImages?.logos?.ru?.length">
                        <el-alert type="warning" :closable="false">Логотипы RU не найдены</el-alert>
                    </el-col>
                </el-row>
            </div>

            <!-- Логотипы EN -->
            <div class="mb-6">
                <h4 class="mb-3">
                    🇬 Логотипы (EN) - выберите 1
                    <el-tag v-if="tmdbImages?.logos?.en?.length" size="small" type="info" class="ml-2">
                        {{ tmdbImages.logos.en.length }} шт.
                    </el-tag>
                </h4>
                <el-row :gutter="20">
                    <el-col :xs="12" :sm="8" :md="6" :lg="6" :xl="4" v-for="(logo, index) in tmdbImages?.logos?.en || []" :key="'en-' + index">
                        <el-card
                            :body-style="{ padding: '15px', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }"
                            :class="{ 'selected-card': selectedTMDBImages.logos.en?.path === logo.path }"
                            @click="selectTMDBLogo('en', logo)"
                            class="image-card"
                            style="cursor: pointer; margin-bottom: 15px;"
                        >
                            <div style="width: 100%; text-align: center;">
                                <img
                                    :src="logo.url_w200"
                                    :alt="'Logo EN ' + index"
                                    style="max-width: 100%; max-height: 150px; object-fit: contain;"
                                    loading="lazy"
                                />
                                <div class="text-center mt-2 text-sm text-gray-500">
                                    {{ logo.width }}×{{ logo.height }}
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="24" v-if="!tmdbImages?.logos?.en?.length">
                        <el-alert type="warning" :closable="false">Логотипы EN не найдены</el-alert>
                    </el-col>
                </el-row>
            </div>

            <!-- Backdrops -->
            <div class="mb-6">
                <h4 class="mb-3">
                    🖼️ Backdrops - выберите 1
                    <el-tag size="small" type="info" class="ml-2">
                        Загружено {{ tmdbPagination.loaded }} из {{ tmdbPagination.total }}
                    </el-tag>
                </h4>
                <el-row :gutter="20">
                    <el-col :xs="12" :sm="8" :md="6" :lg="6" :xl="4" v-for="(backdrop, index) in tmdbImages?.backdrops || []" :key="'bd-' + index">
                        <el-card
                            :body-style="{ padding: '0', overflow: 'hidden' }"
                            :class="{ 'selected-card': selectedTMDBImages.backdrop?.path === backdrop.path }"
                            @click="selectTMDBBackdrop(backdrop)"
                            class="backdrop-card"
                            style="cursor: pointer; margin-bottom: 15px;"
                        >
                            <div style="position: relative; width: 100%; padding-bottom: 56.25%;"> <!-- 16:9 aspect ratio -->
                                <img
                                    :src="backdrop.url_w300"
                                    :alt="'Backdrop ' + index"
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; background: #f5f5f5;"
                                    loading="lazy"
                                />
                            </div>
                            <div style="padding: 10px; text-align: center;">
                                <div class="text-sm text-gray-600">
                                    {{ backdrop.width }}×{{ backdrop.height }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    ⭐ {{ backdrop.vote_average.toFixed(1) }}
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- Кнопка Load More с правильным спинером -->
                <div v-if="tmdbPagination.hasMore" class="text-center mt-4">
                    <el-button
                        type="primary"
                        @click="loadMoreBackdrops"
                        :loading="loadingMore"
                        :disabled="loadingMore"
                        size="large"
                        :icon="loadingMore ? undefined : ArrowDown"
                    >
                        {{ loadingMore ? 'Загрузка...' : `Загрузить еще (${Math.min(tmdbPagination.limit, tmdbPagination.total - tmdbPagination.loaded)})` }}
                    </el-button>
                </div>

                <div v-else-if="tmdbPagination.total > 0 && tmdbPagination.loaded === tmdbPagination.total" class="text-center mt-4">
                    <el-alert
                        type="success"
                        :closable="false"
                        show-icon
                    >
                        Все backdrops загружены ({{ tmdbPagination.total }} шт.)
                    </el-alert>
                </div>
            </div>

            <div class="text-right">
                <el-button @click="closeTMDBDialog">Отмена</el-button>
                <el-button
                    type="primary"
                    @click="applyTMDBImages"
                    :loading="applyingImages"
                >
                    Применить выбранные изображения
                </el-button>
            </div>
        </div>
    </el-dialog>

    <el-image-viewer
        v-if="previewVisible"
        :url-list="[previewImageUrl]"
        @close="closePreview"
    />

    <!-- Диалог выбора фильма из предложенных -->
    <el-dialog
        v-model="suggestedMoviesDialogVisible"
        title="Назначение фильмов для секции"
        width="85%"
        :close-on-click-modal="false"
    >
        <div v-if="suggestedMoviesData">
            <el-alert
                :title="'Секция: ' + (suggestedMoviesData.section_title || '') + ' | Текущий главный фильм: ' + (suggestedMoviesData.current_imdb_id || 'не задан')"
                type="info"
                :closable="false"
                show-icon
                class="mb-4"
            />

            <!-- Инструкция -->
            <el-alert class="mb-4" :closable="false" show-icon type="info">
                <template #title>
                    <strong>Как пользоваться:</strong>
                </template>
                <div>
                    • Клик по карточке → добавить/убрать из <strong>дополнительных фильмов</strong> (нужно 7-10)<br>
                    • Клик по <strong>★</strong> в углу → назначить <strong>главным фильмом</strong> секции
                </div>
            </el-alert>

            <!-- Статус выбора -->
            <el-row :gutter="20" class="mb-4">
                <el-col :span="12">
                    <el-card :class="{ 'status-card-main': selectedSuggestedMovie }">
                        <div class="status-label">🎬 Главный фильм:</div>
                        <div class="status-value">
                            <template v-if="selectedSuggestedMovie">
                                <strong>{{ selectedSuggestedMovie.title }}</strong>
                                <el-tag size="small" type="primary" class="ml-2">{{ selectedSuggestedMovie.id_movie }}</el-tag>
                            </template>
                            <span v-else class="text-gray-400">не выбран</span>
                        </div>
                    </el-card>
                </el-col>
                <el-col :span="12">
                    <el-card :class="{
                    'status-card-poster': selectedPosterMovies.length >= 7 && selectedPosterMovies.length <= 10,
                    'status-card-warning': selectedPosterMovies.length > 0 && (selectedPosterMovies.length < 7 || selectedPosterMovies.length > 10)
                }">
                        <div class="status-label">🖼️ Дополнительные фильмы:</div>
                        <div class="status-value">
                            <strong>{{ selectedPosterMovies.length }}</strong> из 7-10
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- Выбранные дополнительные фильмы -->
            <div v-if="selectedPosterMovies.length > 0" class="mb-4">
                <h4 class="mb-2">Выбранные дополнительные фильмы:</h4>
                <el-tag
                    v-for="(movie, index) in selectedPosterMovies"
                    :key="movie.id_movie"
                    closable
                    @close="removePosterMovie(index)"
                    class="mr-2 mb-2"
                    type="success"
                    size="large"
                >
                    {{ index + 1 }}. {{ movie.title }}
                </el-tag>
            </div>

            <!-- ЕДИНЫЙ список фильмов -->
            <el-row :gutter="15" v-if="suggestedMoviesData.data && suggestedMoviesData.data.length > 0" class="movies-grid">
                <el-col
                    v-for="movie in suggestedMoviesData.data"
                    :key="movie.id_movie"
                    class="movie-col"
                >
                    <el-card
                        :body-style="{ padding: '0', position: 'relative' }"
                        :class="{
                        'selected-main-card': selectedSuggestedMovie?.id_movie === movie.id_movie,
                        'selected-poster-card': isPosterMovieSelected(movie.id_movie)
                    }"
                        class="movie-card"
                    >
                        <!-- Кнопка "Сделать главным" в углу -->
                        <div
                            class="main-star-btn"
                            :class="{ 'is-main': selectedSuggestedMovie?.id_movie === movie.id_movie }"
                            @click.stop="makeMainMovie(movie)"
                            :title="selectedSuggestedMovie?.id_movie === movie.id_movie ? 'Убрать роль главного' : 'Сделать главным фильмом'"
                        >
                            ★
                        </div>

                        <!-- Номер, если в дополнительных -->
                        <div
                            v-if="isPosterMovieSelected(movie.id_movie)"
                            class="poster-number"
                        >
                            {{ getPosterMovieIndex(movie.id_movie) + 1 }}
                        </div>

                        <!-- Область клика для дополнительных -->
                        <div
                            @click="togglePosterMovie(movie)"
                            style="cursor: pointer;"
                        >
                            <div style="position: relative; width: 100%; padding-bottom: 150%;">
                                <img
                                    v-if="movie.poster"
                                    :src="movie.poster"
                                    :alt="movie.title"
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;"
                                    loading="lazy"
                                />
                                <div v-else style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">
                                    Нет постера
                                </div>
                            </div>
                            <div style="padding: 10px;">
                                <div class="text-sm font-bold" style="min-height: 40px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                    {{ movie.title }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span v-if="movie.year">{{ movie.year }}</span>
                                    <span v-if="movie.rating"> | ⭐ {{ Number(movie.rating).toFixed(1) }}</span>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    {{ movie.id_movie }}
                                </div>
                            </div>
                        </div>
                    </el-card>
                </el-col>
            </el-row>

            <el-alert
                v-else
                type="warning"
                :closable="false"
                show-icon
            >
                Нет доступных фильмов для выбора в этой секции
            </el-alert>

            <!-- Кнопки действий -->
            <div class="text-right mt-4">
                <el-button @click="suggestedMoviesDialogVisible = false">Отмена</el-button>
                <el-button
                    type="primary"
                    @click="applySuggestedMovie"
                    :disabled="!selectedSuggestedMovie && selectedPosterMovies.length === 0"
                    :loading="applyingSuggestedMovie"
                >
                    Применить выбор
                </el-button>
            </div>
        </div>
        <div v-else class="text-center py-8">
            <el-icon class="is-loading" :size="40"><Loading /></el-icon>
            <p class="mt-2">Загрузка списка фильмов...</p>
        </div>
    </el-dialog>
</template>

<script setup>
import { storeToRefs } from "pinia";
import { useSectionStore } from "../store/sectionsStore";
import { ref, computed, onMounted, watch } from "vue";
import { ElMessage } from "element-plus";
import { Plus, Refresh, ArrowDown, Film, Loading } from '@element-plus/icons-vue';
import { ElImageViewer } from 'element-plus';

const sectionStore = useSectionStore();
const { sections, sectionImagesData } = storeToRefs(sectionStore);
const tabPosition = ref('right');
const activeSectionId = ref(null);

const sectionFormRefs = ref({});
const previewVisible = ref(false);
const previewImageUrl = ref('');
const sectionImages = ref({});
const posterIdsInput = ref({});
const loadingMore = ref(false);

const suggestedMoviesDialogVisible = ref(false);
const suggestedMoviesLoading = ref(false);
const suggestedMoviesData = ref(null);
const selectedSuggestedMovie = ref(null);
const applyingSuggestedMovie = ref(false);
const currentSuggestedSectionId = ref(null);
const selectedPosterMovies = ref([]);

const activeCollapse = ref(['images']); // По умолчанию открыт. Если хотите закрытый по умолчанию: ref([])

const tmdbDialogVisible = ref(false);
const tmdbLoading = ref(false);
const selectedTMDBImages = ref({
    logos: { ru: null, en: null },
    backdrop: null
});
const tmdbPagination = ref({
    total: 0,
    offset: 0,
    limit: 10,
    loaded: 0,
    hasMore: false
});

// Кэш данных TMDB по IMDB ID
const tmdbCache = ref({});
// Текущий активный IMDB ID в модалке
const currentTmdbImdbId = ref(null);

// Вычисляемые свойства для работы с текущими данными
const tmdbImages = computed(() => {
    if (!currentTmdbImdbId.value) return null;
    return tmdbCache.value[currentTmdbImdbId.value]?.images || null;
});

const applyingImages = ref(false);
const updatingImages = ref(false);

const formRules = ref({
    section_logo: [
        { required: true, message: 'Логотип раздела обязателен', trigger: 'change' },
        {
            validator: (rule, value, callback) => {
                if (!value || value.length === 0 || (!value[0]?.raw && !value[0]?.url)) {
                    callback(new Error('Логотип раздела обязателен'));
                } else {
                    callback();
                }
            },
            trigger: 'change',
        },
    ],
    backdrop: [
        { required: true, message: 'Бэкдроп обязателен', trigger: 'change' },
        {
            validator: (rule, value, callback) => {
                if (!value || value.length === 0 || (!value[0]?.raw && !value[0]?.url)) {
                    callback(new Error('Бэкдроп обязателен'));
                } else {
                    callback();
                }
            },
            trigger: 'change',
        },
    ],
});

onMounted(() => {
    sectionStore.getSections().then(() => {
        initSectionImages();
        sectionStore.getSectionImages();
        if (sections.value.length > 0) {
            activeSectionId.value = sections.value[0].id.toString();
        }
    });
});

watch([sections, sectionImagesData], () => {
    initSectionImages();
    if (!activeSectionId.value && sections.value.length > 0) {
        activeSectionId.value = sections.value[0].id.toString();
    }
}, { deep: true });

const handlePreview = (file) => {
    if (file.url || file.raw) {
        previewImageUrl.value = file.url || URL.createObjectURL(file.raw);
        previewVisible.value = true;
    }
};

const closePreview = () => {
    previewVisible.value = false;
    previewImageUrl.value = '';
};

const urlToFile = async (url, filename) => {
    try {
        const urlWithCacheBuster = url + (url.includes('?') ? '&' : '?') + `v=${Date.now()}`;

        const response = await fetch(urlWithCacheBuster, {
            cache: 'no-store'  // ← Запрещаем кэширование
        });
        if (!response.ok) throw new Error(`Failed to fetch ${url}`);
        const blob = await response.blob();
        return new File([blob], filename, { type: blob.type });
    } catch (error) {
        console.error(`Error converting URL to File: ${url}`, error);
        return null;
    }
};

const initSectionImages = async () => {
    sections.value.forEach(async (item) => {
        if (!sectionImages.value[item.id]) {
            sectionImages.value[item.id] = {
                section_logo: [],
                backdrop: [],
                film_logo_ru: [],
                film_logo_en: [],
                poster_ids: [],
                posters: [],
            };
        }
        if (!posterIdsInput.value[item.id]) {
            posterIdsInput.value[item.id] = '';
        }
        if (sectionImagesData.value[item.id]) {
            const fields = ['section_logo', 'backdrop', 'film_logo_ru', 'film_logo_en', 'posters'];
            for (const field of fields) {
                sectionImages.value[item.id][field] = await Promise.all(
                    sectionImagesData.value[item.id][field].map(async (url) => {
                        const filename = url.split('/').pop();
                        const file = await urlToFile(url, filename);
                        return file ? { name: filename, url, raw: file } : { name: filename, url };
                    })
                );
            }
        }
    });
};

const setFormRef = (sectionId) => (el) => {
    if (el) {
        sectionFormRefs.value[sectionId] = el;
    }
};

const handleUpdateSection = (item) => {
    sectionStore.updateSection(item);
};

const handleFileChange = (file, fileList, sectionId, field) => {
    sectionImages.value[sectionId][field] = fileList;
};

const handleRemove = (file, fileList, sectionId, field) => {
    sectionImages.value[sectionId][field] = fileList;
    if (file.url) {
        sectionStore.removeImage(file, sectionId, field);
    }
};

const handlePosterIdsInput = (sectionId) => {
    const input = posterIdsInput.value[sectionId].trim();
    if (input.endsWith(',')) {
        const ids = input.split(',').map(id => id.trim()).filter(id => id && /^tt\d{7,}$/.test(id));
        ids.forEach(newId => {
            if (!sectionImages.value[sectionId].poster_ids.includes(newId)) {
                sectionImages.value[sectionId].poster_ids.push(newId);
            }
        });
        posterIdsInput.value[sectionId] = '';
    }
};

const removePosterId = (sectionId, index) => {
    sectionImages.value[sectionId].poster_ids.splice(index, 1);
};

// TMDB методы
const handleSyncWithTMDB = async (item) => {
    if (!item.index_id || !/^tt\d{7,}$/.test(item.index_id)) {
        ElMessage.warning('У секции нет корректного IMDB ID');
        return;
    }

    const imdbId = item.index_id;

    // Сбрасываем выбор при смене секции
    selectedTMDBImages.value = {
        logos: { ru: null, en: null },
        backdrop: null
    };

    // Устанавливаем текущий IMDB ID
    currentTmdbImdbId.value = imdbId;

    // Если данные для этого IMDB ID уже загружены - просто открываем модалку
    if (tmdbCache.value[imdbId]) {
        tmdbPagination.value = tmdbCache.value[imdbId].pagination;
        tmdbDialogVisible.value = true;
        return;
    }

    // Иначе загружаем данные
    tmdbLoading.value = true;

    const result = await sectionStore.syncWithTMDB(imdbId, 0, 10);

    if (result.success) {
        // Сохраняем в кэш по IMDB ID
        tmdbCache.value[imdbId] = {
            images: result.data,
            pagination: result.data.pagination
        };
        tmdbPagination.value = result.data.pagination;
        tmdbDialogVisible.value = true;
    } else {
        ElMessage.error(result.message || 'Не удалось получить данные из TMDB');
        currentTmdbImdbId.value = null;
    }

    tmdbLoading.value = false;
};

const selectTMDBLogo = (lang, logo) => {
    selectedTMDBImages.value.logos[lang] = logo;
};

const selectTMDBBackdrop = (backdrop) => {
    selectedTMDBImages.value.backdrop = backdrop;
};

const applyTMDBImages = async () => {
    try {
        await ElMessageBox.confirm(
            'Вы действительно хотите применить выбранные изображения? Текущие изображения будут заменены.',
            'Подтверждение',
            {
                confirmButtonText: 'Применить',
                cancelButtonText: 'Отмена',
                type: 'warning',
            }
        );

        applyingImages.value = true;

        if (!activeSectionId.value) return;

        const sectionId = activeSectionId.value;
        const images = selectedTMDBImages.value;

        if (images.logos.ru) {
            sectionImages.value[sectionId].film_logo_ru = [{
                name: 'logo_ru.png',
                url: images.logos.ru.url_original,
                raw: null
            }];
        }

        if (images.logos.en) {
            sectionImages.value[sectionId].film_logo_en = [{
                name: 'logo_en.png',
                url: images.logos.en.url_original,
                raw: null
            }];
        }

        if (images.backdrop) {
            sectionImages.value[sectionId].backdrop = [{
                name: 'backdrop.jpg',
                url: images.backdrop.url_original,
                raw: null
            }];
        }

        tmdbDialogVisible.value = false;
        ElMessage.success('Изображения выбраны. Нажмите "Обновить изображения" для сохранения.');

    } catch (cancelled) {
        // Пользователь отменил
        ElMessage.info('Применение отменено');
    } finally {
        applyingImages.value = false;
    }
};

const handleOpenSuggestedMovies = async (item) => {
    if (!item.id) {
        ElMessage.warning('Сначала сохраните секцию');
        return;
    }

    currentSuggestedSectionId.value = item.id;
    selectedSuggestedMovie.value = null;
    suggestedMoviesData.value = null;
    suggestedMoviesDialogVisible.value = true;
    suggestedMoviesLoading.value = true;
    selectedPosterMovies.value = [];

    const result = await sectionStore.getSuggestedMovies(item.id);

    if (result.success) {
        suggestedMoviesData.value = result;
    } else {
        ElMessage.error(result.message || 'Не удалось получить список фильмов');
        suggestedMoviesDialogVisible.value = false;
    }

    suggestedMoviesLoading.value = false;
};

const selectSuggestedMovie = (movie) => {
    selectedSuggestedMovie.value = movie;
};

const isPosterMovieSelected = (idMovie) => {
    return selectedPosterMovies.value.some(m => m.id_movie === idMovie);
};

const getPosterMovieIndex = (idMovie) => {
    return selectedPosterMovies.value.findIndex(m => m.id_movie === idMovie);
};

const togglePosterMovie = (movie) => {
    // Если этот фильм уже главный — не добавляем в дополнительные
    if (selectedSuggestedMovie.value?.id_movie === movie.id_movie) {
        ElMessage.warning('Этот фильм уже назначен главным. Сначала уберите роль главного.');
        return;
    }

    const index = selectedPosterMovies.value.findIndex(m => m.id_movie === movie.id_movie);

    if (index !== -1) {
        // Убираем из списка
        selectedPosterMovies.value.splice(index, 1);
    } else {
        // Добавляем в список (если меньше 10)
        if (selectedPosterMovies.value.length < 10) {
            selectedPosterMovies.value.push(movie);
        } else {
            ElMessage.warning('Максимум 10 дополнительных фильмов');
        }
    }
};

const removePosterMovie = (index) => {
    selectedPosterMovies.value.splice(index, 1);
};
const applySuggestedMovie = async () => {
    // Проверка: должно быть выбрано что-то (главный или дополнительные)
    const hasMainMovie = !!selectedSuggestedMovie.value;
    const hasPosterMovies = selectedPosterMovies.value.length > 0;

    if ((!hasMainMovie && !hasPosterMovies) || !currentSuggestedSectionId.value) {
        ElMessage.warning('Выберите хотя бы главный или дополнительный фильм');
        return;
    }

    // Валидация дополнительных фильмов
    if (hasPosterMovies &&
        (selectedPosterMovies.value.length < 7 || selectedPosterMovies.value.length > 10)) {
        ElMessage.warning('Дополнительных фильмов должно быть от 7 до 10');
        return;
    }

    try {
        // Формируем текст подтверждения в зависимости от выбора
        const confirmParts = [];

        if (hasMainMovie) {
            confirmParts.push(`Сменить главный фильм на "${selectedSuggestedMovie.value.title}" (${selectedSuggestedMovie.value.id_movie})`);
        }

        if (hasPosterMovies) {
            confirmParts.push(`Назначить ${selectedPosterMovies.value.length} дополнительных фильмов`);
        }

        const confirmMessage = `Вы действительно хотите:\n• ${confirmParts.join('\n• ')}?`;

        await ElMessageBox.confirm(
            confirmMessage,
            'Подтверждение',
            {
                confirmButtonText: 'Применить',
                cancelButtonText: 'Отмена',
                type: 'warning',
            }
        );

        applyingSuggestedMovie.value = true;

        // Находим секцию
        const section = sections.value.find(s => s.id === currentSuggestedSectionId.value);
        if (!section) {
            ElMessage.error('Секция не найдена');
            return;
        }

        const oldImdbId = section.index_id;
        const sectionId = section.id;

        // 1. Обновляем главный фильм (только если он выбран)
        if (hasMainMovie) {
            section.index_id = selectedSuggestedMovie.value.id_movie;
            await sectionStore.updateSection(section);
        }

        // 2. Обновляем дополнительные фильмы (только если они выбраны)
        if (hasPosterMovies) {
            const posterIds = selectedPosterMovies.value.map(m => m.id_movie);

            // Отправляем poster_ids на сервер для создания файлов постеров
            const formData = new FormData();
            posterIds.forEach((id) => {
                formData.append(`sections[${sectionId}][poster_ids][]`, id);
            });

            await sectionStore.updateSectionImages(formData);

            // Обновляем локально
            sectionImages.value[sectionId].poster_ids = posterIds;
        }

        // 3. Принудительно обновляем данные изображений с сервера
        await sectionStore.getSectionImages();

        // Ждём чтобы watch сработал
        await new Promise(resolve => setTimeout(resolve, 300));

        // 4. Принудительно перезагружаем изображения для текущей секции
        if (sectionImagesData.value[sectionId]) {
            const fields = ['section_logo', 'backdrop', 'film_logo_ru', 'film_logo_en', 'posters'];
            for (const field of fields) {
                if (sectionImagesData.value[sectionId][field]) {
                    sectionImages.value[sectionId][field] = await Promise.all(
                        sectionImagesData.value[sectionId][field].map(async (url) => {
                            const filename = url.split('/').pop().split('?')[0];
                            const file = await urlToFile(url, filename);
                            return file ? { name: filename, url, raw: file } : { name: filename, url };
                        })
                    );
                }
            }
        }

        // 5. Очищаем кэш TMDB (только если менялся главный фильм)
        if (hasMainMovie) {
            if (oldImdbId && tmdbCache.value[oldImdbId]) {
                delete tmdbCache.value[oldImdbId];
            }
            if (tmdbCache.value[selectedSuggestedMovie.value.id_movie]) {
                delete tmdbCache.value[selectedSuggestedMovie.value.id_movie];
            }

            if (currentTmdbImdbId.value === oldImdbId) {
                currentTmdbImdbId.value = null;
            }
        }

        suggestedMoviesDialogVisible.value = false;

        // Формируем сообщение об успехе
        let successMessage = '';
        if (hasMainMovie && hasPosterMovies) {
            successMessage = `Главный фильм и ${selectedPosterMovies.value.length} дополнительных фильмов успешно назначены`;
        } else if (hasMainMovie) {
            successMessage = `Главный фильм успешно изменён на "${selectedSuggestedMovie.value.title}"`;
        } else if (hasPosterMovies) {
            successMessage = `${selectedPosterMovies.value.length} дополнительных фильмов успешно назначены`;
        }

        ElMessage.success(successMessage);

    } catch (cancelled) {
        ElMessage.info('Изменение отменено');
    } finally {
        applyingSuggestedMovie.value = false;
    }
};
const loadMoreBackdrops = async () => {
    const imdbId = currentTmdbImdbId.value;
    if (!imdbId) return;

    loadingMore.value = true;

    const nextOffset = tmdbPagination.value.loaded;
    const result = await sectionStore.syncWithTMDB(
        imdbId,
        nextOffset,
        tmdbPagination.value.limit
    );

    if (result.success) {
        // Добавляем новые backdrops к существующим в кэше
        tmdbCache.value[imdbId].images.backdrops = [
            ...tmdbCache.value[imdbId].images.backdrops,
            ...result.data.backdrops
        ];
        tmdbCache.value[imdbId].pagination = result.data.pagination;
        tmdbPagination.value = result.data.pagination;
    } else {
        ElMessage.error('Не удалось загрузить дополнительные backdrops');
    }

    loadingMore.value = false;
};
const handleUpdateImages = async () => {
    if (!activeSectionId.value) {
        ElMessage.error('Выберите секцию для обновления');
        return;
    }

    try {
        await ElMessageBox.confirm(
            'Вы действительно хотите обновить изображения для этой секции? Текущие файлы будут заменены.',
            'Подтверждение',
            {
                confirmButtonText: 'Обновить',
                cancelButtonText: 'Отмена',
                type: 'warning',
            }
        );

        updatingImages.value = true;

        const sectionId = activeSectionId.value;
        const form = sectionFormRefs.value[sectionId];

        if (!form) {
            ElMessage.error(`Форма для секции ${sectionId} не найдена`);
            return;
        }

        const validation = await new Promise((resolve) => {
            form.validate((valid) => {
                resolve({ sectionId, valid });
            });
        });

        const errors = [];
        if (!validation.valid) {
            errors.push(`Секция ${sectionId}: проверьте обязательные поля.`);
        }

        const posterIds = sectionImages.value[sectionId].poster_ids;
        if (posterIds.length > 0 && (posterIds.length < 7 || posterIds.length > 10)) {
            errors.push(`Секция ${sectionId}: должно быть от 7 до 10 ID, если указаны.`);
        }

        if (errors.length > 0) {
            errors.forEach(error => ElMessage.warning(error));
            return;
        }

        const formData = new FormData();
        const images = sectionImages.value[sectionId];

        // Отправляем ТОЛЬКО файлы (если есть)
        if (images.section_logo[0]?.raw) {
            formData.append(`sections[${sectionId}][section_logo]`, images.section_logo[0].raw);
        }
        if (images.backdrop[0]?.raw) {
            formData.append(`sections[${sectionId}][backdrop]`, images.backdrop[0].raw);
        }
        if (images.film_logo_ru[0]?.raw) {
            formData.append(`sections[${sectionId}][film_logo_ru]`, images.film_logo_ru[0].raw);
        }
        if (images.film_logo_en[0]?.raw) {
            formData.append(`sections[${sectionId}][film_logo_en]`, images.film_logo_en[0].raw);
        }

        // Отправляем ТОЛЬКО URL (если есть и нет файлов)
        if (!images.section_logo[0]?.raw && images.section_logo[0]?.url && !images.section_logo[0].url.startsWith('/api/')) {
            formData.append(`sections[${sectionId}][section_logo_url]`, images.section_logo[0].url);
        }
        if (!images.backdrop[0]?.raw && images.backdrop[0]?.url && !images.backdrop[0].url.startsWith('/api/')) {
            formData.append(`sections[${sectionId}][backdrop_url]`, images.backdrop[0].url);
        }
        if (!images.film_logo_ru[0]?.raw && images.film_logo_ru[0]?.url && !images.film_logo_ru[0].url.startsWith('/api/')) {
            formData.append(`sections[${sectionId}][film_logo_ru_url]`, images.film_logo_ru[0].url);
        }
        if (!images.film_logo_en[0]?.raw && images.film_logo_en[0]?.url && !images.film_logo_en[0].url.startsWith('/api/')) {
            formData.append(`sections[${sectionId}][film_logo_en_url]`, images.film_logo_en[0].url);
        }

        // Постеры
        if (images.poster_ids.length >= 7 && images.poster_ids.length <= 10) {
            images.poster_ids.forEach((id) => {
                formData.append(`sections[${sectionId}][poster_ids][]`, id);
            });
        }

        await sectionStore.updateSectionImages(formData);

// ← ДОБАВИТЬ: Принудительно обновляем данные изображений
        await sectionStore.getSectionImages();

// Ждём немного чтобы watch сработал и initSectionImages обновил данные
        await new Promise(resolve => setTimeout(resolve, 300));

// Принудительно перезагружаем изображения для текущей секции
        if (sectionImagesData.value[sectionId]) {
            const fields = ['section_logo', 'backdrop', 'film_logo_ru', 'film_logo_en', 'posters'];
            for (const field of fields) {
                if (sectionImagesData.value[sectionId][field]) {
                    sectionImages.value[sectionId][field] = await Promise.all(
                        sectionImagesData.value[sectionId][field].map(async (url) => {
                            const filename = url.split('/').pop().split('?')[0]; // Убираем версию из имени
                            const file = await urlToFile(url, filename);
                            return file ? { name: filename, url, raw: file } : { name: filename, url };
                        })
                    );
                }
            }
        }

        ElMessage.success(`Изображения для секции ${sectionId} успешно обновлены`);

    } catch (cancelled) {
        ElMessage.info('Обновление отменено');
    } finally {
        updatingImages.value = false;
    }
};
const makeMainMovie = (movie) => {
    if (selectedSuggestedMovie.value?.id_movie === movie.id_movie) {
        // Если уже главный — убираем роль
        selectedSuggestedMovie.value = null;
    } else {
        // Делаем главным
        selectedSuggestedMovie.value = movie;

        // Если этот фильм был в дополнительных — убираем оттуда
        const posterIndex = selectedPosterMovies.value.findIndex(m => m.id_movie === movie.id_movie);
        if (posterIndex !== -1) {
            selectedPosterMovies.value.splice(posterIndex, 1);
        }
    }
};
const closeTMDBDialog = () => {
    tmdbDialogVisible.value = false;
};
</script>

<style scoped>
::v-deep(.el-upload-list) {
    display: flex;
    flex-wrap: wrap;
}
::v-deep(.el-upload-list__item) {
    max-width: 250px;
    margin-right: 15px;
}

.selected-card {
    border: 3px solid #409EFF !important;
    box-shadow: 0 0 15px rgba(64, 158, 255, 0.6);
    transform: scale(1.02);
    transition: all 0.3s ease;
}

.image-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.backdrop-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

/* Сетка 5 в ряд для предложенных фильмов */
.movies-grid {
    display: grid !important;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
}

.movie-col {
    width: 100% !important;
    max-width: 100% !important;
    flex: 0 0 100% !important;
}

.movie-card {
    transition: all 0.3s ease;
    height: 100%;
    position: relative;
}

.movie-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Карточка главного фильма */
.selected-main-card {
    border: 3px solid #409EFF !important;
    background-color: #ECF5FF !important;
    box-shadow: 0 0 15px rgba(64, 158, 255, 0.5);
    transform: scale(1.02);
}

/* Карточка дополнительного фильма */
.selected-poster-card {
    border: 2px solid #67C23A !important;
    background-color: #F0F9EB !important;
    box-shadow: 0 2px 8px rgba(103, 194, 58, 0.3);
}

/* Кнопка "Сделать главным" */
.main-star-btn {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 32px;
    height: 32px;
    background: rgba(0, 0, 0, 0.5);
    color: #FFD700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    cursor: pointer;
    z-index: 10;
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.main-star-btn:hover {
    background: rgba(0, 0, 0, 0.8);
    transform: scale(1.15);
}

.main-star-btn.is-main {
    background: #409EFF;
    color: white;
    border-color: white;
    box-shadow: 0 0 10px rgba(64, 158, 255, 0.8);
}

/* Номер дополнительного фильма */
.poster-number {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    background: #67C23A;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
    z-index: 10;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
}

/* Карточки статуса */
.status-card-main {
    border: 2px solid #409EFF !important;
    background-color: #ECF5FF !important;
}

.status-card-poster {
    border: 2px solid #67C23A !important;
    background-color: #F0F9EB !important;
}

.status-card-warning {
    border: 2px solid #E6A23C !important;
    background-color: #FDF6EC !important;
}

.status-label {
    font-size: 12px;
    color: #909399;
    margin-bottom: 5px;
}

.status-value {
    font-size: 14px;
    color: #303133;
}

/* Адаптивность */
@media (max-width: 1400px) {
    .movies-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 1000px) {
    .movies-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .movies-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .image-card, .backdrop-card {
        margin-bottom: 10px;
    }
}

@media (max-width: 480px) {
    .movies-grid {
        grid-template-columns: repeat(1, 1fr);
    }
}
</style>
