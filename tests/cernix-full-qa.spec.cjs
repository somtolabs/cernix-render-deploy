const { test, expect } = require('@playwright/test');

const BASE = process.env.CERNIX_BASE_URL || 'http://127.0.0.1:8000';

const USERS = {
  examiner: { username: 'examiner1', password: 'password123' },
  admin: { username: 'admin1', password: 'admin123' },
  superadmin: { username: 'superadmin', password: 'superadmin123' },
};

const publicRoutes = [
  '/',
  '/student/register',
  '/examiner/login',
  '/admin/login',
  '/documentation',
  '/presentation',
  '/health',
];

const protectedStudentRoutes = [
  '/student/dashboard',
  '/student/exam-pass',
  '/student/exam-pass/print',
];

const protectedExaminerRoutes = [
  '/examiner/dashboard',
];

const protectedAdminRoutes = [
  '/admin/dashboard',
  '/admin/settings',
  '/admin/intelligence',
  '/admin/students',
  '/admin/examiners',
  '/admin/payments',
  '/admin/timetable',
  '/admin/scan-logs',
  '/admin/activity',
];

const forbiddenText = [
  'SQLSTATE',
  'Integrity constraint violation',
  'Undefined variable',
  'Undefined index',
  'Trying to access array offset',
  'Whoops',
  'Internal Server Error',
  'Server Error',
  'Exception',
  'Stack trace',
  'APP_KEY',
  'CERNIX_HMAC_KEY',
  'CERNIX_ENCRYPTION_KEY',
  'REMITA_API_KEY',
];

async function setupWatchers(page, label) {
  page.on('console', msg => {
    if (['error', 'warning'].includes(msg.type())) {
      console.log(`[${label}] CONSOLE ${msg.type()}: ${msg.text()}`);
    }
  });

  page.on('pageerror', err => {
    console.log(`[${label}] PAGE ERROR: ${err.message}`);
  });

  page.on('response', res => {
    if (res.status() >= 400) {
      console.log(`[${label}] HTTP ${res.status()}: ${res.url()}`);
    }
  });
}

async function pageText(page) {
  return await page.locator('body').innerText().catch(() => '');
}

async function checkNoCrashText(page, label) {
  const text = await pageText(page);

  for (const bad of forbiddenText) {
    if (text.includes(bad)) {
      throw new Error(`[${label}] Found dangerous/server error text: ${bad}`);
    }
  }

  return text;
}

async function screenshot(page, name) {
  await page.screenshot({
    path: `screenshots/${name}.png`,
    fullPage: true,
  });
}

async function login(page, role) {
  if (role === 'examiner') {
    await page.goto(`${BASE}/examiner/login`, { waitUntil: 'domcontentloaded' });
  } else {
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'domcontentloaded' });
  }

  await page.locator('#username, input[autocomplete="username"], input[type="text"]').first().fill(USERS[role].username);
  await page.locator('#password, input[autocomplete="current-password"], input[type="password"]').first().fill(USERS[role].password);
  await page.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.waitForURL(url => !/\/(admin|examiner)\/login$/.test(url.pathname), { timeout: 15000 });

  if (/\/(admin|examiner)\/login$/.test(new URL(page.url()).pathname)) {
    throw new Error(`${role} login did not leave the login page`);
  }
}

async function fillStudentRegistration(page, data) {
  await page.goto(`${BASE}/student/register`, { waitUntil: 'networkidle' });

  const faculty = page.locator('select[name="faculty"], select[name*="faculty"]').first();
  if (await faculty.count()) {
    await faculty.selectOption({ label: 'Faculty of Computing' }).catch(async () => {
      await faculty.selectOption('Faculty of Computing').catch(() => {});
    });
  }

  const department = page.locator('select[name="department_id"], select[name="department"], select[name*="department"]').first();
  if (await department.count()) {
    await department.selectOption({ label: data.department }).catch(async () => {
      const optionValue = await department.locator('option', { hasText: data.department }).first().getAttribute('value');
      if (optionValue) {
        await department.selectOption(optionValue);
      }
    });
  }

  const level = page.locator('select[name="level"], select[name*="level"]').first();
  if (await level.count()) {
    await level.selectOption({ value: String(data.level) }).catch(async () => {
      await level.selectOption({ label: String(data.level) });
    });
  }

  const studentNumber = page.locator(
    'input[name="student_number"], input[name*="student_number"], input[placeholder*="001"], input[placeholder*="008"], input[placeholder*="014"]'
  ).first();

  if (await studentNumber.count()) {
    await studentNumber.fill(data.studentNumber);
  } else {
    await page.locator('input[type="text"], input:not([type])').first().fill(data.studentNumber);
  }

  const rrr = page.locator(
    'input[name="rrr"], input[name*="rrr"], input[name*="remita"], input[placeholder*="TEST"]'
  ).first();

  if (await rrr.count()) {
    await rrr.fill(data.rrr);
  } else {
    await page.locator('input[type="text"], input:not([type])').last().fill(data.rrr);
  }
}

test.describe('CERNIX aggressive QA suite', () => {
  test.describe.configure({ timeout: 90000 });

  test.beforeEach(async ({ page }, testInfo) => {
    await setupWatchers(page, testInfo.title);
  });

  test('01 public routes load without Laravel crash pages', async ({ page }) => {
    for (const route of publicRoutes) {
      const res = await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
      console.log(`${route}: ${res && res.status()}`);

      await expect(page.locator('body')).toBeVisible();
      const text = await checkNoCrashText(page, `public route ${route}`);

      expect(text.length).toBeGreaterThan(10);
      await screenshot(page, `public-${route.replaceAll('/', '_') || 'home'}`);
    }
  });

  test('02 homepage has main portal links and no broken visible layout', async ({ page }) => {
    await page.goto(BASE, { waitUntil: 'networkidle' });

    const text = await checkNoCrashText(page, 'homepage');
    expect(text).toMatch(/CERNIX|AAUA|Exam|Verification/i);

    const links = await page.locator('a').evaluateAll(items =>
      items.map(a => ({ text: a.innerText.trim(), href: a.href })).filter(x => x.href)
    );

    console.log('Homepage links:', links);
    expect(links.length).toBeGreaterThan(0);

    await screenshot(page, 'homepage-full');
  });

  test('03 all homepage links are reachable', async ({ page }) => {
    await page.goto(BASE, { waitUntil: 'domcontentloaded' });

    const links = await page.locator('a').evaluateAll(items =>
      items
        .map(a => a.href)
        .filter(Boolean)
        .filter(href => href.startsWith(window.location.origin))
    );

    const uniqueLinks = [...new Set(links)].slice(0, 25);
    console.log('Testing links:', uniqueLinks);

    for (const href of uniqueLinks) {
      const res = await page.goto(href, { waitUntil: 'domcontentloaded' });
      const status = res && res.status();

      console.log(`${status} ${href}`);

      expect(status).toBeLessThan(500);
      await checkNoCrashText(page, `link ${href}`);
    }
  });

  test('04 student registration valid demo flow with TEST-DEMO', async ({ page }) => {
    await fillStudentRegistration(page, {
      department: 'Computer Science',
      level: 400,
      studentNumber: '008',
      rrr: 'TEST-DEMO',
    });

    await screenshot(page, 'student-register-valid-filled');

    const previewText = await pageText(page);
    console.log('Before submit:', previewText.slice(0, 1200));

    expect(previewText).toMatch(/220404008|008|Computer Science|100,000|₦100,000|TEST/i);

    await page.locator('button[type="submit"], input[type="submit"], button').last().click();
    await page.waitForTimeout(2500);

    const text = await checkNoCrashText(page, 'student valid registration');
    console.log('After submit URL:', page.url());
    console.log(text.slice(0, 1500));

    await screenshot(page, 'student-register-valid-result');

    expect(text).toMatch(/dashboard|exam access|registered|verified|student|CERNIX|payment/i);
    expect(page.url()).toContain('/student/dashboard');
  });

  test('05 student registration accepts another TEST- value in demo mode', async ({ page }) => {
    await fillStudentRegistration(page, {
      department: 'Data Science',
      level: 300,
      studentNumber: '010',
      rrr: 'TEST-SOMTO',
    });

    const previewText = await pageText(page);
    console.log('Preview:', previewText.slice(0, 1000));
    expect(previewText).toMatch(/230408010|010|Data Science|150,000|₦150,000/i);

    await page.locator('button[type="submit"], input[type="submit"], button').last().click();
    await page.waitForTimeout(2500);

    const text = await checkNoCrashText(page, 'student TEST-SOMTO registration');
    console.log('Result URL:', page.url());
    console.log(text.slice(0, 1500));

    await screenshot(page, 'student-register-test-somto-result');

    expect(text).toMatch(/dashboard|exam access|registered|verified|student|CERNIX|payment/i);
    expect(page.url()).toContain('/student/dashboard');
  });

  test('06 invalid student number is rejected cleanly', async ({ page }) => {
    await fillStudentRegistration(page, {
      department: 'Computer Science',
      level: 400,
      studentNumber: '015',
      rrr: 'TEST-DEMO',
    });

    await page.locator('button[type="submit"], input[type="submit"], button').last().click();
    await page.waitForTimeout(1500);

    const text = await checkNoCrashText(page, 'invalid student number');
    console.log(text.slice(0, 1200));

    await screenshot(page, 'student-invalid-number-015');

    expect(text).toMatch(/001|014|passport|available|invalid|student number|demo/i);
  });

  test('07 invalid TEST- with empty suffix is rejected', async ({ page }) => {
    await fillStudentRegistration(page, {
      department: 'Computer Science',
      level: 400,
      studentNumber: '008',
      rrr: 'TEST-',
    });

    await page.locator('button[type="submit"], input[type="submit"], button').last().click();
    await page.waitForTimeout(1500);

    const text = await checkNoCrashText(page, 'invalid TEST-');
    console.log(text.slice(0, 1200));

    await screenshot(page, 'student-invalid-test-empty');

    expect(text).toMatch(/invalid|required|RRR|TEST|payment|not allowed/i);
  });

  test('08 random non-TEST RRR is rejected in demo unless real payment exists', async ({ page }) => {
    await fillStudentRegistration(page, {
      department: 'Computer Science',
      level: 400,
      studentNumber: '008',
      rrr: 'RANDOM-001',
    });

    await page.locator('button[type="submit"], input[type="submit"], button').last().click();
    await page.waitForTimeout(1500);

    const text = await checkNoCrashText(page, 'invalid RANDOM RRR');
    console.log(text.slice(0, 1200));

    await screenshot(page, 'student-invalid-random-rrr');

    expect(text).toMatch(/RRR|payment|not match|invalid|failed|not found/i);
  });

  test('09 admin login works', async ({ page }) => {
    await login(page, 'admin');

    const text = await checkNoCrashText(page, 'admin login');
    console.log('Admin URL:', page.url());
    console.log(text.slice(0, 1500));

    await screenshot(page, 'admin-dashboard');

    expect(text).toMatch(/admin|dashboard|students|payments|CERNIX/i);
  });

  test('10 super admin login works and should show elevated controls', async ({ page }) => {
    await login(page, 'superadmin');

    const text = await checkNoCrashText(page, 'superadmin login');
    console.log('Super Admin URL:', page.url());
    console.log(text.slice(0, 2000));

    await screenshot(page, 'superadmin-dashboard');

    expect(text).toMatch(/super admin|settings|control|roles|users|dashboard|CERNIX/i);
  });

  test('10b admin intelligence page shows metrics and hides sensitive internals', async ({ page }) => {
    await login(page, 'admin');
    await page.goto(`${BASE}/admin/intelligence`, { waitUntil: 'domcontentloaded' });

    const text = await checkNoCrashText(page, 'admin intelligence');
    console.log(text.slice(0, 1800));

    await screenshot(page, 'admin-intelligence');

    expect(text).toMatch(/Risk Intelligence|Total Scans|Approved|Rejected|Repeated|Duplicate/i);
    expect(text).toMatch(/Live System Summary|Enhanced Analysis|Live Summary|Python Enhanced|Live database summary|Python-enhanced report/i);

    for (const secret of ['encrypted_payload', 'hmac_signature', 'aes_key', 'hmac_secret', 'APP_KEY', 'REMITA_SECRET_KEY']) {
      expect(text).not.toContain(secret);
    }
  });

  test('11 examiner login works', async ({ page }) => {
    await login(page, 'examiner');

    const text = await checkNoCrashText(page, 'examiner login');
    console.log('Examiner URL:', page.url());
    console.log(text.slice(0, 1500));

    await screenshot(page, 'examiner-dashboard');

    expect(text).toMatch(/examiner|scanner|scan|dashboard|CERNIX/i);
  });

  test('12 super admin must NOT login to examiner portal', async ({ page }) => {
    await page.goto(`${BASE}/examiner/login`, { waitUntil: 'domcontentloaded' });

    await page.locator('input').nth(0).fill(USERS.superadmin.username);
    await page.locator('input').nth(1).fill(USERS.superadmin.password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForTimeout(1500);

    const text = await checkNoCrashText(page, 'superadmin blocked examiner');
    console.log('URL:', page.url());
    console.log(text.slice(0, 1200));

    await screenshot(page, 'superadmin-blocked-from-examiner');

    expect(text).toMatch(/not permitted|not allowed|unauthorized|invalid|examiner portal|login/i);
  });

  test('13 examiner must NOT login to admin portal', async ({ page }) => {
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'domcontentloaded' });

    await page.locator('input').nth(0).fill(USERS.examiner.username);
    await page.locator('input').nth(1).fill(USERS.examiner.password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForTimeout(1500);

    const text = await checkNoCrashText(page, 'examiner blocked admin');
    console.log('URL:', page.url());
    console.log(text.slice(0, 1200));

    await screenshot(page, 'examiner-blocked-from-admin');

    expect(text).toMatch(/not permitted|not allowed|unauthorized|invalid|admin portal|login/i);
  });

  test('13b examiner cannot open admin intelligence page', async ({ page }) => {
    await login(page, 'examiner');
    await page.goto(`${BASE}/admin/intelligence`, { waitUntil: 'domcontentloaded' });

    const text = await checkNoCrashText(page, 'examiner blocked admin intelligence');
    console.log('URL:', page.url());
    console.log(text.slice(0, 1200));

    expect(text).toMatch(/Admin Login|admin access|required|not permitted|login/i);
    expect(text).not.toMatch(/Risk Intelligence\s+Python-assisted/i);
  });

  test('14 protected student pages redirect when not logged in', async ({ page }) => {
    for (const route of protectedStudentRoutes) {
      const res = await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
      const text = await checkNoCrashText(page, `protected student ${route}`);

      console.log(`${route}: ${res && res.status()} ${page.url()}`);
      console.log(text.slice(0, 500));

      expect(text).toMatch(/login|register|student|CERNIX|unauthorized|forbidden/i);
    }
  });

  test('15 protected examiner pages redirect when not logged in', async ({ page }) => {
    for (const route of protectedExaminerRoutes) {
      const res = await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
      const text = await checkNoCrashText(page, `protected examiner ${route}`);

      console.log(`${route}: ${res && res.status()} ${page.url()}`);
      console.log(text.slice(0, 500));

      expect(text).toMatch(/login|examiner|CERNIX|unauthorized|forbidden/i);
    }
  });

  test('16 protected admin pages redirect when not logged in', async ({ page }) => {
    for (const route of protectedAdminRoutes) {
      const res = await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
      const text = await checkNoCrashText(page, `protected admin ${route}`);

      console.log(`${route}: ${res && res.status()} ${page.url()}`);
      console.log(text.slice(0, 500));

      expect(res.status()).toBeLessThan(500);
      expect(text).toMatch(/login|admin|CERNIX|unauthorized|forbidden/i);
    }
  });

  test('17 admin dashboard links do not crash after login', async ({ page }) => {
    await login(page, 'admin');

    const links = await page.locator('a').evaluateAll((items, baseUrl) =>
      items
        .map(a => ({ text: a.innerText.trim(), href: a.href }))
        .filter(x => x.href && x.href.startsWith(baseUrl))
    , BASE);

    const unique = [...new Map(links.map(x => [x.href, x])).values()].slice(0, 30);
    console.log('Admin links:', unique);

    for (const link of unique) {
      const res = await page.goto(link.href, { waitUntil: 'domcontentloaded' });
      const status = res && res.status();

      console.log(`${status} ${link.text} ${link.href}`);

      expect(status).toBeLessThan(500);
      await checkNoCrashText(page, `admin link ${link.href}`);
    }
  });

  test('18 examiner dashboard links do not crash after login', async ({ page }) => {
    await login(page, 'examiner');

    const links = await page.locator('a').evaluateAll((items, baseUrl) =>
      items
        .map(a => ({ text: a.innerText.trim(), href: a.href }))
        .filter(x => x.href && x.href.startsWith(baseUrl))
    , BASE);

    const unique = [...new Map(links.map(x => [x.href, x])).values()].slice(0, 30);
    console.log('Examiner links:', unique);

    for (const link of unique) {
      const res = await page.goto(link.href, { waitUntil: 'domcontentloaded' });
      const status = res && res.status();

      console.log(`${status} ${link.text} ${link.href}`);

      expect(status).toBeLessThan(500);
      await checkNoCrashText(page, `examiner link ${link.href}`);
    }
  });

  test('19 invalid route shows clean 404, not crash', async ({ page }) => {
    const res = await page.goto(`${BASE}/this-route-should-not-exist`, { waitUntil: 'domcontentloaded' });
    const status = res && res.status();

    const text = await checkNoCrashText(page, 'invalid route');
    console.log('Invalid route status:', status);
    console.log(text.slice(0, 800));

    await screenshot(page, 'invalid-route');

    expect(status).toBeGreaterThanOrEqual(400);
    expect(status).toBeLessThan(500);
  });

  test('20 mobile screenshots for core routes', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });

    for (const route of publicRoutes) {
      const res = await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
      console.log(`Mobile ${route}: ${res && res.status()}`);

      await expect(page.locator('body')).toBeVisible();
      await checkNoCrashText(page, `mobile ${route}`);

      await screenshot(page, `mobile-${route.replaceAll('/', '_') || 'home'}`);
    }
  });

  test('21 desktop screenshots for core routes', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });

    for (const route of publicRoutes) {
      const res = await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
      console.log(`Desktop ${route}: ${res && res.status()}`);

      await expect(page.locator('body')).toBeVisible();
      await checkNoCrashText(page, `desktop ${route}`);

      await screenshot(page, `desktop-${route.replaceAll('/', '_') || 'home'}`);
    }
  });

  test('22 light reliability navigation loop', async ({ page }) => {
    for (let i = 1; i <= 5; i++) {
      for (const route of publicRoutes) {
        const res = await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
        console.log(`Loop ${i} ${route}: ${res && res.status()}`);

        expect(res.status()).toBeLessThan(500);
        await page.waitForTimeout(250);
      }
    }
  });
});
