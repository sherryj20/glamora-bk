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

    /** PROTECTED: todos */
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
            'bio'       => 'nullable|string',   // ← permitir bio larga
            'active'    => 'nullable|boolean',
            'img'       => 'nullable|file|image|max:4096',
            'file'      => 'nullable|file|image|max:4096',
        ]);

        $imgUrl = null;
        if ($request->hasFile('file') || $request->hasFile('img')) {
            $f   = $request->file('file') ?? $request->file('img');
            $ext = $f->getClientOriginalExtension() ?: 'jpg';
            $dst = 'stylists/' . date('Y/m/d') . '/stylist_' . Str::uuid() . '.' . $ext;

            $res    = $this->gcs->upload($f, $dst, true);
            $imgUrl = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);
        }

        $id = DB::table('stylists')->insertGetId([
            'user_id'    => (int) $request->input('user_id'),
            'specialty'  => $request->input('specialty'),
            'bio'        => $request->input('bio'),     // ← GUARDAR BIO
            'img'        => $imgUrl,
            'active'     => $request->boolean('active', true),
            'created_at' => now(),
        ]);

        $row = DB::table('stylists as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $id)
            ->select('s.*', 'u.name as name')
            ->first();

        return response()->json($row, 201);
    }

    /** POST actualizar (archivo opcional) */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'user_id'   => 'sometimes|required|exists:users,id',
            'specialty' => 'sometimes|nullable|string',
            'bio'       => 'sometimes|nullable|string', // ← permitir bio larga
            'active'    => 'sometimes|boolean',
            'img'       => 'sometimes|file|image|max:4096',
            'file'      => 'sometimes|file|image|max:4096',
        ]);

        $exists = DB::table('stylists')->where('id', $id)->exists();
        if (!$exists) return response()->json(null, 404);

        $data = [];

        if ($request->has('user_id'))   $data['user_id']   = (int) $request->input('user_id');
        if ($request->has('specialty')) $data['specialty'] = $request->input('specialty');
        if ($request->has('active'))    $data['active']    = $request->boolean('active');

        // IMPORTANTE: usar exists() para permitir vaciar bio con "" (string vacía)
        if ($request->exists('bio'))    $data['bio']       = $request->input('bio');

        if ($request->hasFile('file') || $request->hasFile('img')) {
            $f   = $request->file('file') ?? $request->file('img');
            $ext = $f->getClientOriginalExtension() ?: 'jpg';
            $dst = 'stylists/' . date('Y/m/d') . '/stylist_' . Str::uuid() . '.' . $ext;

            $res         = $this->gcs->upload($f, $dst, true);
            $data['img'] = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);
        }

        if (!empty($data)) {
            DB::table('stylists')->where('id', $id)->update($data);
        }

        $row = DB::table('stylists as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $id)
            ->select('s.*', 'u.name as name')
            ->first();

        return response()->json($row);
    }

    /** POST activar/inactivar */
    public function setActive(Request $request, int $id)
    {
        $request->validate(['active' => 'required|boolean']);

        $affected = DB::table('stylists')->where('id', $id)->update([
            'active' => $request->boolean('active'),
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
