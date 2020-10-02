<?php

namespace Amidesfahani\NovaJalaliMetrics\JalaliTrend;

use DateTime;
use Carbon\Carbon;
use Laravel\Nova\Nova;
use Cake\Chronos\Chronos;
use Morilog\Jalali\Jalalian;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Metrics\TrendDateExpressionFactory;

trait JalaliTrend
{
    protected function aggregate($request, $model, $unit, $function, $column, $dateColumn = null)
    {
        $query = $model instanceof Builder ? $model : (new $model)->newQuery();

        $timezone = Nova::resolveUserTimezone($request) ?? $request->timezone;

        $expression = (string) \Laravel\Nova\Metrics\TrendDateExpressionFactory::make(
            $query, $dateColumn = $dateColumn ?? $query->getModel()->getCreatedAtColumn(),
            $unit, $timezone
        );

        $possibleDateResults = $this->getAllPossibleDateResults(
            $startingDate = $this->getAggregateStartingDate($request, $unit),
            $endingDate = Chronos::now(),
            $unit,
            $timezone,
            $request->twelveHourTime === 'true'
        );

        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

        $results = $query
                ->select(DB::raw("{$expression} as date_result, {$function}({$wrappedColumn}) as aggregate"))
                ->whereBetween($dateColumn, [$startingDate, $endingDate])
                ->groupBy(DB::raw($expression))
                ->orderBy('date_result')
                ->get();

        $results = array_merge($possibleDateResults, $results->mapWithKeys(function ($result) use ($request, $unit) {
            return [$this->formatAggregateResultDate(
                $result->date_result, $unit, $request->twelveHourTime === 'true'
            ) => round($result->aggregate, $this->precision)];
        })->all());

        if (count($results) > $request->range) {
            array_shift($results);
        }

        return $this->result()->trend(
            $results
        );
    }

    protected function formatAggregateResultDate($result, $unit, $twelveHourTime)
    {
        switch ($unit) {
            case 'month':
                return $this->formatAggregateMonthDate($result);

            case 'week':
                return $this->formatAggregateWeekDate($result);

            case 'day':
                // (!) resets time to 00:00:00
                // $date = Carbon::createFromFormat("!Y-m-d", $result);
                return with(Jalalian::fromCarbon(Carbon::createFromFormat('Y-m-d', $result)), function ($date) {
                    return __($date->format('j')).' '.$date->format('F').' '.$date->format('Y');
                });

            case 'hour':
                return with(Jalalian::fromCarbon(Carbon::createFromFormat('Y-m-d H:00', $result)), function ($date) use ($twelveHourTime) {
                    return $twelveHourTime
                            ? __($date->format('F')).' '.$date->format('j').' - '.$date->format('g:00 A')
                            : __($date->format('F')).' '.$date->format('j').' - '.$date->format('G:00');
                });

            case 'minute':
                return with(Jalalian::fromCarbon(Carbon::createFromFormat('Y-m-d H:i:00', $result)), function ($date) use ($twelveHourTime) {
                    return $twelveHourTime
                            ? __($date->format('F')).' '.$date->format('j').' - '.$date->format('g:i A')
                            : __($date->format('F')).' '.$date->format('j').' - '.$date->format('G:i');
                });
        }
    }

    /**
     * Format the aggregate month result date into a proper string.
     *
     * @param  string  $result
     * @return string
     */
    protected function formatAggregateMonthDate($result)
    {
        [$year, $month] = explode('-', $result);

        return with(Jalalian::fromCarbon(Carbon::create((int) $year, (int) $month, 1)), function ($date) {
            return __($date->format('F')).' '.$date->format('Y');
        });
    }

    /**
     * Format the aggregate week result date into a proper string.
     *
     * @param  string  $result
     * @return string
     */
    protected function formatAggregateWeekDate($result)
    {
        [$year, $week] = explode('-', $result);

        $isoDate = (new DateTime)->setISODate($year, $week)->setTime(0, 0);

        [$startingDate, $endingDate] = [
            Jalalian::fromCarbon(Carbon::instance($isoDate)),
            Jalalian::fromCarbon(Carbon::instance($isoDate)->endOfWeek()),
        ];

        return __($startingDate->format('F')).' '.$startingDate->format('j').' - '.
               __($endingDate->format('F')).' '.$endingDate->format('j');
    }

    /**
     * Format the possible aggregate result date into a proper string.
     *
     * @param  \Cake\Chronos\Chronos  $date
     * @param  string  $unit
     * @param  bool  $twelveHourTime
     * @return string
     */
    protected function formatPossibleAggregateResultDate(Chronos $date, $unit, $twelveHourTime)
    {
        $date = Jalalian::fromCarbon(Carbon::createFromDate((string)$date));
        switch ($unit) {
            case 'month':
                return __($date->format('F')).' '.$date->format('Y');

            case 'week':
                return __($date->startOfWeek()->format('F')).' '.$date->startOfWeek()->format('j').' - '.
                       __($date->endOfWeek()->format('F')).' '.$date->endOfWeek()->format('j');

            case 'day':
                return __($date->format('j')).' '.$date->format('F').' '.$date->format('Y');

            case 'hour':
                return $twelveHourTime
                        ? __($date->format('F')).' '.$date->format('j').' - '.$date->format('g:00 A')
                        : __($date->format('F')).' '.$date->format('j').' - '.$date->format('G:00');

            case 'minute':
                return $twelveHourTime
                        ? __($date->format('F')).' '.$date->format('j').' - '.$date->format('g:i A')
                        : __($date->format('F')).' '.$date->format('j').' - '.$date->format('G:i');
        }
    }
}
