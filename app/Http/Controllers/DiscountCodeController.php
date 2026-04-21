<?php

namespace App\Http\Controllers;

use App\Mail\DiscountCodeMail;
use App\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DiscountCodeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $percentage = config('discount.percentage', 10);

        $existing = DiscountCode::where('email', $validated['email'])->first();

        if ($existing) {
            Mail::to($existing->email)->send(new DiscountCodeMail($existing, $percentage));

            return response()->json(['success' => true, 'email' => $existing->email]);
        }

        do {
            $code = 'JENVER-' . strtoupper(Str::random(4));
        } while (DiscountCode::where('code', $code)->exists());

        $discountCode = DiscountCode::create([
            'email' => $validated['email'],
            'code'  => $code,
        ]);

        Mail::to($discountCode->email)->send(new DiscountCodeMail($discountCode, $percentage));

        return response()->json(['success' => true, 'email' => $discountCode->email]);
    }
}
