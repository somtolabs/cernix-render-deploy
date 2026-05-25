<?php

namespace Database\Seeders;

use App\Models\MockSisRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MockSISSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            ['matric_no' => '230405001', 'full_name' => 'Chidera Favour Nnamdi', 'department' => 'Software Engineering', 'department_code' => '05', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-001.jpg'],
            ['matric_no' => '250404002', 'full_name' => 'Ifeoma Grace Okafor', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '100', 'photo_path' => 'demo-passports/student-002.jpg'],
            ['matric_no' => '240406003', 'full_name' => 'Chiamaka Ruth Eze', 'department' => 'Information Technology', 'department_code' => '06', 'faculty_code' => '04', 'level' => '200', 'photo_path' => 'demo-passports/student-003.jpg'],
            ['matric_no' => '220405004', 'full_name' => 'Adaeze Jennifer Obi', 'department' => 'Software Engineering', 'department_code' => '05', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-004.jpg'],
            ['matric_no' => '230404005', 'full_name' => 'Tunde Michael Bello', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-005.jpg'],
            ['matric_no' => '220406006', 'full_name' => 'Ayomide Samuel Adeyemi', 'department' => 'Information Technology', 'department_code' => '06', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-006.jpg'],
            ['matric_no' => '240407007', 'full_name' => 'Somtochukwu David Okafor', 'department' => 'Cyber Security', 'department_code' => '07', 'faculty_code' => '04', 'level' => '200', 'photo_path' => 'demo-passports/student-007.jpg'],
            ['matric_no' => '220404008', 'full_name' => 'Chukwuemeka Daniel Nwosu', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-008.jpg'],
            ['matric_no' => '250408009', 'full_name' => 'Toluwani Deborah Akinola', 'department' => 'Data Science', 'department_code' => '08', 'faculty_code' => '04', 'level' => '100', 'photo_path' => 'demo-passports/student-009.jpg'],
            ['matric_no' => '230408010', 'full_name' => 'Amara Blessing Nwankwo', 'department' => 'Data Science', 'department_code' => '08', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-010.jpg'],
            ['matric_no' => '250405011', 'full_name' => 'Femi Joshua Akinola', 'department' => 'Software Engineering', 'department_code' => '05', 'faculty_code' => '04', 'level' => '100', 'photo_path' => 'demo-passports/student-011.jpg'],
            ['matric_no' => '240404012', 'full_name' => 'Ibrahim Musa Adamu', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '200', 'photo_path' => 'demo-passports/student-012.jpg'],
            ['matric_no' => '230407013', 'full_name' => 'Emeka Kingsley Obi', 'department' => 'Cyber Security', 'department_code' => '07', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-013.jpg'],
            ['matric_no' => '220408014', 'full_name' => 'Uche David Nnamdi', 'department' => 'Data Science', 'department_code' => '08', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-014.jpg'],

            ['matric_no' => 'CSC/2021/001', 'full_name' => 'Adebayo Oluwaseun Emmanuel', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-020.jpg'],
            ['matric_no' => 'SEN/2021/002', 'full_name' => 'Chinwe Ifeoma Okonkwo', 'department' => 'Software Engineering', 'department_code' => '05', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-003.jpg'],
            ['matric_no' => 'IFT/2021/003', 'full_name' => 'Musa Abdullahi Garba', 'department' => 'Information Technology', 'department_code' => '06', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-014.jpg'],
            ['matric_no' => 'CYS/2021/004', 'full_name' => 'Ngozi Chinyere Eze', 'department' => 'Cyber Security', 'department_code' => '07', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-016.jpg'],
            ['matric_no' => 'DTS/2021/005', 'full_name' => 'Emeka Tochukwu Nwosu', 'department' => 'Data Science', 'department_code' => '08', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-019.jpg'],

            ['matric_no' => '220404001', 'full_name' => 'Chidera Favour Nnamdi', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-001.jpg'],
            ['matric_no' => '220404008', 'full_name' => 'Chukwuemeka Daniel Nwosu', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-008.jpg'],
            ['matric_no' => '220504001', 'full_name' => 'Ifeoma Grace Okafor', 'department' => 'Software Engineering', 'department_code' => '05', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-003.jpg'],
            ['matric_no' => '220504008', 'full_name' => 'Adaeze Jennifer Obi', 'department' => 'Software Engineering', 'department_code' => '05', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-004.jpg'],
            ['matric_no' => '220604001', 'full_name' => 'Tunde Michael Bello', 'department' => 'Information Technology', 'department_code' => '06', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-005.jpg'],
            ['matric_no' => '220604008', 'full_name' => 'Chiamaka Ruth Eze', 'department' => 'Information Technology', 'department_code' => '06', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-006.jpg'],
            ['matric_no' => '220704001', 'full_name' => 'Toluwani Deborah Akinola', 'department' => 'Cyber Security', 'department_code' => '07', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-007.jpg'],
            ['matric_no' => '220704008', 'full_name' => 'Somtochukwu David Okafor', 'department' => 'Cyber Security', 'department_code' => '07', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-008.jpg'],
            ['matric_no' => '220804001', 'full_name' => 'Ayomide Samuel Adeyemi', 'department' => 'Data Science', 'department_code' => '08', 'faculty_code' => '04', 'level' => '300', 'photo_path' => 'demo-passports/student-009.jpg'],
            ['matric_no' => '220804008', 'full_name' => 'Amara Blessing Nwankwo', 'department' => 'Data Science', 'department_code' => '08', 'faculty_code' => '04', 'level' => '400', 'photo_path' => 'demo-passports/student-010.jpg'],
            ['matric_no' => '230404011', 'full_name' => 'Femi Joshua Akinola', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '100', 'photo_path' => 'demo-passports/student-011.jpg'],
            ['matric_no' => '230404012', 'full_name' => 'Zainab Maryam Bello', 'department' => 'Computer Science', 'department_code' => '04', 'faculty_code' => '04', 'level' => '200', 'photo_path' => 'demo-passports/student-012.jpg'],
            ['matric_no' => '230504011', 'full_name' => 'Kemi Victoria Adeyemi', 'department' => 'Software Engineering', 'department_code' => '05', 'faculty_code' => '04', 'level' => '100', 'photo_path' => 'demo-passports/student-013.jpg'],
            ['matric_no' => '230504012', 'full_name' => 'Ibrahim Musa Adamu', 'department' => 'Software Engineering', 'department_code' => '05', 'faculty_code' => '04', 'level' => '200', 'photo_path' => 'demo-passports/student-014.jpg'],
            ['matric_no' => '230604011', 'full_name' => 'Chinedu Victor Eze', 'department' => 'Information Technology', 'department_code' => '06', 'faculty_code' => '04', 'level' => '100', 'photo_path' => 'demo-passports/student-015.jpg'],
            ['matric_no' => '230604012', 'full_name' => 'Ngozi Esther Chukwu', 'department' => 'Information Technology', 'department_code' => '06', 'faculty_code' => '04', 'level' => '200', 'photo_path' => 'demo-passports/student-016.jpg'],
            ['matric_no' => '230704011', 'full_name' => 'Temilade Sarah Ogunleye', 'department' => 'Cyber Security', 'department_code' => '07', 'faculty_code' => '04', 'level' => '100', 'photo_path' => 'demo-passports/student-017.jpg'],
            ['matric_no' => '230704012', 'full_name' => 'Uche David Nnamdi', 'department' => 'Cyber Security', 'department_code' => '07', 'faculty_code' => '04', 'level' => '200', 'photo_path' => 'demo-passports/student-018.jpg'],
            ['matric_no' => '230804011', 'full_name' => 'Emeka Kingsley Obi', 'department' => 'Data Science', 'department_code' => '08', 'faculty_code' => '04', 'level' => '100', 'photo_path' => 'demo-passports/student-019.jpg'],
            ['matric_no' => '230804012', 'full_name' => 'Adebayo Oluwaseun Emmanuel', 'department' => 'Data Science', 'department_code' => '08', 'faculty_code' => '04', 'level' => '200', 'photo_path' => 'demo-passports/student-020.jpg'],
        ];

        $prefixes = [
            'Computer Science' => ['prefix' => 'CSC', 'department_code' => '04'],
            'Software Engineering' => ['prefix' => 'SEN', 'department_code' => '05'],
            'Information Technology' => ['prefix' => 'IFT', 'department_code' => '06'],
            'Cyber Security' => ['prefix' => 'CYS', 'department_code' => '07'],
            'Data Science' => ['prefix' => 'DTS', 'department_code' => '08'],
        ];
        $firstNames = ['Amina', 'Tunde', 'Kehinde', 'Ifeanyi', 'Zainab', 'David', 'Mariam', 'Samuel', 'Chioma', 'Ridwan'];
        $lastNames = ['Adeyemi', 'Okafor', 'Bello', 'Ogunleye', 'Nwachukwu', 'Balogun', 'Salami', 'Eze', 'Akinyemi', 'Mohammed'];
        $femalePhotoCycle = ['student-001.jpg', 'student-003.jpg', 'student-004.jpg', 'student-006.jpg', 'student-007.jpg', 'student-010.jpg', 'student-012.jpg', 'student-013.jpg', 'student-016.jpg', 'student-017.jpg'];
        $malePhotoCycle = ['student-002.jpg', 'student-005.jpg', 'student-008.jpg', 'student-009.jpg', 'student-011.jpg', 'student-014.jpg', 'student-015.jpg', 'student-018.jpg', 'student-019.jpg', 'student-020.jpg'];
        $femaleFirstNames = ['Amina', 'Kehinde', 'Zainab', 'Mariam', 'Chioma'];

        foreach ($prefixes as $department => $meta) {
            foreach ([2021, 2022, 2023, 2024, 2025] as $year) {
                for ($i = 6; $i <= 65; $i++) {
                    $firstName = $firstNames[$i % count($firstNames)];
                    $photoCycle = in_array($firstName, $femaleFirstNames, true) ? $femalePhotoCycle : $malePhotoCycle;
                    $students[] = [
                        'matric_no' => sprintf('%s/%d/%03d', $meta['prefix'], $year, $i),
                        'full_name' => $firstName . ' ' . $lastNames[($year + $i) % count($lastNames)],
                        'department' => $department,
                        'department_code' => $meta['department_code'],
                        'faculty_code' => '04',
                        'level' => (string) ([100, 200, 300, 400][$i % 4]),
                        'photo_path' => 'demo-passports/' . $photoCycle[$i % count($photoCycle)],
                    ];
                }
            }
        }

        foreach ($students as $student) {
            MockSisRecord::updateOrCreate(
                ['matric_no' => $student['matric_no']],
                $student
            );

            if (preg_match('/^\d{9}$/', $student['matric_no']) === 1 && Schema::hasTable('students')) {
                $studentUpdate = [
                    'full_name' => $student['full_name'],
                    'photo_path' => $student['photo_path'],
                ];

                foreach (['level', 'department_code', 'faculty_code'] as $column) {
                    if (Schema::hasColumn('students', $column)) {
                        $studentUpdate[$column] = $student[$column] ?? null;
                    }
                }

                DB::table('students')
                    ->where('matric_no', $student['matric_no'])
                    ->update($studentUpdate);
            }
        }
    }
}
