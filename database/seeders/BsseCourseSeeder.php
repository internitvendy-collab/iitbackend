<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the full BSSE (Bachelor of Science in Software Engineering) curriculum
 * of IIT, NSTU, taken from the Jan 2020 BSSE syllabus.
 *
 * Core courses are grouped by the syllabus terms (Year X Term Y); elective
 * courses are grouped under "Elective".
 */
class BsseCourseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->curriculum() as $semester => $courses) {
            foreach ($courses as [$courseId, $courseName]) {
                DB::table('Courses')->updateOrInsert(
                    ['course_id' => $courseId],
                    ['course_name' => $courseName, 'semester' => $semester],
                );
            }
        }
    }

    /**
     * @return array<string, array<int, array{0: string, 1: string}>>
     */
    private function curriculum(): array
    {
        return [
            'Year 1 Term 1' => [
                ['CSE 1101', 'Structured Programming'],
                ['CSE 1102', 'Structured Programming Lab'],
                ['CSE 1103', 'Discrete Mathematics'],
                ['STAT 1105', 'Probability and Statistics for Engineers-I'],
                ['MATH 1107', 'Calculus and Analytical Geometry'],
                ['GE 1109', 'Soft Skill Communication'],
                ['GE 1111', 'Technology and Society'],
                ['GE 1112', 'Technology and Society Lab'],
                ['SE 1113', 'Introduction to Software Engineering'],
            ],
            'Year 1 Term 2' => [
                ['CSE 1201', 'Data Structure'],
                ['CSE 1202', 'Data Structure Lab'],
                ['CSE 1203', 'Computer Organization'],
                ['CSE 1204', 'Computer Organization Lab'],
                ['STAT 1205', 'Probability and Statistics for Engineers-II'],
                ['MATH 1207', 'Ordinary Differential Equations'],
                ['GE 1209', 'History of Emergence of Bangladesh'],
                ['GE 1211', 'Bengali Literature'],
                ['SE 1213', 'Object Oriented Concepts I'],
                ['SE 1214', 'Object Oriented Concepts I Lab'],
            ],
            'Year 2 Term 1' => [
                ['CSE 2101', 'Algorithm Analysis'],
                ['CSE 2102', 'Algorithm Analysis Lab'],
                ['SE 2103', 'Theory of Computation'],
                ['SE 2104', 'Theory of Computation Lab'],
                ['CSE 2105', 'Computer Networks'],
                ['CSE 2106', 'Computer Networks Lab'],
                ['MATH 2107', 'Numerical Analysis for Engineers'],
                ['MATH 2108', 'Numerical Analysis for Engineers Lab'],
                ['SE 2109', 'Object Oriented Concepts II'],
                ['SE 2110', 'Object Oriented Concepts II Lab'],
                ['SE 2112', 'Software Project Lab I'],
            ],
            'Year 2 Term 2' => [
                ['CSE 2201', 'Operating Systems and System Programming'],
                ['CSE 2202', 'Operating Systems and System Programming Lab'],
                ['GE 2203', 'Business Psychology'],
                ['CSE 2205', 'Information Security'],
                ['CSE 2206', 'Information Security Lab'],
                ['CSE 2207', 'Database Management System-I'],
                ['CSE 2208', 'Database Management System-I Lab'],
                ['SE 2209', 'Software Requirements Spec. and Analysis'],
                ['SE 2210', 'Software Requirements Spec. and Analysis Lab'],
                ['BUS 2211', 'Business Studies for Engineers'],
            ],
            'Year 3 Term 1' => [
                ['SE 3101', 'Professional Ethics for Information Systems'],
                ['CSE 3103', 'Web Technology'],
                ['CSE 3104', 'Web Technology Lab'],
                ['CSE 3105', 'Data Science and Analytics - DBMS II'],
                ['CSE 3106', 'Data Science and Analytics - DBMS II Lab'],
                ['BUS 3107', 'Business Communications'],
                ['BUS 3108', 'Business Communications Lab'],
                ['SE 3109', 'Design Pattern'],
                ['SE 3110', 'Design Pattern Lab'],
                ['SE 3112', 'Software Project Lab II'],
            ],
            'Year 3 Term 2' => [
                ['CSE 3201', 'Distributed Systems'],
                ['CSE 3202', 'Distributed Systems Lab'],
                ['SE 3203', 'Software Metrics'],
                ['SE 3204', 'Software Metrics Lab'],
                ['SE 3205', 'Software Security'],
                ['SE 3206', 'Software Security Lab'],
                ['CSE 3207', 'Artificial Intelligence'],
                ['CSE 3208', 'Artificial Intelligence Lab'],
                ['SE 3209', 'Software Testing and Quality Assurance'],
                ['SE 3210', 'Software Testing and Quality Assurance Lab'],
                ['SE 3211', 'Software Design and Architecture'],
                ['SE 3212', 'Software Design and Architecture Lab'],
            ],
            'Year 4 Term 1' => [
                ['SE 4100', 'Internship'],
            ],
            'Year 4 Term 2' => [
                ['SE 4202', 'Project'],
                ['SE 4203', 'Software Maintenance'],
                ['SE 4204', 'Software Maintenance Lab'],
                ['SE 4205', 'Software Project Management'],
                ['SE 4206', 'Software Project Management Lab'],
            ],
            'Elective' => [
                ['CSE 804', 'Information Retrieval'],
                ['CSE 825', 'Data Mining and Warehousing'],
                ['CSE 829', 'Pattern Recognition and Image Processing'],
                ['CSE 831', 'Computer Graphics and Multimedia'],
                ['BUS 842', 'Strategic Management'],
                ['EEE 202', 'Digital System Design'],
                ['CSE 303', 'Data Communication and Networking'],
                ['CSE 501', 'Parallel Computing'],
                ['BUS 602', 'Management Information Systems'],
                ['CSE 802', 'Computer, Data and Network Security'],
                ['CSE 844', 'Applied Data Science'],
            ],
        ];
    }
}
