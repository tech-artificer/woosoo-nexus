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
        $instance = match ($type) {
            'sales' => $this->app->make(SalesReport::class),
            default => throw new InvalidArgumentException("Unknown report type: {$type}"),
        };

        if (! $instance instanceof ReportService) {
            $given = is_object($instance) ? get_class($instance) : gettype($instance);
            throw new InvalidArgumentException("Resolved report for type '{$type}' must implement " . ReportService::class . ", got {$given}");
        }

        return $instance;
    }
}