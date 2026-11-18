import { chromium } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';

async function scanAllPagesForClipping() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();

    await adminPage.goto(`${BASE_URL}/admin/login`);
    await adminPage.fill('input[name="username"]', 'superadmin');
    await adminPage.fill('input[name="password"]', 'superadmin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'));

    const adminUrls = [
        { path: '/admin/dashboard', name: 'Dashboard' },
        { path: '/admin/student-registry', name: 'Student Registry' },
        { path: '/admin/timetable', name: 'Timetable (Exams)' },
        { path: '/admin/timetable?type=test', name: 'Timetable (Tests)' },
        { path: '/admin/timetable?type=makeup', name: 'Timetable (Make-ups)' },
        { path: '/admin/photo-approvals', name: 'Photo Approvals' },
        { path: '/admin/profile-photo-change-requests', name: 'Photo Change Requests' },
        { path: '/admin/students', name: 'Student Accounts' },
        { path: '/admin/examiners', name: 'Examiners' },
        { path: '/admin/payments', name: 'Payments' },
        { path: '/admin/scan-logs', name: 'Scan Logs' },
        { path: '/admin/attendance', name: 'Attendance' },
        { path: '/admin/settings', name: 'Settings' },
        { path: '/admin/intelligence', name: 'Intelligence' },
        { path: '/admin/activity', name: 'Audit Logs' },
        { path: '/admin/notes', name: 'Notes' }
    ];

    console.log('=== CLIPPING SCAN AT 320px (Content Area = 288px) ===');
    await adminPage.setViewportSize({ width: 320, height: 700 });

    for (const item of adminUrls) {
        await adminPage.goto(`${BASE_URL}${item.path}`, { waitUntil: 'domcontentloaded' });
        await adminPage.waitForTimeout(100);

        const clipped = await adminPage.evaluate(() => {
            const main = document.querySelector('.admin-main') || document.body;
            const mainRect = main.getBoundingClientRect();
            const results = [];

            const elements = main.querySelectorAll('*');
            for (const el of elements) {
                if (['SCRIPT', 'STYLE', 'SVG', 'PATH', 'THEAD', 'TBODY', 'TR'].includes(el.tagName)) continue;
                const r = el.getBoundingClientRect();
                if (r.width === 0 || r.height === 0) continue;

                // Check if element spills beyond 320px viewport
                if (r.right > 320.5) {
                    // Check if inside a designated table horizontal scroller
                    let p = el.parentElement;
                    let isScroller = false;
                    while (p && p !== main) {
                        const cName = String(p.className || '');
                        const style = window.getComputedStyle(p);
                        if ((cName.includes('table-wrap') || cName.includes('scroll') || cName.includes('preview') || cName.includes('st-nav')) &&
                            (style.overflowX === 'auto' || style.overflowX === 'scroll')) {
                            isScroller = true;
                            break;
                        }
                        p = p.parentElement;
                    }
                    if (!isScroller) {
                        results.push({
                            tag: el.tagName.toLowerCase(),
                            className: (el.className || '').toString().slice(0, 50),
                            right: Math.round(r.right),
                            width: Math.round(r.width),
                            text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40)
                        });
                    }
                }
            }
            return {
                mainWidth: Math.round(mainRect.width),
                mainScrollWidth: main.scrollWidth,
                spillingCount: results.length,
                spillSamples: results.slice(0, 5)
            };
        });

        if (clipped.mainScrollWidth > 320 || clipped.spillingCount > 0) {
            console.log(`[SPILL] ${item.name}: mainScrollWidth=${clipped.mainScrollWidth}px (spilling elements: ${clipped.spillingCount})`);
            for (const s of clipped.spillSamples) {
                console.log(`   -> <${s.tag} class="${s.className}"> right=${s.right}px width=${s.width}px: "${s.text}"`);
            }
        } else {
            console.log(`[CLEAN] ${item.name} fits within 320px`);
        }
    }

    // Now scan Examiner Pages at 320px
    console.log('\n=== CLIPPING SCAN FOR EXAMINER PAGES AT 320px ===');
    const exContext = await browser.newContext();
    const exPage = await exContext.newPage();
    await exPage.goto(`${BASE_URL}/examiner/login`);
    await exPage.fill('input[name="username"]', 'examiner1');
    await exPage.fill('input[name="password"]', 'password123');
    await exPage.click('button[type="submit"]');
    await exPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'));
    await exPage.setViewportSize({ width: 320, height: 700 });

    const examinerUrls = [
        { path: '/examiner/dashboard', name: 'Dashboard' },
        { path: '/examiner/today-exams', name: "Today's Exams" },
        { path: '/examiner/assessments', name: 'Assessments' },
        { path: '/examiner/scan-history', name: 'Scan History' },
        { path: '/examiner/audit-trail', name: 'Audit Trail' },
        { path: '/examiner/metrics', name: 'Metrics' },
        { path: '/examiner/notifications', name: 'Notifications' }
    ];

    for (const item of examinerUrls) {
        await exPage.goto(`${BASE_URL}${item.path}`, { waitUntil: 'domcontentloaded' });
        await exPage.waitForTimeout(100);

        const clipped = await exPage.evaluate(() => {
            const main = document.querySelector('.ex-main') || document.body;
            const mainRect = main.getBoundingClientRect();
            const results = [];

            const elements = main.querySelectorAll('*');
            for (const el of elements) {
                if (['SCRIPT', 'STYLE', 'SVG', 'PATH', 'THEAD', 'TBODY', 'TR'].includes(el.tagName)) continue;
                const r = el.getBoundingClientRect();
                if (r.width === 0 || r.height === 0) continue;

                if (r.right > 320.5) {
                    let p = el.parentElement;
                    let isScroller = false;
                    while (p && p !== main) {
                        const cName = String(p.className || '');
                        const style = window.getComputedStyle(p);
                        if ((cName.includes('table-wrap') || cName.includes('scroll')) &&
                            (style.overflowX === 'auto' || style.overflowX === 'scroll')) {
                            isScroller = true;
                            break;
                        }
                        p = p.parentElement;
                    }
                    if (!isScroller) {
                        results.push({
                            tag: el.tagName.toLowerCase(),
                            className: (el.className || '').toString().slice(0, 50),
                            right: Math.round(r.right),
                            width: Math.round(r.width),
                            text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40)
                        });
                    }
                }
            }
            return {
                mainWidth: Math.round(mainRect.width),
                mainScrollWidth: main.scrollWidth,
                spillingCount: results.length,
                spillSamples: results.slice(0, 5)
            };
        });

        if (clipped.mainScrollWidth > 320 || clipped.spillingCount > 0) {
            console.log(`[SPILL] Examiner ${item.name}: mainScrollWidth=${clipped.mainScrollWidth}px (spilling elements: ${clipped.spillingCount})`);
            for (const s of clipped.spillSamples) {
                console.log(`   -> <${s.tag} class="${s.className}"> right=${s.right}px width=${s.width}px: "${s.text}"`);
            }
        } else {
            console.log(`[CLEAN] Examiner ${item.name} fits within 320px`);
        }
    }

    await browser.close();
}

scanAllPagesForClipping().catch(e => console.error(e));
