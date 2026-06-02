<template>
    <el-col :span="8" >
        <el-card shadow="hover" class="statistic-card" >
            <el-statistic :value="countCard.count" >
                <template #title>
                    <div style="display: inline-flex; align-items: center">
                        {{ countCard.title }}
                    </div>
                </template>
            </el-statistic>
            <div class="statistic-footer">
                <div class="footer-item">
                   <div class="font-bold">
                       <el-text type="warning">{{ locale.last_update }}</el-text>
                       <span :class="[countCard.lastAddCount  ? 'red' : 'grey']">{{countCard.lastAddCount}}</span>
                   </div>
                    <div class="font-bold">
                        <el-text type="success">{{ locale.published }}</el-text>
                        <span :class="'red'">{{countCard.published}}</span>
                    </div>
                </div>
            </div>
        </el-card>
    </el-col>
</template>

<script setup>
    const props = defineProps({
        countCard: {
            type: Object,
            required: true,
            default: () => {}
        },
        locale: {
            type: Object,
            required: true,
            default: () => {}
        },
    })
</script>

<style scoped lang="scss">
    .el-col {
        margin-bottom: 15px;
    }
    .statistic-card {
        height: 100%;
        padding: 20px;
        border-radius: 4px;
        background-color: #f8f9fa;
    }
    ::v-deep(.el-statistic__head) {
        font-size: 18px;
    }
    .statistic-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        font-size: 12px;
        color: var(--el-text-color-regular);
        margin-top: 16px;
    }
    .red {
        color: var(--el-color-danger);
        padding: 0 5px;
    }
    .grey {
        color: var(--el-text-color-secondary);
        padding: 0 5px;
    }
</style>
