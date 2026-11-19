import { expect, test, type Page } from '@playwright/test';
import { createGroup, createGroupWithItem, createItem, grabHandle, groupOrder, item, openApp, track, unique } from './support/app';

const toast = (page: Page) => page.locator('.toast');
const undoButton = (page: Page) => toast(page).getByRole('button', { name: 'Undo' });

async function secondTab(page: Page): Promise<Page> {
    const second = await (await page.context().browser()!.newContext()).newPage();
    await openApp(second);

    return second;
}

test.describe('undo', () => {
    test('a resize is undone from the toast, and another tab sees it', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const second = await secondTab(page);

        await grabHandle(page, item(page, title), 'end', 40);
        await page.mouse.up();
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-08`);
        await expect(item(second, title)).toHaveAttribute('data-end-date', `${year}-08`);
        await expect(toast(page).locator('.toast-message')).toHaveText(`Changed “${title}”: Jan ${year} → Aug ${year}`);

        await undoButton(page).click();
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-06`);
        await expect(item(second, title)).toHaveAttribute('data-end-date', `${year}-06`);
        await expect(toast(page)).toContainText('Undone');

        await second.context().close();
    });

    test('an item edit, including its group, is undone with Ctrl+Z', async ({ page }) => {
        await openApp(page);
        const { group, title, year } = await createGroupWithItem(page);
        const target = unique('Target');
        await createGroup(page, target);
        const second = await secondTab(page);
        const renamed = unique('Renamed');

        await item(page, title).click();
        const dialog = page.getByRole('dialog', { name: 'Edit item' });
        await dialog.getByLabel('Title').fill(renamed);
        await dialog.getByLabel('Group', { exact: true }).selectOption({ label: `🧪 ${target}` });
        await dialog.getByLabel('End').fill(`${year}-09`);
        await dialog.getByRole('button', { name: 'Purple' }).click();
        await dialog.getByLabel('Description').fill('Edited by mistake');
        await dialog.getByRole('button', { name: 'Save' }).click();
        await expect(dialog).toHaveCount(0);
        await expect(track(page, target).locator('.timeline-item', { hasText: renamed })).toHaveCount(1);
        await expect(toast(page).locator('.toast-message')).toHaveText(`Changed “${renamed}”`);

        await page.keyboard.press('Control+z');
        await expect(track(page, group).locator('.timeline-item', { hasText: title })).toHaveCount(1);
        await expect(item(page, renamed)).toHaveCount(0);
        await expect(item(second, title)).toHaveAttribute('data-end-date', `${year}-06`);
        await expect(track(second, group).locator('.timeline-item', { hasText: title })).toHaveCount(1);

        await item(page, title).click();
        const reopened = page.getByRole('dialog', { name: 'Edit item' });
        await expect(reopened.getByLabel('Description')).toHaveValue('');
        await expect(reopened.getByLabel('Same as the group')).toBeChecked();
        await expect(reopened.getByLabel('End')).toHaveValue(`${year}-06`);

        await second.context().close();
    });

    test('a group edit is undone from the toast', async ({ page }) => {
        await openApp(page);
        const group = unique('Group');
        await createGroup(page, group, '#27ae60');
        const second = await secondTab(page);
        const renamed = unique('Renamed group');

        await track(page, group).locator('.track-info').click();
        const dialog = page.getByRole('dialog', { name: 'Edit group' });
        await dialog.getByLabel('Name').fill(renamed);
        await dialog.getByLabel('Icon').fill('🚗');
        await dialog.getByRole('button', { name: 'Save' }).click();
        await expect(track(second, renamed)).toHaveCount(1);
        await expect(toast(page).locator('.toast-message')).toHaveText(`Changed group “${renamed}”`);

        await undoButton(page).click();
        await expect(track(page, group)).toHaveCount(1);
        await expect(track(page, group).locator('.track-icon')).toHaveText('🧪');
        await expect(track(second, group)).toHaveCount(1);
        await expect(track(second, renamed)).toHaveCount(0);
        expect(await track(page, group).evaluate(el => el.style.getPropertyValue('--group-color'))).toBe('#27ae60');

        await second.context().close();
    });

    test('a reorder is undone with Ctrl+Z and from the toast', async ({ page }) => {
        await openApp(page);
        const [a, b] = [unique('A'), unique('B')];
        await createGroup(page, a);
        await createGroup(page, b);
        const second = await secondTab(page);
        const tail = async (p: Page): Promise<string[]> => (await groupOrder(p)).slice(-2);

        await track(page, b).locator('.track-info').focus();
        await page.keyboard.press('Alt+ArrowUp');
        await expect.poll(() => tail(page)).toEqual([b, a]);
        await expect(toast(page)).toContainText(`Moved “${b}” to position`);
        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await page.keyboard.press('Control+z');
        await expect.poll(() => tail(page)).toEqual([a, b]);
        await expect.poll(() => tail(second)).toEqual([a, b]);

        await track(page, b).locator('.drag-handle').dragTo(track(page, a).locator('.track-label'));
        await expect.poll(() => tail(page)).toEqual([b, a]);
        await undoButton(page).click();
        await expect.poll(() => tail(page)).toEqual([a, b]);
        await expect.poll(() => tail(second)).toEqual([a, b]);

        await second.context().close();
    });

    test('creating an item or a group is undone by deleting it', async ({ page }) => {
        await openApp(page);
        const group = unique('Group');
        await createGroup(page, group);
        await expect(toast(page).locator('.toast-message')).toHaveText(`Added group “${group}”`);
        const title = unique('Item');
        const year = new Date().getFullYear() - 1;
        await createItem(page, { title, group, start: `${year}-01`, end: `${year}-06` });
        const second = await secondTab(page);
        await expect(item(second, title)).toHaveCount(1);

        await undoButton(page).click();
        await expect(item(page, title)).toHaveCount(0);
        await expect(item(second, title)).toHaveCount(0);

        await page.keyboard.press('Control+z');
        await expect(track(page, group)).toHaveCount(0);
        await expect(track(second, group)).toHaveCount(0);

        await second.context().close();
    });

    test('undoes several steps back, newest first', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);

        await bar.focus();
        await page.keyboard.press('Shift+ArrowRight');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-07`);
        await page.keyboard.press('Shift+ArrowRight');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-08`);
        await page.keyboard.press('Alt+ArrowLeft');
        await expect(bar).toHaveAttribute('data-start-date', `${year - 1}-12`);

        await page.keyboard.press('Control+z');
        await expect(bar).toHaveAttribute('data-start-date', `${year}-01`);
        await expect(bar).toHaveAttribute('data-end-date', `${year}-08`);
        await page.keyboard.press('Control+z');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-07`);
        await page.keyboard.press('Control+z');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-06`);

        await page.reload();
        await openApp(page);
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-06`);
        await expect(item(page, title)).toHaveAttribute('data-start-date', `${year}-01`);
    });

    test('an undo that fails says so and leaves the history', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);
        await bar.focus();
        await page.keyboard.press('Shift+ArrowRight');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-07`);

        // Another window deletes the item
        const id = await bar.getAttribute('data-item-id');
        expect((await page.request.delete(`/cmd/items/${id}`)).status()).toBe(204);
        await expect(item(page, title)).toHaveCount(0);

        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await page.keyboard.press('Control+z');
        await expect(page.locator('.toast-error')).toContainText(`Could not undo: Changed “${title}”`);
        await expect(page.locator('.toast-error')).toContainText('deleted in the meantime');

        // The failed change is gone: the next undo is the one before it, the item's creation
        await page.keyboard.press('Control+z');
        await expect(page.locator('.toast-error')).toContainText(`Could not undo: Added “${title}”`);
    });

    test('Ctrl+Z leaves fields and open dialogs alone, and works while locked', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const bar = item(page, title);
        await bar.focus();
        await page.keyboard.press('Shift+ArrowRight');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-07`);

        await page.locator('#search').fill('abc');
        await page.locator('#search').press('Control+z');
        await page.locator('#search').press('Escape');
        await page.getByRole('button', { name: 'New item' }).click();
        await page.keyboard.press('Control+z');
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
        await expect(bar).toHaveAttribute('data-end-date', `${year}-07`);

        // Locking right after a slip still lets the user undo it
        await page.getByRole('button', { name: 'Lock', exact: true }).click();
        await page.keyboard.press('Control+z');
        await expect(bar).toHaveAttribute('data-end-date', `${year}-06`);
    });

    test('a reload clears the history', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        await item(page, title).focus();
        await page.keyboard.press('Shift+ArrowRight');
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-07`);

        await page.reload();
        await openApp(page);
        await page.keyboard.press('Control+z');
        await expect(page.locator('.toast-message')).toHaveText('Nothing to undo');
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-07`);
    });
});
