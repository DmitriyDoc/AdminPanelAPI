import { defineStore } from "pinia";
import axios from 'axios'
import { ref,reactive} from "vue";
import { ElMessage, ElNotification } from 'element-plus';


export const useExportStore = defineStore('exportStore',() => {

    const statePagination = ref({
        page: 1,
        limit: 20,
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
    const spinBtnExportIndexImg = ref(false);
    const spinBtnExportCollections = ref(false);
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
    const spinBtnFullTaxonomy = ref(false);
    const spinBtnFullTags = ref(false);
    const spinBtnFullMovie = ref(false);
    const messageExportFullMovie = reactive({ type: "", text: "" });
    const countsExportFullMovie = ref(0);
    const messageExportTaxonomyType = ref('success');
    const messageExportTagType = ref('success');
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
    const exportIndex = async () => {
        spinBtnExportIndexImg.value = true;
        axios.post('/index/send').then((response) => {
            if (response.data.data.original.message) {
                ElMessage({
                    type: response.data.data.original.type,
                    message: response.data.data.original.message,
                })
                spinBtnExportIndexImg.value = false;
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Export is not finished',
                });
            }

        });
    }
    const exportCollections = async () => {
        spinBtnExportCollections.value = true;
        axios.post('/collections/send').then((response) => {
            if (response.data.data.original.message) {
                ElMessage({
                    type: response.data.data.original.type,
                    message: response.data.data.original.message,
                })
                spinBtnExportCollections.value = false;
            } else {
                ElMessage({
                    type: 'error',
                    message: 'Export is not finished',
                });
            }
        });
    }


    const exportMovies = async () => {
        spinBtnExportMovie.value = true;
        loader.value = true;

        axios.post('/movies/send').then((response) => {

            const resData = response.data.data ? response.data.data : response.data;
            const msgType = resData.type || 'success';

            // Основное уведомление (краткое)
            ElMessage({
                type: msgType,
                message: resData.message,
                duration: msgType === 'warning' ? 5000 : 3000,
            });

            if (resData.skipped_details && Array.isArray(resData.skipped_details) && resData.skipped_details.length > 0) {

                // Формируем HTML-список ошибок
                const errorListHtml = resData.skipped_details.map(error => {
                    return `<li style="margin-bottom: 8px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; font-size: 13px;">
                        <strong style="color: #856404;">⚠️ ${error}</strong>
                    </li>`;
                }).join('');

                ElNotification({
                    title: `⚠️ Пропущено фильмов: ${resData.skipped_details.length}`,
                    type: 'warning',
                    duration: 0, // Не закрывается автоматически
                    dangerouslyUseHTMLString: true,
                    message: `
                        <div style="max-height: 400px; overflow-y: auto; margin-top: 10px;">
                            <p style="margin-bottom: 12px; color: #666; font-weight: bold;">
                                Следующие фильмы не были экспортированы из-за отсутствия обязательных полей:
                            </p>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                ${errorListHtml}
                            </ul>
                        </div>
                    `,
                    position: 'top-right',
                });
            }

            messageExportMovie.type = msgType;
            messageExportMovie.text = resData.message;

            if (resData.sent_count !== undefined) {
                countsExportMovie.value = resData.sent_count;
            }

            if (msgType === 'success' && resData.remaining === 0) {
                disableBtnMovieExport.value = true;
            } else {
                disableBtnMovieExport.value = false;
            }

            getExportMovies();
            loader.value = false;

        }).catch((error) => {
            console.error(error);
            ElMessage({
                type: 'error',
                message: 'Ошибка соединения или сервера при экспорте',
            });
            loader.value = false;
        }).finally(() => {
            spinBtnExportMovie.value = false;
        });
    }
    const exportTaxonomy = async () => {
        spinBtnExportTaxonomy.value = true;
        try {
            const response = await axios.post('/taxonomies/send');
            const resData = response.data.data ? response.data.data : response.data;

            if (resData.success) {
                ElMessage({ type: 'success', message: resData.message, duration: 4000 });
                messageExportTaxonomy.value = resData.message;
                // Сохраняем как число, а не как массив
                countsExportTaxonomy.value = resData.sent_count || 0;
                disableBtnTaxonomyExport.value = true;
            } else {
                ElMessage({ type: resData.type || 'warning', message: resData.message });
                disableBtnTaxonomyExport.value = false;
            }
        } catch (error) {
            console.error(error);
            ElMessage({ type: 'error', message: 'Ошибка соединения или сервера при экспорте таксономий' });
        } finally {
            spinBtnExportTaxonomy.value = false;
        }
    }

    const exportTags = async () => {
        spinBtnExportTag.value = true;
        try {
            const response = await axios.post('/tags/send');
            const resData = response.data.data ? response.data.data : response.data;

            if (resData.success) {
                ElMessage({ type: 'success', message: resData.message, duration: 4000 });
                messageExportTag.value = resData.message;
                // Сохраняем как число, а не как массив
                countsExportTag.value = resData.sent_count || 0;
                disableBtnTagExport.value = true;
                disableBtnMovieExport.value = false;
            } else {
                ElMessage({ type: resData.type || 'warning', message: resData.message });
                disableBtnTagExport.value = false;
            }
        } catch (error) {
            console.error(error);
            ElMessage({ type: 'error', message: 'Ошибка соединения или сервера при экспорте тегов' });
        } finally {
            spinBtnExportTag.value = false;
        }
    }
    const exportFullTaxonomy = async () => {
        spinBtnFullTaxonomy.value = true;
        try {
            const response = await axios.post('/taxonomies/send-full');
            const resData = response.data;

            if (resData.success && resData.data) {
                ElMessage({ type: 'success', message: resData.data.message || 'Синхронизация завершена' });

                // Формируем детальный HTML-отчет
                const details = resData.data.details || {};
                const reportHtml = Object.entries(details).map(([key, info]) => {
                    const color = info.status === 'success' ? '#67c23a' : '#f56c6c';
                    const icon = info.status === 'success' ? '✅' : '❌';
                    return `<div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #eee;">
                        <span>${icon} <strong>${translateKey(key)}</strong></span>
                        <span style="color: ${color}; font-weight: bold;">${info.count} записей (${info.message})</span>
                    </div>`;
                }).join('');

                ElNotification({
                    title: '📊 Детальный отчет: Таксономии',
                    type: 'success',
                    duration: 0,
                    dangerouslyUseHTMLString: true,
                    message: `<div style="min-width: 350px;">${reportHtml}</div>`,
                    position: 'top-right',
                });
            } else {
                throw new Error(resData.message || 'Ошибка синхронизации');
            }
        } catch (error) {
            console.error(error);
            ElMessage({ type: 'error', message: error.message || 'Ошибка соединения при полной синхронизации' });
        } finally {
            spinBtnFullTaxonomy.value = false;
        }
    };

    const exportFullTags = async () => {
        spinBtnFullTags.value = true;
        try {
            const response = await axios.post('/tags/send-full');
            const resData = response.data;

            if (resData.success && resData.data) {
                ElMessage({ type: 'success', message: resData.data.message || 'Синхронизация завершена' });

                const details = resData.data.details || {};
                const reportHtml = Object.entries(details).map(([key, info]) => {
                    const color = info.status === 'success' ? '#67c23a' : '#f56c6c';
                    const icon = info.status === 'success' ? '✅' : '❌';
                    return `<div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #eee;">
                        <span>${icon} <strong>${translateKey(key)}</strong></span>
                        <span style="color: ${color}; font-weight: bold;">${info.count} записей (${info.message})</span>
                    </div>`;
                }).join('');

                ElNotification({
                    title: '📊 Детальный отчет: Теги',
                    type: 'success',
                    duration: 0,
                    dangerouslyUseHTMLString: true,
                    message: `<div style="min-width: 350px;">${reportHtml}</div>`,
                    position: 'top-right',
                });
            } else {
                throw new Error(resData.message || 'Ошибка синхронизации');
            }
        } catch (error) {
            console.error(error);
            ElMessage({ type: 'error', message: error.message || 'Ошибка соединения при полной синхронизации' });
        } finally {
            spinBtnFullTags.value = false;
        }
    };

    const translateKey = (key) => {
        const map = {
            'categories': 'Категории',
            'collections': 'Коллекции',
            'franchises': 'Франшизы',
            'collections_categories_pivots': 'Связи (Коллекции/Франшизы)',
            'tags': 'Теги',
            'tags_movies_pivots': 'Связи (Теги/Фильмы)'
        };
        return map[key] || key;
    };
    const exportFullMovies = async () => {
        spinBtnFullMovie.value = true;
        try {
            const response = await axios.post('/movies/send-full');
            const resData = response.data.data ? response.data.data : response.data;
            const msgType = resData.type || 'success';

            ElMessage({
                type: msgType,
                message: resData.message || 'Синхронизация фильмов завершена',
                duration: 5000,
            });

            messageExportFullMovie.type = msgType;
            messageExportFullMovie.text = resData.message;

            if (resData.sent_count !== undefined) {
                countsExportFullMovie.value = resData.sent_count;
            }

            // Если есть ошибки валидации, показываем их
            if (resData.skipped_details && Array.isArray(resData.skipped_details) && resData.skipped_details.length > 0) {
                const errorListHtml = resData.skipped_details.map(error => {
                    return `<li style="margin-bottom: 8px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px; font-size: 13px;">
                        <strong style="color: #856404;">⚠️ ${error}</strong>
                    </li>`;
                }).join('');

                ElNotification({
                    title: `⚠️ Пропущено фильмов: ${resData.skipped_details.length}`,
                    type: 'warning',
                    duration: 0,
                    dangerouslyUseHTMLString: true,
                    message: `<div style="max-height: 400px; overflow-y: auto; margin-top: 10px;">
                        <p style="margin-bottom: 12px; color: #666; font-weight: bold;">Следующие фильмы не прошли валидацию:</p>
                        <ul style="list-style: none; padding: 0; margin: 0;">${errorListHtml}</ul>
                    </div>`,
                    position: 'top-right',
                });
            }

        } catch (error) {
            console.error(error);
            ElMessage({ type: 'error', message: error.message || 'Ошибка соединения при полной синхронизации фильмов' });
        } finally {
            spinBtnFullMovie.value = false;
        }
    };
    const updateCurrentPage = (param) => {
        statePagination.value.page = param;
    }
    return {
        exportMovies,
        exportTaxonomy,
        exportTags,
        getExportMovies,
        updateCurrentPage,
        exportIndex,
        exportCollections,
        exportFullTaxonomy,
        exportFullTags,
        exportFullMovies,
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
        spinBtnExportIndexImg,
        spinBtnExportCollections,
        disableBtnMovieExport,
        disableBtnTaxonomyExport,
        disableBtnTagExport,
        spinBtnFullTaxonomy,
        spinBtnFullTags,
        spinBtnFullMovie,
        messageExportFullMovie,
        countsExportFullMovie,
        messageExportTaxonomyType,
        messageExportTagType,
    }
});
