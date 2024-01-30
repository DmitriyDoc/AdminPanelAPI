import { defineStore } from "pinia";
import axios from 'axios'
import { useRoute } from 'vue-router';
import { onMounted, onUpdated,onBeforeUnmount, ref} from "vue";

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

    const getImages = async () =>{
        try {
            axios.get('/api/media/images/'
                + route.params.slug
                + '/'
                + route.params.id
                + '?page=' + pageImg.value
            ).then((response) => {
                console.log('RESPONCE', response.data.data);
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
            axios.get('/api/media/posters/'
                + route.params.slug
                + '/'
                + route.params.id
                + '?page=' + pagePoster.value
            ).then((response) => {
                response.data.data.forEach((item) => {
                    postersData.value.push(item);
                    srcListPosters.value.push(item.src);
                });
                countPoster.value = response.data.to;
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
        flushState,
        updatePosterPageSize,
        updateImagePageSize,
        getImages,
        getPosters,
    }
});
