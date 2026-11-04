<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountRecoveryController;
use App\Http\Controllers\AdminClassroomController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlockMembershipController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\StudentScoreboardController;
use App\Http\Controllers\TeacherAccountController;
use App\Http\Controllers\TeacherPortalController;
use Illuminate\Support\Facades\Route;

Route::middleware('no.cache')->group(function () {
    Route::redirect('/', '/login');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration')->name('register.submit');
    Route::get('/forgot-password', [AccountRecoveryController::class, 'forgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AccountRecoveryController::class, 'sendPasswordReset'])->middleware('throttle:recovery')->name('password.email');
    // Logout remains available even during an upstream outage or an expired session.
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('firebase.session')->group(function () {
        Route::get('/account/password', [AccountController::class, 'password'])->name('account.password');
        Route::put('/account/password', [AccountController::class, 'changePassword'])->middleware('throttle:account')->name('account.password.update');
        Route::get('/verify-email', [AccountRecoveryController::class, 'verificationNotice'])->name('verification.notice');
        Route::post('/verify-email', [AccountRecoveryController::class, 'sendVerification'])->middleware('throttle:verification')->name('verification.send');

        Route::middleware('account.ready')->group(function () {
            Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
            Route::post('/admin/users', [AdminUserController::class, 'store'])->middleware(['role:admin', 'throttle:account'])->name('admin.users.store');
            Route::middleware('role:admin')->group(function () {
                Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
                Route::get('/admin/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
                Route::get('/admin/users/{uid}/delete', [AdminUserController::class, 'confirmDeletion'])->name('admin.users.delete');
                Route::delete('/admin/users/{uid}', [AdminUserController::class, 'destroy'])->middleware('throttle:account')->name('admin.users.destroy');
                Route::get('/admin/classes', [AdminClassroomController::class, 'index'])->name('admin.classes');
                Route::post('/admin/blocks', [AdminClassroomController::class, 'storeBlock'])->middleware('throttle:account')->name('admin.blocks.store');
                Route::put('/admin/blocks/students', [AdminClassroomController::class, 'assignBlock'])->middleware('throttle:account')->name('admin.blocks.students');
                Route::get('/admin/blocks/{block}/delete', [AdminClassroomController::class, 'confirmBlockDeletion'])->whereUuid('block')->name('admin.blocks.delete');
                Route::delete('/admin/blocks/{block}', [AdminClassroomController::class, 'destroyBlock'])->whereUuid('block')->middleware('throttle:account')->name('admin.blocks.destroy');
                Route::post('/admin/classes', [AdminClassroomController::class, 'save'])->middleware('throttle:account')->name('admin.classes.store');
                Route::put('/admin/classes/{classroom}', [AdminClassroomController::class, 'save'])->whereUuid('classroom')->middleware('throttle:account')->name('admin.classes.update');
            });
        });
        // Keep account settings accessible to users who need to correct their email.
        Route::middleware('role:student')->group(function () {
            Route::middleware('account.ready')->group(function () {
                Route::view('/student/play-game', 'student.play-game')->name('student.play-game');
                Route::view('/student/about-game', 'student.about-game')->name('student.about-game');
                Route::get('/student/scoreboard', [StudentScoreboardController::class, 'show'])->name('student.scoreboard');
                Route::post('/student/block/application', [BlockMembershipController::class, 'apply'])->middleware('throttle:account')->name('student.block.apply');
                Route::delete('/student/block/application', [BlockMembershipController::class, 'cancel'])->middleware('throttle:account')->name('student.block.cancel');
                Route::delete('/student/block', [BlockMembershipController::class, 'leave'])->middleware('throttle:account')->name('student.block.leave');
            });
            Route::get('/student/account', [StudentAccountController::class, 'show'])->name('student.account');
            Route::put('/student/account', [StudentAccountController::class, 'update'])->middleware('throttle:account')->name('student.account.update');
        });
        Route::middleware('role:teacher')->group(function () {
            Route::middleware('account.ready')->group(function () {
                Route::get('/teacher/applications', [BlockMembershipController::class, 'applications'])->name('teacher.applications');
                Route::get('/teacher/classes/{classroom}/students/{student}/remove', [BlockMembershipController::class, 'confirmRemoval'])->whereUuid('classroom')->name('teacher.students.remove');
                Route::delete('/teacher/classes/{classroom}/students/{student}', [BlockMembershipController::class, 'removeStudent'])->whereUuid('classroom')->middleware('throttle:account')->name('teacher.students.destroy');
                Route::post('/teacher/applications/{student}/{application}/accept', [BlockMembershipController::class, 'review'])->whereUuid('application')->middleware('throttle:account')->name('teacher.applications.accept');
                Route::post('/teacher/applications/{student}/{application}/decline', [BlockMembershipController::class, 'review'])->whereUuid('application')->middleware('throttle:account')->name('teacher.applications.decline');
            });
            Route::middleware('account.ready')->controller(TeacherPortalController::class)->group(function () {
                Route::get('/teacher', 'show')->name('teacher.home');
                Route::get('/teacher/students', 'show')->name('teacher.students');
                Route::get('/teacher/students/{student}', 'show')->name('teacher.students.show');
                Route::get('/teacher/progress', 'show')->name('teacher.progress');
                Route::get('/teacher/leaderboard', 'show')->name('teacher.leaderboard');
            });
            Route::get('/teacher/account', [TeacherAccountController::class, 'show'])->name('teacher.account');
            Route::put('/teacher/account', [TeacherAccountController::class, 'update'])->middleware('throttle:account')->name('teacher.account.update');
        });
    });
});
