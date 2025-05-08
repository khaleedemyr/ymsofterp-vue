<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function getQuoteByDay($dayOfYear)
    {
        $quote = DB::table('quotes')
            ->where('day_of_year', $dayOfYear)
            ->select('quote', 'author')
            ->first();

        if (!$quote) {
            return response()->json([
                'quote' => 'Keep the spirit and stay productive!',
                'author' => 'YMSoft ERP'
            ]);
        }

        return response()->json($quote);
    }
} 