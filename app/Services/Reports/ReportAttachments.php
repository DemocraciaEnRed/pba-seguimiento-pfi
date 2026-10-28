<?php

namespace App\Services\Reports;

use App\File;
use App\ImageFile;
use App\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use RuntimeException;

class ReportAttachments
{
    /** @var list<string> */
    private array $createdPaths = [];

    /** @param list<UploadedFile> $photos
     *  @param list<UploadedFile> $files
     */
    public function store(Report $report, array $photos, array $files): void
    {
        foreach ($photos as $upload) {
            $name = Str::uuid().'.jpg';
            $path = 'photos/'.$name;
            $thumbnail = 'photos/thumb-'.$name;
            $image = Image::make($upload);
            $image->resize(1366, 910, function ($constraint): void {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $this->put($path, (string) $image->encode('jpg'));
            $image->resize(400, 266, function ($constraint): void {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $this->put($thumbnail, (string) $image->encode('jpg', 80));
            $photo = new ImageFile();
            $photo->disk = 'reports_private';
            $photo->name = $name;
            $photo->path = $path;
            $photo->mime = 'image/jpeg';
            $photo->size = Storage::disk('reports_private')->size($path);
            $photo->thumbnail_name = 'thumb-'.$name;
            $photo->thumbnail_path = $thumbnail;
            $photo->thumbnail_mime = 'image/jpeg';
            $photo->thumbnail_size = Storage::disk('reports_private')->size($thumbnail);
            $report->photos()->save($photo);
        }
        foreach ($files as $upload) {
            $path = 'files/'.Str::uuid();
            $this->createdPaths[] = $path;
            if (! Storage::disk('reports_private')->putFileAs('files', $upload, basename($path))) {
                throw new RuntimeException('No se pudo guardar el archivo del reporte.');
            }
            $file = new File();
            $file->disk = 'reports_private';
            $file->name = $upload->getClientOriginalName();
            $file->path = $path;
            $file->mime = $upload->getMimeType();
            $file->size = $upload->getSize();
            $report->files()->save($file);
        }
    }

    private function put(string $path, string $contents): void
    {
        $this->createdPaths[] = $path;
        if (! Storage::disk('reports_private')->put($path, $contents)) {
            throw new RuntimeException('No se pudo guardar la imagen del reporte.');
        }
    }

    public function cleanup(): void
    {
        foreach ($this->createdPaths as $path) {
            if (! Storage::disk('reports_private')->delete($path)) {
                throw new RuntimeException('No se pudo limpiar el adjunto incompleto: '.$path);
            }
        }
        $this->createdPaths = [];
    }

    public function committed(): void
    {
        $this->createdPaths = [];
    }
}
