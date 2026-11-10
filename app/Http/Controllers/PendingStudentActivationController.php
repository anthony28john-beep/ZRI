<?php

namespace App\Http\Controllers;

use App\Services\StudentEnrollment;
use Illuminate\Http\Request;

class PendingStudentActivationController extends Controller
{
    public function index(StudentEnrollment $enrollment)
    {
        return view('admin.users.activations', ['pending' => $enrollment->pending()]);
    }

    public function destroy(Request $request, StudentEnrollment $enrollment, string $invitation)
    {
        $request->validate(['confirmation' => ['accepted']]);
        $enrollment->revoke($invitation, $request->user()->uid);

        return redirect()->route('admin.students.activations')->with('success', 'Pending invitation removed. The assigned teacher can import corrected details. Existing accounts and scores were retained.');
    }
}
