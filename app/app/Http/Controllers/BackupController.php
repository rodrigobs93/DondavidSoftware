<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        return view('backups.index', compact('settings'));
    }

    public function export()
    {
        $config   = config('database.connections.pgsql');
        $filename = 'dondavid_backup_' . now()->setTimezone('America/Bogota')->format('Y-m-d_His') . '.sql';
        $tmpPath  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        $pgDump = 'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe';

        $process = new Process([
            $pgDump,
            '-U', $config['username'],
            '-h', $config['host'],
            '-p', (string) $config['port'],
            $config['database'],
            '-f', $tmpPath,
        ]);

        $process->setEnv(['PGPASSWORD' => $config['password']]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            return back()->withErrors(['backup' => 'Error al generar backup: ' . $process->getErrorOutput()]);
        }

        // Copy to backup_path if configured
        $backupPath = Setting::get('backup_path');
        if ($backupPath && is_dir($backupPath)) {
            @copy($tmpPath, rtrim($backupPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename);
        }

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }

    public function saveSettings(Request $request)
    {
        $fields = [
            'shop_name', 'shop_address', 'shop_phone', 'shop_nit',
            'invoice_footer', 'lan_ip', 'backup_path', 'thermal_printer_name',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::set($field, (string) $request->input($field, ''));
            }
        }

        return back()->with('success', 'Configuración guardada correctamente.');
    }

    public function testPrint()
    {
        try {
            (new ThermalPrinterService())->testPrint();
            return response()->json(['ok' => true, 'message' => 'Ticket de prueba enviado.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
