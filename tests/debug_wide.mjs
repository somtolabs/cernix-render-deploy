import { chromium } from '@playwright/test';

async function main() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 320, height: 650 });
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[name="username"]', 'superadmin');
    await page.fill('input[name="password"]', 'superadmin123');
    await page.click('button[type="submit"]');
    await page.waitForURL(url => url.pathname.includes('/admin/dashboard'));
    await page.goto('http://127.0.0.1:8000/admin/student-registry');
    const input = await page.$('#registryCsvInput');
    await input.setInputFiles('tests/fixtures/test_registry_import.csv');
    await page.waitForTimeout(1000);
    const diagBtn = await page.$('#runDiagnosticBtn');
    if (diagBtn) await diagBtn.click();
    await page.waitForSelector('#diagnosticResults', { state: 'visible' });
    await page.waitForTimeout(500);

    const wide = await page.evaluate(() => {
        return Array.from(document.querySelectorAll('*'))
            .filter(el => {
                const s = window.getComputedStyle(el);
                return s.display !== 'none' && el.offsetWidth > 320;
            })
            .map(el => ({
                tag: el.tagName,
                id: el.id,
                cls: typeof el.className === 'string' ? el.className : '',
                ow: el.offsetWidth,
                sw: el.scrollWidth,
                cw: el.clientWidth,
                parentTag: el.parentElement ? el.parentElement.tagName : null,
                parentId: el.parentElement ? el.parentElement.id : null,
                parentCls: el.parentElement && typeof el.parentElement.className === 'string' ? el.parentElement.className : null
            }));
    });
    console.log('WIDE ELEMENTS COUNT:', wide.length);
    console.log(JSON.stringify(wide, null, 2));
    await browser.close();
}

main().catch(console.error);
