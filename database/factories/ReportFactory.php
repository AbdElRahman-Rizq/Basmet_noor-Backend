<?php

namespace Database\Factories;

use App\Models\CaseFile;
use App\Models\Report;
use App\Models\Specialist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        $id = (string) Str::uuid();

        return [
            'case_id' => CaseFile::factory(),
            'specialist_id' => Specialist::factory(),
            'session_date' => now()->toDateString(),
            'file_path' => "reports/{$id}.pdf",
            'file_original_name' => 'report.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 1048576),
            'file_checksum' => hash('sha256', $id),
            'visible_to_parent' => true,
        ];
    }
}
