// store/dashboardStore.js
import { defineStore } from "pinia";
import axios from 'axios';
import { ref } from "vue";

export const useDashboardStore = defineStore('dashboardStore', () => {
    const countCard = ref(null);
    const title = ref('');
    const loader = ref(true);
    const error = ref(null);

    const getMoviesCount = async () => {
        try {
            const response = await axios.get('/dashboard');
            countCard.value = response.data;
            title.value = response.data?.locale?.title || '';
        } catch (err) {
            error.value = err.message || 'Ошибка загрузки';
            console.error('Ошибка getMoviesCount:', err);
            throw err;
        } finally {
            setTimeout(() => loader.value = false, 1000);
        }
    };

    const fetchCollectionGraph = async () => {
        try {
            const { data } = await axios.get('/dashboard/graph-collections');
            return data; // Возвращаем данные, компонент сам решит куда их положить
        } catch (err) {
            console.error('Ошибка загрузки графика коллекций:', err);
            throw err;
        }
    };

    const fetchYearsGraph = async () => {
        try {
            const { data } = await axios.get('/dashboard/graph-years');
            return data; // Возвращаем данные, компонент сам решит куда их положить
        } catch (err) {
            console.error('Ошибка загрузки графика по годам:', err);
            throw err;
        }
    };

    const getYAxisConfig = (maxValue) => {
        const actual = maxValue || 100;
        const niceMax = Math.ceil(actual / 50) * 50;
        const rawStep = niceMax / 4;
        const step = Math.ceil(rawStep / 50) * 50;
        const finalMax = step * 4;
        const ticks = [];
        for (let i = finalMax; i >= 0; i -= step) ticks.push(i);
        return { max: finalMax, step, ticks };
    };

    const findCollectionsMax = (data) => {
        if (!data) return 100;
        let max = 0;
        data.forEach(cat => {
            cat.collections?.forEach(c => { if (c.count > max) max = c.count; });
        });
        return max || 100;
    };

    const findYearsMax = (data, mode) => {
        if (!data) return 100;
        if (mode === 'count') {
            const items = data.by_count || [];
            if (!items.length) return 100;
            return Math.max(...items.map(y => y.collections?.[0]?.count || 0));
        }
        if (mode === 'rating') {
            const items = data.by_rating || [];
            if (!items.length) return 100;
            return Math.max(...items.flatMap(y => y.collections?.map(c => c.count) || []));
        }
        return 100;
    };

    const flattenCollections = (data) => {
        if (!data) return [];
        return data.flatMap(cat =>
            (cat.collections || []).map(coll => ({
                ...coll,
                category_name: cat.name,
                color_key: cat.color_key,
                category_id: cat.id
            }))
        );
    };

    const getRatingLegend = (data) => {
        if (!data?.by_rating) return [];
        const buckets = new Map();
        data.by_rating.forEach(year => {
            (year.collections || []).forEach(c => {
                if (!buckets.has(c.id)) {
                    buckets.set(c.id, {
                        id: c.id,
                        name: c.label,
                        color_key: c.color_key
                    });
                }
            });
        });
        return Array.from(buckets.values()).sort((a, b) => {
            const aNum = parseFloat(a.name) || 0;
            const bNum = parseFloat(b.name) || 0;
            return aNum - bNum;
        });
    };

    return {
        countCard,
        title,
        loader,
        error,
        getMoviesCount,
        fetchCollectionGraph,
        fetchYearsGraph,
        getYAxisConfig,
        findCollectionsMax,
        findYearsMax,
        flattenCollections,
        getRatingLegend,
    };
});
