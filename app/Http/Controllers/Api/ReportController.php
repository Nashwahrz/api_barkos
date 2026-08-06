<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Send an automated moderation warning to a reported product's seller via chat,
     * and notify them. Used both when a report first comes in and when admin acts on
     * it — $sender is whichever "voice" (system admin or the acting admin) it's from.
     */
    private function sendModerationChat(Report $report, User $sender, string $message): void
    {
        $seller = $report->product?->user;
        if (!$seller || $seller->id === $sender->id) {
            return;
        }

        $chat = Chat::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $seller->id,
            'product_id'  => $report->product->id,
            'message'     => $message,
            'is_read'     => false,
        ]);

        $seller->notify(new \App\Notifications\ChatNotification($chat));
    }

    /**
     * Display a listing of reports (Super Admin Only).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reports = Report::with(['reporter', 'product.user', 'product.category'])->latest()->get();
        $reports->each(fn ($report) => $this->normalizeProductPhoto($report));

        return response()->json([
            'data' => $reports
        ]);
    }

    /**
     * Prefix a loaded report's product photo path so the frontend can resolve it
     * the same way ProductResource does (raw DB paths otherwise 404).
     */
    private function normalizeProductPhoto(Report $report): void
    {
        if ($report->product && $report->product->foto && !str_starts_with($report->product->foto, '/api/storage/')) {
            $report->product->foto = '/api/storage/' . $report->product->foto;
        }
    }

    /**
     * Store a newly created report in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $report = Report::create([
            'reporter_id' => Auth::id(),
            'product_id' => $request->product_id,
            'reason' => $request->reason,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        $report->load(['reporter', 'product.user']);

        // Warn the seller immediately via chat, from the platform's system admin
        // voice (the reporter obviously isn't the sender, and no specific admin
        // has acted yet at this point).
        $systemAdmin = User::where('role', 'super_admin')->first();
        if ($systemAdmin) {
            $this->sendModerationChat(
                $report,
                $systemAdmin,
                "⚠️ Produk Anda \"{$report->product->nama_barang}\" telah dilaporkan oleh pengguna lain.\n\nAlasan: {$report->reason}"
                    . ($report->description ? "\nDetail: {$report->description}" : '')
                    . "\n\nMohon periksa kembali produk Anda agar sesuai dengan ketentuan platform. Tim kami akan segera meninjau laporan ini."
            );
        }

        \App\Jobs\NotifyAdminsOfNewReportJob::dispatchAfterResponse($report);

        return response()->json([
            'message' => 'Report submitted successfully',
            'data' => $report
        ], 201);
    }

    /**
     * Display the specified report.
     */
    public function show(Request $request, Report $report): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $report->load(['reporter', 'product.user', 'product.category']);
        $this->normalizeProductPhoto($report);

        return response()->json([
            'data' => $report
        ]);
    }

    /**
     * Update the specified report status in storage.
     */
    public function update(Request $request, Report $report): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,investigated,resolved,dismissed',
        ]);

        $report->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Report status updated successfully',
            'data' => $report
        ]);
    }

    /**
     * Reject a report: the reported product is left untouched, report is dismissed.
     */
    public function reject(Request $request, Report $report): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $report->update(['status' => 'dismissed']);

        return response()->json([
            'message' => 'Laporan berhasil ditolak',
            'data' => $report->load(['reporter', 'product.user', 'product.category']),
        ]);
    }

    /**
     * Accept a report by deleting the reported product; the report is kept as
     * a resolved record (product_id becomes null once the product is gone).
     */
    public function deleteReportedProduct(Request $request, Report $report): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($report->product) {
            // Sent while the product still exists so product_id stays valid at
            // insert time; the chat row survives the delete below (product_id
            // just becomes null — see the chats FK migration) so the seller keeps
            // a permanent record of why their listing was removed.
            $this->sendModerationChat(
                $report,
                $request->user(),
                "⚠️ Produk Anda \"{$report->product->nama_barang}\" telah dihapus oleh Admin karena melanggar ketentuan platform.\n\nAlasan pelaporan: {$report->reason}"
                    . ($report->description ? "\nDetail: {$report->description}" : '')
                    . "\n\nJika Anda merasa ini kesalahan, silakan balas pesan ini untuk menghubungi Admin."
            );

            $report->product->delete();
        }

        $report->update(['status' => 'resolved']);

        return response()->json([
            'message' => 'Produk berhasil dihapus dan laporan diterima',
            'data' => $report->fresh()->load(['reporter', 'product.user', 'product.category']),
        ]);
    }

    /**
     * Get users who have received 3 or more reports across all their products.
     */
    public function frequentViolators(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::withCount('receivedReports')
            ->having('received_reports_count', '>=', 3)
            ->orderByDesc('received_reports_count')
            ->get();

        $users->each(function (User $u) {
            $u->avatar = str_starts_with((string)$u->avatar, 'http') ? $u->avatar : ($u->avatar ? '/api/storage/' . $u->avatar : null);
        });

        return response()->json([
            'data' => $users
        ]);
    }
}
