<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\JWT;
use App\Models\Admin;
use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthJWT
{
    private const SCHEMA_PATTERN = '/^[a-z_][a-z0-9_]*$/';

    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(.*)/', $authHeader, $matches)) {
            return response()->json(['message' => 'Token tidak ditemukan'], 401);
        }
        $token = $matches[1];
        try {
            $payload = JWT::decode($token, env('JWT_SECRET'));
            if (isset($payload->role) && in_array($payload->role, ['admin', 'super_admin', 'admin_unit'])) {
                $admin = Admin::find($payload->sub);
                if (!$admin) {
                    return response()->json(['message' => 'Admin tidak ditemukan'], 401);
                }
                $request->attributes->set('admin', $admin);
            } elseif (isset($payload->role) && $payload->role === 'monitoring') {
                $admin = Admin::where('role', 'monitoring')->where('id', $payload->sub)->first();
                if (!$admin) {
                    return response()->json(['message' => 'Admin monitoring tidak ditemukan'], 401);
                }
                $request->attributes->set('admin', $admin);
            } elseif (isset($payload->role) && $payload->role === 'pegawai') {
                $pegawai = Pegawai::with(['shift.details', 'unit'])->find($payload->sub);

                if (!$pegawai) {
                    return response()->json(['message' => 'Pegawai tidak ditemukan'], 401);
                }
                $request->attributes->set('pegawai', $pegawai);
            } else {
                return response()->json(['message' => 'Role tidak valid'], 401);
            }

            $tenantSchema = $this->resolveTenantSchema($payload);
            if (!$tenantSchema) {
                return response()->json(['message' => 'Tenant schema tidak valid'], 401);
            }

            if (!$this->setSearchPath($tenantSchema)) {
                return response()->json(['message' => 'Gagal mengatur tenant schema'], 500);
            }

            $request->attributes->set('tenant_schema', $tenantSchema);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Token tidak valid: ' . $e->getMessage()], 401);
        }
        return $next($request);
    }

    private function resolveTenantSchema(object $payload): ?string
    {
        $defaultSchema = (string) config('tenancy.default_schema', 'public');
        $fallbackToDefault = (bool) config('tenancy.fallback_to_default_schema', true);
        $envClientSchema = strtolower(trim((string) env('DB_SCHEMA', '')));

        // Prioritaskan schema dari env untuk mode single-tenant per deployment.
        $candidate = $envClientSchema;

        if ($candidate === '' && isset($payload->tenant_schema)) {
            $candidate = strtolower(trim((string) $payload->tenant_schema));
        }

        if ($candidate === '' && $fallbackToDefault) {
            $candidate = strtolower(trim($defaultSchema));
        }

        if ($candidate === '' || !preg_match(self::SCHEMA_PATTERN, $candidate)) {
            return null;
        }

        $allowedSchemas = config('tenancy.allowed_schemas', []);
        if (!empty($allowedSchemas) && !in_array($candidate, $allowedSchemas, true)) {
            return null;
        }

        return $candidate;
    }

    private function setSearchPath(string $tenantSchema): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return true;
        }

        $defaultSchema = (string) config('tenancy.default_schema', 'public');
        $defaultSchema = strtolower(trim($defaultSchema)) ?: 'public';

        if (!preg_match(self::SCHEMA_PATTERN, $defaultSchema)) {
            $defaultSchema = 'public';
        }

        $sql = sprintf('SET search_path TO "%s", "%s"', $tenantSchema, $defaultSchema);

        try {
            DB::statement($sql);
            return true;
        } catch (\Throwable $exception) {
            Log::warning('Failed to set PostgreSQL search_path', [
                'tenant_schema' => $tenantSchema,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
