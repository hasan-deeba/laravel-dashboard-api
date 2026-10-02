<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Models\General\MediaInfo;


class GlobalController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $mediaInfo = MediaInfo::all();
        $mediaInfoResult = array();
        foreach ($mediaInfo as $media) {
            $mediaInfoResult[$media->ident] = $media->description;
        }

        return response()->json(["mediaInfo"=>$mediaInfoResult]);
    }

}
