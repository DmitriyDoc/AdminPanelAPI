const express = require('express');
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const fs = require('fs');
const path = require('path');

puppeteer.use(StealthPlugin());

const app = express();
const PORT = process.env.PORT || 3001;
const COOKIES_FILE = path.join(__dirname, 'imdb_cookies.json');

app.use(express.json({ limit: '15mb' }));

const sendResult = (res, url, html = null, error = null) => {
    res.json({
        status: html ? 'success' : 'error',
        url,
        html,
        length: html ? html.length : 0,
        error: error ? (error.message || String(error)) : null
    });
};

const loadCleanCookies = () => {
    try {
        if (fs.existsSync(COOKIES_FILE)) {
            const all = JSON.parse(fs.readFileSync(COOKIES_FILE, 'utf8'));
            return all.filter(c => ['aws-waf-token', 'session-id', 'session-id-time', 'ci'].includes(c.name));
        }
        return [];
    } catch (e) { return []; }
};

const attemptFetch = async (url, attemptNumber = 1, minBytesOverride = null) => {
    const cleanUrl = url.trim();
    let minBytes = 10000;
    if (cleanUrl.match(/\/title\/tt\d+\/$/)) minBytes = 50000;
    if (minBytesOverride !== null) minBytes = minBytesOverride;

    let browser, page;
    try {
        const proxyUrl = process.env.HTTP_PROXY || process.env.HTTPS_PROXY;
        const args = [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-blink-features=AutomationControlled',
            '--disable-gpu',
            '--disable-dev-shm-usage'
        ];
        if (proxyUrl) args.push(`--proxy-server=${proxyUrl}`);

        console.log(`🚀 Запуск браузера (попытка ${attemptNumber})...`);

        browser = await puppeteer.launch({
            headless: true,
            executablePath: '/usr/bin/chromium',
            args,
            defaultViewport: { width: 1920, height: 1080 }
        });

        page = await browser.newPage();

        const cookies = loadCleanCookies();
        if (cookies.length > 0) await page.setCookie(...cookies);

        await page.setExtraHTTPHeaders({
            'Accept-Language': 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Sec-Fetch-Dest': 'document', 'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'none', 'Upgrade-Insecure-Requests': '1'
        });

        console.log(`🌐 Переход на: ${cleanUrl}`);
        await page.goto(cleanUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await new Promise(r => setTimeout(r, 2000));

        const html = await page.content();
        if (html.length < minBytes || html.includes('403 Forbidden') || html.includes('gokuProps')) {
            throw new Error(`WAF Block or Too Short (${html.length} bytes)`);
        }

        console.log(`✅ Успех: ${html.length} байт`);
        return html;

    } catch (error) {
        console.error(`❌ ОШИБКА: ${error.message || error}`);
        if (attemptNumber < 2 && (!error.message || !error.message.includes('WAF'))) {
            console.log("🔄 Повторная попытка через 2 сек...");
            await new Promise(r => setTimeout(r, 2000));
            return attemptFetch(url, attemptNumber + 1, minBytes);
        }
        throw error;
    } finally {
        if (page) try { await page.close(); } catch(e){}
        if (browser) try { await browser.close(); } catch(e){}
    }
};

app.post('/parse/info', async (req, res) => {
    try {
        const html = await attemptFetch(req.body.url, 1, 10000);
        sendResult(res, req.body.url, html);
    } catch (error) { sendResult(res, req.body.url, null, error); }
});

app.get('/health', (req, res) => res.json({ status: 'ok' }));
app.listen(PORT, '0.0.0.0', () => console.log(`⚡ Local Stealth Parser on port ${PORT}`));
