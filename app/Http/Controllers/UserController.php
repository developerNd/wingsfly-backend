<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function updateGender(Request $request)
    {
        $request->validate([
            'gender' => 'required|string|in:male,female,other',
        ]);

        $user = Auth::user();
        $user->gender = $request->gender;
        $user->save();

        return response()->json([
            'message' => 'Gender updated successfully',
            'user' => $user
        ]);
    }

    public function getProfile()
    {
        $user = Auth::user();
        return response()->json([
            'user' => $user
        ]);
    }
}
