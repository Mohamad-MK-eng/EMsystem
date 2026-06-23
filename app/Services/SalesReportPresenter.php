<?php

namespace App\Services;

use App\Models\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prepares sales report responses for HTML or JSON without touching
 * generation, chunking, or dispatch logic.
 */
class SalesReportPresenter
{
    public function __construct(private SalesReportService $reportService)
    {
    }

    public function wantsJson(Request $request): bool
    {
        return $request->query('format') === 'json';
    }

    public function show(Request $request, string $date): JsonResponse|View|Response
    {
        if (!strtotime($date)) {
            return $this->errorResponse(
                $request,
                'Invalid date format. Use Y-m-d (e.g. 2026-06-09).',
                422
            );
        }

        $report = $this->reportService->getReport($date);

        if (!$report) {
            return $this->errorResponse(
                $request,
                "No report found for {$date}.",
                404
            );
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'data'    => $report,
            ]);
        }

        return view('reports.daily', [
            'report' => $report,
            'date'   => $date,
        ]);
    }

    public function summary(Request $request): JsonResponse|View|Response
    {
        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to'   => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);

        $from = $request->input('from');
        $to   = $request->input('to');

        $summary   = $this->reportService->getSummary($from, $to);
        $breakdown = $this->reportService->getDailyBreakdown($from, $to);

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'data'    => array_merge($summary, ['daily_breakdown' => $breakdown]),
            ]);
        }

        return view('reports.summary', [
            'summary'   => $summary,
            'breakdown' => $breakdown,
            'from'      => $from,
            'to'        => $to,
        ]);
    }

    private function errorResponse(Request $request, string $message, int $status): JsonResponse|View
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return view('reports.error', [
            'message' => $message,
            'status'  => $status,
        ], $status);
    }
}
