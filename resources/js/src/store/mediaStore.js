import { defineStore } from "pinia";
import axios from 'axios';
import { useRoute } from 'vue-router';
import { ref, reactive } from "vue";
import { ElMessage } from "element-plus";
import { useMoviesStore } from './moviesStore.js';

export const useMediaStore = defineStore('mediaStore', () => {
    const movieStore = useMoviesStore();
    const route = useRoute();

    // Основные массивы (для обратной совместимости)
    const imagesData = ref([]);
    const postersData = ref([]);
    const srcListImages = ref([]);
    const srcListPosters = ref([]);

    // Разделённые по источникам
    const imagesDataTmdb = ref([]);
    const imagesDataImdb = ref([]);
    const postersDataTmdb = ref([]);
    const postersDataImdb = ref([]);

    const pageImg = ref(1);
    const pagePoster = ref(1);

    const countImg = ref(null);
    const countPoster = ref(null);
    const error = ref();
    const disabledBtnResize = ref(false);

    // Активные источники
    const activeImageSource = ref('tmdb');
    const activePosterSource = ref('tmdb');

    // Видимость модалок
    const isImagesModalVisible = ref(false);
    const isPostersModalVisible = ref(false);

    const postersAssignInfo = reactive({
        id_poster_original: { locale: 'Original', count: 0 },
        id_poster_ru: { locale: 'Russian', count: 0 },
        id_posters_characters: { locale: 'Characters', count: 0 },
        id_posters_alternative: { locale: 'Alternative', count: 0 },
        id_wallpaper: { locale: 'Wallpapers', count: 0 },
    });
    const hasMoreImagesTmdb = ref(true);
    const hasMoreImagesImdb = ref(true);
    const hasMorePostersTmdb = ref(true);
    const hasMorePostersImdb = ref(true);
    const flushState = () => {
        pageImg.value = 1;
        pagePoster.value = 1;
        hasMoreImagesTmdb.value = true;
        hasMoreImagesImdb.value = true;
        hasMorePostersTmdb.value = true;
        hasMorePostersImdb.value = true;
        imagesData.value = [];
        postersData.value = [];
        imagesDataTmdb.value = [];
        imagesDataImdb.value = [];
        postersDataTmdb.value = [];
        postersDataImdb.value = [];
        srcListImages.value = [];
        srcListPosters.value = [];
        Object.keys(postersAssignInfo).forEach(key => {
            postersAssignInfo[key].count = 0;
        });
    };
    const getImages = async (slug) => {
        try {
            const response = await axios.get('/media/images/' + slug + '/' + route.params.id + '?page=' + pageImg.value);

            const newTmdb = (response.data.data_tmdb || []).map(item => ({ ...item, source: 'tmdb' }));
            const newImdb = (response.data.data_imdb || []).map(item => ({ ...item, source: 'imdb' }));

            // Читаем надежные флаги прямо с бэкенда
            hasMoreImagesTmdb.value = response.data.has_more_tmdb ?? false;
            hasMoreImagesImdb.value = response.data.has_more_imdb ?? false;

            let hasNewData = false;

            if (newTmdb.length > 0) {
                if (pageImg.value === 1) {
                    imagesDataTmdb.value = newTmdb;
                } else {
                    imagesDataTmdb.value = [...imagesDataTmdb.value, ...newTmdb];
                }
                hasNewData = true;
            }

            if (newImdb.length > 0) {
                if (pageImg.value === 1) {
                    imagesDataImdb.value = newImdb;
                } else {
                    imagesDataImdb.value = [...imagesDataImdb.value, ...newImdb];
                }
                hasNewData = true;
            }

            if (hasNewData) {
                imagesData.value = [...imagesDataTmdb.value, ...imagesDataImdb.value];
                srcListImages.value = imagesData.value;
                countImg.value = (response.data.total_tmdb || 0) + (response.data.total_imdb || 0);

                if (pageImg.value === 1) {
                    if (imagesDataTmdb.value.length > 0) activeImageSource.value = 'tmdb';
                    else if (imagesDataImdb.value.length > 0) activeImageSource.value = 'imdb';
                }
            } else {
                pageImg.value = Math.max(1, pageImg.value - 1);
                ElMessage.info('Больше изображений нет');
            }
        } catch (e) {
            error.value = e;
            console.log('error getImages', e);
            pageImg.value = Math.max(1, pageImg.value - 1);
        }
    };

    const getPosters = async (slug) => {
        try {
            const response = await axios.get('/media/posters/' + slug + '/' + route.params.id + '?page=' + pagePoster.value);

            const newTmdb = (response.data.data_tmdb || []).map(item => ({ ...item, source: 'tmdb' }));
            const newImdb = (response.data.data_imdb || []).map(item => ({ ...item, source: 'imdb' }));

            hasMorePostersTmdb.value = response.data.has_more_tmdb ?? false;
            hasMorePostersImdb.value = response.data.has_more_imdb ?? false;

            let hasNewData = false;

            if (newTmdb.length > 0) {
                if (pagePoster.value === 1) {
                    postersDataTmdb.value = newTmdb;
                } else {
                    postersDataTmdb.value = [...postersDataTmdb.value, ...newTmdb];
                }
                hasNewData = true;
            }

            if (newImdb.length > 0) {
                if (pagePoster.value === 1) {
                    postersDataImdb.value = newImdb;
                } else {
                    postersDataImdb.value = [...postersDataImdb.value, ...newImdb];
                }
                hasNewData = true;
            }

            if (hasNewData) {
                postersData.value = [...postersDataTmdb.value, ...postersDataImdb.value];
                countPoster.value = (response.data.total_tmdb || 0) + (response.data.total_imdb || 0);

                if (response.data.poster_count) {
                    for (let key in response.data.poster_count) {
                        if (postersAssignInfo[key]) {
                            postersAssignInfo[key].locale = response.data.locale[key];
                            postersAssignInfo[key].count = response.data.poster_count[key];
                        }
                    }
                }

                if (pagePoster.value === 1) {
                    if (postersDataTmdb.value.length > 0) activePosterSource.value = 'tmdb';
                    else if (postersDataImdb.value.length > 0) activePosterSource.value = 'imdb';
                }
                srcListPosters.value = postersData.value.map(item => item.src);
            } else {
                pagePoster.value = Math.max(1, pagePoster.value - 1);
                ElMessage.info('Больше постеров нет');
            }
        } catch (e) {
            error.value = e;
            console.log('error getPosters', e);
            pagePoster.value = Math.max(1, pagePoster.value - 1);
        }
    };
    const getAssignedImages = async () => {
        try {
            const response = await axios.get('/media/show/images/' + route.params.id);
            const newImages = [];
            const newSrcList = [];
            response.data.forEach((item) => {
                newImages.push(item);
                newSrcList.push(item.src);
            });
            imagesData.value = newImages;
            srcListImages.value = newSrcList;
        } catch (e) {
            error.value = e;
            console.log('error', e);
        }
    };

    const getAssignedPosters = async () => {
        try {
            const response = await axios.get('/media/show/posters/' + route.params.id);
            const newPosters = [];
            const newSrcList = [];
            response.data.forEach((item) => {
                newPosters.push(item);
                newSrcList.push(item.src);
            });
            postersData.value = newPosters;
            srcListPosters.value = newSrcList;
        } catch (e) {
            error.value = e;
            console.log('error', e);
        }
    };

    const removeImages = async (ids, type, slug) => {
        try {
            await axios.delete('/media/delete', {
                data: { 'data': ids, 'type': type, 'slug': slug }
            });

            if (type === 'posters') {
                await getPosters(slug);
            } else {
                const idsToRemove = new Set(ids.map(id => String(id)));
                imagesData.value = imagesData.value.filter(item => !idsToRemove.has(String(item.id)));
                imagesDataTmdb.value = imagesDataTmdb.value.filter(item => !idsToRemove.has(String(item.id)));
                imagesDataImdb.value = imagesDataImdb.value.filter(item => !idsToRemove.has(String(item.id)));
                srcListImages.value = srcListImages.value.filter(item => !idsToRemove.has(String(item.id)));
            }

            movieStore.showItem();
        } catch (e) {
            error.value = e;
            console.log('error remove', e);
            throw e;
        }
    };

    const moveImagesToPosters = async (ids, slug) => {
        try {
            await axios.patch('/media/move', { 'data': ids, 'slug': slug });

            const idsToRemove = new Set(ids.map(id => String(id)));
            imagesData.value = imagesData.value.filter(item => !idsToRemove.has(String(item.id)));
            imagesDataTmdb.value = imagesDataTmdb.value.filter(item => !idsToRemove.has(String(item.id)));
            imagesDataImdb.value = imagesDataImdb.value.filter(item => !idsToRemove.has(String(item.id)));
            srcListImages.value = srcListImages.value.filter(item => !idsToRemove.has(String(item.id)));

            await getPosters(slug);
            movieStore.showItem();
        } catch (e) {
            error.value = e;
            console.log('error move', e);
            throw e;
        }
    };

    const setPoster = async (id, cat, slug) => {
        try {
            await axios.post('/media/poster_assign', {
                type_film: slug,
                id_movie: route.params.id,
                id_poster: id,
                poster_cat: cat,
            });

            await getPosters(slug);
            movieStore.showItem();
        } catch (e) {
            error.value = e;
            console.log('error setPoster', e);
            throw e;
        }
    };

    const resizeAllImages = (id,slug) => {
        disabledBtnResize.value = true;
        try {
            axios.post('/resize-images', { id_movie: id, slug: slug }).then((response) => {
                if (response.status === 200) {
                    disabledBtnResize.value = false;
                    movieStore.showItem();
                    ElMessage({ type: 'success', message: 'Resize images completed' });
                } else {
                    ElMessage({ type: 'error', message: 'Resize images is not finished' });
                }
            });
        } catch (e) {
            error.value = e;
            console.log('error', e);
        }
    };

    const saveImageOrder = async (payload) => {
        const { movieId, slug, orderedIds } = payload;
        try {
            const response = await axios.post('/media/reorder', {
                movie_id: movieId,
                slug: slug, // ← ВАЖНО: добавляем slug
                ordered_ids: orderedIds,
            });

            if (response.data.success && Array.isArray(response.data.images)) {
                // Обновляем только текущий активный источник
                if (activeImageSource.value === 'tmdb') {
                    imagesDataTmdb.value = response.data.images.filter(img => img.source === 'tmdb');
                } else {
                    imagesDataImdb.value = response.data.images.filter(img => img.source === 'imdb');
                }

                // Пересобираем общий массив
                imagesData.value = [...imagesDataTmdb.value, ...imagesDataImdb.value];
                srcListImages.value = imagesData.value;

                ElMessage({ type: 'success', message: 'Order images saved successfully' });
            }
            return response;
        } catch (e) {
            error.value = e;
            console.error('Error saving image order:', e);
            ElMessage({ type: 'error', message: 'Failed to save order' });
            throw e;
        }
    };

    const updateImagePageSize = (slug) => {
        pageImg.value = pageImg.value + 1;
        getImages(slug);
    };

    const updatePosterPageSize = (slug) => {
        pagePoster.value = pagePoster.value + 1;
        getPosters(slug);
    };

    const removeMultipleImages = (ids, type, slug) => removeImages(ids, type, slug);
    const moveMultipleImages = (ids, slug) => moveImagesToPosters(ids, slug);
    const assignPoster = (id, cat, slug) => setPoster(id, cat, slug);

    return {
        error,
        imagesData,
        postersData,
        imagesDataTmdb,
        imagesDataImdb,
        postersDataTmdb,
        postersDataImdb,
        srcListImages,
        disabledBtnResize,
        srcListPosters,
        countImg,
        countPoster,
        postersAssignInfo,
        activeImageSource,
        activePosterSource,
        isImagesModalVisible,
        isPostersModalVisible,
        hasMoreImagesTmdb,
        hasMoreImagesImdb,
        hasMorePostersTmdb,
        hasMorePostersImdb,
        resizeAllImages,
        removeMultipleImages,
        moveMultipleImages,
        assignPoster,
        flushState,
        updatePosterPageSize,
        updateImagePageSize,
        getImages,
        getPosters,
        getAssignedImages,
        getAssignedPosters,
        saveImageOrder,
    };
});
