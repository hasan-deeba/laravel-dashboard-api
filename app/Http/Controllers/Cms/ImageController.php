<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImageController extends Controller
{
    use ApiResponse;

    public function uploadPhoto(Request $request): \Illuminate\Http\JsonResponse
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            "photo" => 'required|mimes:jpeg,jpg,png',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->errors(), "message" => "Please complete the missing fields.",
            ], 422);
        }

        $path = $request->file('photo')->store('front-temp');
        return response()->json([
            "message" => "Photo uploaded successfully",
            "data" => ["url" => "storage/" . $path],
        ]);
    }

    public function uploadMultiplePhoto(Request $request)
    {
        $path = [];
        foreach ($request->file('photo') as $key =>$file) {
            $temp = new \stdClass();
            $temp->id = null;
            $temp->image= "storage/" .$file->store('front-temp');
            $temp->sort_order = $key;
            $path[]= $temp;
        }
        return response()->json([
            "message" => "Photo uploaded successfully",
            "data" => ["url" => $path],
        ]);
    }

    public function deletePhoto(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            "path" => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->errors(), "message" => "Please complete the missing fields.",
            ], 422);
        }

        if (\File::exists($request['path'])) {
            \File::delete($request['path']);
            return $this->successMessage("Image deleted successfully", 200);
        } else {
            return $this->errorResponse("File Not Found", 400);
        }

    }

    public function uploadVideo(Request $request): \Illuminate\Http\JsonResponse
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            "video" => 'required|mimes:mp4,x-flv,application/x-mpegURL,MP2T
            ,3gpp,quicktime,x-msvideo,x-ms-wmv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "errors" => $validator->errors(), "message" => "Please complete the missing fields.",
            ], 422);
        }

        $path = $request->file('video')->store('front-temp');
        return response()->json([
            "message" => "video uploaded successfully",
            "data" => ["url" => "storage/" . $path],
        ]);
    }


}
