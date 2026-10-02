<?php

namespace App\Http\Controllers\Cms\AccessManagement;

use App\Http\Controllers\CrudController;
use App\Http\Requests\Cms\AccessManagement\Users\CreateUserRequest;
use App\Http\Requests\Cms\AccessManagement\Users\UpdateUserRequest;
use App\Services\Cms\AccessManagement\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends CrudController
{
    protected string $permissionPrefix = 'users';

    public function __construct(UserService $userService)
    {
        $this->createRequest = CreateUserRequest::class;
        $this->updateRequest = UpdateUserRequest::class;

        parent::__construct($userService);
    }

    public function setTheme(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'in:light,dark,system'],
        ]);

        $result = $this->mainService->setTheme($data, $request->user());

        return $this->responseMessage($result->message, $result->code, $result->valid);
    }
}
