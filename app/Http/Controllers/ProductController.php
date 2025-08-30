<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\GCSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private GCSService $gcs) {}

    /**
     * LISTADO PROTEGIDO (admin): trae todo
     * Filtros opcionales: ?q=...&category=1&in_stock=1&per_page=20&all=1
     * - Si ?all=1 => sin paginar
     * - Si no, pagina (per_page default 15)
     */
    public function index(Request $request)
    {
        $q = Product::query();

        if ($request->filled('q')) {
            $search = $request->q;
            $q->where(function($w) use ($search) {
                $w->where('name','like',"%{$search}%")
                  ->orWhere('description','like',"%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $q->where('category', (int) $request->input('category'));
        }

        if ($request->filled('in_stock')) {
            $q->where('in_stock', (bool) $request->boolean('in_stock'));
        }

        $q->orderByDesc('id');

        if ($request->boolean('all')) {
            return response()->json($q->get());
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($q->paginate($perPage));
    }

    /**
     * LISTADO PÚBLICO (catálogo): SOLO in_stock = true
     * Misma interfaz de filtros (q, category, per_page, all)
     */
    public function publicIndex(Request $request)
    {
        $q = Product::query()->where('in_stock', true);

        if ($request->filled('q')) {
            $search = $request->q;
            $q->where(function($w) use ($search) {
                $w->where('name','like',"%{$search}%")
                  ->orWhere('description','like',"%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $q->where('category', (int) $request->input('category'));
        }

        $q->orderByDesc('id');

        if ($request->boolean('all')) {
            return response()->json($q->get());
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($q->paginate($perPage));
    }

    /** MOSTRAR uno */
    public function show(Product $product)
    {
        return response()->json($product);
    }

    /** CREAR (POST + FormData; campo de archivo = "file") */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'price'       => 'required|numeric',
            'category'    => 'nullable|integer',
            'description' => 'nullable|string|max:255',
            'in_stock'    => 'nullable|boolean',
            'img'         => 'nullable|file|image|max:4096',
            'file'        => 'nullable|file|image|max:4096',
        ]);

        $data = $request->only(['name','price','category','description','in_stock']);

        // category NOT NULL en migración: si no viene, usa 0
        if (!$request->filled('category')) {
            $data['category'] = 0;
        } else {
            $data['category'] = (int) $request->input('category');
        }

        // in_stock por defecto true
        $data['in_stock'] = $request->boolean('in_stock', true);

        // Subida de imagen si llega "file" (preferente) o "img"
        $uploadField = $request->hasFile('file') ? 'file' : ($request->hasFile('img') ? 'img' : null);
        if ($uploadField) {
            $file     = $request->file($uploadField);
            $ext      = $file->getClientOriginalExtension() ?: 'jpg';
            $destPath = 'products/' . date('Y/m/d') . '/product_' . Str::uuid() . '.' . $ext;

            $res       = $this->gcs->upload($file, $destPath, true);
            $publicUrl = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);
            $data['img'] = $publicUrl;
        }

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Producto creado',
            'data'    => $product,
        ], 201);
    }

    /** EDITAR (SOLO POST + FormData; reemplaza campos y opcionalmente la imagen) */
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name'        => 'sometimes|required|string',
            'price'       => 'sometimes|required|numeric',
            'category'    => 'sometimes|nullable|integer',
            'description' => 'sometimes|nullable|string|max:255',
            'in_stock'    => 'sometimes|boolean',
            'img'         => 'sometimes|file|image|max:4096',
            'file'        => 'sometimes|file|image|max:4096',
        ]);

        // Log::info('products.update payload', [
        //   'all' => $request->except(['file','img']),
        //   'hasFile' => $request->hasFile('file') || $request->hasFile('img'),
        // ]);

        $data = $request->only(['name','price','category','description','in_stock']);

        // category NOT NULL -> si viene vacío, a 0; si no viene, no tocar
        if ($request->has('category')) {
            $data['category'] = $request->filled('category')
                ? (int) $request->input('category')
                : 0;
        }

        // Subir imagen si llega "file" o "img"
        $uploadField = $request->hasFile('file') ? 'file' : ($request->hasFile('img') ? 'img' : null);
        if ($uploadField) {
            $file     = $request->file($uploadField);
            $ext      = $file->getClientOriginalExtension() ?: 'jpg';
            $destPath = 'products/' . date('Y/m/d') . '/product_' . Str::uuid() . '.' . $ext;

            $res       = $this->gcs->upload($file, $destPath, true);
            $publicUrl = $this->gcs->firebaseDownloadUrl($res['name'], $res['firebase_token']);
            $data['img'] = $publicUrl;

            // Si quieres borrar la imagen anterior: guarda el path en BD para eliminar aquí.
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado',
            'data'    => $product->fresh(),
        ]);
    }

    /** “Eliminar” suave: marcar fuera de stock */
    public function destroy(Product $product)
    {
        $product->destroy(['in_stock' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Producto marcado como fuera de stock',
            'data'    => $product->fresh(),
        ]);
    }

    /** Setear in_stock explícitamente */
    public function setInStock(Request $request, Product $product)
    {
        $request->validate(['in_stock' => 'required|boolean']);
        $product->update(['in_stock' => $request->boolean('in_stock')]);

        return response()->json([
            'success' => true,
            'message' => 'Estado de inventario actualizado',
            'data'    => $product->fresh(),
        ]);
    }
}
