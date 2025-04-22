<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Exception\Auth\EmailExists;
use Kreait\Firebase\Exception\InvalidArgumentException;
use Kreait\Firebase\Factory;


class AuthController extends Controller
{
    protected $auth;
    protected $database;

    public function __construct()
    {
        $credentialsPath = storage_path('app/firebase_credentials.json');

        if (!file_exists($credentialsPath)) {
            throw new \Exception("Firebase credentials file not found at {$credentialsPath}");
        }

        $firebaseFactory = (new Factory)
            ->withServiceAccount($credentialsPath)
            ->withDatabaseUri(config('firebase.database_url'));

        $this->auth = $firebaseFactory->createAuth();
        $this->database = $firebaseFactory->createDatabase();
    }
    public function login(Request $request)
    {
        $email = strtolower(trim($request->email));

        $validator = Validator::make(['email' => $email, 'password' => $request->password], [
        'email' => 'required|email|filled',
        'password' => 'required|filled',
    ]);

    if ($validator->fails()) {
        return response()->json([
        'status' => 422,
        'error' => 'Validation Error',
        'details' => $validator->errors()
    ], 422);
    }

try {
    $signInResult = $this->auth->signInWithEmailAndPassword($email, $request->password);
    $firebaseUser = $this->auth->getUserByEmail($email);

    if (!$firebaseUser->emailVerified) {
        $this->auth->sendEmailVerificationLink($firebaseUser->email);
        return response()->json([
            'error' => 'Email not verified. Verification email has been resent.'
        ], 403);
    }

    $idToken = $signInResult->idToken();
    return response()->json(['token' => $idToken], 200);

} catch (\Kreait\Firebase\Auth\SignIn\FailedToSignIn $e) {
    return response()->json(['error' => 'Wrong Email or Password'], 401);
} catch (InvalidArgumentException $e) {
    return response()->json(['error' => $e->getMessage()], 401);
} catch (\Exception $e) {
    return response()->json(['error' => "Something Went Wrong"], 500);
}
    }

public function register(Request $request)
{
    // Validasi Input
    $validator = Validator::make($request->all(), [
        'email' => 'required|filled|email',
        'password' => 'required|filled|min:8',
        'name' => 'required|filled|string|max:255',
        'address' => 'required|filled|string|max:500',
        'phone' => 'required|filled|string|min:10|max:15',
    ]);

    // Cek jika validasi gagal
    if ($validator->fails()) {
        return response()->json([
            'status' => 422,
            'error' => 'Validation Error',
            'details' => $validator->errors()
        ], 422);
    }

    try {
        // Properti untuk pembuatan user di Firebase
        $userProperties = [
            'email' => $request->email,
            'password' => $request->password,
            'displayName' => $request->name,
        ];

        // Buat user baru di Firebase Authentication
        $user = $this->auth->createUser($userProperties);

        // Kirim email verifikasi
        $this->auth->sendEmailVerificationLink($user->email);

        // Simpan data user ke Realtime Database Firebase
        $this->database->getReference('users/' . $user->uid)->set([
            'email' => $request->email,
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'uid' => $user->uid,
        ]);

        return response()->json([
            'message' => 'User registered successfully. Please verify your email.',
            'user' => [
                'uid' => $user->uid,
                'email' => $user->email,
                'name' => $user->displayName,
                'address' => $request->address,
                'phone' => $request->phone,
            ],
        ], 201);

    } catch (EmailExists $e) {
        return response()->json([
            'error' => 'Email already exists'
        ], 400);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Internal Server Error'
        ], 500);
    }
}

    public function logout(Request $request)
    {
        $token = $request->header('Authorization');

        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return response()->json(['error' => 'Authorization token not found or invalid format'], 401);
        }

        try {
            $idToken = str_replace('Bearer ', '', $token);
            $verifiedToken = $this->auth->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');

            $this->auth->revokeRefreshTokens($uid);

            return response()->json(['message' => 'Logout successful'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to logout: ' . $e->getMessage()], 400);
        }
    }

    public function resetPassword(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'email' => 'required|filled|email'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'error' => 'Validation Error',
                'details' => $validator->errors()
            ], 422);
        }
    
        $email = trim($request->email);
    
        try {
            // Cek apakah email terdaftar di Firebase
            $firebaseUser = $this->auth->getUserByEmail($email);
    
            // Kirim email reset password
            $this->auth->sendPasswordResetLink($email);
            return response()->json([
                'message' => 'Password reset email sent successfully to ' . $email,
            ], 200);
    
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            return response()->json([
                'error' => 'Email not registered in the application.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal Server Error',
            ], 500);
        }
    }
    
}
