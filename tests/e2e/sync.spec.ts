import { expect, test } from '@playwright/test';
import { createGroup, createItem, deleteGroup, grabHandle, item, openApp, scrollLeft, track, unique, zoomPercent } from './support/app';

test.describe('live sync between two tabs', () => {
    test('changes made in one tab show up in the other without a reload', async ({ browser }) => {
        const first = await (await browser.newContext()).newPage();
        const second = await (await browser.newContext()).newPage();
        await openApp(first);
        await openApp(second);

        // The second tab has its own view, which updates must not reset
        await second.getByRole('button', { name: 'Zoom in' }).click();
        await expect.poll(() => zoomPercent(second)).toBe(150);
        await second.waitForTimeout(300);
        const view = await scrollLeft(second);

        const group = unique('Shared');
        const title = unique('Shared item');
        const year = new Date().getFullYear() - 1;

        await createGroup(first, group);
        await expect(track(second, group)).toHaveCount(1);
        await expect(second.locator('#add-item-group option', { hasText: group })).toHaveCount(1);

        await createItem(first, { title, group, start: `${year}-01`, end: `${year}-06` });
        await expect(item(second, title)).toHaveAttribute('data-end-date', `${year}-06`);

        // Resize in the first tab
        await item(first, title).scrollIntoViewIfNeeded();
        await grabHandle(first, item(first, title), 'end', 40);
        await first.mouse.up();
        await expect(item(second, title)).toHaveAttribute('data-end-date', `${year}-08`);

        // Edit in the second tab, seen in the first
        const renamed = unique('Renamed');
        await item(second, title).scrollIntoViewIfNeeded();
        await item(second, title).click();
        const dialog = second.getByRole('dialog', { name: 'Edit item' });
        await dialog.getByLabel('Title').fill(renamed);
        await dialog.getByRole('button', { name: 'Save' }).click();
        await expect(item(first, renamed)).toHaveCount(1);
        await expect(item(first, title)).toHaveCount(0);

        // Reorder in the first tab
        const other = unique('Other');
        await createGroup(first, other);
        await track(first, other).locator('.track-info').click();
        await first.getByRole('dialog', { name: 'Edit group' }).getByRole('button', { name: 'Move up' }).click();
        await expect.poll(async () => (await second.locator('.track .track-name').allTextContents()).slice(-2)).toEqual([other, group]);
        await first.getByRole('dialog', { name: 'Edit group' }).getByRole('button', { name: 'Cancel' }).click();

        // Delete in the first tab
        await deleteGroup(first, group);
        await expect(track(second, group)).toHaveCount(0);
        await expect(item(second, renamed)).toHaveCount(0);

        expect(await zoomPercent(second)).toBe(150);
        expect(Math.abs((await scrollLeft(second)) - view)).toBeLessThan(2);

        await first.context().close();
        await second.context().close();
    });

    test('warns when the group chosen in an open form is deleted in another tab', async ({ browser }) => {
        const first = await (await browser.newContext()).newPage();
        const second = await (await browser.newContext()).newPage();
        await openApp(first);
        await openApp(second);
        const doomed = unique('Doomed');
        const safe = unique('Safe');
        const title = unique('Kept');
        await createGroup(first, doomed);
        await createGroup(first, safe);

        await first.getByRole('button', { name: 'New item' }).click();
        const dialog = first.getByRole('dialog', { name: 'New item' });
        await dialog.getByLabel('Title').fill(title);
        await dialog.getByLabel('Group', { exact: true }).selectOption({ label: `🧪 ${doomed}` });
        await dialog.getByLabel('Start').fill('2020-01');

        await deleteGroup(second, doomed);

        await expect(dialog.getByRole('alert')).toContainText('deleted in another window');
        await expect(dialog).toBeVisible();
        await expect(dialog.getByLabel('Title')).toHaveValue(title);
        await expect(dialog.getByLabel('Group', { exact: true })).toHaveValue('');

        // Nothing is filed in a group the user did not choose
        await dialog.getByRole('button', { name: 'Add item' }).click();
        await expect(dialog).toBeVisible();
        await expect(item(first, title)).toHaveCount(0);

        await dialog.getByLabel('Group', { exact: true }).selectOption({ label: `🧪 ${safe}` });
        await dialog.getByRole('button', { name: 'Add item' }).click();
        await expect(dialog).toBeHidden();
        await expect(track(first, safe).locator('.timeline-item')).toHaveCount(1);
        await expect(track(second, safe).locator('.timeline-item')).toHaveCount(1);

        await first.context().close();
        await second.context().close();
    });

    test('an unrelated change keeps the chosen group of an open form', async ({ browser }) => {
        const first = await (await browser.newContext()).newPage();
        const second = await (await browser.newContext()).newPage();
        await openApp(first);
        await openApp(second);
        const chosen = unique('Chosen');
        await createGroup(first, chosen);

        await first.getByRole('button', { name: 'New item' }).click();
        const select = first.getByRole('dialog', { name: 'New item' }).getByLabel('Group', { exact: true });
        await select.selectOption({ label: `🧪 ${chosen}` });
        const value = await select.inputValue();

        const other = unique('Other');
        await createGroup(second, other);
        await expect(select.locator('option', { hasText: other })).toHaveCount(1);
        await deleteGroup(second, other);
        await expect(select.locator('option', { hasText: other })).toHaveCount(0);

        await expect(select).toHaveValue(value);

        await first.context().close();
        await second.context().close();
    });
});
