import { expect, test } from '@playwright/test';
import { createGroup, createGroupWithItem, deleteGroup, groupOrder, item, openApp, track, unique } from './support/app';

test.describe('groups', () => {
    test('creates a group at the end of the list', async ({ page }) => {
        await openApp(page);
        const before = await groupOrder(page);
        const name = unique('Created');

        await createGroup(page, name, '#1abc9c');

        expect(await groupOrder(page)).toEqual([...before, name]);
        const row = track(page, name);
        await expect(row.locator('.track-icon')).toHaveText('🧪');
        await expect(row.locator('.track-count')).toHaveText('0');
        await expect(row.locator('.track-empty')).toHaveText('No items yet');
        expect(await row.evaluate(el => el.style.getPropertyValue('--group-color'))).toBe('#1abc9c');
        await expect(page.locator('.ruler-corner-count')).toHaveText(String(before.length + 1));
    });

    test('edits a group without moving it', async ({ page }) => {
        await openApp(page);
        const name = unique('Group');
        const renamed = unique('Renamed');
        await createGroup(page, name);
        await createGroup(page, unique('After'));
        const order = await groupOrder(page);

        await track(page, name).locator('.track-info').click();
        const dialog = page.getByRole('dialog', { name: 'Edit group' });
        await expect(dialog.getByLabel('Name')).toHaveValue(name);
        await dialog.getByLabel('Name').fill(renamed);
        await dialog.getByLabel('Icon').fill('🚀');
        await dialog.getByRole('button', { name: 'Green' }).click();
        await dialog.getByRole('button', { name: 'Save' }).click();

        await expect(dialog).toHaveCount(0);
        const row = track(page, renamed);
        await expect(row.locator('.track-icon')).toHaveText('🚀');
        expect(await row.evaluate(el => el.style.getPropertyValue('--group-color'))).toBe('#27ae60');
        expect(await groupOrder(page)).toEqual(order.map(n => (n === name ? renamed : n)));
        await expect(row.locator('.track-info')).toBeFocused();
    });

    test('deletes a group together with its items after the confirmation', async ({ page }) => {
        await openApp(page);
        const { group, title } = await createGroupWithItem(page);

        await track(page, group).locator('.track-info').click();
        const dialog = page.getByRole('dialog', { name: 'Edit group' });
        await dialog.getByRole('button', { name: 'Delete' }).click();
        const confirm = page.getByRole('alertdialog');
        await expect(confirm).toContainText(`Delete the group “${group}” and its 1 item?`);

        await confirm.getByRole('button', { name: 'Cancel' }).click();
        await expect(track(page, group)).toHaveCount(1);

        await dialog.getByRole('button', { name: 'Delete' }).click();
        await confirm.getByRole('button', { name: 'Delete' }).click();
        await expect(track(page, group)).toHaveCount(0);
        await expect(item(page, title)).toHaveCount(0);
        await expect(dialog).toHaveCount(0);
    });

    test('the group list of an open New item dialog follows live changes and keeps the selection', async ({ page }) => {
        await openApp(page);
        const chosen = unique('Chosen');
        const later = unique('Later');
        await createGroup(page, chosen);

        await page.getByRole('button', { name: 'New item' }).click();
        const select = page.getByRole('dialog', { name: 'New item' }).getByLabel('Group', { exact: true });
        await select.selectOption({ label: `🧪 ${chosen}` });
        const value = await select.inputValue();

        // Another client adds a group
        const response = await page.request.post('/cmd/groups', { form: { name: later, icon: '🧪', color: '#3498db' } });
        expect(response.status()).toBe(201);

        await expect(select.locator('option', { hasText: later })).toHaveCount(1);
        await expect(select).toHaveValue(value);
    });

    test('reorders groups by dragging the handle', async ({ page }) => {
        await openApp(page);
        const [a, b, c] = [unique('A'), unique('B'), unique('C')];
        for (const name of [a, b, c]) {
            await createGroup(page, name);
        }
        const tail = async (): Promise<string[]> => (await groupOrder(page)).slice(-3);
        expect(await tail()).toEqual([a, b, c]);
        await track(page, c).scrollIntoViewIfNeeded();

        // Up: the dragged group lands above the target
        await track(page, c).locator('.drag-handle').dragTo(track(page, a).locator('.track-label'));
        await expect.poll(tail).toEqual([c, a, b]);

        // Down: it lands below the target
        await track(page, c).locator('.drag-handle').dragTo(track(page, b).locator('.track-label'));
        await expect.poll(tail).toEqual([a, b, c]);

        await page.reload();
        await expect(page.locator('.track').first()).toBeVisible();
        expect(await tail()).toEqual([a, b, c]);
    });

    test('shows where a dragged group will land', async ({ page }) => {
        await openApp(page);
        const [a, b] = [unique('A'), unique('B')];
        await createGroup(page, a);
        await createGroup(page, b);
        await track(page, b).scrollIntoViewIfNeeded();

        const handle = (await track(page, b).locator('.drag-handle').boundingBox())!;
        const target = (await track(page, a).locator('.track-label').boundingBox())!;
        await page.mouse.move(handle.x + handle.width / 2, handle.y + handle.height / 2);
        await page.mouse.down();
        await page.mouse.move(target.x + 40, target.y + target.height / 2, { steps: 4 });
        await page.mouse.move(target.x + 42, target.y + target.height / 2);

        await expect(track(page, a)).toHaveClass(/drop-before/);
        await expect(track(page, b)).toHaveClass(/dragging/);

        await page.mouse.up();
        await expect(page.locator('.drop-before, .drop-after, .dragging')).toHaveCount(0);
        await expect.poll(async () => (await groupOrder(page)).slice(-2)).toEqual([b, a]);
    });

    test('reorders groups with the buttons in the dialog and with Alt and the arrow keys', async ({ page }) => {
        await openApp(page);
        const [a, b] = [unique('A'), unique('B')];
        await createGroup(page, a);
        await createGroup(page, b);
        const tail = async (): Promise<string[]> => (await groupOrder(page)).slice(-2);

        await track(page, b).locator('.track-info').click();
        const dialog = page.getByRole('dialog', { name: 'Edit group' });
        await dialog.getByRole('button', { name: 'Move up' }).click();
        await expect.poll(tail).toEqual([b, a]);
        await expect(page.locator('.toast')).toContainText(`Moved “${b}” to position`);
        await dialog.getByRole('button', { name: 'Move down' }).click();
        await expect.poll(tail).toEqual([a, b]);
        await dialog.getByRole('button', { name: 'Cancel' }).click();

        await track(page, b).locator('.track-info').focus();
        await page.keyboard.press('Alt+ArrowUp');
        await expect.poll(tail).toEqual([b, a]);
        await expect(track(page, b).locator('.track-info')).toBeFocused();
        await page.keyboard.press('Alt+ArrowDown');
        await expect.poll(tail).toEqual([a, b]);
    });

    test('a deleted group disappears from the New item dialog', async ({ page }) => {
        await openApp(page);
        const name = unique('Gone');
        await createGroup(page, name);
        const options = page.locator('#add-item-group option', { hasText: name });
        await expect(options).toHaveCount(1);

        await deleteGroup(page, name);

        await expect(options).toHaveCount(0);
    });
});
