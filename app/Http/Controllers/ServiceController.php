<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\GCSService;          // <-- usa tu servicio
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function __construct(private GCSService $gcs) {}

    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string',
            'description'       => 'nullable|string',
            'duration_minutes'  => 'nullable|integer',
            'price'             => 'required|numeric',
            'requires_deposit'  => 'nullable|boolean',
            'deposit_amount'    => 'nullable|numeric',
            'active'            => 'nullable|boolean',
            'category_id'       => 'nullable|integer',
            'img'               => 'nullable|file|image|max:4096',
        ]);

        $data = $request->only([
            'name','description','duration_minutes','price',
            'requires_deposit','deposit_amount','active','category_id'
        ]);

        if ($request->hasFile('img')) {
            $file     = $request->file('img');
            $ext      = $file->getClientOriginalExtension() ?: 'jpg';
            $destPath = 'services/' . date('Y/m/d') . '/service_' . Str::uuid() . '.' . $ext;

            // Sube usando TU servicio y genera token de Firebase
            $res = $this->gcs->upload($file, $destPath, true);

            // URL permanente estilo Firebase (usa el token del metadato):
            $publicUrl = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);

            // Si prefieres temporal:
            // $publicUrl = $this->gcs->signedUrl($res['name'], 60);

            // Guarda lo que necesites en DB:
            $data['img'] = $publicUrl;     // URL pública para mostrar en el front
            // Opcional: guarda también el path interno para borrar/rotar en el futuro:
            // $data['img_path'] = $res['name'];
        }

        $service = Service::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Servicio creado',
            'data'    => $service,
        ], 201);
    }
}
