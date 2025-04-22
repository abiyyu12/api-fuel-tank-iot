<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Factory;

class ProfileController extends Controller
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

    public function getProfile(Request $request)
    {
        $token = $request->header('Authorization');

        // Pastikan token ada dan hapus 'Bearer '
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return response()->json(['error' => 'Authorization token not found or invalid format'], 401);
        }
        $token = substr($token, 7);

        try {
            // Verifikasi token Firebase
            $verifiedIdToken = $this->auth->verifyIdToken($token);
            $uid = $verifiedIdToken->claims()->get('sub'); // Ambil UID dari token

            if ($uid) {
                // Ambil data pengguna dari Firebase berdasarkan UID
                $userData = $this->database->getReference('users/' . $uid)->getValue();

                if ($userData) {
                    // Kembalikan data pengguna sebagai response JSON
                    return response()->json(['user' => $userData]);
                } else {
                    return response()->json(['error' => 'User data not found'], 404);
                }
            } else {
                return response()->json(['error' => 'Invalid UID'], 400);
            }
        } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
            return response()->json(['error' => 'Invalid token', 'message' => $e->getMessage()], 401);
        }
    }


    public function updateProfile($uid,Request $request)
    {
        // Validasi input parameter
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        // Cek apakah validasi gagal
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'error' => 'Validation Error',
                'details' => $validator->errors()
            ], 422);
        }

        // Ambil token dari header Authorization
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return response()->json(['error' => 'Authorization token not found or invalid format'], 401);
        }

        try {
            // Data yang akan diperbarui
            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->input('name');
            }

            if ($request->has('address')) {
                $updateData['address'] = $request->input('address');
            }

            if ($request->has('phone')) {
                $updateData['phone'] = $request->input('phone');
            }

            if (!empty($updateData)) {
                // Update data pengguna di Firebase
                $this->database->getReference('users/' . $uid)->update($updateData);
                
                return response()->json(['status' => 'success', 'message' => 'Profile updated successfully'], 200);
            }

            return response()->json(['error' => 'No valid data provided to update'], 400);

        } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
            return response()->json(['error' => 'Invalid token', 'message' => $e->getMessage()], 401);
        }
    }

    
}
