import { chromium } from '@playwright/test';

const VIEWPORTS = [
    { name: '320px', width: 320, height: 600 },
    { name: '360px', width: 360, height: 740 },
    { name: '375px', width: 375, height: 667 },
    { name: '390px', width: 390, height: 844 },
    { name: '430px', width: 430, height: 932 },
    { name: '768px', width: 768, height: 1024 },
    { name: '1024px', width: 1024, height: 768 },
    { name: '1280px', width: 1280, height: 800 },
];

const BASE_URL = 'http://127.0.0.1:8000';

async function checkOverflow(page) {
    return await page.evaluate(() => {
        const docWidth = document.documentElement.clientWidth;
        const scrollWidth = Math.max(
            document.documentElement.scrollWidth,
            document.body ? document.body.scrollWidth : 0
        );
        const hasHorizontalScroll = scrollWidth > docWidth;

        // Find all elements that overflow the viewport boundary
        const overflowingElements = [];
        const all = document.querySelectorAll('*');
        for (const el of all) {
            if (['SCRIPT', 'STYLE', 'HEAD', 'META', 'TITLE'].includes(el.tagName)) continue;
            const rect = el.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) continue;
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') continue;

            if (rect.right > docWidth + 1.5) {
                let parent = el.parentElement;
                let insideScroller = false;
                while (parent && parent !== document.body && parent !== document.documentElement) {
                    const pStyle = window.getComputedStyle(parent);
                    if (pStyle.overflowX === 'auto' || pStyle.overflowX === 'scroll' || pStyle.overflowX === 'hidden') {
                        insideScroller = true;
                        break;
                    }
                    parent = parent.parentElement;
                }

                if (!insideScroller) {
                    overflowingElements.push({
                        tag: el.tagName.toLowerCase(),
                        id: el.id || '',
                        className: (typeof el.className === 'string' ? el.className : '').trim().slice(0, 80),
                        rect: {
                            left: Math.round(rect.left),
                            right: Math.round(rect.right),
                            width: Math.round(rect.width)
                        },
                        excess: Math.round(rect.right - docWidth),
                        textSnippet: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 60)
                    });
                }
            }
        }

        return {
            docWidth,
            scrollWidth,
            hasHorizontalScroll,
            overflowDelta: Math.max(0, scrollWidth - docWidth),
            overflowingElements: overflowingElements.slice(0, 10)
        };
    });
}

async function runAudit() {
    console.log('Starting Playwright UI Overflow Audit...');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const results = {};

    // 1. Super Admin Tests
    console.log('\n=== AUDITING SUPER ADMIN PORTAL ===');
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    
    await adminPage.goto(`${BASE_URL}/admin/login`);
    await adminPage.fill('input[name="username"]', 'superadmin');
    await adminPage.fill('input[name="password"]', 'superadmin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'), { timeout: 10000 });
    console.log('Super Admin logged in successfully.');

    const adminUrls = [
        { path: '/admin/dashboard', name: 'Super Admin Dashboard' },
        { path: '/admin/student-registry', name: 'Student Registry' },
        { path: '/admin/timetable', name: 'Timetable (Exams)' },
        { path: '/admin/timetable?type=test', name: 'Timetable (Tests)' },
        { path: '/admin/timetable?type=makeup', name: 'Timetable (Make-ups)' },
        { path: '/admin/photo-approvals', name: 'Photo Approvals' },
        { path: '/admin/profile-photo-change-requests', name: 'Photo Change Requests' },
        { path: '/admin/students', name: 'Student Accounts' },
        { path: '/admin/examiners', name: 'Examiner Management' },
        { path: '/admin/payments', name: 'Payment Records' },
        { path: '/admin/scan-logs', name: 'Verification Scan Logs' },
        { path: '/admin/attendance', name: 'Attendance Registry' },
        { path: '/admin/settings', name: 'Settings & Danger Zone' },
        { path: '/admin/intelligence', name: 'Risk Intelligence' },
        { path: '/admin/activity', name: 'System Audit Logs' },
        { path: '/admin/notes', name: 'Administrative Notes' }
    ];

    for (const item of adminUrls) {
        results[item.name] = {};
        for (const vp of VIEWPORTS) {
            await adminPage.setViewportSize({ width: vp.width, height: vp.height });
            await adminPage.goto(`${BASE_URL}${item.path}`, { waitUntil: 'domcontentloaded' });
            await adminPage.waitForTimeout(100);
            const check = await checkOverflow(adminPage);
            results[item.name][vp.width] = check;
            if (check.hasHorizontalScroll || check.overflowingElements.length > 0) {
                console.log(`[OVERFLOW] ${item.name} at ${vp.width}px: scrollWidth=${check.scrollWidth} (docWidth=${check.docWidth}, +${check.overflowDelta}px), elements=${check.overflowingElements.length}`);
                for (const el of check.overflowingElements) {
                    console.log(`   -> <${el.tag} class="${el.className}" id="${el.id}"> right=${el.rect.right}px (+${el.excess}px): "${el.textSnippet}"`);
                }
            } else {
                console.log(`[PASS] ${item.name} at ${vp.width}px`);
            }
        }
    }

    // 2. Examiner Tests
    console.log('\n=== AUDITING EXAMINER PORTAL ===');
    const exContext = await browser.newContext();
    const exPage = await exContext.newPage();
    
    await exPage.goto(`${BASE_URL}/examiner/login`);
    await exPage.fill('input[name="username"]', 'examiner1');
    await exPage.fill('input[name="password"]', 'password123');
    await exPage.click('button[type="submit"]');
    await exPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'), { timeout: 10000 });
    console.log('Examiner logged in successfully.');

    const examinerUrls = [
        { path: '/examiner/dashboard', name: 'Examiner Live Scanner' },
        { path: '/examiner/today-exams', name: "Today's Exams" },
        { path: '/examiner/assessments', name: 'Assessment Workspace List' },
        { path: '/examiner/scan-history', name: 'Scan History' },
        { path: '/examiner/audit-trail', name: 'Examiner Audit Trail' },
        { path: '/examiner/metrics', name: 'Examiner Metrics' },
        { path: '/examiner/notifications', name: 'Examiner Notifications' }
    ];

    for (const item of examinerUrls) {
        results[item.name] = {};
        for (const vp of VIEWPORTS) {
            await exPage.setViewportSize({ width: vp.width, height: vp.height });
            await exPage.goto(`${BASE_URL}${item.path}`, { waitUntil: 'domcontentloaded' });
            await exPage.waitForTimeout(100);
            const check = await checkOverflow(exPage);
            results[item.name][vp.width] = check;
            if (check.hasHorizontalScroll || check.overflowingElements.length > 0) {
                console.log(`[OVERFLOW] ${item.name} at ${vp.width}px: scrollWidth=${check.scrollWidth} (docWidth=${check.docWidth}, +${check.overflowDelta}px), elements=${check.overflowingElements.length}`);
                for (const el of check.overflowingElements) {
                    console.log(`   -> <${el.tag} class="${el.className}" id="${el.id}"> right=${el.rect.right}px (+${el.excess}px): "${el.textSnippet}"`);
                }
            } else {
                console.log(`[PASS] ${item.name} at ${vp.width}px`);
            }
        }
    }

    // 3. Public / Onboarding Tests
    console.log('\n=== AUDITING STUDENT PORTAL & ONBOARDING ===');
    const pubContext = await browser.newContext();
    const pubPage = await pubContext.newPage();

    const publicUrls = [
        { path: '/student/register', name: 'Student Matric Lookup / Registration' },
        { path: '/student/login', name: 'Student Login' },
        { path: '/health', name: 'Health Check' }
    ];

    for (const item of publicUrls) {
        results[item.name] = {};
        for (const vp of VIEWPORTS) {
            await pubPage.setViewportSize({ width: vp.width, height: vp.height });
            await pubPage.goto(`${BASE_URL}${item.path}`, { waitUntil: 'domcontentloaded' });
            await pubPage.waitForTimeout(100);
            const check = await checkOverflow(pubPage);
            results[item.name][vp.width] = check;
            if (check.hasHorizontalScroll || check.overflowingElements.length > 0) {
                console.log(`[OVERFLOW] ${item.name} at ${vp.width}px: scrollWidth=${check.scrollWidth} (docWidth=${check.docWidth}, +${check.overflowDelta}px), elements=${check.overflowingElements.length}`);
                for (const el of check.overflowingElements) {
                    console.log(`   -> <${el.tag} class="${el.className}" id="${el.id}"> right=${el.rect.right}px (+${el.excess}px): "${el.textSnippet}"`);
                }
            } else {
                console.log(`[PASS] ${item.name} at ${vp.width}px`);
            }
        }
    }

    await browser.close();
    console.log('\nAudit complete!');
}

runAudit().catch(err => {
    console.error('Audit failed with error:', err);
});
