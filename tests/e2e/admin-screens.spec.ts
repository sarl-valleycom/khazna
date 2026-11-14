import { expect, test } from '@playwright/test';

const email = process.env.E2E_EMAIL;
const password = process.env.E2E_PASSWORD;

test.skip(
    !email || !password,
    'Set E2E_EMAIL and E2E_PASSWORD to run the admin smoke test.',
);

test('the owner can open the main admin and client screens', async ({
    page,
}) => {
    for (const [path, heading] of [
        ['/clients', 'Clients'],
        ['/contracts', 'Contracts'],
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
