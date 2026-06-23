<?php

namespace App\Http\Controllers;

use App\Services\SalesReportPresenter;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SalesReportController extends Controller
{
    public function __construct(
        private SalesReportService $reportService,
        private SalesReportPresenter $presenter,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'sometimes|in:pending,processing,completed,failed',
            'from'     => 'sometimes|date_format:Y-m-d',
            'to'       => 'sometimes|date_format:Y-m-d|after_or_equal:from',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $reports = $this->reportService->getReports($request->only([
            'status', 'from', 'to', 'per_page',
        ]));

        return response()->json([
            'success' => true,
            'data'    => $reports,
        ]);
    }

    public function show(Request $request, string $date): JsonResponse|View|Response
    {
        return $this->presenter->show($request, $date);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date   = $request->input('date');
        $report = $this->reportService->generateReport($date);

        return response()->json([
            'success' => true,
            'message' => "Report generation started for {$date}. "
                       . "Status will update as batch jobs complete.",
            'data'    => $report,
            'nfr_note' => [
                'nfr_4_batch'  => 'GenerateDailyReportJob dispatched via Bus::batch()',
                'nfr_3_async'  => 'Processing happens in background queue workers',
                'flag_status'  => config('performance.use_batch_processing')
                    ? 'Batch mode ON — parallel chunk processing'
                    : 'Batch mode OFF — synchronous single-thread processing',
            ],
        ], 202);
    }

    public function retryFailed(): JsonResponse
    {
        $retriedCount = $this->reportService->retryFailedReports();

        return response()->json([
            'success' => true,
            'message' => "Re-queued {$retriedCount} failed report(s) for processing.",
            'data'    => ['retried_count' => $retriedCount],
        ]);
    }

    public function summary(Request $request): JsonResponse|View|Response
    {
        return $this->presenter->summary($request);
    }
}
