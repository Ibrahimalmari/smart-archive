<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Models\User;


class EmailVerificationController extends Controller
{
    public function send(Request $request)
{
    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email already verified']);
    }

    $user->sendEmailVerificationNotification();

    return response()->json(['message' => 'Verification email resent.']);
}


    public function verify(Request $request, $id, $hash)
{
    $user = User::findOrFail($id);

    // الرابط انتهت صلاحيته
    if (! $request->hasValidSignature()) {
        return response()->json(['message' => 'Link expired'], 400);
    }

    // تأكد من الهاش
    if (! hash_equals($hash, sha1($user->email))) {
        return response()->json(['message' => 'Invalid verification link'], 400);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email already verified']);
    }

    $user->markEmailAsVerified();

    return response()->json(['message' => 'Email verified successfully']);
}

}