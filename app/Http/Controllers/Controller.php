<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function operationalDateFilter(Request $request, string $field = 'fecha'): ?string
    {
        if (! $request->has($field)) {
            return today()->toDateString();
        }

        $date = $request->string($field)->toString();

        if ($date === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return today()->toDateString();
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year) ? $date : today()->toDateString();
    }
}
