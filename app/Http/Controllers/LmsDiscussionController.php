<?php

namespace App\Http\Controllers;

use App\Models\LmsDiscussion;
use App\Models\LmsCourse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsDiscussionController extends Controller
{
    public function index()
    {
        $discussions = LmsDiscussion::with(['course', 'user', 'replies'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Lms/Discussions/Index', [
            'discussions' => $discussions
        ]);
    }

    public function create()
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Discussions/Create', [
            'courses' => $courses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'status' => 'required|in:active,locked,archived'
        ]);

        $validated['user_id'] = auth()->id();

        LmsDiscussion::create($validated);

        return redirect()->route('lms.discussions.index')
            ->with('success', 'Diskusi berhasil dibuat');
    }

    public function show(LmsDiscussion $discussion)
    {
        $discussion->load(['course', 'user', 'replies.user']);

        return Inertia::render('Lms/Discussions/Show', [
            'discussion' => $discussion
        ]);
    }

    public function edit(LmsDiscussion $discussion)
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Discussions/Edit', [
            'discussion' => $discussion,
            'courses' => $courses
        ]);
    }

    public function update(Request $request, LmsDiscussion $discussion)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'status' => 'required|in:active,locked,archived'
        ]);

        $discussion->update($validated);

        return redirect()->route('lms.discussions.index')
            ->with('success', 'Diskusi berhasil diperbarui');
    }

    public function destroy(LmsDiscussion $discussion)
    {
        $discussion->delete();

        return redirect()->route('lms.discussions.index')
            ->with('success', 'Diskusi berhasil dihapus');
    }

    public function togglePin(LmsDiscussion $discussion)
    {
        $discussion->update(['is_pinned' => !$discussion->is_pinned]);

        return back()->with('success', 'Status pin berhasil diubah');
    }

    public function toggleLock(LmsDiscussion $discussion)
    {
        $discussion->update(['is_locked' => !$discussion->is_locked]);

        return back()->with('success', 'Status lock berhasil diubah');
    }
} 