import { expect, test } from '@playwright/test';

test('a seeded organizer owner can log in and see their workspace', async ({
    page,
}) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'alice@pulse.test');
    await page.fill('input[name="password"]', 'password');
    await page.getByRole('button', { name: /^log in$/i }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    // Sidebar should include the organizer's workspace entry
    await expect(page.getByText(/pulse collective/i).first()).toBeVisible();

    // Navigating to the organizer events page should show the seeded roster
    await page.getByRole('link', { name: /pulse collective/i }).first().click();
    await expect(page).toHaveURL(/\/organizer\/pulse-collective\/events/);
    await expect(page.getByText(/harbor lights/i).first()).toBeVisible();
});
