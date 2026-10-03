<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shift;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::first() ?? new SystemSetting;
        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);
        $users = User::all();

        $hardwareStatus = [
            'receipt_printer' => filled($config->cashier_printer_ip) || filled($config->cashier_printer_name),
            'backup' => config('database.default') === 'sqlite' && is_file(database_path('database.sqlite')),
            'fbr' => $config->fbr_enabled && filled($config->fbr_pos_id) && filled($config->fbr_bearer_token),
        ];

        return view('pos.settings', compact('settings', 'config', 'users', 'hardwareStatus'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'shop_name' => 'required|string|max:255',
            'phone_number' => ['nullable', 'string', 'max:255', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_limit_pct' => 'nullable|numeric|min:0|max:100',
            'fbr_enabled' => 'nullable|boolean',
            'seller_province' => 'nullable|string|max:100',
            'fbr_scenario_id' => 'nullable|string|max:20',
            'kitchen_printer_ip' => 'nullable|ip',
            'cashier_printer_ip' => 'nullable|ip',
            'kitchen_printer_name' => 'nullable|string|max:255',
            'cashier_printer_name' => 'nullable|string|max:255',
            'pickup_enabled' => 'nullable|boolean',
            'delivery_enabled' => 'nullable|boolean',
            'card_enabled' => 'nullable|boolean',
            'card_terminal_name' => 'nullable|string|max:100',
            'raast_enabled' => 'nullable|boolean',
            'lan_access_enabled' => 'nullable|boolean',
            'raast_qr' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $settings = SystemSetting::first() ?: new SystemSetting;

        $data = $request->only(['shop_name', 'phone_number', 'address', 'currency_symbol', 'tax_number']);

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        if ($settings->exists) {
            $settings->update($data);
        } else {
            $settings->fill($data)->save();
        }

        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);
        $configData = $request->only([
            'fbr_pos_id',
            'fbr_environment',
            'kitchen_printer_ip',
            'cashier_printer_ip',
            'tax_rate',
            'discount_limit_pct',
            'seller_province',
            'fbr_scenario_id',
            'kitchen_printer_name',
            'cashier_printer_name',
            'card_terminal_name',
        ]);
        if ($request->filled('fbr_bearer_token')) {
            $configData['fbr_bearer_token'] = $request->fbr_bearer_token;
        }
        if ($request->has('pickup_enabled')) {
            $configData['pickup_enabled'] = $request->boolean('pickup_enabled');
        }
        if ($request->has('delivery_enabled')) {
            $configData['delivery_enabled'] = $request->boolean('delivery_enabled');
        }
        if ($request->has('card_enabled')) {
            $configData['card_enabled'] = $request->boolean('card_enabled');
        }
        if ($request->has('raast_enabled')) {
            $configData['raast_enabled'] = $request->boolean('raast_enabled');
        }
        if ($request->has('lan_access_enabled')) {
            $configData['lan_access_enabled'] = $request->boolean('lan_access_enabled');
        }
        if ($request->hasFile('raast_qr')) {
            if ($config->raast_qr_path) {
                Storage::disk('public')->delete($config->raast_qr_path);
            }
            $configData['raast_qr_path'] = $request->file('raast_qr')->store('payments', 'public');
        }
        $config->update($configData);
        if (array_key_exists('lan_access_enabled', $configData) && ($dataDir = getenv('MACARON_DATA_DIR'))) {
            $written = @file_put_contents(
                rtrim($dataDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'desktop.json',
                json_encode(['lan_enabled' => (bool) $config->lan_access_enabled])
            );
            if ($written === false) {
                Log::warning('Macaron desktop.json LAN handoff write failed', ['dir' => $dataDir]);
            }
        }
        if ($request->has('fbr_enabled')) {
            $config->update(['fbr_enabled' => $request->boolean('fbr_enabled')]);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function backup()
    {
        abort_unless(config('database.default') === 'sqlite', 422, 'Automatic backup currently supports SQLite installations only.');
        DB::statement('PRAGMA wal_checkpoint(FULL)');
        $path = database_path('database.sqlite');
        abort_unless(is_file($path), 404, 'Database file not found.');

        return response()->download($path, 'macaron-backup-'.now()->format('Y-m-d-His').'.sqlite');
    }

    public function testPrinter(Request $request)
    {
        $validated = $request->validate(['target' => 'required|in:cashier,kitchen']);
        $ok = app(PrintService::class)->printTestPage($validated['target']);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Printer test page sent.' : 'Printer connection failed. Check IP/shared name and logs.');
    }

    public function testCashDrawer()
    {
        $ok = app(PrintService::class)->openCashDrawer();

        return back()->with($ok ? 'success' : 'error', $ok ? 'Cash drawer pulse sent.' : 'Cash drawer test failed. Connect it to the configured receipt printer.');
    }

    public function reset(Request $request)
    {
        $request->validate([
            'confirmation' => 'required|in:RESET',
            'password' => 'required|current_password',
        ]);
        // 1. Delete all transactional data
        Order::query()->forceDelete();
        OrderItem::query()->forceDelete();

        // 2. Delete all setup data
        Item::query()->forceDelete();
        Category::query()->forceDelete();
        Shift::query()->forceDelete();
        User::query()->forceDelete();

        // 3. Reset Configs
        $config = SystemConfig::first();
        if ($config) {
            $config->is_setup_completed = false;
            $config->save();
        }

        // 4. Logout the session
        auth()->logout();
        session()->flush();

        return redirect()->route('setup.index');
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,manager,cashier',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return back()->with('success', 'Staff user registered successfully.');
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        if (auth()->check() && $user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own logged-in user!']);
        }

        $user->delete();

        return back()->with('success', 'Staff user deleted successfully.');
    }
}
