const express = require('express');
const { chromium } = require('playwright');

const app = express();
const PORT = 3001;

app.get('/parse', async (req, res) => {
    const { url, userAgent, width, height, locale, timezone, waitSelector, proxy } = req.query;

    if (!url) {
        return res.status(400).json({ error: 'URL is required' });
    }

    let browser;
    try {
        // 1. Запуск браузера с аргументами для скрытия автоматизации
        browser = await chromium.launch({
            headless: true, // Используем новый режим headless
            args: [
                '--disable-blink-features=AutomationControlled',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-features=IsolateOrigins,site-per-process',
                '--user-agent=' + (userAgent || 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'),
                '--window-size=' + (width || 1920) + ',' + (height || 1080),
            ]
        });

        // 2. Настройка контекста
        const contextOptions = {
            viewport: { width: parseInt(width) || 1920, height: parseInt(height) || 1080 },
            locale: locale || 'en-US',
            timezoneId: timezone || 'America/New_York',
            userAgent: userAgent || 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            permissions: ['geolocation'],
            geolocation: { longitude: -74.0060, latitude: 40.7128 }, // Нью-Йорк
        };

        // Добавляем прокси, если передан
        if (proxy) {
            contextOptions.proxy = { server: proxy };
        }

        const context = await browser.newContext(contextOptions);

        // 3. 🔥 ГЛОБАЛЬНАЯ МАСКИРОВКА на уровне КОНТЕКСТА
        // Выполняется до загрузки любой страницы и до инициализации JS браузера
        await context.addInitScript(() => {
            // Скрываем webdriver через прототип (самый надежный способ)
            Object.defineProperty(Navigator.prototype, 'webdriver', {
                get: () => undefined,
                configurable: true,
                enumerable: false
            });

            // Дублируем для текущего экземпляра navigator
            Object.defineProperty(navigator, 'webdriver', {
                get: () => undefined,
                configurable: true
            });

            // Эмулируем плагины
            Object.defineProperty(Navigator.prototype, 'plugins', {
                get: () => [1, 2, 3, 4, 5],
                configurable: true
            });

            // Эмулируем языки
            Object.defineProperty(Navigator.prototype, 'languages', {
                get: () => ['en-US', 'en'],
                configurable: true
            });

            // Эмулируем window.chrome (критично для Chrome-детекции)
            window.chrome = {
                runtime: {},
                loadTimes: () => ({ connectionType: '4g' }),
                csi: () => ({ startE: Date.now() }),
            };

            // Исправляем разрешения окна
            Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: 1920 });
            Object.defineProperty(window, 'innerHeight', { writable: true, configurable: true, value: 1080 });
        });

        const page = await context.newPage();

        console.log(`Navigating to: ${url}`);

        // 4. Переход на страницу
        await page.goto(url, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        // 5. Ожидание целевого элемента
        if (waitSelector) {
            try {
                await page.waitForSelector(waitSelector, { timeout: 30000 });
            } catch (e) {
                console.warn(`Selector "${waitSelector}" not found, continuing anyway...`);
            }
        }

        // 6. Небольшая задержка для выполнения динамических скриптов защиты
        await page.waitForTimeout(2000);

        const html = await page.content();

        // 7. Проверка на блокировку (WAF, Captcha)
        if (html.length < 5000 || html.includes('awsWaf') || html.includes('captcha') || html.includes('Access Denied')) {
            throw new Error(`Blocked by anti-bot protection. Response length: ${html.length} bytes`);
        }

        res.send(html);

    } catch (error) {
        console.error("Parser error:", error.message);
        res.status(500).json({ error: error.message });
    } finally {
        if (browser) {
            await browser.close();
        }
    }
});

app.listen(PORT, '0.0.0.0', () => {
    console.log(`Playwright parser running on port ${PORT}`);
});
