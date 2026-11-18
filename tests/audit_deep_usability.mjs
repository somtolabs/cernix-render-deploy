import { chromium } from '@playwright/test';

const VIEWPORTS = [
    { name: '320px', width: 320, height: 600 },
    { name: '360px', width: 360, height: 740 },
    { name: '375px', width: 375, height: 667 },
    { name: '390px', width: 390, height: 844 },
    { name: '430px', width: 430, height: 932 },
];

const BASE_URL = 'http://127.0.0.1:8000';

async function auditDeepLayout() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });

    // Login as Super Admin
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await adminPage.goto(`${BASE_URL}/admin/login`);
    await adminPage.fill('input[name="username"]', 'superadmin');
    await adminPage.fill('input[name="password"]', 'superadmin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'), { timeout: 10000 });

    console.log('=== DEEP INSPECTION OF ADMIN INTERFACES ===');
    
    // Inspect Header at 320px
    for (const vp of VIEWPORTS) {
        await adminPage.setViewportSize({ width: vp.width, height: vp.height });
        await adminPage.goto(`${BASE_URL}/admin/dashboard`, { waitUntil: 'domcontentloaded' });
        await adminPage.waitForTimeout(100);

        const headerMetrics = await adminPage.evaluate(() => {
            const head = document.querySelector('.admin-mobile-head');
            if (!head) return null;
            const menuBtn = head.querySelector('.admin-menu-btn');
            const title = head.querySelector('.admin-mobile-head-title');
            const logo = head.querySelector('svg, img');
            const headRect = head.getBoundingClientRect();
            const titleSpan = title ? title.querySelector('span') : null;
            return {
                headWidth: headRect.width,
                headScrollWidth: head.scrollWidth,
                menuBtnWidth: menuBtn ? menuBtn.getBoundingClientRect().width : 0,
                titleWidth: title ? title.getBoundingClientRect().width : 0,
                titleSpanWidth: titleSpan ? titleSpan.getBoundingClientRect().width : 0,
                titleSpanScrollWidth: titleSpan ? titleSpan.scrollWidth : 0,
                titleSpanText: titleSpan ? titleSpan.textContent : '',
                logoRight: logo ? logo.getBoundingClientRect().right : 0
            };
        });

        console.log(`[Mobile Header @ ${vp.width}px]:`, headerMetrics);
    }

    // Inspect Settings Fee Grid and Danger Zone at 320px
    console.log('\n=== INSPECTING ADMIN SETTINGS AT 320px ===');
    await adminPage.setViewportSize({ width: 320, height: 700 });
    await adminPage.goto(`${BASE_URL}/admin/settings`, { waitUntil: 'domcontentloaded' });
    const settingsMetrics = await adminPage.evaluate(() => {
        const feeItems = Array.from(document.querySelectorAll('.fee-item')).map(item => {
            const label = item.querySelector('label');
            const input = item.querySelector('.fee-input, b');
            return {
                labelText: label ? label.textContent.trim() : '',
                labelWidth: label ? label.getBoundingClientRect().width : 0,
                inputWidth: input ? input.getBoundingClientRect().width : 0,
                itemScrollWidth: item.scrollWidth,
                itemClientWidth: item.clientWidth
            };
        });
        const dangerActions = Array.from(document.querySelectorAll('.danger-action')).map(da => {
            const h3 = da.querySelector('h3');
            return {
                title: h3 ? h3.textContent.trim() : '',
                scrollWidth: da.scrollWidth,
                clientWidth: da.clientWidth
            };
        });
        return { feeItems: feeItems.slice(0, 3), dangerActions: dangerActions.slice(0, 3) };
    });
    console.log('Settings feeItems:', settingsMetrics.feeItems);
    console.log('Settings dangerActions:', settingsMetrics.dangerActions);

    // Inspect Examiner Scanner Overlay at 320px
    console.log('\n=== INSPECTING EXAMINER SCANNER OVERLAY AT 320px ===');
    const exContext = await browser.newContext();
    const exPage = await exContext.newPage();
    await exPage.goto(`${BASE_URL}/examiner/login`);
    await exPage.fill('input[name="username"]', 'examiner1');
    await exPage.fill('input[name="password"]', 'password123');
    await exPage.click('button[type="submit"]');
    await exPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'), { timeout: 10000 });

    await exPage.setViewportSize({ width: 320, height: 700 });
    // Open verification overlay for inspection
    const scannerOverlayMetrics = await exPage.evaluate(() => {
        const overlay = document.getElementById('verifyOverlay');
        if (overlay) {
            overlay.removeAttribute('hidden');
            overlay.style.display = 'block';
        }
        const doc = document.querySelector('.verify-document');
        const details = document.querySelector('.verify-details');
        const actions = document.querySelector('.verify-actions');
        const detailCols = details ? Array.from(details.querySelectorAll('.verify-detail')).map(d => ({
            label: d.querySelector('span')?.textContent?.trim() || '',
            val: d.querySelector('b')?.textContent?.trim() || '',
            width: Math.round(d.getBoundingClientRect().width)
        })) : [];

        return {
            docWidth: doc ? doc.getBoundingClientRect().width : 0,
            docScrollWidth: doc ? doc.scrollWidth : 0,
            detailsWidth: details ? details.getBoundingClientRect().width : 0,
            detailCols: detailCols.slice(0, 6),
            actionsWidth: actions ? actions.getBoundingClientRect().width : 0,
            actionsScrollWidth: actions ? actions.scrollWidth : 0
        };
    });
    console.log('Examiner verifyOverlay at 320px:', scannerOverlayMetrics);

    await browser.close();
}

auditDeepLayout().catch(e => console.error(e));
