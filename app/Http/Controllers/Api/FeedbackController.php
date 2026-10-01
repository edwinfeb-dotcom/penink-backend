<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:saran,masukan,kendala,lainnya',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'message' => $validated['message'],
        ]);

        return response()->json([
            'message' => 'Saran dan masukan berhasil dikirim.',
            'data' => $feedback,
        ], 201);
    }

    public function index(Request $request)
    {
    $feedbacks = Feedback::with([
        'user.unitKerja'
    ])
    ->latest()
    ->get();

    return response()->json([
        'message' => 'Data saran dan masukan berhasil diambil.',
        'data' => $feedbacks,
    ]);
    }

    public function destroy($id)
    {
    $feedback = \App\Models\Feedback::find($id);

    if (!$feedback) {
        return response()->json([
            'message' => 'Saran & masukan tidak ditemukan.',
        ], 404);
    }

    $feedback->delete();

    return response()->json([
        'message' => 'Saran & masukan berhasil dihapus.',
    ]);
    }
}