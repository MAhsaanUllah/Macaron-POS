<?php

namespace App\Http\Controllers;

use App\Models\SystemConfig;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SetupController extends Controller
{
    public function index()
    {
        $config = SystemConfig::first();
        if ($config && $config->is_setup_completed) {
            return redirect()->route('login');
        }

        return view('setup.wizard');
    }

    public function process(Request $request)
    {
        abort_if(
            SystemConfig::where('is_setup_completed', true)->exists(),
            403,
            'Setup has already been completed.'
        );

        $request->validate([
            'shop_name' => 'required|string|max:255',
            'phone_number' => ['required', 'string', 'max:255', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'tax_number' => 'nullable|string|max:255',
            'persona' => 'required|in:sweets',
            'cashier_printer_ip' => 'nullable|ip',
            'admin_name' => 'required|string',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|min:6',
        ]);

        $user = DB::transaction(function () use ($request) {
            // Re-check under the write lock: two concurrent setup POSTs must not mint two admins
            if (SystemConfig::where('is_setup_completed', true)->lockForUpdate()->exists()) {
                abort(403, 'Setup has already been completed.');
            }

            // 1. Create Admin User
            $user = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'role' => 'admin',
            ]);

            // 2. Save Settings
            $settings = SystemSetting::first() ?? new SystemSetting;
            $settings->fill([
                'shop_name' => $request->shop_name,
                'phone_number' => $request->phone_number,
                'tax_number' => $request->tax_number,
                'currency_symbol' => 'Rs.',
            ])->save();

            // 3. Save Configs
            $config = SystemConfig::first() ?? new SystemConfig;
            $config->fill([
                'fbr_environment' => 'sandbox',
                'cashier_printer_ip' => $request->cashier_printer_ip,
                'business_type' => 'sweets',
                'is_setup_completed' => true,
            ])->save();

            // 4. Run Persona Seeder (--force: desktop shell runs APP_ENV=production,
            //    where db:seed would otherwise demand an interactive confirmation
            //    and fatal on undefined STDIN inside an HTTP request)
            Artisan::call('db:seed', ['--class' => 'SweetShopSeeder', '--force' => true]);

            return $user;
        });

        // Automatically login the admin user
        auth()->login($user);

        return redirect()->route('dashboard')->with('success', 'Setup Completed Successfully!');
    }
}
