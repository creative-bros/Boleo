import { test, expect } from '@playwright/test';

test.describe('Boleo login flows', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('can create a new account from the UI', async ({ page }) => {
    const email = `playwright-${Date.now()}@boleo.mx`;

    await page.getByRole('link', { name: 'Crear cuenta' }).click();
    await expect(page.getByRole('heading', { name: 'Registro de usuario' })).toBeVisible();

    await page.getByLabel('Nombre completo').fill('Usuario Playwright');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.getByLabel('Número telefónico').fill(`55${Date.now().toString().slice(-8)}`);
    await page.getByLabel('Contraseña', { exact: true }).fill('claveSegura123');
    await page.getByLabel('Confirmar contraseña').fill('claveSegura123');
    await page.getByRole('button', { name: 'Crear cuenta' }).click();

    await expect(page.getByText('Cuenta creada correctamente')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Estado de la Comunidad' })).toBeVisible();
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
