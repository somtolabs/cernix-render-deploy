import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const BASE_URL = 'http://127.0.0.1:8000';
const PHASE = process.argv[2] || 'before';
const OUTPUT_DIR = path.join(process.cwd(), 'tests', 'screenshots', PHASE);

if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

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
    console.log(`Starting visual audit capture for phase: [${PHASE}]...`);
    const browser = await chromium.launch({ channel: 'msedge', headless: true });

    // 1. ADMIN AUDIT
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();

    console.log('Logging in as Admin...');
    await adminPage.goto(`${BASE_URL}/admin/login`);
    await adminPage.fill('input[name="username"]', 'superadmin');
    await adminPage.fill('input[name="password"]', 'superadmin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'), { timeout: 10000 });
    console.log('Admin logged in.');

    const adminScreens = [
        { name: 'admin_student_registry', path: '/admin/student-registry' },
        { name: 'admin_timetable', path: '/admin/timetable' },
        { name: 'admin_students', path: '/admin/students' },
        { name: 'admin_attendance', path: '/admin/attendance' },
        { name: 'admin_scan_logs', path: '/admin/scan-logs' },
        { name: 'admin_examiners', path: '/admin/examiners' },
        { name: 'admin_payments', path: '/admin/payments' },
        { name: 'admin_activity', path: '/admin/activity' },
    ];

    for (const screen of adminScreens) {
        for (const vp of VIEWPORTS) {
            await adminPage.setViewportSize({ width: vp.width, height: vp.height });
            await adminPage.goto(`${BASE_URL}${screen.path}`, { waitUntil: 'networkidle' });
            await adminPage.waitForTimeout(300);

            const shotPath = path.join(OUTPUT_DIR, `${screen.name}_${vp.name}.png`);
            await adminPage.screenshot({ path: shotPath, fullPage: false });
            console.log(`Captured: ${screen.name} @ ${vp.name}`);
        }
    }

    // 2. EXAMINER AUDIT
    const examinerContext = await browser.newContext();
    const examinerPage = await examinerContext.newPage();

    console.log('Logging in as Examiner...');
    await examinerPage.goto(`${BASE_URL}/examiner/login`);
    await examinerPage.fill('input[name="username"]', 'examiner1');
    await examinerPage.fill('input[name="password"]', 'examiner123');
    await examinerPage.click('button[type="submit"]');
    await examinerPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'), { timeout: 10000 });
    console.log('Examiner logged in.');

    // Fetch CSC401 assessment id
    let csc401Id = 1;
    try {
        await examinerPage.goto(`${BASE_URL}/examiner/assessments`);
        const href = await examinerPage.getAttribute('a[href*="/examiner/assessments/"]', 'href');
        if (href) {
            const m = href.match(/\/examiner\/assessments\/(\d+)/);
            if (m) csc401Id = m[1];
        }
    } catch (e) {
        console.log('Could not determine assessment id dynamically, using 1');
    }

    const examinerScreens = [
        { name: 'examiner_assessment_workspace', path: `/examiner/assessments/${csc401Id}` },
        { name: 'examiner_today_exams', path: '/examiner/today-exams' },
        { name: 'examiner_scan_history', path: '/examiner/scan-history' },
    ];

    for (const screen of examinerScreens) {
        for (const vp of VIEWPORTS) {
            await examinerPage.setViewportSize({ width: vp.width, height: vp.height });
            await examinerPage.goto(`${BASE_URL}${screen.path}`, { waitUntil: 'networkidle' });
            await examinerPage.waitForTimeout(300);

            const shotPath = path.join(OUTPUT_DIR, `${screen.name}_${vp.name}.png`);
            await examinerPage.screenshot({ path: shotPath, fullPage: false });
            console.log(`Captured: ${screen.name} @ ${vp.name}`);
        }
    }

    // 3. STUDENT AUDIT
    const studentContext = await browser.newContext();
    const studentPage = await studentContext.newPage();

    console.log('Logging in as Student...');
    await studentPage.goto(`${BASE_URL}/student/login`);
    await studentPage.fill('#matric_no', '220404007');
    await studentPage.fill('#password', 'student123');
    await studentPage.click('#login-btn');
    await studentPage.waitForURL(url => url.pathname.includes('/student/dashboard'), { timeout: 10000 });
    console.log('Student logged in.');

    const studentScreens = [
        { name: 'student_timetable', path: '/student/timetable' },
        { name: 'student_profile', path: '/student/profile' },
        { name: 'student_dashboard', path: '/student/dashboard' },
    ];

    for (const screen of studentScreens) {
        for (const vp of VIEWPORTS) {
            await studentPage.setViewportSize({ width: vp.width, height: vp.height });
            await studentPage.goto(`${BASE_URL}${screen.path}`, { waitUntil: 'networkidle' });
            await studentPage.waitForTimeout(300);

            const shotPath = path.join(OUTPUT_DIR, `${screen.name}_${vp.name}.png`);
            await studentPage.screenshot({ path: shotPath, fullPage: false });
            console.log(`Captured: ${screen.name} @ ${vp.name}`);
        }
    }

    await browser.close();
    console.log(`Visual audit capture complete for [${PHASE}]! Files saved in: ${OUTPUT_DIR}`);
}

run().catch(err => {
    console.error('Capture failed:', err);
    process.exit(1);
});
