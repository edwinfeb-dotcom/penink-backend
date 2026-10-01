<?php

namespace App\Http\Controllers;
use App\Services\AuditLogService;
use App\Models\LinkHub;
use Illuminate\Http\Request;

class LinkHubController extends Controller
{
    public function index(Request $request)
    {
    $user = $request->user();

    if ($user->role === 'ADMIN') {
        $linkHubs = LinkHub::with('items')
            ->latest()
            ->get();
    } else {
        $linkHubs = LinkHub::with('items')
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }

    return response()->json([
        'message' => 'Data Link Hub berhasil diambil.',
        'data' => $linkHubs,
    ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_code' => [
                'required',
                'alpha_dash',
                'unique:link_hubs,short_code',
                'not_in:admin,login,register,api,dashboard,user,settings',
            ],
        ], [
            'title.required' => 'Judul Link Hub wajib diisi.',
            'short_code.required' => 'Alias Link Hub wajib diisi.',
            'short_code.alpha_dash' => 'Alias hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
            'short_code.unique' => 'Alias Link Hub sudah digunakan.',
            'short_code.not_in' => 'Alias ini tidak dapat digunakan karena termasuk alias yang dicadangkan.',
        ]);

        $linkHub = LinkHub::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'short_code' => $request->short_code,
            'status' => true,
        ]);

        AuditLogService::log(
            'linkhub.created',
            'LinkHub',
            $linkHub->id,
            'Link Hub berhasil dibuat',
            [
                'title' => $linkHub->title,
                'short_code' => $linkHub->short_code,
                ]
                );

        return response()->json([
            'message' => 'Link Hub berhasil dibuat.',
            'data' => $linkHub,
        ], 201);
    }

    public function addItem(Request $request, $id)
    {
    $user = $request->user();
    // ADMIN boleh menambahkan link ke semua Link Hub
    // USER hanya boleh menambahkan link ke Link Hub miliknya sendiri
    if ($user->role === 'ADMIN') {
        $linkHub = LinkHub::where('id', $id)
            ->firstOrFail();
    } else {
        $linkHub = LinkHub::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    $request->validate([
        'title' => 'required|string|max:255',
        'url' => 'required|url',
    ], [
        'title.required' => 'Judul link wajib diisi.',
        'url.required' => 'URL link wajib diisi.',
        'url.url' => 'URL yang dimasukkan tidak valid.',
    ]);

    $item = $linkHub->items()->create([
        'title' => $request->title,
        'url' => $request->url,
        'sort_order' => ($linkHub->items()->max('sort_order') ?? 0) + 1,
        'status' => true,
    ]);

    AuditLogService::log(
        'linkhub.item.created',
        'LinkHubItem',
        $item->id,
        'Link berhasil ditambahkan ke Link Hub',
        [
            'link_hub_id' => $linkHub->id,
            'hub_title' => $linkHub->title,
            'title' => $item->title,
            'url' => $item->url,
            'sort_order' => $item->sort_order,
            ]
            );

    return response()->json([
        'message' => 'Link berhasil ditambahkan ke Link Hub.',
        'data' => $item,
    ], 201);
    }

   public function updateItem(Request $request, $hubId, $itemId)
   {
    $user = $request->user();

    // ADMIN boleh mengedit link pada semua Link Hub
    // USER hanya boleh mengedit link pada Link Hub miliknya sendiri
    if ($user->role === 'ADMIN') {
        $linkHub = LinkHub::where('id', $hubId)
            ->firstOrFail();
    } else {
        $linkHub = LinkHub::where('id', $hubId)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    $item = $linkHub->items()
        ->where('id', $itemId)
        ->firstOrFail();

    $request->validate([
    'title' => 'required|string|max:255',
    'url' => 'required|url',
    'sort_order' => 'sometimes|integer|min:0',
    ], [
        'title.required' => 'Judul link wajib diisi.',
        'url.required' => 'URL link wajib diisi.',
        'url.url' => 'URL yang dimasukkan tidak valid.',
    ]);

    $item->update([
    'title' => $request->title,
    'url' => $request->url,
    'sort_order' => $request->has('sort_order')
        ? $request->sort_order
        : $item->sort_order,
        
    ]);

    AuditLogService::log(
        'linkhub.item.updated',
        'LinkHubItem',
        $item->id,
        'Link berhasil diperbarui',
        [
            'link_hub_id' => $linkHub->id,
            'hub_title' => $linkHub->title,
            'title' => $item->title,
            'url' => $item->url,
            'sort_order' => $item->sort_order,
        ]);

    return response()->json([
        'message' => 'Link berhasil diperbarui.',
        'data' => $item,
    ]);
    }

    public function deleteItem(Request $request, $hubId, $itemId)
    {
    $user = $request->user();

    if ($user->role === 'ADMIN') {
        $linkHub = LinkHub::where('id', $hubId)->firstOrFail();
    } else {
        $linkHub = LinkHub::where('id', $hubId)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    $item = $linkHub->items()->where('id', $itemId)->firstOrFail();

    $auditData = [
        'link_hub_id' => $linkHub->id,
        'hub_title' => $linkHub->title,
        'title' => $item->title,
        'url' => $item->url,
        'sort_order' => $item->sort_order,
        'status' => $item->status,
    ];

    $item->delete();

    AuditLogService::log(
        'linkhub.item.deleted',
        'LinkHubItem',
        $itemId,
        'Link berhasil dihapus',
        $auditData
    );

    return response()->json([
        'message' => 'Link berhasil dihapus.',
    ]);
    }

    public function toggleItemStatus(Request $request, $hubId, $itemId)
    {
    $user = $request->user();

    // ADMIN boleh mengubah status link pada semua Link Hub
    // USER hanya boleh mengubah status link pada Link Hub miliknya sendiri
    if ($user->role === 'ADMIN') {
        $linkHub = LinkHub::where('id', $hubId)
            ->firstOrFail();
    } else {
        $linkHub = LinkHub::where('id', $hubId)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    $item = $linkHub->items()
        ->where('id', $itemId)
        ->firstOrFail();

    $item->status = !$item->status;
    $item->save();

    AuditLogService::log(
        'linkhub.item.status_changed',
        'LinkHubItem',
        $item->id,
        $item->status
        ? 'Link berhasil diaktifkan'
        : 'Link berhasil dinonaktifkan',
        
        [
            'link_hub_id' => $linkHub->id,
            'hub_title' => $linkHub->title,
            'title' => $item->title,
            'url' => $item->url,
            'status' => $item->status,
        ]);

    return response()->json([
        'message' => $item->status
            ? 'Link berhasil diaktifkan.'
            : 'Link berhasil dinonaktifkan.',
        'data' => $item,
    ]);
    }

    public function update(Request $request, $id)
    {
    $user = $request->user();

    // ADMIN boleh mengedit semua Link Hub
    // USER hanya boleh mengedit Link Hub miliknya sendiri
    if ($user->role === 'ADMIN') {
        $linkHub = LinkHub::where('id', $id)
            ->firstOrFail();
    } else {
        $linkHub = LinkHub::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'short_code' => [
            'required',
            'alpha_dash',
            'unique:link_hubs,short_code,' . $linkHub->id,
            'not_in:admin,login,register,api,dashboard,user,settings',
        ],
    ], [
        'title.required' => 'Judul Link Hub wajib diisi.',
        'short_code.required' => 'Alias Link Hub wajib diisi.',
        'short_code.alpha_dash' => 'Alias hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
        'short_code.unique' => 'Alias Link Hub sudah digunakan.',
        'short_code.not_in' => 'Alias ini tidak dapat digunakan karena termasuk alias yang dicadangkan.',
    ]);

    $linkHub->update([
        'title' => $request->title,
        'description' => $request->description,
        'short_code' => $request->short_code,
    ]);

    AuditLogService::log(
        'linkhub.updated','LinkHub',
        $linkHub->id,
        'Link Hub berhasil diperbarui',
        [
            'title' => $linkHub->title,
            'short_code' => $linkHub->short_code,
            ]
            );

    return response()->json([
        'message' => 'Link Hub berhasil diperbarui.',
        'data' => $linkHub,
    ]);
    
    }

    public function toggleStatus(Request $request, $id)
    {
    $user = $request->user();

    // ADMIN boleh mengubah status semua Link Hub
    // USER hanya boleh mengubah status Link Hub miliknya sendiri
    if ($user->role === 'ADMIN') {
        $linkHub = LinkHub::where('id', $id)
            ->firstOrFail();
    } else {
        $linkHub = LinkHub::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    $linkHub->status = !$linkHub->status;
    $linkHub->save();

    AuditLogService::log(
        'linkhub.status_changed',
        'LinkHub',
        $linkHub->id,
        $linkHub->status
        ? 'Link Hub berhasil diaktifkan'
        : 'Link Hub berhasil dinonaktifkan',
        [
            'title' => $linkHub->title,'short_code' => $linkHub->short_code,
            'status' => $linkHub->status,
            ]
            );

    return response()->json([
        'message' => $linkHub->status
            ? 'Link Hub berhasil diaktifkan.'
            : 'Link Hub berhasil dinonaktifkan.',
        'data' => $linkHub,
    ]);
    }

    public function destroy(Request $request, $id)
    {
    $linkHub = LinkHub::where('id', $id)
        ->where('user_id', $request->user()->id)
        ->firstOrFail();

    $auditData = [
        'title' => $linkHub->title,
        'short_code' => $linkHub->short_code,
        'description' => $linkHub->description,
    ];

    $linkHub->items()->delete();
    $linkHub->delete();

    AuditLogService::log(
        'linkhub.deleted',
        'LinkHub',
        $id,
        'Link Hub berhasil dihapus',
        $auditData
    );

    return response()->json([
        'message' => 'Link Hub berhasil dihapus.',
    ]);
    }

    public function publicShow($shortCode)
    {
        $linkHub = LinkHub::with(['items' => function ($query) {
            $query->where('status', true)
                  ->orderBy('sort_order');
        }])
        ->where('short_code', $shortCode)
        ->where('status', true)
        ->firstOrFail();

        return response()->json([
            'message' => 'Link Hub berhasil diambil.',
            'data' => $linkHub,
        ]);
    }
}