import { chromium } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

const VIEWPORTS = [
    { name: '320px', width: 320, height: 650 },
    { name: '360px', width: 360, height: 740 },
    { name: '375px', width: 375, height: 667 },
    { name: '390px', width: 390, height: 844 },
    { name: '430px', width: 430, height: 932 },
    { name: '768px', width: 768, height: 1024 },
    { name: '1024px', width: 1024, height: 768 },
    { name: '1280px', width: 1280, height: 800 },
];

async function run() {
    console.log('--- STARTING COMPREHENSIVE RESPONSIVENESS & OVERFLOW VERIFICATION ---');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    let totalChecks = 0;
    let failures = 0;
    const failureLog = [];

    // 1. ADMIN
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await adminPage.goto(`${BASE_URL}/admin/login`);
    await adminPage.fill('input[name="username"]', 'superadmin');
    await adminPage.fill('input[name="password"]', 'superadmin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'));

    const adminScreens = [
        { name: 'Student Registry', path: '/admin/student-registry' },
        { name: 'Timetable', path: '/admin/timetable' },
        { name: 'Students', path: '/admin/students' },
        { name: 'Attendance', path: '/admin/attendance' },
        { name: 'Scan Logs', path: '/admin/scan-logs' },
        { name: 'Examiners', path: '/admin/examiners' },
        { name: 'Payments', path: '/admin/payments' },
        { name: 'Activity', path: '/admin/activity' },
    ];

    for (const screen of adminScreens) {
        for (const vp of VIEWPORTS) {
            totalChecks++;
            await adminPage.setViewportSize({ width: vp.width, height: vp.height });
            await adminPage.goto(`${BASE_URL}${screen.path}`, { waitUntil: 'networkidle' });
            await adminPage.waitForTimeout(150);

            const check = await adminPage.evaluate(() => {
                const docWidth = document.documentElement.clientWidth;
                const scrollWidth = document.documentElement.scrollWidth;
                const bodyScrollWidth = document.body.scrollWidth;
                const hasOverflow = scrollWidth > docWidth + 1 || bodyScrollWidth > docWidth + 1;
                return { docWidth, scrollWidth, bodyScrollWidth, hasOverflow };
            });

            if (check.hasOverflow) {
                failures++;
                failureLog.push(`[ADMIN] ${screen.name} @ ${vp.name}: document.clientWidth=${check.docWidth}, scrollWidth=${check.scrollWidth}, bodyScrollWidth=${check.bodyScrollWidth}`);
            }
        }
    }

    // 2. EXAMINER
    const examinerContext = await browser.newContext();
    const examinerPage = await examinerContext.newPage();
    await examinerPage.goto(`${BASE_URL}/examiner/login`);
    await examinerPage.fill('input[name="username"]', 'examiner1');
    try {
        await examinerPage.fill('input[name="password"]', 'password123');
        await examinerPage.click('button[type="submit"]');
        await examinerPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'), { timeout: 3000 });
    } catch (_) {
        await examinerPage.goto(`${BASE_URL}/examiner/login`);
        await examinerPage.fill('input[name="username"]', 'examiner1');
        await examinerPage.fill('input[name="password"]', 'examiner123');
        await examinerPage.click('button[type="submit"]');
        await examinerPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'), { timeout: 5000 });
    }

    let csc401Id = 1;
    try {
        await examinerPage.goto(`${BASE_URL}/examiner/assessments`);
        const href = await examinerPage.getAttribute('a[href*="/examiner/assessments/"]', 'href');
        if (href) {
            const m = href.match(/\/examiner\/assessments\/(\d+)/);
            if (m) csc401Id = m[1];
        }
    } catch (_) {}

    const examinerScreens = [
        { name: 'Assessment Workspace', path: `/examiner/assessments/${csc401Id}` },
        { name: 'Today Exams', path: '/examiner/today-exams' },
        { name: 'Scan History', path: '/examiner/scan-history' },
    ];

    for (const screen of examinerScreens) {
        for (const vp of VIEWPORTS) {
            totalChecks++;
            await examinerPage.setViewportSize({ width: vp.width, height: vp.height });
            await examinerPage.goto(`${BASE_URL}${screen.path}`, { waitUntil: 'networkidle' });
            await examinerPage.waitForTimeout(150);

            const check = await examinerPage.evaluate(() => {
                const docWidth = document.documentElement.clientWidth;
                const scrollWidth = document.documentElement.scrollWidth;
                const bodyScrollWidth = document.body.scrollWidth;
                const hasOverflow = scrollWidth > docWidth + 1 || bodyScrollWidth > docWidth + 1;
                return { docWidth, scrollWidth, bodyScrollWidth, hasOverflow };
            });

            if (check.hasOverflow) {
                failures++;
                failureLog.push(`[EXAMINER] ${screen.name} @ ${vp.name}: document.clientWidth=${check.docWidth}, scrollWidth=${check.scrollWidth}`);
            }
        }
    }

    // 3. STUDENT
    const studentContext = await browser.newContext();
    const studentPage = await studentContext.newPage();
    await studentPage.goto(`${BASE_URL}/student/login`);
    await studentPage.fill('#matric_no', '220404007');
    await studentPage.fill('#password', 'student123');
    await studentPage.click('#login-btn');
    await studentPage.waitForURL(url => url.pathname.includes('/student/dashboard'));

    const studentScreens = [
        { name: 'Timetable', path: '/student/timetable' },
        { name: 'Profile', path: '/student/profile' },
        { name: 'Dashboard', path: '/student/dashboard' },
    ];

    for (const screen of studentScreens) {
        for (const vp of VIEWPORTS) {
            totalChecks++;
            await studentPage.setViewportSize({ width: vp.width, height: vp.height });
            await studentPage.goto(`${BASE_URL}${screen.path}`, { waitUntil: 'networkidle' });
            await studentPage.waitForTimeout(150);

            const check = await studentPage.evaluate(() => {
                const docWidth = document.documentElement.clientWidth;
                const scrollWidth = document.documentElement.scrollWidth;
                const bodyScrollWidth = document.body.scrollWidth;
                const hasOverflow = scrollWidth > docWidth + 1 || bodyScrollWidth > docWidth + 1;
                return { docWidth, scrollWidth, bodyScrollWidth, hasOverflow };
            });

            if (check.hasOverflow) {
                failures++;
                failureLog.push(`[STUDENT] ${screen.name} @ ${vp.name}: document.clientWidth=${check.docWidth}, scrollWidth=${check.scrollWidth}`);
            }
        }
    }

    await browser.close();

    console.log(`\nVerification complete. Total checks: ${totalChecks}`);
    if (failures === 0) {
        console.log('SUCCESS: Zero horizontal viewport overflows detected across all screens and all 8 viewports (320px-1280px)!');
    } else {
        console.log(`FAILED: ${failures} overflow issue(s) detected:`);
        failureLog.forEach(f => console.log('  - ' + f));
        process.exit(1);
    }
}

run().catch(e => {
    console.error('Run error:', e);
    process.exit(1);
});
