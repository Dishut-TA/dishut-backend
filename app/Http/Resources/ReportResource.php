<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'donation_program_id' => $this->donation_program_id,
            'user_id' => $this->user_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'report_file_path' => $this->report_file_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}