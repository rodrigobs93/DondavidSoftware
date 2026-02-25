<?php

namespace App\Services;

class EscPosTicketRenderer
{
    private const WIDTH = 42;

    // ESC/POS command constants
    private const INIT         = "\x1B\x40";
    private const CUT          = "\x1D\x56\x41\x00";
    private const BOLD_ON      = "\x1B\x45\x01";
    private const BOLD_OFF     = "\x1B\x45\x00";
    private const ALIGN_LEFT   = "\x1B\x61\x00";
    private const ALIGN_CENTER = "\x1B\x61\x01";
    private const LF           = "\n";

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
        $out .= self::BOLD_ON . mb_strtoupper($shop['name']) . self::LF . self::BOLD_OFF;
        $out .= $shop['address'] . self::LF;
        if ($shop['phone']) $out .= 'Tel: ' . $shop['phone'] . self::LF;
        if ($shop['nit'])   $out .= 'NIT: ' . $shop['nit'] . self::LF;
        $out .= $this->divider('=');

        // === Invoice number & date ===
        $out .= self::ALIGN_LEFT;
        $out .= "Factura N: {$invoice['consecutive']}" . self::LF;
        $out .= "Fecha: {$invoice['date']}  {$invoice['time']}" . self::LF;

        // === FE customer info (only when required) ===
        if ($invoice['requires_fe'] && !$customer['is_generic']) {
            $out .= $this->divider('-');
            $out .= "Cliente: {$customer['name']}" . self::LF;
            if ($customer['doc_label']) {
                $out .= "Doc: {$customer['doc_label']}" . self::LF;
            }
        }
        $out .= $this->divider('-');

        // === Column headers ===
        $out .= $this->pad('DESCRIPCION', 24) . ' ' . $this->padL('CANT', 7) . ' ' . $this->padL('TOTAL', 9) . self::LF;
        $out .= $this->divider('-');

        // === Items ===
        foreach ($items as $item) {
            $name = $item['product_name_snapshot'];
            if (mb_strlen($name) > 24) {
                $name = mb_substr($name, 0, 21) . '...';
            }
            $qty   = $item['formatted_quantity'];
            $total = $this->cop($item['line_total']);

            $out .= $this->pad($name, 24) . ' ' . $this->padL($qty, 7) . ' ' . $this->padL($total, 9) . self::LF;
        }
        $out .= $this->divider('-');

        // === Totals ===
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
        $out .= self::ALIGN_CENTER . $invoice['fe_label'] . self::LF;

        if (!empty($shop['footer'])) {
            $out .= $this->divider('-');
            $out .= $shop['footer'] . self::LF;
        }

        $out .= $this->divider('=');
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
        return mb_str_pad($s, $len);
    }

    private function padL(string $s, int $len): string
    {
        return mb_str_pad($s, $len, ' ', STR_PAD_LEFT);
    }

    /** Format as COP: $38.000 (no decimals, dot thousands separator) */
    private function cop(string|float|int $amount): string
    {
        $n = (int) round((float) $amount);
        return '$' . number_format($n, 0, ',', '.');
    }
}
