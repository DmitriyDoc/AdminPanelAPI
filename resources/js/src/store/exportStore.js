import { defineStore } from "pinia";
import axios from 'axios'
import { ref,reactive} from "vue";
import { ElMessage } from "element-plus";


export const useExportStore = defineStore('exportStore',() => {

    const statePagination = ref({
        page: 1,
        limit: 50,
        total: 0,
    });
    const tableData = ref([]);
    const locale = ref([]);
    const pageSize = ref(statePagination.value.limit);
    const currentPage = ref(statePagination.value.page);
    const totalCount = ref(statePagination.value.total);
    const loader = ref(false);
    const spinBtnExportMovie = ref(false);
    const spinBtnExportTaxonomy = ref(false);
    const spinBtnExportTag = ref(false);
    const messageExportTaxonomy = ref('');
    const messageExportTag = ref('');
    const countsExportMovie = ref(0);
    const countsExportTaxonomy = ref([]);
    const countsExportTag = ref([]);
    const disableBtnMovieExport = ref(true);
    const disableBtnTaxonomyExport = ref(false);
    const disableBtnTagExport = ref(false);
    const messageExportMovie = reactive( {
        type:"",
        text:"",
    });
    const getExportMovies = () =>{
        try {
            loader.value = true;
            axios.get('/movies/export/show' + '?page=' + statePagination.value.page).then((response) => {
                locale.value = response.data.locale;
                tableData.value = response.data.data;
                totalCount.value = response.data.total;
                pageSize.value = response.data.per_page;
                currentPage.value = response.data.current_page;
                loader.value = false;
            });
        } catch (e) {
            console.log('error',e);
        }
    }
    const exportMovies = async (switchAll) => {
        spinBtnExportMovie.value = true;
        loader.value = true;
        axios.post('/movies/send',{
            'switch_all': switchAll,
        }).then((response) => {
            if (response.status === 200) {
                ElMessage({
                    type: response.data.data.type,
                    message: response.data.data.message,
                })
                if (response.data.data.body){
                    countsExportMovie.value = response.data.data.body.exported_count;
                    disableBtnMovieExport.value = true;
                }
                messageExportMovie.type = response.data.data.type;
                messageExportMovie.text = response.data.data.message;
                getExportMovies();
                loader.value = false;
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Export is not finished',
                });
            }
            spinBtnExportMovie.value = false
        });
    }
    const exportTags = async (switchAll) => {
        spinBtnExportTag.value = true;
        axios.post('/tags/send',{
            'switch_all': switchAll,
        }).then((response) => {
            if (response.status === 200) {
                ElMessage({
                    type: 'success',
                    message: response.data.data.message,
                })
                messageExportTag.value = response.data.data.message;
                countsExportTag.value = response.data.data.body.exported_count;
                disableBtnTagExport.value = true;
                disableBtnMovieExport.value = false;
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Export is not finished',
                });
            }
        });
        spinBtnExportTag.value = false;
    }
    const exportTaxonomy = async (switchAll) => {
        spinBtnExportTaxonomy.value = true;
        axios.post('/taxonomies/send',{
            'switch_all': switchAll,
        }).then((response) => {
            if (response.status === 200) {
                ElMessage({
                    type: 'success',
                    message: response.data.data.message,
                })
                messageExportTaxonomy.value = response.data.data.message;
                countsExportTaxonomy.value = response.data.data.body.exported_count;
                disableBtnTaxonomyExport.value = true;
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Export is not finished',
                });
            }
        });
        spinBtnExportTaxonomy.value = false;
    }
    const updateCurrentPage = (param) => {
        statePagination.value.page = param;
    }
    return {
        exportMovies,
        exportTaxonomy,
        exportTags,
        getExportMovies,
        updateCurrentPage,
        locale,
        loader,
        tableData,
        totalCount,
        currentPage,
        pageSize,
        messageExportMovie,
        messageExportTaxonomy,
        messageExportTag,
        countsExportMovie,
        countsExportTaxonomy,
        countsExportTag,
        spinBtnExportMovie,
        spinBtnExportTaxonomy,
        spinBtnExportTag,
        disableBtnMovieExport,
        disableBtnTaxonomyExport,
        disableBtnTagExport,
    }
});
