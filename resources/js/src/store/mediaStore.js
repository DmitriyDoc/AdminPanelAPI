import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { onMounted, onUpdated,onBeforeUnmount, ref,reactive} from "vue";

export const useMediaStore = defineStore('mediaStore',() => {
    const route = useRoute();
    const imagesData = ref( [] );
    const postersData = ref( [] );
    const srcListImages = ref( [] );
    const srcListPosters = ref( [] );
    const pageImg = ref(0);
    const pagePoster = ref(0);
    const countImg = ref(null);
    const countPoster = ref(null);
    const error = ref();
    const postersAssignInfo = reactive({
        id_poster_original: {
            locale: 'Original',
            count: 0,
        },
        id_poster_ru: {
            locale: 'Russian',
            count: 0,
        },
        id_posters_characters: {
            locale: 'Characters',
            count: 0,
        },
        id_posters_alternative: {
            locale: 'Alternative',
            count: 0,
        },
        id_wallpaper: {
            locale: 'Wallpapers',
            count: 0,
        },
    });
    const getAssignedImages = async () =>{
        try {
            axios.get('/media/show/images/'
                + route.params.slug
                + '/'
                + route.params.id
            ).then((response) => {
                response.data.forEach((item) => {
                    imagesData.value.push(item);
                    srcListImages.value.push(item.src);
                });
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {}
    }
    const getAssignedPosters = async () =>{
        try {
            axios.get('/media/show/posters/'
                + route.params.id
            ).then((response) => {
                response.data.forEach((item) => {
                    postersData.value.push(item);
                    srcListPosters.value.push(item.src);
                });
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {}
    }
    const getImages = async () =>{
        try {
            axios.get('/media/images/'
                + route.params.slug
                + '/'
                + route.params.id
                + '?page=' + pageImg.value
            ).then((response) => {
                response.data.data.forEach((item) => {
                    imagesData.value.push(item);
                    srcListImages.value.push(item.src);
                });
                countImg.value = response.data.to;
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {
            //loader.value = false;
        }
    }
    const getPosters = async () =>{
        try {
            axios.get('/media/posters/'
                + route.params.slug
                + '/'
                + route.params.id
                + '?page=' + pagePoster.value
            ).then((response) => {
                response.data.data.forEach((item) => {
                    postersData.value.push(item);
                    srcListPosters.value.push(item.src);
                });
                if (response.data.poster_count){
                    for (var item in response.data.poster_count) {
                        postersAssignInfo[item].count = reactive(response.data.poster_count[item]);
                    }
                }
                countPoster.value = response.data.to;
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {
            //loader.value = false;
        }
    }
    const removeImages = async (ids,type) =>{
        try {
            axios.delete('/media/'
                + type
                + '/'
                + route.params.slug, { data: ids}).then((response) => {
                //if (response.data.success) getImages();
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {
            //loader.value = false;
        }
    }
    const setPoster = async (id,cat) =>{
        try {
            axios.post('/media/poster_assign', {
                type_film: route.params.slug,
                id_movie: route.params.id,
                id_poster: id,
                poster_cat: cat,
                }).then((response) => {
                //flushState();
                getPosters();
            });
        } catch (e) {
            error.value = e;
            console.log('error',e);
        } finally {
            //loader.value = false;
        }
    }
    const updateImagePageSize = () => {
        pageImg.value =  pageImg.value + 1;
        getImages();
    }
    const updatePosterPageSize = () => {
        pagePoster.value =  pagePoster.value + 1;
        getPosters();
    }
    const removeMultipleImages = (ids,type) => {
        removeImages(ids,type);
    }
    const assignPoster = (id,cat) => {
        setPoster(id,cat);
    }
    const flushState = () => {
        pageImg.value = 1;
        pagePoster.value = 1;
        imagesData.value = [];
        postersData.value = [];
        srcListImages.value = [];
        srcListPosters.value = [];
    }
    return {
        error,
        imagesData,
        postersData,
        srcListImages,
        srcListPosters,
        countImg,
        countPoster,
        postersAssignInfo,
        removeMultipleImages,
        assignPoster,
        flushState,
        updatePosterPageSize,
        updateImagePageSize,
        getImages,
        getPosters,
        getAssignedImages,
        getAssignedPosters,
    }
});
