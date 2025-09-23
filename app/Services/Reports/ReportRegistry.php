<?php
namespace App\Services\Reports;


use App\Services\Reports\Contracts\ReportService;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;


class ReportRegistry
{
    public function __construct(protected Container $app) {}


    public function resolve(string $type): ReportService
    {
        return match ($type) {
            'sales' => $this->app->make(SalesReport::class),
            default => throw new InvalidArgumentException('Unknown report type'),
        };
    }
}