<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\GenerateComplianceReportRequest;
use App\Services\Enterprise\AuditComplianceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AuditComplianceController extends Controller
{
    protected AuditComplianceService $auditService;

    public function __construct(AuditComplianceService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Query audit logs.
     */
    public function queryLogs(Request $request): JsonResponse
    {
        $filters = $request->only([
            'event_type', 'severity', 'resource_type', 'resource_id',
            'start_date', 'end_date', 'severity_min', 'risk_score_min',
            'compliance_tags', 'search', 'user_id', 'tenant_id',
        ]);

        $pagination = $request->only(['page', 'per_page', 'sort_by', 'sort_order']);

        return response()->json($this->auditService->queryLogs($filters, $pagination));
    }

    /**
     * Generate compliance report.
     */
    public function generateReport(GenerateComplianceReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $report = $this->auditService->generateComplianceReport(
            $validated['framework'],
            $startDate,
            $endDate,
            $validated['options'] ?? []
        );

        return response()->json([
            'message' => 'Compliance report generated',
            'data' => $report,
        ]);
    }

    /**
     * Get audit statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $startDate = $request->has('start_date') ? Carbon::parse($request->start_date) : null;
        $endDate = $request->has('end_date') ? Carbon::parse($request->end_date) : null;

        $stats = $this->auditService->getStatistics($startDate, $endDate);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Export audit trail.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'in:csv,json',
            'filters' => 'nullable|array',
        ]);

        $filters = $request->input('filters', []);
        $format = $request->input('format', 'csv');

        $content = $this->auditService->exportAuditTrail($filters, $format);

        $filename = 'audit-trail-' . now()->format('Y-m-d-H-i-s') . ($format === 'csv' ? '.csv' : '.json');
        
        return response($content)
            ->header('Content-Type', $format === 'csv' ? 'text/csv' : 'application/json')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Archive old logs.
     */
    public function archive(): JsonResponse
    {
        $archived = $this->auditService->archiveOldLogs();

        return response()->json([
            'message' => 'Audit logs archived',
            'archived_count' => $archived,
        ]);
    }

    /**
     * Get high-risk events.
     */
    public function getHighRiskEvents(Request $request): JsonResponse
    {
        $filters = $request->only([
            'start_date', 'end_date', 'user_id', 'resource_type',
        ]);
        
        $filters['risk_score_min'] = 70;
        
        $pagination = $request->only(['page', 'per_page', 'sort_by', 'sort_order']);
        $pagination['sort_by'] = $pagination['sort_by'] ?? 'risk_score';
        $pagination['sort_order'] = $pagination['sort_order'] ?? 'desc';

        return response()->json($this->auditService->queryLogs($filters, $pagination));
    }

    /**
     * Get security events.
     */
    public function getSecurityEvents(Request $request): JsonResponse
    {
        $filters = $request->only([
            'start_date', 'end_date', 'user_id', 'severity',
        ]);
        
        $filters['category'] = 'security';
        
        $pagination = $request->only(['page', 'per_page', 'sort_by', 'sort_order']);

        return response()->json($this->auditService->queryLogs($filters, $pagination));
    }

    /**
     * Get data access events.
     */
    public function getDataAccessEvents(Request $request): JsonResponse
    {
        $filters = $request->only([
            'start_date', 'end_date', 'user_id', 'resource_type', 'resource_id',
        ]);
        
        $filters['category'] = 'data_access';
        
        $pagination = $request->only(['page', 'per_page', 'sort_by', 'sort_order']);

        return response()->json($this->auditService->queryLogs($filters, $pagination));
    }

    /**
     * Get data modification events.
     */
    public function getDataModificationEvents(Request $request): JsonResponse
    {
        $filters = $request->only([
            'start_date', 'end_date', 'user_id', 'resource_type', 'resource_id',
        ]);
        
        $filters['category'] = 'data_modification';
        
        $pagination = $request->only(['page', 'per_page', 'sort_by', 'sort_order']);

        return response()->json($this->auditService->queryLogs($filters, $pagination));
    }
}
