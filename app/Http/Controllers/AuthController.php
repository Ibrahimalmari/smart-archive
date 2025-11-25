<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\Auth\AuthServiceInterface;
use App\Http\Services\Auth\PasswordServiceInterface;
use App\Http\DTOs\Auth\ForgotPasswordDto;
use App\Http\DTOs\Auth\ResetPasswordDto;
use App\Http\DTOs\Auth\AddUserDto;
use App\Http\DTOs\Auth\LoginDto;
use App\Http\Requests\AddUserRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\DTOs\Auth\UpdateUserDto;
use App\Http\Requests\ToggleStatusRequest;
use App\Http\DTOs\Auth\ToggleStatusDto;
class AuthController extends Controller
{
    private AuthServiceInterface $auth;
    private PasswordServiceInterface $password;

    public function __construct(AuthServiceInterface $auth, PasswordServiceInterface $password)
    {
        $this->auth     = $auth;
        $this->password = $password;
    }
   

    // Register
      public function AddUser(AddUserRequest $request)
    {
        $dto = new AddUserDto(
            $request->name,
            $request->email,
            $request->password,
            $request->role ?? 'Employee'
        );


        return response()->json(
            $this->auth->AddUser($dto),
            201
        );
    }

    // Login
     public function login(LoginRequest $request)
    {
         $dto = new LoginDto(
        $request->email,
        $request->password
    );

    $result = $this->auth->login($dto);

    // خطأ باسورد / إيميل
    if ($result === null) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // الإيميل غير مُتحقق
    if (isset($result['error']) && $result['error'] === true) {
        return response()->json(['message' => $result['message']], 403);
    }

    return response()->json($result);
    }


    public function updateMe(UpdateUserRequest $request)
{
    $userId = $request->user()->id;

    $dto = new UpdateUserDto(
        $request->input('name'),
        $request->input('email'),
        $request->input('password'),
        null // ❌ ممنوع تغيير الدور
    );

    $user = $this->auth->updateOwnProfile($userId, $dto);

    return response()->json(['user' => $user]);
}


public function updateUser(UpdateUserRequest $request, $id)
{
    $dto = new UpdateUserDto(
        $request->input('name'),
        $request->input('email'),
        $request->input('password'),
        $request->input('role') // ✔️ Admin/Manager يمكنه تعديل الدور
    );

    $user = $this->auth->updateUserAsAdmin($id, $dto);

    return response()->json(['user' => $user]);
}


    // Get logged in user
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
 public function logoutAll(Request $request)
{
    $userId = $request->user()->id;

    $this->auth->logoutAll($userId);

    return response()->json([
        'message' => 'Logged out from all devices successfully'
    ]);
}

        public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $dto = new ForgotPasswordDto($request->input('email'));

        if (!$this->password->forgot($dto)) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        return response()->json(['message' => 'Reset link sent']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required',
            'password' => 'required|min:6',
            // لو تحب add confirmation:
            // 'password_confirmation' => 'required|same:password',
        ]);

        $dto = new ResetPasswordDto(
            $request->input('email'),
            $request->input('token'),
            $request->input('password'),
        );

        if (!$this->password->reset($dto)) {
            return response()->json(['message' => 'Invalid token or email'], 400);
        }

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function deleteUser(Request $request, $id)
{
    $this->auth->deleteUser($id);
    return response()->json(['message' => 'User deleted successfully']);
}

public function changeStatus(ToggleStatusRequest $request, $id)
{
    $dto = new ToggleStatusDto(
        status: $request->input('status'),
    );

    $user = $this->auth->toggleUserStatus($id, $dto);

    return response()->json(['user' => $user]);
}

    

}
