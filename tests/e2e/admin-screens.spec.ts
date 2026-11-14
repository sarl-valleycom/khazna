import { expect, test } from '@playwright/test';

const email = process.env.E2E_EMAIL;
const password = process.env.E2E_PASSWORD;

test.skip(
    !email || !password,
    'Set E2E_EMAIL and E2E_PASSWORD to run the admin smoke test.',
);

test('the owner can open the offices, users, settings and audit screens', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email!);
    await page.getByLabel('Password', { exact: true }).fill(password!);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    for (const [path, heading] of [
        ['/offices', 'Offices'],
        ['/users', 'Users'],
        ['/settings', 'Settings'],
        ['/audit-log', 'Audit log'],
    ] as const) {
        await page.goto(path);
        await expect(
            page.getByRole('heading', { name: heading }),
        ).toBeVisible();
    }
});
