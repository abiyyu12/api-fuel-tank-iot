<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;

use Illuminate\Support\Facades\Validator;

class VolumeTankController extends Controller
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

    public function updateVolumeTankIoT(Request $request)
    {
    $validator = Validator::make($request->all(), [
        'current_volume' => 'required|numeric|min:0',
        'max_tank' => 'required|numeric|min:0',
    ]);
    if ($validator->fails()) {
        return response()->json([
            'status' => 422,
            'error' => 'Validation Error',
            'details' => $validator->errors()
        ], 422);
    }
    try {
        $timezone = 'Asia/Jakarta';
        $now = Carbon::now($timezone)->format('Y-m-d\TH:i:s\Z');
        $data = $request->json()->all();
        $this->database->getReference('tank_data/' . 'Tank-01')->set([
            'current_volume' => $data['current_volume'],
            'max_tank' => $data['max_tank'],
            'last_updated' => $now
        ]);

        return response()->json(['status' => 'success', 'message' => 'Volume data updated successfully'], 201);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Internal Server Error: ' . $e->getMessage()], 500);
    }
    }

    public function getVolumeTank(Request $request)
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return response()->json(['error' => 'Authorization token not found or invalid format'], 401);
        }
        try {
            $tank_id = "Tank-01";
            $tankData = $this->database->getReference('tank_data/' . $tank_id)->getValue();
            if ($tankData === null) {
                return response()->json(['error' => 'Tank data not found'], 404);
            }
        return response()->json(
            $tankData
        , 200);

        } catch (\Exception $e) {
        return response()->json(['error' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }

}
