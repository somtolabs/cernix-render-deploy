<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentsSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['dept_name' => 'Computer Science',       'faculty' => 'Faculty of Computing', 'department_code' => '04', 'faculty_code' => '04'],
            ['dept_name' => 'Software Engineering',   'faculty' => 'Faculty of Computing', 'department_code' => '05', 'faculty_code' => '04'],
            ['dept_name' => 'Information Technology', 'faculty' => 'Faculty of Computing', 'department_code' => '06', 'faculty_code' => '04'],
            ['dept_name' => 'Cyber Security',         'faculty' => 'Faculty of Computing', 'department_code' => '07', 'faculty_code' => '04'],
            ['dept_name' => 'Data Science',           'faculty' => 'Faculty of Computing', 'department_code' => '08', 'faculty_code' => '04'],
        ];

        foreach ($departments as $department) {
            DB::table('departments')->updateOrInsert(
                ['dept_name' => $department['dept_name']],
                $department
            );
        }
    }
}
