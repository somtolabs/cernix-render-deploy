import { chromium } from '@playwright/test';

async function test() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    page.on('response', async res => {
        console.log('RES:', res.status(), res.url());
        if (res.status() >= 300 && res.status() < 400) {
            console.log('  Location:', res.headers()['location']);
        }
    });

    await page.goto('http://127.0.0.1:8000/student/login');
    await page.fill('#matric_no', '220404007');
    await page.fill('#password', 'student123');
    await page.click('#login-btn');
    await page.waitForTimeout(4000);
    console.log('Final URL:', page.url());
    await browser.close();
}

test().catch(e => console.error(e));
