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
    private const SIZE_NORMAL  = "\x1D\x21\x00";   // 1×1 character size (GS !)
    private const SIZE_DH      = "\x1B\x21\x18";   // double-height + bold (ESC ! 0x18)
    private const SIZE_NML     = "\x1B\x21\x00";   // normal reset (ESC ! 0x00) — re-apply FONT_x after
    private const LF           = "\n";

    // ── Minimum ticket length (lines) ────────────────────────────────────────
    private const MIN_INVOICE_LINES   = 46;  // 16 cm / 3.5 mm per line
    private const MIN_MARQUILLA_LINES = 14;  // ~5 cm / 3.5 mm per line

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
        // Layout fills full WIDTH_B=56: name(20)+sp+qty(9)+sp+price(12)+sp+total(12) = 56
        $out .= $this->pad('DESCRIPCION', 20)
              . ' ' . $this->padL('CANT',   9)
              . ' ' . $this->padL('P.UNIT', 12)
              . ' ' . $this->padL('TOTAL',  12)
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
                  . ' ' . $this->padL($qty,   9)
                  . ' ' . $this->padL($price, 12)
                  . ' ' . $this->padL($total, 12)
                  . self::LF;
        }
        $out .= $this->divider('-', self::WIDTH_B);

        // ── Totals (font B + bold, WIDTH_B = full 80 mm width) ───────────────
        $out .= self::FONT_B . self::BOLD_ON;
        $out .= $this->twoCol('Subtotal:', $this->cop($invoice['subtotal']), self::WIDTH_B);
        if ((float) $invoice['delivery_fee'] > 0) {
            $out .= $this->twoCol('Domicilio:', $this->cop($invoice['delivery_fee']), self::WIDTH_B);
        }
        $out .= $this->twoCol('TOTAL:', $this->cop($invoice['total']), self::WIDTH_B);
        $out .= self::BOLD_OFF;
        $out .= $this->divider('=', self::WIDTH_B);

        // ── Payments (font B) ─────────────────────────────────────────────────
        $out .= self::FONT_B . 'PAGOS:' . self::LF;
        foreach ($payments as $p) {
            $out .= $this->twoCol($p['method_label'], $this->cop($p['amount']), self::WIDTH_B);
        }
        $out .= $this->divider('-', self::WIDTH_B);

        // Paid/balance totals: font B + bold, full-width
        $out .= self::FONT_B . self::BOLD_ON;
        $out .= $this->twoCol('TOTAL PAGADO:', $this->cop($invoice['paid_amount']), self::WIDTH_B);
        $out .= $this->twoCol('SALDO:',        $this->cop($invoice['balance']),     self::WIDTH_B);
        $out .= self::BOLD_OFF;
        $out .= $this->divider('=', self::WIDTH_B);

        // FE status line REMOVED per requirements

        // ── Footer (build separately so we can push it toward the bottom) ────
        $footerBlock = '';
        $footerLines = 0;
        if (!empty($shop['footer'])) {
            $footerEnc = $this->enc($shop['footer']);
            $footerBlock .= self::FONT_B . self::ALIGN_CENTER;
            foreach (explode(self::LF, wordwrap($footerEnc, self::WIDTH_B, self::LF, true)) as $line) {
                $footerBlock .= $line . self::LF;
                $footerLines++;
            }
            $footerBlock .= $this->divider('=', self::WIDTH_B);
            $footerLines++;   // divider counts as one line
        }

        // ── Padding: fill lines so footer lands near the tear-off edge ────────
        $linesBeforeFooter  = substr_count($out, self::LF);
        $targetBeforeFooter = self::MIN_INVOICE_LINES - $footerLines;
        $prePad  = max(0, $targetBeforeFooter - $linesBeforeFooter);
        $out .= str_repeat(self::LF, $prePad);

        $out .= $footerBlock;

        // Safety: ensure total is at least MIN_INVOICE_LINES
        $totalLines = substr_count($out, self::LF);
        $postPad    = max(0, self::MIN_INVOICE_LINES - $totalLines);
        $out .= str_repeat(self::LF, $postPad);

        $out .= self::LF . self::LF . self::LF;
        $out .= self::CUT;

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public: render quick-sale ticket
    // ─────────────────────────────────────────────────────────────────────────
    public function renderQuickSale(array $payload): string
    {
        $out  = self::INIT . self::FONT_B;
        $shop = $payload['shop'];
        $r    = $payload['receipt'];

        // ── Logo (raster only; SVG silently skipped) ──────────────────────────
        $out .= $this->renderLogo($shop['logo_path'] ?? '');

        // ── Header (font A for shop name, font B for details) ─────────────────
        $out .= self::FONT_A . self::ALIGN_CENTER;
        $out .= self::BOLD_ON . $this->enc(mb_strtoupper($shop['name'])) . self::LF . self::BOLD_OFF;
        $out .= self::FONT_B;
        foreach (explode(self::LF, wordwrap($this->enc($shop['address']), self::WIDTH_A, self::LF, true)) as $line) {
            $out .= $line . self::LF;
        }
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
            $out .= self::FONT_B . self::ALIGN_CENTER;
            foreach (explode(self::LF, wordwrap($this->enc($shop['footer']), self::WIDTH_B, self::LF, true)) as $line) {
                $out .= $line . self::LF;
            }
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
    private function renderLogo(string $logoPath, int $targetW = self::LOGO_TARGET_W, int $maxH = self::LOGO_MAX_H): string
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
        $ratio = $targetW / $srcW;
        $dstW  = (int) ($srcW * $ratio);
        $dstH  = (int) ($srcH * $ratio);

        // Clamp height so logo doesn't take up the whole ticket
        if ($dstH > $maxH) {
            $dstH = $maxH;
            $dstW = (int) ($srcW * $maxH / $srcH);
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

        // ALIGN_LEFT ensures the image starts from the true left edge;
        // centering is achieved by the prepended 0x00 bytes per row (not ESC a).
        return self::ALIGN_LEFT
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

    // ─────────────────────────────────────────────────────────────────────────
    // Public: render "sacar el cobro" cartera summary ticket
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render a collection-summary ticket for a customer's pending invoices.
     *
     * Payload:
     *   shop:     [name, address, phone, nit, logo_path, footer]
     *   customer: [name, business_name]
     *   invoices: array of [consecutive, date (d/m/Y), total, balance]
     *   totalDebt:     string (sum of invoice balances)
     *   creditBalance: string
     *   netAmount:     string (totalDebt - creditBalance)
     *   printDate:     string (dd/mm/yyyy HH:mm)
     */
    public function renderCarteraResumen(array $payload): string
    {
        $out      = self::INIT . self::FONT_B;
        $shop     = $payload['shop'];
        $customer = $payload['customer'];
        $invoices = $payload['invoices'];

        // ── Logo ─────────────────────────────────────────────────────────────
        $out .= $this->renderLogo($shop['logo_path'] ?? '');

        // ── Shop header ───────────────────────────────────────────────────────
        $out .= self::FONT_A . self::ALIGN_CENTER;
        $out .= self::BOLD_ON . $this->enc(mb_strtoupper($shop['name'])) . self::LF . self::BOLD_OFF;
        $out .= self::FONT_B;
        foreach (explode(self::LF, wordwrap($this->enc($shop['address']), self::WIDTH_A, self::LF, true)) as $line) {
            $out .= $line . self::LF;
        }
        if ($shop['phone']) $out .= 'Tel: ' . $this->enc($shop['phone']) . self::LF;
        if ($shop['nit'])   $out .= 'NIT: ' . $this->enc($shop['nit'])   . self::LF;
        $out .= $this->divider('=', self::WIDTH_A);

        // ── Ticket title & date ───────────────────────────────────────────────
        $out .= self::ALIGN_LEFT;
        $out .= self::FONT_A . self::BOLD_ON . 'COBRO' . self::BOLD_OFF . self::FONT_B
              . '  ' . $payload['printDate'] . self::LF;

        // ── Customer ──────────────────────────────────────────────────────────
        $out .= $this->divider('-', self::WIDTH_B);
        $out .= 'Cliente: ' . $this->enc($customer['name']) . self::LF;
        if (!empty($customer['business_name'])) {
            $out .= 'Empresa: ' . $this->enc($customer['business_name']) . self::LF;
        }
        $out .= $this->divider('=', self::WIDTH_B);

        // ── Invoice table header ──────────────────────────────────────────────
        // Cols (Font B, 56): consec(6) + date(9) + total(right,19) + balance(right,19) = 53
        $out .= $this->pad('#FACT', 6)
              . $this->pad(' FECHA', 10)
              . $this->padL('TOTAL', 19)
              . $this->padL('SALDO', 18)
              . self::LF;
        $out .= $this->divider('-', self::WIDTH_B);

        // ── Invoice rows ──────────────────────────────────────────────────────
        foreach ($invoices as $inv) {
            $consec  = $this->pad('#' . $inv['consecutive'], 6);
            $date    = $this->pad(' ' . $inv['date'], 10);
            $total   = $this->padL($this->cop($inv['total']),   19);
            $balance = $this->padL($this->cop($inv['balance']), 18);
            $out    .= $this->enc($consec) . $date . $total . $balance . self::LF;
        }
        $out .= $this->divider('=', self::WIDTH_B);

        // ── Totals ────────────────────────────────────────────────────────────
        $out .= $this->twoCol('Deuda total:', $this->cop($payload['totalDebt']), self::WIDTH_B);
        if (bccomp($payload['creditBalance'], '0', 2) > 0) {
            $out .= $this->twoCol('Saldo a favor:', $this->cop($payload['creditBalance']), self::WIDTH_B);
        }
        $out .= self::BOLD_ON;
        $out .= $this->twoCol('NETO A COBRAR:', $this->cop($payload['netAmount']), self::WIDTH_B);
        $out .= self::BOLD_OFF;
        $out .= $this->divider('=', self::WIDTH_B);

        // ── Footer ────────────────────────────────────────────────────────────
        if (!empty($shop['footer'])) {
            $out .= self::FONT_B . self::ALIGN_CENTER;
            foreach (explode(self::LF, wordwrap($this->enc($shop['footer']), self::WIDTH_B, self::LF, true)) as $line) {
                $out .= $line . self::LF;
            }
            $out .= $this->divider('=', self::WIDTH_B);
        }

        $out .= self::LF . self::LF . self::LF;
        $out .= self::CUT;

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public: render one marquilla (product label) — includes INIT + CUT
    // ─────────────────────────────────────────────────────────────────────────
    public function renderMarquilla(array $shop, string $labelText): string
    {
        // SIZE_DWDH: ESC ! 0x38 = double-width (bit5) + double-height (bit4) + bold (bit3)
        // With double-width, Font A's 42 columns become 21 usable columns.
        $SIZE_DWDH  = "\x1B\x21\x38";
        $CHAR_SPC   = "\x1B\x20\x03";   // ESC SP 3 — 3 extra dots between chars
        $CHAR_SPC_0 = "\x1B\x20\x00";   // ESC SP 0 — restore normal spacing
        $LABEL_WRAP = 21;                // columns available with double-width Font A

        // Larger logo for labels: 320 dots ≈ 40 mm wide, max 120 dots tall (~15 mm)
        $out  = self::INIT . self::FONT_A;
        $out .= $this->renderLogo($shop['logo_path'] ?? '', 320, 120);

        $out .= self::ALIGN_CENTER;
        $out .= $this->divider('=', self::WIDTH_A);

        // Label text — double-width + double-height + bold, extra letter spacing
        // wordwrap at LABEL_WRAP to prevent mid-word cuts at the new width
        $sanitized = $this->enc($labelText);
        $wrapped   = explode(self::LF, wordwrap($sanitized, $LABEL_WRAP, self::LF, false));
        $out .= $SIZE_DWDH . $CHAR_SPC;
        foreach ($wrapped as $line) {
            $out .= $line . self::LF;
        }
        $out .= self::SIZE_NML . $CHAR_SPC_0;

        $out .= $this->divider('=', self::WIDTH_A);

        // Auto length: no minimum padding — just a small feed before the cut
        $out .= str_repeat(self::LF, 3);
        $out .= self::CUT;
        return $out;
    }
}
