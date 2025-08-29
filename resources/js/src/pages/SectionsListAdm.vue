<template>
    <h3 class="text-center mt-3 mb-3">List sections</h3>
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
            <hr>
            <h4 class="text-center mt-3 mb-3">Index page customization</h4>

            <!-- Формы для изображений -->
            <el-form
                label-width="200px"
                v-if="sectionImages[item.id] "
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
                            accept="image/jpeg,image/png,image/gif"
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
            <div v-else>Загрузка данных секции...</div>
        </el-tab-pane>
    </el-tabs>
    <el-button type="success" @click="handleUpdateImages" class="mt-4">Обновить изображения для текущей секции</el-button>
    <el-image-viewer
        v-if="previewVisible"
        :url-list="[previewImageUrl]"
        @close="closePreview"
    />
</template>

<script setup>
import { storeToRefs } from "pinia";
import { useSectionStore } from "../store/sectionsStore";
import { ref, onMounted, watch } from "vue";
import { ElMessage } from "element-plus";
import { Plus } from '@element-plus/icons-vue';
import { ElImageViewer } from 'element-plus';

const sectionStore = useSectionStore();
const { sections, sectionImagesData } = storeToRefs(sectionStore);
const tabPosition = ref('right');
const activeSectionId = ref(null); // Активная вкладка

const sectionFormRefs = ref({});
const previewVisible = ref(false);
const previewImageUrl = ref('');
const sectionImages = ref({});
const posterIdsInput = ref({});
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
        // Установить первую секцию активной, если секции загружены
        if (sections.value.length > 0) {
            activeSectionId.value = sections.value[0].id.toString();
        }
    });
});

watch([sections, sectionImagesData], () => {
    initSectionImages();
    // Обновить активную секцию, если она ещё не выбрана
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
        const response = await fetch(url);
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

const handleUpdateImages = async () => {
    if (!activeSectionId.value) {
        ElMessage.error('Выберите секцию для обновления');
        return;
    }

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
        errors.push(`Секция ${sectionId}: проверьте обязательные поля (логотип раздела и бэкдроп).`);
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

    if (images.section_logo[0]?.raw || images.section_logo[0]?.url) {
        const file = images.section_logo[0]?.raw || images.section_logo[0];
        formData.append(`sections[${sectionId}][section_logo]`, file);
    }
    if (images.backdrop[0]?.raw || images.backdrop[0]?.url) {
        const file = images.backdrop[0]?.raw || images.backdrop[0];
        formData.append(`sections[${sectionId}][backdrop]`, file);
    }
    if (images.film_logo_ru[0]?.raw || images.film_logo_ru[0]?.url) {
        const file = images.film_logo_ru[0]?.raw || images.film_logo_ru[0];
        formData.append(`sections[${sectionId}][film_logo_ru]`, file);
    }
    if (images.film_logo_en[0]?.raw || images.film_logo_en[0]?.url) {
        const file = images.film_logo_en[0]?.raw || images.film_logo_en[0];
        formData.append(`sections[${sectionId}][film_logo_en]`, file);
    }
    if (images.poster_ids.length >= 7 && images.poster_ids.length <= 10) {
        images.poster_ids.forEach((id) => {
            formData.append(`sections[${sectionId}][poster_ids][]`, id);
        });
    }

    try {
        await sectionStore.updateSectionImages(formData);
        await sectionStore.getSectionImages();
        ElMessage.success(`Изображения для секции ${sectionId} успешно обновлены`);
    } catch (error) {
        console.error('Upload error:', error);
        if (error.response?.status === 413) {
            ElMessage.error('Слишком большой размер файлов. Попробуйте загрузить меньшие изображения.');
        } else {
            ElMessage.error('Ошибка при загрузке изображений.');
        }
    }
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
</style>
