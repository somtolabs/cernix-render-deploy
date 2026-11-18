import { chromium } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8000';
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

async function scanStudentPortal() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    // Login as student
    await page.goto(`${BASE_URL}/student/login`);
    await page.fill('#matric_no', '220404007');
    await page.fill('#password', 'student123');
    await page.click('#login-btn');
    await page.waitForURL(url => url.pathname.includes('/student/dashboard'), { timeout: 10000 });
    console.log('Student logged in successfully!');

    const studentUrls = [
        { path: '/student/dashboard', name: 'Dashboard' },
        { path: '/student/profile', name: 'Profile' },
        { path: '/student/timetable', name: 'Timetable (Exams)' },
        { path: '/student/timetable?type=test', name: 'Timetable (Tests)' },
        { path: '/student/timetable?type=makeup', name: 'Timetable (Make-ups)' },
        { path: '/student/generate-exam-pass', name: 'Generate Pass' },
        { path: '/student/exam-access-id', name: 'Digital ID Card' },
        { path: '/student/payment', name: 'Payment Clearance' },
        { path: '/student/instructions', name: 'Instructions' },
        { path: '/student/notifications', name: 'Notifications' },
    ];

    console.log('=== CLIPPING & SPILL SCAN FOR STUDENT PAGES ===');
    for (const item of studentUrls) {
        for (const vp of VIEWPORTS) {
            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.goto(`${BASE_URL}${item.path}`, { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(100);

            const check = await page.evaluate((vpWidth) => {
                const docWidth = document.documentElement.clientWidth;
                const scrollWidth = Math.max(document.documentElement.scrollWidth, document.body.scrollWidth);
                const results = [];

                const elements = document.body.querySelectorAll('*');
                for (const el of elements) {
                    if (['SCRIPT', 'STYLE', 'SVG', 'PATH', 'THEAD', 'TBODY', 'TR'].includes(el.tagName)) continue;
                    const r = el.getBoundingClientRect();
                    if (r.width === 0 || r.height === 0) continue;

                    if (r.right > vpWidth + 1.5) {
                        let p = el.parentElement;
                        let isScroller = false;
                        while (p && p !== document.body) {
                            const cName = String(p.className || '');
                            const style = window.getComputedStyle(p);
                            if ((cName.includes('table-wrap') || cName.includes('scroll') || cName.includes('segmented')) &&
                                (style.overflowX === 'auto' || style.overflowX === 'scroll')) {
                                isScroller = true;
                                break;
                            }
                            p = p.parentElement;
                        }
                        if (!isScroller) {
                            results.push({
                                tag: el.tagName.toLowerCase(),
                                className: String(el.className || '').slice(0, 50),
                                right: Math.round(r.right),
                                width: Math.round(r.width),
                                text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40)
                            });
                        }
                    }
                }
                return {
                    docWidth,
                    scrollWidth,
                    spills: results.slice(0, 5)
                };
            }, vp.width);

            if (check.scrollWidth > vp.width || check.spills.length > 0) {
                console.log(`[SPILL] Student ${item.name} at ${vp.width}px: scrollWidth=${check.scrollWidth}px, spills=${check.spills.length}`);
                for (const s of check.spills) {
                    console.log(`   -> <${s.tag} class="${s.className}"> right=${s.right}px (+${s.right - vp.width}px): "${s.text}"`);
                }
            } else {
                console.log(`[PASS] Student ${item.name} at ${vp.width}px`);
            }
        }
    }

    await browser.close();
}

scanStudentPortal().catch(e => console.error(e));
