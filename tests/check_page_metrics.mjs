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
    await page.setInputFiles('#registryCsvInput', 'tests/fixtures/test_registry_import.csv');
    await page.waitForTimeout(1000);
    await page.click('#runDiagnosticBtn');
    await page.waitForSelector('#diagnosticResults', { state: 'visible' });

    const metrics = await page.evaluate(() => {
        const docEl = document.documentElement;
        const body = document.body;
        const scrollWrap = document.querySelector('.reg-preview-scroll');
        const scrollWrapRect = scrollWrap ? scrollWrap.getBoundingClientRect() : null;
        const panel = document.querySelector('#importStep2');
        const panelRect = panel ? panel.getBoundingClientRect() : null;
        const wizard = document.querySelector('.import-wizard');
        const wizardRect = wizard ? wizard.getBoundingClientRect() : null;
        const conflictCards = document.querySelector('.diag-conflict-cards');
        const conflictCardsRect = conflictCards ? conflictCards.getBoundingClientRect() : null;
        const conflictTableWrap = document.querySelector('.diag-conflict-table-wrap');
        const conflictTableWrapRect = conflictTableWrap ? conflictTableWrap.getBoundingClientRect() : null;

        return {
            windowWidth: window.innerWidth,
            docScrollWidth: docEl.scrollWidth,
            bodyScrollWidth: body.scrollWidth,
            wizardWidth: wizardRect ? Math.round(wizardRect.width) : null,
            wizardRight: wizardRect ? Math.round(wizardRect.right) : null,
            panelWidth: panelRect ? Math.round(panelRect.width) : null,
            panelRight: panelRect ? Math.round(panelRect.right) : null,
            scrollWrapWidth: scrollWrapRect ? Math.round(scrollWrapRect.width) : null,
            scrollWrapRight: scrollWrapRect ? Math.round(scrollWrapRect.right) : null,
            conflictCardsWidth: conflictCardsRect ? Math.round(conflictCardsRect.width) : null,
            conflictCardsRight: conflictCardsRect ? Math.round(conflictCardsRect.right) : null,
            conflictTableWrapDisplay: conflictTableWrap ? window.getComputedStyle(conflictTableWrap).display : null,
        };
    });
    console.log('PAGE METRICS AT 320px:', JSON.stringify(metrics, null, 2));
    await browser.close();
}

main().catch(console.error);
