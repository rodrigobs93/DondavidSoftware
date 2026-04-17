<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    public function index()
    {
        $settings  = Setting::pluck('value', 'key')->toArray();
        $touchMode = Setting::get('touch_mode', '0') === '1';
        return view('backups.index', compact('settings', 'touchMode'));
    }

    public function export()
    {
        $config   = config('database.connections.pgsql');
        $filename = 'mipos_backup_' . now()->setTimezone('America/Bogota')->format('Y-m-d_His') . '.sql';
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
            'touch_mode',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::set($field, (string) $request->input($field, ''));
            }
        }

        if ($request->has('header_color')) {
            $color = (string) $request->input('header_color', '#111827');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                return back()->withErrors(['header_color' => 'Color no válido.']);
            }
            Setting::set('header_color', $color);
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

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:jpeg,png,gif,webp,svg', 'max:2048'],
        ]);

        $file = $request->file('logo');
        $isSvg = in_array(strtolower($file->getClientOriginalExtension()), ['svg'])
               || $file->getMimeType() === 'image/svg+xml';

        if ($isSvg) {
            $content = file_get_contents($file->getRealPath());
            $cleaned = self::sanitizeSvg($content);
            if ($cleaned === false) {
                return back()->withErrors(['logo' => 'El archivo SVG no es válido o contiene contenido inseguro.']);
            }
            $filename = 'logos/' . \Illuminate\Support\Str::uuid() . '.svg';
            Storage::disk('public')->put($filename, $cleaned);
            $path = $filename;
        } else {
            $path = $file->store('logos', 'public');
        }

        // Delete old logo after successful store
        $old = Setting::get('business_logo_path');
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        Setting::set('business_logo_path', $path);

        return back()->with('success', 'Logo actualizado correctamente.');
    }

    private static function sanitizeSvg(string $content): string|false
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($content, LIBXML_NONET);
        libxml_clear_errors();

        if (!$loaded || $doc->documentElement?->localName !== 'svg') {
            return false;
        }

        // Remove dangerous elements
        foreach (['script', 'foreignObject', 'iframe', 'object', 'embed'] as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $el) {
                $el->parentNode?->removeChild($el);
            }
        }

        // Remove event-handler attributes and javascript: URIs from every element
        $xpath = new \DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('//*')) as $el) {
            if (!($el instanceof \DOMElement)) {
                continue;
            }
            $remove = [];
            foreach ($el->attributes as $attr) {
                $name = strtolower($attr->nodeName);
                $val  = strtolower(trim($attr->nodeValue));
                if (str_starts_with($name, 'on')) {
                    $remove[] = $attr->nodeName;
                } elseif (in_array($name, ['href', 'xlink:href', 'src', 'action', 'formaction'])
                          && str_starts_with(ltrim($val), 'javascript:')) {
                    $remove[] = $attr->nodeName;
                }
            }
            foreach ($remove as $a) {
                $el->removeAttribute($a);
            }
        }

        return $doc->saveXML($doc->documentElement);
    }

    public function deleteLogo()
    {
        $path = Setting::get('business_logo_path');
        if ($path) {
            Storage::disk('public')->delete($path);
        }
        Setting::set('business_logo_path', '');

        return back()->with('success', 'Logo eliminado.');
    }
}
