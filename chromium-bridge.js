const express = require('express');
const puppeteer = require('puppeteer-core');

const app = express();
const PORT = process.env.PORT || 3002;
const BROWSERLESS_WS = process.env.BROWSERLESS_WS_URL || 'ws://spectrum_chromium:3000';

app.use(express.json({ limit: '15mb' }));

// Универсальная конфигурация
const getPageConfig = (url) => {
    if (url.includes('/search/title/')) {
        return { type: 'search', minBytes: 50000, wait: 1000 };
    }
    if (url.includes('/mediaindex?')) {
        return { type: 'mediaindex', minBytes: 50000, wait: 800 };
    }
    if (url.includes('/mediaviewer/rm')) {
        return { type: 'mediaviewer', minBytes: 30000, wait: 500 };
    }
    // Главная страница фильма/сериала
    if (url.match(/\/title\/tt\d+\/$/)) {
        return { type: 'main', minBytes: 100000, wait: 1500 };
    }
    return { type: 'other', minBytes: 10000, wait: 500 };
};

const attemptFetch = async (url, attemptNumber = 1) => {
    const cleanUrl = url.trim().replace(/\s+/g, '');
    const config = getPageConfig(cleanUrl);
    let browser;
    let page;

    try {
        browser = await puppeteer.connect({
            browserWSEndpoint: BROWSERLESS_WS,
            defaultViewport: { width: 1920, height: 1080 },
        });

        page = await browser.newPage();

        // 1. УМНАЯ БЛОКИРОВКА: Разрешаем HTML, JS, CSS. Блокируем тяжелые медиа.
        if (cleanUrl.includes('/title/tt')) {
            await page.setRequestInterception(true);
            page.on('request', request => {
                const type = request.resourceType();
                if (type === 'image' || type === 'font' || type === 'media') {
                    request.abort();
                } else {
                    request.continue();
                }
            });
        }

        // 2. БАЗОВАЯ МАСКИРОВКА
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
        await page.setExtraHTTPHeaders({
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language': 'ru-RU,ru;q=0.9,en;q=0.8'
        });
        await page.setCookie({ name: 'lc-main', value: 'ru_RU', domain: '.imdb.com', path: '/' });

        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'language', { get: () => 'ru-RU' });
            Object.defineProperty(navigator, 'languages', { get: () => ['ru-RU', 'ru', 'en-US', 'en'] });
        });

        // 3. НАВИГАЦИЯ
        const timeoutMs = config.type === 'main' ? 30000 : 15000;
        await page.goto(cleanUrl, { waitUntil: 'domcontentloaded', timeout: timeoutMs });

        // 4. ЧЕЛОВЕЧЕСКАЯ ПАУЗА (Вместо скролла)
        // Имитируем время, которое человек тратит на "осмотр" страницы перед действием
        const humanDelay = config.wait + Math.floor(Math.random() * 1000);
        await new Promise(r => setTimeout(r, humanDelay));

        const html = await page.content();

        // 5. ВАЛИДАЦИЯ
        const hasBlockers = html.includes('awsWaf') || html.includes('challenge') || html.includes('Access Denied');

        if (html.length < config.minBytes || hasBlockers) {
            throw new Error(`WAF Block or Too Short (${html.length} bytes, expected >${config.minBytes})`);
        }

        return { url: cleanUrl, html, status: 'success', attempts: attemptNumber };

    } catch (error) {
        // Если это не WAF, а временный сбой сети, делаем одну повторную попытку
        if (attemptNumber < 2 && !error.message.includes('WAF') && !error.message.includes('Short')) {
            await new Promise(r => setTimeout(r, 1500));
            return attemptFetch(url, attemptNumber + 1);
        }
        return { url: cleanUrl, html: null, status: 'error', error: error.message, attempts: attemptNumber };
    } finally {
        if (page) try { await page.close(); } catch(e){}
        if (browser) try { await browser.disconnect(); } catch(e){}
    }
};

// 6. ОБРАБОТКА ЗАПРОСОВ (Строго последовательная для защиты прокси)
app.post('/fetch', async (req, res) => {
    try {
        const { urls } = req.body;
        if (!Array.isArray(urls) || urls.length === 0) {
            return res.status(400).json({ error: 'urls array is required' });
        }

        const results = [];
        // ВАЖНО: Обрабатываем строго по одному (for...of), чтобы не создавать пиковую нагрузку на один IP прокси
        for (const url of urls) {
            results.push(await attemptFetch(url));
        }

        res.json({ results });
    } catch (globalErr) {
        console.error("💥 GLOBAL ERROR:", globalErr);
        res.json({ results: urls.map(u => ({ url: u, status: 'error', error: globalErr.message })) });
    }
});

app.get('/health', (req, res) => res.json({ status: 'ok', timestamp: Date.now() }));

app.listen(PORT, '0.0.0.0', () => {
    console.log(`⚡ Chromium Bridge (PRODUCTION READY) running on port ${PORT}`);
});
