import { expect, test } from '@playwright/test';

const email = process.env.E2E_EMAIL;
const password = process.env.E2E_PASSWORD;

test.skip(
    !email || !password,
    'Set E2E_EMAIL and E2E_PASSWORD to run the contract smoke test.',
);

test('the owner can create a client contract', async ({ page }) => {
    const stamp = Date.now();
    const clientName = `E2E Contract Client ${stamp}`;
    const phone = `05${String(stamp).slice(-8)}`;

    await page.goto('/clients');
    await page.getByRole('button', { name: 'New client' }).click();

    const clientDialog = page.getByRole('dialog');
    await clientDialog.getByLabel('Name').fill(clientName);
    await clientDialog.getByLabel('Phone').fill(phone);
    await clientDialog.getByRole('combobox').first().click();
    await page.getByRole('option', { name: /Amine Boudjelal/ }).click();
    await clientDialog.getByRole('button', { name: 'Save' }).click();
    await expect(clientDialog).toBeHidden();

    await page.goto('/contracts');
    await page.getByRole('button', { name: 'New contract' }).click();

    const contractDialog = page.getByRole('dialog');
    await contractDialog.getByRole('combobox').first().click();
    await page.getByRole('option', { name: new RegExp(clientName) }).click();
    await contractDialog.getByLabel('Amount').fill('55000');
    await contractDialog.getByRole('button', { name: 'Save' }).click();

    await expect(page).toHaveURL(/\/contracts\/\d+$/);
    await expect(
        page.getByRole('heading', { name: `SaaS renewal · ${clientName}` }),
    ).toBeVisible();
    await expect(page.getByText('55,000.00 DA')).toBeVisible();
});
