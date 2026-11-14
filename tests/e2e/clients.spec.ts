import { expect, test } from '@playwright/test';

const email = process.env.E2E_EMAIL;
const password = process.env.E2E_PASSWORD;

test.skip(
    !email || !password,
    'Set E2E_EMAIL and E2E_PASSWORD to run the client import smoke test.',
);

test('the owner can preview a client CSV import', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email!);
    await page.getByLabel('Password', { exact: true }).fill(password!);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/clients/import');
    await expect(
        page.getByRole('heading', { name: 'Import clients' }),
    ).toBeVisible();

    const phone = `07${String(Date.now()).slice(-8)}`;
    const csv = [
        'name,phone,account_manager_email',
        `E2E Import ${Date.now()},${phone},algiers@khazna.test`,
    ].join('\n');

    await page.locator('input[type="file"]').setInputFiles({
        name: 'clients.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from(csv),
    });

    await page.getByRole('button', { name: 'Preview import' }).click();

    await expect(page).toHaveURL(/\/clients\/import\/[A-Za-z0-9]{40}$/);
    await expect(page.getByText('2. Review rows')).toBeVisible();
    await expect(
        page.getByRole('cell', { name: 'Ready to import' }),
    ).toBeVisible();
    await expect(
        page.getByRole('cell', { name: 'algiers@khazna.test' }),
    ).toBeVisible();
});

test('the owner can create a client from the list', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email!);
    await page.getByLabel('Password', { exact: true }).fill(password!);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/clients');
    await page.getByRole('button', { name: 'New client' }).click();

    const dialog = page.getByRole('dialog');
    const name = `E2E Client ${Date.now()}`;
    const phone = `06${String(Date.now()).slice(-8)}`;

    await dialog.getByLabel('Name').fill(name);
    await dialog.getByLabel('Phone').fill(phone);
    await dialog.getByRole('combobox').first().click();
    await page.getByRole('option', { name: /Amine Boudjelal/ }).click();
    await dialog.getByRole('button', { name: 'Save' }).click();

    await expect(dialog).toBeHidden();
    await expect(page.getByRole('link', { name })).toBeVisible();
});
