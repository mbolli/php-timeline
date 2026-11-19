import { expect, test } from '@playwright/test';
import { createGroupWithItem, grabHandle, item, openApp, swipe, unlock, zoomPercent } from './support/app';

test.describe('resizing items', () => {
    test('dragging the end handle changes the end date, with a tooltip on the way', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);
        const width = (await bar.boundingBox())!.width;

        // Two months of 20px to the right
        await grabHandle(page, bar, 'end', 40);
        const tooltip = page.locator('.resize-tooltip');
        await expect(tooltip).toHaveText(`Jan ${year} → Aug ${year}`);
        await expect(bar).toHaveClass(/resizing/);
        expect((await bar.boundingBox())!.width).toBeCloseTo(width + 40, 0);

        await page.mouse.up();
        await expect(tooltip).toHaveCount(0);
        await expect(bar).not.toHaveClass(/resizing/);
        await expect(bar).toHaveAttribute('data-end-date', `${year}-08`);
        // The click that ends the drag does not open the item
        await page.waitForTimeout(300);
        await expect(page.getByRole('dialog', { name: 'Edit item' })).toHaveCount(0);

        // Saved on the server
        await page.reload();
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-08`);
        await expect(item(page, title)).toHaveAttribute('data-start-date', `${year}-01`);
    });

    test('dragging the start handle changes the start date', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);
        const right = async (): Promise<number> => {
            const box = (await bar.boundingBox())!;

            return box.x + box.width;
        };
        const edge = await right();

        await grabHandle(page, bar, 'start', -60);
        await expect(page.locator('.resize-tooltip')).toHaveText(`Oct ${year - 1} → Jun ${year}`);
        await page.mouse.up();

        await expect(bar).toHaveAttribute('data-start-date', `${year - 1}-10`);
        await expect(bar).toHaveAttribute('data-end-date', `${year}-06`);
        expect(await right()).toBeCloseTo(edge, 0);
    });

    test('snaps to months at the current zoom', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        await page.getByRole('button', { name: 'Zoom in' }).click();
        await expect.poll(() => zoomPercent(page)).toBe(150);
        const bar = item(page, title);
        await bar.scrollIntoViewIfNeeded();

        // At 150% a month is 30px: 55px rounds to two months
        await grabHandle(page, bar, 'end', 55);
        await page.mouse.up();

        await expect(bar).toHaveAttribute('data-end-date', `${year}-08`);
    });

    test('cannot shrink an item below one month', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);

        await grabHandle(page, bar, 'end', -400);
        await expect(page.locator('.resize-tooltip')).toHaveText(`Jan ${year} → Jan ${year}`);
        await page.mouse.up();

        await expect(bar).toHaveAttribute('data-end-date', `${year}-01`);
    });

    test('Escape cancels a resize', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);
        const width = (await bar.boundingBox())!.width;
        let saved = false;
        page.on('request', request => { saved ||= request.url().includes('/resize'); });

        await grabHandle(page, bar, 'end', 80);
        await expect(page.locator('.resize-tooltip')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('.resize-tooltip')).toHaveCount(0);
        await page.mouse.up();

        await expect(bar).toHaveAttribute('data-end-date', `${year}-06`);
        expect((await bar.boundingBox())!.width).toBeCloseTo(width, 0);
        await page.waitForTimeout(200);
        expect(saved).toBe(false);
    });

    test('puts the bar back and says so when the server does not save', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);
        const width = (await bar.boundingBox())!.width;

        await page.route('**/cmd/items/*/resize', route => route.fulfill({ status: 500, contentType: 'application/json', body: '{"error":"Database is locked"}' }));
        await grabHandle(page, bar, 'end', 60);
        await page.mouse.up();

        await expect(page.locator('.toast-error')).toContainText('The new dates were not saved');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-06`);
        expect((await bar.boundingBox())!.width).toBeCloseTo(width, 0);

        // Offline behaves the same
        await page.unroute('**/cmd/items/*/resize');
        await page.route('**/cmd/items/*/resize', route => route.abort());
        await grabHandle(page, bar, 'start', -40);
        await page.mouse.up();
        await expect(page.locator('.toast-error')).toContainText('could not be reached');
        await expect(bar).toHaveAttribute('data-start-date', `${year}-01`);
        expect((await bar.boundingBox())!.width).toBeCloseTo(width, 0);
    });

    test('the keyboard moves the end with Shift and the start with Alt', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);

        await bar.focus();
        await page.keyboard.press('Shift+ArrowRight');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-07`);
        await expect(page.locator('.toast-message')).toHaveText(`Changed “${title}”: Jan ${year} → Jul ${year}`);
        await page.keyboard.press('Shift+ArrowLeft');
        await page.keyboard.press('Shift+ArrowLeft');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-05`);
        await page.keyboard.press('Alt+ArrowLeft');
        await expect(bar).toHaveAttribute('data-start-date', `${year - 1}-12`);
        await expect(bar).toBeFocused();

        await page.reload();
        await expect(item(page, title)).toHaveAttribute('data-start-date', `${year - 1}-12`);
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-05`);
    });
});

test.describe('resizing on a touch screen', () => {
    test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

    test('the handles are visible and follow a finger', async ({ page }) => {
        await openApp(page);
        await unlock(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);

        const handle = bar.locator('.resize-handle-end');
        expect(await handle.evaluate(el => parseFloat(getComputedStyle(el).opacity))).toBeGreaterThan(0.5);
        const box = (await handle.boundingBox())!;
        expect(box.width).toBeGreaterThanOrEqual(16);

        await swipe(page, box.x + box.width / 2, box.y + box.height / 2, 40, 0);

        await expect(bar).toHaveAttribute('data-end-date', `${year}-08`);
        await expect(bar).toHaveAttribute('data-start-date', `${year}-01`);
        await expect(page.getByRole('dialog', { name: 'Edit item' })).toHaveCount(0);
    });
});
