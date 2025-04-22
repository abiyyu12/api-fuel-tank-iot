<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Kreait\Firebase\Factory;

use Illuminate\Support\Facades\Validator;

class MonitoringFlowMeterController extends Controller
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

    public function saveDataFlowMeter(Request $request){
        $validator = Validator::make($request->all(), [
            'flow_liter' => 'required|numeric|min:0',
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
            $priceFuel = $this->database->getReference("fuel_price")->getValue();
            $totalPrice = $data['flow_liter'] * $priceFuel["price"];
            $this->database->getReference('flow-meter/' . $now )->set([
                'flow_liter' => $data['flow_liter'],
                'timestamp' => $now,
                'price' => $totalPrice
            ]);
            $data['price'] = $totalPrice;
            return response()->json(['status' => 'success', 'message' => 'Volume data updated successfully',"data" => $data], 201);
        }catch (\Exception $e) {
            return response()->json(['error' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function getAllFlowMeterData(Request $request)
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return response()->json(['error' => 'Authorization token not found or invalid format'], 401);
        }
        try {
            $flowMeter = $this->database->getReference('flow-meter')->getValue();
            if ($flowMeter === null) {
                return response()->json(['error' => 'Flow Meter Not Found'], 404);
            }
            return response()->json(['status' => 'Success Fetch Data',"data" => $flowMeter], 200);
        } catch (\Exception $e) {
        return response()->json(['error' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }

    public function getFlowMeterDataFilterDate(Request $request)
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return response()->json(['error' => 'Authorization token not found or invalid format'], 401);
        }
    
        try {
            // Set zona waktu ke WIB (Asia/Jakarta)
            $timezone = 'Asia/Jakarta';

            // Ambil parameter tanggal dari request (default: seminggu terakhir)
            $startDate = $request->query('start_date', Carbon::now($timezone)->subDays(7)->format('Y-m-d'));
            $endDate = $request->query('end_date', Carbon::now($timezone)->format('Y-m-d'));

            // Konversi ke ISO 8601 untuk Firebase Query
            $startDateTime = Carbon::parse($startDate, $timezone)->startOfDay()->toIso8601String();
            $endDateTime = Carbon::parse($endDate, $timezone)->endOfDay()->toIso8601String();

            // Query data dari Firebase berdasarkan timestamp
            $flowMeterData = $this->database->getReference('flow-meter')
                ->orderByKey()
                ->startAt($startDateTime)
                ->endAt($endDateTime)
                ->getValue();
    
            if (empty($flowMeterData)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No data found for the past week.',
                ], 404);
            }
    
            // Mengelompokkan data berdasarkan tanggal WIB
            $groupedData = [];
            foreach ($flowMeterData as $key => $value) {
                // Konversi timestamp ke WIB
                $dateWIB = Carbon::parse($value['timestamp'])->setTimezone($timezone)->format('Y-m-d');
                $timeWIB = Carbon::parse($value['timestamp'])->format('H:i:s');
                
                $value['timestamp'] = $timeWIB;
    
                // Tambahkan data ke dalam array berdasarkan tanggal WIB
                if (!isset($groupedData[$dateWIB])) {
                    $groupedData[$dateWIB] = [];
                }
                $groupedData[$dateWIB][] = $value;
            }
    
            // Kembalikan data yang sudah dikelompokkan
            return response()->json([
                'status' => 'success fetch data',
                'from' => Carbon::parse($startDateTime)->setTimezone($timezone)->toIso8601String(),
                'to' => Carbon::parse($endDateTime)->setTimezone($timezone)->toIso8601String(),
                'flow_meter_data' => $groupedData
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal Server Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
    public function getFlowMeterDataToday(Request $request)
{
    $token = $request->header('Authorization');
    if (!$token || !str_starts_with($token, 'Bearer ')) {
        return response()->json(['error' => 'Authorization token not found or invalid format'], 401);
    }

    try {
        // Set zona waktu Indonesia (WIB: Asia/Jakarta, WITA: Asia/Makassar, WIT: Asia/Jayapura)
        $timezone = 'Asia/Jakarta'; // WIB
        $now = Carbon::now($timezone)->toIso8601String();
        $todayStart = Carbon::now($timezone)->startOfDay()->toIso8601String();

        $flowMeterData = $this->database->getReference('flow-meter')
            ->orderByKey()
            ->startAt($todayStart)
            ->endAt($now)
            ->getValue();

        if (empty($flowMeterData)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No data found for today.',
            ], 404);
        }

        $groupedData = [];
        foreach ($flowMeterData as $key => $value) {
            // Konversi timestamp ke zona waktu Indonesia
            $time = Carbon::parse($value['timestamp'])->format('H:i:s');
            $value['timestamp'] = $time;

            // Tambahkan ke hasil
            $groupedData[] = $value;
        }

        return response()->json([
            'status' => 'success fetch data',
            'flow_meter_data' => $groupedData
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Internal Server Error: ' . $e->getMessage()
        ], 500);
    }
}


    


    public function deleteFlowMeterById($id) {
        try {
            $sensorRef = $this->database->getReference('flow-meter/' . $id);
            if (!$sensorRef->getSnapshot()->exists()) {
                return response()->json(['message' => 'Data Tidak Ditemukan'], 404);
            }
            $sensorRef->remove();
            return response()->json(['message' => 'Data berhasl dihapus'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => "Internal Servers Error"], 500);
        }
    }
}
