<?php

namespace App\Domain\Companies;

use App\Models\Company;

class CompanyService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Company
    {
        return Company::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        return $company->refresh();
    }

    public function delete(Company $company): void
    {
        $company->delete();
    }
}
