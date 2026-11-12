<?php

namespace Database\Seeders;

use App\Models\EvaluationForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * School-authored learner feedback; not an official CSC/DepEd appraisal form.
 *
 * PPST domains: DepEd Order 42, s. 2017.
 * https://tec.deped.gov.ph/wp-content/uploads/2020/09/Philippine-Professional-Standards-for-Teachers.pdf
 * CSC five-point labels: CSC Memorandum Circular 6, s. 2012 / SPMS.
 * https://www.csc.gov.ph/index.php?Itemid=174&id=29&option=com_content&view=article
 * PMES rating thresholds: DepEd Memorandum 89, s. 2025, Table 17.
 * https://www.deped.gov.ph/wp-content/uploads/DM_s2025_089r.pdf
 *
 * Bands use the application's two-decimal report scores. Item wording and
 * equal domain weights are school adaptations, not prescribed PMES weights.
 */
class EvaluationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $form = EvaluationForm::whereNull('template_id')->firstOrCreate(
                ['name' => 'Grade 11–12 Student Feedback — PPST-aligned'],
                [
                    'description' => "For Grade 11 and Grade 12 students: rate classroom practices you have personally observed during this evaluation period. Consider the whole period and give constructive feedback without naming yourself or classmates.\n\nRatings: 1 = Poor (well below expectations); 2 = Unsatisfactory (below expectations); 3 = Satisfactory (meets expectations); 4 = Very Satisfactory (exceeds expectations); 5 = Outstanding (exceptional practice).\n\nThis school-adapted student feedback tool is not the official DepEd PMES/IPCRF performance appraisal. It covers learner-observable practices in PPST Domains 1–5. The 20 equally weighted questions and equal domain weights (20% each) are school-designed, not official PMES component weights. Survey bands apply to the displayed two-decimal mean.\n\nReferences: DepEd Order 42, s. 2017 (PPST); DepEd Memorandum 89, s. 2025 (PMES rating ranges); CSC Memorandum Circular 6, s. 2012 (SPMS rating labels).",
                    'scale_min' => 1,
                    'scale_max' => 5,
                    'is_active' => true,
                    'performance_bands' => [
                        ['label' => 'Poor', 'min' => 1, 'max' => 1.49],
                        ['label' => 'Unsatisfactory', 'min' => 1.5, 'max' => 2.49],
                        ['label' => 'Satisfactory', 'min' => 2.5, 'max' => 3.49],
                        ['label' => 'Very Satisfactory', 'min' => 3.5, 'max' => 4.49],
                        ['label' => 'Outstanding', 'min' => 4.5, 'max' => 5],
                    ],
                ],
            );

            if (! $form->wasRecentlyCreated) {
                return;
            }

            $position = 1;
            foreach ($this->questions() as $domain => $questions) {
                foreach ($questions as $question) {
                    $form->criteria()->create(['prompt' => $domain.' — '.$question, 'weight' => 1, 'position' => $position++]);
                }
            }
        });
    }

    /** @return array<string, list<string>> */
    private function questions(): array
    {
        return [
            'Content Knowledge and Pedagogy' => [
                'My teacher explains subject concepts in a way I can understand.',
                'My teacher uses examples connected to everyday life, further study, or work.',
                'My teacher asks questions that help me explain my thinking instead of only memorizing answers.',
                'My teacher uses learning materials or technology that help me understand the lesson.',
            ],
            'Learning Environment' => [
                'My teacher treats me and my classmates with respect.',
                'My teacher makes it safe to ask questions or admit mistakes.',
                'My teacher applies classroom rules fairly without humiliating students.',
                'My teacher gives all students opportunities to participate in learning activities.',
            ],
            'Diversity of Learners' => [
                'My teacher adjusts explanations or activities when students need more help.',
                'My teacher respects differences in language, culture, beliefs, and background.',
                'My teacher provides ways for students with different learning needs to participate.',
                'My teacher provides suitable challenges for students who are ready to learn more.',
            ],
            'Curriculum and Planning' => [
                'My teacher explains what we are expected to learn before starting a lesson.',
                'My teacher presents lessons and activities in a clear, logical sequence.',
                'My teacher connects activities to the learning goals of our Grade 11 or Grade 12 subject.',
                'My teacher uses class time well for meaningful learning tasks.',
            ],
            'Assessment and Reporting' => [
                'My teacher explains the criteria or rubrics before grading our work.',
                'My teacher checks our understanding during lessons.',
                'My teacher gives feedback that helps me improve my next output.',
                'My teacher explains assessment results and provides a way to clarify grading concerns.',
            ],
        ];
    }
}
