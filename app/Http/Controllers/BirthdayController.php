<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BirthdayController extends Controller
{
    public function getBirthdays()
    {
        try {
            $today = Carbon::today();
            $next30Days = Carbon::today()->addDays(30);
            
            // Get all active users with birth dates (5 closest birthdays)
            $users = User::with(['jabatan', 'outlet'])
                ->whereNotNull('tanggal_lahir')
                ->where('status', 'A')
                ->get();
            
            $birthdays = $users->filter(function ($user) use ($today, $next30Days) {
                $birthday = Carbon::parse($user->tanggal_lahir);
                $birthdayThisYear = $birthday->copy()->year($today->year);
                
                // If birthday has passed this year, check next year
                if ($birthdayThisYear->lt($today)) {
                    $birthdayThisYear->addYear();
                }
                
                // Include today's birthdays and birthdays in the next 30 days
                return $birthdayThisYear->between($today, $next30Days, true) || 
                       $birthdayThisYear->isSameDay($today);
            })
            ->map(function ($user) use ($today) {
                $birthday = Carbon::parse($user->tanggal_lahir);
                $birthdayThisYear = $birthday->copy()->year($today->year);
                
                // If birthday has passed this year, set to next year
                if ($birthdayThisYear->lt($today)) {
                    $birthdayThisYear->addYear();
                }
                
                $daysUntil = $today->diffInDays($birthdayThisYear, false);
                
                return [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'avatar' => $user->avatar,
                    'birthday' => $user->tanggal_lahir,
                    'birthday_this_year' => $birthdayThisYear->format('Y-m-d'),
                    'days_until' => $daysUntil,
                    'jabatan' => $user->jabatan,
                    'outlet' => $user->outlet
                ];
            })
            ->sortBy(function ($user) {
                // Sort by days until birthday (today first, then by days)
                return $user['days_until'];
            })
            ->take(5)
            ->values();

            return response()->json($birthdays);
        } catch (\Exception $e) {
            \Log::error('Birthday API Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch birthdays: ' . $e->getMessage()], 500);
        }
    }
}
