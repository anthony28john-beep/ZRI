<?php

namespace App\Http\Controllers;

use App\Exceptions\ClassroomUnavailable;
use App\Services\BlockMemberships;

class StudentAccountController extends AccountController
{
    public function show()
    {
        $user = session('firebase_user');
        $blockState = null;
        $blocksUnavailable = false;
        try {
            $blockState = app(BlockMemberships::class)->forStudent(request()->user()->uid);
        } catch (ClassroomUnavailable) {
            $blocksUnavailable = true;
        }

        return view('student.account', compact('user', 'blockState', 'blocksUnavailable'));
    }
}
