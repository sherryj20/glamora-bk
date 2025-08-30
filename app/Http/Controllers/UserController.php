<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * GET /api/users
     * Params:
     *  - q:        (opcional) texto a buscar en name/email
     *  - all:      (opcional) 1 => sin paginar, devuelve array plano
     *  - per_page: (opcional) por defecto 15
     *
     * Respuestas:
     *  - all=1 => [ {id, name, email}, ... ]
     *  - paginado => objeto de Laravel con "data", "current_page", etc.
     */
    public function index(Request $request)
    {
        $q = User::query()
            ->select(['id', 'name', 'email'])
            ->orderBy('name');

        if ($request->filled('q')) {
            $term = $request->get('q');
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($request->boolean('all')) {
            return response()->json($q->get());
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($q->paginate($perPage));
    }

        public function names()
    {
        return User::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }
    
}
