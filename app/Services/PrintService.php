<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrintService
{
    protected $printerType;

    protected $kitchenIp;

    protected $cashierIp;

    protected $kitchenName;

    protected $cashierName;

    public function __construct(string $type = 'auto')
    {
        $this->printerType = $type;
        $config = SystemConfig::first();
        $this->kitchenIp = $config?->kitchen_printer_ip;
        $this->cashierIp = $config?->cashier_printer_ip;
        $this->kitchenName = $config?->kitchen_printer_name;
        $this->cashierName = $config?->cashier_printer_name;
    }

    protected function getConnector(string $target = 'cashier')
    {
        try {
            $ip = $target === 'kitchen' ? $this->kitchenIp : $this->cashierIp;

            if ($ip) {
                return new NetworkPrintConnector($ip, 9100, 1);
            } elseif (($target === 'kitchen' ? $this->kitchenName : $this->cashierName)
                && ($this->printerType === 'usb' || $this->printerType === 'windows' || $this->printerType === 'auto')) {
                $name = $target === 'kitchen' ? $this->kitchenName : $this->cashierName;

                return new WindowsPrintConnector($name);
            } else {
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Printer Connection Failed ({$target}): ".$e->getMessage());

            return null;
        }
    }

    public function printTestPage(string $target): bool
    {
        $connector = $this->getConnector($target);
        if (! $connector) {
            return false;
        }

        try {
            $printer = new Printer($connector);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Macaron printer test\n");
            $printer->text(strtoupper($target)."\n");
            $printer->text(now()->format('Y-m-d H:i:s')."\n");
            $printer->feed(3);
            $printer->cut();
            $printer->close();

            return true;
        } catch (\Throwable $e) {
            Log::error("Printer test failed ({$target}): ".$e->getMessage());

            return false;
        }
    }

    public function openCashDrawer(): bool
    {
        $connector = $this->getConnector('cashier');
        if (! $connector) {
            return false;
        }

        try {
            $printer = new Printer($connector);
            $printer->pulse();
            $printer->close();

            return true;
        } catch (\Throwable $e) {
            Log::error('Cash drawer test failed: '.$e->getMessage());

            return false;
        }
    }

    public function printReceipt(Order $order)
    {
        $connector = $this->getConnector('cashier');
        if (! $connector) {
            return false;
        }

        try {
            $printer = new Printer($connector);

            // Header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(2, 2);
            $settings = SystemSetting::first();
            $printer->text(($settings?->shop_name ?: 'Macaron')."\n");
            $printer->setTextSize(1, 1);
            if ($settings?->address) {
                $printer->text($settings->address."\n");
            }
            if ($settings?->tax_number) {
                $printer->text('NTN/STRN: '.$settings->tax_number."\n");
            }
            $printer->text("--------------------------------\n");
            $printer->text("Order: {$order->order_number}\n");
            $printer->text('Date: '.now()->format('Y-m-d H:i')."\n");
            $printer->text("--------------------------------\n");

            // Items
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            foreach ($order->items as $item) {
                $line = sprintf("%-15s %3d x %6s\n", substr($item->item->name, 0, 15), $item->quantity, $item->price);
                $printer->text($line);
            }

            // Totals
            $printer->text("--------------------------------\n");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->text(sprintf("Subtotal: %8s\n", $order->subtotal));
            $printer->text(sprintf("Tax: %8s\n", $order->tax));
            $printer->text(sprintf("Discount: %8s\n", $order->discount));
            $printer->setTextSize(1, 2);
            $printer->text(sprintf("TOTAL: %8s\n", $order->grand_total));
            $printer->setTextSize(1, 1);

            // Payment info
            $printer->text(sprintf("Tendered: %8s\n", $order->amount_tendered ?? $order->grand_total));
            $printer->text(sprintf("Change: %8s\n", $order->change_amount ?? 0));
            $printer->feed(2);

            // FBR Integration
            if ($order->fbr_status === 'submitted' && $order->fbr_invoice_number) {
                $fbrInvoice = $order->fbr_invoice_number;
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("--------------------------------\n");
                $printer->text("FBR INVOICE NUMBER:\n");
                $printer->text("{$fbrInvoice}\n");
                // Native QR Code Generation (size 7)
                $printer->qrCode($fbrInvoice, Printer::QR_ECLEVEL_L, 7);
                $printer->text("Verify this invoice through FBR\nTax Asaan Mobile App or SMS at 9966\n");
                $printer->text("--------------------------------\n");
            }

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Thank you for your visit!\n");
            $printer->feed(4);
            $printer->cut();

            // Open cash drawer
            if ($order->payment_method === 'cash') {
                $printer->pulse();
            }
            $printer->close();

            return true;
        } catch (\Exception $e) {
            Log::error('Printing Failed: '.$e->getMessage());

            return false;
        }
    }

    public function printKOT(Order $order)
    {
        $connector = $this->getConnector('kitchen');
        if (! $connector) {
            return false;
        }

        try {
            $printer = new Printer($connector);

            // Header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(2, 2);
            $printer->text("PRODUCTION ORDER\n");
            $printer->setTextSize(1, 1);
            $printer->text("--------------------------------\n");
            $printer->text("Order: {$order->order_number}\n");
            $printer->text('Customer: '.($order->customer_name ?: 'Walk-in')."\n");
            $printer->text("Type: {$order->order_type}\n");
            $printer->text('Time: '.now()->format('H:i:s')."\n");
            $printer->text("--------------------------------\n");

            // Items
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setTextSize(1, 2);
            foreach ($order->items as $item) {
                $quantity = $item->item->uom === 'KG'
                    ? number_format((float) $item->quantity, 3).' kg'
                    : number_format((float) $item->quantity, 0).' pcs';
                $printer->text("[ {$quantity} ] ".substr($item->item->name, 0, 20)."\n");
            }
            $printer->setTextSize(1, 1);

            $printer->feed(4);
            $printer->cut();
            $printer->close();

            return true;
        } catch (\Exception $e) {
            Log::error('KOT Printing Failed: '.$e->getMessage());

            return false;
        }
    }
}
