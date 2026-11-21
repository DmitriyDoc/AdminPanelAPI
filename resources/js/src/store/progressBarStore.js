import { defineStore } from "pinia";
import axios from 'axios'
import { ref } from "vue";
import { io } from "socket.io-client";

export const useProgressBarStore = defineStore('progressBarStore', () => {
    const socketURI = "http://spectrum.local";
    const percentage = ref(0);
    const parserReport = ref({});
    const chartProgress = ref({});
    const syncActionsHistory = ref([]);
    const resizeActionsHistory = ref([]);
    const getCurrentPercentage = () => {
        const socket = io(socketURI);
        socket.on("connect", (s) => {
            console.log(`connect ${socket.id}`);
            socket.on('adminpanel_spectrum_database_dashboard-bar:App\\Events\\DashboardCurrentPercentageEvent', function (data) {
                percentage.value = data.dashboardBar;
            });
        });
    }

    const getSyncCurrentPercentage = () => {
        syncActionsHistory.value = [];
        const socket = io(socketURI);
        socket.on("connect", (s) => {
            socket.on('adminpanel_spectrum_database_sync-bar:App\\Events\\CurrentPercentageEvent', function (data) {
                const actionData = data.syncBar;

                if (actionData && actionData.action) {
                    if (!syncActionsHistory.value.includes(actionData.action)) {
                        syncActionsHistory.value.push(actionData.action);
                    }
                }
            });
        });
    }

    const getCurrentReportState = () => {
        const socket = io(socketURI);
        socket.on("connect", (s) => {
            socket.on('adminpanel_spectrum_database_parser-report:App\\Events\\ParserReportEvent', function (data) {
                parserReport.value = data.result;
            });
        });
    }


    const getChartProgressState = () => {
        const socket = io(socketURI);
        socket.on("connect", (s) => {
            console.log(`Chart progress socket connected: ${socket.id}`);
            socket.on('adminpanel_spectrum_database_chart-bar:App\\Events\\ChartProgressEvent', function (data) {
                chartProgress.value = data.chartBar;
            });
        });
    }

    const getResizeCurrentPercentage = () => {
        resizeActionsHistory.value = [];
        const socket = io(socketURI);
        socket.off('adminpanel_spectrum_database_resize-bar:App\\Events\\ResizeReportEvent');

        socket.on("connect", (s) => {
            socket.on('adminpanel_spectrum_database_resize-bar:App\\Events\\ResizeReportEvent', function (data) {
                const actionData = data.resizeBar;

                if (actionData && (actionData.title || actionData.category)) {
                    // Проверяем на дубликаты по комбинации title и category
                    const isDuplicate = resizeActionsHistory.value.some(
                        (item) => item.title === actionData.title && item.category === actionData.category
                    );

                    if (!isDuplicate) {
                        resizeActionsHistory.value.push(actionData);
                    }
                }
            });
        });
    }
    return {
        percentage,
        parserReport,
        chartProgress,
        syncActionsHistory,
        resizeActionsHistory,
        getCurrentReportState,
        getCurrentPercentage,
        getSyncCurrentPercentage,
        getChartProgressState,
        getResizeCurrentPercentage
    }
});
