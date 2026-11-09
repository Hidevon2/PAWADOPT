import { test, expect, type Page } from '@playwright/test'

async function staffSession(page: Page, role: 'admin' | 'manager') {
  const pets = (await (await page.request.get('/api/pets')).json()).data
  let status = 'submitted'
  const application = () => ({ id: 1000, reference: 'PAW-STAFF-TEST', status, pet: pets[0], user: { name: 'Alex Sample', email: 'alex@example.test' }, phone: '09123456789', city: 'Pasig', housing: 'Apartment', experience: 'Had pets before', message: 'A calm and caring home with plenty of daily care and attention.', review_note: null, created_at: '2026-11-09T10:00:00Z' })
  await page.route('**/api/login', route => route.fulfill({ json: { user: { id: 999, name: 'Staff Test', email: 'staff@example.test', role }, token: 'staff-ui-token' } }))
  await page.route('**/api/favorites', route => route.fulfill({ json: { data: [] } }))
  await page.route('**/api/staff/overview', route => route.fulfill({ json: { data: { available_pets: 8, pending_applications: status === 'submitted' ? 1 : 0, adopted_pets: status === 'approved' ? 1 : 0, open_messages: 0 } } }))
  await page.route('**/api/staff/applications?*', route => route.fulfill({ json: { data: [application()], last_page: 1 } }))
  await page.route('**/api/staff/applications/1000', route => {
    status = route.request().postDataJSON().status
    return route.fulfill({ json: { data: application() } })
  })
  await page.route('**/api/staff/users?*', route => route.fulfill({ json: { data: [{ id: 999, name: 'Staff Test', email: 'staff@example.test', role: 'admin' }, { id: 1001, name: 'Sample Adopter', email: 'sample@example.test', role: 'adopter' }], last_page: 1 } }))
  await page.goto('/')
  await page.getByRole('button', { name: 'Sign in', exact: true }).click()
  await page.getByLabel('Email address', { exact: true }).fill('staff@example.test')
  await page.getByLabel('Password', { exact: true }).fill('ui-test-password')
  await page.locator('.auth-dialog').getByRole('button', { name: 'Sign in', exact: true }).last().click()
  await expect(page.getByText(`${role.toUpperCase()} WORKSPACE`, { exact: true })).toBeVisible()
  await expect(page.getByText('PAW-STAFF-TEST', { exact: false })).toBeVisible()
  await expect(page.locator('.auth-dialog')).not.toBeVisible()
}

test('manager reviews applications on mobile and has no account-role controls', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await staffSession(page, 'manager')
  await expect(page.getByRole('button', { name: 'Accounts', exact: true })).toHaveCount(0)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy()
  expect((await page.locator('.staff-card-copy').first().boundingBox())!.width).toBeGreaterThan(150)
  await page.evaluate(() => document.fonts.ready)
  await expect(page.locator('.q-notification')).toHaveCount(0, { timeout: 10000 })
  await page.screenshot({ path: '../screenshots/manager-dashboard-mobile.png', fullPage: true })
  await page.getByRole('button', { name: 'View application', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Alex Sample & Sage' })).toBeVisible()
  await page.getByLabel('Note for the applicant (optional)').fill('Approved in the fictional classroom workflow.')
  await page.getByRole('button', { name: 'Approve adoption', exact: true }).click()
  await expect(page.locator('.staff-card .staff-status')).toHaveText('Approved')
  await page.getByRole('button', { name: 'Pets', exact: true }).click()
  await page.getByRole('button', { name: 'Edit Sage', exact: true }).click()
  await expect(page.getByLabel('Pet name', { exact: true })).toHaveValue('Sage')
  await expect(page.getByRole('button', { name: 'Save pet profile', exact: true })).toBeVisible()
})

test('admin can change another account role and open password settings', async ({ page }) => {
  await staffSession(page, 'admin')
  await page.getByRole('button', { name: 'Accounts', exact: true }).click()
  await expect(page.getByText('Sample Adopter', { exact: true })).toBeVisible()
  const ownAccount = page.locator('.staff-account').filter({ hasText: 'staff@example.test' })
  await expect(ownAccount.getByRole('button', { name: 'Save role' })).toBeDisabled()
  const account = page.locator('.staff-account').filter({ hasText: 'sample@example.test' })
  let changed = ''
  await page.route('**/api/staff/users/1001/role', route => { changed = route.request().postDataJSON().role; return route.fulfill({ json: { data: { id: 1001, role: changed } } }) })
  await account.getByLabel('Account role', { exact: true }).click()
  await page.getByRole('option', { name: 'manager', exact: true }).click()
  await account.getByRole('button', { name: 'Save role', exact: true }).click()
  await expect(page.getByText('Role updated. This account must sign in again.')).toBeVisible()
  expect(changed).toBe('manager')
  await page.evaluate(() => document.fonts.ready)
  await expect(page.locator('.q-notification')).toHaveCount(0, { timeout: 10000 })
  await page.screenshot({ path: '../screenshots/admin-dashboard-desktop.png', fullPage: true })
  await page.getByRole('button', { name: 'Change password', exact: true }).click()
  await expect(page.getByLabel('Current password', { exact: true })).toBeVisible()
})

test('a revoked staff session clears private screens and allows signing in again', async ({ page }) => {
  await staffSession(page, 'manager')
  await page.route('**/api/staff/overview', route => route.fulfill({ status: 401, json: { message: 'Please sign in to continue.' } }))
  await page.getByRole('button', { name: 'Refresh', exact: true }).click()
  await expect(page.locator('.signin-button')).toBeVisible()
  await expect(page.locator('.staff-card')).toHaveCount(0)
  expect(await page.evaluate(() => sessionStorage.getItem('pawadopt-token'))).toBeNull()
})
