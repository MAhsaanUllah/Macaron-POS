<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FBRIntegrationService
{
    /**
     * FBR-verifiable QR for the printed receipt, rendered as an inline SVG data URI
     * (no internet, no temp files — works inside the desktop print pipeline).
     */
    public static function qrDataUri(?string $payload): ?string
    {
        if (blank($payload)) {
            return null;
        }

        try {
            $svg = (new Writer(new ImageRenderer(new RendererStyle(300), new SvgImageBackEnd)))
                ->writeString($payload);

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (\Throwable $e) {
            Log::error('FBR QR generation failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function submitInvoice(Order $order): bool
    {
        $config = SystemConfig::first();
        $settings = SystemSetting::first();

        if (! $config?->fbr_enabled) {
            $order->update(['fbr_status' => 'not_configured']);

            return false;
        }

        $missing = collect([
            'FBR token' => $config->fbr_bearer_token,
            'seller NTN/CNIC' => $settings?->tax_number,
            'seller business name' => $settings?->shop_name,
            'seller address' => $settings?->address,
        ])->filter(fn ($value) => blank($value))->keys();

        if ($missing->isNotEmpty() || $order->items->contains(fn ($line) => blank($line->item?->hs_code))) {
            $message = $missing->isNotEmpty()
                ? 'Missing: '.$missing->implode(', ')
                : 'One or more sold items have no HS code.';
            $order->update(['fbr_status' => 'blocked', 'fbr_error' => $message]);

            return false;
        }

        $payload = $this->payload($order, $config, $settings);
        $url = $config->fbr_environment === 'production'
            ? 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata'
            : 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb';

        try {
            $response = Http::withToken($config->fbr_bearer_token)
                ->acceptJson()->timeout(10)->retry(2, 250)->post($url, $payload);
            $data = $response->json() ?: [];
            $invoiceNumber = data_get($data, 'invoiceNumber')
                ?? data_get($data, 'InvoiceNumber')
                ?? data_get($data, 'result')
                ?? data_get($data, 'Result');

            if ($response->successful() && $invoiceNumber) {
                $order->update([
                    'fbr_status' => 'submitted',
                    'fbr_invoice_number' => (string) $invoiceNumber,
                    'fbr_error' => null,
                    'fbr_submitted_at' => now(),
                ]);

                return true;
            }

            $error = data_get($data, 'validationResponse.error')
                ?? data_get($data, 'error')
                ?? data_get($data, 'ErrorMessage')
                ?? 'FBR rejected the invoice (HTTP '.$response->status().').';
            $order->update(['fbr_status' => 'failed', 'fbr_error' => is_string($error) ? $error : json_encode($error)]);
            Log::warning('FBR invoice rejected', ['order_id' => $order->id, 'status' => $response->status()]);
        } catch (\Throwable $e) {
            $order->update(['fbr_status' => 'pending', 'fbr_error' => 'Connection failed; retry required.']);
            Log::error('FBR connection failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
        }

        return false;
    }

    private function payload(Order $order, SystemConfig $config, SystemSetting $settings): array
    {
        $taxRate = (float) $config->tax_rate;

        $payload = [
            'invoiceType' => 'Sale Invoice',
            'invoiceDate' => $order->created_at->format('Y-m-d'),
            'sellerNTNCNIC' => preg_replace('/\D/', '', $settings->tax_number),
            'sellerBusinessName' => $settings->shop_name,
            'sellerProvince' => $config->seller_province,
            'sellerAddress' => $settings->address,
            'buyerNTNCNIC' => '',
            'buyerBusinessName' => $order->customer_name ?: 'Walk-in Customer',
            'buyerProvince' => $config->seller_province,
            'buyerAddress' => '',
            'buyerRegistrationType' => 'Unregistered',
            'invoiceRefNo' => $order->order_number,
            'items' => $order->items->map(function ($line) use ($taxRate, $order) {
                $net = (float) $line->total;
                $tax = round($net * ($taxRate / 100), 2);
                $billDiscount = (float) $order->discount + (float) $order->points_redeemed;
                $lineDiscount = (float) $order->subtotal > 0
                    ? round($billDiscount * ($net / (float) $order->subtotal), 2)
                    : 0;

                return [
                    'hsCode' => $line->item->hs_code,
                    'productDescription' => $line->item->name,
                    'rate' => rtrim(rtrim(number_format($taxRate, 2, '.', ''), '0'), '.').'%',
                    'uoM' => $line->item->uom,
                    'quantity' => (float) $line->quantity,
                    'totalValues' => $net + $tax - $lineDiscount,
                    'valueSalesExcludingST' => $net,
                    'fixedNotifiedValueOrRetailPrice' => 0,
                    'salesTaxApplicable' => $tax,
                    'salesTaxWithheldAtSource' => 0,
                    'extraTax' => 0,
                    'furtherTax' => 0,
                    'sroScheduleNo' => '',
                    'fedPayable' => 0,
                    'discount' => $lineDiscount,
                    'saleType' => $line->item->sale_type,
                    'sroItemSerialNo' => '',
                ];
            })->all(),
        ];

        if ($config->fbr_environment === 'sandbox' && $config->fbr_scenario_id) {
            $payload['scenarioId'] = $config->fbr_scenario_id;
        }

        return $payload;
    }
}
