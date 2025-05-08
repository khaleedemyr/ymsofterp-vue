<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SignatureController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'signature' => 'required|file|image|max:2048', // max 2MB
            'type' => 'required|in:draw,upload'
        ]);

        try {
            // Get current user
            $user = auth()->user();

            // Delete old signature if exists
            if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
                Storage::disk('public')->delete($user->signature_path);
            }

            // Store new signature
            $file = $request->file('signature');
            $extension = $file->getClientOriginalExtension();
            $filename = 'signatures/' . Str::uuid() . '.' . $extension;
            
            // Store file
            Storage::disk('public')->put($filename, file_get_contents($file));

            // Update user signature path
            $user->signature_path = $filename;
            $user->save();

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Signature saved successfully',
                    'signature_path' => $filename
                ]);
            }

            return back()->with('success', 'Signature saved successfully');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to save signature',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Failed to save signature');
        }
    }
} 