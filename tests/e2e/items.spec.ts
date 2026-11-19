import { expect, test } from '@playwright/test';
import { createGroup, createGroupWithItem, createItem, item, openApp, track, unique } from './support/app';

test.describe('items', () => {
    test('creates an item that takes its group\'s color', async ({ page }) => {
        await openApp(page);
        const group = unique('Group');
        const title = unique('Created');
        const year = new Date().getFullYear() - 1;
        await createGroup(page, group, '#e74c3c');

        await createItem(page, { title, group, start: `${year}-03`, end: `${year}-09` });

        const bar = item(page, title);
        await expect(track(page, group).locator('.timeline-item')).toHaveCount(1);
        await expect(bar).toHaveAttribute('data-start-date', `${year}-03`);
        await expect(bar).toHaveAttribute('data-end-date', `${year}-09`);
        await expect(bar).toHaveAttribute('aria-label', `${title}, ${group}, Mar ${year} to Sep ${year}`);
        expect(await bar.evaluate(el => el.style.getPropertyValue('--item-color'))).toBe('#e74c3c');
        // Seven months of 20px at 100%, minus the 2px gap
        expect((await bar.boundingBox())!.width).toBeCloseTo(138, 0);
        await expect(track(page, group).locator('.track-count')).toHaveText('1');

        // The form is empty again for the next item
        await page.getByRole('button', { name: 'New item' }).click();
        await expect(page.getByRole('dialog', { name: 'New item' }).getByLabel('Title')).toHaveValue('');
    });

    test('an item without an end is ongoing and runs to today', async ({ page }) => {
        await openApp(page);
        const group = unique('Group');
        const title = unique('Ongoing');
        await createGroup(page, group);

        await createItem(page, { title, group, start: `${new Date().getFullYear() - 1}-01` });

        const bar = item(page, title);
        await expect(bar).toHaveClass(/ongoing/);
        await expect(bar).toHaveAttribute('data-end-date', '');
        const now = (await page.locator('.now-line').boundingBox())!.x;
        const box = (await bar.boundingBox())!;
        expect(box.x + box.width).toBeGreaterThan(now);
    });

    test('validates in the browser: title required, end not before start', async ({ page }) => {
        await openApp(page);
        await page.getByRole('button', { name: 'New item' }).click();
        const dialog = page.getByRole('dialog', { name: 'New item' });

        await dialog.getByRole('button', { name: 'Add item' }).click();
        await expect(dialog).toBeVisible();
        expect(await dialog.getByLabel('Title').evaluate((el: HTMLInputElement) => el.validity.valueMissing)).toBe(true);

        await dialog.getByLabel('Start').fill('2020-05');
        await expect(dialog.getByLabel('End')).toHaveAttribute('min', '2020-05');
        await dialog.getByLabel('End').fill('2020-01');
        expect(await dialog.getByLabel('End').evaluate((el: HTMLInputElement) => el.validity.rangeUnderflow)).toBe(true);
    });

    test('keeps the dialog and the input when the server rejects or cannot be reached', async ({ page }) => {
        await openApp(page);
        const group = unique('Group');
        const title = unique('Rejected');
        await createGroup(page, group);

        await page.getByRole('button', { name: 'New item' }).click();
        const dialog = page.getByRole('dialog', { name: 'New item' });
        await dialog.getByLabel('Title').fill(title);
        await dialog.getByLabel('Group', { exact: true }).selectOption({ label: `🧪 ${group}` });
        await dialog.getByLabel('Start').fill('2021-02');

        // A real 422: the browser check is switched off and the end lies before the start
        await dialog.locator('form').evaluate((form: HTMLFormElement) => { form.noValidate = true; });
        await dialog.getByLabel('End').fill('2020-01');
        await dialog.getByRole('button', { name: 'Add item' }).click();
        await expect(dialog.getByRole('alert')).toContainText('End date must not be before the start date');
        await expect(dialog).toBeVisible();
        await expect(dialog.getByLabel('Title')).toHaveValue(title);
        await expect(dialog.getByLabel('End')).toHaveAttribute('aria-invalid', 'true');
        await expect(item(page, title)).toHaveCount(0);

        // No connection
        await dialog.getByLabel('End').fill('2021-08');
        await page.route('**/cmd/items', route => route.abort());
        await dialog.getByRole('button', { name: 'Add item' }).click();
        await expect(dialog.getByRole('alert')).toContainText('could not be reached');
        await expect(dialog.getByLabel('Title')).toHaveValue(title);

        // Trying again works and closes the dialog
        await page.unroute('**/cmd/items');
        await dialog.getByRole('button', { name: 'Add item' }).click();
        await expect(dialog).toBeHidden();
        await expect(item(page, title)).toBeVisible();
    });

    test('edits an item from a click', async ({ page }) => {
        await openApp(page);
        const { title, year } = await createGroupWithItem(page);
        const renamed = unique('Renamed');

        await item(page, title).click();
        const dialog = page.getByRole('dialog', { name: 'Edit item' });
        await expect(dialog.getByLabel('Title')).toHaveValue(title);
        await expect(dialog.getByLabel('Start')).toHaveValue(`${year}-01`);
        await expect(dialog.getByLabel('Same as the group')).toBeChecked();

        await dialog.getByLabel('Title').fill(renamed);
        await dialog.getByLabel('End').fill(`${year}-10`);
        await dialog.getByRole('button', { name: 'Purple' }).click();
        await expect(dialog.getByLabel('Same as the group')).not.toBeChecked();
        await dialog.getByLabel('Description').fill('Changed in a test');
        await dialog.getByRole('button', { name: 'Save' }).click();

        await expect(dialog).toHaveCount(0);
        const bar = item(page, renamed);
        await expect(bar).toHaveAttribute('data-end-date', `${year}-10`);
        expect(await bar.evaluate(el => el.style.getPropertyValue('--item-color'))).toBe('#9b59b6');
        await expect(item(page, title)).toHaveCount(0);

        // The description was saved too
        await bar.click();
        await expect(page.getByRole('dialog', { name: 'Edit item' }).getByLabel('Description')).toHaveValue('Changed in a test');
    });

    test('moves an item to another group', async ({ page }) => {
        await openApp(page);
        const { title, group } = await createGroupWithItem(page);
        const other = unique('Other');
        await createGroup(page, other);

        await item(page, title).click();
        const dialog = page.getByRole('dialog', { name: 'Edit item' });
        await dialog.getByLabel('Group', { exact: true }).selectOption({ label: `🧪 ${other}` });
        await dialog.getByRole('button', { name: 'Save' }).click();

        await expect(track(page, other).locator('.timeline-item')).toHaveCount(1);
        await expect(track(page, group).locator('.timeline-item')).toHaveCount(0);
        await expect(track(page, group).locator('.track-empty')).toBeVisible();
    });

    test('closes the edit dialog with Escape, Cancel, the close button and a click outside, and returns the focus', async ({ page }) => {
        await openApp(page);
        const { title } = await createGroupWithItem(page);
        const bar = item(page, title);
        const dialog = page.getByRole('dialog', { name: 'Edit item' });

        await bar.click();
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(bar).toBeFocused();

        await bar.click();
        await dialog.getByRole('button', { name: 'Cancel' }).click();
        await expect(dialog).toHaveCount(0);

        await bar.click();
        await dialog.getByRole('button', { name: 'Close' }).click();
        await expect(dialog).toHaveCount(0);

        await bar.click();
        await expect(dialog).toBeVisible();
        await page.mouse.click(5, 5);
        await expect(dialog).toHaveCount(0);
        await expect(bar).toBeVisible();
    });

    test('deletes an item only after the confirmation', async ({ page }) => {
        await openApp(page);
        const { title, group } = await createGroupWithItem(page);
        const bar = item(page, title);
        const confirm = page.getByRole('alertdialog');

        await bar.click();
        const dialog = page.getByRole('dialog', { name: 'Edit item' });
        await dialog.getByRole('button', { name: 'Delete' }).click();
        await expect(confirm).toContainText(`Delete “${title}”?`);
        await expect(confirm).toContainText('cannot be undone');

        await confirm.getByRole('button', { name: 'Cancel' }).click();
        await expect(confirm).toBeHidden();
        await expect(dialog).toBeVisible();
        await expect(bar).toHaveCount(1);

        await dialog.getByRole('button', { name: 'Delete' }).click();
        await confirm.getByRole('button', { name: 'Delete' }).click();
        await expect(confirm).toBeHidden();
        await expect(dialog).toHaveCount(0);
        await expect(bar).toHaveCount(0);
        // The focus goes to the group, since the item is gone
        await expect(track(page, group).locator('.track-info')).toBeFocused();
    });

    test('says so when a delete fails and keeps the item', async ({ page }) => {
        await openApp(page);
        const { title } = await createGroupWithItem(page);

        await item(page, title).click();
        await page.route('**/cmd/items/*', route => route.fulfill({ status: 500, contentType: 'application/json', body: '{"error":"Database is locked"}' }));
        await page.getByRole('dialog', { name: 'Edit item' }).getByRole('button', { name: 'Delete' }).click();
        const confirm = page.getByRole('alertdialog');
        await confirm.getByRole('button', { name: 'Delete' }).click();

        await expect(confirm.getByRole('alert')).toContainText('Database is locked');
        await expect(confirm).toBeVisible();
        await expect(item(page, title)).toHaveCount(1);
    });

    test('places items that overlap in separate lanes, and hand-overs in one', async ({ page }) => {
        await openApp(page);
        const group = unique('Lanes');
        const year = new Date().getFullYear() - 1;
        await createGroup(page, group);
        const [first, next, overlapping] = [unique('First'), unique('Next'), unique('Overlap')];

        await createItem(page, { title: first, group, start: `${year}-01`, end: `${year}-04` });
        // Starts in the month the first one ends: same lane
        await createItem(page, { title: next, group, start: `${year}-04`, end: `${year}-08` });
        await createItem(page, { title: overlapping, group, start: `${year}-02`, end: `${year}-06` });

        const top = async (title: string): Promise<number> => (await item(page, title).boundingBox())!.y;
        await expect.poll(async () => (await top(next)) - (await top(first))).toBe(0);
        expect(await top(overlapping)).toBeGreaterThan(await top(first));

        const a = (await item(page, first).boundingBox())!;
        const b = (await item(page, next).boundingBox())!;
        expect(a.x + a.width).toBeLessThanOrEqual(b.x);
    });
});
