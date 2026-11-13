import { expect, test } from '@playwright/test';

const email = process.env.E2E_EMAIL;
const password = process.env.E2E_PASSWORD;

test.skip(
    !email || !password,
    'Set E2E_EMAIL and E2E_PASSWORD to run the staging smoke test.',
);

test('login then reach the authenticated app', async ({ page }) => {
    await page.goto('/login');

    await page.getByLabel('Email address').fill(email!);
    await page.getByLabel('Password', { exact: true }).fill(password!);
    await page.getByTestId('login-button').click();

    await expect(page).toHaveURL(/\/dashboard$/);
});

// Phase 1 adds /triage. Add the triage navigation to this smoke test then.
test.fixme('login then open triage', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email ?? '');
    await page.getByLabel('Password', { exact: true }).fill(password ?? '');
    await page.getByTestId('login-button').click();
    await page.goto('/triage');
    await expect(page).toHaveURL(/\/triage$/);
});
