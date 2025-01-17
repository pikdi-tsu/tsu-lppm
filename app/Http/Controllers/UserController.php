<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage as Storage;

class UserController extends Controller
{
    use ApiResponse;


    public function __construct()
    {
        $this->middleware(['role:superadmin'])->except(['login', 'getCurrentUser', 'updateCurrentUser', 'updateCurrentUserPassword']);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Register new user",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="name",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="password_confirmation",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="role",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 example={"name": "lppm sinus", "email": "lppm@sinus.ac.id", "password": "rahasia", "password_confirmation": "rahasia", "role": "superadmin"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Register user success."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="lppm sinus"),
     *                  @OA\Property(property="email", type="string", example="lppm@sinus.ac.id"),
     *                  @OA\Property(property="password", type="string", example="x2YxT56...."),
     *                  @OA\Property(property="role", type="string", example="superadmin"),
     *                  @OA\Property(property="image_path", type="string", example="images/users/1234567890_lppm_sinus.jpg")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The name field must be at least 3 characters. (and 1 more error)"),
     *              @OA\Property(
     *                  property="errors", type="object",
     *                  @OA\Property(
     *                      property="name", type="array",
     *                      @OA\Items(
     *                          type="string", example="The name field must be at least 3 characters."
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="email", type="array",
     *                      @OA\Items(
     *                          type="string", example="The email has already been taken."
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="password_confirmation", type="array",
     *                      @OA\Items(
     *                          type="string", example="The password confirmation does not match."
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="image", type="array",
     *                      @OA\Items(
     *                          type="string", example="The image must be a file of type: jpg, jpeg, png."
     *                      )
     *                  )
     *              )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized Access",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized"),
     *         )
     *     ),
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required_with:password|same:password',
            'role' => 'required|string',
            'image' => 'nullable|mimes:jpg,jpeg,png'
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if ($request->file('image')) {
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $new_image_name = time() . '_' . $request->name . '.' . $extension;
            $image_path = $image->storeAs('images/users', $new_image_name, 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'image_path' => $image_path ?? ''
        ]);

        $user->assignRole($request->role);

        $role = $user->getRoleNames()->first();

        $userData = $user->only(['id', 'name', 'email', 'password']);
        $userData['role'] = $role;

        return $this->successResponse($userData, 'User data registered successfully.', 201);
    }

    /**
     * @OA\Post(
     *     path="/api/users/login",
     *     tags={"Users"},
     *     summary="Login user",
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="email",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string"
     *                 ),
     *                 example={"email": "lppm@sinus.ac.id", "password": "rahasia"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User Login Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logged in successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="lppm sinus"),
     *                  @OA\Property(property="email", type="string", example="lppm@sinus.ac.id"),
     *                  @OA\Property(property="role", type="string", example="superadmin"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid Credentials",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The selected email is invalid."),
     *              @OA\Property(
     *                  property="errors", type="object",
     *                  @OA\Property(
     *                      property="email", type="array",
     *                      @OA\Items(
     *                          type="string", example="The selected email is invalid."
     *                      )
     *                  )
     *              )
     *         )
     *     ),
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('The provided credentials are incorrect.', 401);
        }

        $token = $user->createToken($user->name);

        $role = $user->getRoleNames()->first();

        $userData = $user->only(['id', 'name', 'email']);
        $userData['role'] = $role;

        return $this->authSuccessResponse($userData, 'Logged in successfully.', $token->plainTextToken, 200);
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Get user list",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Get user list successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User list data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="data", 
     *                      type="array", 
     *                      example={{ 
     *                          "id": 1,
     *                          "name": "lppm sinus",
     *                          "email": "lppm@sinus.ac.id",
     *                          "password": "$2y$12$W9fjhpsicGLDD3kbA9XK6upPUdABEGE7ai0pBxhF3Treq5uoSq8oW",
     *                          "role": "superadmin",
     *                          "created_at": "2024-09-12T06:33:25.000000Z",
     *                          "updated_at": "2024-09-12T06:33:25.000000Z"
     *                      }, {
     *                          "id": 1,
     *                          "name": "lppm sinus 2",
     *                          "email": "lppm2@sinus.ac.id",
     *                          "password": "$2y$12$W9fjhpsicGLDD3kbA9XK6upPUdABEGE7ai0pBxhF3Treq5uoSq8oW",
     *                          "role": "admin",
     *                          "created_at": "2024-09-12T06:33:25.000000Z",
     *                          "updated_at": "2024-09-12T06:33:25.000000Z"
     *                      }},
     *                      @OA\Items(
     *                           type="object",
     *                           @OA\Property(property="id", type="integer", example=1),
     *                           @OA\Property(property="name", type="string", example="lppm sinus"),
     *                           @OA\Property(property="email", type="string", example="lppm@sinus.ac.id"),
     *                           @OA\Property(property="password", type="string", example="$2y$12..."),
     *                           @OA\Property(property="role", type="string", example="superadmin"),
     *                           @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                           @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                       ),
     *                       @OA\Items(
     *                           type="object",
     *                           @OA\Property(property="id", type="integer", example=2),
     *                           @OA\Property(property="name", type="string", example="lppm sinus 2"),
     *                           @OA\Property(property="email", type="string", example="lppm2@sinus.ac.id"),
     *                           @OA\Property(property="password", type="string", example="$2y$12..."),
     *                           @OA\Property(property="role", type="string", example="admin"),
     *                           @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-12T07:06:41.000000Z"),
     *                           @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-12T07:06:41.000000Z"),
     *                       )
     *                  ),
     *                  @OA\Property(property="first_page_url", type="string", example="http://localhost:8000/api/users?page=1"),
     *                  @OA\Property(property="from", type="integer", example=1),
     *                  @OA\Property(property="last_page", type="integer", example=2),
     *                  @OA\Property(property="last_page_url", type="string", example="http://localhost:8000/api/users?page=2"),
     *                  @OA\Property(property="links", 
     *                      type="array", 
     *                      example={{ 
     *                          "url": null,
     *                          "label": "&laquo; Previous",
     *                          "active": false
     *                      }, {
     *                          "url": "http://localhost:8000/api/users?page=1",
     *                          "label": "1",
     *                          "active": true
     *                      }, {
     *                          "url": null,
     *                          "label": "Next &raquo;",
     *                          "active": false
     *                      }},
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(property="url", type="string", example=null),
     *                          @OA\Property(property="label", type="string", example="&laquo; Previous"),
     *                          @OA\Property(property="active", type="boolean", example=false),
     *                      ),
     *                  ),
     *                  @OA\Property(property="next_page_url", type="string", example=null),
     *                  @OA\Property(property="path", type="string", example="http://localhost:8000/api/users"),
     *                  @OA\Property(property="per_page", type="integer", example=5),
     *                  @OA\Property(property="prev_page", type="string", example=null),
     *                  @OA\Property(property="to", type="integer", example=5),
     *                  @OA\Property(property="total", type="integer", example=5)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized Access",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized."),
     *         )
     *     ),
     * )
     */
    public function getUserList()
    {
        $query = User::with([
            'roles' => function ($query) {
                $query->select('name');
            }
        ]);

        $query->where('id', '!=', Auth::user()->id);

        if (request()->has('filter')) {
            $filter_content = strtolower(request()->input('filter'));
            $query->whereHas('roles', function ($q) use ($filter_content) {
                $q->where('name', $filter_content);
            });
        }

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query->where('name', 'like', "%$search_term%");
        }

        $users = $query->paginate(10);

        return $this->successResponse($users, 'User data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get a user by ID",
     *     tags={"Users"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="lppm sinus"),
     *                 @OA\Property(property="email", type="string", example="lppm@sinus.ac.id"),
     *                 @OA\Property(property="password", type="string", example="$2y$12$W9fjhpsicGLDD3kbA9XK6upPUdABEGE7ai0pBxhF3Treq5uoSq8oW"),
     *                 @OA\Property(property="role", type="string", example="superadmin")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     )
     * )
     */
    public function getUserByID($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $role = $user->getRoleNames()->first();
        $user['role'] = $role;

        return $this->successResponse($user, 'User data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/users/current",
     *     summary="Get current user",
     *     tags={"Users"},
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Current user data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="lppm sinus"),
     *                 @OA\Property(property="email", type="string", example="lppm@sinus.ac.id"),
     *                 @OA\Property(property="image_path", type="string", example="path/to/image.jpg"),
     *                 @OA\Property(property="role", type="string", example="superadmin")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function getCurrentUser()
    {
        $user = Auth::user();
        $role = $user->getRoleNames()->first();

        $userData = $user->only(['id', 'name', 'email', 'image_path']);
        $userData['role'] = $role;

        return $this->successResponse($userData, 'Current user data retrieved successfully.', 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/users/current",
     *     tags={"Users"},
     *     summary="Update current user",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="name",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="image",
     *                     type="file",
     *                     format="binary",
     *                     description="User profile image (jpg, jpeg, png)"
     *                 ),
     *                 example={"name": "lppm sinus", "email": "lppm@sinus.ac.id", "role": "superadmin"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User data successfully updated."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="lppm sinus"),
     *                  @OA\Property(property="email", type="string", example="lppm@sinus.ac.id"),
     *                  @OA\Property(property="image_path", type="string", example="path/to/image.jpg"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The name field must be at least 3 characters. (and 1 more error)"),
     *              @OA\Property(
     *                  property="errors", type="object",
     *                  @OA\Property(
     *                      property="name", type="array",
     *                      @OA\Items(
     *                          type="string", example="The name field must be at least 3 characters."
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="email", type="array",
     *                      @OA\Items(
     *                          type="string", example="The email has already been taken."
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="image", type="array",
     *                      @OA\Items(
     *                          type="string", example="The image must be a file of type: jpg, jpeg, png."
     *                      )
     *                  )
     *              )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function updateCurrentUser(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|string|email|unique:users,email,' . $user->id,
            'image' => 'nullable|mimes:jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if ($request->file('image')) {
            // Check and delete if old image exists
            if (Storage::disk('public')->exists($user->image_path)) {
                Storage::disk('public')->delete($user->image_path);
            }

            // Upload images
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $new_image_name = time() . '_' . $request->name . '.' . $extension;
            $image_path = $image->storeAs('images/users', $new_image_name, 'public');
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->image_path = $image_path ?? $user->image_path;

        $user->save();

        $role = $user->getRoleNames()->first();

        $userData = $user->only(['id', 'name', 'email', 'image_path']);
        $userData['role'] = $role;

        return $this->successResponse($userData, 'User data successfully updated.', 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/users/current/password",
     *     tags={"Users"},
     *     summary="Update current user's password",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="old_password",
     *                     type="string",
     *                     description="Current password"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     description="New password"
     *                 ),
     *                 @OA\Property(
     *                     property="password_confirmation",
     *                     type="string",
     *                     description="Confirm new password"
     *                 ),
     *                 example={"old_password": "current123", "password": "newpass123", "password_confirmation": "newpass123"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User password updated successfully."),
     *             @OA\Property(property="data", type="null", example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The old password does not match."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="old_password",
     *                     type="array",
     *                     @OA\Items(type="string", example="The old password does not match.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function updateCurrentUserPassword(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required_with:password|same:password'
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if (!Hash::check($request->old_password, $user->password)) {
            return $this->customValidationErrors([
                "old_password" => [
                    "The old password does not match."
                ]
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return $this->successResponse(null, 'User password updated successfully.', 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Update a user by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="name",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 @OA\Property(
     *                     property="role",
     *                     type="string"
     *                 ),
     *                 example={"name": "lppm sinus", "email": "lppm@sinus.ac.id", "role": "superadmin"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User data successfully updated."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="lppm sinus"),
     *                  @OA\Property(property="email", type="string", example="lppm@sinus.ac.id"),
     *                  @OA\Property(property="image_path", type="string", example="images/users/1234567890_lppm_sinus.jpg"),
     *                  @OA\Property(property="role", type="string", example="superadmin"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The name field must be at least 3 characters. (and 1 more error)"),
     *              @OA\Property(
     *                  property="errors", type="object",
     *                  @OA\Property(
     *                      property="name", type="array",
     *                      @OA\Items(
     *                          type="string", example="The name field must be at least 3 characters."
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="email", type="array",
     *                      @OA\Items(
     *                          type="string", example="The email has already been taken."
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="image", type="array",
     *                      @OA\Items(
     *                          type="string", example="The image must be a file of type: jpg, jpeg, png."
     *                      )
     *                  )
     *              )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function updateUserByID(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|string|email|unique:users,email,' . $user->id,
            'image' => 'nullable',
            'role' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if ($request->file('image')) {
            // Check and delete if old image exists
            if (Storage::disk('public')->exists($user->image_path)) {
                Storage::disk('public')->delete($user->image_path);
            }

            // Upload images
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $new_image_name = time() . '_' . $request->name . '.' . $extension;
            $image_path = $image->storeAs('images/users', $new_image_name, 'public');
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->image_path = $image_path;
        $user->save();

        $role = $user->getRoleNames()->first();

        $userData = $user->only(['id', 'name', 'email', 'image_path']);
        $userData['role'] = $role;

        return $this->successResponse($userData, 'User data successfully updated.', 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/users/{id}/password",
     *     tags={"Users"},
     *     summary="Update user password by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password", "password_confirmation"},
     *             @OA\Property(property="password", type="string", format="password", minimum=6, example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password Updated Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User password updated successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string", example="The password field is required.")),
     *                 @OA\Property(property="password_confirmation", type="array", @OA\Items(type="string", example="The password confirmation does not match."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function updateUserPasswordByID(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required_with:password|same:password'
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return $this->successResponse(null, 'User password updated successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Delete a user by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the user",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User Deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User successfully deleted."),
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized.")
     *         )
     *     )
     * )
     */
    public function deleteUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        if ($user->image_path && Storage::disk('public')->exists($user->image_path)) {
            Storage::disk('public')->delete($user->image_path);
            dd('deleted');
        }

        $user->delete();

        return $this->successResponse(null, 'User successfully deleted.', 200);
    }

    /**
     * @OA\Post(
     *     path="/api/users/logout",
     *     tags={"Users"},
     *     summary="Logout user",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged Out Successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logged out successfully."),
     *         )
     *     ),
     * )
     */
    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully.', 200);
    }
}
