<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NotesController extends Controller
{
    public function index()
    {
        try {
            $userId = auth()->id();
            
            $notes = DB::table('notes')
                ->leftJoin('note_attachments', 'notes.id', '=', 'note_attachments.note_id')
                ->where('notes.user_id', $userId)
                ->select(
                    'notes.id',
                    'notes.title',
                    'notes.content',
                    'notes.created_at',
                    'notes.updated_at',
                    DB::raw('GROUP_CONCAT(
                        JSON_OBJECT(
                            "id", note_attachments.id,
                            "filename", note_attachments.filename,
                            "original_name", note_attachments.original_name,
                            "file_path", note_attachments.file_path,
                            "file_size", note_attachments.file_size,
                            "mime_type", note_attachments.mime_type
                        )
                    ) as attachments')
                )
                ->groupBy('notes.id', 'notes.title', 'notes.content', 'notes.created_at', 'notes.updated_at')
                ->orderBy('notes.created_at', 'desc')
                ->get();

            // Parse attachments JSON
            $notes->transform(function ($note) {
                if ($note->attachments) {
                    $note->attachments = json_decode('[' . $note->attachments . ']', true);
                } else {
                    $note->attachments = [];
                }
                return $note;
            });

            return response()->json($notes);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch notes'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'nullable|string',
                'attachments.*' => 'nullable|file|max:10240' // 10MB max
            ]);

            $userId = auth()->id();

            // Create note
            $noteId = DB::table('notes')->insertGetId([
                'user_id' => $userId,
                'title' => $request->title,
                'content' => $request->content,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('notes', $filename, 'public');
                    
                    DB::table('note_attachments')->insert([
                        'note_id' => $noteId,
                        'filename' => $filename,
                        'original_name' => $originalName,
                        'file_path' => $filePath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Fetch the created note with attachments
            $note = $this->getNoteWithAttachments($noteId);

            return response()->json($note, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to save note'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $userId = auth()->id();
            
            // Get note attachments
            $attachments = DB::table('note_attachments')
                ->where('note_id', $id)
                ->get();

            // Delete files from storage
            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete attachments from database
            DB::table('note_attachments')->where('note_id', $id)->delete();

            // Delete note
            $deleted = DB::table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->delete();

            if ($deleted) {
                return response()->json(['message' => 'Note deleted successfully']);
            } else {
                return response()->json(['error' => 'Note not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete note'], 500);
        }
    }

    private function getNoteWithAttachments($noteId)
    {
        $note = DB::table('notes')->where('id', $noteId)->first();
        
        $attachments = DB::table('note_attachments')
            ->where('note_id', $noteId)
            ->get()
            ->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'original_name' => $attachment->original_name,
                    'file_path' => $attachment->file_path,
                    'file_size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type
                ];
            });

        $note->attachments = $attachments;
        return $note;
    }
}
