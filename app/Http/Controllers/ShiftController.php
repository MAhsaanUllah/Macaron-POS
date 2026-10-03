<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function openForm()
    {
        if (Shift::where('user_id', auth()->id())->where('status', 'open')->exists()) {
            return redirect()->route('dashboard');
        }
        $settings = SystemSetting::first() ?: new SystemSetting;
        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);

        return view('pos.shift-open', compact('settings', 'config'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            if (Shift::where('user_id', auth()->id())->where('status', 'open')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['opening_balance' => 'You already have an open shift.']);
            }
            Shift::create([
                'user_id' => auth()->id(),
                'start_time' => now(),
                'opening_balance' => $request->opening_balance,
                'expected_cash' => $request->opening_balance,
                'status' => 'open',
            ]);
        });

        return redirect()->route('dashboard')->with('success', 'Shift opened successfully.');
    }

    public function closeForm()
    {
        $activeShift = Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();
        if (! $activeShift) {
            return redirect()->route('dashboard')->with('error', 'No active shift found.');
        }

        $settings = SystemSetting::first() ?: new SystemSetting;
        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);

        return view('pos.shift-close', compact('activeShift', 'settings', 'config'));
    }

    public function close(Request $request)
    {
        $validated = $request->validate([
            'declared_cash' => 'required|numeric|min:0',
        ]);
        $user = Auth::user();

        // Find the active open shift for this terminal/user
        $activeShift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (! $activeShift) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active open shift found for this terminal.',
                ], 400);
            }

            return redirect()->route('dashboard')->with('error', 'No active open shift found.');
        }

        DB::beginTransaction();
        try {
            // 1. Calculate Shift Totals for Z-Report Persistence
            $declaredCash = $validated['declared_cash'];
            // Sales and cash refunds update this locked register balance as they occur.
            $expectedCash = $activeShift->expected_cash;

            // 2. Update Shift Status to Closed
            $activeShift->status = 'closed';
            $activeShift->end_time = now();
            $activeShift->expected_cash = $expectedCash;
            $activeShift->cash_collected_declared = $declaredCash; // matching DB column names
            $activeShift->closing_balance = $expectedCash;
            $activeShift->save();

            // 3. Explicit Authentication Session Handoff Logout
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'redirect_to' => route('login'),
                    'message' => 'Z-Report compiled successfully. Terminal session closed.',
                ]);
            }

            return redirect()->route('login')->with('success', 'Shift closed. Z-Report compiled successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Shift closure system error: '.$e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Critical error saving shift metrics. Please retry.',
                ], 500);
            }

            return back()->with('error', 'Critical error saving shift metrics. Please retry.');
        }
    }
}
