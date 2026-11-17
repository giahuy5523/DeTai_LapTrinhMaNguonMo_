async page => {
    const base = 'http://127.0.0.1:8023';
    const errors = [];
    const external = [];
    const onPageError = error => errors.push(error.message);
    page.on('pageerror', onPageError);
    await page.context().clearCookies();
    await page.route('**/*', route => {
        const url = new URL(route.request().url());
        if (url.hostname !== '127.0.0.1') {
            external.push(url.hostname);
            return route.abort();
        }
        return route.continue();
    });
    const measurements = [];
    try {
        for (const path of ['/', '/san-pham', '/login', '/register']) {
            for (const width of [320, 390, 768, 1440]) {
                await page.setViewportSize({ width, height: width === 320 ? 568 : 900 });
                const response = await page.goto(base + path);
                if (response.status() !== 200) throw Error(path + ': HTTP ' + response.status());
                if (path === '/' || path === '/san-pham') {
                    const loaded = await page.locator('link[href*="bootstrap.min.css"]').evaluate(el => !!el.sheet);
                    if (!loaded) throw Error('Local Bootstrap CSS did not load');
                }
                const size = await page.evaluate(() => ({ actual: document.documentElement.scrollWidth, expected: innerWidth }));
                if (size.actual > size.expected) throw Error('Overflow: ' + path + ' at ' + width);
                measurements.push({ path, width, ...size });
                if ((width === 390 || width === 1440) && (path === '/' || path === '/san-pham')) {
                    await page.screenshot({ path: 'docs/gd3-' + (path === '/' ? 'home' : 'store') + '-' + width + '.png', fullPage: true });
                }
            }
        }
        await page.setViewportSize({ width: 390, height: 900 });
        await page.goto(base);
        await page.getByRole('button', { name: 'Mở menu' }).click();
        await page.locator('#shopNav.show').waitFor();
        if (!await page.getByRole('link', { name: 'Đăng nhập', exact: true }).isVisible()) throw Error('Mobile menu failed');
        if (errors.length || external.length) throw Error(JSON.stringify({ errors, external }));
        return { result: 'PASS', offlineAssets: true, mobileMenu: true, errors, measurements };
    } finally {
        await page.unroute('**/*');
        page.off('pageerror', onPageError);
    }
}
