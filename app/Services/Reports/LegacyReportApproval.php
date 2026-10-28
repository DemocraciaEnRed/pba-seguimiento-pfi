<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LegacyReportApproval
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $reports = DB::table('reports')->whereNull('review_status');
            if (! $reports->exists()) {
                return;
            }
            $isAdmin = DB::table('users')->where('id', 1)->exists()
                && DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->where('role_user.user_id', 1)->where('roles.name', 'admin')->exists();
            if (! $isAdmin) {
                throw new RuntimeException('La aprobación histórica requiere que el usuario 1 exista y sea administrador global.');
            }
            $count = $reports->update([
                'review_status' => 'approved',
                'reviewed_by' => 1,
                'reviewed_at' => now(),
            ]);
            Log::channel('mysql')->info('Regularización automática de reportes históricos por el administrador 1', [
                'user_id' => 1,
                'action' => 'legacy-report-approval',
                'reports_count' => $count,
            ]);
        });
    }
}
