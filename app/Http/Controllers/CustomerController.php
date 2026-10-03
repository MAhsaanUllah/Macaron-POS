<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function search(Request $request)
    {
        $request->validate(['phone' => 'required|string|max:20']);

        $customer = Customer::where('phone_number', $request->phone)->first();

        if (! $customer) {
            return response()->json(['customer' => null]);
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone_number' => $customer->phone_number,
                'total_points_balance' => $customer->total_points_balance,
            ],
        ]);
    }
}
