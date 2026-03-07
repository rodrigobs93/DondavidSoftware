<?php

namespace App\Services;

use App\Models\Setting;

class ThermalPrinterService
{
    public function printerName(): string
    {
        return Setting::get('thermal_printer_name', env('THERMAL_PRINTER_NAME', 'XP-80C'));
    }

    public function send(string $bytes): void
    {
        $this->sendToSpooler($bytes, $this->printerName());
    }

    public function testPrint(): void
    {
        $now = now()->setTimezone('America/Bogota');

        $bytes = chr(0x1B) . chr(0x40)          // ESC @ init
               . chr(0x1B) . chr(0x61) . chr(1) // CENTER
               . chr(0x1B) . chr(0x45) . chr(1) // BOLD ON
               . "TEST DE IMPRESION\n"
               . chr(0x1B) . chr(0x45) . chr(0) // BOLD OFF
               . chr(0x1B) . chr(0x61) . chr(0) // LEFT
               . str_repeat('-', 42) . "\n"
               . "Impresora: " . $this->printerName() . "\n"
               . "Fecha:     " . $now->format('d/m/Y H:i:s') . "\n"
               . str_repeat('-', 42) . "\n"
               . "Don David POS - OK\n\n\n"
               . chr(0x1D) . chr(0x56) . chr(0x41) . chr(3); // FULL CUT

        $this->send($bytes);
    }

    private function sendToSpooler(string $bytes, string $printerName): void
    {
        // Write ESC/POS bytes to a temp binary file
        $tmpBin  = tempnam(sys_get_temp_dir(), 'esc_');
        $ps1File = tempnam(sys_get_temp_dir(), 'rp_') . '.ps1';

        try {
            file_put_contents($tmpBin,  $bytes);
            file_put_contents($ps1File, $this->rawPrintScript());

            $pName = str_replace('"', '`"', $printerName);
            $pFile = str_replace('\\', '\\\\', $tmpBin);

            $cmd = "powershell -ExecutionPolicy Bypass -NonInteractive"
                 . " -File \"" . $ps1File . "\""
                 . " -PrinterName \"" . $pName . "\""
                 . " -FilePath \"" . $pFile . "\"";

            exec($cmd . ' 2>&1', $output, $retval);

            if ($retval !== 0) {
                throw new \RuntimeException(
                    "Error al imprimir en '{$printerName}': " . implode(' | ', $output)
                );
            }
        } finally {
            @unlink($tmpBin);
            @unlink($ps1File);
        }
    }

    private function rawPrintScript(): string
    {
        // param() must be the FIRST statement in the PS1 file
        return <<<'PS1'
param([string]$PrinterName, [string]$FilePath)
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class WinSpool {
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Auto)]
    public struct DOCINFO {
        [MarshalAs(UnmanagedType.LPTStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPTStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPTStr)] public string pDatatype;
    }
    [DllImport("winspool.drv", CharSet=CharSet.Auto, SetLastError=true)]
    public static extern bool OpenPrinter(string n, out IntPtr h, IntPtr d);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool ClosePrinter(IntPtr h);
    [DllImport("winspool.drv", CharSet=CharSet.Auto, SetLastError=true)]
    public static extern int StartDocPrinter(IntPtr h, int lev, ref DOCINFO di);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool EndDocPrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool StartPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool EndPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool WritePrinter(IntPtr h, byte[] b, int cb, out int written);
}
"@
$bytes = [System.IO.File]::ReadAllBytes($FilePath)
$hPrinter = [IntPtr]::Zero
if (-not [WinSpool]::OpenPrinter($PrinterName, [ref]$hPrinter, [IntPtr]::Zero)) {
    Write-Error "No se pudo abrir la impresora '$PrinterName'. Verifique el nombre en Dispositivos e Impresoras."
    exit 1
}
try {
    $di           = New-Object WinSpool+DOCINFO
    $di.pDocName  = "DonDavidTicket"
    $di.pDatatype = "RAW"
    [WinSpool]::StartDocPrinter($hPrinter, 1, [ref]$di) | Out-Null
    [WinSpool]::StartPagePrinter($hPrinter) | Out-Null
    $written = 0
    [WinSpool]::WritePrinter($hPrinter, $bytes, $bytes.Length, [ref]$written) | Out-Null
    [WinSpool]::EndPagePrinter($hPrinter) | Out-Null
    [WinSpool]::EndDocPrinter($hPrinter) | Out-Null
} finally {
    [WinSpool]::ClosePrinter($hPrinter) | Out-Null
}
PS1;
    }
}
