<template>
    <template v-if="locale">
        <h3 class="text-center mt-3 mb-3">{{locale.parser_settings}}</h3>
        <el-tabs type="border-card" >
            <el-tab-pane :label="locale.add_movie_by_id">
                <div class="mt-3">
                    <div class="input-group mb-3">
                        <span>{{locale.enter_movie_id??''}}</span>
                        <el-input
                            v-model="movie_ID"
                            minlength="8"
                            maxlength="10"
                            :placeholder="locale.input_movie_id"
                            show-word-limit
                            type="text"
                        />
                    </div>
                    <div class="mt-3">{{locale.select_table_type}}</div>
                    <el-select v-model="selectType" :placeholder="locale.select_table_for_this_movie" style="width: 240px">
                        <el-option
                            v-for="(item, key) in types"
                            :key="key"
                            :label="item"
                            :value="key"
                        />
                    </el-select>
                    <div class="mt-3 image-type">
                        <h5>{{locale.select_poster_type}}</h5>
                        <el-radio-group v-model="posterType">
                            <el-radio value="poster" >[ poster ]</el-radio>
                            <el-radio value="product" >[ product ]</el-radio>
                        </el-radio-group>
                    </div>
                </div>
                <div v-if="percentageSync" class="mt-1">
                    <el-progress :percentage="percentageSync" :status="statusBar"/>
                </div>
                <div class="mt-3">
                    <button @click="handleAddMovie" class="btn btn-primary" >{{locale.add_movie}}</button>
                </div>
            </el-tab-pane>
            <el-tab-pane :label="locale.add_celebs_by_id">
                <div class="mt-3">
                    <div class="input-group mb-3">
                        <span>{{locale.enter_person_id}}</span>
                        <el-input
                            v-model="celeb_ID"
                            minlength="8"
                            maxlength="10"
                            :placeholder="locale.input_person_id"
                            show-word-limit
                            type="text"
                        />
                    </div>
                </div>
                <div v-if="percentageSync" class="mt-1">
                    <el-progress :percentage="percentageSync" :status="statusBar"/>
                </div>
                <div>
                    <button @click="handleAddCeleb" class="btn btn-primary" >{{locale.add_person}}</button>
                </div>
            </el-tab-pane>
            <el-tab-pane :label="locale.parser_start">
                <div style="max-width: 1200px">
                    <div style="margin-top: 20px">
                        <div>{{locale.choose_parser_type}}</div>
                        <el-radio-group v-model="radioTypeToggle" :change="autoChangeRadioType()" @click="handleSelectsClear">
                            <el-radio-button :label="locale.movies" :value=true />
                            <el-radio-button :label="locale.persons" :value=false />
                        </el-radio-group>
                    </div>
                    <div class="demo-date-picker">
                        <div class="block">
                            <span class="demonstration">{{localeDatePicker.from}}</span>

                            <el-date-picker
                                v-model="pickerFrom"
                                type="date"
                                :placeholder="placeholderPicker"
                                :disabled-date="disabledDate"
                                format="YYYY/MM/DD"
                                value-format="YYYY-MM-DD"
                                :disabled="disabledPickerType"
                            />
                        </div>
                        <div class="block">
                            <span class="demonstration">{{localeDatePicker.till}}</span>
                            <el-date-picker
                                v-model="pickerTill"
                                type="date"
                                :placeholder="placeholderPicker"
                                :disabled-date="disabledDate"
                                format="YYYY/MM/DD"
                                value-format="YYYY-MM-DD"
                                :disabled="disabledPickerType"
                            />
                        </div>
                    </div>
                    <div>
                        <el-checkbox
                            v-model="checkAllTypes"
                            :indeterminate="isIndeterminateTypes"
                            :disabled="disabledPickerType"
                            @change="handleCheckAllChange"
                        >
                            <span>{{locale.choose_movie_types}}</span>
                        </el-checkbox>
                        <el-checkbox-group
                            v-model="checkedTypes"
                            :disabled="disabledPickerType"
                            @change="handleCheckedTypesChange"
                        >
                            <el-checkbox v-for="(item, key) in types"
                                         :key="key"
                                         :label="key" >
                                {{ item }}
                            </el-checkbox>
                        </el-checkbox-group>
                    </div>
                    <div>
                        <div class="mt-3">
                            <el-switch
                                v-model="switchAddNewOrUpdate"
                                class="mb-2"
                                :active-text="locale.and_update_old"
                                :inactive-text="locale.parse_only_new_person"
                            />
                        </div>
                        <div class="mt-3">{{locale.select_sorting}}</div>
                        <el-select v-model="selectSort" :placeholder="locale.sort_by" style="width: 240px">
                            <el-option
                                v-for="item in sort"
                                :key="item.value"
                                :label="item.label"
                                :value="item.value"
                                :disabled="item.disabled"
                            />
                        </el-select>
                    </div>
                    <div>
                        <div class="mt-3">{{locale.search_persons_filters}}</div>
                        <el-select
                            v-model="personsSource"
                            multiple
                            clearable
                            collapse-tags
                            :placeholder="locale.select_filters"
                            popper-class="custom-header"
                            :max-collapse-tags="1"
                            :disabled="disabledPersons"
                            style="width: 240px"
                        >
                            <template #header>
                                <el-checkbox
                                    v-model="checkAllPersonsSource"
                                    :indeterminate="indeterminatePersonsSource"
                                    @change="handleCheckAllPersonsSource"
                                >
                                    <span>{{locale.sort_by_all}}</span>
                                </el-checkbox>
                            </template>
                            <el-option
                                v-for="item in filters"
                                :key="item.value"
                                :label="item.label"
                                :value="item.value"
                            />
                        </el-select>
                    </div>
                    <div>
                        <div class="mt-3">{{locale.select_type_images}}</div>
                        <el-select v-model="selectTypeImages" :placeholder="locale.select_type" style="width: 240px">
                            <el-option
                                v-for="item in typesImages"
                                :key="item.value"
                                :label="item.label"
                                :value="item.value"
                                :disabled="item.disabled"
                            />
                        </el-select>
                    </div>
                    <div>
                        <div class="mt-3">{{locale.select_type_posters}}</div>
                        <el-select v-model="selectTypePosters" :disabled ="disabledTypePosters" :placeholder="locale.select_type" style="width: 240px">
                            <el-option
                                v-for="item in typesPosters"
                                :key="item.value"
                                :label="item.label"
                                :value="item.value"
                            />
                        </el-select>
                    </div>
                    <el-button type="danger" @click="handleParserStart()" :plain="!toggleButton" :disabled="!toggleButton" class="mt-3">{{locale.start}}</el-button>
                    <el-button type="info" :plain="toggleButton" :disabled="toggleButton" @click="dialogVisible = true" class="mt-3">{{locale.report}}</el-button>
                </div>
            </el-tab-pane>
            <el-tab-pane label="Test">
                <el-button type="danger" @click="handleTestTranslate()" class="mt-3">Handle (test)</el-button><br>
            </el-tab-pane>
        </el-tabs>
        <el-dialog
            v-if="Object.keys(parserReport).length"
            v-model="dialogVisible"
            :title="locale.parse_progress"
            width="615"
            style="overflow-y: scroll;height: calc(100vh - 200px);"
            :before-close="handleClose"
        >
            <template v-if="parserReport.report">
                <el-result
                    :icon="parserReport.report.stop ? 'success' : 'info'"
                    :title="parserReport.report.stop ?? parserReport.report.start"
                >
                </el-result>
            </template>
            <template v-if="parserReport.report">
                <h4 class="mt-2">{{locale.current_bar_progress}}</h4>
                <el-progress :percentage="parserReport.report?.finishIdsPeriod ? parserReport.syncMoviePercentageBar : parserReport.syncPersonPercentageBar" :stroke-width="15" striped />
            </template>
            <template v-if="parserReport.report.finishIdsPeriod">
                <h4 class="mt-2">{{locale.finish_id_parse_by_date_period}}</h4>
                <ul v-for="(item, index) in parserReport.report.finishIdsPeriod" class="list-group">
                    <li class="list-group-item">{{index+1}}{{'. '}}{{item}}</li>
                </ul>
            </template>
            <template v-if="parserReport.report.finishLocalizing">
                <el-collapse>
                    <el-collapse-item >
                        <template #title>
                            <el-badge :value="parserReport.report.finishLocalizing.length" :max="1000"class="item">
                                {{locale.parsed_and_localizing}}<el-icon class="header-icon"><info-filled /></el-icon>
                            </el-badge>
                        </template>
                        <span v-for="item in parserReport.report.finishLocalizing">
                            <el-tag type="success" class="m-1">{{item}}</el-tag>
                        </span>
                    </el-collapse-item>
                </el-collapse>
            </template>
            <template v-if="parserReport.report.finishInfo">
                <h4 class="mt-2">{{locale.finish_parse_for_types}}</h4>
                <ul class="list-group-flush">
                    <template v-for="item in parserReport.report.finishInfo">
                        <li class="list-group-item"><el-icon color="#67C23A"  ><Check /></el-icon>{{item}}</li>
                    </template>
                </ul>
            </template>
    <!--        <template v-if="parserReport.report.finishActualize">-->
    <!--            <h4 class="mt-2">Finish Actualize Table(s):</h4>-->
    <!--            <span v-for="item in parserReport.report.finishActualize" >-->
    <!--                <el-tag type="primary" class="m-1">{{item}}</el-tag>-->
    <!--            </span>-->
    <!--        </template>-->
        </el-dialog>
    </template>
    <template v-else>
        <p style="text-align: center">{{$t('data_not_found')}}</p>
    </template>
</template>

<script setup lang="ts">
    import { storeToRefs } from 'pinia';
    import { onMounted, ref, watch } from "vue";
    import { ElMessage, ElMessageBox } from "element-plus";
    import { useParserStore } from "../store/parserStore";
    import { useProgressBarStore } from "../store/progressBarStore";
    import { useMoviesStore } from "../store/moviesStore";
    import { useLanguageStore } from "../store/languageStore";
    import { Check } from '@element-plus/icons-vue'

    const parserStore = useParserStore();
    const progressBarStore = useProgressBarStore();
    const moviesStore = useMoviesStore();
    const languageStore = useLanguageStore();

    const { loader, locale, filters, localeDatePicker, types, error } = storeToRefs(parserStore);
    const { percentage, statusBar, percentageSync, parserReport } = storeToRefs(progressBarStore);
    const { watcherLang } = storeToRefs( languageStore );
    const celeb_ID = ref('nm');
    const movie_ID = ref('tt');

    const radioTypeToggle =  ref(true);
    const pickerFrom  = ref(null);
    const pickerTill = ref(null);
    const personsSource = ref([]);
    const disabledPickerType = ref(false);
    const disabledTypePosters = ref(false);
    const disabledMovies = ref(false);
    const disabledPersons = ref(true);
    const placeholderPicker = ref(localeDatePicker.value.enabled);
    const checkAllTypes = ref(false);
    const checkAllPersonsSource = ref(false)
    const indeterminatePersonsSource = ref(false)
    const isIndeterminateTypes = ref(true);
    const selectSort = ref(null);
    const selectType = ref(null);
    const posterType = ref('poster');
    const selectTypeImages = ref(null);
    const selectTypePosters = ref(null);
    const toggleButton = ref(true);
    const switchAddNewOrUpdate = ref(true);
    const typesDefault = ['FeatureFilm', 'MiniSeries', 'ShortFilm', 'TvMovie','TvSeries', 'TvShort','TvSpecial','Video'];
    const checkedTypes = ref(typesDefault);
    const sort = ref([
        {
            value: 'moviemeter',
            label: '[ moviemeter ]',
            disabled: false,
        },
        {
            value: 'alpha',
            label: '[ alpha ]',
            disabled: false,
        },
        {
            value: 'user_rating',
            label: '[ user_rating ]',
            disabled: disabledMovies,
        },
        {
            value: 'year',
            label: '[ year ]',
            disabled: disabledMovies,
        },
        {
            value: 'birth_date',
            label: '[ birth_date ]',
            disabled: disabledPersons,
        },
        {
            value: 'death_date',
            label: '[ death_date ]',
            disabled: disabledPersons,
        },
    ]);
    const typesImages = ref([
        {
            value: 'still_frame',
            label: '[ still_frame ]',
            disabled: disabledMovies,
        },
        {
            value: 'event',
            label: '[ event ]',
            disabled: disabledPersons,
        },
        {
            value: 'publicity',
            label: '[ publicity ]',
            disabled: disabledPersons,
        },
    ]);
    const typesPosters = ref([
        {
            value: 'poster',
            label: '[ poster ]',
        },
        {
            value: 'product',
            label: '[ product ]',
        },
    ]);

    watch(() => watcherLang.value, (newLang) => {
        parserStore.getLocale();
    });

    watch(personsSource, (val) => {
        if (val.length === 0) {
            checkAllPersonsSource.value = false
            indeterminatePersonsSource.value = false
        } else if (val.length === Object.keys(filters).length) {
            checkAllPersonsSource.value = true
            indeterminatePersonsSource.value = false
        } else {
            indeterminatePersonsSource.value = true
        }
    });
    onMounted(() => {
        parserStore.getLocale();
    })
    const dialogVisible = ref(true);
    const handleClose = (done: () => void) => {
        ElMessageBox.confirm('Are you sure to close this report dialog window?')
            .then(() => {
                done()
            })
            .catch(() => {
                // catch error
            })
    };

    const disabledDate = (time) => {
        return time.getTime() > Date.now()
    };

    const autoChangeRadioType = () => {
        if(radioTypeToggle.value){
            disabledPickerType.value = false;
            disabledMovies.value = false;
            disabledPersons.value = true;
            placeholderPicker.value = localeDatePicker.value.enabled;
            disabledTypePosters.value = false;
            sort.value[0]['value'] = 'moviemeter';
            switchAddNewOrUpdate.value = false;
        } else {
            disabledPickerType.value = true;
            disabledMovies.value = true;
            disabledPersons.value = false;
            placeholderPicker.value = localeDatePicker.value.disabled;
            disabledTypePosters.value = true;
            sort.value[0]['value'] = 'starmeter';
        }
    };
    const handleSelectsClear = () => {
        personsSource.value = [];
        selectSort.value = '';
        pickerFrom.value = '';
        pickerTill.value = '';
        selectTypeImages.value = '';
        checkAllTypes.value = false;
        checkedTypes.value = [];
        selectTypePosters.value = '';
    };
    const handleCheckAllChange = (val) => {
        checkedTypes.value = val ? typesDefault : []
        isIndeterminateTypes.value = false
    };
    const handleCheckedTypesChange = (value) => {
        const checkedCount = value.length
        checkAllTypes.value = checkedCount === typesDefault.length
        isIndeterminateTypes.value = checkedCount > 0 && checkedCount < typesDefault.length
    };

    const handleCheckAllPersonsSource = (val) => {
        indeterminatePersonsSource.value = false
        if (val) {
            personsSource.value = filters.value.map((_) => _.value)
        } else {
            personsSource.value = []
        }
    }
    const handleAddCeleb = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            parserStore.addCelebById(celeb_ID.value);
            progressBarStore.getSyncCurrentPercentage();
            celeb_ID.value = 'nm';
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Add canceled',
            })
        });
    };
    const handleAddMovie = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            moviesStore.syncItem({
                id: movie_ID.value,
                type: selectType.value,
                posterType: posterType.value,
            });
            progressBarStore.getSyncCurrentPercentage('syncMoviePercentageBar');
            celeb_ID.value = 'nm';
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Add canceled',
            })
        });
    };
    const handleParserStart = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            if (selectSort.value && selectTypeImages.value){
                parserStore.parserStart({
                    'flag': radioTypeToggle.value,
                    'date_from': pickerFrom.value?pickerFrom.value:"1900-01-01",
                    'date_till': pickerTill.value?pickerTill.value:"1900-01-01",
                    'movie_types': checkedTypes.value,
                    'sort': selectSort.value,
                    'persons_source': personsSource.value,
                    'type_images': selectTypeImages.value,
                    'type_posters': selectTypePosters.value,
                    'switch_new_update': switchAddNewOrUpdate.value,
                });
                handleSelectsClear();
                progressBarStore.getReportParser();
                toggleButton.value = false;
            }
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Parser canceled',
            })
        });
    };
    const  handleTestTranslate = () => {
        parserStore.test();
    }
</script>

<style  lang="scss" scoped>
    :deep(.el-badge__content.is-fixed){
        top: 24px;
    }
    .demo-date-picker {
        display: flex;
        width: 100%;
        padding: 0;
        flex-wrap: wrap;
    }

    .demo-date-picker .block {
        padding: 30px 0;
        text-align: center;
        border-right: solid 1px var(--el-border-color);
        flex: 1;
    }

    .demo-date-picker .block:last-child {
        border-right: none;
    }

    .demo-date-picker .demonstration {
        display: block;
        color: var(--el-text-color-secondary);
        font-size: 14px;
        margin-bottom: 20px;
    }
    .custom-header {
        .el-checkbox {
            display: flex;
            height: unset;
        }
    }
</style>
