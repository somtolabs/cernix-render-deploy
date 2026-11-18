import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const BASE_URL = 'http://127.0.0.1:8000';
const DESKTOP_VIEWPORTS = [
    { name: '768px', width: 768, height: 1024 },
    { name: '1024px', width: 1024, height: 768 },
    { name: '1280px', width: 1280, height: 800 },
];

const OUTPUT_DIR = path.join(process.cwd(), 'tests', 'screenshots', 'desktop_regression');
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

async function run() {
    console.log('=== STARTING DESKTOP REGRESSION VERIFICATION (768px, 1024px, 1280px) ===');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });

    // Super Admin Session
    const adminPage = await browser.newPage();
    await adminPage.goto(`${BASE_URL}/admin/login`);
    await adminPage.fill('input[name="username"]', 'superadmin');
    await adminPage.fill('input[name="password"]', 'superadmin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'));

    // Examiner Session
    const examinerPage = await browser.newPage();
    await examinerPage.goto(`${BASE_URL}/examiner/login`);
    await examinerPage.fill('input[name="username"]', 'examiner1');
    await examinerPage.fill('input[name="password"]', 'examiner123');
    await examinerPage.click('button[type="submit"]');
    await examinerPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'));

    for (const vp of DESKTOP_VIEWPORTS) {
        console.log(`\nTesting Desktop at ${vp.name} (${vp.width}x${vp.height})...`);
        
        // 1. Student Registry Import & Conflict Resolution
        await adminPage.setViewportSize({ width: vp.width, height: vp.height });
        await adminPage.goto(`${BASE_URL}/admin/student-registry`, { waitUntil: 'networkidle' });
        await adminPage.setInputFiles('#registryCsvInput', 'tests/fixtures/test_registry_import.csv');
        await adminPage.waitForTimeout(500);
        await adminPage.click('#runDiagnosticBtn');
        await adminPage.waitForSelector('#diagnosticResults', { state: 'visible' });
        await adminPage.waitForTimeout(500);

        const registryDesktopCheck = await adminPage.evaluate(() => {
            const tableWrap = document.querySelector('.diag-conflict-table-wrap');
            const cards = document.querySelector('.diag-conflict-cards');
            const doc = document.documentElement;
            return {
                docScrollWidth: doc.scrollWidth,
                tableWrapDisplay: tableWrap ? window.getComputedStyle(tableWrap).display : null,
                cardsDisplay: cards ? window.getComputedStyle(cards).display : null,
            };
        });
        console.log(`  Registry at ${vp.name}:`, registryDesktopCheck);

        await adminPage.screenshot({ path: path.join(OUTPUT_DIR, `registry_desktop_${vp.name}.png`), fullPage: false });

        // 2. Examiner Assessment Workspace
        await examinerPage.setViewportSize({ width: vp.width, height: vp.height });
        await examinerPage.goto(`${BASE_URL}/examiner/assessments/1`, { waitUntil: 'networkidle' });
        await examinerPage.waitForTimeout(300);

        const workspaceDesktopCheck = await examinerPage.evaluate(() => {
            const table = document.querySelector('.aws-table-wrap.aws-roster-table');
            const cards = document.querySelector('.aws-roster-cards');
            const doc = document.documentElement;
            return {
                docScrollWidth: doc.scrollWidth,
                tableDisplay: table ? window.getComputedStyle(table).display : null,
                cardsDisplay: cards ? window.getComputedStyle(cards).display : null,
            };
        });
        console.log(`  Examiner Workspace at ${vp.name}:`, workspaceDesktopCheck);
        await examinerPage.screenshot({ path: path.join(OUTPUT_DIR, `workspace_desktop_${vp.name}.png`), fullPage: false });

        // 3. Admin Timetable / Assessment Roster
        await adminPage.goto(`${BASE_URL}/admin/timetable?type=exam`, { waitUntil: 'networkidle' });
        await adminPage.waitForTimeout(300);

        const timetableDesktopCheck = await adminPage.evaluate(() => {
            const table = document.querySelector('.tt-roster-table-wrap');
            const cards = document.querySelector('.tt-roster-cards');
            const doc = document.documentElement;
            return {
                docScrollWidth: doc.scrollWidth,
                tableDisplay: table ? window.getComputedStyle(table).display : null,
                cardsDisplay: cards ? window.getComputedStyle(cards).display : null,
            };
        });
        console.log(`  Admin Timetable at ${vp.name}:`, timetableDesktopCheck);
        await adminPage.screenshot({ path: path.join(OUTPUT_DIR, `timetable_desktop_${vp.name}.png`), fullPage: false });
    }

    await browser.close();
    console.log('\n=== DESKTOP REGRESSION VERIFICATION COMPLETE ===');
}

run().catch(console.error);
