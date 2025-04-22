<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

use Kreait\Firebase\Factory;

use Illuminate\Support\Facades\Validator;

class FlameSensorController extends Controller
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

    public function saveFlameSensor(Request $request) {
        $validator = Validator::make($request->all(), [
            'flame_detected' => 'required|boolean',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'error' => 'Validation Error',
                'details' => $validator->errors()
            ], 422);
        }
    
        try {
            $data = $request->json()->all();
            $sensorRef = $this->database->getReference('flame_sensor/' . "FLAME-0001");
            $timezone = 'Asia/Jakarta';
            $now = Carbon::now($timezone)->format('Y-m-d\TH:i:s\Z');
            // Ambil data sebelumnya dari Firebase
            $existingData = $sensorRef->getValue();
            
            $flameData = [
                'flame_detected' => $data['flame_detected']
            ];
    
            // Jika flame_detected = true, update last_detected dari request
            if ($data['flame_detected']) {
                $flameData['last_detected'] = $now;
            } else {
                // Jika false, gunakan last_detected lama jika ada
                if (!empty($existingData['last_detected'])) {
                    $flameData['last_detected'] = $existingData['last_detected'];
                }
            }
    
            $sensorRef->set($flameData);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Flame Sensor data updated successfully',
                'data' => $flameData
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal Server Error: ' . $e->getMessage()
            ], 500);
        }
    }    
    

    public function getFlameSensor(Request $request){
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return response()->json(['error' => 'Authorization token not found or invalid format'], 401);
        }
        try {
            $data = $this->database->getReference('flame_sensor/' . "FLAME-0001")->getValue();
            if ($data === null) {
                return response()->json(['error' => 'Flame Not Found'], 404);
            }
            return response()->json(['status' => 'success', 'message' => 'Success Fetch Data',"data" => $data], 200);
        }catch (\Exception $e) {
            return response()->json(['error' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }
}
