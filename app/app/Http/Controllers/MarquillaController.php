<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\EscPosTicketRenderer;
use App\Services\ThermalPrinterService;
use Illuminate\Http\Request;

class MarquillaController extends Controller
{
    public function print(Request $request)
    {
        $validated = $request->validate([
            'lines'          => 'required|array|min:1|max:50',
            'lines.*.text'   => 'required|string|max:100',
            'lines.*.copies' => 'required|integer|min:1|max:20',
        ]);

        $shop     = Setting::shopInfo();
        $renderer = new EscPosTicketRenderer();
        $printer  = new ThermalPrinterService();

        $bytes = '';
        foreach ($validated['lines'] as $line) {
            for ($i = 0; $i < $line['copies']; $i++) {
                $bytes .= $renderer->renderMarquilla($shop, $line['text']);
            }
        }

        try {
            $printer->send($bytes);
            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
