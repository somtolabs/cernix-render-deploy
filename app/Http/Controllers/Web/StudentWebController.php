<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\RegistrationService;
use App\Support\MatricNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentWebController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    // Step 0: matric entry page
    public function index()
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        return view('student.register', compact('session'));
    }

    // AJAX: validate matric, check registry and existing account
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matric_no' => ['required', 'string', 'max:50'],
        ]);

        $raw = trim($data['matric_no']);

        try {
            $parts = MatricNumber::parseLiveFormat($raw);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $matric = $parts['matric_number'];

        if (! Schema::hasTable('official_students')) {
            return response()->json(['success' => false, 'message' => 'The student registry is not ready. Contact the exam office.'], 422);
        }

        $official = DB::table('official_students')->where('matric_number', $matric)->first();

        if (! $official) {
            return response()->json([
                'success' => false,
                'message' => 'This matric number was not found in the official student list. Contact the exam office if you believe this is an error.',
            ], 422);
        }

        if (strtolower((string) $official->status) !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This matric number is not currently active. Contact the exam office.',
            ], 422);
        }

        $existing = DB::table('students')->where('matric_no', $matric)->first();

        if ($existing) {
            $hasPassword = Schema::hasColumn('students', 'password') && ! empty($existing->password);

            if ($hasPassword) {
                return response()->json([
                    'success' => true,
                    'status'  => 'login_redirect',
                    'matric'  => $matric,
                    'message' => 'An account already exists for this matric number. Please log in.',
                ]);
            }

            // Account exists but has no password — direct to onboard to complete setup
            return response()->json([
                'success' => true,
                'status'  => 'onboard_setup',
                'matric'  => $matric,
                'message' => 'Your registration is incomplete. Please set a password to continue.',
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => 'proceed',
            'matric' => $matric,
            'identity' => [
                'full_name'  => $official->full_name,
                'department' => $official->department,
                'faculty'    => $official->faculty,
                'level'      => $official->level,
                'programme'  => $official->programme ?? null,
            ],
        ]);
    }

    // Step 1+2: identity confirm + password + ID card + selfie
    public function onboard(Request $request)
    {
        $matric = trim((string) $request->query('matric', ''));

        if ($matric === '') {
            return redirect()->route('student.register')->with('status', 'Please enter your matric number to begin.');
        }

        try {
            $parts = MatricNumber::parseLiveFormat($matric);
            $matric = $parts['matric_number'];
        } catch (\InvalidArgumentException) {
            return redirect()->route('student.register')->withErrors(['matric_no' => 'Invalid matric number format.']);
        }

        $official = Schema::hasTable('official_students')
            ? DB::table('official_students')->where('matric_number', $matric)->where('status', 'active')->first()
            : null;

        if (! $official) {
            return redirect()->route('student.register')->withErrors(['matric_no' => 'Matric number not found in official registry.']);
        }

        $existing = DB::table('students')->where('matric_no', $matric)->first();

        if ($existing) {
            $hasPassword = Schema::hasColumn('students', 'password') && ! empty($existing->password);
            if ($hasPassword) {
                return redirect()->route('student.login', ['matric' => $matric])
                    ->with('status', 'An account already exists for this matric number. Please log in.');
            }
            // Account exists but has no password — allow through to complete setup
        }

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        return view('student.onboard', [
            'matric'           => $matric,
            'official'         => $official,
            'session'          => $session,
            'completingSetup'  => $existing && ! (Schema::hasColumn('students', 'password') && ! empty($existing->password)),
        ]);
    }

    // Complete registration: password + ID card + selfie
    public function completeOnboarding(Request $request): JsonResponse|RedirectResponse
    {
        $idCardRule = $this->settingBoolean('require_id_card_upload', true) ? 'required' : 'nullable';
        $data = $request->validate([
            'matric_no'        => ['required', 'string', 'max:50'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
            'id_card'          => [$idCardRule, 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:5120'],
            'selfie'           => ['required', 'file', 'mimes:jpg,jpeg,png,heic,heif', 'max:4096'],
            'profile_photo'    => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:4096'],
        ]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        if (! $session) {
            $msg = 'No active exam session. Contact the exam office.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['session' => $msg]);
        }

        try {
            $parts  = MatricNumber::parseLiveFormat($data['matric_no']);
            $matric = $parts['matric_number'];

            $official = $this->assertOfficialStudentCanRegister($matric, $parts);

            $existingStudent = DB::table('students')->where('matric_no', $matric)->first();

            if ($existingStudent) {
                $hasPassword = Schema::hasColumn('students', 'password') && ! empty($existingStudent->password);

                if ($hasPassword) {
                    $msg = 'An account already exists for this matric number. Please log in.';

                    return $request->expectsJson()
                        ? response()->json(['success' => false, 'message' => $msg, 'redirect_url' => route('student.login', ['matric' => $matric])], 409)
                        : redirect()->route('student.login', ['matric' => $matric])->with('status', $msg);
                }

                // Account exists but has no password — complete the setup by updating the record
                $selfiePath       = $this->storePassportPhoto($request, $matric);
                $idCardPath       = $this->storeIdCard($request, $matric);
                $profilePhotoPath = $this->storeProfilePhoto($request, $matric);

                $updateData = ['updated_at' => now()];
                if (Schema::hasColumn('students', 'password')) {
                    $updateData['password'] = Hash::make($data['password']);
                }
                if (Schema::hasColumn('students', 'id_card_path')) {
                    $updateData['id_card_path'] = $idCardPath;
                }
                if (Schema::hasColumn('students', 'account_status')) {
                    $updateData['account_status'] = 'active';
                }
                if (Schema::hasColumn('students', 'photo_status')) {
                    $updateData['photo_path']   = $selfiePath;
                    $updateData['photo_status'] = 'pending_admin_approval';
                }
                if (Schema::hasColumn('students', 'profile_photo_path')) {
                    $updateData['profile_photo_path'] = $profilePhotoPath;
                }
                if (Schema::hasColumn('students', 'profile_photo_locked_at')) {
                    $updateData['profile_photo_locked_at'] = now();
                }

                DB::table('students')->where('matric_no', $matric)->update($updateData);

                $request->session()->put('student_matric_no', $matric);
                $request->session()->put('student_session_id', (int) $session->session_id);

                app(AuditService::class)->logAction($matric, 'student', 'student.setup_completed', [
                    'session_id' => $session->session_id,
                ]);

                app(AuditService::class)->logAction($matric, 'student', 'student.profile_photo_locked', [
                    'session_id' => $session->session_id,
                    'profile_photo_path' => $profilePhotoPath,
                ]);

                $redirectUrl = route('student.dashboard');

                return $request->expectsJson()
                    ? response()->json(['success' => true, 'redirect_url' => $redirectUrl])
                    : redirect($redirectUrl);
            }

            $selfiePath       = $this->storePassportPhoto($request, $matric);
            $idCardPath       = $this->storeIdCard($request, $matric);
            $profilePhotoPath = $this->storeProfilePhoto($request, $matric);

            $result = $this->registrationService->registerStudent([
                'matric_no'      => $matric,
                'session_id'     => (int) $session->session_id,
                'photo_path'     => $selfiePath,
                'id_card_path'   => $idCardPath,
                'password'       => Hash::make($data['password']),
                'account_status' => 'active',
            ]);

            // Lock the profile photo immediately upon registration.
            $lockUpdates = [];
            if (Schema::hasColumn('students', 'profile_photo_path')) {
                $lockUpdates['profile_photo_path'] = $profilePhotoPath;
            }
            if (Schema::hasColumn('students', 'profile_photo_locked_at')) {
                $lockUpdates['profile_photo_locked_at'] = now();
            }
            if ($lockUpdates) {
                $lockUpdates['updated_at'] = now();
                DB::table('students')->where('matric_no', $matric)->update($lockUpdates);
            }

            $request->session()->put('student_matric_no', $matric);
            $request->session()->put('student_session_id', (int) $session->session_id);

            app(AuditService::class)->logAction(
                $matric,
                'student',
                'student.registered',
                [
                    'session_id'   => $session->session_id,
                    'photo_status' => $result['data']['photo_status'] ?? 'pending_admin_approval',
                    'has_id_card'  => true,
                    'has_password' => true,
                ]
            );

            app(AuditService::class)->logAction($matric, 'student', 'student.profile_photo_locked', [
                'session_id' => $session->session_id,
                'profile_photo_path' => $profilePhotoPath,
            ]);

            $redirectUrl = route('student.dashboard');

            return $request->expectsJson()
                ? response()->json(['success' => true, 'redirect_url' => $redirectUrl])
                : redirect($redirectUrl);
        } catch (\Throwable $e) {
            Log::warning('Student onboarding failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);
            $message = $this->registrationErrorMessage($e);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->withErrors(['registration' => $message])->withInput();
        }
    }

    // Login page
    public function loginPage(Request $request)
    {
        if ($request->session()->has('student_matric_no')) {
            return redirect()->route('student.dashboard');
        }

        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $matric  = trim((string) $request->query('matric', ''));

        return view('student.login', compact('session', 'matric'));
    }

    // Authenticate student
    public function doLogin(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'matric_no' => ['required', 'string', 'max:50'],
            'password'  => ['required', 'string'],
        ]);

        try {
            $parts  = MatricNumber::parseLiveFormat($data['matric_no']);
            $matric = $parts['matric_number'];
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['matric_no' => $msg])->withInput();
        }

        $student = DB::table('students')->where('matric_no', $matric)->first();

        if (! $student) {
            $msg = 'No account found for this matric number. Please register first.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['matric_no' => $msg])->withInput();
        }

        $storedPassword = Schema::hasColumn('students', 'password') ? ($student->password ?? null) : null;

        if (! $storedPassword) {
            $msg = 'Your account needs a password. Please complete your registration setup.';
            $onboardUrl = route('student.onboard') . '?matric=' . urlencode($matric);

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg, 'redirect_url' => $onboardUrl], 422)
                : redirect($onboardUrl)->with('status', $msg);
        }

        if (! Hash::check($data['password'], $storedPassword)) {
            $msg = 'Incorrect password. Please try again.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['password' => $msg])->withInput();
        }

        if (Schema::hasColumn('students', 'account_status')) {
            $accountStatus = $student->account_status ?? null;
            if ($accountStatus !== null && $accountStatus !== 'active') {
                $msg = match ($accountStatus) {
                    'suspended' => 'Your account has been suspended. Please contact the registrar.',
                    'pending'   => 'Your account is pending administrative approval. You will be notified by email once activated.',
                    default     => 'Your account is not active. Please contact the registrar.',
                };

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 403)
                    : back()->withErrors(['matric_no' => $msg])->withInput();
            }
        }

        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $sessionId = $session ? (int) $session->session_id : (int) ($student->session_id ?? 0);

        $request->session()->regenerate();
        $request->session()->put('student_matric_no', $matric);
        $request->session()->put('student_session_id', $sessionId);

        app(AuditService::class)->logAction($matric, 'student', 'student.login', [
            'session_id' => $sessionId,
        ]);

        $redirectUrl = route('student.dashboard');

        return $request->expectsJson()
            ? response()->json(['success' => true, 'redirect_url' => $redirectUrl])
            : redirect($redirectUrl);
    }

    // Legacy registration endpoint — keeps backward compat for any external callers
    public function register(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'matric_no'      => ['required_without:matric_number', 'nullable', 'string', 'max:50'],
            'matric_number'  => ['required_without:matric_no', 'nullable', 'string', 'max:50'],
            'passport_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:2048'],
        ]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active exam session found.'], 422);
        }

        try {
            $matricParts = MatricNumber::parseLiveFormat($data['matric_no'] ?? $data['matric_number'] ?? null);
            $data['matric_no'] = $matricParts['matric_number'];
            $officialStudent = $this->assertOfficialStudentCanRegister($data['matric_no'], $matricParts);
            $data['photo_path'] = $this->storePassportPhoto($request, $data['matric_no']);

            $result = $this->registrationService->registerStudent([
                'matric_no'  => $data['matric_no'],
                'session_id' => (int) $session->session_id,
                'photo_path' => $data['photo_path'],
            ]);

            $request->session()->put('student_matric_no', $data['matric_no']);
            $request->session()->put('student_session_id', (int) $session->session_id);

            app(AuditService::class)->logAction(
                $data['matric_no'],
                'student',
                'student.registered',
                [
                    'session_id'          => $session->session_id,
                    'photo_status'        => $result['data']['photo_status'] ?? 'pending_admin_approval',
                    'matric_format'       => $matricParts,
                    'official_department' => $officialStudent->department ?? null,
                ]
            );

            if (! $request->expectsJson()) {
                return redirect()->route('student.dashboard');
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Registration successful. Your profile is waiting for admin approval.',
                'redirect_url' => route('student.dashboard'),
                'data'         => [
                    'matric_no'    => $data['matric_no'],
                    'photo_status' => $result['data']['photo_status'] ?? 'pending_admin_approval',
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logRegistrationFailure($e, $data, $session);
            $message = $this->registrationErrorMessage($e);

            if (! $request->expectsJson()) {
                return back()->withErrors(['registration' => $message])->withInput();
            }

            return response()->json(['success' => false, 'message' => $message], 422);
        }
    }

    private function registrationErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        $containsInternalDetails = preg_match(
            '/SQLSTATE|database|table\s+\S+|column\s+\S+|stack trace|constraint/i',
            $message
        ) === 1;

        if (! $containsInternalDetails
            && ($exception instanceof \InvalidArgumentException || $exception instanceof \RuntimeException)) {
            return $exception->getMessage();
        }

        return 'Registration could not be completed right now. Please check your details and try again.';
    }

    private function logRegistrationFailure(\Throwable $exception, array $data, ?object $session): void
    {
        $matricNo = (string) ($data['matric_no'] ?? '');

        try {
            $context = [
                'exception_class'         => $exception::class,
                'database_driver'         => DB::getDriverName(),
                'active_session_exists'   => (bool) $session,
                'official_student_exists' => $matricNo !== ''
                    && Schema::hasTable('official_students')
                    && DB::table('official_students')->where('matric_number', $matricNo)->exists(),
            ];
        } catch (\Throwable $diagnosticException) {
            $context = [
                'exception_class'            => $exception::class,
                'diagnostic_exception_class' => $diagnosticException::class,
            ];
        }

        Log::warning('Student registration failed.', $context);
    }

    private function storePassportPhoto(Request $request, string $matricNo): string
    {
        $file      = $request->file('selfie') ?? $request->file('passport_photo');
        $directory = public_path('photos/student-submissions');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = Str::slug(str_replace('/', '-', $matricNo))
            . '-'
            . now()->format('YmdHis')
            . '-'
            . Str::random(8)
            . '.jpg';

        file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, file_get_contents($file->getRealPath()));

        return 'photos/student-submissions/' . $filename;
    }

    private function storeProfilePhoto(Request $request, string $matricNo): string
    {
        $file      = $request->file('profile_photo');
        $directory = public_path('photos/profiles');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'profile-'
            . Str::slug(str_replace('/', '-', $matricNo))
            . '-'
            . now()->format('YmdHis')
            . '-'
            . Str::random(6)
            . '.jpg';

        file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, file_get_contents($file->getRealPath()));

        return 'photos/profiles/' . $filename;
    }

    private function storeIdCard(Request $request, string $matricNo): string
    {
        $file      = $request->file('id_card');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename  = 'idcard-'
            . Str::slug(str_replace('/', '-', $matricNo))
            . '-'
            . now()->format('YmdHis')
            . '-'
            . Str::random(8)
            . '.' . $extension;

        Storage::disk('local')->put('id-cards/' . $filename, file_get_contents($file->getRealPath()));

        return 'id-cards/' . $filename;
    }

    private function assertOfficialStudentCanRegister(string $matricNo, array $matricParts): object
    {
        if (! Schema::hasTable('official_students')) {
            throw new \RuntimeException('Student registry is not ready. Please contact the admin or exam officer.');
        }

        $officialStudent = DB::table('official_students')
            ->where('matric_number', $matricNo)
            ->first();

        if (! $officialStudent) {
            throw new \RuntimeException('your matric number was not found in the official student list. please contact the admin or exam officer.');
        }

        if (strtolower((string) $officialStudent->status) !== 'active') {
            throw new \RuntimeException('This matric number is not active on the official student list. Please contact the admin or exam officer.');
        }

        $department = DB::table('departments')
            ->whereRaw('LOWER(dept_name) = ?', [strtolower((string) $officialStudent->department)])
            ->first();

        if (
            $department
            && Schema::hasColumn('departments', 'faculty_code')
            && Schema::hasColumn('departments', 'department_code')
            && $department->faculty_code
            && $department->department_code
            && (
                (string) $department->faculty_code !== $matricParts['faculty_code']
                || (string) $department->department_code !== $matricParts['department_code']
            )
        ) {
            throw new \RuntimeException('Matric number code does not match the official department record. Please contact the admin or exam officer.');
        }

        return $officialStudent;
    }

    private function settingBoolean(string $key, bool $default): bool
    {
        if (! Schema::hasTable('cernix_settings')) {
            return $default;
        }

        $value = DB::table('cernix_settings')->where('key', $key)->value('value');

        return $value === null
            ? $default
            : in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
