// playwright-parser.js — Stable + Poster Retries
const express = require('express');
const { chromium } = require('playwright');

const app = express();
const PORT = 3001;
const EMPTY_GIF = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');

app.get('/parse', async (req, res) => {
    const { url, userAgent, width, height, locale, timezone, waitSelector, proxy } = req.query;

    if (!url) return res.status(400).json({ error: 'URL is required' });

    const isMediaindex = url.includes('/mediaindex?');
    const isPosterIndex = isMediaindex && url.includes('contentTypes=poster');
    const isTitlePage = url.match(/\/title\/tt\d+/) && !isMediaindex;

    let browser;
    try {
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-zygote',
                '--disable-blink-features=AutomationControlled',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--user-agent=' + (userAgent || 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'),
                '--window-size=' + (width || 1920) + ',' + (height || 1080),
            ]
        });

        const context = await browser.newContext({
            viewport: { width: parseInt(width) || 1920, height: parseInt(height) || 1080 },
            // === [RU-LOCALE-MOD] START ===
            locale: locale || 'ru-RU',              // 🔥 Русский
            timezoneId: timezone || 'Europe/Moscow', // 🔥 Москва
            // === [RU-LOCALE-MOD] END ===
            userAgent: userAgent || 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            permissions: ['geolocation'],
            geolocation: { longitude: -74.0060, latitude: 40.7128 },
        });

        await context.addInitScript(() => {
            Object.defineProperty(Navigator.prototype, 'webdriver', { get: () => undefined });
            Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
            Object.defineProperty(Navigator.prototype, 'plugins', { get: () => [1,2,3,4,5] });
            window.chrome = { runtime: {}, loadTimes: () => ({ connectionType: '4g' }), csi: () => ({ startE: Date.now() }) };
        });
        // === [RU-LOCALE-MOD] START ===
        // 🔥 Эмуляция navigator.language через evaluate (дополнительный слой)
        await context.addInitScript(() => {
            Object.defineProperty(navigator, 'language', {
                get: () => 'ru-RU',
                configurable: true
            });
            Object.defineProperty(navigator, 'languages', {
                get: () => ['ru-RU', 'ru', 'en-US', 'en'],
                configurable: true
            });
        });
        // === [RU-LOCALE-MOD] END ===

        const page = await context.newPage();

        // === [RU-LOCALE-MOD] START ===
        // 🔥 Установка куки lc-main для IMDb (до навигации!)
        await context.addCookies([{
            name: 'lc-main',
            value: 'ru_RU',
            domain: '.imdb.com',
            path: '/',
            expires: Math.floor(Date.now() / 1000) + 31536000 // 1 год
        }]);
        // === [RU-LOCALE-MOD] END ===

        // 🔧 Подмена картинок ТОЛЬКО для /title/
        if (isTitlePage) {
            await page.route('**/*.{png,jpg,jpeg,webp,gif,svg}', async route => {
                await route.fulfill({ status: 200, contentType: 'image/gif', body: EMPTY_GIF });
            });
        }

        // 🔧 Загрузка страницы
        // 🔧 Загрузка страницы
        console.log(`🌐 Navigating: ${url}`);

        // Для поиска используем networkidle, чтобы дождаться всех AJAX-запросов
        const waitStrategy = url.includes('/search/') ? 'networkidle' : 'domcontentloaded';

        await page.goto(url, {
            waitUntil: waitStrategy,
            timeout: 60000 // Увеличили таймаут самой загрузки
        });

        // 🔧 Дополнительная пауза для поиска, чтобы JS точно все отрисовал
        if (url.includes('/search/')) {
            await page.waitForTimeout(2000);
        }

        // 🔧 Ожидание селектора
        if (waitSelector) {
            try {
                const waitTimeout = url.includes('/search/') ? 15000 : 10000;
                await page.waitForSelector(waitSelector, { timeout: waitTimeout, state: 'attached' });
            } catch (e) {
                console.warn(`⚠️ Selector "${waitSelector}" not found for ${url}`);
                const currentContent = await page.content();
                console.log(`📄 Current HTML length: ${currentContent.length}`);
                console.log(`📄 First 500 chars: ${currentContent.substring(0, 500)}`);
            }
        }

        // 🔧 Прогрессивный ретрай для постеров
        let html = await page.content();
        if (isPosterIndex && html.length < 50000) {
            console.log(`⚠️ Poster index too short (${html.length} bytes), retrying...`);
            await page.waitForTimeout(2000); // Ждём 2 сек
            await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
            if (waitSelector) {
                try {
                    await page.waitForSelector(waitSelector, { timeout: 8000, state: 'attached' });
                } catch (e) {}
            }
            await page.waitForTimeout(300);
            html = await page.content();
            console.log(`✅ Retry done: ${html.length} bytes`);
        }

        await page.waitForTimeout(200);

        // 🔧 Валидация
        const isBlockedByWaf = html.includes('awsWaf') || html.includes('Access Denied');
        const isCaptchaPage = html.includes('mp_verify') || (html.includes('captcha') && html.length < 50000);

        if (html.length < 2000 || isBlockedByWaf || isCaptchaPage) {
            console.error(`🚫 Blocked content detected. Length: ${html.length}, WAF: ${isBlockedByWaf}, CaptchaPage: ${isCaptchaPage}`);
            throw new Error(`Blocked or empty: ${html.length} bytes`);
        }

        res.send(html);
        console.log(`✅ Sent ${html.length} bytes for ${url}`);

    } catch (error) {
        console.error(`❌ Error for ${url}:`, error.message);
        return res.status(500).json({ error: error.message });
    } finally {
        if (browser) await browser.close().catch(() => {});
    }
});

app.listen(PORT, '0.0.0.0', () => {
    console.log(`⚡ Playwright parser (POSTER-RETRY) running on port ${PORT}`);
});
