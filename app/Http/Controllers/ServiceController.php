<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\GCSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function __construct(private GCSService $gcs) {}

    /** LISTAR: por defecto solo activos. ?q=buscar&category_id=1&per_page=20
     *  Usa ?all=1 para traer todos (sin paginar), igual solo activos.
     *  Usa ?include_inactive=1 para incluir inactivos.
     */
    public function index(Request $request)
    {
        $q = Service::query();

        // Solo activos por defecto
        if (!$request->boolean('include_inactive')) {
            $q->where('active', true);
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $q->where(function($w) use ($search) {
                $w->where('name','like',"%{$search}%")
                  ->orWhere('description','like',"%{$search}%");
            });
        }
        if ($request->filled('category_id')) {
            $q->where('category_id', $request->integer('category_id'));
        }

        $q->orderByDesc('id');

        if ($request->boolean('all')) {
            return response()->json($q->get());
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($q->paginate($perPage));
    }

    /** MOSTRAR uno */
    public function show(Service $service)
    {
        return response()->json($service);
    }

    /** CREAR (tu método actual, le forzamos active=true si no viene) */
    public function Save(Request $request)
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
            'file'               => 'nullable|file|image|max:4096',
        ]);

        $data = $request->only([
            'name','description','duration_minutes','price',
            'requires_deposit','deposit_amount','category_id'
        ]);
        $data['active'] = $request->boolean('active', true);

        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $ext      = $file->getClientOriginalExtension() ?: 'jpg';
            $destPath = 'services/' . date('Y/m/d') . '/service_' . Str::uuid() . '.' . $ext;

            $res       = $this->gcs->upload($file, $destPath, true);
            $publicUrl = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);
            $data['img'] = $publicUrl;
        }

        $service = Service::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Servicio creado',
            'data'    => $service,
        ], 201);
    }

    /** EDITAR (reemplaza campos y opcionalmente la imagen) */
    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name'              => 'sometimes|required|string',
            'description'       => 'sometimes|nullable|string',
            'duration_minutes'  => 'sometimes|nullable|integer',
            'price'             => 'sometimes|required|numeric',
            'requires_deposit'  => 'sometimes|boolean',
            'deposit_amount'    => 'sometimes|nullable|numeric',
            'active'            => 'sometimes|boolean',
            'category_id'       => 'sometimes|nullable|integer',
            'img'               => 'sometimes|file|image|max:4096',
            'file'               => 'sometimes|file|image|max:4096',
        ]);

        
        Log::error($request);
        Log::error($service);
        
        $data = $request->only([
            'name','description','duration_minutes','price',
            'requires_deposit','deposit_amount','category_id','active'
        ]);

        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $ext      = $file->getClientOriginalExtension() ?: 'jpg';
            $destPath = 'services/' . date('Y/m/d') . '/service_' . Str::uuid() . '.' . $ext;

            $res       = $this->gcs->upload($file, $destPath, true);
            $publicUrl = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);
            $data['img'] = $publicUrl;
            // Nota: Si quieres borrar la imagen anterior del bucket,
            // guarda también img_path en BD para poder eliminarla aquí.
        }

        $service->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Servicio actualizado',
            'data'    => $service->fresh(),
        ]);
    }

    /** “Eliminar”: solo desactiva (active = false) */
    public function destroy(Service $service)
    {
        $service->update(['active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Servicio desactivado',
            'data'    => $service->fresh(),
        ]);
    }

    /** Setear activo/inactivo explícitamente */
    public function setActive(Request $request, Service $service)
    {
        $request->validate(['active' => 'required|boolean']);
        $service->update(['active' => $request->boolean('active')]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado',
            'data'    => $service->fresh(),
        ]);
    }
}
