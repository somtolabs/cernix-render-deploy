@extends('layouts.portal')

@section('title', 'Student Registration')

@section('content')
@php
    use App\Support\MatricNumber;

    $departmentMeta = $departments->map(fn ($department) => [
        'id' => (string) $department->dept_id,
        'name' => $department->dept_name,
        'faculty' => $department->faculty,
        'facultyCode' => $department->faculty_code ?? MatricNumber::FACULTY_CODES[$department->faculty] ?? '',
        'departmentCode' => $department->department_code ?? MatricNumber::DEPARTMENT_CODES[$department->dept_name] ?? '',
        'fee' => (float) ($feeMap[$department->dept_name] ?? 0),
    ])->values();
    $sampleRecords = [
        ['Faculty of Computing', 'Computer Science', '400', '008', '220404008', 'TEST-DEMO', 100000],
        ['Faculty of Computing', 'Software Engineering', '300', '001', '230405001', 'TEST-DEMO', 120000],
        ['Faculty of Computing', 'Data Science', '300', '010', '230408010', 'TEST-DEMO', 150000],
    ];
@endphp

<style>
    .student-register { min-height:100vh; background:var(--bg); color:var(--ink); }
    .sr-top { min-height:74px; display:flex; align-items:center; justify-content:space-between; gap:14px; padding:0 18px; border-bottom:1px solid var(--line); background:rgba(255,255,255,.88); backdrop-filter:blur(14px); }
    .sr-brand { display:flex; align-items:center; gap:12px; min-width:0; }
    .sr-brand img { width:46px; height:46px; object-fit:contain; flex:0 0 auto; }
    .sr-brand b { display:block; color:var(--navy); line-height:1.1; }
    .sr-brand span { display:block; margin-top:2px; color:var(--ink-3); font-size:12px; }
    .sr-back { min-height:38px; display:inline-flex; align-items:center; padding:0 12px; border:1px solid var(--line); border-radius:12px; background:#fff; color:var(--ink); text-decoration:none; font-size:12px; font-weight:900; transition:transform .16s ease, box-shadow .16s ease; }
    .sr-back:hover { transform:translateY(-1px); box-shadow:var(--shadow-sm); }
    .sr-shell { width:min(980px,100%); margin:0 auto; padding:30px 16px 60px; }
    .sr-panel { border:1px solid var(--line); border-radius:22px; background:#fff; box-shadow:var(--shadow-sm); overflow:hidden; animation:srIn .24s ease both; }
    .sr-panel-head { padding:22px 24px; border-bottom:1px solid var(--line); display:grid; gap:8px; }
    .sr-panel-head h1 { margin:0; font-size:clamp(28px,5vw,42px); line-height:1; letter-spacing:-.06em; color:var(--navy); }
    .sr-panel-head p { margin:0; max-width:720px; color:var(--ink-3); line-height:1.6; }
    .sr-status { display:flex; gap:8px; flex-wrap:wrap; margin-top:4px; }
    .sr-chip { display:inline-flex; width:fit-content; padding:5px 9px; border-radius:999px; background:rgba(15,32,80,.06); color:var(--ink-2); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .sr-body { padding:22px 24px 24px; }
    .sr-grid { display:grid; gap:16px; }
    .sr-grid.two { grid-template-columns:1fr; }
    .sr-field label { display:block; margin-bottom:7px; color:var(--ink); font-size:12px; font-weight:900; }
    .sr-field .input { min-height:48px; border-radius:13px; border:1px solid var(--line-2); background:#fff; transition:border-color .16s ease, box-shadow .16s ease; }
    .sr-field .input:focus { border-color:var(--navy); box-shadow:0 0 0 4px rgba(15,32,80,.08); }
    .sr-hint { margin-top:7px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .sr-preview { margin-top:16px; display:flex; align-items:center; justify-content:space-between; gap:14px; padding:14px 16px; border:1px solid rgba(15,32,80,.12); border-radius:15px; background:linear-gradient(180deg,#fff,#f8fafc); }
    .sr-preview span { display:block; color:var(--ink-4); font-size:10px; font-weight:900; letter-spacing:.13em; text-transform:uppercase; }
    .sr-preview small { display:block; margin-top:4px; color:var(--ink-3); }
    .sr-preview b { font-family:'JetBrains Mono', ui-monospace, monospace; color:var(--navy); font-size:clamp(21px,4vw,28px); white-space:nowrap; }
    .sr-fee { min-height:72px; display:flex; align-items:center; justify-content:space-between; gap:14px; padding:14px 16px; border:1px solid var(--line); border-radius:15px; background:var(--bg); }
    .sr-fee span { display:block; color:var(--ink-4); font-size:10px; font-weight:900; letter-spacing:.13em; text-transform:uppercase; }
    .sr-fee small { display:block; margin-top:4px; color:var(--ink-3); }
    .sr-fee b { font-family:'JetBrains Mono', ui-monospace, monospace; color:var(--navy); font-size:clamp(22px,4vw,30px); white-space:nowrap; }
    .demo-helper { margin-top:16px; border:1px solid var(--line); border-radius:16px; background:#fff; overflow:hidden; }
    .demo-helper summary { min-height:48px; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:0 14px; color:var(--navy); font-size:13px; font-weight:900; }
    .demo-helper summary::after { content:"+"; color:var(--ink-3); font-family:'JetBrains Mono', ui-monospace, monospace; }
    .demo-helper[open] summary::after { content:"-"; }
    .demo-body { padding:0 14px 14px; }
    .demo-body p { margin:0 0 12px; color:var(--ink-3); font-size:13px; line-height:1.5; }
    .demo-list { display:grid; gap:8px; }
    .demo-record { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:center; padding:10px 0; border-top:1px solid var(--line); }
    .demo-record b { display:block; color:var(--ink); font-size:13px; }
    .demo-record span { display:block; margin-top:3px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .demo-record button { min-height:34px; padding:0 10px; border-radius:11px; border:1px solid var(--line); background:#fff; color:var(--navy); font-size:12px; font-weight:900; transition:transform .16s ease, box-shadow .16s ease; }
    .demo-record button:hover { transform:translateY(-1px); box-shadow:var(--shadow-sm); }
    .sr-submit { margin-top:18px; min-height:50px; border-radius:14px; transition:transform .16s ease, box-shadow .16s ease; }
    .sr-submit:hover { transform:translateY(-1px); box-shadow:var(--shadow-sm); }
    #message { display:none; margin-top:14px; padding:12px 13px; border:1px dashed var(--line-2); border-radius:14px; background:var(--bg); color:var(--ink-3); font-size:13px; line-height:1.5; }
    #message.show { display:block; }
    @keyframes srIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    @media (min-width:760px) {
        .sr-grid.two { grid-template-columns:1fr 1fr; }
    }
    @media (max-width:560px) {
        .sr-brand span { display:none; }
        .sr-panel-head, .sr-body { padding:18px; }
        .sr-preview, .sr-fee, .demo-record { display:grid; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation:none !important; transition:none !important; }
    }
</style>

<main class="student-register">
    <header class="sr-top">
        <div class="sr-brand">
            <img src="/aaua-logo.png" alt="AAUA logo">
            <div>
                <b>CERNIX Student Registration</b>
                <span>Adekunle Ajasin University</span>
            </div>
        </div>
        <a class="sr-back" href="/">Back</a>
    </header>

    <section class="sr-shell">
        <form id="reg-form" class="sr-panel" method="POST" action="{{ route('student.register') }}">
            @csrf
            <div class="sr-panel-head">
                <div class="cx-eyebrow">Exam Access</div>
                <h1>Register for your exam pass</h1>
                <p>Select your faculty, department, and level, then enter only your last three matric digits. CERNIX builds the full matric number before verification.</p>
                <div class="sr-status">
                    <span class="sr-chip">{{ ($session->semester ?? 'No active semester') }} {{ $session->academic_year ?? '' }}</span>
                    <span class="sr-chip">Faculty of Computing</span>
                </div>
            </div>

            <div class="sr-body">
                <div class="sr-grid two">
                    <div class="sr-field">
                        <label for="faculty">Faculty</label>
                        <select id="faculty" name="faculty" class="input" required>
                            <option value="">Select faculty</option>
                            @foreach($faculties as $faculty)
                                <option value="{{ $faculty }}" @selected($loop->first)>{{ $faculty }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sr-field">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" class="input" required>
                            <option value="">Select department</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->dept_id }}" data-faculty="{{ $department->faculty }}" data-fee="{{ $feeMap[$department->dept_name] ?? 0 }}">{{ $department->dept_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="sr-grid two" style="margin-top:16px">
                    <div class="sr-field">
                        <label for="level">Level</label>
                        <select id="level" name="level" class="input" required>
                            <option value="">Select level</option>
                            @foreach(['100','200','300','400'] as $level)
                                <option value="{{ $level }}">{{ $level }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sr-field mono">
                        <label for="student_number">Student Number</label>
                        <input id="student_number" name="student_number" type="text" class="input" placeholder="008" inputmode="numeric" maxlength="3" pattern="\d{3}" autocomplete="off" required>
                        <div class="sr-hint">Enter the last 3 digits of your matric number, for example 001, 008, or 014.</div>
                    </div>
                </div>

                <div class="sr-preview" aria-live="polite">
                    <div>
                        <span>Generated Matric Number</span>
                        <small id="matricPreviewHelp">Complete the fields above to preview your matric.</small>
                    </div>
                    <b id="matricPreview">---------</b>
                </div>

                <div class="sr-grid two" style="margin-top:16px">
                    <div class="sr-fee" aria-live="polite">
                        <div>
                            <span>School Fee</span>
                            <small id="feeDepartment">Select department</small>
                        </div>
                        <b id="feeAmount">₦0</b>
                    </div>
                    <div class="sr-field mono">
                        <label for="rrr_number">Remita RRR</label>
                        <input id="rrr_number" name="rrr_number" type="text" class="input" placeholder="TEST-DEMO" autocomplete="off" required>
                        <div class="sr-hint">Use your real Remita RRR in production.</div>
                    </div>
                </div>

                @if(\App\Support\DepartmentFees::isDemoMode())
                    <details class="demo-helper">
                        <summary>Need demo credentials?</summary>
                        <div class="demo-body">
                            <p>For demo/testing, enter a student number from 001 to 014. The system generates the full matric number automatically. Any RRR starting with TEST- will confirm demo registration.</p>
                            <div class="demo-list">
                                @foreach($sampleRecords as [$faculty, $dept, $level, $studentNumber, $matric, $rrr, $fee])
                                    @php $deptId = optional($departments->firstWhere('dept_name', $dept))->dept_id; @endphp
                                    <div class="demo-record">
                                        <div>
                                            <b>{{ $level }} Level · {{ $dept }}</b>
                                            <span>No: <span class="mono">{{ $studentNumber }}</span> · Generated: <span class="mono">{{ $matric }}</span> · RRR: <span class="mono">{{ $rrr }}</span> · Fee: ₦{{ number_format($fee, 0) }}</span>
                                        </div>
                                        <button type="button" data-demo-sample data-faculty="{{ $faculty }}" data-dept="{{ $deptId }}" data-level="{{ $level }}" data-student-number="{{ $studentNumber }}" data-rrr="{{ $rrr }}">Use this sample</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endif

                <button class="btn btn-primary btn-block sr-submit" type="submit" id="submit-btn">Open my Exam Dashboard</button>
                <div id="message"></div>
            </div>
        </form>
    </section>
</main>
@endsection

@push('scripts')
<script>
    const form = document.getElementById('reg-form');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submit-btn');
    const facultySelect = document.getElementById('faculty');
    const departmentSelect = document.getElementById('department_id');
    const levelSelect = document.getElementById('level');
    const studentNumberInput = document.getElementById('student_number');
    const feeAmount = document.getElementById('feeAmount');
    const feeDepartment = document.getElementById('feeDepartment');
    const matricPreview = document.getElementById('matricPreview');
    const matricPreviewHelp = document.getElementById('matricPreviewHelp');
    const departments = @json($departmentMeta);
    const levelCodes = @json(MatricNumber::LEVEL_YEAR_CODES);

    const formatNaira = (value) => new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

    function updateDepartmentOptions() {
        const faculty = facultySelect.value;
        [...departmentSelect.options].forEach((option) => {
            if (!option.value) return;
            option.hidden = option.dataset.faculty !== faculty;
        });
        const selected = departmentSelect.selectedOptions[0];
        if (selected && selected.hidden) departmentSelect.value = '';
        updateFee();
    }

    function updateFee() {
        const selected = departments.find((item) => item.id === departmentSelect.value);
        feeAmount.textContent = formatNaira(selected ? selected.fee : 0);
        feeDepartment.textContent = selected ? selected.name : 'Select department';
        updateMatricPreview();
    }

    function generatedMatric() {
        const selected = departments.find((item) => item.id === departmentSelect.value);
        const yearCode = levelCodes[levelSelect.value] || '';
        const facultyCode = selected ? selected.facultyCode : '';
        const departmentCode = selected ? selected.departmentCode : '';
        const studentNumber = studentNumberInput.value.trim();

        if (!yearCode || !facultyCode || !departmentCode || !/^\d{3}$/.test(studentNumber)) {
            return '';
        }

        return `${yearCode}${facultyCode}${departmentCode}${studentNumber}`;
    }

    function updateMatricPreview() {
        const matric = generatedMatric();
        matricPreview.textContent = matric || '---------';
        matricPreviewHelp.textContent = matric ? 'This matric number will be verified against the demo SIS.' : 'Complete the fields above to preview your matric.';
    }

    document.querySelectorAll('[data-demo-sample]').forEach((button) => {
        button.addEventListener('click', () => {
            facultySelect.value = button.dataset.faculty || '';
            updateDepartmentOptions();
            departmentSelect.value = button.dataset.dept || '';
            levelSelect.value = button.dataset.level || '';
            studentNumberInput.value = button.dataset.studentNumber || '';
            document.getElementById('rrr_number').value = button.dataset.rrr || '';
            updateFee();
            updateMatricPreview();
        });
    });

    facultySelect.addEventListener('change', updateDepartmentOptions);
    departmentSelect.addEventListener('change', updateFee);
    levelSelect.addEventListener('change', updateMatricPreview);
    studentNumberInput.addEventListener('input', () => {
        studentNumberInput.value = studentNumberInput.value.replace(/\D/g, '').slice(0, 3);
        updateMatricPreview();
    });
    updateDepartmentOptions();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        message.textContent = 'Verifying registration...';
        message.classList.add('show');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Opening dashboard...';

        const response = await fetch('/student/register', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(Object.fromEntries(new FormData(form))),
        });

        const result = await response.json().catch(() => ({ success: false, message: 'Registration failed.' }));
        if (response.ok && result.success && result.redirect_url) {
            window.location.href = result.redirect_url;
            return;
        }

        message.textContent = result.message || 'Registration failed. Check your details and try again.';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Open my Exam Dashboard';
    });
</script>
@endpush
