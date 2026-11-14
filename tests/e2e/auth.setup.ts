import { expect, test as setup } from '@playwright/test';

const email = process.env.E2E_EMAIL;
const password = process.env.E2E_PASSWORD;
const authFile = 'tests/e2e/.auth/owner.json';

setup.skip(
    !email || !password,
    'Set E2E_EMAIL and E2E_PASSWORD to run the browser tests.',
);

setup('authenticate as the owner', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email!);
    await page.getByLabel('Password', { exact: true }).fill(password!);
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/$/);
    await page.context().storageState({ path: authFile });
});
