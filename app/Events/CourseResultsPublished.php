<?php

namespace App\Events;

use App\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseResultsPublished
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, int>  $studentProfileIds
     */
    public function __construct(
        public Course $course,
        public array $studentProfileIds,
    ) {}
}
