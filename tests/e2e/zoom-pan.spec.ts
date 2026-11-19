import { expect, test, type Page } from '@playwright/test';
import { openApp, scrollLeft, wrapper, zoomPercent } from './support/app';

/** Screen x of the now line, a fixed point of the timeline to measure zoom anchoring with */
async function nowX(page: Page): Promise<number> {
    return (await page.locator('.now-line').boundingBox())!.x;
}

test.describe('zoom', () => {
    test('Ctrl+wheel zooms around the pointer', async ({ page }) => {
        await openApp(page);
        const x = await nowX(page);
        await page.mouse.move(x + 1, 400);

        await page.keyboard.down('Control');
        await page.mouse.wheel(0, -100);
        await page.keyboard.up('Control');

        await expect.poll(() => zoomPercent(page)).toBeGreaterThan(110);
        // The point under the pointer stays where it was
        await expect.poll(async () => Math.abs((await nowX(page)) - x)).toBeLessThan(3);

        await page.keyboard.down('Control');
        await page.mouse.wheel(0, 100);
        await page.mouse.wheel(0, 100);
        await page.keyboard.up('Control');
        await expect.poll(() => zoomPercent(page)).toBeLessThan(100);
        await expect.poll(async () => Math.abs((await nowX(page)) - x)).toBeLessThan(3);
    });

    test('Cmd+wheel zooms too, a plain wheel does not', async ({ page }) => {
        await openApp(page);
        await page.mouse.move(700, 400);

        await page.mouse.wheel(0, -100);
        await page.waitForTimeout(150);
        expect(await zoomPercent(page)).toBe(100);

        await page.keyboard.down('Meta');
        await page.mouse.wheel(0, -100);
        await page.keyboard.up('Meta');
        await expect.poll(() => zoomPercent(page)).toBeGreaterThan(110);
    });

    test('the dock zooms in and out around the middle and resets', async ({ page }) => {
        await openApp(page);
        const home = await scrollLeft(page);

        await page.getByRole('button', { name: 'Zoom in' }).click();
        await expect.poll(() => zoomPercent(page)).toBe(150);
        await page.getByRole('button', { name: 'Zoom out' }).click();
        await page.getByRole('button', { name: 'Zoom out' }).click();
        await expect.poll(() => zoomPercent(page)).toBe(67);

        await page.getByRole('button', { name: 'Reset view' }).click();
        await expect.poll(() => zoomPercent(page)).toBe(100);
        await expect.poll(() => scrollLeft(page)).toBe(home);
    });

    test('Fit shows every year, Today comes back to the present', async ({ page }) => {
        await openApp(page);

        await page.getByRole('button', { name: 'Fit timeline' }).click();
        await expect.poll(() => zoomPercent(page)).toBeLessThan(30);
        await expect(page.locator('.year').first()).toBeInViewport();
        await expect(page.locator('.year').last()).toBeInViewport();

        await page.getByRole('button', { name: 'Reset view' }).click();
        await expect.poll(() => zoomPercent(page)).toBe(100);
        await page.waitForTimeout(300);
        await wrapper(page).evaluate(el => el.scrollTo({ left: 0 }));
        await expect(page.locator('.now-line')).not.toBeInViewport();
        await page.getByRole('button', { name: 'Today' }).click();
        await expect(page.locator('.now-line')).toBeInViewport();
    });

    test('the keyboard zooms with Ctrl and + or -, and resets with Ctrl+0', async ({ page }) => {
        await openApp(page);
        const home = await scrollLeft(page);

        await page.keyboard.press('Control+=');
        await expect.poll(() => zoomPercent(page)).toBe(120);
        await page.keyboard.press('Control++');
        await expect.poll(() => zoomPercent(page)).toBe(144);
        await page.keyboard.press('Control+-');
        await page.keyboard.press('Control+-');
        await page.keyboard.press('Control+-');
        await expect.poll(() => zoomPercent(page)).toBe(83);
        await page.keyboard.press('Meta+=');
        await expect.poll(() => zoomPercent(page)).toBe(100);

        await page.keyboard.press('Control+-');
        await page.keyboard.press('Control+0');
        await expect.poll(() => zoomPercent(page)).toBe(100);
        await expect.poll(() => scrollLeft(page)).toBe(home);
    });
});

test.describe('panning', () => {
    test('dragging empty space slides the timeline', async ({ page }) => {
        await openApp(page);
        const before = await scrollLeft(page);
        // The ruler has no items that would take the drag
        const ruler = (await page.locator('.ruler').boundingBox())!;
        const y = ruler.y + ruler.height / 2;

        await page.mouse.move(900, y);
        await page.mouse.down();
        await expect(wrapper(page)).toHaveClass(/is-panning/);
        await page.mouse.move(700, y, { steps: 5 });
        await page.mouse.up();

        await expect.poll(() => scrollLeft(page)).toBe(before + 200);
        await expect(wrapper(page)).not.toHaveClass(/is-panning/);
    });

    test('a horizontal wheel or trackpad scroll pans, and later actions start from there', async ({ page }) => {
        await openApp(page);
        const before = await scrollLeft(page);
        await page.mouse.move(700, 400);

        await page.mouse.wheel(-300, 0);
        await expect.poll(() => scrollLeft(page)).toBe(before - 300);

        // The scroll position reached the pan state: an arrow key continues from it
        await page.waitForTimeout(200);
        await page.keyboard.press('ArrowRight');
        await expect.poll(() => scrollLeft(page)).toBe(before - 200);
    });

    test('arrow keys pan, but not while typing or in a dialog', async ({ page }) => {
        await openApp(page);
        const before = await scrollLeft(page);

        await page.keyboard.press('ArrowLeft');
        await expect.poll(() => scrollLeft(page)).toBe(before - 100);
        await page.keyboard.press('ArrowRight');
        await expect.poll(() => scrollLeft(page)).toBe(before);

        await page.getByRole('button', { name: 'New item' }).click();
        const title = page.getByRole('dialog', { name: 'New item' }).getByLabel('Title');
        await title.fill('abc');
        await title.press('ArrowLeft');
        await title.press('ArrowLeft');
        await page.waitForTimeout(150);
        expect(await scrollLeft(page)).toBe(before);
    });
});
