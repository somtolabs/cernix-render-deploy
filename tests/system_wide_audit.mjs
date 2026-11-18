import { chromium } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';
const VIEWPORTS = [
    { name: '320px', width: 320, height: 650 },
    { name: '375px', width: 375, height: 667 },
    { name: '390px', width: 390, height: 844 },
    { name: '768px', width: 768, height: 1024 },
    { name: '1024px', width: 1024, height: 768 },
    { name: '1280px', width: 1280, height: 800 },
];

async function checkPageOverflow(page, url, name, maxWidth) {
    try {
        await page.goto(`${BASE_URL}${url}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(300);

        const result = await page.evaluate((maxW) => {
            const doc = document.documentElement;
            const body = document.body;
            const elements = document.querySelectorAll('*');
            const overflowing = [];

            for (const el of elements) {
                const s = window.getComputedStyle(el);
                if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') continue;
                const rect = el.getBoundingClientRect();
                if (rect.right > maxW + 1) {
                    let parent = el.parentElement;
                    let isEnclosed = false;
                    while (parent && parent !== body && parent !== doc) {
                        const ps = window.getComputedStyle(parent);
                        if (ps.overflowX === 'auto' || ps.overflowX === 'scroll' || ps.overflowX === 'hidden') {
                            if (parent.getBoundingClientRect().right <= maxW + 1) {
                                isEnclosed = true;
                                break;
                            }
                        }
                        parent = parent.parentElement;
                    }
                    if (isEnclosed) continue;

                    overflowing.push({
                        tag: el.tagName.toLowerCase(),
                        id: el.id || null,
                        cls: el.className ? String(el.className).substring(0, 50) : null,
                        right: Math.round(rect.right),
                        width: Math.round(rect.width),
                        overflow: Math.round(rect.right - maxW),
                        snippet: el.innerText ? el.innerText.substring(0, 30).replace(/\n/g, ' ') : null,
                    });
                }
            }

            return {
                docScrollWidth: doc.scrollWidth,
                bodyScrollWidth: body.scrollWidth,
                hasPageScroll: doc.scrollWidth > maxW || body.scrollWidth > maxW,
                overflowingCount: overflowing.length,
                samples: overflowing.slice(0, 5),
            };
        }, maxWidth);

        return { url, name, ...result };
    } catch (err) {
        return { url, name, error: err.message };
    }
}

async function main() {
    console.log('=== RUNNING COMPREHENSIVE SYSTEM-WIDE RESPONSIVENESS AUDIT ===');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });

    for (const vp of VIEWPORTS) {
        console.log(`\n================ VIEWPORT: ${vp.name} (${vp.width}x${vp.height}) ================`);

        // 1. SUPER ADMIN PAGES
        console.log('\n--- 1. SUPER ADMIN INTERFACE ---');
        const adminPage = await browser.newPage();
        await adminPage.setViewportSize({ width: vp.width, height: vp.height });
        await adminPage.goto(`${BASE_URL}/admin/login`);
        await adminPage.fill('input[name="username"]', 'superadmin');
        await adminPage.fill('input[name="password"]', 'superadmin123');
        await adminPage.click('button[type="submit"]');
        await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'));

        const adminRoutes = [
            { url: '/admin/dashboard', name: 'Super Admin Dashboard' },
            { url: '/admin/settings', name: 'Settings & Branding' },
            { url: '/admin/student-registry', name: 'Student Registry' },
            { url: '/admin/students', name: 'Student Directory' },
            { url: '/admin/examiners', name: 'Examiners Management' },
            { url: '/admin/payments', name: 'Payments & Revenue' },
            { url: '/admin/attendance', name: 'Attendance Records' },
            { url: '/admin/scan-logs', name: 'Verification Scan Logs' },
            { url: '/admin/activity', name: 'Audit & Activity Log' },
            { url: '/admin/timetable?type=exam', name: 'Timetable Exams' },
            { url: '/admin/timetable?type=test', name: 'Timetable Tests' },
            { url: '/admin/timetable?type=makeup', name: 'Timetable Make-ups' },
            { url: '/admin/timetable?type=exam&edit=1', name: 'Timetable Edit Exam Roster' },
        ];

        for (const route of adminRoutes) {
            const res = await checkPageOverflow(adminPage, route.url, route.name, vp.width);
            const status = (res.hasPageScroll || res.overflowingCount > 0) ? 'FAIL' : 'PASS';
            console.log(`  [${status}] ${res.name} (${res.url}) - docScroll: ${res.docScrollWidth}px, overflowing: ${res.overflowingCount}`);
            if (res.overflowingCount > 0) {
                console.log('    Samples:', res.samples);
            }
        }
        await adminPage.close();

        // 2. EXAMINER PAGES
        console.log('\n--- 2. EXAMINER INTERFACE ---');
        const examinerPage = await browser.newPage();
        await examinerPage.setViewportSize({ width: vp.width, height: vp.height });
        await examinerPage.goto(`${BASE_URL}/examiner/login`);
        await examinerPage.fill('input[name="username"]', 'examiner1');
        await examinerPage.fill('input[name="password"]', 'examiner123');
        await examinerPage.click('button[type="submit"]');
        await examinerPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'));

        const examinerRoutes = [
            { url: '/examiner/dashboard', name: 'Examiner Dashboard' },
            { url: '/examiner/assessments', name: 'Examiner Assessments List' },
            { url: '/examiner/assessments/1', name: 'Examiner Assessment Workspace' },
            { url: '/examiner/today-exams', name: 'Examiner Today Exams' },
            { url: '/examiner/scan-history', name: 'Examiner Scan History' },
        ];

        for (const route of examinerRoutes) {
            const res = await checkPageOverflow(examinerPage, route.url, route.name, vp.width);
            const status = (res.hasPageScroll || res.overflowingCount > 0) ? 'FAIL' : 'PASS';
            console.log(`  [${status}] ${res.name} (${res.url}) - docScroll: ${res.docScrollWidth}px, overflowing: ${res.overflowingCount}`);
            if (res.overflowingCount > 0) {
                console.log('    Samples:', res.samples);
            }
        }
        await examinerPage.close();

        // 3. STUDENT PORTAL PAGES
        console.log('\n--- 3. STUDENT PORTAL INTERFACE ---');
        const studentPage = await browser.newPage();
        await studentPage.setViewportSize({ width: vp.width, height: vp.height });
        await studentPage.goto(`${BASE_URL}/student/login`);
        await studentPage.fill('input[name="matric_no"]', '220404001');
        await studentPage.fill('input[name="password"]', 'student123');
        await studentPage.click('#login-btn');
        await studentPage.waitForURL(url => url.pathname.includes('/student/dashboard'), { timeout: 8000 }).catch(() => {});

        const studentRoutes = [
            { url: '/student/dashboard', name: 'Student Dashboard' },
            { url: '/student/timetable', name: 'Student Timetable' },
            { url: '/student/exam-pass', name: 'Student Exam Pass' },
            { url: '/student/generate-exam-pass', name: 'Generate Exam Pass' },
            { url: '/student/profile', name: 'Student Profile' },
            { url: '/student/payment', name: 'Student Payment' },
            { url: '/student/instructions', name: 'Student Instructions' },
            { url: '/student/notifications', name: 'Student Notifications' },
        ];

        for (const route of studentRoutes) {
            const res = await checkPageOverflow(studentPage, route.url, route.name, vp.width);
            const status = (res.hasPageScroll || res.overflowingCount > 0) ? 'FAIL' : 'PASS';
            console.log(`  [${status}] ${res.name} (${res.url}) - docScroll: ${res.docScrollWidth}px, overflowing: ${res.overflowingCount}`);
            if (res.overflowingCount > 0) {
                console.log('    Samples:', res.samples);
            }
        }
        await studentPage.close();
    }

    await browser.close();
    console.log('\n=== AUDIT COMPLETE ===');
}

main().catch(console.error);
