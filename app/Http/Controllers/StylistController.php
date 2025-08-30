<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\GCSService;

class StylistController extends Controller
{
    public function __construct(private GCSService $gcs) {}

    /** PUBLIC: solo activos */
    public function publicIndex()
    {
        return DB::table('stylists as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.active', 1)
            ->select('s.*', 'u.name as name')
            ->orderByDesc('s.id')
            ->get();
    }

    /** PROTECTED: todos (activos e inactivos) */
    public function index()
    {
        return DB::table('stylists as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->select('s.*', 'u.name as name')
            ->orderByDesc('s.id')
            ->get();
    }

    /** GET uno */
    public function show(int $id)
    {
        $row = DB::table('stylists as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $id)
            ->select('s.*', 'u.name as name')
            ->first();

        if (!$row) return response()->json(null, 404);
        return response()->json($row);
    }

    /** POST crear (archivo: 'file' o 'img') */
    public function store(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|exists:users,id',
            'specialty' => 'nullable|string',
            'bio'       => 'nullable|string',   // bio larga permitida
            'active'    => 'nullable|boolean',
            'img'       => 'nullable|file|image|max:4096',
            'file'      => 'nullable|file|image|max:4096',
        ]);

        // Subir imagen (si hay)
        $imgUrl = null;
        if ($request->hasFile('file') || $request->hasFile('img')) {
            $f   = $request->file('file') ?? $request->file('img');
            $ext = $f->getClientOriginalExtension() ?: 'jpg';
            $dst = 'stylists/' . date('Y/m/d') . '/stylist_' . Str::uuid() . '.' . $ext;

            $res    = $this->gcs->upload($f, $dst, true);
            $imgUrl = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);
        }

        $userId = (int) $request->input('user_id');

        // Transacción: crear stylist y asegurar rol del usuario = 2
        $id = DB::transaction(function () use ($request, $imgUrl, $userId) {
            // Setear rol=2 al usuario asignado
            DB::table('users')->where('id', $userId)->update([
                'role'       => 2,
                'updated_at' => now(),
            ]);

            // Insertar stylist
            return DB::table('stylists')->insertGetId([
                'user_id'    => $userId,
                'specialty'  => $request->input('specialty'),
                'bio'        => $request->input('bio'),
                'img'        => $imgUrl,
                'active'     => $request->boolean('active', true),
                'created_at' => now(),
            ]);
        });

        $row = DB::table('stylists as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $id)
            ->select('s.*', 'u.name as name')
            ->first();

        return response()->json($row, 201);
    }

    /** POST actualizar (solo campos enviados; archivo opcional) */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'user_id'   => 'sometimes|required|exists:users,id',
            'specialty' => 'sometimes|nullable|string',
            'bio'       => 'sometimes|nullable|string',
            'active'    => 'sometimes|boolean',
            'img'       => 'sometimes|file|image|max:4096',
            'file'      => 'sometimes|file|image|max:4096',
        ]);

        $exists = DB::table('stylists')->where('id', $id)->exists();
        if (!$exists) return response()->json(null, 404);

        // Subir imagen si llegó nueva
        $imgUrl = null;
        if ($request->hasFile('file') || $request->hasFile('img')) {
            $f   = $request->file('file') ?? $request->file('img');
            $ext = $f->getClientOriginalExtension() ?: 'jpg';
            $dst = 'stylists/' . date('Y/m/d') . '/stylist_' . Str::uuid() . '.' . $ext;

            $res    = $this->gcs->upload($f, $dst, true);
            $imgUrl = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);
        }

        DB::transaction(function () use ($request, $id, $imgUrl) {
            // Tomamos el user_id previo ANTES de actualizar
            $prevUserId = (int) DB::table('stylists')->where('id', $id)->value('user_id');

            // Armar data parcial de actualización
            $data = [];
            if ($request->has('user_id'))   $data['user_id']   = (int) $request->input('user_id');
            if ($request->has('specialty')) $data['specialty'] = $request->input('specialty');
            if ($request->has('bio'))       $data['bio']       = $request->input('bio');
            if ($request->has('active'))    $data['active']    = $request->boolean('active');
            if ($imgUrl)                    $data['img']       = $imgUrl;
            if (!empty($data))              $data['updated_at']= now();

            // Actualizamos el stylist
            if (!empty($data)) {
                DB::table('stylists')->where('id', $id)->update($data);
            }

            // Si cambió el user_id, ajustar roles
            if (array_key_exists('user_id', $data)) {
                $newUserId = (int) $data['user_id'];

                if ($newUserId !== $prevUserId) {
                    // 1) Nuevo usuario => role = 2
                    DB::table('users')->where('id', $newUserId)->update([
                        'role'       => 2,
                        'updated_at' => now(),
                    ]);

                    // 2) Usuario viejo => si ya no tiene ningún stylist asignado, role = 0
                    $stillUsed = DB::table('stylists')
                        ->where('user_id', $prevUserId)
                        ->exists(); // ya cuenta el registro actualizado; si cambió, no lo contará

                    if (!$stillUsed) {
                        DB::table('users')->where('id', $prevUserId)->update([
                            'role'       => 0,
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        });

        $row = DB::table('stylists as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $id)
            ->select('s.*', 'u.name as name')
            ->first();

        return response()->json($row);
    }

    /** POST activar/inactivar (no hay DELETE) */
    public function setActive(Request $request, int $id)
    {
        $request->validate(['active' => 'required|boolean']);

        $affected = DB::table('stylists')->where('id', $id)->update([
            'active'     => $request->boolean('active'),
            'updated_at' => now(),
        ]);
        if (!$affected) return response()->json(null, 404);

        $row = DB::table('stylists as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $id)
            ->select('s.*', 'u.name as name')
            ->first();

        return response()->json($row);
    }
}
