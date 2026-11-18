import { chromium } from '@playwright/test';

async function main() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const page = await browser.newPage();
    
    // Login as Super Admin
    await page.goto('http://127.0.0.1:8000/admin/login');
    await page.fill('input[name="username"]', 'superadmin');
    await page.fill('input[name="password"]', 'superadmin123');
    await page.click('button[type="submit"]');
    await page.waitForURL(url => url.pathname.includes('/admin/dashboard'));

    const viewports = [
        { name: '320px', width: 320, height: 650 },
        { name: '375px', width: 375, height: 667 },
    ];

    for (const vp of viewports) {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        console.log(`\n=== CHECKING SCHEDULING AT ${vp.name} ===`);

        // Test 1: Timetable type=test (scheduling form with enrollment cards)
        await page.goto('http://127.0.0.1:8000/admin/timetable?type=test', { waitUntil: 'networkidle' });
        
        // Expand the schedule panel if collapsed
        const isCollapsed = await page.evaluate(() => {
            const panel = document.getElementById('createSchedulePanel');
            return panel && panel.classList.contains('collapsed');
        });
        if (isCollapsed) {
            await page.click('#createPanelToggle');
            await page.waitForTimeout(400);
        }

        // Click CSV Roster radio to show file uploader
        const csvRadio = await page.$('#modeCsvRadio');
        if (csvRadio) {
            await csvRadio.click();
            await page.waitForTimeout(300);
        }

        const overflowElements = await page.evaluate((maxWidth) => {
            const elements = document.querySelectorAll('*');
            const overflowing = [];
            for (const el of elements) {
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') continue;
                const rect = el.getBoundingClientRect();
                if (rect.right > maxWidth + 1) {
                    let parent = el.parentElement;
                    let isEnclosed = false;
                    while (parent && parent !== document.body && parent !== document.documentElement) {
                        const ps = window.getComputedStyle(parent);
                        if (ps.overflowX === 'auto' || ps.overflowX === 'scroll' || ps.overflowX === 'hidden') {
                            if (parent.getBoundingClientRect().right <= maxWidth + 1) {
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
                        className: el.className ? String(el.className).substring(0, 60) : null,
                        right: Math.round(rect.right),
                        width: Math.round(rect.width),
                        overflow: Math.round(rect.right - maxWidth),
                        snippet: el.innerText ? el.innerText.substring(0, 30).replace(/\n/g, ' ') : null,
                    });
                }
            }
            return {
                docScrollWidth: document.documentElement.scrollWidth,
                bodyScrollWidth: document.body.scrollWidth,
                count: overflowing.length,
                samples: overflowing.slice(0, 10),
            };
        }, vp.width);

        console.log(`Test scheduling view overflow at ${vp.name}:`, overflowElements);

        // Test 2: Edit entry (with candidate roster)
        await page.goto('http://127.0.0.1:8000/admin/timetable?type=exam&edit=1', { waitUntil: 'networkidle' });
        const editOverflow = await page.evaluate((maxWidth) => {
            const elements = document.querySelectorAll('*');
            const overflowing = [];
            for (const el of elements) {
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') continue;
                const rect = el.getBoundingClientRect();
                if (rect.right > maxWidth + 1) {
                    let parent = el.parentElement;
                    let isEnclosed = false;
                    while (parent && parent !== document.body && parent !== document.documentElement) {
                        const ps = window.getComputedStyle(parent);
                        if (ps.overflowX === 'auto' || ps.overflowX === 'scroll' || ps.overflowX === 'hidden') {
                            if (parent.getBoundingClientRect().right <= maxWidth + 1) {
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
                        className: el.className ? String(el.className).substring(0, 60) : null,
                        right: Math.round(rect.right),
                        width: Math.round(rect.width),
                        overflow: Math.round(rect.right - maxWidth),
                        snippet: el.innerText ? el.innerText.substring(0, 30).replace(/\n/g, ' ') : null,
                    });
                }
            }
            return {
                docScrollWidth: document.documentElement.scrollWidth,
                bodyScrollWidth: document.body.scrollWidth,
                count: overflowing.length,
                samples: overflowing.slice(0, 10),
            };
        }, vp.width);
        console.log(`Edit entry view overflow at ${vp.name}:`, editOverflow);
    }

    await browser.close();
}

main().catch(console.error);
