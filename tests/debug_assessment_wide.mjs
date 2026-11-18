import { chromium } from '@playwright/test';

async function main() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    
    // 1. Examiner Assessment Workspace at 320px
    const examinerPage = await browser.newPage();
    await examinerPage.setViewportSize({ width: 320, height: 650 });
    await examinerPage.goto('http://127.0.0.1:8000/examiner/login');
    await examinerPage.fill('input[name="username"]', 'examiner1');
    await examinerPage.fill('input[name="password"]', 'examiner123');
    await examinerPage.click('button[type="submit"]');
    await examinerPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'));
    await examinerPage.goto('http://127.0.0.1:8000/examiner/assessments/1');

    const examinerWide = await examinerPage.evaluate(() => {
        return Array.from(document.querySelectorAll('*'))
            .filter(el => {
                const s = window.getComputedStyle(el);
                return s.display !== 'none' && el.offsetWidth > 320;
            })
            .map(el => ({
                tag: el.tagName,
                id: el.id,
                cls: typeof el.className === 'string' ? el.className.substring(0, 60) : '',
                ow: el.offsetWidth,
                sw: el.scrollWidth,
                cw: el.clientWidth,
                rectRight: Math.round(el.getBoundingClientRect().right)
            }));
    });
    console.log('EXAMINER WORKSPACE WIDE COUNT:', examinerWide.length);
    if (examinerWide.length > 0) {
        console.log('Sample Examiner Wide:', examinerWide.slice(0, 8));
    }

    // 2. Admin Timetable at 320px
    const adminPage = await browser.newPage();
    await adminPage.setViewportSize({ width: 320, height: 650 });
    await adminPage.goto('http://127.0.0.1:8000/admin/login');
    await adminPage.fill('input[name="username"]', 'superadmin');
    await adminPage.fill('input[name="password"]', 'superadmin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'));
    await adminPage.goto('http://127.0.0.1:8000/admin/timetable?type=exam');

    const adminWide = await adminPage.evaluate(() => {
        return Array.from(document.querySelectorAll('*'))
            .filter(el => {
                const s = window.getComputedStyle(el);
                return s.display !== 'none' && el.offsetWidth > 320;
            })
            .map(el => ({
                tag: el.tagName,
                id: el.id,
                cls: typeof el.className === 'string' ? el.className.substring(0, 60) : '',
                ow: el.offsetWidth,
                sw: el.scrollWidth,
                cw: el.clientWidth,
                rectRight: Math.round(el.getBoundingClientRect().right)
            }));
    });
    console.log('ADMIN TIMETABLE WIDE COUNT:', adminWide.length);
    if (adminWide.length > 0) {
        console.log('Sample Admin Timetable Wide:', adminWide.slice(0, 8));
    }

    await browser.close();
}

main().catch(console.error);
