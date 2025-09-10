<?php

namespace App\Services\Reports;


use App\Http\Requests\ReportQueryRequest;
use App\Services\Reports\Contracts\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;


class SalesReport implements ReportService
{
    protected array $allowedSorts = ['date', 'total', 'transactions'];


    public function list(ReportQueryRequest $request): array
    {
        $query = $this->getQuery($request);
        $queryClone = clone $query;
        $total = $queryClone->count();
        $page = max(1, (int)$request->input('page', 1));
        $perPage = max(1, min(500, (int)$request->input('perPage', 25)));
        $rows = $query->forPage($page, $perPage)->get()->map(function ($row) {
            return [
                'date' => $row->date,
                'total' => (float)$row->total,
                'transactions' => (int)$row->transactions,
            ];
        })->toArray();
        $meta = [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => (int)ceil($total / $perPage),
            'sortBy' => $request->input('sortBy'),
            'sortDir' => $request->input('sortDir'),
        ];
        return [$rows, $meta];
    }


    public function exportCsv(ReportQueryRequest $request): StreamedResponse
    {
        [$rows, ] = $this->list($request);
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales-report.csv"',
        ];
        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'total', 'transactions']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['date'], $row['total'], $row['transactions']]);
            }
            fclose($out);
        }, 200, $headers);
    }


    protected function getQuery(ReportQueryRequest $request): Builder
    {
        $query = DB::table('sales_report')
            ->selectRaw('date, SUM(total) as total, COUNT(*) as transactions')
            ->groupBy('date');
        if ($request->input('q')) {
            $query->where('date', 'like', '%' . $request->input('q') . '%');
        }
        foreach ($request->input('filters', []) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (!in_array($key, $this->allowedSorts, true)) {
                continue;
            }
            $query->where($key, 'like', '%' . $value . '%');
        }
        if ($request->input('sortBy') && in_array($request->input('sortBy'), $this->allowedSorts, true)) {
            $query->orderBy($request->input('sortBy'), $request->input('sortDir') === 'desc' ? 'desc' : 'asc');
        }
        return $query;
    }
}
