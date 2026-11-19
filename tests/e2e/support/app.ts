import { expect, type Locator, type Page } from '@playwright/test';

let counter = 0;

/** A name no other test uses, since all tests share one database */
export function unique(prefix: string): string {
    counter += 1;

    return `${prefix} ${Date.now().toString(36)}${counter}`;
}

/** Open the app and wait until the live connection and the first update have arrived */
export async function openApp(page: Page): Promise<void> {
    await page.goto('/');
    await expect(page.locator('.connection')).toHaveClass(/is-live/);
    await expect(page.locator('.track').first()).toBeVisible();
    await page.waitForFunction(() => 'timeline' in window);
}

/** Touch screens start locked; tests that edit there unlock first, as a user would */
export async function unlock(page: Page): Promise<void> {
    const button = page.getByRole('button', { name: 'Lock', exact: true });
    if ((await button.getAttribute('aria-pressed')) === 'true') await button.click();
    await expect(button).toHaveAttribute('aria-pressed', 'false');
}

export const wrapper =(page: Page): Locator => page.locator('.timeline-wrapper');

export async function zoomPercent(page: Page): Promise<number> {
    return parseInt((await page.locator('.zoom-level').textContent()) ?? '', 10);
}

export async function scrollLeft(page: Page): Promise<number> {
    return wrapper(page).evaluate(el => el.scrollLeft);
}

export const track = (page: Page, name: string): Locator =>
    page.locator('.track').filter({ has: page.locator('.track-name', { hasText: name }) });

export const item = (page: Page, title: string): Locator =>
    page.locator('.timeline-item').filter({ has: page.locator('.item-title', { hasText: title }) });

export async function groupOrder(page: Page): Promise<string[]> {
    return page.locator('.track .track-name').allTextContents();
}

export async function createGroup(page: Page, name: string, color = '#e74c3c'): Promise<void> {
    await page.getByRole('button', { name: 'Add group' }).click();
    const dialog = page.getByRole('dialog', { name: 'New group' });
    await dialog.getByLabel('Name').fill(name);
    await dialog.getByLabel('Icon').fill('🧪');
    await dialog.getByLabel('Color', { exact: true }).fill(color);
    await dialog.getByRole('button', { name: 'Add group' }).click();
    await expect(dialog).toBeHidden();
    await expect(track(page, name)).toBeVisible();
}

export interface NewItem {
    title: string;
    group: string;
    start: string;
    end?: string;
}

export async function createItem(page: Page, data: NewItem): Promise<void> {
    await page.getByRole('button', { name: 'New item' }).click();
    const dialog = page.getByRole('dialog', { name: 'New item' });
    await dialog.getByLabel('Title').fill(data.title);
    await dialog.getByLabel('Group', { exact: true }).selectOption({ label: `🧪 ${data.group}` });
    await dialog.getByLabel('Start').fill(data.start);
    if (data.end) {
        await dialog.getByLabel('End').fill(data.end);
    }
    await dialog.getByRole('button', { name: 'Add item' }).click();
    await expect(dialog).toBeHidden();
    await expect(item(page, data.title)).toBeVisible();
}

/** A group with one item from January to June of last year, which the opening view shows */
export async function createGroupWithItem(page: Page): Promise<{ group: string; title: string; year: number }> {
    const group = unique('Group');
    const title = unique('Item');
    const year = new Date().getFullYear() - 1;
    await createGroup(page, group);
    await createItem(page, { title, group, start: `${year}-01`, end: `${year}-06` });
    await item(page, title).scrollIntoViewIfNeeded();

    return { group, title, year };
}

export async function deleteGroup(page: Page, name: string): Promise<void> {
    await track(page, name).locator('.track-info').click();
    await page.getByRole('dialog', { name: 'Edit group' }).getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();
    await expect(track(page, name)).toHaveCount(0);
}

/** Drag one edge of an item by a number of pixels and leave the mouse button down */
export async function grabHandle(page: Page, target: Locator, edge: 'start' | 'end', dx: number): Promise<void> {
    await target.hover();
    const box = (await target.locator(`.resize-handle-${edge}`).boundingBox())!;
    const x = box.x + box.width / 2;
    const y = box.y + box.height / 2;
    await page.mouse.move(x, y);
    await page.mouse.down();
    await page.mouse.move(x + dx, y, { steps: 6 });
}

/** Drag a finger over the page, in steps, so the browser scrolls as it does for a real touch */
export async function swipe(page: Page, x: number, y: number, dx: number, dy: number): Promise<void> {
    const client = await page.context().newCDPSession(page);
    const send = (type: 'touchStart' | 'touchMove' | 'touchEnd', px = 0, py = 0): Promise<unknown> =>
        client.send('Input.dispatchTouchEvent', { type, touchPoints: type === 'touchEnd' ? [] : [{ x: px, y: py, id: 1 }] });

    await send('touchStart', x, y);
    for (let step = 1; step <= 12; step++) {
        await send('touchMove', x + (dx * step) / 12, y + (dy * step) / 12);
        await page.waitForTimeout(16);
    }
    await send('touchEnd');
    await client.detach();
}
