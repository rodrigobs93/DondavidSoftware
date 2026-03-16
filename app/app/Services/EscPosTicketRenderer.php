<?php

namespace App\Services;

class EscPosTicketRenderer
{
    // ── Font A (default, wide) ────────────────────────────────────────────────
    private const FONT_A  = "\x1B\x4D\x00";
    private const WIDTH_A = 42;               // printable columns at font A

    // ── Font B (smaller body font) ────────────────────────────────────────────
    private const FONT_B  = "\x1B\x4D\x01";
    private const WIDTH_B = 56;               // approx — calibrate on real printer

    // ── ESC/POS commands ──────────────────────────────────────────────────────
    private const INIT         = "\x1B\x40";
    private const CUT          = "\x1D\x56\x41\x00";
    private const BOLD_ON      = "\x1B\x45\x01";
    private const BOLD_OFF     = "\x1B\x45\x00";
    private const ALIGN_LEFT   = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const SIZE_NORMAL  = "\x1D\x21\x00";   // 1×1 character size
    private const LF           = "\n";

    // ── Minimum invoice ticket length (lines) ─────────────────────────────────
    // At ~3.5 mm default line spacing: 20 cm ≈ 57 lines.  Tune if needed.
    private const MIN_INVOICE_LINES = 57;

    // ── Logo target width (dots) ──────────────────────────────────────────────
    // 384 dots ≈ 48 mm on a 203 DPI printer, centered on 80 mm (576-dot) paper.
    private const LOGO_TARGET_W = 384;
    private const LOGO_MAX_H    = 200;   // clamp logo height in dots
    private const PAPER_DOTS    = 576;   // printable width of 80 mm roll at 203 DPI

    // ─────────────────────────────────────────────────────────────────────────
    // Public: render invoice ticket
    // ─────────────────────────────────────────────────────────────────────────
    public function render(array $payload): string
    {
        $out      = self::INIT . self::FONT_B;
        $shop     = $payload['shop'];
        $invoice  = $payload['invoice'];
        $customer = $payload['customer'];
        $items    = $payload['items'];
        $payments = $payload['payments'];

        // ── Logo (raster only; SVG silently skipped) ──────────────────────────
        $out .= $this->renderLogo($shop['logo_path'] ?? '');

        // ── Shop header (font A, bold name) ───────────────────────────────────
        $out .= self::FONT_A . self::ALIGN_CENTER;
        $out .= self::BOLD_ON . $this->enc(mb_strtoupper($shop['name'])) . self::LF . self::BOLD_OFF;

        // Address / phone / NIT: font B, word-wrapped
        $out .= self::FONT_B;
        foreach (explode(self::LF, wordwrap($this->enc($shop['address']), self::WIDTH_A, self::LF, true)) as $line) {
            $out .= $line . self::LF;
        }
        if ($shop['phone']) $out .= 'Tel: ' . $this->enc($shop['phone']) . self::LF;
        if ($shop['nit'])   $out .= 'NIT: ' . $this->enc($shop['nit'])   . self::LF;
        $out .= $this->divider('=', self::WIDTH_A);

        // ── Invoice number & date ─────────────────────────────────────────────
        $out .= self::ALIGN_LEFT;
        $out .= "Factura N: {$invoice['consecutive']}" . self::LF;
        $out .= "Fecha: {$invoice['date']}  {$invoice['time']}" . self::LF;

        // ── FE customer info (only when required and non-generic) ─────────────
        if ($invoice['requires_fe'] && !$customer['is_generic']) {
            $out .= $this->divider('-', self::WIDTH_B);
            $out .= 'Cliente: ' . $this->enc($customer['name']) . self::LF;
            if (!empty($customer['business_name'])) {
                $out .= 'Empresa: ' . $this->enc($customer['business_name']) . self::LF;
            }
            if ($customer['doc_label']) {
                $out .= 'Doc: ' . $this->enc($customer['doc_label']) . self::LF;
            }
        }
        $out .= $this->divider('-', self::WIDTH_B);

        // ── Column headers (font B) ───────────────────────────────────────────
        // Layout (font B, 52 active chars): name(20) qty(6) price(12) total(11) + 3 spaces = 52
        $out .= $this->pad('DESCRIPCION', 20)
              . ' ' . $this->padL('CANT',   6)
              . ' ' . $this->padL('P.UNIT', 12)
              . ' ' . $this->padL('TOTAL',  11)
              . self::LF;
        $out .= $this->divider('-', self::WIDTH_B);

        // ── Items ─────────────────────────────────────────────────────────────
        foreach ($items as $item) {
            $name = $item['product_name_snapshot'];
            if (mb_strlen($name) > 20) {
                $name = mb_substr($name, 0, 17) . '...';
            }
            $qty   = $item['formatted_quantity'];
            $price = $this->cop($item['unit_price']);
            $total = $this->cop($item['line_total']);

            $out .= $this->enc($this->pad($name, 20))
                  . ' ' . $this->padL($qty,   6)
                  . ' ' . $this->padL($price, 12)
                  . ' ' . $this->padL($total, 11)
                  . self::LF;
        }
        $out .= $this->divider('-', self::WIDTH_B);

        // ── Totals (font A + bold for prominence) ─────────────────────────────
        $out .= self::FONT_A . self::BOLD_ON;
        $out .= $this->twoCol('Subtotal:', $this->cop($invoice['subtotal']), self::WIDTH_A);
        if ((float) $invoice['delivery_fee'] > 0) {
            $out .= $this->twoCol('Domicilio:', $this->cop($invoice['delivery_fee']), self::WIDTH_A);
        }
        $out .= $this->twoCol('TOTAL:', $this->cop($invoice['total']), self::WIDTH_A);
        $out .= self::BOLD_OFF;
        $out .= $this->divider('=', self::WIDTH_A);

        // ── Payments (font B) ─────────────────────────────────────────────────
        $out .= self::FONT_B . 'PAGOS:' . self::LF;
        foreach ($payments as $p) {
            $out .= $this->twoCol($p['method_label'], $this->cop($p['amount']), self::WIDTH_B);
        }
        $out .= $this->divider('-', self::WIDTH_B);

        // Paid/balance totals: back to font A + bold
        $out .= self::FONT_A . self::BOLD_ON;
        $out .= $this->twoCol('TOTAL PAGADO:', $this->cop($invoice['paid_amount']), self::WIDTH_A);
        $out .= $this->twoCol('SALDO:',        $this->cop($invoice['balance']),     self::WIDTH_A);
        $out .= self::BOLD_OFF;
        $out .= $this->divider('=', self::WIDTH_A);

        // FE status line REMOVED per requirements

        // ── Footer ────────────────────────────────────────────────────────────
        if (!empty($shop['footer'])) {
            $out .= self::FONT_B . self::ALIGN_CENTER;
            $footerEnc = $this->enc($shop['footer']);
            foreach (explode(self::LF, wordwrap($footerEnc, self::WIDTH_B, self::LF, true)) as $line) {
                $out .= $line . self::LF;
            }
            $out .= $this->divider('=', self::WIDTH_B);
        }

        // ── Minimum ticket length padding ─────────────────────────────────────
        $linesAlready = substr_count($out, self::LF);
        $extraLines   = max(0, self::MIN_INVOICE_LINES - $linesAlready);
        $out .= str_repeat(self::LF, $extraLines);

        $out .= self::LF . self::LF . self::LF;
        $out .= self::CUT;

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public: render quick-sale ticket
    // ─────────────────────────────────────────────────────────────────────────
    public function renderQuickSale(array $payload): string
    {
        $out  = self::INIT;
        $shop = $payload['shop'];
        $r    = $payload['receipt'];

        // ── Header (font A for shop name, font B for details) ─────────────────
        $out .= self::FONT_A . self::ALIGN_CENTER;
        $out .= self::BOLD_ON . $this->enc(mb_strtoupper($shop['name'])) . self::LF . self::BOLD_OFF;
        $out .= self::FONT_B;
        $out .= $this->enc($shop['address']) . self::LF;
        if ($shop['phone']) $out .= 'Tel: ' . $this->enc($shop['phone']) . self::LF;
        if ($shop['nit'])   $out .= 'NIT: ' . $this->enc($shop['nit'])   . self::LF;
        $out .= $this->divider('=', self::WIDTH_A);

        // ── Receipt number & date ("VENTA RÁPIDA" title REMOVED) ─────────────
        $out .= self::ALIGN_LEFT;
        $out .= "Recibo N: {$r['number']}" . self::LF;
        $out .= "Fecha: {$r['date']}  {$r['time']}" . self::LF;
        $out .= $this->divider('=', self::WIDTH_B);

        // ── Total ─────────────────────────────────────────────────────────────
        $out .= self::FONT_A . self::BOLD_ON
              . $this->twoCol('TOTAL:', $this->cop($r['total']), self::WIDTH_A)
              . self::BOLD_OFF;
        $out .= $this->divider('=', self::WIDTH_A);

        // ── Payment ───────────────────────────────────────────────────────────
        $out .= self::FONT_B . 'PAGO:' . self::LF;
        $out .= $this->twoCol($r['method_label'], $this->cop($r['total']), self::WIDTH_B);

        if ($r['method'] === 'CASH') {
            $out .= $this->divider('-', self::WIDTH_B);
            $out .= $this->twoCol('Recibido:', $this->cop($r['cash_received']), self::WIDTH_B);
            $out .= self::FONT_A . self::BOLD_ON
                  . $this->twoCol('CAMBIO:', $this->cop($r['change_amount']), self::WIDTH_A)
                  . self::BOLD_OFF;
        }

        $out .= $this->divider('=', self::WIDTH_A);

        if (!empty($shop['footer'])) {
            $out .= self::FONT_B . self::ALIGN_CENTER . $this->enc($shop['footer']) . self::LF;
            $out .= $this->divider('=', self::WIDTH_B);
        }

        $out .= self::LF . self::LF . self::LF;
        $out .= self::CUT;

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: render logo bitmap (raster only, GS v 0 command)
    // Returns empty string on any error or unsupported format (SVG).
    // ─────────────────────────────────────────────────────────────────────────
    private function renderLogo(string $logoPath): string
    {
        if (empty($logoPath)) {
            return '';
        }

        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            return '';   // GD cannot rasterize SVG
        }

        $fullPath = storage_path('app/public/' . $logoPath);
        if (!file_exists($fullPath)) {
            return '';
        }

        $data = @file_get_contents($fullPath);
        if ($data === false) {
            return '';
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return '';
        }

        $srcW  = imagesx($src);
        $srcH  = imagesy($src);
        $ratio = self::LOGO_TARGET_W / $srcW;
        $dstW  = (int) ($srcW * $ratio);
        $dstH  = (int) ($srcH * $ratio);

        // Clamp height so logo doesn't take up the whole ticket
        if ($dstH > self::LOGO_MAX_H) {
            $dstH = self::LOGO_MAX_H;
            $dstW = (int) ($srcW * self::LOGO_MAX_H / $srcH);
        }

        // Width must be a multiple of 8 (one byte = 8 dots)
        $byteW = (int) ceil($dstW / 8);
        $dstW  = $byteW * 8;

        // Resize onto a white canvas
        $dst = imagecreatetruecolor($dstW, $dstH);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        // Convert to 1-bit row data (MSB first, 1 = black dot)
        $rowData = '';
        for ($y = 0; $y < $dstH; $y++) {
            for ($bx = 0; $bx < $byteW; $bx++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x    = $bx * 8 + $bit;
                    $rgb  = imagecolorat($dst, $x, $y);
                    $r    = ($rgb >> 16) & 0xFF;
                    $g    = ($rgb >> 8)  & 0xFF;
                    $b    = $rgb         & 0xFF;
                    $luma = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                    if ($luma < 128) {
                        $byte |= (0x80 >> $bit);
                    }
                }
                $rowData .= chr($byte);
            }
        }
        imagedestroy($dst);

        // Center the logo on the paper by prepending 0x00 padding bytes per row
        $leftPad      = max(0, (int) (floor((self::PAPER_DOTS - $dstW) / 2)));
        $leftPadBytes = (int) ceil($leftPad / 8);
        if ($leftPadBytes > 0) {
            $padStr      = str_repeat("\x00", $leftPadBytes);
            $totalByteW  = $leftPadBytes + $byteW;
            $padded      = '';
            for ($y = 0; $y < $dstH; $y++) {
                $padded .= $padStr . substr($rowData, $y * $byteW, $byteW);
            }
            $rowData = $padded;
            $byteW   = $totalByteW;
        }

        // Build GS v 0 command: 1D 76 30 m xL xH yL yH data
        $xL = $byteW & 0xFF;
        $xH = ($byteW >> 8) & 0xFF;
        $yL = $dstH & 0xFF;
        $yH = ($dstH >> 8) & 0xFF;

        return self::ALIGN_CENTER
             . "\x1D\x76\x30\x00" . chr($xL) . chr($xH) . chr($yL) . chr($yH)
             . $rowData
             . self::LF;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function divider(string $char, int $width): string
    {
        return str_repeat($char, $width) . self::LF;
    }

    private function twoCol(string $left, string $right, int $width): string
    {
        $rLen = mb_strlen($right);
        $lMax = $width - $rLen - 1;
        if (mb_strlen($left) > $lMax) {
            $left = mb_substr($left, 0, $lMax);
        }
        $pad = $width - mb_strlen($left) - $rLen;
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
        return preg_replace('/[^\x00-\x7F]/', '', $text);
    }
}
