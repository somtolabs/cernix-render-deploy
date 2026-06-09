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
    ])->values();
    $sampleDepartments = $departments->values();
    $sampleLevels = ['100', '200', '300', '400'];
    $sampleRecords = collect(range(1, 14))->map(function ($number, $index) use ($sampleDepartments, $sampleLevels) {
        $department = $sampleDepartments->isNotEmpty()
            ? $sampleDepartments[$index % $sampleDepartments->count()]
            : null;
        $studentNumber = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $level = $sampleLevels[$index % count($sampleLevels)];
        $faculty = $department->faculty ?? \App\Support\DepartmentFees::FACULTY;
        $departmentName = $department->dept_name ?? 'Department not configured';
        $matric = $department
            ? MatricNumber::generate($level, $faculty, $departmentName, $studentNumber)
            : 'Unavailable';

        return [
            'faculty' => $faculty,
            'department_id' => $department->dept_id ?? null,
            'department' => $departmentName,
            'level' => $level,
            'student_number' => $studentNumber,
            'matric' => $matric,
            'name' => MatricNumber::demoName($studentNumber),
        ];
    });
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
    .demo-helper { margin-top:16px; border:1px solid rgba(15,32,80,.12); border-radius:16px; background:rgba(235,241,255,.34); overflow:hidden; }
    .demo-helper summary { min-height:48px; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:0 14px; color:var(--navy); font-size:13px; font-weight:900; }
    .demo-helper summary::after { content:"+"; color:var(--ink-3); font-family:'JetBrains Mono', ui-monospace, monospace; }
    .demo-helper[open] summary::after { content:"-"; }
    .demo-body { padding:0 14px 14px; }
    .demo-body p { margin:0 0 12px; color:var(--ink-3); font-size:13px; line-height:1.5; }
    .demo-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
    .demo-record { min-width:0; display:grid; gap:10px; align-content:space-between; padding:12px; border:1px solid var(--line); border-radius:14px; background:rgba(255,255,255,.88); word-break:normal; writing-mode:horizontal-tb; }
    .demo-record-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; min-width:0; }
    .demo-record-copy { min-width:0; }
    .demo-record b { display:block; color:var(--ink); font-size:13px; line-height:1.35; white-space:normal; word-break:normal; }
    .demo-record span { display:block; margin-top:3px; color:var(--ink-3); font-size:12px; line-height:1.45; white-space:normal; word-break:normal; }
    .demo-number { flex:0 0 auto; display:inline-flex !important; align-items:center; justify-content:center; min-width:42px; min-height:28px; margin:0 !important; border-radius:999px; background:rgba(15,32,80,.08); color:var(--navy) !important; font-weight:900; }
    .demo-record button { min-height:34px; padding:0 10px; border-radius:11px; border:1px solid var(--line); background:#fff; color:var(--navy); font-size:12px; font-weight:900; transition:transform .16s ease, box-shadow .16s ease; }
    .demo-record button:hover { transform:translateY(-1px); box-shadow:var(--shadow-sm); }
    .sr-alert { margin-top:12px; padding:10px 12px; border:1px solid #f1d189; border-radius:10px; background:#fff8e5; color:#7c4a13; font-size:13px; line-height:1.45; }
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
        .sr-preview { display:grid; }
        .demo-list { grid-template-columns:1fr; }
        .demo-record button { width:100%; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation:none !important; transition:none !important; }
    }
</style>

<main class="student-register">
    <header class="sr-top">
        <div class="sr-brand">
            <img src="{{ $brandingLogoUrl }}" alt="CERNIX branding">
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
                <h1>Register your student profile</h1>
                <p>Select your faculty, department, and level, then enter the last three matric digits. Payment verification happens separately after registration.</p>
                <div class="sr-status">
                    <span class="sr-chip">{{ ($session->semester ?? 'No active semester') }} {{ $session->academic_year ?? '' }}</span>
                    <span class="sr-chip">Faculty of Computing</span>
                </div>
                @if(! $session)
                    <div class="sr-alert" role="alert">No active exam session is currently open. An admin must activate an exam session.</div>
                @endif
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
                                <option value="{{ $department->dept_id }}">{{ $department->dept_name }}</option>
                            @endforeach
                        </select>
                        <div class="sr-hint" id="departmentHelp">
                            @if($departments->isEmpty())
                                No departments are configured. Ask an admin to seed or create departments.
                            @else
                                Choose the department for your exam registration.
                            @endif
                        </div>
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

                @if(\App\Support\DepartmentFees::isDemoMode())
                    <details class="demo-helper">
                        <summary>Need demo credentials?</summary>
                        <div class="demo-body">
                            <p>For demo/testing, choose a sample student number from 001 to 014. Payment is completed later from Generate Exam Pass.</p>
                            <div class="demo-list">
                                @foreach($sampleRecords as $sample)
                                    <div class="demo-record">
                                        <div class="demo-record-head">
                                            <div class="demo-record-copy">
                                                <b>{{ $sample['name'] }}</b>
                                                <span>{{ $sample['department'] }} · {{ $sample['level'] }} Level</span>
                                                <span class="mono">{{ $sample['matric'] }}</span>
                                            </div>
                                            <span class="demo-number mono">{{ $sample['student_number'] }}</span>
                                        </div>
                                        <button type="button" data-demo-sample data-faculty="{{ $sample['faculty'] }}" data-dept="{{ $sample['department_id'] }}" data-level="{{ $sample['level'] }}" data-student-number="{{ $sample['student_number'] }}">Use this sample</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endif

                <button class="btn btn-primary btn-block sr-submit" type="submit" id="submit-btn" @disabled(! $session)>Open my Exam Dashboard</button>
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
    const matricPreview = document.getElementById('matricPreview');
    const matricPreviewHelp = document.getElementById('matricPreviewHelp');
    const departments = @json($departmentMeta);
    const levelCodes = @json(MatricNumber::LEVEL_YEAR_CODES);

    const normalize = (value) => String(value || '').trim().toLowerCase();

    function updateDepartmentOptions() {
        const selectedDepartment = departmentSelect.value;
        const faculty = normalize(facultySelect.value);
        let matches = departments.filter((department) => !faculty || normalize(department.faculty) === faculty);

        if (!matches.length) matches = departments;

        departmentSelect.replaceChildren(new Option('Select department', ''));
        matches.forEach((department) => {
            departmentSelect.add(new Option(department.name, department.id));
        });

        if (matches.some((department) => department.id === selectedDepartment)) {
            departmentSelect.value = selectedDepartment;
        }

        departmentSelect.disabled = matches.length === 0;
        document.getElementById('departmentHelp').textContent = matches.length
            ? 'Choose the department for your exam registration.'
            : 'No departments are configured. Ask an admin to seed or create departments.';
        updateMatricPreview();
    }

    function updateFee() {
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
        message.textContent = 'Registering your profile...';
        message.classList.add('show');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Opening dashboard...';

        try {
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
        } catch (error) {
            message.textContent = 'Registration could not reach the server. Check your connection and try again.';
        }

        submitBtn.disabled = false;
        submitBtn.textContent = 'Open my Exam Dashboard';
    });
</script>
@endpush
