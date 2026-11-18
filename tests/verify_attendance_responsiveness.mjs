import { chromium } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

const VIEWPORTS = [
    { name: '320px', width: 320, height: 650 },
    { name: '360px', width: 360, height: 740 },
    { name: '375px', width: 375, height: 667 },
    { name: '390px', width: 390, height: 844 },
    { name: '430px', width: 430, height: 932 },
    { name: '768px', width: 768, height: 1024 },
    { name: '1280px', width: 1280, height: 800 },
];

async function run() {
    console.log('--- STARTING ATTENDANCE & SCAN LOGS RESPONSIVENESS AUDIT ---');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });

    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await adminPage.goto(`${BASE_URL}/admin/login`);
    await adminPage.fill('input[name="username"]', 'admin1');
    await adminPage.fill('input[name="password"]', 'admin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard') || url.pathname.includes('/admin'));

    const pages = [
        { name: 'Main Attendance (All Exams)', path: '/admin/attendance' },
        { name: 'Attendance Filtered (Timetable 195)', path: '/admin/attendance?timetable_id=195' },
        { name: 'Verification Logs (Grouped)', path: '/admin/scan-logs?view=grouped' },
        { name: 'Verification Logs (Flat)', path: '/admin/scan-logs?view=flat' },
    ];

    let totalChecks = 0;
    let failures = 0;
    const results = [];

    for (const p of pages) {
        for (const vp of VIEWPORTS) {
            totalChecks++;
            await adminPage.setViewportSize({ width: vp.width, height: vp.height });
            await adminPage.goto(`${BASE_URL}${p.path}`, { waitUntil: 'networkidle' });
            await adminPage.waitForTimeout(100);

            const metrics = await adminPage.evaluate(() => {
                const docWidth = document.documentElement.clientWidth;
                const scrollWidth = document.documentElement.scrollWidth;
                const bodyScrollWidth = document.body.scrollWidth;
                const hasOverflow = scrollWidth > docWidth + 1 || bodyScrollWidth > docWidth + 1;
                return { docWidth, scrollWidth, bodyScrollWidth, hasOverflow };
            });

            if (metrics.hasOverflow) {
                failures++;
                results.push(`FAIL: ${p.name} @ ${vp.name} - docWidth: ${metrics.docWidth}, scrollWidth: ${metrics.scrollWidth}`);
            } else {
                results.push(`PASS: ${p.name} @ ${vp.name} - docWidth: ${metrics.docWidth}, scrollWidth: ${metrics.scrollWidth}`);
            }
        }
    }

    await browser.close();
    console.log('\n--- AUDIT RESULTS ---');
    results.forEach(r => console.log(r));
    console.log(`\nTotal Checks: ${totalChecks}, Failures: ${failures}`);

    if (failures > 0) {
        process.exit(1);
    }
}

run().catch(err => {
    console.error(err);
    process.exit(1);
});
