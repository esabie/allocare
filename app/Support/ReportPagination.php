<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportPagination
{
    public const DEFAULT_PER_PAGE = 25;

    /** @var array<int, int> */
    public const PER_PAGE_OPTIONS = [25, 50, 100];

    public static function perPage(Request $request, string $param = 'per_page'): int
    {
        $perPage = (int) $request->query($param, self::DEFAULT_PER_PAGE);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : self::DEFAULT_PER_PAGE;
    }

    public static function paginateCollection(
        Collection $items,
        Request $request,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $perPage = self::perPage($request);
        $page = max(1, (int) $request->query($pageName, 1));
        $total = $items->count();

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
            ],
        ))->withQueryString();
    }
}
