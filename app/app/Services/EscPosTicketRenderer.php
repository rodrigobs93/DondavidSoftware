<?php

namespace App\Services;

class EscPosTicketRenderer
{
    private const WIDTH = 42;

    // ESC/POS command constants
    private const INIT            = "\x1B\x40";
    private const CUT             = "\x1D\x56\x41\x00";
    private const BOLD_ON         = "\x1B\x45\x01";
    private const BOLD_OFF        = "\x1B\x45\x00";
    private const ALIGN_LEFT      = "\x1B\x61\x00";
    private const ALIGN_CENTER    = "\x1B\x61\x01";
    private const LF              = "\n";

    public function render(array $payload): string
    {
        $out  = self::INIT;
        $shop     = $payload['shop'];
        $invoice  = $payload['invoice'];
        $customer = $payload['customer'];
        $items    = $payload['items'];
        $payments = $payload['payments'];

        // === Header ===
        $out .= self::ALIGN_CENTER;
        $out .= self::BOLD_ON . $this->enc(mb_strtoupper($shop['name'])) . self::LF . self::BOLD_OFF;
        $out .= $this->enc($shop['address']) . self::LF;
        if ($shop['phone']) $out .= 'Tel: ' . $this->enc($shop['phone']) . self::LF;
        if ($shop['nit'])   $out .= 'NIT: ' . $this->enc($shop['nit'])   . self::LF;
        $out .= $this->divider('=');

        // === Invoice number & date ===
        $out .= self::ALIGN_LEFT;
        $out .= "Factura N: {$invoice['consecutive']}" . self::LF;
        $out .= "Fecha: {$invoice['date']}  {$invoice['time']}" . self::LF;

        // === FE customer info (only when required) ===
        if ($invoice['requires_fe'] && !$customer['is_generic']) {
            $out .= $this->divider('-');
            $out .= 'Cliente: ' . $this->enc($customer['name']) . self::LF;
            if (!empty($customer['business_name'])) {
                $out .= 'Empresa: ' . $this->enc($customer['business_name']) . self::LF;
            }
            if ($customer['doc_label']) {
                $out .= 'Doc: ' . $this->enc($customer['doc_label']) . self::LF;
            }
        }
        $out .= $this->divider('-');

        // === Column headers ===
        $out .= $this->pad('DESCRIPCION', 24) . ' ' . $this->padL('CANT', 7) . ' ' . $this->padL('TOTAL', 9) . self::LF;
        $out .= $this->divider('-');

        // === Items ===
        foreach ($items as $item) {
            // Truncate in UTF-8 first (mb_* functions), then encode for printing
            $name = $item['product_name_snapshot'];
            if (mb_strlen($name) > 24) {
                $name = mb_substr($name, 0, 21) . '...';
            }
            $qty   = $item['formatted_quantity'];
            $total = $this->cop($item['line_total']);

            // pad() uses mb_strlen on UTF-8 name → correct display width; enc() result is single-byte CP850
            $out .= $this->enc($this->pad($name, 24)) . ' ' . $this->padL($qty, 7) . ' ' . $this->padL($total, 9) . self::LF;
        }
        $out .= $this->divider('-');

        // === Totals (ASCII amounts — enc() is a no-op but keeps code uniform) ===
        $out .= $this->twoCol('Subtotal:', $this->cop($invoice['subtotal']));
        if ((float) $invoice['delivery_fee'] > 0) {
            $out .= $this->twoCol('Domicilio:', $this->cop($invoice['delivery_fee']));
        }
        $out .= self::BOLD_ON . $this->twoCol('TOTAL:', $this->cop($invoice['total'])) . self::BOLD_OFF;
        $out .= $this->divider('=');

        // === Payments ===
        $out .= 'PAGOS:' . self::LF;
        foreach ($payments as $p) {
            $out .= $this->twoCol($p['method_label'], $this->cop($p['amount']));
        }
        $out .= $this->divider('-');
        $out .= self::BOLD_ON;
        $out .= $this->twoCol('TOTAL PAGADO:', $this->cop($invoice['paid_amount']));
        $out .= $this->twoCol('SALDO:', $this->cop($invoice['balance']));
        $out .= self::BOLD_OFF;
        $out .= $this->divider('=');

        // === FE status line ===
        $out .= self::ALIGN_CENTER . $this->enc($invoice['fe_label']) . self::LF;

        if (!empty($shop['footer'])) {
            $out .= $this->divider('-');
            $out .= $this->enc($shop['footer']) . self::LF;  // "¡Gracias..." has ¡
        }

        $out .= $this->divider('=');
        $out .= self::LF . self::LF . self::LF;
        $out .= self::CUT;

        return $out;
    }

    public function renderQuickSale(array $payload): string
    {
        $out  = self::INIT;
        $shop = $payload['shop'];
        $r    = $payload['receipt'];

        // === Header (same as invoice) ===
        $out .= self::ALIGN_CENTER;
        $out .= self::BOLD_ON . $this->enc(mb_strtoupper($shop['name'])) . self::LF . self::BOLD_OFF;
        $out .= $this->enc($shop['address']) . self::LF;
        if ($shop['phone']) $out .= 'Tel: ' . $this->enc($shop['phone']) . self::LF;
        if ($shop['nit'])   $out .= 'NIT: ' . $this->enc($shop['nit'])   . self::LF;
        $out .= $this->divider('=');

        // === Receipt type + number ===
        $out .= self::BOLD_ON . $this->enc('VENTA RAPIDA') . self::LF . self::BOLD_OFF;
        $out .= self::ALIGN_LEFT;
        $out .= "Recibo N: {$r['number']}" . self::LF;
        $out .= "Fecha: {$r['date']}  {$r['time']}" . self::LF;
        $out .= $this->divider('=');

        // === Total ===
        $out .= self::BOLD_ON . $this->twoCol('TOTAL:', $this->cop($r['total'])) . self::BOLD_OFF;
        $out .= $this->divider('=');

        // === Payment ===
        $out .= 'PAGO:' . self::LF;
        $out .= $this->twoCol($r['method_label'], $this->cop($r['total']));

        if ($r['method'] === 'CASH') {
            $out .= $this->divider('-');
            $out .= $this->twoCol('Recibido:', $this->cop($r['cash_received']));
            $out .= self::BOLD_ON . $this->twoCol('CAMBIO:', $this->cop($r['change_amount'])) . self::BOLD_OFF;
        }

        $out .= $this->divider('=');

        if (!empty($shop['footer'])) {
            $out .= self::ALIGN_CENTER . $this->enc($shop['footer']) . self::LF;  // "¡Gracias..." has ¡
            $out .= $this->divider('=');
        }

        $out .= self::LF . self::LF . self::LF;
        $out .= self::CUT;

        return $out;
    }

    private function divider(string $char = '-'): string
    {
        return str_repeat($char, self::WIDTH) . self::LF;
    }

    private function twoCol(string $left, string $right): string
    {
        $rLen    = mb_strlen($right);
        $lMax    = self::WIDTH - $rLen - 1;
        if (mb_strlen($left) > $lMax) {
            $left = mb_substr($left, 0, $lMax);
        }
        $pad = self::WIDTH - mb_strlen($left) - $rLen;
        return $left . str_repeat(' ', max(1, $pad)) . $right . self::LF;
    }

    private function pad(string $s, int $len): string
    {
        $pad = $len - mb_strlen($s);
        return $s . ($pad > 0 ? str_repeat(' ', $pad) : '');
    }

    private function padL(string $s, int $len): string
    {
        $pad = $len - mb_strlen($s);
        return ($pad > 0 ? str_repeat(' ', $pad) : '') . $s;
    }

    /** Format as COP: $38.000 (no decimals, dot thousands separator) */
    private function cop(string|float|int $amount): string
    {
        $n = (int) round((float) $amount);
        return '$' . number_format($n, 0, ',', '.');
    }

    /**
     * Sanitize UTF-8 text to plain ASCII for thermal printer output.
     * Replaces accented/special Spanish characters with ASCII equivalents,
     * removes ¡ and ¿, and strips any remaining non-ASCII bytes.
     */
    private function enc(string $text): string
    {
        return self::sanitizeForPrinter($text);
    }

    private static function sanitizeForPrinter(string $text): string
    {
        $map = [
            'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a',
            'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
            'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
            'ñ'=>'n','ç'=>'c',
            'Á'=>'A','À'=>'A','Ä'=>'A','Â'=>'A','Ã'=>'A',
            'É'=>'E','È'=>'E','Ë'=>'E','Ê'=>'E',
            'Í'=>'I','Ì'=>'I','Ï'=>'I','Î'=>'I',
            'Ó'=>'O','Ò'=>'O','Ö'=>'O','Ô'=>'O','Õ'=>'O',
            'Ú'=>'U','Ù'=>'U','Ü'=>'U','Û'=>'U',
            'Ñ'=>'N','Ç'=>'C',
            '¡'=>'','¿'=>'',
        ];
        $text = strtr($text, $map);
        // Strip any remaining non-ASCII characters
        return preg_replace('/[^\x00-\x7F]/', '', $text);
    }
}
