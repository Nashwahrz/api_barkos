<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Display a listing of reports (Super Admin Only).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reports = Report::with(['reporter', 'product.user', 'product.category'])->latest()->get();

        return response()->json([
            'data' => $reports
        ]);
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

        return response()->json([
            'data' => $report->load(['reporter', 'product.user', 'product.category'])
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
}
