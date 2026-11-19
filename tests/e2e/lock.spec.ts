import { expect, test, type Page } from '@playwright/test';
import { groupOrder, item, openApp, track, unique } from './support/app';

const lockButton = (page: Page) => page.getByRole('button', { name: 'Lock', exact: true });

/** A group with one item from January to June of last year, with a description, made through the API */
async function seed(page: Page): Promise<{ group: string; groupId: number; title: string; itemId: number; year: number; description: string }> {
    const group = unique('Locked group');
    const title = unique('Locked item');
    const description = 'Source: the receipt in the blue folder';
    const year = new Date().getFullYear() - 1;

    const groupResponse = await page.request.post('/cmd/groups', { form: { name: group, icon: '🧪', color: '#e74c3c' } });
    expect(groupResponse.status()).toBe(201);
    const groupId = (await groupResponse.json()).id as number;
    const itemResponse = await page.request.post('/cmd/items', {
        form: { title, groupId: String(groupId), startDate: `${year}-01`, endDate: `${year}-06`, description },
    });
    expect(itemResponse.status()).toBe(201);
    const itemId = (await itemResponse.json()).id as number;
    await expect(item(page, title)).toHaveCount(1);
    await item(page, title).scrollIntoViewIfNeeded();

    return { group, groupId, title, itemId, year, description };
}

/** Every command the page sends, to prove that a blocked action sent none */
function recordCommands(page: Page): string[] {
    const sent: string[] = [];
    page.on('request', request => {
        if (new URL(request.url()).pathname.startsWith('/cmd/')) sent.push(`${request.method()} ${new URL(request.url()).pathname}`);
    });

    return sent;
}

/** Drive a resize with synthetic pointer events, which reach the handle even while CSS hides it */
async function syntheticResize(page: Page, itemId: number, dx: number): Promise<void> {
    await page.evaluate(({ id, delta }) => {
        const handle = document.querySelector(`#item-${id} .resize-handle-end`) as HTMLElement;
        const x = 500;
        handle.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true, clientX: x, clientY: 300 }));
        window.dispatchEvent(new PointerEvent('pointermove', { bubbles: true, cancelable: true, clientX: x + delta, clientY: 300 }));
        window.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, cancelable: true, clientX: x + delta, clientY: 300 }));
    }, { id: itemId, delta: dx });
}

/** Drag one group's handle onto another track with synthetic drag events and a filled DataTransfer */
async function syntheticGroupDrag(page: Page, fromId: number, toId: number): Promise<void> {
    const dataTransfer = await page.evaluateHandle(id => {
        const transfer = new DataTransfer();
        transfer.setData('text/plain', String(id));

        return transfer;
    }, fromId);
    const handle = page.locator(`#track-${fromId} .drag-handle`);
    const target = page.locator(`#track-${toId} .track-label`);
    await handle.dispatchEvent('dragstart', { dataTransfer });
    await target.dispatchEvent('dragover', { dataTransfer });
    await target.dispatchEvent('drop', { dataTransfer });
    await handle.dispatchEvent('dragend', { dataTransfer });
}

test.describe('edit lock on a desktop', () => {
    test('starts unlocked when nothing is stored', async ({ page }) => {
        await openApp(page);

        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'false');
        await expect(page.locator('html')).not.toHaveClass(/is-locked/);
        await expect(page.getByRole('button', { name: 'New item' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Add group' })).toBeVisible();
    });

    test('the button and L toggle the lock, and the choice survives a reload', async ({ page }) => {
        await openApp(page);

        await lockButton(page).click();
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('html')).toHaveClass(/is-locked/);
        await expect(page.getByRole('button', { name: 'New item' })).toBeHidden();
        await expect(page.getByRole('button', { name: 'Add group' })).toBeHidden();

        await page.reload();
        await openApp(page);
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'true');

        await page.locator('body').press('l');
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'false');
        await page.reload();
        await openApp(page);
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'false');

        // Typing an l in the search field does not toggle it
        await page.locator('#search').press('l');
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'false');
    });

    test('falls back to the default when storage is blocked', async ({ page }) => {
        await page.addInitScript(() => {
            Object.defineProperty(window, 'localStorage', { get() { throw new Error('blocked'); } });
        });
        await openApp(page);

        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'false');
        await lockButton(page).click();
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'true');
    });

    test('while locked, resizing, dragging groups, shortcuts and saving do nothing', async ({ page }) => {
        await openApp(page);
        const { group, groupId, title, itemId, year } = await seed(page);
        const other = await seed(page);
        const bar = item(page, title);
        await lockButton(page).click();
        const sent = recordCommands(page);

        // A stray drag over the end of the bar
        await expect(bar.locator('.resize-handle')).toHaveCount(2);
        await expect(bar.locator('.resize-handle').first()).toBeHidden();
        const box = (await bar.boundingBox())!;
        await page.mouse.move(box.x + box.width - 3, box.y + box.height / 2);
        await page.mouse.down();
        await page.mouse.move(box.x + box.width + 40, box.y + box.height / 2, { steps: 6 });
        await page.mouse.up();
        await page.keyboard.press('Escape');
        // And the same with events sent straight to the hidden handle
        await syntheticResize(page, itemId, 60);
        await expect(page.locator('.resize-tooltip')).toHaveCount(0);
        await expect(bar).toHaveAttribute('data-end-date', `${year}-06`);

        // Dragging a group onto another
        const order = await groupOrder(page);
        await syntheticGroupDrag(page, other.groupId, groupId);
        await expect(page.locator('.track.dragging, .drop-before, .drop-after')).toHaveCount(0);

        // Edit shortcuts
        await page.locator('body').press('n');
        await expect(page.getByRole('dialog', { name: 'New item' })).toBeHidden();
        await expect(page.locator('.toast-message')).toHaveText('Editing is locked. Press L to unlock.');
        await page.locator('body').press('g');
        await expect(page.getByRole('dialog', { name: 'New group' })).toBeHidden();
        await bar.focus();
        await page.keyboard.press('Shift+ArrowRight');
        await page.keyboard.press('Alt+ArrowLeft');
        await track(page, other.group).locator('.track-info').focus();
        await page.keyboard.press('Alt+ArrowUp');

        // The dialog opens read-only; neither Save, Enter nor a direct submit sends anything
        await bar.click();
        const dialog = page.getByRole('dialog', { name: 'Item details' });
        await expect(dialog).toBeVisible();
        await expect(dialog.getByRole('button', { name: 'Save' })).toHaveCount(0);
        await expect(dialog.getByRole('button', { name: 'Delete' })).toHaveCount(0);
        await expect(dialog.getByLabel('Title')).not.toBeEditable();
        await dialog.getByLabel('Title').press('Enter');
        await dialog.locator('form').evaluate((form: HTMLFormElement) => form.requestSubmit());
        expect(await dialog.locator('form').evaluate((form: HTMLFormElement) => window.timeline.submitForm(form, 'PUT', '/cmd/items/0'))).toBe(false);
        await expect(dialog).toBeVisible();
        await dialog.locator('.modal-actions').getByRole('button', { name: 'Close' }).click();
        await expect(dialog).toHaveCount(0);

        await page.waitForTimeout(500);
        expect(sent).toEqual([]);
        expect(await groupOrder(page)).toEqual(order);
        await page.reload();
        await openApp(page);
        await expect(item(page, title)).toHaveAttribute('data-start-date', `${year}-01`);
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-06`);
        expect(await groupOrder(page)).toEqual(order);
        await expect(track(page, group)).toHaveCount(1);

        // The same synthetic resize and drag do work once unlocked, so the checks above prove something
        await lockButton(page).click();
        await syntheticResize(page, itemId, 40);
        await expect(item(page, title)).toHaveAttribute('data-end-date', `${year}-08`);
        await syntheticGroupDrag(page, other.groupId, groupId);
        await expect.poll(async () => (await groupOrder(page)).indexOf(other.group)).toBeLessThan(order.indexOf(other.group));
    });

    test('the read-only views show the description and the group, and navigation still works', async ({ page }) => {
        await openApp(page);
        const { group, title, description } = await seed(page);
        await lockButton(page).click();

        await item(page, title).focus();
        await page.keyboard.press('Enter');
        const dialog = page.getByRole('dialog', { name: 'Item details' });
        await expect(dialog.getByLabel('Description')).toHaveValue(description);
        await expect(dialog.getByLabel('Description')).toBeVisible();
        await expect(dialog.getByLabel('Description')).not.toBeEditable();
        await expect(dialog.getByLabel('Group', { exact: true })).toBeDisabled();
        await expect(dialog.getByText('Editing is locked')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);

        await track(page, group).locator('.track-info').click();
        const groupDialog = page.getByRole('dialog', { name: 'Group details' });
        await expect(groupDialog.getByLabel('Name')).toHaveValue(group);
        await expect(groupDialog.getByLabel('Name')).not.toBeEditable();
        await expect(groupDialog.getByRole('button', { name: 'Move up' })).toHaveCount(0);
        await expect(groupDialog.getByRole('button', { name: 'Delete' })).toHaveCount(0);
        await expect(groupDialog.getByRole('button', { name: 'Save' })).toHaveCount(0);
        await groupDialog.locator('.modal-actions').getByRole('button', { name: 'Close' }).click();

        // Pan, zoom, fit, today, search and about
        const zoom = page.locator('.zoom-level');
        await page.getByRole('button', { name: 'Zoom in' }).click();
        await expect(zoom).toHaveText('150%');
        await page.locator('body').press('f');
        await expect(zoom).not.toHaveText('150%');
        await page.getByRole('button', { name: 'Reset view' }).click();
        await expect(zoom).toHaveText('100%');
        await page.locator('#search').fill(title);
        await page.locator('#search').press('Enter');
        await expect(item(page, title)).toBeFocused();
        await page.getByRole('button', { name: 'About' }).click();
        await expect(page.getByRole('dialog', { name: 'About Life Timeline' })).toBeVisible();
    });
});

test.describe('edit lock on a touch screen', () => {
    test.use({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } });

    test('starts locked, opens items read-only on a tap, and remembers an unlock', async ({ page }) => {
        await openApp(page);
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'true');
        const { title, description } = await seed(page);

        await expect(item(page, title).locator('.resize-handle').first()).toBeHidden();
        await item(page, title).tap();
        const dialog = page.getByRole('dialog', { name: 'Item details' });
        await expect(dialog.getByLabel('Description')).toHaveValue(description);
        await dialog.locator('.modal-actions').getByRole('button', { name: 'Close' }).tap();
        await expect(dialog).toHaveCount(0);

        await lockButton(page).tap();
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'false');
        await expect(item(page, title).locator('.resize-handle').first()).toBeVisible();
        await page.reload();
        await openApp(page);
        await expect(lockButton(page)).toHaveAttribute('aria-pressed', 'false');
    });
});
