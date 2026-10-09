<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    /**
     * Helper: cek admin_unit
     */
    private function isAdminUnit($user)
    {
        return strtolower($user->role) === 'admin_unit';
    }

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
        $user = $request->user();

        $query = Feedback::with([
            'user.unitKerja'
        ]);

        // admin_unit → hanya feedback dari OPD-nya
        if ($this->isAdminUnit($user)) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
        }
        // admin & super_admin → lihat semua

        $feedbacks = $query->latest()->get();

        return response()->json([
            'message' => 'Data saran dan masukan berhasil diambil.',
            'data' => $feedbacks,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $feedback = Feedback::with('user')->find($id);

        if (!$feedback) {
            return response()->json([
                'message' => 'Saran & masukan tidak ditemukan.',
            ], 404);
        }

        // admin_unit → hanya bisa hapus feedback dari OPD-nya
        if ($this->isAdminUnit($user)) {
            if ($feedback->user?->unit_kerja_id !== $user->unit_kerja_id) {
                return response()->json([
                    'message' => 'Anda hanya bisa menghapus masukan dari unit kerja Anda.',
                ], 403);
            }
        }

        $feedback->delete();

        return response()->json([
            'message' => 'Saran & masukan berhasil dihapus.',
        ]);
    }
}