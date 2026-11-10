<?php

namespace App\Modules\Companies\Http\Middleware;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the {company} route parameter and verifies that the
 * authenticated user belongs to it. Non-members get 404 so that the existence of
 * other companies is not disclosed.
 */
class EnsureCompanyMember
{
    public function __construct(private readonly CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->route('company');

        if (! $company instanceof Company) {
            throw new LogicException('company.member middleware requires a {company} route parameter bound to Company.');
        }

        $membership = $request->user()?->membershipIn($company);

        abort_if($membership === null, Response::HTTP_NOT_FOUND);

        $this->current->set($company, $membership);

        return $next($request);
    }
}
