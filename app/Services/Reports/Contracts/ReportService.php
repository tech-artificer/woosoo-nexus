<?php
namespace App\Services\Reports\Contracts;


use App\Http\Requests\ReportQueryRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;


interface ReportService
{
    /** @return array{0: array<int, array>, 1: array} */
    public function list(ReportQueryRequest $request): array;
    public function exportCsv(ReportQueryRequest $request): StreamedResponse;
}

