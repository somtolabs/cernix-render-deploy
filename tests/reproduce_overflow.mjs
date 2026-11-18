import { chromium } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const BASE_URL = 'http://127.0.0.1:8000';
const VIEWPORTS = [
    { name: '320px', width: 320, height: 650 },
    { name: '375px', width: 375, height: 667 },
    { name: '390px', width: 390, height: 844 },
];

const PHASE = process.argv[2] || 'before';
const OUTPUT_DIR = path.join(process.cwd(), 'tests', 'screenshots', PHASE);

if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

const CSV_PATH = path.join(process.cwd(), 'tests', 'fixtures', 'test_registry_import.csv');

async function findOverflowingElements(page, maxViewportWidth) {
    return await page.evaluate((maxWidth) => {
        const elements = document.querySelectorAll('*');
        const overflowing = [];
        for (const el of elements) {
            // Ignore hidden elements
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') continue;
            
            const rect = el.getBoundingClientRect();
            // Check if element's right edge exceeds viewport width by more than 1px
            if (rect.right > maxWidth + 1) {
                // Check if element is enclosed within a scrollable container that itself fits inside maxWidth
                let parent = el.parentElement;
                let isEnclosedInScrollContainer = false;
                while (parent && parent !== document.body && parent !== document.documentElement) {
                    const parentStyle = window.getComputedStyle(parent);
                    const parentOverflowX = parentStyle.overflowX;
                    if (parentOverflowX === 'auto' || parentOverflowX === 'scroll' || parentOverflowX === 'hidden') {
                        const parentRect = parent.getBoundingClientRect();
                        if (parentRect.right <= maxWidth + 1) {
                            isEnclosedInScrollContainer = true;
                            break;
                        }
                    }
                    parent = parent.parentElement;
                }
                if (isEnclosedInScrollContainer) continue;

                overflowing.push({
                    tag: el.tagName.toLowerCase(),
                    id: el.id || null,
                    className: el.className ? String(el.className).substring(0, 80) : null,
                    rectRight: Math.round(rect.right),
                    rectWidth: Math.round(rect.width),
                    overflowAmount: Math.round(rect.right - maxWidth),
                    textSnippet: el.innerText ? el.innerText.substring(0, 40).replace(/\n/g, ' ') : null,
                });
            }
        }
        return overflowing;
    }, maxViewportWidth);
}

async function run() {
    console.log(`=== STARTING OVERFLOW REPRODUCTION AND AUDIT (${PHASE}) ===`);
    const browser = await chromium.launch({ channel: 'msedge', headless: true });

    // ─────────────────────────────────────────────────────────────
    // PART A: STUDENT REGISTRY IMPORT & MODALS
    // ─────────────────────────────────────────────────────────────
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();

    console.log('Logging in as Super Admin...');
    await adminPage.goto(`${BASE_URL}/admin/login`);
    await adminPage.fill('input[name="username"]', 'superadmin');
    await adminPage.fill('input[name="password"]', 'superadmin123');
    await adminPage.click('button[type="submit"]');
    await adminPage.waitForURL(url => url.pathname.includes('/admin/dashboard'));
    console.log('Super Admin logged in successfully.');

    for (const vp of VIEWPORTS) {
        console.log(`\n>>> Testing Student Registry Import at ${vp.name} (${vp.width}x${vp.height}) <<<`);
        await adminPage.setViewportSize({ width: vp.width, height: vp.height });
        await adminPage.goto(`${BASE_URL}/admin/student-registry`, { waitUntil: 'networkidle' });
        await adminPage.waitForTimeout(300);

        // 1. Step 1: File selection
        const step1Shot = path.join(OUTPUT_DIR, `import_step1_select_${vp.name}.png`);
        await adminPage.screenshot({ path: step1Shot, fullPage: false });
        console.log(`Captured: import_step1_select_${vp.name}`);

        const step1Overflow = await findOverflowingElements(adminPage, vp.width);
        if (step1Overflow.length > 0) {
            console.log(`  [OVERFLOW] Step 1 has ${step1Overflow.length} elements exceeding viewport:`, step1Overflow.slice(0, 3));
        }

        // 2. Upload test CSV
        console.log('  Uploading test CSV...');
        const fileInput = await adminPage.$('#registryCsvInput');
        if (fileInput) {
            await fileInput.setInputFiles(CSV_PATH);
            await adminPage.waitForTimeout(1000); // Allow FileReader to process client preview

            // Check if Step 2 is active
            await adminPage.waitForSelector('#importStep2', { state: 'visible', timeout: 5000 });
            console.log('  Step 2 reached.');

            // 3. Step 2: Validate & Preview
            const step2PreviewShot = path.join(OUTPUT_DIR, `import_step2_preview_${vp.name}.png`);
            await adminPage.screenshot({ path: step2PreviewShot, fullPage: false });
            console.log(`Captured: import_step2_preview_${vp.name}`);

            // 4. Run Server Diagnostic
            console.log('  Triggering server diagnostic for Category A-F duplicate & conflict analysis...');
            const diagBtn = await adminPage.$('#runDiagnosticBtn');
            if (diagBtn) {
                await diagBtn.click();
                await adminPage.waitForSelector('#diagnosticResults', { state: 'visible', timeout: 10000 });
                await adminPage.waitForTimeout(500);

                // Scroll to Category A-F cards
                await adminPage.evaluate(() => {
                    const el = document.getElementById('diagnosticResults');
                    if (el) el.scrollIntoView();
                });
                await adminPage.waitForTimeout(300);

                const step2CategoriesShot = path.join(OUTPUT_DIR, `import_step2_categories_${vp.name}.png`);
                await adminPage.screenshot({ path: step2CategoriesShot, fullPage: false });
                console.log(`Captured: import_step2_categories_${vp.name}`);

                // Check conflict container
                const conflictContainer = await adminPage.$('#diagConflictContainer');
                if (conflictContainer && await conflictContainer.isVisible()) {
                    console.log('  Identity Conflict container is visible!');
                    await adminPage.evaluate(() => {
                        const el = document.getElementById('diagConflictContainer');
                        if (el) el.scrollIntoView();
                    });
                    await adminPage.waitForTimeout(300);

                    const step2ConflictShot = path.join(OUTPUT_DIR, `import_step2_conflict_${vp.name}.png`);
                    await adminPage.screenshot({ path: step2ConflictShot, fullPage: false });
                    console.log(`Captured: import_step2_conflict_${vp.name}`);

                    const conflictOverflow = await findOverflowingElements(adminPage, vp.width);
                    console.log(`  [OVERFLOW] Conflict step has ${conflictOverflow.length} elements exceeding viewport:`, conflictOverflow.slice(0, 5));
                }
            }

            // 5. Continue to Step 3: Confirmation
            const step3Btn = await adminPage.$('#importToStep3');
            if (step3Btn && await step3Btn.isEnabled()) {
                await step3Btn.click();
                await adminPage.waitForSelector('#importStep3', { state: 'visible', timeout: 5000 });
                await adminPage.waitForTimeout(300);

                const step3Shot = path.join(OUTPUT_DIR, `import_step3_confirm_${vp.name}.png`);
                await adminPage.screenshot({ path: step3Shot, fullPage: false });
                console.log(`Captured: import_step3_confirm_${vp.name}`);

                const step3Overflow = await findOverflowingElements(adminPage, vp.width);
                if (step3Overflow.length > 0) {
                    console.log(`  [OVERFLOW] Step 3 has ${step3Overflow.length} elements exceeding viewport:`, step3Overflow.slice(0, 3));
                }
            }
        }

        // 6. Test Modals: Clear Unlinked Modal
        console.log('  Testing Clear Unlinked modal...');
        await adminPage.evaluate(() => window.openRegistryModal && window.openRegistryModal('clearUnlinkedModal'));
        await adminPage.waitForTimeout(500);
        const clearUnlinkedShot = path.join(OUTPUT_DIR, `modal_clear_unlinked_${vp.name}.png`);
        await adminPage.screenshot({ path: clearUnlinkedShot, fullPage: false });
        console.log(`Captured: modal_clear_unlinked_${vp.name}`);

        const modalUnlinkedOverflow = await findOverflowingElements(adminPage, vp.width);
        if (modalUnlinkedOverflow.length > 0) {
            console.log(`  [OVERFLOW] Clear Unlinked modal has ${modalUnlinkedOverflow.length} elements exceeding viewport:`, modalUnlinkedOverflow.slice(0, 3));
        }
        await adminPage.evaluate(() => window.closeRegistryModal && window.closeRegistryModal('clearUnlinkedModal'));
        await adminPage.waitForTimeout(300);

        // 7. Test Modals: Full Review Modal
        console.log('  Testing Full Review modal...');
        await adminPage.evaluate(() => window.openRegistryModal && window.openRegistryModal('fullReviewModal'));
        await adminPage.waitForTimeout(500);
        const fullReviewShot = path.join(OUTPUT_DIR, `modal_full_review_${vp.name}.png`);
        await adminPage.screenshot({ path: fullReviewShot, fullPage: false });
        console.log(`Captured: modal_full_review_${vp.name}`);
        await adminPage.evaluate(() => window.closeRegistryModal && window.closeRegistryModal('fullReviewModal'));
        await adminPage.waitForTimeout(300);

        // 8. Test Modals: Manual Register Modal
        console.log('  Testing Manual Register modal...');
        await adminPage.evaluate(() => window.openRegistryModal && window.openRegistryModal('manualRegisterModal'));
        await adminPage.waitForTimeout(500);
        const manualRegShot = path.join(OUTPUT_DIR, `modal_manual_register_${vp.name}.png`);
        await adminPage.screenshot({ path: manualRegShot, fullPage: false });
        console.log(`Captured: modal_manual_register_${vp.name}`);
        await adminPage.evaluate(() => window.closeRegistryModal && window.closeRegistryModal('manualRegisterModal'));
        await adminPage.waitForTimeout(300);
    }

    // ─────────────────────────────────────────────────────────────
    // PART B: ASSESSMENT LIST & WORKSPACE VIEWS
    // ─────────────────────────────────────────────────────────────
    console.log('\n--- AUDITING ASSESSMENT LIST & WORKSPACE VIEWS ---');
    const examinerContext = await browser.newContext();
    const examinerPage = await examinerContext.newPage();

    console.log('Logging in as Examiner...');
    await examinerPage.goto(`${BASE_URL}/examiner/login`);
    await examinerPage.fill('input[name="username"]', 'examiner1');
    await examinerPage.fill('input[name="password"]', 'examiner123');
    await examinerPage.click('button[type="submit"]');
    await examinerPage.waitForURL(url => url.pathname.includes('/examiner/dashboard'));
    console.log('Examiner logged in.');

    // Fetch assessment ID (CSC401)
    let assessmentId = 1;
    try {
        await examinerPage.goto(`${BASE_URL}/examiner/assessments`);
        const href = await examinerPage.getAttribute('a[href*="/examiner/assessments/"]', 'href');
        if (href) {
            const m = href.match(/\/examiner\/assessments\/(\d+)/);
            if (m) assessmentId = m[1];
        }
    } catch (_) {}

    for (const vp of VIEWPORTS) {
        console.log(`\n>>> Testing Assessment Views at ${vp.name} (${vp.width}x${vp.height}) <<<`);
        
        // 1. Examiner Assessment Workspace
        await examinerPage.setViewportSize({ width: vp.width, height: vp.height });
        await examinerPage.goto(`${BASE_URL}/examiner/assessments/${assessmentId}`, { waitUntil: 'networkidle' });
        await examinerPage.waitForTimeout(300);

        const workspaceShot = path.join(OUTPUT_DIR, `assessment_workspace_${vp.name}.png`);
        await examinerPage.screenshot({ path: workspaceShot, fullPage: false });
        console.log(`Captured: assessment_workspace_${vp.name}`);

        const wsOverflow = await findOverflowingElements(examinerPage, vp.width);
        if (wsOverflow.length > 0) {
            console.log(`  [OVERFLOW] Examiner Workspace has ${wsOverflow.length} elements exceeding viewport:`, wsOverflow.slice(0, 3));
        }

        // Test Assisted Check-in Modal in Workspace
        const assistedBtn = await examinerPage.$('#openAssistedBtn');
        if (assistedBtn) {
            await assistedBtn.click();
            await examinerPage.waitForTimeout(400);
            const assistedModalShot = path.join(OUTPUT_DIR, `assessment_assisted_modal_${vp.name}.png`);
            await examinerPage.screenshot({ path: assistedModalShot, fullPage: false });
            console.log(`Captured: assessment_assisted_modal_${vp.name}`);
            
            const assistedOverflow = await findOverflowingElements(examinerPage, vp.width);
            if (assistedOverflow.length > 0) {
                console.log(`  [OVERFLOW] Assisted check-in modal has ${assistedOverflow.length} elements exceeding viewport:`, assistedOverflow.slice(0, 3));
            }
            const closeBtn = await examinerPage.$('#closeAssistedModal');
            if (closeBtn) await closeBtn.click();
            await examinerPage.waitForTimeout(200);
        }

        // 2. Admin Assessment List / Timetable
        await adminPage.setViewportSize({ width: vp.width, height: vp.height });
        await adminPage.goto(`${BASE_URL}/admin/timetable?type=exam`, { waitUntil: 'networkidle' });
        await adminPage.waitForTimeout(300);

        const adminTimetableShot = path.join(OUTPUT_DIR, `assessment_admin_list_${vp.name}.png`);
        await adminPage.screenshot({ path: adminTimetableShot, fullPage: false });
        console.log(`Captured: assessment_admin_list_${vp.name}`);

        const ttOverflow = await findOverflowingElements(adminPage, vp.width);
        if (ttOverflow.length > 0) {
            console.log(`  [OVERFLOW] Admin Timetable has ${ttOverflow.length} elements exceeding viewport:`, ttOverflow.slice(0, 3));
        }

        // 3. Examiner Today's Exams List
        await examinerPage.goto(`${BASE_URL}/examiner/today-exams`, { waitUntil: 'networkidle' });
        await examinerPage.waitForTimeout(300);

        const todayExamsShot = path.join(OUTPUT_DIR, `assessment_examiner_today_${vp.name}.png`);
        await examinerPage.screenshot({ path: todayExamsShot, fullPage: false });
        console.log(`Captured: assessment_examiner_today_${vp.name}`);

        const teOverflow = await findOverflowingElements(examinerPage, vp.width);
        if (teOverflow.length > 0) {
            console.log(`  [OVERFLOW] Examiner Today Exams has ${teOverflow.length} elements exceeding viewport:`, teOverflow.slice(0, 3));
        }
    }

    await browser.close();
    console.log(`\n=== AUDIT COMPLETE FOR [${PHASE}] ===`);
}

run().catch(err => {
    console.error('Audit run error:', err);
    process.exit(1);
});
