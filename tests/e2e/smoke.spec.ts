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
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/$/);
});

test('the landing page is triage with its tabs', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email!);
    await page.getByLabel('Password', { exact: true }).fill(password!);
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page.getByRole('heading', { name: 'Triage' })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Overdue/ })).toBeVisible();
    await expect(
        page.getByRole('tab', { name: /Needs attention/ }),
    ).toBeVisible();
});
