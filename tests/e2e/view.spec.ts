import { expect, test } from '@playwright/test';
import { openApp, scrollLeft, swipe, wrapper, zoomPercent } from './support/app';

test.describe('opening view and grid', () => {
    test('opens on today with a labelled marker', async ({ page }) => {
        await openApp(page);

        expect(await zoomPercent(page)).toBe(100);
        expect(await scrollLeft(page)).toBeGreaterThan(0);
        await expect(page.locator('.now-flag')).toBeInViewport();
        await expect(page.locator('.now-flag')).toHaveText('Today');
        await expect(page.locator('.now-line')).toBeInViewport();
        await expect(page.locator('.month.current')).toBeInViewport();

        // The flag is drawn inside the sticky ruler, not under it
        const flag = (await page.locator('.now-flag').boundingBox())!;
        const top = await page.evaluate(
            ([x, y]) => document.elementFromPoint(x, y)?.closest('.ruler') !== null,
            [flag.x + flag.width / 2, flag.y + flag.height / 2],
        );
        expect(top).toBe(true);
    });

    test('renders a year and month grid that follows the zoom', async ({ page }) => {
        await openApp(page);

        const years = page.locator('.year');
        expect(await years.count()).toBeGreaterThan(10);
        await expect(years.first().locator('.month')).toHaveCount(12);
        await expect(page.locator('.year-current .year-label')).toHaveText(String(new Date().getFullYear()));

        const yearWidth = async (): Promise<number> => (await years.nth(3).boundingBox())!.width;
        const fontSize = (pseudo: string): Promise<number> => page.locator('.month.current').evaluate(
            (el, p) => parseFloat(getComputedStyle(el, p).fontSize), pseudo,
        );

        // 100%: a year is 12 months of 20px, months show one letter
        expect(await yearWidth()).toBeCloseTo(240, 0);
        expect(await fontSize('::before')).toBeGreaterThan(0);
        expect(await fontSize('::after')).toBe(0);

        // Zoomed in: three-letter month names
        await page.getByRole('button', { name: 'Zoom in' }).click();
        await page.getByRole('button', { name: 'Zoom in' }).click();
        await expect.poll(yearWidth).toBeCloseTo(540, 0);
        expect(await fontSize('::before')).toBe(0);
        expect(await fontSize('::after')).toBeGreaterThan(0);

        // Fitted: no month letters, and only every fifth year keeps its label
        await page.getByRole('button', { name: 'Fit timeline' }).click();
        await expect.poll(yearWidth).toBeLessThan(60);
        expect(await fontSize('::before')).toBe(0);
        const opacity = (selector: string): Promise<number> =>
            page.locator(selector).first().evaluate(el => parseFloat(getComputedStyle(el).opacity));
        expect(await opacity('.year-major .year-label')).toBe(1);
        expect(await opacity('.year:not(.year-major) .year-label')).toBe(0);
    });

    test('keeps the ruler and the group labels in place while scrolling', async ({ page }) => {
        await openApp(page);

        const label = page.locator('.track-label').first();
        const ruler = page.locator('.ruler');
        const before = { label: (await label.boundingBox())!.x, ruler: (await ruler.boundingBox())!.y };

        await wrapper(page).evaluate(el => el.scrollBy(-600, 120));

        await expect.poll(async () => (await label.boundingBox())!.x).toBe(before.label);
        expect((await ruler.boundingBox())!.y).toBe(before.ruler);
        // The year that runs off the left edge still shows its label next to the label column
        const sticky = page.locator('.year-label').filter({ hasText: /^\d{4}$/ });
        const corner = (await page.locator('.ruler-corner').boundingBox())!;
        const xs = await sticky.evaluateAll(els => els.map(el => el.getBoundingClientRect().x));
        expect(xs.some(x => Math.abs(x - (corner.x + corner.width)) < 2)).toBe(true);
    });

    test('item titles stay readable and inside the visible part of their bar', async ({ page }) => {
        await openApp(page);

        // An ongoing seed item that started long before the visible range
        const bar = page.locator('.timeline-item.ongoing', { hasText: 'Guitar' });
        await bar.scrollIntoViewIfNeeded();
        const corner = (await page.locator('.ruler-corner').boundingBox())!;
        const title = (await bar.locator('.item-title').boundingBox())!;
        expect((await bar.boundingBox())!.x).toBeLessThan(0);
        expect(title.x).toBeGreaterThanOrEqual(corner.x + corner.width);

        // Text and background of a bar differ clearly in lightness in both themes
        for (const scheme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme: scheme });
            await page.waitForTimeout(300);
            const contrast = await bar.evaluate((el) => {
                const lum = (color: string): number => {
                    const ctx = document.createElement('canvas').getContext('2d')!;
                    ctx.fillStyle = color;
                    ctx.fillRect(0, 0, 1, 1);
                    const [r, g, b] = [...ctx.getImageData(0, 0, 1, 1).data].map((v) => {
                        const c = v / 255;

                        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
                    });

                    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
                };
                const style = getComputedStyle(el);
                const [a, b] = [lum(style.color), lum(style.backgroundColor)].sort((x, y) => y - x);

                return (a + 0.05) / (b + 0.05);
            });
            expect(contrast, `${scheme} theme`).toBeGreaterThanOrEqual(4.5);
        }
    });

    test('follows the light and the dark color scheme', async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'light' });
        await openApp(page);
        const background = (): Promise<string> => page.evaluate(() => getComputedStyle(document.body).backgroundColor);
        const light = await background();

        await page.emulateMedia({ colorScheme: 'dark' });
        const dark = await background();

        expect(light).not.toBe(dark);
        const brightness = (rgb: string): number => (rgb.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number).reduce((a, b) => a + b, 0);
        expect(brightness(light)).toBeGreaterThan(600);
        expect(brightness(dark)).toBeLessThan(120);
    });

    test('shows the live connection', async ({ page }) => {
        await openApp(page);

        await expect(page.locator('.connection')).toContainText('Live');
    });

    test('loads Datastar once', async ({ page }) => {
        const requests: string[] = [];
        page.on('request', request => requests.push(new URL(request.url()).pathname));
        await openApp(page);

        expect(requests.filter(path => path === '/js/datastar.js')).toHaveLength(1);
    });
});

test.describe('phone layout', () => {
    test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

    test('collapses the label column and names the group inside its track', async ({ page }) => {
        await openApp(page);

        const label = (await page.locator('.track-label').first().boundingBox())!;
        expect(label.width).toBeLessThan(70);
        await expect(page.locator('.track-name').first()).toBeHidden();
        await expect(page.locator('.track-caption').first()).toBeVisible();
        await expect(page.locator('.now-line')).toBeInViewport();
        // No horizontal overflow of the page itself
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    });

    test('scrolls natively in both directions with a finger', async ({ page }) => {
        await openApp(page);
        const before = await wrapper(page).evaluate(el => ({ x: el.scrollLeft, y: el.scrollTop }));

        await swipe(page, 250, 400, 150, 0);
        await swipe(page, 250, 500, 0, -150);

        await expect.poll(() => wrapper(page).evaluate(el => el.scrollLeft)).toBeLessThan(before.x - 100);
        expect(await wrapper(page).evaluate(el => el.scrollTop)).toBeGreaterThan(before.y + 100);
    });

    test('opens dialogs as a sheet at the bottom edge', async ({ page }) => {
        await openApp(page);
        await page.getByRole('button', { name: 'New item' }).click();

        const dialog = page.getByRole('dialog', { name: 'New item' });
        await expect(dialog).toBeVisible();
        await expect.poll(async () => {
            const box = (await dialog.boundingBox())!;

            return Math.round(box.y + box.height);
        }).toBe(844);
        expect((await dialog.boundingBox())!.width).toBe(390);
    });

    test('reorders groups without drag and drop', async ({ page }) => {
        await openApp(page);
        await expect(page.locator('.drag-handle').first()).toBeHidden();

        const order = await page.locator('.track').evaluateAll(els => els.map(el => (el as HTMLElement).dataset.groupId));
        await page.locator('.track-info').first().tap();
        const dialog = page.getByRole('dialog', { name: 'Edit group' });
        await dialog.getByRole('button', { name: 'Move down' }).tap();
        await expect.poll(() => page.locator('.track').evaluateAll(els => els.map(el => (el as HTMLElement).dataset.groupId)))
            .toEqual([order[1], order[0], ...order.slice(2)]);

        await dialog.getByRole('button', { name: 'Move up' }).tap();
        await expect.poll(() => page.locator('.track').evaluateAll(els => els.map(el => (el as HTMLElement).dataset.groupId)))
            .toEqual(order);
    });
});
