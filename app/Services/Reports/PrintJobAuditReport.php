<?php

namespace App\Services\Reports;

use App\Http\Requests\ReportQueryRequest;
use App\Models\DeviceOrder;

class PrintJobAuditReport
{
    public function getData(ReportQueryRequest $request): array
    {
        $startDate = $request->input('start_date') ?? now()->subDays(7)->toDateString();
        $endDate = $request->input('end_date') ?? now()->toDateString();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 50);

        // Use Eloquent model for cleaner query
        $query = DeviceOrder::query()
            ->whereBetween('printed_at', [
                now()->parse($startDate)->startOfDay(),
                now()->parse($endDate)->endOfDay(),
            ])
            ->whereNotNull('printed_at')
            ->where('is_printed', true)
            ->select('id', 'order_number', 'printed_by', 'printed_at', 'status', 'total', 'branch_id', 'device_id');

        $total = $query->count();
        $data = $query->orderByDesc('printed_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'order_number' => $row->order_number,
                'printed_by' => $row->printed_by,
                'printed_at' => $row->printed_at,
                'status' => $row->status,
                'total' => (float) $row->total,
                'branch_id' => $row->branch_id,
                'device_id' => $row->device_id,
            ])
            ->toArray();

        $meta = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        return [$data, $meta];
    }
}
