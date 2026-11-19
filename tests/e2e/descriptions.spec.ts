import { expect, test, type Page } from '@playwright/test';
import { item, openApp, unique } from './support/app';

const detailsButton = (page: Page) => page.getByRole('button', { name: 'Details', exact: true });

/** A group with one item that has a description and one that has none, made through the API */
async function seed(page: Page): Promise<{ withText: string; without: string; description: string }> {
    const year = new Date().getFullYear() - 1;
    const description = 'Bought at the flea market, still works';
    const groupResponse = await page.request.post('/cmd/groups', { form: { name: unique('Described group'), icon: '🧪', color: '#3498db' } });
    const groupId = String((await groupResponse.json()).id);
    const withText = unique('Described item');
    const without = unique('Bare item');
    await page.request.post('/cmd/items', { form: { title: withText, groupId, startDate: `${year}-01`, endDate: `${year}-12`, description } });
    await page.request.post('/cmd/items', { form: { title: without, groupId, startDate: `${year - 3}-01`, endDate: `${year - 2}-12` } });
    await expect(item(page, withText)).toHaveCount(1);
    await item(page, withText).scrollIntoViewIfNeeded();

    return { withText, without, description };
}

test.describe('descriptions inside the items', () => {
    test('are hidden until the Details button shows them, and the choice survives a reload', async ({ page }) => {
        await openApp(page);
        const { withText, without, description } = await seed(page);
        const text = item(page, withText).locator('.item-description');

        await expect(detailsButton(page)).toHaveAttribute('aria-pressed', 'false');
        await expect(text).toBeHidden();
        const laneBefore = (await item(page, withText).boundingBox())!.height;

        await detailsButton(page).click();
        await expect(detailsButton(page)).toHaveAttribute('aria-pressed', 'true');
        await expect(text).toBeVisible();
        await expect(text).toHaveText(description);
        await expect(item(page, without).locator('.item-description')).toHaveCount(0);
        expect((await item(page, withText).boundingBox())!.height).toBeGreaterThan(laneBefore);

        await page.reload();
        await expect(detailsButton(page)).toHaveAttribute('aria-pressed', 'true');
        await item(page, withText).scrollIntoViewIfNeeded();
        await expect(item(page, withText).locator('.item-description')).toBeVisible();
    });

    test('the D key toggles them, but not while typing', async ({ page }) => {
        await openApp(page);
        const { withText } = await seed(page);
        const text = item(page, withText).locator('.item-description');

        await page.keyboard.press('d');
        await expect(text).toBeVisible();

        await page.getByRole('searchbox').focus();
        await page.keyboard.press('d');
        await expect(text).toBeVisible();

        await page.getByRole('searchbox').blur();
        await page.keyboard.press('d');
        await expect(text).toBeHidden();
    });

    test('stay visible after a live update re-renders the timeline', async ({ page }) => {
        await openApp(page);
        const { withText } = await seed(page);
        await detailsButton(page).click();

        await page.request.post('/cmd/groups', { form: { name: unique('Trigger'), icon: '🧪', color: '#2ecc71' } });
        await expect(page.locator('.track-name', { hasText: 'Trigger' })).toHaveCount(1);
        await expect(item(page, withText).locator('.item-description')).toBeVisible();
    });
});
