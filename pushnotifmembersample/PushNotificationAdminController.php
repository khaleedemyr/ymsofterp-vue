<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;
use Carbon\Carbon;
use Redirect;
use DataTables;
use Auth;
use Mail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use App\But_products;
use App\Sub_products;

use View;

class PushNotificationAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index()
    {
        // $dataNotifications = DB::table('pushnotification as pn')
        //     ->select('pn.*', DB::raw('COUNT(pps.id) AS sended_devices'))
        //     ->leftJoin('pushnotification_process_send as pps', function ($join) {
        //         $join->on('pps.id_pushnotification', '=', 'pn.id')
        //             ->where('pps.status_send', '=', '1');
        //     })
        //     ->groupBy('pn.id')
        //     ->get();
        // $totalDevices = DB::table('costumers')
        //     ->select('firebase_token_device')
        //     ->where('firebase_token_device', '!=', '')
        //     ->count();
        // $promoSatuAgustus = json_decode($this->getJsonFromApi('https://ymsoft-erp.justusku.co.id/api/getdata/promosatuagustus'));
        // foreach ($promoSatuAgustus as $item) {
        //     $no_bill = $item->Nomor;
        //     $newColumn = DB::table('point as p')
        //         ->select('c.email', DB::raw("IFNULL(pt.email_member, '') as 'telah_terkirim'"))
        //         ->join('costumers as c', 'c.id', '=', 'p.costumer_id')
        //         ->leftJoin('pushnotification_target as pt', 'pt.email_member', '=', 'c.email')
        //         ->where("p.no_bill", "=", $no_bill)
        //         ->first();
        //     if ($newColumn != null) {
        //         $item->Email = $newColumn->email;
        //         $item->TelahTerkirim = ($newColumn->telah_terkirim != "") ? "1" : "0";
        //     } else {
        //         $item->Email = "";
        //         $item->TelahTerkirim = "0";
        //     }
        // }
        // return view('admin.pushnotification.pushnotification', compact('dataNotifications', 'totalDevices','promoSatuAgustus'));

        $dataNotifications = DB::table('pushnotification as pn')
            ->select('pn.*', DB::raw('COUNT(pps.id) AS sended_devices'))
            ->leftJoin('pushnotification_process_send as pps', function ($join) {
                $join->on('pps.id_pushnotification', '=', 'pn.id')
                    ->where('pps.status_send', '=', '1');
            })
            ->groupBy('pn.id')
            ->orderBy('pn.id','desc')
            ->limit(5)
            ->get();

        $totalDevices = DB::table('costumers')
            ->select('firebase_token_device')
            ->where('firebase_token_device', '!=', '')
            ->count();

        $promoSatuAgustus = json_decode($this->getJsonFromApi('https://ymsoft-erp.justusku.co.id/api/getdata/promosatuagustus'));

        // Optimisasi: Ambil semua data yang dibutuhkan sekaligus sebelum loop
        $allPointsData = DB::table('point as p')
            ->select('p.no_bill', 'c.email', DB::raw("IFNULL(pt.email_member, '') as 'telah_terkirim'"))
            ->join('costumers as c', 'c.id', '=', 'p.costumer_id')
            ->leftJoin('pushnotification_target as pt', 'pt.email_member', '=', 'c.email')
            ->whereIn('p.no_bill', array_column($promoSatuAgustus, 'Nomor'))
            ->get()
            ->keyBy('no_bill'); // Menggunakan nomor tagihan sebagai kunci

        foreach ($promoSatuAgustus as $item) {
            $no_bill = $item->Nomor;
            if (isset($allPointsData[$no_bill])) {
                $newColumn = $allPointsData[$no_bill];
                $item->Email = $newColumn->email;
                $item->TelahTerkirim = ($newColumn->telah_terkirim != "") ? "1" : "0";
            } else {
                $item->Email = "";
                $item->TelahTerkirim = "0";
            }
        }

        // Query tambahan untuk mengambil data dari ViewMemberPoint_Promo_8_Agustus_September
        $viewMemberPoints = DB::table('webmastermember_member.ViewMemberPoint_Promo_8_Agustus_September')
            ->orderBy('created_at', 'desc')
            ->get();

        // Optimisasi: Ambil semua data yang dibutuhkan untuk viewMemberPoints
        $allPointsDataView = DB::table('point as p')
            ->select('p.no_bill', 'c.email', DB::raw("IFNULL(pt.email_member, '') as 'telah_terkirim'"))
            ->join('costumers as c', 'c.id', '=', 'p.costumer_id')
            ->leftJoin('pushnotification_target as pt', 'pt.email_member', '=', 'c.email')
            ->whereIn('p.no_bill', array_column($viewMemberPoints->toArray(), 'no_bill')) // Menyesuaikan dengan array_column dari viewMemberPoints
            ->get()
            ->keyBy('no_bill'); // Menggunakan nomor tagihan sebagai kunci

        // Lakukan looping untuk $viewMemberPoints dan kondisi terhadap $allPointsDataView
        foreach ($viewMemberPoints as $item) {
            $no_bill = $item->no_bill; // Sesuaikan ini dengan nama kolom yang benar jika berbeda
            if (isset($allPointsDataView[$no_bill])) {
                $newColumn = $allPointsDataView[$no_bill];
                $item->Email = $newColumn->email;
                $item->TelahTerkirim = ($newColumn->telah_terkirim != "") ? "1" : "0";
            } else {
                $item->Email = "";
                $item->TelahTerkirim = "0";
            }
        }



        return view('admin.pushnotification.pushnotification', compact('dataNotifications', 'totalDevices', 'promoSatuAgustus', 'viewMemberPoints'));
    }

    public function input(Request $request)
    {
        //dd($request->all());
        $notificationData = [
            'title' => $request->txt_title,
            'body' => $request->txt_body,
            'target' => $request->txt_target,
        ];
        if ($request->txt_target != "all") {
            $notificationData['status_send'] = '1';
        }
        $notificationId = DB::table('pushnotification')->insertGetId($notificationData);
        $fileName = "";
        if ($request->hasFile('file_foto')) {
            $fileFoto = $request->file('file_foto');
            $extension = $fileFoto->getClientOriginalExtension();
            // Simpan file yang diunggah tanpa mengubahnya
            $fileName = $notificationId . '.' . $extension;
            $filePath = $fileFoto->move(public_path('assets/file_photo_notification'), $fileName);
            DB::table('pushnotification')->where('id', $notificationId)->update(['photo' => $fileName]);
        }

        $arr_txt_target = explode(",", $request->txt_target);
        foreach ($arr_txt_target as $email) {
            // Ambil data dari tabel costumers
            $customerData = DB::table('costumers')
                ->where('email', $email)
                ->first();
            // Masukkan data yang telah diambil ke dalam tabel pushnotification_target
            DB::table('pushnotification_target')->insert([
                'email_member' => $email,
                'token' => $customerData->firebase_token_device,
                'id_pushnotification' => $notificationId,
            ]);
            $dataPush['token'] = $customerData->firebase_token_device;
            $dataPush['title'] = $request->txt_title;
            $dataPush['body'] = $request->txt_body;
            $dataPush['foto'] = $fileName;
            //$this->sendPushNotification($dataPush);
        }

        //return response()->json(['message' => 'Push notification berhasil dibuat', 'notification_id' => $notificationId]);
        return redirect('admin/pushnotification')->with('msg', 'Data berhasil dikirim');
    }
    public function sendPushNotification($data)
    {
        $token = $data['token'];
        $title = $data['title'];
        $body = $data['body'];
        $foto = $data['foto'];

        // proses kirim pesan FCM
        $data = array(
            "to" => $token,
            "notification" => array(
                "title" => $title,
                "body" => $body
            ),
            "data" => array(
                "photo" => $foto
            )
        );
        $jsonData = json_encode($data);
        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: key=AAAAslzPpRc:APA91bEHothpRmZG8xt9mkS_mqMD8dRJhxAwGnv-7eLudDdfydMBo12cw31GEFYQN7c0tsGbi22Wa3gqObbE17pBmTDXpmxUwtkdN7hqEkpgLxgVCFKkdH--RcpfiN3E1LyXCr1LHRSc',
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    public function test()
    {
            $dataPush['token'] = 'fCV419IOQZWqgjdIXrzZyu:APA91bEubJoiASjsL6TxkVVpQTU-UyFxNknAGLIDBqlV9Y672SDQgsOZ5SvwYaN4UDQx93dzbkG5poFOH26YBrSfbD9srRJ2T0rZQiWfLeEST6ht-WYe3K8';
            $dataPush['title'] = 'test test test';
            $dataPush['body'] = 'test';
            $this->sendPushNotification($dataPush);
            return response()->json(['message' => 'Test notification sent']);
    }
}
