import { expect, test } from '@playwright/test';

test.describe('public surface', () => {
    test('landing page renders the hero and brand', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('heading', { level: 1 })).toContainText(
            /sold-out events/i,
        );
        await expect(page.getByRole('link', { name: /browse events/i }).first()).toBeVisible();
    });

    test('event catalogue shows seeded events + filters work', async ({
        page,
    }) => {
        await page.goto('/events');
        await expect(page.getByRole('heading', { level: 1 })).toContainText(
            /what's happening/i,
        );
        // At least a few seeded events should appear
        await expect(page.locator('h2').first()).toBeVisible();

        // Search narrows the list
        await page.getByPlaceholder(/search by title/i).fill('harbor');
        await expect(page.getByText(/harbor lights/i).first()).toBeVisible();
    });

    test('event detail renders ticket types + attendee form for guests', async ({
        page,
    }) => {
        await page.goto('/events');
        const firstCard = page.getByRole('link').filter({
            has: page.locator('h2'),
        }).first();
        await firstCard.click();

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        // Reserve-your-spot form should be present for guests
        await expect(page.getByText(/reserve your spot/i)).toBeVisible();
        await expect(
            page.getByRole('button', { name: /complete registration/i }),
        ).toBeVisible();
    });
});
