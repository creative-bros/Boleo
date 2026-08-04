import { test, expect } from '@playwright/test';

test.describe('Boleo login flows', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('does not show the create account entry point', async ({ page }) => {
    await expect(page.getByRole('link', { name: 'Crear cuenta' })).toHaveCount(0);
  });

  test('can request password recovery', async ({ page }) => {
    await page.getByRole('link', { name: 'Recuperar contraseña' }).click();
    await expect(page.getByRole('heading', { name: 'Validación de identidad' })).toBeVisible();

    await page.getByLabel('Correo electrónico').fill('admin@boleo.mx');
    await page.getByLabel('Número telefónico').fill('5512345678');
    await page.getByRole('button', { name: 'Enviar recuperación' }).click();

    await expect(page.getByText('Te enviamos un mensaje de recuperacion')).toBeVisible();
  });
});
