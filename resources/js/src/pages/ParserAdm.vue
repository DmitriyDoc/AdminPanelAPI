<template>
    <h3>Parser settings</h3>
    <el-tabs type="border-card">
        <el-tab-pane label="Add Celeb by ID">
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>Celeb ID</span>
                    <el-input
                        v-model="celeb_ID"
                        minlength="8"
                        maxlength="10"
                        placeholder="Input ID CELEB"
                        show-word-limit
                        type="text"
                    />
                </div>
            </div>
            <div v-if="percentageSync" class="mt-1">
                <el-progress :percentage="percentageSync" :status="statusBar"/>
            </div>
            <div>
                <button @click="handleAddCeleb" class="btn btn-primary" type="submit">Add Celeb</button>
            </div>
        </el-tab-pane>
        <el-tab-pane label="Parser start">
            <div style="max-width: 1200px">
                <div style="margin-top: 20px">
                    <div>Choose parser type</div>
                    <el-radio-group v-model="radioTypeToggle" :change="autoChangeRadioType()" @click="handleSelectsClear">
                        <el-radio-button label="Movies" :value=true />
                        <el-radio-button label="Persons" :value=false />
                    </el-radio-group>
                </div>
                <div class="demo-date-picker">
                    <div class="block">
                        <span class="demonstration">Picker date from</span>
                        <el-date-picker
                            v-model="pickerFrom"
                            type="date"
                            :placeholder="placeholderPicker"
                            :disabled-date="disabledDate"
                            :shortcuts="shortcuts"
                            format="YYYY/MM/DD"
                            value-format="YYYY-MM-DD"
                            :disabled="disabledPickerType"
                        />
                    </div>
                    <div class="block">
                        <span class="demonstration">Picker date till</span>
                        <el-date-picker
                            v-model="pickerTill"
                            type="date"
                            :placeholder="placeholderPicker"
                            :disabled-date="disabledDate"
                            :shortcuts="shortcuts"
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
                        Check all movie types
                    </el-checkbox>
                    <el-checkbox-group
                        v-model="checkedTypes"
                        :disabled="disabledPickerType"
                        @change="handleCheckedTypesChange"
                    >
                        <el-checkbox v-for="(type) in types" :key="type" :label="type" >
                            {{ type }}
                        </el-checkbox>
                    </el-checkbox-group>
                </div>
                <div>
                    <div>
                        <el-switch
                            v-model="switchAddNewOrUpdate"
                            class="mb-2"
                            active-text="Parse Only New Person"
                            inactive-text="And Update Old"
                        />
                    </div>
                    <div class="mt-3">Select Sorting</div>
                    <el-select v-model="selectSort" placeholder="Sort by..." style="width: 240px">
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
                    <div class="mt-3">Search Persons Filters</div>
                    <el-select
                        v-model="personsSource"
                        multiple
                        clearable
                        collapse-tags
                        placeholder="Select Filters..."
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
                                All
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
                    <div class="mt-3">Select Type Images</div>
                    <el-select v-model="selectTypeImages" placeholder="Select type..." style="width: 240px">
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
                    <div class="mt-3">Select Type Posters</div>
                    <el-select v-model="selectTypePosters" :disabled ="disabledTypePosters" placeholder="Select type..." style="width: 240px">
                        <el-option
                            v-for="item in typesPosters"
                            :key="item.value"
                            :label="item.label"
                            :value="item.value"
                        />
                    </el-select>
                </div>
                <el-button type="danger" @click="handleParserStart()" :plain="!toggleButton" :disabled="!toggleButton" class="mt-3">Start</el-button>
                <el-button type="info" :plain="toggleButton" :disabled="toggleButton" @click="dialogVisible = true" class="mt-3">Report</el-button>
            </div>
        </el-tab-pane>
        <el-tab-pane label="Role">
            Role</el-tab-pane>
        <el-tab-pane label="Test">
            <el-button type="danger" @click="handleTestTranslate()" class="mt-3">Translate celebs (test)</el-button><br>
        </el-tab-pane>
    </el-tabs>
    <el-dialog
        v-if="Object.keys(parserReport).length"
        v-model="dialogVisible"
        title="Report parser process:"
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
            <h4 class="mt-2">Parse Info Progress:</h4>
            <el-progress :percentage="parserReport.report?.finishIdsPeriod ? parserReport.syncMoviePercentageBar : parserReport.syncPersonPercentageBar" :stroke-width="15" striped />
        </template>
        <template v-if="parserReport.report.finishIdsPeriod">
            <h4 class="mt-2">Finish ID(s) parse by date period:</h4>
            <ul v-for="(item, index) in parserReport.report.finishIdsPeriod" class="list-group">
                <li class="list-group-item">{{index+1}}{{'. '}}{{item}}</li>
            </ul>
        </template>
        <template v-if="parserReport.report.finishLocalizing">
            <el-collapse>
                <el-collapse-item >
                    <template #title>
                        <el-badge :value="parserReport.report.finishLocalizing.length" :max="1000"class="item">
                            Parsed and Localizing<el-icon class="header-icon"><info-filled /></el-icon>
                        </el-badge>
                    </template>
                    <span v-for="item in parserReport.report.finishLocalizing">
                        <el-tag type="success" class="m-1">{{item}}</el-tag>
                    </span>
                </el-collapse-item>
            </el-collapse>
        </template>
        <template v-if="parserReport.report.finishInfo">
            <h4 class="mt-2">Finish Parse Info for Type(s):</h4>
            <ul class="list-group-flush">
                <template v-for="item in parserReport.report.finishInfo">
                    <li class="list-group-item"><el-icon color="#67C23A"  ><Check /></el-icon>{{item}}</li>
                </template>
            </ul>
        </template>
        <template v-if="parserReport.report.finishActualize">
            <h4 class="mt-2">Finish Actualize Table(s):</h4>
            <span v-for="item in parserReport.report.finishActualize" >
                <el-tag type="primary" class="m-1">{{item}}</el-tag>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
    import { storeToRefs } from 'pinia';
    import { ref, watch } from "vue";
    import { ElMessage, ElMessageBox } from "element-plus";
    import { useParserStore } from "../store/parserStore";
    import { useProgressBarStore } from "../store/progressBarStore";
    import { Check } from '@element-plus/icons-vue'

    const parserStore = useParserStore();
    const progressBarStore = useProgressBarStore();

    const { loader, error } = storeToRefs(parserStore);
    const { percentage, statusBar, percentageSync, parserReport } = storeToRefs(progressBarStore);
    const celeb_ID = ref('nm');

    const radioTypeToggle =  ref(true);
    const pickerFrom  = ref(null);
    const pickerTill = ref(null);
    const personsSource = ref([]);
    const disabledPickerType = ref(false);
    const disabledTypePosters = ref(false);
    const disabledMovies = ref(false);
    const disabledPersons = ref(true);
    const placeholderPicker = ref("Pick a day");
    const checkAllTypes = ref(false);
    const checkAllPersonsSource = ref(false)
    const indeterminatePersonsSource = ref(false)
    const isIndeterminateTypes = ref(true);
    const selectSort = ref(null);
    const selectTypeImages = ref(null);
    const selectTypePosters = ref(null);
    const toggleButton = ref(true);
    const switchAddNewOrUpdate = ref(true);
    const types = ['FeatureFilm', 'MiniSeries', 'ShortFilm', 'TvMovie','TvSeries', 'TvShort','TvSpecial','Video'];
    const checkedTypes = ref(types);
    const sort = ref([
        {
            value: 'moviemeter',
            label: 'Popular',
            disabled: false,
        },
        {
            value: 'alpha',
            label: 'A-Z',
            disabled: false,
        },
        {
            value: 'user_rating',
            label: 'User rating',
            disabled: disabledMovies,
        },
        {
            value: 'year',
            label: 'Year',
            disabled: disabledMovies,
        },
        {
            value: 'birth_date',
            label: 'Birth Date',
            disabled: disabledPersons,
        },
        {
            value: 'death_date',
            label: 'Death Date',
            disabled: disabledPersons,
        },
    ]);
    const typesImages = ref([
        {
            value: 'still_frame',
            label: 'Still Frame',
            disabled: disabledMovies,
        },
        {
            value: 'event',
            label: 'Event',
            disabled: disabledPersons,
        },
        {
            value: 'publicity',
            label: 'Publicity',
            disabled: disabledPersons,
        },
    ]);
    const typesPosters = ref([
        {
            value: 'poster',
            label: 'Poster',
        },
        {
            value: 'product',
            label: 'Product',
        },
    ]);
    const filters = ref([
        {
            value: '?gender=male',
            label: 'Gender Male',
        },
        {
            value: '?gender=female',
            label: 'Gender Female',
        },
        {
            value: '?gender=non_binary',
            label: 'Gender Non Binary',
        },
        {
            value: '?gender=other',
            label: 'Gender Other',
        },
        {
            value: '?groups=oscar_best_actress_nominees',
            label: 'Oscar best actress nominees',
        },
        {
            value: '?groups=oscar_best_actor_nominees',
            label: 'Oscar best actor nominees',
        },
        {
            value: '?groups=oscar_best_actress_winners',
            label: 'Oscar best actress winners',
        },
        {
            value: '?groups=oscar_best_actor_winners',
            label: 'Oscar best actor winners',
        },
        {
            value: '?groups=oscar_best_supporting_actress_nominees',
            label: 'Oscar best supporting actress nominees',
        },
        {
            value: '?groups=oscar_best_director_nominees',
            label: 'Oscar best director nominees',
        },
        {
            value: '?groups=best_director_winner',
            label: 'Best director winner',
        },
        {
            value: '?groups=oscar_nominee',
            label: 'Oscar nominee',
        },
        {
            value: '?groups=emmy_nominee',
            label: 'Emmy nominee',
        },
        {
            value: '?groups=golden_globe_nominated',
            label: 'Golden globe nominated',
        },
        {
            value: '?groups=oscar_winner',
            label: 'Oscar winner',
        },
        {
            value: '?groups=emmy_winner',
            label: 'Emmy winner',
        },
        {
            value: '?groups=golden_globe_winning',
            label: 'Golden globe winning',
        },
    ]);

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
    const shortcuts = [
        {
            text: 'Today',
            value: new Date(),
        },
        {
            text: 'Yesterday',
            value: () => {
                const date = new Date()
                date.setTime(date.getTime() - 3600 * 1000 * 24)
                return date
            },
        },
    ];

    const disabledDate = (time) => {
        return time.getTime() > Date.now()
    };
    const autoChangeRadioType = () => {
        if(radioTypeToggle.value){
            disabledPickerType.value = false;
            disabledMovies.value = false;
            disabledPersons.value = true;
            placeholderPicker.value = "Pick a day";
            disabledTypePosters.value = false;
            sort.value[0]['value'] = 'moviemeter';
            switchAddNewOrUpdate.value = false;
        } else {
            disabledPickerType.value = true;
            disabledMovies.value = true;
            disabledPersons.value = false;
            placeholderPicker.value = "Picker DISABLED";
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
        checkedTypes.value = val ? types : []
        isIndeterminateTypes.value = false
    };
    const handleCheckedTypesChange = (value) => {
        const checkedCount = value.length
        checkAllTypes.value = checkedCount === types.length
        isIndeterminateTypes.value = checkedCount > 0 && checkedCount < types.length
    };
    watch(personsSource, (val) => {
        if (val.length === 0) {
            checkAllPersonsSource.value = false
            indeterminatePersonsSource.value = false
        } else if (val.length === filters.value.length) {
            checkAllPersonsSource.value = true
            indeterminatePersonsSource.value = false
        } else {
            indeterminatePersonsSource.value = true
        }
    })
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
