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

    // ── Side margin (left padding, in chars) ─────────────────────────────────
    // Shifts every LEFT-aligned line right by N chars AND shrinks the usable
    // width of dividers / twoCol rows / items / signature by the same N so the
    // right edge stays flush. Centered sections (shop header, footer) are
    // auto-centered by the printer and keep the full paper width.
    // Bump this constant — the ONLY knob you need to touch — if a different
    // printer model prints too close to the left edge. 0 = no padding.
    private const SIDE_MARGIN = 2;

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

        // ── Invoice body — centered as a block on the paper ──────────────────
        // BODY_WIDTH < WIDTH_B so ALIGN_CENTER produces a small symmetric
        // margin on each side. The items table uses the same width as the
        // info/totals/payments band so the whole ticket has one consistent
        // centered column. Bump $bodyWidth — the only knob — to widen or
        // narrow the entire centered block.
        $bodyWidth   = self::WIDTH_B - 2;                           // 54 of 56
        $tableWidth  = $bodyWidth;
        $colQty = 7; $colPrice = 11; $colTotal = 11;
        $colName = $tableWidth - $colQty - $colPrice - $colTotal - 3;

        $out .= self::ALIGN_CENTER;

        // Invoice number & date
        $out .= $this->centeredLine("Factura N: {$invoice['consecutive']}", $bodyWidth);
        $out .= $this->centeredLine("Fecha: {$invoice['date']}  {$invoice['time']}", $bodyWidth);

        // Customer info — always printed (name + business name when present).
        // The Doc line is fiscal, so it stays limited to FE invoices for a
        // non-generic customer. Long values wrap (no mid-word cuts).
        $out .= $this->centeredDivider('-', $bodyWidth);
        $customerName = $customer['is_generic'] ? 'GENERICO' : $this->enc($customer['name']);
        $out .= $this->centeredWrapped('Cliente: ' . $customerName, $bodyWidth);
        if (!empty($customer['business_name'])) {
            $out .= $this->centeredWrapped('Empresa: ' . $this->enc($customer['business_name']), $bodyWidth);
        }
        if ($invoice['requires_fe'] && !$customer['is_generic'] && !empty($customer['doc_label'])) {
            $out .= $this->centeredWrapped('Doc: ' . $this->enc($customer['doc_label']), $bodyWidth);
        }
        $out .= $this->centeredDivider('-', $bodyWidth);

        // Items table (narrower than body for visual hierarchy)
        $out .= str_repeat('-', $tableWidth) . self::LF;
        $out .= $this->pad('DESCRIPCION', $colName)
              . ' ' . $this->padL('CANT',   $colQty)
              . ' ' . $this->padL('P.UNIT', $colPrice)
              . ' ' . $this->padL('TOTAL',  $colTotal) . self::LF;
        $out .= str_repeat('-', $tableWidth) . self::LF;

        foreach ($items as $item) {
            $name = $item['product_name_snapshot'];
            if (mb_strlen($name) > $colName) {
                $name = mb_substr($name, 0, $colName - 3) . '...';
            }
            $qty   = $item['formatted_quantity'];
            $price = $this->cop($item['unit_price']);
            $total = $this->cop($item['line_total']);

            $out .= $this->enc($this->pad($name, $colName))
                  . ' ' . $this->padL($qty,   $colQty)
                  . ' ' . $this->padL($price, $colPrice)
                  . ' ' . $this->padL($total, $colTotal) . self::LF;
        }
        $out .= str_repeat('-', $tableWidth) . self::LF;

        // Totals (font B + bold, body-width centered)
        $out .= self::FONT_B . self::BOLD_ON;
        $out .= $this->centeredTwoCol('Subtotal:', $this->cop($invoice['subtotal']), $bodyWidth);
        if ((float) $invoice['delivery_fee'] > 0) {
            $out .= $this->centeredTwoCol('Domicilio:', $this->cop($invoice['delivery_fee']), $bodyWidth);
        }
        $out .= $this->centeredTwoCol('TOTAL:', $this->cop($invoice['total']), $bodyWidth);
        $out .= self::BOLD_OFF;
        $out .= $this->centeredDivider('=', $bodyWidth);

        // Payments (font B)
        $out .= self::FONT_B . $this->centeredLine('PAGOS:', $bodyWidth);
        foreach ($payments as $p) {
            $out .= $this->centeredTwoCol($p['method_label'], $this->cop($p['amount']), $bodyWidth);
        }
        $out .= $this->centeredDivider('-', $bodyWidth);

        // Paid/balance totals: font B + bold
        $out .= self::FONT_B . self::BOLD_ON;
        $out .= $this->centeredTwoCol('TOTAL PAGADO:', $this->cop($invoice['paid_amount']), $bodyWidth);
        $out .= $this->centeredTwoCol('SALDO:',        $this->cop($invoice['balance']),     $bodyWidth);
        $out .= self::BOLD_OFF;
        $out .= $this->centeredDivider('=', $bodyWidth);

        // FE status line REMOVED per requirements

        // ── Bottom block (signature + optional footer) ────────────────────────
        // Built separately so we can push it toward the tear-off edge with
        // pre-padding, exactly like the footer used to be handled. The
        // signature now lives down here (delivery proof at the bottom of the
        // ticket, where the customer naturally signs).
        $bottomBlock = '';
        $bottomLines = 0;

        // Signature
        $bottomBlock .= self::FONT_B . self::ALIGN_CENTER;
        $bottomBlock .= self::LF;                                           $bottomLines++;
        $bottomBlock .= $this->centeredLine('Firma recibido:', $bodyWidth); $bottomLines++;
        $bottomBlock .= self::LF;                                           $bottomLines++;
        $bottomBlock .= $this->centeredLine(str_repeat('_', $bodyWidth), $bodyWidth); $bottomLines++;
        $bottomBlock .= self::LF;                                           $bottomLines++;

        // Footer (optional shop slogan / thank-you, full-paper centered)
        if (!empty($shop['footer'])) {
            $footerEnc = $this->enc($shop['footer']);
            foreach (explode(self::LF, wordwrap($footerEnc, self::WIDTH_B, self::LF, true)) as $line) {
                $bottomBlock .= $line . self::LF;
                $bottomLines++;
            }
            $bottomBlock .= $this->divider('=', self::WIDTH_B);
            $bottomLines++;
        }

        // ── Padding: fill lines so the bottom block lands near the tear-off ──
        $linesBeforeBottom  = substr_count($out, self::LF);
        $targetBeforeBottom = self::MIN_INVOICE_LINES - $bottomLines;
        $prePad  = max(0, $targetBeforeBottom - $linesBeforeBottom);
        $out .= str_repeat(self::LF, $prePad);

        $out .= $bottomBlock;

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

        // ── Receipt body — centered as a block on the paper (matches invoice) ─
        // Two band widths: $bodyWidth for Font B sections, $bodyWidthA for
        // Font A sections (TOTAL, CAMBIO). They're sized so each band has
        // roughly the same physical width on paper despite the font swap.
        $bodyWidth  = self::WIDTH_B - 2;     // 54 of 56 (Font B)
        $bodyWidthA = self::WIDTH_A - 2;     // 40 of 42 (Font A)

        $out .= self::FONT_B . self::ALIGN_CENTER;

        // Recibo number & date
        $out .= $this->centeredLine("Recibo N: {$r['number']}", $bodyWidth);
        $out .= $this->centeredLine("Fecha: {$r['date']}  {$r['time']}", $bodyWidth);
        $out .= $this->centeredDivider('=', $bodyWidth);

        // TOTAL (Font A + bold for emphasis)
        $out .= self::FONT_A . self::BOLD_ON;
        $out .= $this->centeredTwoCol('TOTAL:', $this->cop($r['total']), $bodyWidthA);
        $out .= self::BOLD_OFF;
        $out .= $this->centeredDivider('=', $bodyWidthA);

        // Payment
        $out .= self::FONT_B;
        $out .= $this->centeredLine('PAGO:', $bodyWidth);
        $out .= $this->centeredTwoCol($r['method_label'], $this->cop($r['total']), $bodyWidth);

        if ($r['method'] === 'CASH' && !empty($r['cash_received'])) {
            $out .= $this->centeredDivider('-', $bodyWidth);
            $out .= $this->centeredTwoCol('Recibido:', $this->cop($r['cash_received']), $bodyWidth);
            $out .= self::FONT_A . self::BOLD_ON;
            $out .= $this->centeredTwoCol('CAMBIO:', $this->cop($r['change_amount'] ?? '0.00'), $bodyWidthA);
            $out .= self::BOLD_OFF;
            $out .= $this->centeredDivider('=', $bodyWidthA);
        } else {
            $out .= $this->centeredDivider('=', $bodyWidth);
        }

        // Footer (optional shop slogan / thank-you, full-paper centered)
        if (!empty($shop['footer'])) {
            $out .= self::FONT_B;
            foreach (explode(self::LF, wordwrap($this->enc($shop['footer']), self::WIDTH_B, self::LF, true)) as $line) {
                $out .= $line . self::LF;
            }
            $out .= $this->centeredDivider('=', $bodyWidth);
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
        return $this->lm() . str_repeat($char, max(0, $width - self::SIDE_MARGIN)) . self::LF;
    }

    private function twoCol(string $left, string $right, int $width): string
    {
        $w    = max(1, $width - self::SIDE_MARGIN);
        $rLen = mb_strlen($right);
        $lMax = $w - $rLen - 1;
        if (mb_strlen($left) > $lMax) {
            $left = mb_substr($left, 0, $lMax);
        }
        $pad = $w - mb_strlen($left) - $rLen;
        return $this->lm() . $left . str_repeat(' ', max(1, $pad)) . $right . self::LF;
    }

    /** Side-margin prefix string (N leading spaces for left-aligned lines). */
    private function lm(): string
    {
        return self::SIDE_MARGIN > 0 ? str_repeat(' ', self::SIDE_MARGIN) : '';
    }

    /** Emit one left-aligned line: side margin + content + LF. */
    private function leftLine(string $content): string
    {
        return $this->lm() . $content . self::LF;
    }

    /**
     * Emit a line for the centered-body strategy: content right-padded with
     * spaces to exactly $width chars, then LF. Wrap several of these in
     * ALIGN_CENTER and they all shift by the same amount, so they look
     * left-aligned within a centered band — the body looks neatly inset
     * from both paper edges.
     */
    private function centeredLine(string $content, int $width): string
    {
        return $this->pad($content, $width) . self::LF;
    }

    private function centeredDivider(string $char, int $width): string
    {
        return str_repeat($char, max(0, $width)) . self::LF;
    }

    /**
     * Emit content as one or more centered-body lines, word-wrapped to $width
     * so long names/values break cleanly across lines instead of overflowing.
     */
    private function centeredWrapped(string $content, int $width): string
    {
        $out = '';
        foreach (explode(self::LF, wordwrap($content, $width, self::LF, true)) as $line) {
            $out .= $this->centeredLine($line, $width);
        }
        return $out;
    }

    private function centeredTwoCol(string $left, string $right, int $width): string
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

    /** Truncate with an ellipsis if longer than $max (same as the invoice item names). */
    private function truncate(string $s, int $max): string
    {
        if ($max < 1) return '';
        return mb_strlen($s) > $max ? mb_substr($s, 0, max(0, $max - 3)) . '...' : $s;
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

        // ── Cobro body — centered as a block, same strategy as the invoice ────
        // BODY_WIDTH < WIDTH_B so ALIGN_CENTER produces a small symmetric margin
        // on each side; every line is right-padded to $bodyWidth so the whole
        // ticket reads as one consistent centered column — matching render().
        $bodyWidth = self::WIDTH_B - 2;                            // 54 of 56
        $out .= self::ALIGN_CENTER;

        // ── Ticket title & date ───────────────────────────────────────────────
        // Emitted as a raw centered line (printer centers it on the paper, like
        // the shop-name header) so the mixed Font A / Font B control codes don't
        // skew the padding-based centering used for the rest of the body.
        $out .= self::FONT_A . self::BOLD_ON . 'COBRO' . self::BOLD_OFF . self::FONT_B
              . '  ' . $payload['printDate'] . self::LF;

        // ── Customer ──────────────────────────────────────────────────────────
        $out .= $this->centeredDivider('-', $bodyWidth);
        $custName = $this->enc($customer['name']);
        $out .= $this->centeredLine('Cliente: ' . $this->truncate($custName, $bodyWidth - 9), $bodyWidth);
        if (!empty($customer['business_name'])) {
            $bizName = $this->enc($customer['business_name']);
            $out .= $this->centeredLine('Empresa: ' . $this->truncate($bizName, $bodyWidth - 9), $bodyWidth);
        }
        $out .= $this->centeredDivider('=', $bodyWidth);

        // ── Column header ─────────────────────────────────────────────────────
        // Cols (Font B): consec(6) + date(11) + total(right,19) + balance(right,18) = 54
        $header = $this->centeredLine(
            $this->pad('#FACT', 6)
          . $this->pad(' FECHA', 11)
          . $this->padL('TOTAL', 19)
          . $this->padL('SALDO', 18),
            $bodyWidth
        );

        $renderRow = function (array $inv) use ($bodyWidth): string {
            $consec  = $this->pad('#' . $inv['consecutive'], 6);
            $date    = $this->pad(' ' . $inv['date'], 11);
            $total   = $this->padL($this->cop($inv['total']),   19);
            $balance = $this->padL($this->cop($inv['balance']), 18);
            return $this->centeredLine($this->enc($consec) . $date . $total . $balance, $bodyWidth);
        };

        $sections = $payload['sections'] ?? null;

        // Single column header — printed once regardless of grouping mode
        $out .= $header;
        $out .= $this->centeredDivider('-', $bodyWidth);

        if ($sections !== null) {
            $first = true;
            foreach ($sections as $section) {
                if (!$first) {
                    $out .= self::LF;   // blank line between sections
                }
                $first = false;
                $out .= self::BOLD_ON . $this->centeredLine($this->enc($section['label']), $bodyWidth) . self::BOLD_OFF;
                foreach ($section['invoices'] as $inv) {
                    $out .= $renderRow($inv);
                }
            }
        } else {
            foreach ($invoices as $inv) {
                $out .= $renderRow($inv);
            }
        }
        $out .= $this->centeredDivider('=', $bodyWidth);

        // ── Totals ────────────────────────────────────────────────────────────
        $out .= $this->centeredTwoCol('Deuda total:', $this->cop($payload['totalDebt']), $bodyWidth);
        if (bccomp($payload['creditBalance'], '0', 2) > 0) {
            $out .= $this->centeredTwoCol('Saldo a favor:', $this->cop($payload['creditBalance']), $bodyWidth);
        }
        $out .= self::BOLD_ON;
        $out .= $this->centeredTwoCol('NETO A COBRAR:', $this->cop($payload['netAmount']), $bodyWidth);
        $out .= self::BOLD_OFF;
        $out .= $this->centeredDivider('=', $bodyWidth);

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
    // Public: render supplier "cuentas por pagar" consolidated statement
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render a consolidated statement for a supplier's pending invoices.
     *
     * Payload:
     *   shop:     [name, address, phone, nit, logo_path, footer]
     *   supplier: [name]
     *   invoices: array of [number, date (d/m/y), total, balance]
     *   totalDebt:     string (sum of invoice balances)
     *   creditBalance: string (saldo a favor)
     *   netAmount:     string (totalDebt - creditBalance)
     *   printDate:     string (dd/mm/yyyy HH:mm)
     */
    public function renderSupplierConsolidado(array $payload): string
    {
        $out      = self::INIT . self::FONT_B;
        $shop     = $payload['shop'];
        $supplier = $payload['supplier'];
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

        // ── Body — centered block (same strategy as cartera resumen) ──────────
        $bodyWidth = self::WIDTH_B - 2;                            // 54 of 56
        $out .= self::ALIGN_CENTER;

        $out .= self::FONT_A . self::BOLD_ON . 'PAGO A PROVEEDOR' . self::BOLD_OFF . self::FONT_B
              . '  ' . $payload['printDate'] . self::LF;

        // ── Supplier ──────────────────────────────────────────────────────────
        $out .= $this->centeredDivider('-', $bodyWidth);
        $supName = $this->enc($supplier['name']);
        $out .= $this->centeredLine('Proveedor: ' . $this->truncate($supName, $bodyWidth - 11), $bodyWidth);
        $out .= $this->centeredDivider('=', $bodyWidth);

        // ── Column header ─────────────────────────────────────────────────────
        // Cols (Font B): num(8) + date(9) + total(right,19) + balance(right,18) = 54
        $out .= $this->centeredLine(
            $this->pad('#FACT', 8)
          . $this->pad('FECHA', 9)
          . $this->padL('TOTAL', 19)
          . $this->padL('SALDO', 18),
            $bodyWidth
        );
        $out .= $this->centeredDivider('-', $bodyWidth);

        foreach ($invoices as $inv) {
            $num     = $this->pad($this->truncate((string) $inv['number'], 7), 8);
            $date    = $this->pad((string) $inv['date'], 9);
            $total   = $this->padL($this->cop($inv['total']),   19);
            $balance = $this->padL($this->cop($inv['balance']), 18);
            $out .= $this->centeredLine($this->enc($num) . $date . $total . $balance, $bodyWidth);
        }
        $out .= $this->centeredDivider('=', $bodyWidth);

        // ── Totals ────────────────────────────────────────────────────────────
        $out .= $this->centeredTwoCol('Deuda total:', $this->cop($payload['totalDebt']), $bodyWidth);
        if (bccomp($payload['creditBalance'], '0', 2) > 0) {
            $out .= $this->centeredTwoCol('Saldo a favor:', $this->cop($payload['creditBalance']), $bodyWidth);
        }
        $out .= self::BOLD_ON;
        $out .= $this->centeredTwoCol('NETO A PAGAR:', $this->cop($payload['netAmount']), $bodyWidth);
        $out .= self::BOLD_OFF;
        $out .= $this->centeredDivider('=', $bodyWidth);

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
        // GS ! 0x22 = 3x width (high nibble) + 3x height (low nibble).
        // Bold via separate ESC E (GS ! does not carry the bold bit).
        // With 3x width, Font A's 42 columns become 14 usable columns.
        $SIZE_3X3   = "\x1D\x21\x22";
        $SIZE_RESET = "\x1D\x21\x00";
        $LABEL_WRAP = 14;                // columns available with 3x-width Font A

        // Larger logo for labels: 320 dots ≈ 40 mm wide, max 120 dots tall (~15 mm)
        $out  = self::INIT . self::FONT_A;
        $out .= $this->renderLogo($shop['logo_path'] ?? '', 320, 120);

        $out .= self::ALIGN_CENTER;
        $out .= $this->divider('=', self::WIDTH_A);

        // Label text — 3x width + 3x height + bold, default char spacing.
        // wordwrap with cut=false so multi-word names break only at spaces;
        // a single overlong word stays whole and lets the printer auto-wrap.
        $sanitized = $this->enc($labelText);
        $wrapped   = explode(self::LF, wordwrap($sanitized, $LABEL_WRAP, self::LF, false));
        $out .= $SIZE_3X3 . self::BOLD_ON;
        foreach ($wrapped as $line) {
            $out .= $line . self::LF;
        }
        $out .= $SIZE_RESET . self::BOLD_OFF;

        $out .= $this->divider('=', self::WIDTH_A);

        // Auto length: no minimum padding — just a small feed before the cut
        $out .= str_repeat(self::LF, 3);
        $out .= self::CUT;
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public: render "cotización" (price quotation) ticket
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render an informational price-quotation ticket. No invoice/sale involved.
     *
     * Payload:
     *   shop:      [name, address, phone, nit, logo_path, footer]  (footer NOT printed)
     *   printDate: string (dd/mm/yyyy HH:mm)
     *   sections:  array of [label, items] — items: [name, sale_unit ('KG'|'UNIT'), base_price]
     *   note:      string (closing note, e.g. "Precios sujetos a cambios...")
     */
    public function renderCotizacion(array $payload): string
    {
        $out      = self::INIT . self::FONT_B;
        $shop     = $payload['shop'];
        $sections = $payload['sections'];

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

        // ── Body — centered as a block, same strategy as the invoice ─────────
        $bodyWidth = self::WIDTH_B - 2;                            // 54 of 56
        $out .= self::ALIGN_CENTER;

        // ── Ticket title & date ───────────────────────────────────────────────
        $out .= self::FONT_A . self::BOLD_ON . 'COTIZACION' . self::BOLD_OFF . self::FONT_B
              . '  ' . $payload['printDate'] . self::LF;

        // ── Products, grouped by category ─────────────────────────────────────
        $out .= $this->centeredDivider('-', $bodyWidth);
        $out .= self::BOLD_ON . $this->centeredLine('PRODUCTOS', $bodyWidth) . self::BOLD_OFF;
        $out .= $this->centeredDivider('-', $bodyWidth);

        $first = true;
        foreach ($sections as $section) {
            if (!$first) {
                $out .= self::LF;   // blank line between category sections
            }
            $first = false;

            $label = $this->enc(mb_strtoupper($section['label']));
            $out .= self::BOLD_ON . $this->centeredLine($this->truncate($label, $bodyWidth), $bodyWidth) . self::BOLD_OFF;

            foreach ($section['items'] as $item) {
                $name  = $this->enc($item['name']);
                $unit  = $item['sale_unit'] === 'KG' ? 'kg' : 'unidad';
                $right = $this->cop($item['base_price']) . ' / ' . $unit;
                if (mb_strlen($name) <= $bodyWidth - mb_strlen($right) - 2) {
                    $out .= $this->centeredTwoCol($name, $right, $bodyWidth);
                } else {
                    // Long name: wrap across full width, price right-aligned below
                    $out .= $this->centeredWrapped($name, $bodyWidth);
                    $out .= $this->centeredTwoCol('', $right, $bodyWidth);
                }
            }
        }
        $out .= $this->centeredDivider('=', $bodyWidth);

        // ── Closing note (the shop footer greeting is intentionally omitted —
        //    a quotation is not a purchase receipt) ─────────────────────────────
        if (!empty($payload['note'])) {
            $out .= $this->centeredWrapped($this->enc($payload['note']), $bodyWidth);
        }

        // Auto length: no minimum padding, no signature block
        $out .= self::LF . self::LF . self::LF;
        $out .= self::CUT;
        return $out;
    }

}
