<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait AuthorizesSuperAdmin
{
    protected function authorizeSuperAdmin(Request $request): void
    {
        $admin = $request->get('admin');
        if (!$admin || $admin->role !== 'super_admin') {
            abort(403, 'Hanya super admin yang boleh mengakses.');
        }
    }
}
