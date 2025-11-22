<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function send(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email resent.'
        ]);
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }
}