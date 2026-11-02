import { expect, test } from '@playwright/test';

test('a guest can register for a free seeded event and lands on confirmation', async ({
    page,
}) => {
    // Start on the catalogue and open the free Fintech Founders Breakfast
    await page.goto('/events');
    await page.getByRole('link', { name: /fintech founders breakfast/i }).click();

    await expect(page.getByText(/reserve your spot/i)).toBeVisible();

    // Fill out the attendee form with a brand-new email
    const email = `jamie-${Date.now()}@e2e.test`;
    await page.fill('input[name="attendee[name]"]', 'Jamie Rivers');
    await page.fill('input[name="attendee[email]"]', email);

    // Accept terms + submit
    await page.check('input[name="terms_accepted"]');
    await page
        .getByRole('button', { name: /complete registration/i })
        .click();

    // Should land on the confirmation page with a reference
    await expect(page).toHaveURL(/\/events\/.+\/confirmation\/REG-/);
    await expect(page.getByText(/you're registered/i)).toBeVisible();
    await expect(page.getByText(/^REG-/)).toBeVisible();
});
