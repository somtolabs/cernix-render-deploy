<?php $__env->startSection('admin-title', 'Payment Records'); ?>

<?php $__env->startSection('admin-content'); ?>
<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Payment Records</div>
        <h1>Payments</h1>
        <p>Verified payment records with student identity, amount confirmed, pass status, and review links.</p>
    </div>
</div>

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Verified Payments</h2>
        <span><?php echo e($payments->total()); ?> records</span>
    </div>
    <div class="admin-section-body">
        <form class="admin-filter" method="GET">
            <input name="q" value="<?php echo e(request('q')); ?>" placeholder="Search student name or matric">
            <select name="department_id">
                <option value="">All departments</option>
                <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $department): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($department->dept_id); ?>" <?php if((string) request('department_id') === (string) $department->dept_id): echo 'selected'; endif; ?>><?php echo e($department->dept_name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <input name="date_from" value="<?php echo e(request('date_from')); ?>" type="date" title="From date">
            <input name="date_to"   value="<?php echo e(request('date_to')); ?>"   type="date" title="To date">
            <button class="admin-action" type="submit">Apply</button>
            <?php if(request()->hasAny(['q','department_id','date_from','date_to'])): ?>
                <a class="admin-action ghost" href="<?php echo e(route('admin.payments')); ?>">Reset</a>
            <?php endif; ?>
        </form>

<style>
    .pay-list { display:grid; gap:8px; }
    .pay-row {
        display:grid;
        grid-template-columns:44px 1fr auto auto;
        align-items:center;
        gap:14px;
        padding:14px 16px;
        background:#fff;
        border:1px solid var(--line);
        border-radius:12px;
        box-shadow:0 1px 3px rgba(0,0,0,.05);
        transition:box-shadow .14s;
    }
    .pay-row:hover { box-shadow:0 4px 12px rgba(0,0,0,.09); }
    .pay-avatar {
        width:44px; height:44px; border-radius:50%; flex-shrink:0;
        background:rgba(5,150,105,.1); color:var(--emerald);
        display:grid; place-items:center;
        font-size:15px; font-weight:900;
    }
    .pay-amount { font-size:16px; font-weight:900; color:var(--navy); font-family:'JetBrains Mono',monospace; white-space:nowrap; flex-shrink:0; }
    @media (max-width:640px) {
        .pay-row { grid-template-columns:40px 1fr; gap:10px; }
        .pay-amount { grid-column:1/-1; padding-top:4px; border-top:1px solid var(--line); font-size:14px; }
    }
</style>

        <div class="pay-list">
            <?php $__empty_1 = true; $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $initials   = strtoupper(substr($payment->full_name ?? 'S', 0, 1));
                    $passStatus = match(strtoupper((string) ($payment->token_status ?? ''))) {
                        'UNUSED'  => ['Generated', 'green'],
                        'USED'    => ['Used',       'neutral'],
                        'REVOKED' => ['Revoked',    'red'],
                        default   => ['Not issued', 'neutral'],
                    };
                ?>
                <div class="pay-row">
                    <div class="pay-avatar"><?php echo e($initials); ?></div>
                    <div style="min-width:0">
                        <div style="font-size:14px;font-weight:900;color:var(--ink)"><?php echo e($payment->full_name ?? 'Student unavailable'); ?></div>
                        <div style="font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--ink-3);margin-top:1px"><?php echo e($payment->student_id); ?></div>
                        <div style="font-size:12px;color:var(--ink-2);margin-top:3px">
                            <?php echo e($payment->dept_name ?? 'Dept not available'); ?><?php if($payment->level): ?> &middot; <?php echo e($payment->level); ?> Level@endif
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:6px;flex-wrap:wrap">
                            <span class="admin-status green">Verified</span>
                            <span class="admin-status <?php echo e($passStatus[1]); ?>">Pass: <?php echo e($passStatus[0]); ?></span>
                            <?php if($payment->verified_at): ?>
                                <span style="font-size:11px;color:var(--ink-4);font-family:'JetBrains Mono',monospace"><?php echo e($payment->verified_at); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="pay-amount">&#x20A6;<?php echo e(number_format((float) $payment->amount_confirmed, 2)); ?></div>
                    <div>
                        <a class="admin-action ghost" href="<?php echo e(route('admin.payments.student.show', $payment->student_id)); ?>" style="font-size:12px;min-height:32px;padding:0 12px">View</a>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="admin-empty" style="text-align:center;padding:40px 20px">
                    <div style="font-size:14px;font-weight:700;color:var(--ink-2);margin-bottom:6px">No payment records found</div>
                    <div style="font-size:13px;color:var(--ink-3)">Adjust your filters or check back when students have made payments.</div>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top:14px"><?php echo e($payments->links()); ?></div>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin-control', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>