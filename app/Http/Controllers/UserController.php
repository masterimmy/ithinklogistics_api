<?php
namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();

        return UserResource::collection($users)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Request received', [
                'data' => $request->except('password'),
            ]);

            $validator = Validator::make($request->all(), [
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'min:8'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validated = $validator->validated();

            Log::info('Validation passed', ['data' => array_keys($validated)]);

            $user = $this->userService->createUser($validated);

            return (new UserResource($user))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);

        } catch (Exception $e) {
            Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create user',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            Log::info('Update request received', [
                'user_id' => $id,
                'data'    => $request->except('password'),
            ]);

            $validator = Validator::make($request->all(), [
                'name'     => ['sometimes', 'string', 'max:255'],
                'email'    => ['sometimes', 'email', 'unique:users,email,' . $id],
                'password' => ['sometimes', 'min:8'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validated = $validator->validated();
            $user      = $this->userService->updateUser($id, $validated);

            return (new UserResource($user))
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (UserNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'error'   => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);

        } catch (Exception $e) {
            Log::error('Failed to update user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to update user',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(User $user)
    {
        //
    }
}
