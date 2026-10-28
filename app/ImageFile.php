<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImageFile extends Model
{
    protected $table = 'image_files';
    public $incrementing = true; // if IDs are auto-incrementing.
    public $timestamps = true; // if the model should be timestamped.

    protected $hidden = [
        'imageable_type', 'imageable_id', 'disk',
    ];

    public function getPathAttribute(?string $value): ?string
    {
        return $this->disk === 'reports_private'
            ? route('reports.attachments', ['reportId' => $this->imageable_id, 'kind' => 'photo', 'attachmentId' => $this->id])
            : $value;
    }

    public function getThumbnailPathAttribute(?string $value): ?string
    {
        return $this->disk === 'reports_private'
            ? route('reports.attachments', ['reportId' => $this->imageable_id, 'kind' => 'thumbnail', 'attachmentId' => $this->id])
            : $value;
    }

    public function imageable()
    {
        return $this->morphTo();
    }
}
