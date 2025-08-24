<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6'],
            'phone'    => ['nullable','string','max:50'],
            'role'     => ['nullable','integer','in:0,1'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'phone'    => $data['phone'] ?? null,
            'role'     => $data['role'] ?? 0,
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user'       => $user,
            'token'      => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        // opcional: revocar tokens anteriores
        // $user->tokens()->delete();

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user'       => $user,
            'token'      => $token        
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) $token->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

public function forgotPassword(Request $request)
{
    $request->validate(['email' => ['required','email']]);

    $status = null;
    try {
        $status = Password::sendResetLink($request->only('email'));
        Log::info('forgot-password status', ['email' => $request->email, 'status' => $status]);
    } catch (\Throwable $e) {
        Log::error('forgot-password exception: '.$e->getMessage());
        // si quieres depurar, puedes retornar 500 temporalmente:
        // return response()->json(['message' => $e->getMessage()], 500);
    }

    // respuesta genérica
    return response()->json([
        'message' => 'Si el correo está registrado, enviaremos un enlace para restablecer tu contraseña.',
    ], 200);
}


public function resetPassword(Request $request)
{
    $request->validate([
        'token'                 => ['required'],
        'email'                 => ['required', 'email'],
        'password'              => ['required', 'string', 'min:6', 'confirmed'], // requiere password_confirmation
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        }
    );

    if ($status === Password::PASSWORD_RESET) {
        // (Opcional) Emitir un nuevo token Sanctum para loguear automáticamente tras el reset
        // $token = $user->createToken('auth')->plainTextToken;
        // return response()->json(['message' => __($status), 'token' => $token], 200);

        return response()->json(['message' => __($status)], 200);
    }

    return response()->json(['message' => __($status)], 422);
}
}
