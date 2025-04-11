import { defineStore } from "pinia";
import axios from 'axios'
import { ref }  from "vue";
import { io } from "socket.io-client";
export const useProgressBarStore = defineStore('progressBarStore',() => {
    const socketURI = "http://spectrum.local:3000";
    const percentage = ref(0);
    const percentageSync = ref({});
    const parserReport = ref({});

    const getCurrentPercentage = () => {
        const socket = io(socketURI);
        socket.on("connect", (s) => {
            //console.log(`connect ${socket.id}`);
            socket.on('laravel_database_dashboard-bar:App\\Events\\DashboardCurrentPercentageEvent', function (data) {
                percentage.value = data.dashboardBar;
            });
        });
    }
    const getSyncCurrentPercentage = () => {
        const socket = io(socketURI);
        socket.on("connect", (s) => {
            //console.log(`connect ${socket.id}`);
            socket.on('laravel_database_sync-bar:App\\Events\\CurrentPercentageEvent', function (data) {
                percentageSync.value = data.syncBar;
            });
        });
    }
    const getCurrentReportState = () => {
        const socket = io(socketURI);
        socket.on("connect", (s) => {
            //console.log(`connect ${socket.id}`);
            socket.on('laravel_database_parser-report:App\\Events\\ParserReportEvent', function (data) {
                parserReport.value  = data.result;
            });
        });
    }
    return {
        percentage,
        percentageSync,
        parserReport,
        getCurrentReportState,
        getCurrentPercentage,
        getSyncCurrentPercentage,
    }
});
