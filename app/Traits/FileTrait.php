<?php

namespace App\Traits;


use Illuminate\Support\Facades\Storage;

trait FileTrait
{
    public function movePublicFileIfInTemp($oldPath, $folderName): string
    {
        if (str_starts_with($oldPath, 'storage/front-temp')) {
            $oldPath = substr($oldPath, strlen("storage/"));

            if (Storage::exists($oldPath)) {
                $newPath = $folderName . "/" . substr($oldPath, strlen("front-temp/"));
                Storage::move($oldPath, $newPath);
                return "storage/" . $newPath;
            }

            return "storage/" . $oldPath;

        } else {
            return $oldPath;
        }
    }

    public function moveLocalFileIfInTemp($oldPath, $folderName): string
    {
        if (str_starts_with($oldPath, 'front-temp')) {
            if (Storage::disk('local')->exists($oldPath)) {
                $newPath = $folderName . "/" . substr($oldPath, strlen("front-temp/"));
                Storage::disk('local')->move($oldPath, $newPath);
                return $newPath;
            }
            return $oldPath;
        } else {
            return $oldPath;
        }
    }

}
