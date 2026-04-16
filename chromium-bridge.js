// chromium-bridge.js — Версия с внутренним Retry и умным ожиданием
const express = require('express');
const puppeteer = require('puppeteer-core');

const app = express();
const PORT = process.env.PORT || 3002;
const BROWSERLESS_WS = process.env.BROWSERLESS_WS_URL || 'ws://spectrum_chromium:3000';
const BASE_TIMEOUT = 45000; // Увеличили базовый таймаут

app.use(express.json({ limit: '15mb' }));

// Конфигурация для разных типов страниц
const getPageConfig = (url) => {
    const cleanUrl = url.trim();
    // Для поисковых страниц IMDB нужна особая логика
    if (cleanUrl.includes('/search/title/')) {
        return {
            type: 'search',
            selector: 'ul.ipc-metadata-list--dividers-between', // Ключевой селектор из вашего PHP
            wait: 2000, // Базовая пауза
            strict: true
        };
    }
    if (cleanUrl.includes('/mediaindex?')) {
        return { type: 'mediaindex', selector: '[data-testid="sub-section-images"]', wait: 1500, strict: false };
    }
    if (cleanUrl.includes('/mediaviewer/rm')) {
        const match = cleanUrl.match(/\/mediaviewer\/(rm\d+)/i);
        const id = match ? match[1] : null;
        return {
            type: 'mediaviewer',
            selector: id ? `[data-image-id="${id}-curr"]` : '[data-testid="media-viewer"]',
            wait: 1000,
            strict: false
        };
    }
    return { type: 'other', selector: 'body', wait: 500, strict: false };
};

// Функция попытки загрузки одной страницы
const attemptFetch = async (url, attemptNumber = 1) => {
    const cleanUrl = url.trim().replace(/\s+/g, '');
    const pageConfig = getPageConfig(cleanUrl);
    let browser;

    // Настройки зависят от номера попытки
    const isRetry = attemptNumber > 1;
    const timeout = isRetry ? BASE_TIMEOUT + 15000 : BASE_TIMEOUT;
    const waitUntil = isRetry ? 'networkidle0' : 'networkidle2'; // networkidle0 ждет полнейшей тишины в сети
    const extraWait = isRetry ? 4000 : pageConfig.wait; // Дольше ждем при ретрае

    try {
        browser = await puppeteer.connect({
            browserWSEndpoint: BROWSERLESS_WS,
            defaultViewport: { width: 1920, height: 1080 },
        });

        const page = await browser.newPage();

        // Более реалистичные заголовки
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
        await page.setExtraHTTPHeaders({
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.9,ru;q=0.8',
            'Referer': 'https://www.google.com/',
            'Upgrade-Insecure-Requests': '1'
        });

        // Попытка навигации
        try {
            await page.goto(cleanUrl, {
                waitUntil: waitUntil,
                timeout: timeout
            });
        } catch (navError) {
            // Если навигация упала, но контент частично есть - продолжаем
            if (!isRetry) console.log(`Nav error on first try for ${cleanUrl}, proceeding...`);
        }

        // Скролл для триггера ленивой загрузки (важно для IMDB)
        try {
            await page.evaluate(() => {
                window.scrollBy(0, 500);
                setTimeout(() => window.scrollBy(0, 500), 500);
            });
        } catch (e) {}

        // Ждем конкретный селектор, если он указан
        if (pageConfig.selector && pageConfig.selector !== 'body') {
            try {
                // При ретрае ждем дольше появления элемента
                const selectorTimeout = isRetry ? 8000 : 3000;
                await page.waitForSelector(pageConfig.selector, { timeout: selectorTimeout, visible: true });
            } catch (e) {
                if (pageConfig.strict && !isRetry) {
                    // Если элемент критичен и не найден с первого раза - бросаем ошибку для триггера ретрая
                    throw new Error(`Selector ${pageConfig.selector} not found`);
                }
            }
        }

        // Финальная пауза перед снятием HTML
        await new Promise(r => setTimeout(r, extraWait));

        const html = await page.content();
        await page.close();

        // Валидация результата
        const minLen = pageConfig.strict ? 40000 : 2000;
        const hasBlockers = html.includes('awsWaf') || html.includes('captcha') || html.includes('Access Denied');

        if (html && html.length > minLen && !hasBlockers) {
            // Дополнительная проверка для строгих страниц: есть ли селектор в HTML
            if (pageConfig.strict && !html.includes(pageConfig.selector.replace('[', '').replace(']', '').split('.')[1])) {
                // Если селектора нет в HTML, считаем неудачей
                if (!isRetry) {
                    throw new Error('Content loaded but structure missing');
                }
            }
            return { url: cleanUrl, html, status: 'success', attempts: attemptNumber };
        } else {
            const reason = hasBlockers ? 'Blocked/WAF' : `Too short (${html ? html.length : 0} bytes)`;
            if (!isRetry) {
                throw new Error(reason); // Триггерим ретрай
            }
            return { url: cleanUrl, html: null, status: 'error', error: `Final fail: ${reason}` };
        }

    } catch (error) {
        // ЛОГИКА RETRY ВНУТРИ NODE.JS
        if (!isRetry && attemptNumber < 2) {
            console.log(`⚠️ First attempt failed for ${cleanUrl}: ${error.message}. Retrying with strict mode...`);
            // Небольшая пауза перед повтором
            await new Promise(r => setTimeout(r, 3000));
            return attemptFetch(url, attemptNumber + 1);
        }

        console.error(`❌ Final failure for ${cleanUrl} after ${attemptNumber} attempts:`, error.message);
        return { url: cleanUrl, html: null, status: 'error', error: error.message, attempts: attemptNumber };
    } finally {
        if (browser) await browser.disconnect().catch(() => {});
    }
};

app.post('/fetch', async (req, res) => {
    const { urls, options = {} } = req.body;
    if (!Array.isArray(urls) || urls.length === 0) {
        return res.status(400).json({ error: 'urls array is required' });
    }

    const results = [];
    const concurrency = options.concurrency || 3; // Чуть снизим конкурентность для стабильности
    const queue = [...urls];
    const workers = [];

    console.log(`🚀 Starting batch for ${urls.length} URLs with concurrency ${concurrency}`);

    while (queue.length > 0 || workers.length > 0) {
        while (workers.length < concurrency && queue.length > 0) {
            const url = queue.shift();
            const worker = attemptFetch(url).then(resData => {
                results.push(resData);
                workers.splice(workers.indexOf(worker), 1);
                return resData;
            });
            workers.push(worker);
        }
        if (workers.length > 0) {
            await Promise.race(workers);
        }
    }

    const successCount = results.filter(r => r.status === 'success').length;
    console.log(`✅ Batch complete: ${successCount}/${results.length} successful`);
    res.json({ results });
});

app.get('/health', (req, res) => res.json({ status: 'ok', timestamp: Date.now() }));

process.on('SIGTERM', () => process.exit(0));

app.listen(PORT, '0.0.0.0', () => {
    console.log(`⚡ Chromium Bridge (Retry Enabled) running on port ${PORT}`);
});
