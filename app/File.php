<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $table = 'files';
    protected $hidden = ['disk'];

    public function getPathAttribute(?string $value): ?string
    {
        return $this->disk === 'reports_private'
            ? route('reports.attachments', ['reportId' => $this->fileable_id, 'kind' => 'file', 'attachmentId' => $this->id])
            : $value;
    }
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.

    public function fileable()
    {
        return $this->morphTo();
    }
}
