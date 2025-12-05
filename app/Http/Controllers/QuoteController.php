<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function getQuoteByDay($dayOfYear)
    {
        // Pastikan dayOfYear dalam range 1-365
        $dayOfYear = (($dayOfYear - 1) % 365) + 1;
        
        $quote = DB::table('quotes')
            ->where('day_of_year', $dayOfYear)
            ->select('quote', 'author')
            ->first();

        // Jika tidak ditemukan berdasarkan day_of_year, coba berdasarkan ID
        if (!$quote) {
            $quote = DB::table('quotes')
                ->where('id', $dayOfYear)
                ->select('quote', 'author')
                ->first();
        }

        // Jika masih tidak ditemukan, ambil quote random
        if (!$quote) {
            $quote = DB::table('quotes')
                ->inRandomOrder()
                ->select('quote', 'author')
                ->first();
        }

        if (!$quote) {
            return response()->json([
                'quote' => 'Keep the spirit and stay productive!',
                'author' => 'YMSoft ERP'
            ]);
        }

        return response()->json($quote);
    }

    // Tambahan untuk API
    public function getQuoteByDayOfYear()
    {
        $dayOfYear = now()->setTimezone('Asia/Jakarta')->dayOfYear;
        
        // Pastikan dayOfYear dalam range 1-365
        $dayOfYear = (($dayOfYear - 1) % 365) + 1;
        
        $quote = DB::table('quotes')
            ->where('day_of_year', $dayOfYear)
            ->select('quote', 'author')
            ->first();

        // Jika tidak ditemukan berdasarkan day_of_year, coba berdasarkan ID
        if (!$quote) {
            $quote = DB::table('quotes')
                ->where('id', $dayOfYear)
                ->select('quote', 'author')
                ->first();
        }

        // Jika masih tidak ditemukan, ambil quote random
        if (!$quote) {
            $quote = DB::table('quotes')
                ->inRandomOrder()
                ->select('quote', 'author')
                ->first();
        }

        if (!$quote) {
            return response()->json([
                'quote' => 'Keep the spirit and stay productive!',
                'author' => 'YMSoft ERP'
            ]);
        }

        return response()->json($quote);
    }
} 