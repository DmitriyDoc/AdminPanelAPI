<template>
    <h3>Parser settings:</h3>
    <el-tabs type="border-card">
        <el-tab-pane label="Add Celeb by ID">
            <div class="mt-3">
                <div class="input-group mb-3">
                    <span>Celeb ID:</span>
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
            <div>
                <button @click="handleAddCeleb" class="btn btn-primary" type="submit">Add Celeb</button>
            </div>
        </el-tab-pane>
        <el-tab-pane label="Config">Config</el-tab-pane>
        <el-tab-pane label="Role">Role</el-tab-pane>
        <el-tab-pane label="Task">Task</el-tab-pane>
    </el-tabs>
</template>

<script setup>
    import { storeToRefs } from 'pinia';
    import {ref} from "vue";
    import {ElMessage, ElMessageBox} from "element-plus";
    import { usePersonsStore } from "../store/personsStore";

    const celeb_ID = ref('nm');
    const personsStore = usePersonsStore();

    const handleAddCeleb = () => {
        ElMessageBox.confirm(`Are you sure?`, 'WARNING', {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
        }).then(() => {
            personsStore.addCelebById(celeb_ID.value);
            celeb_ID.value = 'nm';
        }).catch(() => {
            ElMessage({
                type: 'info',
                message: 'Add canceled',
            })
        })
    }

</script>

<style scoped>

</style>
