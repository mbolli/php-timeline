import { expect, test } from '@playwright/test';
import { createGroup, createItem, item, openApp, scrollLeft, track, unique, wrapper, zoomPercent } from './support/app';

test.describe('keyboard shortcuts', () => {
    test('N and G open the dialogs, Escape closes them and the focus returns', async ({ page }) => {
        await openApp(page);

        await page.keyboard.press('n');
        const newItem = page.getByRole('dialog', { name: 'New item' });
        await expect(newItem).toBeVisible();
        await expect(newItem.getByLabel('Title')).toBeFocused();
        // The key itself is not typed into the field
        await expect(newItem.getByLabel('Title')).toHaveValue('');
        await page.keyboard.press('Escape');
        await expect(newItem).toBeHidden();

        await page.keyboard.press('g');
        const newGroup = page.getByRole('dialog', { name: 'New group' });
        await expect(newGroup).toBeVisible();
        await expect(newGroup.getByLabel('Name')).toHaveValue('');

        // Typing the letters in a field does nothing else
        await newGroup.getByLabel('Name').pressSequentially('gtnf');
        await expect(newGroup.getByLabel('Name')).toHaveValue('gtnf');
        await expect(page.locator('dialog[open]')).toHaveCount(1);
        await page.keyboard.press('Escape');
        await expect(newGroup).toBeHidden();
    });

    test('T goes to today and F fits the timeline', async ({ page }) => {
        await openApp(page);
        const home = await scrollLeft(page);

        await page.keyboard.press('f');
        await expect.poll(() => zoomPercent(page)).toBeLessThan(30);
        await expect(page.locator('.year').first()).toBeInViewport();

        await page.keyboard.press('Control+0');
        await expect.poll(() => zoomPercent(page)).toBe(100);
        await page.waitForTimeout(300);
        await wrapper(page).evaluate(el => el.scrollTo({ left: 0 }));
        await page.waitForTimeout(200);
        await page.keyboard.press('t');
        await expect.poll(() => scrollLeft(page)).toBe(home);
    });

    test('one tab stop per group, arrows walk the items, Enter edits', async ({ page }) => {
        await openApp(page);
        const [group, below] = [unique('Nav'), unique('Below')];
        const [early, late] = [unique('Early'), unique('Late')];
        const year = new Date().getFullYear() - 1;
        await createGroup(page, group);
        await createGroup(page, below);
        await createItem(page, { title: late, group, start: `${year}-07`, end: `${year}-09` });
        await createItem(page, { title: early, group, start: `${year}-01`, end: `${year}-03` });

        expect(await page.locator('.timeline-item[tabindex="0"]').count()).toBe(0);
        const button = track(page, group).locator('.track-info');
        await button.focus();

        await page.keyboard.press('ArrowRight');
        await expect(item(page, early)).toBeFocused();
        await page.keyboard.press('ArrowRight');
        await expect(item(page, late)).toBeFocused();
        await page.keyboard.press('ArrowRight');
        await expect(item(page, late)).toBeFocused();
        await page.keyboard.press('ArrowLeft');
        await expect(item(page, early)).toBeFocused();
        await expect(item(page, early)).toBeInViewport();

        await page.keyboard.press('Enter');
        const dialog = page.getByRole('dialog', { name: 'Edit item' });
        await expect(dialog.getByLabel('Title')).toHaveValue(early);
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(item(page, early)).toBeFocused();

        await page.keyboard.press('ArrowLeft');
        await expect(button).toBeFocused();
        await page.keyboard.press('ArrowDown');
        await expect(track(page, below).locator('.track-info')).toBeFocused();
        await page.keyboard.press('ArrowUp');
        await expect(button).toBeFocused();

        await page.keyboard.press('Enter');
        await expect(page.getByRole('dialog', { name: 'Edit group' }).getByLabel('Name')).toHaveValue(group);
    });

    test('a focused item is not hidden under the label column', async ({ page }) => {
        await openApp(page);
        // A seed item far to the left of the opening view
        const bar = page.locator('.timeline-item', { hasText: 'Nokia 3310' });

        await bar.focus();

        const corner = (await page.locator('.ruler-corner').boundingBox())!;
        await expect.poll(async () => (await bar.boundingBox())!.x).toBeGreaterThanOrEqual(corner.x + corner.width);
        await expect(bar).toBeInViewport();
    });
});

test.describe('search', () => {
    test('finds an item by title and jumps to it', async ({ page }) => {
        await openApp(page);
        const bar = page.locator('.timeline-item', { hasText: 'Xbox 360' });
        await expect(bar).not.toBeInViewport();

        await page.keyboard.press('/');
        const search = page.getByRole('searchbox');
        await expect(search).toBeFocused();
        await search.pressSequentially('xbox');
        const results = page.locator('.search-result');
        await expect(results).toHaveCount(1);
        await expect(results.first()).toContainText('Xbox 360');
        await expect(results.first()).toContainText('Gaming Consoles');

        await search.press('Enter');
        await expect(bar).toBeInViewport();
        await expect(bar).toBeFocused();
        await expect(page.locator('.search-results')).toBeHidden();
        await expect(search).toHaveValue('');
    });

    test('jumps to a year', async ({ page }) => {
        await openApp(page);
        const year = page.locator('.year[data-year="2001"]');
        await expect(year).not.toBeInViewport();

        await page.getByRole('searchbox').fill('2001');
        await page.getByRole('button', { name: 'Go to 2001' }).click();

        await expect(year).toBeInViewport();
        const corner = (await page.locator('.ruler-corner').boundingBox())!;
        const x = (await year.boundingBox())!.x;
        expect(x).toBeGreaterThanOrEqual(corner.x + corner.width);
        expect(x).toBeLessThan(corner.x + corner.width + 200);
    });

    test('says when nothing matches, and Escape closes the list', async ({ page }) => {
        await openApp(page);
        const search = page.getByRole('searchbox');

        await search.fill('no such thing');
        await expect(page.locator('.search-empty')).toHaveText('Nothing found');

        await search.fill('iphone');
        await expect(page.locator('.search-result').first()).toBeVisible();
        await search.press('ArrowDown');
        await expect(page.locator('.search-result').first()).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(page.locator('.search-results')).toBeHidden();
    });
});

test.describe('About dialog', () => {
    test('opens, explains the app and its shortcuts, and closes', async ({ page }) => {
        await openApp(page);
        const dialog = page.getByRole('dialog', { name: 'About Life Timeline' });

        await page.getByRole('button', { name: 'About' }).click();
        await expect(dialog).toBeVisible();
        await expect(dialog).toContainText('PHP on Swoole');
        await expect(dialog).toContainText('Server-Sent Events');
        await expect(dialog).toContainText('Zoom at the pointer');
        await expect(dialog.getByRole('link', { name: 'Source code on GitHub' })).toHaveAttribute('href', 'https://github.com/mbolli/php-timeline');
        await expect(dialog.getByRole('link', { name: 'zwei und eins gmbh' })).toHaveAttribute('href', 'https://zweiundeins.gmbh');
        await expect(dialog.getByRole('button', { name: 'Close' })).toBeFocused();

        await dialog.getByRole('button', { name: 'Close' }).click();
        await expect(dialog).toBeHidden();

        await page.getByRole('button', { name: 'About' }).click();
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(page.getByRole('button', { name: 'About' })).toBeFocused();

        await page.getByRole('button', { name: 'About' }).click();
        await expect(dialog).toBeVisible();
        await page.mouse.click(5, 5);
        await expect(dialog).toBeHidden();
    });
});
